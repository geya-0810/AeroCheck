<?php

declare(strict_types=1);

// 引入依赖的类
require_once 'Passenger.php';
require_once 'CheckInSystem.php'; // 为了 groupCheckIn 方法的参数类型提示
require_once 'Flight.php'; // 为了 groupCheckIn 方法的参数类型提示


/**
 * Represents a group of passengers traveling together.
 */
class Group
{
    /** @var Passenger[] */
    private array $passengers = [];

    public function __construct(
        private string $groupId,
        private Passenger $representative
    ) {
        // The representative is also part of the group.
        $this->addPassenger($representative);
    }
    
    // --- Methods ---

    /**
     * Adds a passenger to the group.
     * @param Passenger $passenger
     */
    public function addPassenger(Passenger $passenger): void
    {
        $this->passengers[$passenger->getPassengerId()] = $passenger;
        echo "Passenger {$passenger->getName()} added to group {$this->groupId}.\n";
    }
    
    /**
     * Removes a passenger from the group.
     * @param string $passengerId
     */
    public function removePassenger(string $passengerId): void
    {
        if (isset($this->passengers[$passengerId])) {
            // Cannot remove the representative without assigning a new one.
            if ($this->representative->getPassengerId() === $passengerId) {
                echo "Cannot remove the group representative.\n";
                return;
            }
            unset($this->passengers[$passengerId]);
            echo "Passenger with ID {$passengerId} removed from group {$this->groupId}.\n";
        }
    }

    public function getRepresentative(): Passenger
    {
        return $this->representative;
    }
    
    /**
     * Check in the entire group.
     * @param CheckInSystem $system
     * @param Flight $flight
     */
    public function groupCheckIn(CheckInSystem $system, Flight $flight): void
    {
        echo "Processing check-in for group {$this->groupId}.\n";
        $system->processCheckInGroup($this, $flight);
    }

    public function getGroupDetails(): array
    {
        return [
            'groupId' => $this->groupId,
            'representative' => $this->representative->getName(),
            'member_count' => count($this->passengers)
        ];
    }
    
    /** @return Passenger[] */
    public function getPassengers(): array
    {
        return $this->passengers;
    }
}