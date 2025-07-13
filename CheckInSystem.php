<?php

declare(strict_types=1);

require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';
require_once 'DatabaseManager.php';
require_once 'Booking.php';

abstract class CheckInSystem
{
    protected DatabaseManager $dbManager;
    
    public function __construct()
    {
        $this->dbManager = new DatabaseManager();
    }

    // 通用：获取所有行李配套
    public function getBaggagePackages(): array
    {
        $conn = $this->dbManager;
        $pdo = (new \ReflectionClass($conn))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($conn);
        $stmt = $pdo->query("SELECT * FROM baggage_packages ORDER BY additional_weight_kg");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 通用
    public function getAllPassengers(): array
    {
        return $this->dbManager->getAllPassengers();
    }
    public function getAllFlights(): array
    {
        return $this->dbManager->getAllFlights();
    }
    public function getPassengerById(string $passengerId): ?Passenger
    {
        return Passenger::loadFromDatabase($passengerId);
    }
    public function getFlightInfo(string $flightNumber): ?array
    {
        return $this->dbManager->getFlight($flightNumber);
    }
    public function sendFlightNotification(string $passengerId, string $flightNumber, string $message): void
    {
        $this->dbManager->saveFlightNotification($passengerId, $flightNumber, $message);
    }
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