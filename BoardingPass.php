<?php

declare(strict_types=1);

require_once 'Flight.php';

/**
 * Represents a boarding pass for a passenger.
 */
class BoardingPass
{
    public function __construct(
        private string $passengerName,
        private string $seatNumber,
        private Flight $flight,
        private \DateTime $issueDateTime,
        private ?string $qrCode = null
    ) {
        $this->qrCode = $this->qrCode ?? $this->generateQRCode();
    }
    
    // --- Methods ---

    /**
     * Generates a QR code string.
     * return string
     */
    public function generateQRCode(): string
    {
        $details = $this->flight->getFlightDetails();
        $data = "PN:{$this->passengerName},SIT:{$this->seatNumber},FLT:{$details['flightNumber']},DEST:{$details['destination']}";
        // In a real app, this would return an image or data URI of a QR code.
        return "QR_CODE_DATA[{$data}]";
    }

    /**
     * Generates an electronic version of the pass (e.g., a PKPass file).
     * return string
     */
    public function generateElectronicPass(): string
    {
        // Logic to create a digital pass file.
        return "Electronic pass data for {$this->passengerName}";
    }
    
    /**
     * Sends the boarding pass to a mobile number.
     * param string $phoneNumber
     */
    public function sendToMobilePhoneNumber(string $phoneNumber): void
    {
        echo "Boarding pass sent to {$phoneNumber}.\n";
    }
}