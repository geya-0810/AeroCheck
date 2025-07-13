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
    public function findBooking(string $bookingRef, string $lastName): ?array
    {
        $booking = new Booking($bookingRef);
        if ($booking->findBooking($lastName)) {
            return [
                'booking' => $booking->getBookingData(),
                'passengers' => $booking->getPassengers(),
                'flight' => $booking->getFlight(),
                'availableSeats' => $booking->getAvailableSeats(),
                'baggagePackages' => $booking->getBaggagePackages()
            ];
        }
        return null;
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
            
            foreach ($selectedPassengers as $passengerId) {
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
                    $this->createBoardingPass($pdo, $passengerId, $bookingRef, $seatNumber);
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

    private function createBoardingPass($pdo, string $passengerId, string $bookingRef, string $seatNumber): void
    {
        $stmt = $pdo->prepare("SELECT flight_number FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingRef]);
        $flightNumber = $stmt->fetchColumn();
        $qrCode = "BP_" . $passengerId . "_" . $bookingRef . "_" . time();
        $stmt = $pdo->prepare("
            INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, qr_code, issue_datetime)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$passengerId, $flightNumber, $bookingRef, $seatNumber, $qrCode]);
    }

    private function processBaggageForSelfCheckIn($pdo, string $passengerId, string $bookingRef, array $baggageInfo): void
    {
        $weight = floatval($baggageInfo['weight'] ?? 0);
        if ($weight > 0) {
            $baggageId = "BAG_" . $passengerId . "_" . time() . "_" . rand(1000, 9999);
            $baggageTag = "BT" . substr($passengerId, 0, 4) . strtoupper(uniqid());
            // $baggageTag = "TAG_" . $passengerId . "_" . time();
            $stmt = $pdo->prepare("
                INSERT INTO baggage (baggage_id, passenger_id, booking_id, weight_kg, baggage_tag, screening_status, package_id, special_handling)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->execute([
                $baggageId,
                $passengerId,
                $bookingRef,
                $weight,
                $baggageTag,
                $baggageInfo['packageId'] !== '' ? $baggageInfo['packageId'] : null,
                $baggageInfo['special_handling'] ?? null
            ]);
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
}