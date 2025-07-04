<?php

declare(strict_types=1);

require_once 'Passenger.php';
require_once 'Flight.php';

/**
 * Notifier service that manages subscriptions and sends flight updates.
 */
class FlightStatusNotifier
{
    /** var Passenger[] */
    private array $subscribers = [];

    /**
     * Subscribe a passenger to receive updates for a flight.
     * param Passenger $passenger
     * param string $flightNumber
     */
    public function subscribe(Passenger $passenger, string $flightNumber): void
    {
        $this->subscribers[$flightNumber][] = $passenger;
        echo "Passenger {$passenger->getName()} subscribed to flight {$flightNumber} updates.\n";
    }

    /**
     * Unsubscribe a passenger from flight updates.
     * param Passenger $passenger
     * param string $flightNumber
     */
    public function unsubscribe(Passenger $passenger, string $flightNumber): void
    {
        if (isset($this->subscribers[$flightNumber])) {
            foreach ($this->subscribers[$flightNumber] as $key => $sub) {
                if ($sub->getPassengerId() === $passenger->getPassengerId()) {
                    unset($this->subscribers[$flightNumber][$key]);
                    echo "Passenger {$passenger->getName()} unsubscribed from flight {$flightNumber} updates.\n";
                    return;
                }
            }
        }
    }

    /**
     * Notify all subscribers of a specific flight.
     * param Flight $flight
     */
    public function notifySubscribers(Flight $flight): void
    {
        $flightNumber = $flight->getFlightNumber();
        if (empty($this->subscribers[$flightNumber])) {
            return;
        }

        echo "Notifying subscribers for flight {$flightNumber}...\n";
        foreach ($this->subscribers[$flightNumber] as $passenger) {
            $this->sendFlightUpdate($passenger, $flight);
        }
    }

    /**
     * Send a specific update to a passenger.
     * param Passenger $passenger
     * param Flight $flight
     */
    public function sendFlightUpdate(Passenger $passenger, Flight $flight): void
    {
        // In a real application, this would send an SMS, email, or push notification.
        $details = $flight->getFlightDetails();
        echo "-> Sending update to {$passenger->getName()} (Contact: {$passenger->getContactInfo()}): Flight {$details['flightNumber']} is now {$details['status']}.\n";
    }
}