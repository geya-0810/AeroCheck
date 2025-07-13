<?php

declare(strict_types=1);

require_once 'CheckInSystem.php';
require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';

/**
 * Represents a check-in counter for staff-assisted check-in.
 */
class CheckInCounter extends CheckInSystem
{
    public function checkInPassenger(Passenger $passenger, Flight $flight, string $seat): BoardingPass
    {
        $this->dbManager->savePassenger($passenger);
        $this->dbManager->saveFlight($flight);
        if (!$this->isCheckInAvailable($flight)) {
            throw new \Exception("Check-in for flight {$flight->getFlightNumber()} is not available.");
        }
        $this->processSpecialNeeds($passenger);
        $boardingPass = new BoardingPass($passenger->getName(), $seat, $flight, new \DateTime());
        $this->dbManager->saveBoardingPass($boardingPass, $passenger->getPassengerId(), $flight->getFlightNumber());
        return $boardingPass;
    }
    
    public function processCheckInGroup(Group $group, Flight $flight): void
    {
        $this->dbManager->saveGroup($group);
        $seatChar = 65; // 'A'
        $seatRow = 25;
        foreach ($group->getPassengers() as $passenger) {
            $seat = $seatRow . chr($seatChar++);
            $this->checkInPassenger($passenger, $flight, $seat);
            if ($seatChar > 70) {
                $seatChar = 65;
                $seatRow++;
            }
        }
    }
    
    public function processBaggage(Baggage $baggage): void
    {
        $baggage->checkInBaggage();
        $baggage->updateScreeningStatus('In Transit to Screening');
        $this->dbManager->saveBaggage($baggage);
    }
    
    public function isCheckInAvailable(Flight $flight): bool
    {
        $status = $flight->getFlightDetails()['status'];
        return $status !== 'Departed' && $status !== 'Closed';
    }
    
    private function processSpecialNeeds(Passenger $passenger): void
    {
        $details = $passenger->getAssistanceDetails();
        if ($details !== null) {
            $details->updateStatus('Processed by System');
            $this->dbManager->saveAssistanceDetails($details, $passenger->getPassengerId());
        }
    }
}