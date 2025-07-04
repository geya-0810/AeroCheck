<?php

declare(strict_types=1);

/**
 * Represents a piece of baggage belonging to a passenger.
 */
class Baggage
{
    public function __construct(
        private string $baggageId,
        private string $passengerId,
        private float $weight,
        private string $screeningStatus = 'Pending',
        private ?string $baggageTag = null
    ) {}

    // --- Methods ---
    
    /**
     * Associate the baggage with a passenger and generate a tag.
     */
    public function checkInBaggage(): void
    {
        $this->baggageTag = $this->generateBaggageTag();
        $this->screeningStatus = 'Checked In';
        echo "Baggage {$this->baggageId} checked in for passenger {$this->passengerId} with tag {$this->baggageTag}.\n";
    }

    /**
     * Updates the screening status of the baggage.
     * param string $status
     */
    public function updateScreeningStatus(string $status): void
    {
        $this->screeningStatus = $status;
        echo "Baggage {$this->baggageId} screening status updated to: {$status}\n";
    }

    /**
     * Generates a unique baggage tag.
     * return string
     */
    public function generateBaggageTag(): string
    {
        return "BT" . substr($this->passengerId, 0, 4) . strtoupper(uniqid());
    }
    
    /**
     * Gets tracking information for the baggage.
     * return array
     */
    public function getTrackingInfo(): array
    {
        return [
            'baggageId' => $this->baggageId,
            'screeningStatus' => $this->screeningStatus,
            'tag' => $this->baggageTag,
        ];
    }
}