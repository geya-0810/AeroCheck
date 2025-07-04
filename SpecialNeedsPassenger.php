<?php

declare(strict_types=1);

// 引入依赖的类
require_once 'Passenger.php';
require_once 'AssistanceDetails.php';

/**
 * Represents a passenger who has formally registered for special needs.
 * This class inherits from Passenger.
 */
class SpecialNeedsPassenger extends Passenger
{
    public function __construct(
        string $passengerId,
        string $name,
        string $contactInfo,
        private AssistanceDetails $assistanceDetails
    ) {
        parent::__construct($passengerId, $name, $contactInfo);
    }

    // --- Overridden and new Methods ---

    /**
     * Requests special assistance, creating or updating the details.
     * @param string $needType
     * @param string $description
     * @return AssistanceDetails
     */
    public function requestSpecialAssistance(string $needType, string $description): AssistanceDetails
    {
        // Overrides parent to interact with its own AssistanceDetails object
        $this->assistanceDetails = new AssistanceDetails(uniqid('assist-'), $needType, $description);
        echo "Special needs for passenger {$this->getName()} have been formally logged.\n";
        return $this->assistanceDetails;
    }

    /**
     * Update the existing assistance details.
     * @param string $details
     */
    public function updateAssistanceDetails(string $details): void
    {
        // This method is not fully defined in the diagram, assuming it updates the description.
        // To be fully functional, it should specify what to update.
        // For now, let's assume it updates the status.
        $this->assistanceDetails->updateStatus($details);
    }
    
    public function getAssistanceDetails(): AssistanceDetails
    {
        return $this->assistanceDetails;
    }
}