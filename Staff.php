<?php

declare(strict_types=1);

// 引入依赖的类
require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'SpecialNeedsPassenger.php';
require_once 'Baggage.php';


/**
 * Represents a staff member.
 */
class Staff
{
    public function __construct(
        private string $staffId,
        private string $name,
        private string $role
    ) {}
    
    // --- Methods ---
    
    /**
     * Handles the check-in for a single passenger.
     * @param Passenger $passenger
     * @param Flight $flight
     * @param string $seatNumber
     * @return BoardingPass
     */
    public function checkInPassenger(Passenger $passenger, Flight $flight, string $seatNumber): BoardingPass
    {
        echo "Staff {$this->name} is checking in passenger {$passenger->getName()} for flight {$flight->getFlightNumber()}.\n";
        return new BoardingPass(
            $passenger->getName(),
            $seatNumber,
            $flight,
            new \DateTime()
        );
    }

    /**
     * Handles the check-in for an entire group.
     * @param Group $group
     * @param Flight $flight
     */
    public function processCheckInGroup(Group $group, Flight $flight): void
    {
        echo "Staff {$this->name} is processing check-in for group {$group->getGroupDetails()['groupId']}.\n";
        foreach ($group->getPassengers() as $passenger) {
            // In a real scenario, seats would be assigned dynamically.
            $this->checkInPassenger($passenger, $flight, "20" . chr(65 + array_rand(range(0,5))));
        }
    }

    /**
     * Assesses special needs for a passenger.
     * @param SpecialNeedsPassenger $passenger
     */
    public function assessSpecialNeeds(SpecialNeedsPassenger $passenger): void
    {
        echo "Staff {$this->name} is assessing special needs for {$passenger->getName()}.\n";
        $passenger->getAssistanceDetails()->updateStatus('Assessed and Approved');
    }

    /**
     * Handles passenger baggage.
     * @param Baggage $baggage
     */
    public function handleBaggage(Baggage $baggage): void
    {
        echo "Staff {$this->name} is handling baggage {$baggage->getTrackingInfo()['baggageId']}.\n";
        $baggage->checkInBaggage();
    }
}