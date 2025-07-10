<?php

declare(strict_types=1);

require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';
require_once 'DatabaseManager.php';
require_once 'Booking.php';

/**
 * The central system that orchestrates the check-in process with database integration.
 */
class CheckInSystem
{
    private DatabaseManager $dbManager;
    
    public function __construct()
    {
        $this->dbManager = new DatabaseManager();
        echo "Check-In System is online and connected to database.\n";
    }

    /**
     * Find booking by reference and last name (for self check-in)
     */
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

    /**
     * Get available seats for a flight
     */
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

    /**
     * Get baggage packages
     */
    public function getBaggagePackages(): array
    {
        $conn = $this->dbManager;
        $pdo = (new \ReflectionClass($conn))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($conn);
        
        $stmt = $pdo->query("SELECT * FROM baggage_packages ORDER BY additional_weight_kg");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process self check-in for passengers
     */
    public function processSelfCheckIn(string $bookingRef, array $selectedPassengers, array $passengerSeats, array $baggageInfo, array $specialNeeds): bool
    {
        try {
            $conn = $this->dbManager;
            $pdo = (new \ReflectionClass($conn))->getProperty('connection');
            $pdo->setAccessible(true);
            $pdo = $pdo->getValue($conn);
            
            $pdo->beginTransaction();
            
            // Update passenger seats and check-in status
            foreach ($selectedPassengers as $passengerId) {
                $seatNumber = $passengerSeats[$passengerId] ?? null;
                if ($seatNumber) {
                    // Update booking_passengers table
                    $stmt = $pdo->prepare("
                        UPDATE booking_passengers 
                        SET seat_number = ?, check_in_status = 'Checked In', 
                            additional_baggage_pieces = ?, purchased_baggage_package_id = ?
                        WHERE booking_id = ? AND passenger_id = ?
                    ");
                    $stmt->execute([
                        $seatNumber,
                        $baggageInfo['count'] ?? 0,
                        $baggageInfo['packageId'] ?? null,
                        $bookingRef,
                        $passengerId
                    ]);
                    
                    // Update seat status to occupied
                    $stmt = $pdo->prepare("
                        UPDATE seats 
                        SET status = 'Occupied' 
                        WHERE flight_number = (SELECT flight_number FROM bookings WHERE booking_id = ?) 
                        AND seat_number = ?
                    ");
                    $stmt->execute([$bookingRef, $seatNumber]);
                    
                    // Create boarding pass
                    $this->createBoardingPass($pdo, $passengerId, $bookingRef, $seatNumber);
                }
            }
            
            // Process special needs
            if (!empty($specialNeeds['needs'])) {
                foreach ($selectedPassengers as $passengerId) {
                    $this->processSpecialNeedsForSelfCheckIn($pdo, $passengerId, $specialNeeds);
                }
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

    /**
     * Create boarding pass for passenger
     */
    private function createBoardingPass($pdo, string $passengerId, string $bookingRef, string $seatNumber): void
    {
        // Get flight number from booking
        $stmt = $pdo->prepare("SELECT flight_number FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingRef]);
        $flightNumber = $stmt->fetchColumn();
        
        // Generate QR code (simplified)
        $qrCode = "BP_" . $passengerId . "_" . $bookingRef . "_" . time();
        
        // Insert boarding pass
        $stmt = $pdo->prepare("
            INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, qr_code, issue_datetime)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$passengerId, $flightNumber, $bookingRef, $seatNumber, $qrCode]);
    }

    /**
     * Process special needs for passenger (self check-in)
     */
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

    /**
     * Main entry point to check in a passenger.
     */
    public function checkInPassenger(Passenger $passenger, Flight $flight, string $seat): BoardingPass
    {
        // Save passenger and flight to database
        $this->dbManager->savePassenger($passenger);
        $this->dbManager->saveFlight($flight);
        
        if (!$this->isCheckInAvailable($flight)) {
            throw new \Exception("Check-in for flight {$flight->getFlightNumber()} is not available.");
        }
        
        echo "System: Processing check-in for passenger {$passenger->getName()}.\n";
        
        // Process special needs if any
        $this->processSpecialNeeds($passenger);
        
        // Create boarding pass
        $boardingPass = new BoardingPass($passenger->getName(), $seat, $flight, new \DateTime());
        
        // Save boarding pass to database
        $this->dbManager->saveBoardingPass($boardingPass, $passenger->getPassengerId(), $flight->getFlightNumber());
        
        echo "System: Check-in completed for passenger {$passenger->getName()}. Seat: {$seat}\n";
        
        return $boardingPass;
    }

    /**
     * Process special needs for a passenger.
     */
    private function processSpecialNeeds(Passenger $passenger): void
    {
        $details = $passenger->getAssistanceDetails();
        if ($details !== null) {
            echo "System: Processing special needs request for {$passenger->getName()}.\n";
            $details->updateStatus('Processed by System');
            // Save updated assistance details
            $this->dbManager->saveAssistanceDetails($details, $passenger->getPassengerId());
        }
    }

    /**
     * Process check-in for a group.
     */
    public function processCheckInGroup(Group $group, Flight $flight): void
    {
        echo "System: Processing group check-in for group ID {$group->getGroupDetails()['groupId']}.\n";
        
        // Save group to database
        $this->dbManager->saveGroup($group);
        
        $seatChar = 65; // 'A'
        $seatRow = 25;
        
        foreach ($group->getPassengers() as $passenger) {
            $seat = $seatRow . chr($seatChar++);
            $this->checkInPassenger($passenger, $flight, $seat);
            
            if ($seatChar > 70) { // 'F'
                $seatChar = 65;
                $seatRow++;
            }
        }
        
        echo "System: Group check-in completed for {$group->getGroupDetails()['member_count']} passengers.\n";
    }
    
    /**
     * Process baggage check-in.
     */
    public function processBaggage(Baggage $baggage): void
    {
        echo "System: Processing baggage {$baggage->getTrackingInfo()['baggageId']}.\n";
        
        $baggage->checkInBaggage();
        $baggage->updateScreeningStatus('In Transit to Screening');
        
        // Save baggage to database
        $this->dbManager->saveBaggage($baggage);
    }

    /**
     * Checks if check-in is open for a given flight.
     */
    public function isCheckInAvailable(Flight $flight): bool
    {
        $status = $flight->getFlightDetails()['status'];
        return $status !== 'Departed' && $status !== 'Closed';
    }
    
    /**
     * Get all passengers from database.
     */
    public function getAllPassengers(): array
    {
        return $this->dbManager->getAllPassengers();
    }
    
    /**
     * Get all flights from database.
     */
    public function getAllFlights(): array
    {
        return $this->dbManager->getAllFlights();
    }
    
    /**
     * Get passenger by ID from database.
     */
    public function getPassengerById(string $passengerId): ?Passenger
    {
        return Passenger::loadFromDatabase($passengerId);
    }
    
    /**
     * Get flight information from database.
     */
    public function getFlightInfo(string $flightNumber): ?array
    {
        return $this->dbManager->getFlight($flightNumber);
    }
    
    /**
     * Send notification to passenger about flight status.
     */
    public function sendFlightNotification(string $passengerId, string $flightNumber, string $message): void
    {
        $this->dbManager->saveFlightNotification($passengerId, $flightNumber, $message);
        echo "System: Flight notification sent to passenger {$passengerId} for flight {$flightNumber}.\n";
    }
    
    /**
     * Generate system report.
     */
    public function generateSystemReport(): array
    {
        $passengers = $this->getAllPassengers();
        $flights = $this->getAllFlights();
        
        return [
            'total_passengers' => count($passengers),
            'total_flights' => count($flights),
            'system_status' => 'Online',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}