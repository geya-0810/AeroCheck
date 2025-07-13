<?php

declare(strict_types=1);

require_once 'DatabaseManager.php';

/**
 * Represents a seat in the system (maps to the seats table).
 */
class Seats
{
    private string $seatId;
    private string $flightNumber;
    private int $row;
    private string $column;
    private string $seatNumber;
    private string $seatClass;
    private bool $isPremium;
    private string $status;
    private DatabaseManager $dbManager;

    public function __construct(
        string $seatId,
        string $flightNumber,
        int $row,
        string $column,
        string $seatNumber,
        string $seatClass = 'Economy',
        bool $isPremium = false,
        string $status = 'Available'
    ) {
        $this->seatId = $seatId;
        $this->flightNumber = $flightNumber;
        $this->row = $row;
        $this->column = $column;
        $this->seatNumber = $seatNumber;
        $this->seatClass = $seatClass;
        $this->isPremium = $isPremium;
        $this->status = $status;
        $this->dbManager = new DatabaseManager();
    }

    // --- Static methods ---
    public static function loadById(string $seatId): ?self
    {
        $db = new DatabaseManager();
        $pdo = (new \ReflectionClass($db))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($db);
        $stmt = $pdo->prepare("SELECT * FROM seats WHERE seat_id = ?");
        $stmt->execute([$seatId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) return null;
        return new self(
            $data['seat_id'],
            $data['flight_number'],
            (int)$data['row'],
            $data['column'],
            $data['seat_number'],
            $data['seat_class'],
            (bool)$data['is_premium'],
            $data['status']
        );
    }

    public static function getSeatsByFlight(string $flightNumber): array
    {
        $db = new DatabaseManager();
        $pdo = (new \ReflectionClass($db))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($db);
        $stmt = $pdo->prepare("SELECT * FROM seats WHERE flight_number = ? ORDER BY `row`, `column`");
        $stmt->execute([$flightNumber]);
        $result = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = new self(
                $data['seat_id'],
                $data['flight_number'],
                (int)$data['row'],
                $data['column'],
                $data['seat_number'],
                $data['seat_class'],
                (bool)$data['is_premium'],
                $data['status']
            );
        }
        return $result;
    }

    public static function getAvailableSeatsByFlight(string $flightNumber): array
    {
        $db = new DatabaseManager();
        $pdo = (new \ReflectionClass($db))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($db);
        $stmt = $pdo->prepare("SELECT * FROM seats WHERE flight_number = ? AND status = 'Available' ORDER BY `row`, `column`");
        $stmt->execute([$flightNumber]);
        $result = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = new self(
                $data['seat_id'],
                $data['flight_number'],
                (int)$data['row'],
                $data['column'],
                $data['seat_number'],
                $data['seat_class'],
                (bool)$data['is_premium'],
                $data['status']
            );
        }
        return $result;
    }

    // --- Instance methods ---
    public function assignToPassenger(string $passengerId, string $bookingId): bool
    {
        $pdo = (new \ReflectionClass($this->dbManager))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($this->dbManager);
        // 更新booking_passengers表的assigned_seat_id和seat_number
        $stmt = $pdo->prepare("UPDATE booking_passengers SET assigned_seat_id = ?, seat_number = ? WHERE passenger_id = ? AND booking_id = ?");
        $ok = $stmt->execute([$this->seatId, $this->seatNumber, $passengerId, $bookingId]);
        if ($ok) {
            $this->markOccupied();
        }
        return $ok;
    }

    public function markOccupied(): bool
    {
        $pdo = (new \ReflectionClass($this->dbManager))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($this->dbManager);
        $stmt = $pdo->prepare("UPDATE seats SET status = 'Occupied' WHERE seat_id = ?");
        $ok = $stmt->execute([$this->seatId]);
        if ($ok) $this->status = 'Occupied';
        return $ok;
    }

    public function markAvailable(): bool
    {
        $pdo = (new \ReflectionClass($this->dbManager))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($this->dbManager);
        $stmt = $pdo->prepare("UPDATE seats SET status = 'Available' WHERE seat_id = ?");
        $ok = $stmt->execute([$this->seatId]);
        if ($ok) $this->status = 'Available';
        return $ok;
    }

    public function save(): bool
    {
        $pdo = (new \ReflectionClass($this->dbManager))->getProperty('connection');
        $pdo->setAccessible(true);
        $pdo = $pdo->getValue($this->dbManager);
        $stmt = $pdo->prepare("REPLACE INTO seats (seat_id, flight_number, row, column, seat_number, seat_class, is_premium, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $this->seatId,
            $this->flightNumber,
            $this->row,
            $this->column,
            $this->seatNumber,
            $this->seatClass,
            $this->isPremium,
            $this->status
        ]);
    }

    // --- Getters ---
    public function getSeatId(): string { return $this->seatId; }
    public function getFlightNumber(): string { return $this->flightNumber; }
    public function getRow(): int { return $this->row; }
    public function getColumn(): string { return $this->column; }
    public function getSeatNumber(): string { return $this->seatNumber; }
    public function getSeatClass(): string { return $this->seatClass; }
    public function isPremium(): bool { return $this->isPremium; }
    public function getStatus(): string { return $this->status; }
} 