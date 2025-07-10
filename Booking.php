<?php

declare(strict_types=1);

require_once 'DatabaseManager.php';
require_once 'Passenger.php';
require_once 'Flight.php';

class Booking
{
    private string $bookingRef;
    private ?array $bookingData = null;
    private DatabaseManager $dbManager;

    public function __construct(string $bookingRef)
    {
        $this->bookingRef = $bookingRef;
        $this->dbManager = new DatabaseManager();
    }

    /**
     * Find booking by reference and last name
     * @param string $lastName
     * @return bool
     */
    public function findBooking(string $lastName): bool
    {
        // Get database connection
        $conn = $this->dbManager;
        $pdo = (new \ReflectionClass($conn))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($conn);
        
        // Join bookings with booking_passengers and passengers to find by last name
        $stmt = $pdo->prepare("
            SELECT DISTINCT b.* 
            FROM bookings b 
            JOIN booking_passengers bp ON b.booking_id = bp.booking_id 
            JOIN passengers p ON bp.passenger_id = p.passenger_id 
            WHERE b.booking_id = ? AND p.last_name = ?
        ");
        $stmt->execute([$this->bookingRef, $lastName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $this->bookingData = $result;
            return true;
        }
        return false;
    }

    /**
     * Get booking data
     * @return array|null
     */
    public function getBookingData(): ?array
    {
        return $this->bookingData;
    }

    /**
     * Get passengers for this booking
     * @return array
     */
    public function getPassengers(): array
    {
        if (!$this->bookingData) return [];
        $conn = $this->dbManager;
        $pdo = (new \ReflectionClass($conn))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($conn);
        
        // Join booking_passengers with passengers to get passenger details
        $stmt = $pdo->prepare("
            SELECT p.*, bp.seat_number, bp.check_in_status, bp.additional_baggage_pieces
            FROM booking_passengers bp 
            JOIN passengers p ON bp.passenger_id = p.passenger_id 
            WHERE bp.booking_id = ?
        ");
        $stmt->execute([$this->bookingRef]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get flight info for this booking
     * @return array|null
     */
    public function getFlight(): ?array
    {
        if (!$this->bookingData) return null;
        $flightNumber = $this->bookingData['flight_number'] ?? null;
        if (!$flightNumber) return null;
        return $this->dbManager->getFlight($flightNumber);
    }

    /**
     * Get available seats for the flight
     * @return array
     */
    public function getAvailableSeats(): array
    {
        if (!$this->bookingData) return [];
        $flightNumber = $this->bookingData['flight_number'] ?? null;
        if (!$flightNumber) return [];
        
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
     * @return array
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
} 