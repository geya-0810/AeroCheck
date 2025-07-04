<?php

declare(strict_types=1);

// 引入依赖的类
require_once 'Staff.php';
require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'SpecialNeedsPassenger.php';
require_once 'Baggage.php';


/**
 * Represents a physical check-in counter.
 */
class CheckInCounter
{
    public function __construct(
        private string $counterId,
        private string $location,
        private Staff $assignedStaff // A counter is operated by a staff member
    ) {}

    // --- Methods (delegating to the assigned staff) ---

    public function checkInPassenger(Passenger $passenger, Flight $flight, string $seat): BoardingPass
    {
        echo "Processing at Counter {$this->counterId}: \n";
        return $this->assignedStaff->checkInPassenger($passenger, $flight, $seat);
    }
    
    public function checkInGroup(Group $group, Flight $flight): void
    {
        echo "Processing at Counter {$this->counterId}: \n";
        $this->assignedStaff->processCheckInGroup($group, $flight);
    }

    public function handleSpecialNeeds(SpecialNeedsPassenger $passenger): void
    {
        echo "Processing at Counter {$this->counterId}: \n";
        $this->assignedStaff->assessSpecialNeeds($passenger);
    }
    
    public function processBaggage(Baggage $baggage): void
    {
        echo "Processing at Counter {$this->counterId}: \n";
        $this->assignedStaff->handleBaggage($baggage);
    }
}