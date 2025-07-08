<?php

declare(strict_types=1);

require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';
require_once 'DatabaseManager.php';

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