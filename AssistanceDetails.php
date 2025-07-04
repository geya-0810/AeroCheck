<?php

declare(strict_types=1);

/**
 * Represents assistance details for a passenger with special needs.
 */
class AssistanceDetails
{
    public function __construct(
        private string $assistanceId,
        private string $needType,
        private string $description,
        private string $status = 'Requested'
    ) {}

    /**
     * Get the details of the assistance required.
     * return array
     */
    public function getDetails(): array
    {
        return [
            'id' => $this->assistanceId,
            'type' => $this->needType,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }

    /**
     * Update the status of the assistance request.
     * param string $newStatus
     */
    public function updateStatus(string $newStatus): void
    {
        $this->status = $newStatus;
        echo "Assistance request {$this->assistanceId} status updated to: {$newStatus}\n";
    }
}