<?php

declare(strict_types=1);

// 引入 FlightStatusNotifier 类，因为 Flight 类依赖它
require_once 'FlightStatusNotifier.php';

/**
 * Represents a flight. It acts as a subject in the Observer pattern.
 */
class Flight
{
    public function __construct(
        private string $flightNumber,
        private \DateTime $departureTime,
        private string $destination,
        private string $gate,
        private string $status = 'On Time'
    ) {}

    // --- Methods ---

    /**
     * Updates the flight status and notifies observers.
     * @param string $status
     * @param FlightStatusNotifier $notifier
     */
    public function updateStatus(string $status, FlightStatusNotifier $notifier): void
    {
        $this->status = $status;
        echo "Flight {$this->flightNumber} status changed to: {$this->status}\n";
        $this->notifyObservers($notifier);
    }

    /**
     * Gets the current details of the flight.
     * @return array
     */
    public function getFlightDetails(): array
    {
        return [
            'flightNumber' => $this->flightNumber,
            'departureTime' => $this->departureTime->format('Y-m-d H:i:s'),
            'destination' => $this->destination,
            'gate' => $this->gate,
            'status' => $this->status,
        ];
    }

    /**
     * Notifies subscribers about the flight status change through the notifier.
     * @param FlightStatusNotifier $notifier
     */
    private function notifyObservers(FlightStatusNotifier $notifier): void
    {
        $notifier->notifySubscribers($this);
    }
    
    public function getFlightNumber(): string
    {
        return $this->flightNumber;
    }
}