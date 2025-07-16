<?php

declare(strict_types=1);

require_once 'CheckInSystem.php';
require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';
require_once 'Booking.php';

/**
 * Represents a self-service kiosk.
 */
class SelfServiceKiosk extends CheckInSystem
{
    public function __construct(
        private string $kioskId = '',
        private string $location = ''
    ) {
        parent::__construct();
    }
    
    public function displayInterface(): void
    {
        echo "Welcome to Kiosk {$this->kioskId}. Please scan your passport or enter booking reference.\n";
    }

    // 自助值机专用方法
    public function findBooking($booking_ref = '', $last_name = '', $passport_number = '') {
        // passport_number优先
        if ($passport_number) {
            $conn = $this->dbManager;
            $pdo = (new \ReflectionClass($conn))->getProperty('connection');
            $pdo->setAccessible(true);
            $pdo = $pdo->getValue($conn);
            // 1. 先查passenger表拿到passenger_id
            $stmt = $pdo->prepare("SELECT * FROM passengers WHERE passport_number = ?");
            $stmt->execute([$passport_number]);
            $passenger = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($passenger && !empty($passenger['passenger_id'])) {
                // 2. 再查booking_passengers表拿到booking_id
                $stmt2 = $pdo->prepare("SELECT booking_id FROM booking_passengers WHERE passenger_id = ? LIMIT 1");
                $stmt2->execute([$passenger['passenger_id']]);
                $bp = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($bp && !empty($bp['booking_id'])) {
                    $booking = new Booking($bp['booking_id']);
                    // 手动查bookings表并设置bookingData
                    $stmt3 = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
                    $stmt3->execute([$bp['booking_id']]);
                    $bookingData = $stmt3->fetch(PDO::FETCH_ASSOC);
                    if ($bookingData) {
                        $ref = new \ReflectionClass($booking);
                        $prop = $ref->getProperty('bookingData');
                        $prop->setAccessible(true);
                        $prop->setValue($booking, $bookingData);
                    }
                    return [
                        'booking' => $booking->getBookingData(),
                        'passengers' => $booking->getPassengers(),
                        'flight' => $booking->getFlight()
                    ];
                }
            }
        } else if ($booking_ref && $last_name) {
            $booking = new Booking($booking_ref);
            if ($booking->findBooking($last_name)) {
            return [
                'booking' => $booking->getBookingData(),
                'passengers' => $booking->getPassengers(),
                    'flight' => $booking->getFlight()
            ];
            }
        }
        return false;
    }

