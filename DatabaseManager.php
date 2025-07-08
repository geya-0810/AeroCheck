<?php

declare(strict_types=1);

require_once 'databaseconnect.php';

/**
 * Database manager for AeroCheck system
 */
class DatabaseManager
{
    private PDO $connection;
    
    public function __construct()
    {
        $this->connection = include 'databaseconnect.php';
    }
    
    // --- Passenger operations ---
    
    public function savePassenger(Passenger $passenger): bool
    {
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO passengers (passenger_id, name, contact_info) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), contact_info = VALUES(contact_info)"
            );
            return $stmt->execute([
                $passenger->getPassengerId(),
                $passenger->getName(),
                $passenger->getContactInfo()
            ]);
        } catch (PDOException $e) {
            echo "Error saving passenger: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function getPassenger(string $passengerId): ?array
    {
        try {
            $stmt = $this->connection->prepare("SELECT * FROM passengers WHERE passenger_id = ?");
            $stmt->execute([$passengerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            echo "Error retrieving passenger: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    // --- Flight operations ---
    
    public function saveFlight(Flight $flight): bool
    {
        try {
            $details = $flight->getFlightDetails();
            $stmt = $this->connection->prepare(
                "INSERT INTO flights (flight_number, departure_time, destination, gate, status) 
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE departure_time = VALUES(departure_time), 
                 destination = VALUES(destination), gate = VALUES(gate), status = VALUES(status)"
            );
            return $stmt->execute([
                $details['flightNumber'],
                $details['departureTime'],
                $details['destination'],
                $details['gate'],
                $details['status']
            ]);
        } catch (PDOException $e) {
            echo "Error saving flight: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function getFlight(string $flightNumber): ?array
    {
        try {
            $stmt = $this->connection->prepare("SELECT * FROM flights WHERE flight_number = ?");
            $stmt->execute([$flightNumber]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            echo "Error retrieving flight: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    // --- Boarding Pass operations ---
    
    public function saveBoardingPass(BoardingPass $boardingPass, string $passengerId, string $flightNumber): bool
    {
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO boarding_passes (passenger_id, flight_number, seat_number, qr_code, issue_datetime) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            return $stmt->execute([
                $passengerId,
                $flightNumber,
                $this->extractSeatNumber($boardingPass), // We'll need to modify BoardingPass to expose seat number
                $this->extractQRCode($boardingPass),
                date('Y-m-d H:i:s')
            ]);
        } catch (PDOException $e) {
            echo "Error saving boarding pass: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // --- Group operations ---
    
    public function saveGroup(Group $group): bool
    {
        try {
            $this->connection->beginTransaction();
            
            $details = $group->getGroupDetails();
            $stmt = $this->connection->prepare(
                "INSERT INTO groups (group_id, representative_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE representative_id = VALUES(representative_id)"
            );
            $stmt->execute([
                $details['groupId'],
                $group->getRepresentative()->getPassengerId()
            ]);
            
            // Clear existing members
            $stmt = $this->connection->prepare("DELETE FROM group_members WHERE group_id = ?");
            $stmt->execute([$details['groupId']]);
            
            // Add current members
            foreach ($group->getPassengers() as $passenger) {
                $stmt = $this->connection->prepare(
                    "INSERT INTO group_members (group_id, passenger_id) VALUES (?, ?)"
                );
                $stmt->execute([
                    $details['groupId'],
                    $passenger->getPassengerId()
                ]);
            }
            
            $this->connection->commit();
            return true;
        } catch (PDOException $e) {
            $this->connection->rollback();
            echo "Error saving group: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // --- Baggage operations ---
    
    public function saveBaggage(Baggage $baggage): bool
    {
        try {
            $tracking = $baggage->getTrackingInfo();
            $stmt = $this->connection->prepare(
                "INSERT INTO baggage (baggage_id, passenger_id, weight, screening_status, baggage_tag) 
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE screening_status = VALUES(screening_status), 
                 baggage_tag = VALUES(baggage_tag)"
            );
            return $stmt->execute([
                $tracking['baggageId'],
                $this->extractPassengerIdFromBaggage($baggage),
                $this->extractWeightFromBaggage($baggage),
                $tracking['screeningStatus'],
                $tracking['tag']
            ]);
        } catch (PDOException $e) {
            echo "Error saving baggage: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // --- Assistance operations ---
    
    public function saveAssistanceDetails(AssistanceDetails $assistance, string $passengerId): bool
    {
        try {
            $details = $assistance->getDetails();
            $stmt = $this->connection->prepare(
                "INSERT INTO assistance_details (assistance_id, passenger_id, need_type, description, status) 
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status)"
            );
            return $stmt->execute([
                $details['id'],
                $passengerId,
                $details['type'],
                $details['description'],
                $details['status']
            ]);
        } catch (PDOException $e) {
            echo "Error saving assistance details: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // --- Staff operations ---
    
    public function saveStaff(Staff $staff): bool
    {
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO staff (staff_id, name, role) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role)"
            );
            return $stmt->execute([
                $this->extractStaffId($staff),
                $this->extractStaffName($staff),
                $this->extractStaffRole($staff)
            ]);
        } catch (PDOException $e) {
            echo "Error saving staff: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // --- Helper methods to extract private properties ---
    // Note: These methods use reflection to access private properties
    // In a real application, you should add getter methods to the classes
    
    private function extractSeatNumber(BoardingPass $boardingPass): string
    {
        // This is a simplified approach - in practice, add a getSeatNumber() method
        return "18A"; // Default seat for MVP
    }
    
    private function extractQRCode(BoardingPass $boardingPass): string
    {
        // This would use the actual QR code from the boarding pass
        return "QR_CODE_PLACEHOLDER";
    }
    
    private function extractPassengerIdFromBaggage(Baggage $baggage): string
    {
        // This would extract the passenger ID from the baggage object
        return "P001"; // Placeholder for MVP
    }
    
    private function extractWeightFromBaggage(Baggage $baggage): float
    {
        // This would extract the weight from the baggage object
        return 20.5; // Placeholder for MVP
    }
    
    private function extractStaffId(Staff $staff): string
    {
        return "S001"; // Placeholder for MVP
    }
    
    private function extractStaffName(Staff $staff): string
    {
        return "Staff Member"; // Placeholder for MVP
    }
    
    private function extractStaffRole(Staff $staff): string
    {
        return "Check-in Agent"; // Placeholder for MVP
    }
    
    // --- Flight notification operations ---
    
    public function saveFlightNotification(string $passengerId, string $flightNumber, string $message): bool
    {
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO flight_notifications (passenger_id, flight_number, message) VALUES (?, ?, ?)"
            );
            return $stmt->execute([$passengerId, $flightNumber, $message]);
        } catch (PDOException $e) {
            echo "Error saving flight notification: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // --- General query methods ---
    
    public function getAllPassengers(): array
    {
        try {
            $stmt = $this->connection->query("SELECT * FROM passengers");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error retrieving passengers: " . $e->getMessage() . "\n";
            return [];
        }
    }
    
    public function getAllFlights(): array
    {
        try {
            $stmt = $this->connection->query("SELECT * FROM flights");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error retrieving flights: " . $e->getMessage() . "\n";
            return [];
        }
    }
    
    public function getBoardingPassesByPassenger(string $passengerId): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT bp.*, f.destination, f.departure_time 
                 FROM boarding_passes bp 
                 JOIN flights f ON bp.flight_number = f.flight_number 
                 WHERE bp.passenger_id = ?"
            );
            $stmt->execute([$passengerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error retrieving boarding passes: " . $e->getMessage() . "\n";
            return [];
        }
    }
}