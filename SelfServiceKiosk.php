<?php

declare(strict_types=1);

// 引入依赖的类
require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'BoardingPass.php';
require_once 'Group.php';
require_once 'Baggage.php';
require_once 'SpecialNeedsPassenger.php'; // 用于 instanceof 检查


/**
 * Represents a self-service kiosk.
 */
class SelfServiceKiosk
{
    public function __construct(
        private string $kioskId,
        private string $location
    ) {}
    
    // --- Methods ---

    public function displayInterface(): void
    {
        echo "Welcome to Kiosk {$this->kioskId}. Please scan your passport or enter booking reference.\n";
    }

    public function checkInPassenger(Passenger $passenger, Flight $flight, string $seat): ?BoardingPass
    {
        echo "Kiosk {$this->kioskId}: Checking in {$passenger->getName()}.\n";
        // Kiosks might have restrictions (e.g., no special needs, no group check-in)
        if ($passenger instanceof SpecialNeedsPassenger) {
            echo "Please see a staff member for special assistance.\n";
            return null;
        }
        return $this->printBoardingPass($passenger, $flight, $seat);
    }
    
    public function checkInGroup(Group $group, Flight $flight): void
    {
        echo "Kiosk {$this->kioskId}: Group check-in is not supported. Please see a staff member.\n";
    }

    public function printBoardingPass(Passenger $passenger, Flight $flight, string $seat): BoardingPass
    {
        $boardingPass = new BoardingPass($passenger->getName(), $seat, $flight, new \DateTime());
        echo "Kiosk {$this->kioskId}: Printing boarding pass for {$passenger->getName()}.\n";
        return $boardingPass;
    }

    public function processPayableBaggage(Baggage $baggage, float $amount): void
    {
        echo "Kiosk {$this->kioskId}: Processing payment of {$amount} for baggage {$baggage->getTrackingInfo()['baggageId']}.\n";
        $baggage->checkInBaggage();
    }
}