    public function getAvailableSeats(string $flightNumber): array
    {
        $conn = $this->dbManager;
        $pdo = (new \ReflectionClass($conn))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($conn);
        $stmt = $pdo->prepare("
            SELECT * FROM seats 
            WHERE flight_number = ? AND status = 'Available'
            ORDER BY seat_number
        ");
        $stmt->execute([$flightNumber]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllSeats(string $flightNumber): array
    {
        $conn = $this->dbManager;
        $pdo = (new \ReflectionClass($conn))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($conn);
        $stmt = $pdo->prepare("SELECT * FROM seats WHERE flight_number = ? ORDER BY `row`, `column`");
        $stmt->execute([$flightNumber]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function processSelfCheckIn(string $bookingRef, array $selectedPassengers, array $passengerSeats, array $baggageInfo, array $specialNeeds): bool
    {
        try {
            $conn = $this->dbManager;
            $pdo = (new \ReflectionClass($conn))->getProperty('connection');
            $pdo->setAccessible(true);
            $pdo = $pdo->getValue($conn);
            $pdo->beginTransaction();
            
            // 获取flight_number
            $stmt = $pdo->prepare("SELECT flight_number FROM bookings WHERE booking_id = ?");
            $stmt->execute([$bookingRef]);
            $flightNumber = $stmt->fetchColumn();
            
            foreach (
                $selectedPassengers as $passengerId
            ) {
                $seatNumber = $passengerSeats[$passengerId] ?? null;
                if ($seatNumber) {
                    // 查找seat_id
                    $stmt = $pdo->prepare("SELECT seat_id FROM seats WHERE flight_number = ? AND seat_number = ?");
                    $stmt->execute([$flightNumber, $seatNumber]);
                    $seatId = $stmt->fetchColumn();

                    // Update booking_passengers table
                    $stmt = $pdo->prepare("
                        UPDATE booking_passengers 
                        SET seat_number = ?, assigned_seat_id = ?, check_in_status = 'Checked In', 
                            additional_baggage_pieces = ?, purchased_baggage_package_id = ?
                        WHERE booking_id = ? AND passenger_id = ?
                    ");
                    $stmt->execute([
                        $seatNumber,
                        $seatId,
                        $baggageInfo['count'] ?? 0,
                        $baggageInfo['packageId'] !== '' ? $baggageInfo['packageId'] : null,
                        $bookingRef,
                        $passengerId
                    ]);
                    
                    // Update seats table
                    if ($seatId) {
                        $stmt = $pdo->prepare("UPDATE seats SET status = 'Occupied' WHERE seat_id = ?");
                        $stmt->execute([$seatId]);
                    }
                    
                    // Create boarding pass
                    $passengerObj = Passenger::loadFromDatabase($passengerId);
                    $flightObj = $this->getPassengerFlight($passengerId, $flightNumber);
                    if ($passengerObj && $flightObj) {
                        $this->createBoardingPass($passengerObj, $flightObj, $seatNumber);
                    }
                }
            }
            
            // 行李：只为owner插入且只插入一次
            if (!empty($baggageInfo['items']) && is_array($baggageInfo['items'])) {
                foreach ($baggageInfo['items'] as $item) {
                    $ownerId = $item['owner_id'] ?? null;
                    if ($ownerId && in_array($ownerId, $selectedPassengers)) {
                        $this->processBaggageForSelfCheckIn($pdo, $ownerId, $bookingRef, [
                            'weight' => $item['weight'] ?? 0,
                            'packageId' => $baggageInfo['packageId'] ?? '',
                            'special_handling' => $item['special_handling'] ?? null
                        ]);
                    }
                }
            }
            
            // 特殊需求：只为第一个乘客插入
            if (!empty($specialNeeds['needs']) && count($selectedPassengers) > 0) {
                $firstPassenger = $selectedPassengers[0];
                $this->processSpecialNeedsForSelfCheckIn($pdo, $firstPassenger, $specialNeeds);
            }
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollback();
            }
            error_log("Self check-in failed: " . $e->getMessage());
            return false;
        }
    }

    private function processBaggageForSelfCheckIn($pdo, string $passengerId, string $bookingRef, array $baggageInfo): void
    {
        $weight = floatval($baggageInfo['weight'] ?? 0);
        if ($weight > 0) {
            $packageId = $baggageInfo['packageId'] ?? null;
            $specialHandling = $baggageInfo['special_handling'] ?? null;
            $this->createBaggage($passengerId, $bookingRef, $weight, $packageId, $specialHandling);
        }
    }

    private function processSpecialNeedsForSelfCheckIn($pdo, string $passengerId, array $specialNeeds): void
    {
        foreach ($specialNeeds['needs'] as $needType) {
            $assistanceId = "ASSIST_" . $passengerId . "_" . time() . "_" . rand(1000, 9999);
            $description = $specialNeeds['notes'] ?? "Special assistance requested: " . $needType;
            $stmt = $pdo->prepare("
                INSERT INTO assistance_details (assistance_id, passenger_id, need_type, description, status)
                VALUES (?, ?, ?, ?, 'Requested')
            ");
            $stmt->execute([$assistanceId, $passengerId, $needType, $description]);
        }
    }

    // 辅助方法：通过flightNumber获取Flight对象
    private function getPassengerFlight(string $passengerId, string $flightNumber): ?Flight
    {
        $flightData = $this->getFlightInfo($flightNumber);
        if ($flightData) {
            // departure_time需为DateTime对象
            $departureTime = $flightData['departure_time'] instanceof DateTime
                ? $flightData['departure_time']
                : new DateTime($flightData['departure_time']);
            return new Flight(
                $flightData['flight_number'],
                $departureTime,
                $flightData['destination'],
                $flightData['gate'],
                $flightData['status'],
                $flightData['capacity']
            );
        }
        return null;
    }
}