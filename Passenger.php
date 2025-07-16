<?php

declare(strict_types=1);

require_once 'BoardingPass.php';
require_once 'Baggage.php';
require_once 'AssistanceDetails.php';
require_once 'CheckInSystem.php'; 
require_once 'Flight.php'; 
require_once 'DatabaseManager.php';

/**
 * Represents a passenger with database integration.
 */
class Passenger
{
    /** var BoardingPass|null */
    protected ?BoardingPass $boardingPass = null;

    /** var Baggage[] */
    protected array $baggage = [];

    /** var AssistanceDetails|null 
     * This property holds the special needs information.
     * If it's null, the passenger has no special needs.
     */
    private ?AssistanceDetails $assistanceDetails = null;
    
    /** var DatabaseManager */
    private DatabaseManager $dbManager;
    
    public function __construct(
        private string $passengerId,
        private string $name,
        private string $contactInfo
    ) {
        $this->dbManager = new DatabaseManager();
        // Save passenger to database when created
        $this->saveToDatabase();
    }
    
    /**
     * Save passenger data to database
     */
    private function saveToDatabase(): void
    {
        $this->dbManager->savePassenger($this);
    }
    
    /**
     * Load passenger data from database
     */
    public static function loadFromDatabase(string $passengerId): ?self
    {
        $dbManager = new DatabaseManager();
        $data = $dbManager->getPassenger($passengerId);
        
        if (!$data) {
            return null;
        }
        // 拼接姓名和联系方式
        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $contact = $data['contact_phone'] ?? '';
        return new self(
            $data['passenger_id'],
            $name,
            $contact
        );
    }
    
    /**
     * Assigns special assistance needs to this passenger.
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
        
        // Save to database
        $this->dbManager->saveAssistanceDetails($this->assistanceDetails, $this->passengerId);
        
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

    /**
     * Check in the passenger using the system
     */
    public function checkIn(CheckInSystem $system, Flight $flight): void
    {
        echo "Passenger {$this->name} is attempting to check in.\n";
        
        // Save flight to database
        $this->dbManager->saveFlight($flight);
        
        // Process check-in
        $this->boardingPass = $system->checkInPassenger($this, $flight, "18A");
        
        // Save boarding pass to database
        if ($this->boardingPass) {
            $this->dbManager->saveBoardingPass($this->boardingPass, $this->passengerId, $flight->getFlightNumber());
        }
    }

    /**
     * Add baggage to passenger
     */
    public function addBaggage(Baggage $bag): void
    {
        $this->baggage[] = $bag;
        // Save baggage to database
        $this->dbManager->saveBaggage($bag);
    }

    /**
     * Get boarding pass history from database
     */
    public function getBoardingPassHistory(): array
    {
        return $this->dbManager->getBoardingPassesByPassenger($this->passengerId);
    }

    /**
     * Update passenger contact information
     */
    public function updateContactInfo(string $newContactInfo): void
    {
        $this->contactInfo = $newContactInfo;
        $this->saveToDatabase();
        echo "Contact information updated for passenger {$this->name}.\n";
    }

    // --- Getters ---
    
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
            'hasSpecialNeeds' => $this->hasSpecialNeeds()
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
    
    public function getSeatNumber(): string
    {
        return $this->boardingPass ? "18A" : "Not assigned"; // Simplified for MVP
    }
}