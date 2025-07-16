<?php

declare(strict_types=1);

require_once 'Passenger.php';
require_once 'CheckInSystem.php'; 
require_once 'Flight.php'; 

/**
 * Represents a group of passengers traveling together.
 */
class Group
{
    /** var Passenger[] */
    private array $passengers = [];

    public function __construct(
        private string $groupId
    ) {
        // Group starts empty, passengers are added separately
    }
    
    /**
     * Adds a passenger to the group.
     * param Passenger $passenger
     */
    public function addPassenger(Passenger $passenger): void
    {
        $this->passengers[$passenger->getPassengerId()] = $passenger;
        echo "Passenger {$passenger->getName()} added to group {$this->groupId}.\n";
    }
    
    /**
     * Removes a passenger from the group.
     * param string $passengerId
     */
    public function removePassenger(string $passengerId): void
    {
        if (isset($this->passengers[$passengerId])) {
            unset($this->passengers[$passengerId]);
            echo "Passenger with ID {$passengerId} removed from group {$this->groupId}.\n";
        }
    }

    /**
     * Gets the first passenger as representative (if any passengers exist).
     * return Passenger|null
     */
    public function getRepresentative(): ?Passenger
    {
        return !empty($this->passengers) ? reset($this->passengers) : null;
    }
    
    /**
     * Check in the entire group.
     * param CheckInSystem $system
     * param Flight $flight
     */
    public function groupCheckIn(CheckInSystem $system, Flight $flight): void
    {
        echo "Processing check-in for group {$this->groupId}.\n";
        $system->processCheckInGroup($this, $flight);
    }

    public function getGroupDetails(): array
    {
        $representative = $this->getRepresentative();
        return [
            'groupId' => $this->groupId,
            'representative' => $representative ? $representative->getName() : 'No representative',
            'member_count' => count($this->passengers)
        ];
    }
    
    /** return Passenger[] */
    public function getPassengers(): array
    {
        return $this->passengers;
    }
}