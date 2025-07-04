<?php

declare(strict_types=1);

// 引入依赖的类
require_once 'BoardingPass.php';
require_once 'Baggage.php';
require_once 'AssistanceDetails.php';
require_once 'CheckInSystem.php'; // 为了 checkIn 方法的参数类型提示
require_once 'Flight.php'; // 为了 checkIn 方法的参数类型提示


/**
 * Represents a passenger.
 */
class Passenger
{
    /** @var BoardingPass|null */
    protected ?BoardingPass $boardingPass = null;

    /** @var Baggage[] */
    protected array $baggage = [];
    
    public function __construct(
        private string $passengerId,
        private string $name,
        private string $contactInfo
    ) {}
    
    // --- Methods ---

    /**
     * Base method for requesting special assistance.
     * Can be overridden by subclasses.
     * @param string $needType
     * @param string $description
     * @return AssistanceDetails
     */
    public function requestSpecialAssistance(string $needType, string $description): AssistanceDetails
    {
        // This suggests that any passenger can become one with special needs.
        // This is where the Composition over Inheritance argument is strong.
        // For this implementation, we will create a details object.
        return new AssistanceDetails(uniqid('assist-'), $needType, $description);
    }
    
    /**
     * A simplified check-in method for the passenger.
     * @param CheckInSystem $system
     * @param Flight $flight
     */
    public function checkIn(CheckInSystem $system, Flight $flight): void
    {
        echo "Passenger {$this->name} is attempting to check in.\n";
        $this->boardingPass = $system->checkInPassenger($this, $flight, "18A");
    }

    public function addBaggage(Baggage $bag): void
    {
        $this->baggage[] = $bag;
    }

    public function getBoardingPass(): ?BoardingPass
    {
        return $this->boardingPass;
    }

    public function getPassengerDetails(): array
    {
        return [
            'id' => $this->passengerId,
            'name' => $this->name,
            'contact' => $this->contactInfo,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }
    
    public function getPassengerId(): string
    {
        return $this->passengerId;
    }
    
    public function getContactInfo(): string
    {
        return $this->contactInfo;
    }
}