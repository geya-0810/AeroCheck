<?php

declare(strict_types=1);

require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';

/**
 * The central system that orchestrates the check-in process.
 * This class acts as a Facade or Service Layer.
 */
class CheckInSystem
{
    public function __construct()
    {
        echo "Check-In System is online.\n";
    }

    /**
     * Main entry point to check in a passenger.
     * param Passenger $passenger
     * param Flight $flight
     * param string $seat
     * return BoardingPass
     */
    public function checkInPassenger(Passenger $passenger, Flight $flight, string $seat): BoardingPass
    {
        if (!$this->isCheckInAvailable($flight)) {
            throw new \Exception("Check-in for flight {$flight->getFlightNumber()} is not available.");
        }
        
        echo "System: Processing check-in for passenger {$passenger->getName()}.\n";
        // Logic to assign a seat if not provided, validate documents etc.
        return new BoardingPass($passenger->getName(), $seat, $flight, new \DateTime());
    }

    /**
     * This private method is called if a passenger has special needs.
     * It now accepts any Passenger object and performs a check inside.
     * param Passenger $passenger
     */
    private function processSpecialNeeds(Passenger $passenger): void
    {
        $details = $passenger->getAssistanceDetails();
        // This check is robust, as getAssistanceDetails() will only return an object if needs exist.
        if ($details !== null) {
            echo "System: Logging and routing special needs request for {$passenger->getName()}.\n";
            // Update the status on the details object directly.
            $details->updateStatus('Pending airline confirmation');
        }
    }

    /**
     * Process check-in for a group.
     * param Group $group
     * param Flight $flight
     */
    public function processCheckInGroup(Group $group, Flight $flight): void
    {
        echo "System: Processing group check-in for group ID {$group->getGroupDetails()['groupId']}.\n";
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
    }
    
    /**
     * Process baggage check-in.
     * param Baggage $baggage
     */
    public function processBaggage(Baggage $baggage): void
    {
        echo "System: Processing baggage {$baggage->getTrackingInfo()['baggageId']}.\n";
        $baggage->checkInBaggage();
        $baggage->updateScreeningStatus('In Transit to Screening');
    }

    /**
     * Checks if check-in is open for a given flight.
     * param Flight $flight
     * return bool
     */
    public function isCheckInAvailable(Flight $flight): bool
    {
        // In reality, this would check the time against the flight's departure time.
        // e.g., check-in opens 24 hours before and closes 1 hour before departure.
        return $flight->getFlightDetails()['status'] !== 'Departed' && $flight->getFlightDetails()['status'] !== 'Closed';
    }
}