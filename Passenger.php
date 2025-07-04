<?php

declare(strict_types=1);

require_once 'BoardingPass.php';
require_once 'Baggage.php';
require_once 'AssistanceDetails.php';
require_once 'CheckInSystem.php'; 
require_once 'Flight.php'; 


/**
 * Represents a passenger.
 */
class Passenger
{
    /** var BoardingPass|null */
    protected ?BoardingPass $boardingPass = null;

    /** var Baggage[] */
    protected array $baggage = [];

    /** * var AssistanceDetails|null 
     * This property holds the special needs information.
     * If it's null, the passenger has no special needs. This replaces the boolean flag.
     */
    private ?AssistanceDetails $assistanceDetails = null;
    
    public function __construct(
        private string $passengerId,
        private string $name,
        private string $contactInfo
    ) {}
    
    /**
     * Assigns special assistance needs to this passenger.
     * This method creates and sets the AssistanceDetails object.
     * param string $needType e.g., "Wheelchair", "Dietary"
     * param string $description e.g., "Requires wheelchair from check-in to gate"
     */
    public function requestSpecialAssistance(string $needType, string $description): void
    {
        $this->assistanceDetails = new AssistanceDetails(
            uniqid('assist-'), 
            $needType, 
            $description
        );
        echo "Passenger {$this->name} has requested special assistance: {$needType}.\n";
    }
    
    /**
     * Removes the special assistance request.
     */
    public function cancelSpecialAssistance(): void
    {
        $this->assistanceDetails = null;
        echo "Special assistance for passenger {$this->name} has been cancelled.\n";
    }

    /**
     * Checks if the passenger has special needs.
     * return bool
     */
    public function hasSpecialNeeds(): bool
    {
        return $this->assistanceDetails !== null;
    }

    /**
     * Gets the assistance details object. Returns null if there are no special needs.
     * return AssistanceDetails|null
     */
    public function getAssistanceDetails(): ?AssistanceDetails
    {
        return $this->assistanceDetails;
    }

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
    
    public function getName(): string { return $this->name; }
    public function getPassengerId(): string { return $this->passengerId; }
    public function getContactInfo(): string { return $this->contactInfo; }
}