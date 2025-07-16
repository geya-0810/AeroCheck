<?php
// Include necessary files
require_once 'databaseconnect.php';
require_once 'DatabaseManager.php';
require_once 'Passenger.php';
require_once 'Flight.php';
require_once 'Staff.php';
require_once 'Baggage.php';
require_once 'BoardingPass.php';
require_once 'CheckInSystem.php';

// Initialize database manager
$dbManager = new DatabaseManager();
$dbconnect = include 'databaseconnect.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';
$flights = [];
$passengers = [];
$staff = [];

// Get real data from database
try {
    // Get flights
    $stmt = $dbconnect->query("SELECT * FROM flights ORDER BY departure_time");
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get passengers
    $stmt = $dbconnect->query("SELECT *, CONCAT(first_name, ' ', last_name) as name FROM passengers ORDER BY first_name, last_name");
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get staff
    $stmt = $dbconnect->query("SELECT * FROM staff ORDER BY name");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's statistics
    $stmt = $dbconnect->query("
        SELECT 
            COUNT(DISTINCT f.flight_number) as total_flights,
            COUNT(DISTINCT bp.passenger_id) as checked_in_passengers,
            COUNT(DISTINCT ad.assistance_id) as special_assistance,
            COUNT(DISTINCT b.baggage_tag) as total_baggage,
            COUNT(DISTINCT CASE WHEN f.status = 'Delayed' THEN f.flight_number END) as delayed_flights,
            COUNT(DISTINCT CASE WHEN f.status = 'On Time' THEN f.flight_number END) as on_time_flights,
            COUNT(DISTINCT CASE WHEN f.status = 'Cancelled' THEN f.flight_number END) as cancelled_flights
        FROM flights f
        LEFT JOIN boarding_passes bp ON f.flight_number = bp.flight_number
        LEFT JOIN assistance_details ad ON bp.passenger_id = ad.passenger_id
        LEFT JOIN baggage b ON bp.passenger_id = b.passenger_id
        WHERE DATE(f.departure_time) = CURDATE()
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total passengers for today (from bookings)
    $stmt = $dbconnect->query("
        SELECT COUNT(DISTINCT bp.passenger_id) as total_passengers
        FROM flights f
        LEFT JOIN boarding_passes bp ON f.flight_number = bp.flight_number
        WHERE DATE(f.departure_time) = CURDATE()
    ");
    $passengerStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate pending check-ins
    $pendingCheckins = ($passengerStats['total_passengers'] ?? 0) - ($stats['checked_in_passengers'] ?? 0);
    
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Handle AJAX booking search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_booking'])) {
    try {
        $passportNumber = $_POST['passport_number'] ?? '';
        if (!empty($passportNumber)) {
            // Find the passenger by passport number
            $stmtP = $dbconnect->prepare("SELECT passenger_id FROM passengers WHERE passport_number = ? LIMIT 1");
            $stmtP->execute([$passportNumber]);
            $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);
            if ($rowP && $rowP['passenger_id']) {
                $passengerId = $rowP['passenger_id'];
                // Check if this passenger is in a group
                $stmtGroup = $dbconnect->prepare("SELECT gm.group_id, g.booking_id as group_booking_id FROM group_members gm JOIN groups g ON gm.group_id = g.group_id WHERE gm.passenger_id = ? LIMIT 1");
                $stmtGroup->execute([$passengerId]);
                $groupRow = $stmtGroup->fetch(PDO::FETCH_ASSOC);
                if ($groupRow && $groupRow['group_id']) {
                    // Get all group members
                    $groupId = $groupRow['group_id'];
                    $stmtMembers = $dbconnect->prepare("SELECT passenger_id FROM group_members WHERE group_id = ?");
                    $stmtMembers->execute([$groupId]);
                    $allPassengerIds = $stmtMembers->fetchAll(PDO::FETCH_COLUMN);
                    if (count($allPassengerIds) > 0) {
                        // Get all bookings for these passengers for the same flight(s) as the group booking
                        $stmtGroupBooking = $dbconnect->prepare("SELECT flight_number FROM bookings WHERE booking_id = ? LIMIT 1");
                        $stmtGroupBooking->execute([$groupRow['group_booking_id']]);
                        $groupFlightRow = $stmtGroupBooking->fetch(PDO::FETCH_ASSOC);
                        $groupFlightNumber = $groupFlightRow ? $groupFlightRow['flight_number'] : null;
                        $inClause = implode(',', array_fill(0, count($allPassengerIds), '?'));
                        $query = "
                            SELECT 
                                b.booking_id,
                                b.flight_number,
                                p.passenger_id,
                                p.first_name,
                                p.last_name,
                                p.passport_number,
                                CONCAT(p.first_name, ' ', p.last_name) as passenger_name,
                                bp.check_in_status
                            FROM bookings b
                            JOIN booking_passengers bp ON b.booking_id = bp.booking_id
                            JOIN passengers p ON bp.passenger_id = p.passenger_id
                            WHERE p.passenger_id IN ($inClause) ";
                        if ($groupFlightNumber) {
                            $query .= "AND b.flight_number = ? ";
                        }
                        $query .= "ORDER BY b.booking_id, b.flight_number, p.last_name, p.first_name";
                        $params = $allPassengerIds;
                        if ($groupFlightNumber) $params[] = $groupFlightNumber;
                        $stmt = $dbconnect->prepare($query);
                        $stmt->execute($params);
                        $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $passengers = [];
                    }
                } else {
                    // Not in a group, show only this passenger's bookings
                    $stmt = $dbconnect->prepare("
                        SELECT 
                            b.booking_id,
                            b.flight_number,
                            p.passenger_id,
                            p.first_name,
                            p.last_name,
                            p.passport_number,
                            CONCAT(p.first_name, ' ', p.last_name) as passenger_name,
                            bp.check_in_status
                        FROM passengers p
                        JOIN booking_passengers bp ON p.passenger_id = bp.passenger_id
                        JOIN bookings b ON bp.booking_id = b.booking_id
                        WHERE p.passport_number = ?
                        ORDER BY b.booking_id, b.flight_number, p.last_name, p.first_name
                    ");
                    $stmt->execute([$passportNumber]);
                    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                if ($passengers && count($passengers) > 0) {
                    $flightNumber = $passengers[0]['flight_number'];
                    $flightInfo = [
                        'flight_number' => $flightNumber,
                        'destination' => '',
                        'departure_time' => '',
                        'gate' => ''
                    ];
                    $stmtFlight = $dbconnect->prepare("SELECT flight_number, destination, departure_time, gate FROM flights WHERE flight_number = ? LIMIT 1");
                    if ($stmtFlight->execute([$flightNumber])) {
                        $row = $stmtFlight->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $flightInfo = $row;
                        }
                    }
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'booking_id' => $passengers[0]['booking_id'],
                        'flight_number' => $flightNumber,
                        'flight_info' => $flightInfo,
                        'passengers' => $passengers
                    ]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'No passenger found with this passport number.'
                    ]);
                }
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'No passenger found with this passport number.'
                ]);
                exit;
            }
        }
        $bookingId = $_POST['booking_id'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        if ($bookingId) {
            // 1. Get group_id from groups table
            $stmtGroup = $dbconnect->prepare("SELECT group_id FROM groups WHERE booking_id = ? LIMIT 1");
            $stmtGroup->execute([$bookingId]);
            $groupRow = $stmtGroup->fetch(PDO::FETCH_ASSOC);
            if ($groupRow && $groupRow['group_id']) {
                $groupId = $groupRow['group_id'];
                // 2. Get all group members with LEFT JOIN to booking_passengers for this booking
                $query = "
                    SELECT 
                        gm.passenger_id,
                        p.first_name,
                        p.last_name,
                        CONCAT(p.first_name, ' ', p.last_name) as passenger_name,
                        bp.booking_id,
                        b.flight_number,
                        bp.check_in_status
                    FROM group_members gm
                    JOIN passengers p ON gm.passenger_id = p.passenger_id
                    LEFT JOIN booking_passengers bp ON bp.passenger_id = gm.passenger_id AND bp.booking_id = ?
                    LEFT JOIN bookings b ON bp.booking_id = b.booking_id
                    WHERE gm.group_id = ?
                ";
                $params = [$bookingId, $groupId];
                $stmt = $dbconnect->prepare($query);
                $stmt->execute($params);
                $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Not a group booking, search for all passengers in booking
            $stmt = $dbconnect->prepare("
                SELECT 
                    b.booking_id,
                    b.flight_number,
                    p.passenger_id,
                    p.first_name,
                    p.last_name,
                    CONCAT(p.first_name, ' ', p.last_name) as passenger_name,
                    bp.check_in_status
                FROM bookings b
                JOIN booking_passengers bp ON b.booking_id = bp.booking_id
                JOIN passengers p ON bp.passenger_id = p.passenger_id
                WHERE b.booking_id = ? " . ($lastName ? "AND p.last_name = ?" : "") . "
                ORDER BY p.last_name, p.first_name
            ");
            if ($lastName) {
                $stmt->execute([$bookingId, $lastName]);
            } else {
                $stmt->execute([$bookingId]);
            }
            $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($passengers && count($passengers) > 0) {
                // Fetch flight details
                $flightNumber = $passengers[0]['flight_number'];
                // Always initialize with all keys
                $flightInfo = [
                    'flight_number' => $flightNumber,
                    'destination' => '',
                    'departure_time' => '',
                    'gate' => ''
                ];
                $stmtFlight = $dbconnect->prepare("SELECT flight_number, destination, departure_time, gate FROM flights WHERE flight_number = ? LIMIT 1");
                if ($stmtFlight->execute([$flightNumber])) {
                    $row = $stmtFlight->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $flightInfo = $row;
                    }
                }
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'booking_id' => $bookingId,
                    'flight_number' => $flightNumber,
                    'flight_info' => $flightInfo,
                    'passengers' => $passengers
                ]);
            } else {
                $message = $lastName ? "No passengers found for this booking and last name." : "No passengers found for this booking.";
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $message
                ]);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Booking ID is required'
            ]);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// 1. Refactor batch_check_in handler to only assign seats and return assignments, do NOT update check-in status or create boarding passes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_check_in'])) {
    // Debug: Log the entire $_POST and passenger_ids to error_log
    error_log('DEBUG batch_check_in $_POST: ' . print_r($_POST, true));
    $bookingId = $_POST['booking_id'] ?? '';
    $passengerIds = isset($_POST['passenger_ids']) ? (array)$_POST['passenger_ids'] : [];
    error_log('DEBUG batch_check_in passengerIds: ' . print_r($passengerIds, true));
    $assignedSeats = [];
    if ($bookingId && is_array($passengerIds) && count($passengerIds) > 0) {
        $stmt = $dbconnect->prepare("
            SELECT p.passenger_id, p.passport_number, p.first_name, p.last_name, b.flight_number, b.fare_class
            FROM bookings b
            JOIN booking_passengers bp ON b.booking_id = bp.booking_id
            JOIN passengers p ON bp.passenger_id = p.passenger_id
            WHERE b.booking_id = ? AND p.passenger_id IN (" . implode(',', array_fill(0, count($passengerIds), '?')) . ")
        ");
        $stmt->execute(array_merge([$bookingId], $passengerIds));
        $passengerData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$passengerData) $passengerData = [];
        $flightNumber = $passengerData[0]['flight_number'] ?? null;
        $stmtSeats = $dbconnect->prepare("SELECT seat_id, seat_number, seat_class, status FROM seats WHERE flight_number = ? AND status = 'Available' ORDER BY seat_class, seat_number ASC");
        $stmtSeats->execute([$flightNumber]);
        $allSeats = $stmtSeats->fetchAll(PDO::FETCH_ASSOC);
        $seatsByClass = ['First Class' => [], 'Business Class' => [], 'Economy' => []];
        foreach ($allSeats as $seat) {
            $class = $seat['seat_class'];
            if (isset($seatsByClass[$class])) {
                $seatsByClass[$class][] = $seat;
            } else {
                $seatsByClass[$class] = [$seat];
            }
        }
        $totalSeats = count($allSeats);
        $firstClassCount = max(1, round($totalSeats * 0.10));
        $businessClassCount = max(1, round($totalSeats * 0.30));
        foreach ($passengerData as $pdata) {
            $pid = $pdata['passenger_id'];
            $fareClass = $pdata['fare_class'] ?? 'Economy';
            $assignedSeat = null;
            if ($fareClass === 'First Class' && count($seatsByClass['First Class']) > 0) {
                $assignedSeat = array_shift($seatsByClass['First Class']);
            } elseif ($fareClass === 'Business Class' && count($seatsByClass['Business Class']) > 0) {
                $assignedSeat = array_shift($seatsByClass['Business Class']);
            } elseif (count($seatsByClass['Economy']) > 0) {
                $assignedSeat = array_shift($seatsByClass['Economy']);
            }
            if ($assignedSeat) {
                // Only update seat_number and assigned_seat_id, do NOT check in yet
                $stmtUpdate = $dbconnect->prepare("UPDATE booking_passengers SET seat_number = ?, assigned_seat_id = ? WHERE booking_id = ? AND passenger_id = ?");
                $stmtUpdate->execute([$assignedSeat['seat_number'], $assignedSeat['seat_id'], $bookingId, $pid]);
                // Update seat status to 'Occupied'
                $stmtSeat = $dbconnect->prepare("UPDATE seats SET status = 'Occupied' WHERE seat_id = ?");
                $stmtSeat->execute([$assignedSeat['seat_id']]);
                // Mark as checked in immediately after seat assignment
                $stmtCheckIn = $dbconnect->prepare("UPDATE booking_passengers SET check_in_status = 'Checked In' WHERE booking_id = ? AND passenger_id = ?");
                $stmtCheckIn->execute([$bookingId, $pid]);
                // Generate boarding pass immediately
                $stmtBP = $dbconnect->prepare("INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, issue_datetime) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number)");
                $stmtBP->execute([$pid, $flightNumber, $bookingId, $assignedSeat['seat_number']]);
                $assignedSeats[] = [
                    'passenger_id' => $pid,
                    'passport_number' => $pdata['passport_number'],
                    'seat_number' => $assignedSeat['seat_number'],
                    'seat_class' => $assignedSeat['seat_class'],
                    'passenger_name' => $pdata['first_name'] . ' ' . $pdata['last_name']
                ];
            }
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'assignedSeats' => $assignedSeats
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No passengers selected for seat assignment.'
        ]);
    }
    exit;
}
// 2. Add a new handler for final check-in after baggage is checked in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_check_in'])) {
    $bookingId = $_POST['booking_id'] ?? '';
    $passengerIds = $_POST['passenger_ids'] ?? [];
    $successCount = 0;
    $errorCount = 0;
    $messages = [];
    $checkInSummary = [];
    if ($bookingId && is_array($passengerIds) && count($passengerIds) > 0) {
        $stmt = $dbconnect->prepare("
            SELECT p.passenger_id, p.first_name, p.last_name, bp.seat_number, b.flight_number
            FROM bookings b
            JOIN booking_passengers bp ON b.booking_id = bp.booking_id
            JOIN passengers p ON bp.passenger_id = p.passenger_id
            WHERE b.booking_id = ? AND p.passenger_id IN (" . implode(',', array_fill(0, count($passengerIds), '?')) . ")
        ");
        $stmt->execute(array_merge([$bookingId], $passengerIds));
        $passengerData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($passengerData as $pdata) {
            $pid = $pdata['passenger_id'];
            $seatNumber = $pdata['seat_number'];
            $flightNumber = $pdata['flight_number'];
            // Update booking_passengers to set check_in_status
            $stmtUpdate = $dbconnect->prepare("UPDATE booking_passengers SET check_in_status = 'Checked In' WHERE booking_id = ? AND passenger_id = ?");
            $stmtUpdate->execute([$bookingId, $pid]);
            // Insert/update boarding_passes
            $stmtBP = $dbconnect->prepare("INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, issue_datetime) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number)");
            $stmtBP->execute([$pid, $flightNumber, $bookingId, $seatNumber]);
            // Fetch baggage for summary
            $stmtBag = $dbconnect->prepare("SELECT baggage_id, weight_kg, baggage_tag, special_handling FROM baggage WHERE passenger_id = ? AND booking_id = ?");
            $stmtBag->execute([$pid, $bookingId]);
            $baggage = $stmtBag->fetchAll(PDO::FETCH_ASSOC);
            $checkInSummary[] = [
                'passenger_id' => $pid,
                'passenger_name' => $pdata['first_name'] . ' ' . $pdata['last_name'],
                'seat_number' => $seatNumber,
                'baggage' => $baggage
            ];
            $successCount++;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'successCount' => $successCount,
            'checkInSummary' => $checkInSummary
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No passengers selected for final check-in.'
        ]);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_in'])) {
        try {
            $bookingId = $_POST['booking_id'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            
            if ($bookingId && $lastName) {
                // Find the booking and passenger
                $stmt = $dbconnect->prepare("
                    SELECT p.passenger_id, p.first_name, p.last_name, p.contact_phone, b.booking_id, b.flight_number
                    FROM bookings b
                    JOIN booking_passengers bp ON b.booking_id = bp.booking_id
                    JOIN passengers p ON bp.passenger_id = p.passenger_id
                    WHERE b.booking_id = ? AND p.last_name = ?
                ");
                $stmt->execute([$bookingId, $lastName]);
                $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bookingData) {
                    // Update booking_passengers table to mark as checked in
                    $stmt = $dbconnect->prepare("
                        UPDATE booking_passengers 
                        SET check_in_status = 'Checked In'
                        WHERE booking_id = ? AND passenger_id = ?
                    ");
                    $stmt->execute([$bookingId, $bookingData['passenger_id']]);
                    
                    // Create boarding pass (without seat number)
                    $stmt = $dbconnect->prepare("
                        INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, issue_datetime)
                        VALUES (?, ?, ?, NULL, NOW())
                    ");
                    $stmt->execute([$bookingData['passenger_id'], $bookingData['flight_number'], $bookingId]);
                    
                    $passengerName = $bookingData['first_name'] . ' ' . $bookingData['last_name'];
                    $successMessage = "✅ Passenger $passengerName successfully checked in for flight {$bookingData['flight_number']}";
                } else {
                    $errorMessage = "❌ Booking not found or passenger last name doesn't match.";
                }
            } else {
                $errorMessage = "❌ Please fill in all required fields for passenger check-in.";
            }
        } catch (Exception $e) {
            $errorMessage = "❌ Error during check-in: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['multiple_baggage_checkin'])) {
        try {
            $packageId = $_POST['package_id'] ?? '';
            $baggageWeights = $_POST['baggage_weights'] ?? [];
            $baggagePassengerIds = $_POST['baggage_passenger_ids'] ?? [];
            $baggageSpecial = $_POST['specialHandling'] ?? [];
            
            if ($packageId && !empty($baggageWeights)) {
                $successCount = 0;
                $totalWeight = 0;
                $baggageIds = [];
                
                // Get booking ID for the first passenger with baggage
                $firstPassengerId = null;
                foreach ($baggagePassengerIds as $index => $baggagePassengerId) {
                    if (!empty($baggagePassengerId) && !empty($baggageWeights[$index])) {
                        $firstPassengerId = $baggagePassengerId;
                        break;
                    }
                }
                
                if (!$firstPassengerId) {
                    $errorMessage = "❌ Please provide at least one valid passport number for baggage check-in.";
                } else {
                    $stmt = $dbconnect->prepare("SELECT booking_id FROM booking_passengers WHERE passenger_id = ? LIMIT 1");
                    $stmt->execute([$firstPassengerId]);
                    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$booking) {
                        $errorMessage = "❌ Could not find a valid booking for the selected passenger(s).";
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'message' => $errorMessage
                        ]);
                        exit;
                    }
                    $bookingId = $booking['booking_id'];
                    
                    // Process each baggage item
                    foreach ($baggageWeights as $index => $weight) {
                        if (!empty($weight) && $weight > 0) {
                            $baggagePassengerId = $baggagePassengerIds[$index] ?? '';
                            if (empty($baggagePassengerId)) {
                                continue; // Skip if no passenger ID provided
                            }
                            
                            // Generate unique baggage ID
                            $baggageId = 'BAG' . date('Ymd') . strtoupper(substr(uniqid(), -6));
                            
                            // Generate baggage tag
                            $baggageTag = 'BT' . substr($baggagePassengerId, 0, 4) . strtoupper(uniqid());
                            
                            // Create baggage object
                            $baggage = new Baggage($baggageId, $baggagePassengerId, (float)$weight);
                            
                            // Check in baggage
                            $baggage->checkInBaggage();
                            
                            // Save to database with package information
                            $stmt = $dbconnect->prepare("
                                INSERT INTO baggage (baggage_id, passenger_id, booking_id, weight_kg, baggage_tag, screening_status, package_id, special_handling)
                                VALUES (?, ?, ?, ?, ?, 'Checked In', ?, ?)
                            ");
                            
                            $specialHandling = $baggageSpecial[$index] ?? '';
                            
                            if ($stmt->execute([$baggageId, $baggagePassengerId, $bookingId, $weight, $baggageTag, $packageId, $specialHandling])) {
                                $successCount++;
                                $totalWeight += (float)$weight;
                                $baggageIds[] = $baggageId;
                            }
                        }
                    }
                    
                    if ($successCount > 0) {
                        $successMessage = "✅ Successfully checked in $successCount baggage items (Total weight: {$totalWeight}kg)";
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'successCount' => $successCount
                        ]);
                        exit;
                    }
                    if (!empty($errorMessage)) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'message' => $errorMessage
                        ]);
                        exit;
                    }
                }
            } else {
                $errorMessage = "❌ Please fill in all required fields for baggage check-in.";
            }
        } catch (Exception $e) {
            $errorMessage = "❌ Error during baggage check-in: " . $e->getMessage();
        }
        // Always return JSON response for AJAX
        if (!empty($errorMessage)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $errorMessage
            ]);
            exit;
        }
        if (!empty($successMessage)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'successCount' => $successCount,
                // Optionally add baggageSummary or other data here
            ]);
            exit;
        }
    }
    
    if (isset($_POST['search_passenger'])) {
        $searchTerm = $_POST['search_term'] ?? '';
        if ($searchTerm) {
            try {
                $stmt = $dbconnect->prepare("
                    SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as name, bp.seat_number, bp.flight_number, f.destination, f.departure_time
                    FROM passengers p
                    LEFT JOIN boarding_passes bp ON p.passenger_id = bp.passenger_id
                    LEFT JOIN flights f ON bp.flight_number = f.flight_number
                    WHERE CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR p.passenger_id LIKE ?
                ");
                $stmt->execute(["%$searchTerm%", "%$searchTerm%"]);
                $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errorMessage = "❌ Search error: " . $e->getMessage();
            }
        }
    }
}

// ... existing code ...
// Handle special needs submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_special_needs'])) {
    $passports = $_POST['special_passenger_id'] ?? [];
    $types = $_POST['special_need_type'] ?? [];
    $descs = $_POST['special_need_desc'] ?? [];
    $successCount = 0;
    for ($i = 0; $i < count($passports); $i++) {
        $passport = trim($passports[$i] ?? '');
        $type = trim($types[$i] ?? '');
        $desc = trim($descs[$i] ?? '');
        if ($passport && $type) {
            // Look up passenger_id from passport_number
            $stmtLookup = $dbconnect->prepare("SELECT passenger_id FROM passengers WHERE passport_number = ? LIMIT 1");
            $stmtLookup->execute([$passport]);
            $row = $stmtLookup->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['passenger_id'])) {
                $pid = $row['passenger_id'];
                $assistanceId = uniqid('ASST');
                $stmt = $dbconnect->prepare("INSERT INTO assistance_details (assistance_id, passenger_id, need_type, description, status) VALUES (?, ?, ?, ?, 'Requested')");
                if ($stmt->execute([$assistanceId, $pid, $type, $desc])) {
                    $successCount++;
                }
            } else {
                // Optionally log or handle the case where the passport number is not found
                error_log("Special needs submission: Passport number '$passport' not found in passengers table.");
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $successCount > 0,
        'message' => $successCount > 0 ? 'Special needs submitted.' : 'No valid special needs submitted.'
    ]);
    exit;
}
// ... existing code ...

// ... existing code ...
// Handle final check-in after special needs step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_special_needs_checkin'])) {
    // Save special needs if any
    $passports = $_POST['passport_number'] ?? [];
    $types = $_POST['special_need_type'] ?? [];
    $descs = $_POST['special_need_desc'] ?? [];
    $specialSuccess = 0;
    // Debug: log the arrays being processed
    error_log('Passports: ' . print_r($passports, true));
    error_log('Types: ' . print_r($types, true));
    error_log('Descs: ' . print_r($descs, true));
    for ($i = 0; $i < count($passports); $i++) {
        $passport = trim($passports[$i] ?? '');
        $type = trim($types[$i] ?? '');
        $desc = trim($descs[$i] ?? '');
        // Debug: log each passport being processed
        error_log("Processing special needs for passport: '$passport'");
        if ($passport && $type) {
            // Look up passenger_id from passport number
            $stmtLookup = $dbconnect->prepare("SELECT passenger_id FROM passengers WHERE passport_number = ? LIMIT 1");
            $stmtLookup->execute([$passport]);
            $row = $stmtLookup->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['passenger_id'])) {
                error_log("Found passenger_id: {$row['passenger_id']} for passport: '$passport'");
                $pid = $row['passenger_id'];
                $assistanceId = uniqid('ASST');
                $stmt = $dbconnect->prepare("INSERT INTO assistance_details (assistance_id, passenger_id, need_type, description, status) VALUES (?, ?, ?, ?, 'Requested')");
                if ($stmt->execute([$assistanceId, $pid, $type, $desc])) {
                $specialSuccess++;
                }
            } else {
                error_log("NOT FOUND: Passport number '$passport' not found in passengers table.");
            }
        }
    }
    // Finalize check-in for passengers
    $finalPassengerIds = $_POST['final_passenger_ids'] ?? [];
    $successCount = 0;
    foreach ($finalPassengerIds as $pid) {
        // Mark as checked in (update booking_passengers, create boarding pass, etc.)
        $stmt = $dbconnect->prepare("SELECT booking_id, flight_number, seat_number FROM booking_passengers WHERE passenger_id = ? LIMIT 1");
        $stmt->execute([$pid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $bookingId = $row['booking_id'];
            $flightNumber = $row['flight_number'];
            $seatNumber = $row['seat_number'];
            // Update booking_passengers to set check_in_status
            $stmtUpdate = $dbconnect->prepare("UPDATE booking_passengers SET check_in_status = 'Checked In' WHERE booking_id = ? AND passenger_id = ?");
            $stmtUpdate->execute([$bookingId, $pid]);
            // Insert/update boarding_passes
            $stmtBP = $dbconnect->prepare("INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, issue_datetime) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number)");
            $stmtBP->execute([$pid, $flightNumber, $bookingId, $seatNumber]);
            $successCount++;
        }
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $successCount > 0,
        'message' => $successCount > 0 ? 'Check-in completed.' : 'No valid passengers to check in.'
    ]);
    exit;
}
// ... existing code ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AeroCheck - Staff Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #2563eb;
        }

        .logo i {
            font-size: 2rem;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .staff-avatar {
            width: 40px;
            height: 40px;
            background: #2563eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .passenger-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .baggage-icon {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }

        .flight-icon {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .search-icon {
            background: linear-gradient(135deg, #a8edea, #fed6e3);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: white;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
        }

        /* Status Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .quick-action-btn {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #374151;
        }

        .quick-action-btn:hover {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
            transform: translateY(-2px);
        }

        /* Flight Status Panel */
        .flight-status {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .status-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .status-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
        }

        .status-label {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .status-details {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .status-details small {
            color: #9ca3af;
            font-size: 0.75rem;
            line-height: 1.2;
        }

        /* Search Results */
        .search-results {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .result-item {
            background: white;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #2563eb;
        }

        .result-item h4 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .result-item p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0.25rem 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .main-container {
                padding: 0 1rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        #specialNeedsTable.styled-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            margin-top: 0;
            margin-bottom: 0;
        }
        #specialNeedsTable.styled-table th, #specialNeedsTable.styled-table td {
            padding: 0.75rem;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-content">
        <div class="logo">
            <i class="fas fa-plane"></i>
            <span>AeroCheck Staff Dashboard</span>
        </div>
        <div class="staff-info">
            <div class="staff-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div style="font-weight: 600;">Staff Member</div>
                <div style="font-size: 0.875rem; color: #6b7280;">Check-in Agent</div>
            </div>
        </div>
    </div>
</header>

<div class="main-container">

    <?php if (!empty($successMessage)) : ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)) : ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Passenger Check-In Panel -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon passenger-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="card-title">Passenger Check-In</div>
        </div>
        <form id="passengerCheckInTableForm" onsubmit="return false;">
            <table style="width:100%; margin-bottom: 1rem;">
                <tr>
                    <td colspan="5" style="padding: 0.75rem;">
                        <label><input type="radio" name="search_method" id="search_by_booking" value="booking" checked onchange="toggleSearchMethod()"> Search by Booking ID & Last Name</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="search_method" id="search_by_passport" value="passport" onchange="toggleSearchMethod()"> Search by Passport Number</label>
                    </td>
                </tr>
            </table>
            <table id="passengerCheckInTable" style="width:100%; border-collapse:collapse; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                <thead>
                    <tr style="background: #f3f4f6;">
                        <th style="text-align:left; padding: 0.75rem;" id="th_booking_id">Booking ID</th>
                        <th style="text-align:left; padding: 0.75rem;" id="th_last_name">Passenger's Last Name</th>
                        <th style="text-align:left; padding: 0.75rem;" id="th_passport_number">Passport Number</th>
                        <th style="text-align:left; padding: 0.75rem;">Action</th>
                        <th style="text-align:left; padding: 0.75rem;">&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="passengerCheckInTableBody">
                    <tr>
                        <td style="padding: 0.75rem;" id="booking_id_cell">
                            <input type="text" class="form-input" name="booking_id" id="booking_id" placeholder="Enter booking ID (e.g., BKG001)" style="width: 100%;">
                            <small style="color: #6b7280; font-size: 0.875rem;">Enter the booking reference number to search</small>
                        </td>
                        <td style="padding: 0.75rem;" id="last_name_cell">
                            <input type="text" class="form-input" name="last_name" id="last_name" placeholder="Enter passenger's last name " style="width: 100%;">
                        </td>
                        <td style="padding: 0.75rem;" id="passport_number_cell">
                            <input type="text" class="form-input" name="passport_number" id="passport_number" placeholder="Enter passport number " style="width: 100%;">
                        </td>
                        <td style="padding: 0.75rem;">
                            <button type="button" onclick="searchBookingData(); return false;" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </td>
                        <td style="padding: 0.75rem;"></td>
                    </tr>
                    <!-- Checklist rows will be inserted here by JS -->
                </tbody>
            </table>
        </form>
        <!-- Add a container for seat assignment summary below the passenger check-in table -->
        <div id="seatAssignmentSummary" style="display:none; margin-top:2rem;"></div>
        <div style="margin-top: 1rem;">
        </div>
            </div>

    <!-- Baggage Handling Panel -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon baggage-icon">
                <i class="fas fa-suitcase"></i>
            </div>
            <div class="card-title">Multiple Baggage Check-In</div>
            </div>

        <!-- Baggage Package Selection -->
        <div class="form-group">
            <label class="form-label" for="baggage_package">
                <i class="fas fa-box"></i> Baggage Package
            </label>
            <select class="form-select" name="baggage_package" id="baggage_package" required onchange="updatePackageDetails()">
                <option value="">Select Package</option>
                <?php
                // Get baggage packages from database
                $stmt = $dbconnect->query("SELECT * FROM baggage_packages ORDER BY additional_weight_kg");
                foreach ($stmt as $package) {
                    echo "<option value='" . htmlspecialchars($package['package_id']) . "' 
                          data-weight='" . htmlspecialchars($package['additional_weight_kg']) . "'
                          data-price='" . htmlspecialchars($package['price']) . "'
                          data-description='" . htmlspecialchars($package['description']) . "'>
                          " . htmlspecialchars($package['package_name']) . " - " . htmlspecialchars($package['additional_weight_kg']) . "kg - RM" . htmlspecialchars($package['price']) . "
                          </option>";
                }
                ?>
            </select>
            <div id="package_details" style="margin-top: 0.5rem; padding: 0.5rem; background: #f8fafc; border-radius: 4px; display: none;">
                <small id="package_description"></small><br>
                <small id="package_price"></small>
            </div>
            </div>

        <!-- Multiple Baggage Items -->
        <div class="form-group">
            <label class="form-label">
                <i class="fas fa-suitcase"></i> Baggage Items
            </label>
            <div id="baggage_items_container">
                <div class="baggage-item" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
            <div>
                            <label style="font-size: 0.875rem; color: #374151; margin-bottom: 0.25rem; display: block;">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-input" name="baggage_weights[]" 
                                   placeholder="Weight" required style="margin: 0;">
                        </div>
                        <div>
                            <label style="font-size: 0.875rem; color: #374151; margin-bottom: 0.25rem; display: block;">Passport Number</label>
                            <input type="text" class="form-input" name="baggage_passenger_ids[]" placeholder="Enter passport number" style="margin: 0;">
                        </div>
                        <div>
                            <label style="font-size: 0.875rem; color: #374151; margin-bottom: 0.25rem; display: block;">Special Handling</label>
                            <select class="form-select" name="specialHandling[]" style="margin: 0;">
                                <option value="">None</option>
                                <option value="fragile">Fragile</option>
                                <option value="oversized">Oversized</option>
                                <option value="valuable">Valuable</option>
                                <option value="perishable">Perishable</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="removeBaggageItem(this); return false;" style="padding: 0.5rem; min-width: auto;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addBaggageItem(event); return false;" style="margin-top: 0.5rem;">
                <i class="fas fa-plus"></i> Add Another Baggage Item
            </button>
            </div>

        <!-- Total Summary -->
        <div id="baggage_summary" style="background: #f8fafc; border-radius: 8px; padding: 1rem; margin-top: 1rem; display: none;">
            <h4 style="margin-bottom: 0.5rem; color: #374151;">Baggage Summary</h4>
            <div id="summary_details"></div>
    </div>

        <button type="button" onclick="submitMultipleBaggageCheckIn(event); return false;" class="btn btn-primary" style="margin-top: 1rem;">
            <i class="fas fa-check"></i>
            Check In All Baggage
        </button>
        <button type="button" onclick="showSummaryModal(); return false;" class="btn btn-info" id="viewSummaryBtn" style="margin-top: 1rem; margin-left: 0.5rem;" disabled>
            <i class="fas fa-list"></i> View Summary
        </button>
        <div style="margin-top: 1rem; display: inline-block;">
            <button type="button" id="confirmCheckInBtn" class="btn btn-success" style="min-width:180px; margin-left: 0.5rem;" disabled>
                <i class="fas fa-user-check"></i> Confirm Check-In
            </button>
        </div>

        <div class="card" style="margin-top:2rem;">
    <div class="card-header">
        <div class="card-icon search-icon">
            <i class="fas fa-wheelchair"></i>
        </div>
        <div class="card-title">Passenger Special Needs</div>
    </div>
    <form id="specialNeedsInputForm" onsubmit="submitSpecialNeedsInput(event); return false;">
        <table style="width:100%; border-collapse:collapse; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="text-align:left; padding: 0.75rem;">Passport Number</th>
                    <th style="text-align:left; padding: 0.75rem;">Special Need Type</th>
                    <th style="text-align:left; padding: 0.75rem;">Description (Optional)</th>
                    <th style="text-align:left; padding: 0.75rem;">Action</th>
                </tr>
            </thead>
            <tbody id="specialNeedsInputTableBody">
                <tr>
                    <td style="padding: 0.75rem;">
                        <input type="text" class="form-input" name="special_passenger_id[]" placeholder="Passport Number" required="">
                    </td>
                    <td style="padding: 0.75rem;">
                        <select class="form-select" name="special_need_type[]" required="">
                            <option value="">Select</option>
                            <option value="Wheelchair">Wheelchair</option>
                            <option value="Visual Assistance">Visual Assistance</option>
                            <option value="Hearing Assistance">Hearing Assistance</option>
                            <option value="Medical">Medical</option>
                            <option value="Other">Other</option>
                        </select>
                    </td>
                    <td style="padding: 0.75rem;">
                        <input type="text" class="form-input" name="special_need_desc[]" placeholder="Description (optional)">
                    </td>
                    <td style="padding: 0.75rem;">
                        <button type="button" class="btn btn-secondary" onclick="removeSpecialNeedsInputRow(this); return false;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-secondary" onclick="addSpecialNeedsInputRow(event); return false;" style="margin-top: 0.5rem;">
            <i class="fas fa-plus"></i> Add Another Special Need
        </button>
        <button type="submit" class="btn btn-primary" id="specialNeedsInputSubmitBtn" style="margin-top: 1rem;">
            <i class="fas fa-check"></i> Submit
        </button>
    </form>
</div>
    </div>
            </div>

<script>
// --- Utility: Notification ---
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
    `;
    document.querySelector('.main-container').insertBefore(notification, document.querySelector('.main-container').firstChild);
    setTimeout(() => { notification.remove(); }, 3000);
}

// --- Passenger Checklist Rendering ---
function renderPassengerChecklist(passengers, bookingId, flightInfo) {
    const bookingIdInput = document.getElementById('booking_id');
    if (bookingIdInput && bookingId) {
        bookingIdInput.value = bookingId;
        console.log('DEBUG (renderPassengerChecklist) booking_id input value set to:', bookingIdInput.value);
    }
    const tableBody = document.getElementById('passengerCheckInTableBody');
    while (tableBody.rows.length > 1) tableBody.deleteRow(1);
    // Show flight details if available
    if (flightInfo && flightInfo.flight_number) {
        const flightRow = tableBody.insertRow(-1);
        flightRow.innerHTML = `<td colspan="4" style="padding-bottom:0.5rem;">
            <div style="background:#f3f4f6; border-radius:8px; padding:1rem; margin-bottom:0.5rem;">
                <strong>Flight:</strong> ${flightInfo.flight_number} &nbsp; | &nbsp;
                <strong>Destination:</strong> ${flightInfo.destination || '-'} &nbsp; | &nbsp;
                <strong>Departure:</strong> ${flightInfo.departure_time ? (new Date(flightInfo.departure_time)).toLocaleString() : '-'} &nbsp; | &nbsp;
                <strong>Gate:</strong> ${flightInfo.gate || '-'}
            </div>
        </td>`;
    }
    if (!passengers || passengers.length === 0) {
        const row = tableBody.insertRow(-1);
        const cell = row.insertCell(0);
        cell.colSpan = 4;
        cell.innerHTML = '<div class="alert alert-error">No passengers found for this booking.</div>';
        return;
    }
    const headerRow = tableBody.insertRow(-1);
    headerRow.innerHTML = `<td colspan="4" style="padding-top:1.5rem; padding-bottom:0.5rem;">
        <h4 style="margin:0; color: #2563eb; font-weight: 600; letter-spacing: 0.5px;">
            <i class='fas fa-list-check'></i> Passenger Check-In List
        </h4>
    </td>`;
    passengers.forEach(p => {
        const row = tableBody.insertRow(-1);
        row.innerHTML = `
            <td style='padding: 0.75rem;'><input type='checkbox' name='passenger_ids[]' value='${p.passenger_id}' ${p.check_in_status === 'Checked In' ? 'disabled checked' : ''}></td>
            <td style='padding: 0.75rem;'>${p.passenger_name}</td>
            <td style='padding: 0.75rem;'>${p.passport_number || ''}</td>
            <td style='padding: 0.75rem;'>${p.check_in_status}</td>
        `;
    });
    const btnRow = tableBody.insertRow(-1);
    btnRow.innerHTML = `<td colspan='4' style='padding-top:1rem;'><button type='button' class='btn btn-primary' onclick='submitBatchCheckIn(event)'><i class='fas fa-check'></i> Check In Selected</button></td>`;
}

// --- Booking Search and Checklist ---
function searchBookingData() {
    const bookingIdInput = document.getElementById('booking_id');
    const lastNameInput = document.getElementById('last_name');
    const passportInput = document.getElementById('passport_number');
    const byBooking = document.getElementById('search_by_booking').checked;
    const bookingId = bookingIdInput.value.trim();
    const lastName = lastNameInput.value.trim();
    const passportNumber = passportInput.value.trim();
    if (byBooking) {
    if (bookingId) {
        bookingIdInput.style.borderColor = '#fbbf24';
        bookingIdInput.style.backgroundColor = '#fef3c7';
        const formData = new FormData();
        formData.append('search_booking', '1');
        formData.append('booking_id', bookingId);
            formData.append('last_name', lastName);
        fetch('StaffUI.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
                console.log('Booking search AJAX response:', data); // Debug: log AJAX response
                const summaryBtn = document.getElementById('viewSummaryBtn');
                if (data.success && data.passengers && data.passengers.length > 0) {
                if (lastNameInput && !lastName) lastNameInput.value = data.passengers[0].last_name;
                    if (bookingIdInput && data.booking_id) {
                        bookingIdInput.value = data.booking_id;
                        console.log('DEBUG booking_id input value set to:', bookingIdInput.value);
                    }
                bookingIdInput.style.borderColor = '#10b981';
                bookingIdInput.style.backgroundColor = '#d1fae5';
                if (lastNameInput) {
                    lastNameInput.style.borderColor = '#10b981';
                    lastNameInput.style.backgroundColor = '#d1fae5';
                }
                showNotification(`✅ Booking found: ${data.passengers.length} passenger(s)`, 'success');
                // Store flight info globally for summary
                window.currentFlightInfo = data.flight_info || {};
                    window.lastBookingPassengers = data.passengers || [];
                    window.lastBookingFlightInfo = data.flight_info || {};
                    console.log('Set globals:', window.currentFlightInfo, window.lastBookingFlightInfo, window.lastBookingPassengers);
                    // Enable the summary button
                    if (summaryBtn) summaryBtn.disabled = false;
                // Pass flight info if available
                renderPassengerChecklist(data.passengers, data.booking_id, data.flight_info || {
                    flight_number: data.flight_number
                });
            } else {
                    // Disable the summary button if search fails or no passengers
                    if (summaryBtn) summaryBtn.disabled = true;
                bookingIdInput.style.borderColor = '#ef4444';
                bookingIdInput.style.backgroundColor = '#fee2e2';
                if (lastNameInput) {
                    lastNameInput.style.borderColor = '#ef4444';
                    lastNameInput.style.backgroundColor = '#fee2e2';
                }
                showNotification(`❌ ${data.message || 'Booking not found'}`, 'error');
                renderPassengerChecklist([], '', null);
            }
        })
        .catch(error => {
            bookingIdInput.style.borderColor = '#ef4444';
            bookingIdInput.style.backgroundColor = '#fee2e2';
            if (lastNameInput) {
                lastNameInput.style.borderColor = '#ef4444';
                lastNameInput.style.backgroundColor = '#fee2e2';
            }
            showNotification('❌ Error searching booking data', 'error');
            renderPassengerChecklist([], '', null);
        });
    } else {
        bookingIdInput.style.borderColor = '#e5e7eb';
        bookingIdInput.style.backgroundColor = '#ffffff';
        if (lastNameInput) {
            lastNameInput.style.borderColor = '#e5e7eb';
            lastNameInput.style.backgroundColor = '#ffffff';
        }
        renderPassengerChecklist([], '', null);
        }
    } else {
        if (passportNumber) {
            bookingIdInput.style.borderColor = '#e5e7eb';
            bookingIdInput.style.backgroundColor = '#ffffff';
            lastNameInput.style.borderColor = '#e5e7eb';
            lastNameInput.style.backgroundColor = '#ffffff';
            const formData = new FormData();
            formData.append('search_booking', '1');
            formData.append('passport_number', passportNumber);
            fetch('StaffUI.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Booking search AJAX response:', data); // Debug: log AJAX response
                const summaryBtn = document.getElementById('viewSummaryBtn');
                if (data.success && data.passengers && data.passengers.length > 0) {
                    if (lastNameInput && !lastName) lastNameInput.value = data.passengers[0].last_name;
                    bookingIdInput.style.borderColor = '#10b981';
                    bookingIdInput.style.backgroundColor = '#d1fae5';
                    if (lastNameInput) {
                        lastNameInput.style.borderColor = '#10b981';
                        lastNameInput.style.backgroundColor = '#d1fae5';
                    }
                    showNotification(`✅ Booking found: ${data.passengers.length} passenger(s)`, 'success');
                    // Store flight info globally for summary
                    window.currentFlightInfo = data.flight_info || {};
                    window.lastBookingPassengers = data.passengers || [];
                    window.lastBookingFlightInfo = data.flight_info || {};
                    console.log('Set globals:', window.currentFlightInfo, window.lastBookingFlightInfo, window.lastBookingPassengers);
                    // Enable the summary button
                    if (summaryBtn) summaryBtn.disabled = false;
                    // Pass flight info if available
                    renderPassengerChecklist(data.passengers, data.booking_id, data.flight_info || {
                        flight_number: data.flight_number
                    });
                } else {
                    // Disable the summary button if search fails or no passengers
                    if (summaryBtn) summaryBtn.disabled = true;
                    bookingIdInput.style.borderColor = '#ef4444';
                    bookingIdInput.style.backgroundColor = '#fee2e2';
                    if (lastNameInput) {
                        lastNameInput.style.borderColor = '#ef4444';
                        lastNameInput.style.backgroundColor = '#fee2e2';
                    }
                    showNotification(`❌ ${data.message || 'Booking not found'}`, 'error');
                    renderPassengerChecklist([], '', null);
                }
            })
            .catch(error => {
                bookingIdInput.style.borderColor = '#ef4444';
                bookingIdInput.style.backgroundColor = '#fee2e2';
                if (lastNameInput) {
                    lastNameInput.style.borderColor = '#ef4444';
                    lastNameInput.style.backgroundColor = '#fee2e2';
                }
                showNotification('❌ Error searching booking data', 'error');
                renderPassengerChecklist([], '', null);
            });
        } else {
            passportInput.style.borderColor = '#e5e7eb';
            passportInput.style.backgroundColor = '#ffffff';
            renderPassengerChecklist([], '', null);
        }
    }
}

// --- Batch Check-In ---
function submitBatchCheckIn(event) {
    event.preventDefault();
    const form = document.getElementById('passengerCheckInTableForm');
    const formData = new FormData(form);
    // Debug: log selected passenger IDs
    console.log('Selected passenger_ids:', formData.getAll('passenger_ids[]'));
    // Debug: log booking_id value from input
    const bookingIdInput = document.getElementById('booking_id');
    if (bookingIdInput) {
        console.log('DEBUG booking_id input value before submit:', bookingIdInput.value);
    } else {
        console.log('DEBUG booking_id input not found');
    }
    formData.append('batch_check_in', '1');
    // Ensure booking_id is always included and up-to-date
    let bookingId = '';
    if (bookingIdInput) {
        bookingId = bookingIdInput.value.trim();
    }
    formData.set('booking_id', bookingId); // set (overwrite) to ensure it's present
    fetch('StaffUI.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`✅ Seat assignment successful for ${data.assignedSeats.length} passenger(s).`, 'success');
            if (data.assignedSeats && data.assignedSeats.length > 0) {
                renderSeatAssignmentSummary(data.assignedSeats, {});
                // Show baggage entry form for these passengers, pass group members for dropdown
                showBaggageEntryForm(data.assignedSeats, window.lastBookingPassengers);
                // Store seat assignment summary globally for summary modal
                window.seatAssignmentSummary = data.assignedSeats;
            }
            // Disable confirm check-in button until baggage is checked in
            let confirmBtn = document.getElementById('confirmCheckInBtn');
            if (confirmBtn) confirmBtn.disabled = true;
        } else {
            showNotification(`❌ ${data.message || 'Seat assignment failed.'}`, 'error');
            document.getElementById('seatAssignmentSummary').style.display = 'none';
        }
    })
    .catch(error => {
        showNotification('❌ Error during seat assignment', 'error');
        document.getElementById('seatAssignmentSummary').style.display = 'none';
    });
}

// Show baggage entry form for selected passengers
function showBaggageEntryForm(assignedSeats, groupMembers) {
    // Only include checked-in group members in the dropdown
    const checkedInMembers = (groupMembers || []).filter(m => m.check_in_status === 'Checked In');
    window.selectedCheckInPassengers = assignedSeats.map(p => p.passenger_id);
    document.querySelector('.card .card-title').textContent = 'Baggage Check-In for Selected Passengers';
    const container = document.getElementById('baggage_items_container');
    container.innerHTML = '';

    assignedSeats.forEach(() => {
        const div = document.createElement('div');
        div.className = 'baggage-item';
        div.style = 'border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;';
        div.innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
            <div>
                    <label style="font-size: 0.875rem; color: #374151; margin-bottom: 0.25rem; display: block;">Weight (kg)</label>
                    <input type="number" step="0.1" class="form-input" name="baggage_weights[]" placeholder="Weight" required style="margin: 0;">
            </div>
                <div>
                    <label style="font-size: 0.875rem; color: #374151; margin-bottom: 0.25rem; display: block;">Passport Number</label>
                    <input type="text" class="form-input" name="baggage_passenger_ids[]" placeholder="Enter passport number" style="margin: 0;">
                </div>
                <div>
                    <label style="font-size: 0.875rem; color: #374151; margin-bottom: 0.25rem; display: block;">Special Handling</label>
                    <select class="form-select" name="specialHandling[]" style="margin: 0;">
                        <option value="">None</option>
                        <option value="fragile">Fragile</option>
                        <option value="oversized">Oversized</option>
                        <option value="valuable">Valuable</option>
                        <option value="perishable">Perishable</option>
                    </select>
                </div>
                <button type="button" class="btn btn-secondary" onclick="removeBaggageItem(this); return false;" style="padding: 0.5rem; min-width: auto;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(div);
    });

    document.getElementById('baggage_package').disabled = false;
    document.querySelector('button[onclick*="submitMultipleBaggageCheckIn"]').disabled = false;

    // Hide the add button if you want to restrict to one baggage per passenger
    document.querySelector('button[onclick*="addBaggageItem"]').style.display = 'none';

    // Hide remove buttons for pre-filled rows
    container.querySelectorAll('.btn-secondary').forEach(btn => btn.style.display = 'none');

    // Show confirm check-in button only after baggage is checked in
    let confirmBtn = document.getElementById('confirmCheckInBtn');
    if (!confirmBtn) {
        confirmBtn = document.createElement('button');
        confirmBtn.id = 'confirmCheckInBtn';
        confirmBtn.className = 'btn btn-success';
        confirmBtn.style = 'margin-top: 1rem; display: none;';
        confirmBtn.innerHTML = '<i class="fas fa-user-check"></i> Confirm Check-In';
        confirmBtn.onclick = finalizeCheckIn;
        document.querySelector('.card').appendChild(confirmBtn);
    }
    confirmBtn.style.display = 'none';
}

// After baggage is checked in, enable confirm check-in button
function submitMultipleBaggageCheckIn(event) {
    event.preventDefault();
    event.stopPropagation();
    const packageId = document.getElementById('baggage_package').value;
    const baggageWeights = document.querySelectorAll('input[name="baggage_weights[]"]');
    const baggagePassengerIds = document.querySelectorAll('input[name="baggage_passenger_ids[]"]');
    const baggageSpecial = document.querySelectorAll('select[name="specialHandling[]"]');
    if (!packageId) {
        showNotification('Please select baggage package.', 'error');
        return;
    }
    let hasValidBaggage = false;
    let totalWeight = 0;
    baggageWeights.forEach(input => {
        if (input.value.trim() && parseFloat(input.value) > 0) {
            hasValidBaggage = true;
            totalWeight += parseFloat(input.value);
        }
    });
    if (!hasValidBaggage) {
        showNotification('Please add at least one baggage item with weight.', 'error');
        return;
    }
    const packageSelect = document.getElementById('baggage_package');
    const selectedOption = packageSelect.options[packageSelect.selectedIndex];
    const weightAllowance = parseFloat(selectedOption.getAttribute('data-weight'));
    if (totalWeight > weightAllowance) {
        showNotification(`❌ Total baggage weight (${totalWeight}kg) exceeds package allowance (${weightAllowance}kg)`, 'error');
        packageSelect.style.borderColor = '#ef4444';
        packageSelect.style.backgroundColor = '#fee2e2';
        return;
    } else {
        packageSelect.style.borderColor = '';
        packageSelect.style.backgroundColor = '';
    }
    // Build FormData with correct field names
    const formData = new FormData();
    formData.append('multiple_baggage_checkin', '1');
    formData.append('package_id', packageId);
    baggageWeights.forEach(input => {
        formData.append('baggage_weights[]', input.value);
    });
    baggagePassengerIds.forEach(input => {
        formData.append('baggage_passenger_ids[]', input.value);
    });
    baggageSpecial.forEach(select => {
        formData.append('specialHandling[]', select.value);
    });
    // Store the last baggage data input by the user for modal summary
    window.lastBaggageData = [];
    for (let i = 0; i < baggageWeights.length; i++) {
        if (baggagePassengerIds[i].value && baggageWeights[i].value) {
            window.lastBaggageData.push({
                passenger_id: baggagePassengerIds[i].value,
                weight: baggageWeights[i].value,
                special: baggageSpecial[i].value
            });
        }
    }
    fetch('StaffUI.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`✅ ${data.successCount} baggage item(s) checked in.`, 'success');
            if (data.baggageSummary && data.baggageSummary.length > 0) {
                renderBaggageSummary(data.baggageSummary);
            }
            // Enable confirm check-in button
            let confirmBtn = document.getElementById('confirmCheckInBtn');
            if (confirmBtn) confirmBtn.disabled = false;
        } else {
            showNotification(`❌ ${data.message || 'Baggage check-in failed.'}`, 'error');
            document.getElementById('baggage_summary').style.display = 'none';
        }
    })
    .catch(error => {
        showNotification('❌ Error during baggage check-in', 'error');
        document.getElementById('baggage_summary').style.display = 'none';
    });
}

// Pre-fill special needs table with checked-in passengers
function prefillSpecialNeedsTable(passengerIds) {
    const tbody = document.getElementById('specialNeedsTableBody');
    tbody.innerHTML = '';
    passengerIds.forEach(pid => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td style="padding: 0.75rem;"><input type="text" class="form-input" name="special_passenger_id[]" value="${pid}" readonly></td>
            <td style="padding: 0.75rem;">
                <select class="form-select" name="special_need_type[]">
                    <option value="">Select</option>
                    <option value="Wheelchair">Wheelchair</option>
                    <option value="Visual Assistance">Visual Assistance</option>
                    <option value="Hearing Assistance">Hearing Assistance</option>
                    <option value="Medical">Medical</option>
                    <option value="Other">Other</option>
                </select>
            </td>
            <td style="padding: 0.75rem;"><input type="text" class="form-input" name="special_need_desc[]" placeholder="Description (optional)"></td>
            <td style="padding: 0.75rem;"><button type="button" class="btn btn-secondary" onclick="removeSpecialNeedsRow(this); return false;"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    });
}



// Finalize check-in after baggage is checked in
function finalizeCheckIn() {
    const bookingId = document.getElementById('booking_id').value;
    const passengerIds = window.selectedCheckInPassengers || [];
    if (!bookingId || !passengerIds.length) {
        showNotification('Missing booking or passenger selection.', 'error');
        return;
    }
    const formData = new FormData();
    formData.append('finalize_check_in', '1');
    formData.append('booking_id', bookingId);
    passengerIds.forEach(pid => formData.append('passenger_ids[]', pid));
    fetch('StaffUI.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`✅ ${data.successCount} passenger(s) checked in.`, 'success');
            if (data.checkInSummary && data.checkInSummary.length > 0) {
                renderCheckInSummary(data.checkInSummary);
            }
            // Optionally reset UI
            document.getElementById('confirmCheckInBtn').style.display = 'none';
        } else {
            showNotification(`❌ ${data.message || 'Final check-in failed.'}`, 'error');
        }
    })
    .catch(error => {
        showNotification('❌ Error during final check-in', 'error');
    });
}

// Render check-in summary after final check-in
function renderCheckInSummary(summary) {
    let html = `<div class='card' style='max-width:700px; margin:2rem auto;'>`;
    html += `<div class='card-header'><div class='card-icon passenger-icon'><i class='fas fa-user-check'></i></div><div class='card-title'>Check-In Summary</div></div>`;
    html += `<div style='padding:1rem;'><table style='width:100%; border-collapse:collapse; background:#fff; border-radius:8px;'><thead><tr style='background:#f3f4f6;'><th style='padding:0.75rem;'>Passenger</th><th style='padding:0.75rem;'>Seat</th><th style='padding:0.75rem;'>Baggage</th></tr></thead><tbody>`;
    summary.forEach(row => {
        html += `<tr>`;
        html += `<td style='padding:0.75rem;'>${row.passenger_name} (${row.passenger_id})</td>`;
        html += `<td style='padding:0.75rem;'>${row.seat_number}</td>`;
        html += `<td style='padding:0.75rem;'>`;
        if (row.baggage && row.baggage.length > 0) {
            html += '<ul style="margin:0; padding-left:1.2em;">';
            row.baggage.forEach(b => {
                html += `<li>Tag: ${b.baggage_tag}, Weight: ${b.weight_kg}kg${b.special_handling ? ', Special: ' + b.special_handling : ''}</li>`;
            });
            html += '</ul>';
        } else {
            html += '-';
        }
        html += `</td></tr>`;
    });
    html += `</tbody></table></div></div>`;
    // Insert or replace summary in DOM
    let container = document.getElementById('checkInSummaryContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'checkInSummaryContainer';
        document.querySelector('.main-container').appendChild(container);
    }
    container.innerHTML = html;
    container.scrollIntoView({behavior: 'smooth'});
}

// --- Event Listeners: Clear checklist on input change ---
document.addEventListener('DOMContentLoaded', function() {
    const bookingIdInput = document.getElementById('booking_id');
    const lastNameInput = document.getElementById('last_name');
    const tableBody = document.getElementById('passengerCheckInTableBody');
    function clearChecklist() {
        while (tableBody.rows.length > 1) tableBody.deleteRow(1);
    }
    if (bookingIdInput) bookingIdInput.addEventListener('input', clearChecklist);
    if (lastNameInput) lastNameInput.addEventListener('input', clearChecklist);
});

// Render seat assignment summary in StaffUI style
function renderSeatAssignmentSummary(assignments, nameMap) {
    const container = document.getElementById('seatAssignmentSummary');
    if (!assignments || assignments.length === 0) {
        container.style.display = 'none';
        return;
    }
    let html = `<div class="card" style="max-width:600px; margin:0 auto;">
        <div class="card-header">
            <div class="card-icon passenger-icon"><i class="fas fa-chair"></i></div>
            <div class="card-title">Seat Assignment Summary</div>
        </div>
        <div style="padding:1rem;">
            <table style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th style="text-align:left; padding:0.75rem;">Passport Number</th>
                        <th style="text-align:left; padding:0.75rem;">Name</th>
                        <th style="text-align:left; padding:0.75rem;">Seat Number</th>
                        <th style="text-align:left; padding:0.75rem;">Seat Class</th>
                    </tr>
                </thead>
                <tbody>`;
    assignments.forEach(a => {
        html += `<tr>
            <td style="padding:0.75rem;">${a.passport_number}</td>
            <td style="padding:0.75rem;">${a.passenger_name || (nameMap[a.passenger_id] || '')}</td>
            <td style="padding:0.75rem;">${a.seat_number}</td>
            <td style="padding:0.75rem;">${a.seat_class}</td>
        </tr>`;
    });
    html += `</tbody></table>
        </div>
    </div>`;
    container.innerHTML = html;
    container.style.display = '';
}

// Add updatePackageDetails for baggage package selection
function updatePackageDetails() {
    const packageSelect = document.getElementById('baggage_package');
    const packageDetails = document.getElementById('package_details');
    const packageDescription = document.getElementById('package_description');
    const packagePrice = document.getElementById('package_price');
    if (packageSelect.value) {
        const selectedOption = packageSelect.options[packageSelect.selectedIndex];
        const weight = parseFloat(selectedOption.getAttribute('data-weight'));
        const price = selectedOption.getAttribute('data-price');
        const description = selectedOption.getAttribute('data-description');
        packageDescription.textContent = description;
        packagePrice.textContent = `Price: RM${price} | Weight Allowance: ${weight}kg`;
        packageDetails.style.display = 'block';
        // Check if total baggage weight exceeds package
        let totalWeight = 0;
        document.querySelectorAll('input[name="baggage_weights[]"]').forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) totalWeight += val;
        });
        if (totalWeight > weight) {
            showNotification(`❌ Total baggage weight (${totalWeight}kg) exceeds package allowance (${weight}kg)`, 'error');
            packageSelect.style.borderColor = '#ef4444';
            packageSelect.style.backgroundColor = '#fee2e2';
        } else {
            packageSelect.style.borderColor = '';
            packageSelect.style.backgroundColor = '';
        }
    } else {
        packageDetails.style.display = 'none';
        packageSelect.style.borderColor = '';
        packageSelect.style.backgroundColor = '';
    }
    // Optionally update the summary
    if (typeof updateBaggageSummary === 'function') updateBaggageSummary();
}

function showSummaryModal() {
    // Check if flight info is available
    if (!window.currentFlightInfo || !window.currentFlightInfo.flight_number) {
        alert('Please perform a booking search first.');
        return;
    }
    let flightInfo = window.currentFlightInfo || {};
    const seatAssignments = window.seatAssignmentSummary || [];
    const baggage = window.lastBaggageData || [];
    const specialNeeds = window.lastSpecialNeedsData || [];
    // Debug: log what is being used for the modal
    console.log('Modal flightInfo:', flightInfo);
    console.log('Modal lastBookingFlightInfo:', window.lastBookingFlightInfo);
    console.log('Modal lastBookingPassengers:', window.lastBookingPassengers);
    // If flightInfo.flight_number is missing, try to get from seatAssignments or lastBookingPassengers
    if ((!flightInfo.flight_number || flightInfo.flight_number === '-') && seatAssignments.length > 0) {
        if (seatAssignments[0].flight_number) {
            flightInfo.flight_number = seatAssignments[0].flight_number;
        } else if (window.lastBookingPassengers && window.lastBookingPassengers.length > 0) {
            flightInfo.flight_number = window.lastBookingPassengers[0].flight_number;
        }
    }
    // If flightInfo is missing destination, departure_time, or gate, try to get from window.lastBookingFlightInfo
    if (window.lastBookingFlightInfo) {
        if (!flightInfo.destination && window.lastBookingFlightInfo.destination) {
            flightInfo.destination = window.lastBookingFlightInfo.destination;
        }
        if (!flightInfo.departure_time && window.lastBookingFlightInfo.departure_time) {
            flightInfo.departure_time = window.lastBookingFlightInfo.departure_time;
        }
        if (!flightInfo.gate && window.lastBookingFlightInfo.gate) {
            flightInfo.gate = window.lastBookingFlightInfo.gate;
        }
    }

    let html = `<div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;padding:1.2rem 1rem;margin-bottom:1.2rem;color:#fff;">
        <div><strong>Flight:</strong> ${flightInfo.flight_number || '-'}<\/div>
        <div><strong>Destination:</strong> ${flightInfo.destination || '-'}<\/div>
        <div><strong>Departure:</strong> ${flightInfo.departure_time ? (new Date(flightInfo.departure_time)).toLocaleString() : '-'}<\/div>
        <div><strong>Gate:</strong> ${flightInfo.gate || '-'}<\/div>
      <\/div>`;

    if (seatAssignments.length > 0) {
        html += `<div style="margin-bottom:1rem;"><div style="font-weight:600; color:#2563eb; margin-bottom:0.5rem;">Passengers</div>`;
        seatAssignments.forEach(a => {
            html += `<div style="background:#f3f4f6; border-radius:8px; padding:0.7rem; margin-bottom:0.5rem;">
                <div><b>Name:</b> ${a.passenger_name || '-'}<\/div>
                <div><b>Seat:</b> ${a.seat_number || '-'}<\/div>
                <div><b>Passport Number:</b> ${a.passport_number || '-'}<\/div>`;
            // Add baggage details for this passenger
            const passengerBaggage = baggage.filter(b => b.passenger_id === a.passenger_id);
            if (passengerBaggage.length > 0) {
                html += `<div><b>Baggage:</b><ul style='margin:0; padding-left:1.2em;'>`;
                passengerBaggage.forEach(b => {
                    html += `<li>Weight: ${b.weight}kg${b.special ? ', Special: ' + b.special : ''}</li>`;
                });
                html += `</ul></div>`;
            }
            html += `</div>`;
        });
        html += `</div>`;
    } else {
        html += `<div style='margin-bottom:1rem; color:#888;'>No passengers checked in yet.</div>`;
    }

    if (specialNeeds.length > 0) {
        html += `<div style="margin-bottom:1rem;"><div style="font-weight:600; color:#2563eb; margin-bottom:0.5rem;">Special Needs</div>`;
        specialNeeds.forEach(sn => {
            html += `<div style="background:#fef3c7; border-radius:8px; padding:0.6rem; margin-bottom:0.3rem;">
                <div><b>Passenger:</b> ${sn.passenger_id}</div>
                <div><b>Type:</b> ${sn.type}</div>
                <div><b>Description:</b> ${sn.desc || '-'}</div>
                <div><b>Passport Number:</b> ${sn.passport_number || '-'}</div>
            </div>`;
        });
        html += `</div>`;
    }

    // Show the modal
    let modal = document.getElementById('summaryModal');
    if (!modal) {
        // If modal doesn't exist, create it
        modal = document.createElement('div');
        modal.id = 'summaryModal';
        modal.style = 'display:flex; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;';
        modal.innerHTML = `
          <div style="background:#fff; border-radius:18px; max-width:420px; margin:5vh auto; padding:2rem 1.5rem 1.5rem 1.5rem; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative;">
            <button onclick="closeSummaryModal()" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:1.5rem; color:#888;">&times;</button>
            <div id="summaryContent"></div>
            <div style="margin-top:1.5rem; text-align:center;">
              <button class="btn btn-primary" onclick="closeSummaryModal()">Done</button>
            </div>
          </div>
        `;
        document.body.appendChild(modal);
    }
    document.getElementById('summaryContent').innerHTML = html;
    modal.style.display = 'flex';
}

function closeSummaryModal() {
    let modal = document.getElementById('summaryModal');
    if (modal) modal.style.display = 'none';
}
</script>

<?php
// AJAX handlers for manifest, baggage, assistance, counter
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'manifest' && isset($_GET['flight_number'])) {
        $flightNum = $_GET['flight_number'];
        $stmt = $dbconnect->prepare("SELECT CONCAT(p.first_name, ' ', p.last_name) as name, bp.seat_number FROM boarding_passes bp JOIN passengers p ON bp.passenger_id = p.passenger_id WHERE bp.flight_number = ?");
        $stmt->execute([$flightNum]);
        echo "<h4>Passenger Manifest for Flight $flightNum</h4><ul>";
        foreach ($stmt as $row) {
            echo "<li>".htmlspecialchars($row['name'])." (Seat: ".htmlspecialchars($row['seat_number']).")</li>";
        }
        echo "</ul>";
        exit;
    }
    if ($_GET['ajax'] === 'baggage' && isset($_GET['passenger_id'])) {
        $pid = $_GET['passenger_id'];
        $stmt = $dbconnect->prepare("SELECT baggage_id, passenger_id, weight_kg, baggage_tag, screening_status, special_handling FROM baggage WHERE passenger_id = ?");
        $stmt->execute([$pid]);
        echo "<h4>Baggage for Passenger $pid</h4><ul>";
        foreach ($stmt as $bag) {
            $specialHandling = !empty($bag['special_handling']) ? " ({$bag['special_handling']})" : "";
                    echo "<li>Tag: ".htmlspecialchars($bag['baggage_tag']).", Weight: ".htmlspecialchars($bag['weight_kg'])."kg, Status: ".htmlspecialchars($bag['screening_status']).$specialHandling."</li>";
        }
        echo "</ul>";
        exit;
    }
    if ($_GET['ajax'] === 'assistance' && isset($_GET['passenger_id'])) {
        $pid = $_GET['passenger_id'];
        $stmt = $dbconnect->prepare("SELECT * FROM assistance_details WHERE passenger_id = ?");
        $stmt->execute([$pid]);
        echo "<h4>Assistance for Passenger $pid</h4><ul>";
        foreach ($stmt as $ad) {
            echo "<li>Type: ".htmlspecialchars($ad['need_type']).", Status: ".htmlspecialchars($ad['status']).", Desc: ".htmlspecialchars($ad['description'])."</li>";
        }
        echo "</ul>";
        exit;
    }

}
?>

<script>
function renderPassengerChecklist(passengers, bookingId, flightInfo) {
    const bookingIdInput = document.getElementById('booking_id');
    if (bookingIdInput && bookingId) {
        bookingIdInput.value = bookingId;
        console.log('DEBUG (renderPassengerChecklist) booking_id input value set to:', bookingIdInput.value);
    }
    const tableBody = document.getElementById('passengerCheckInTableBody');
    while (tableBody.rows.length > 1) tableBody.deleteRow(1);
    // Show flight details if available
    if (flightInfo && flightInfo.flight_number) {
        const flightRow = tableBody.insertRow(-1);
        flightRow.innerHTML = `<td colspan="4" style="padding-bottom:0.5rem;">
            <div style="background:#f3f4f6; border-radius:8px; padding:1rem; margin-bottom:0.5rem;">
                <strong>Flight:</strong> ${flightInfo.flight_number} &nbsp; | &nbsp;
                <strong>Destination:</strong> ${flightInfo.destination || '-'} &nbsp; | &nbsp;
                <strong>Departure:</strong> ${flightInfo.departure_time ? (new Date(flightInfo.departure_time)).toLocaleString() : '-'} &nbsp; | &nbsp;
                <strong>Gate:</strong> ${flightInfo.gate || '-'}
</div>
        </td>`;
    }
    if (!passengers || passengers.length === 0) {
        const row = tableBody.insertRow(-1);
        const cell = row.insertCell(0);
        cell.colSpan = 4;
        cell.innerHTML = '<div class="alert alert-error">No passengers found for this booking.</div>';
        return;
    }
    const headerRow = tableBody.insertRow(-1);
    headerRow.innerHTML = `<td colspan="4" style="padding-top:1.5rem; padding-bottom:0.5rem;">
        <h4 style="margin:0; color: #2563eb; font-weight: 600; letter-spacing: 0.5px;">
            <i class='fas fa-list-check'></i> Passenger Check-In List
        </h4>
    </td>`;
    passengers.forEach(p => {
        const row = tableBody.insertRow(-1);
        row.innerHTML = `
            <td style='padding: 0.75rem;'><input type='checkbox' name='passenger_ids[]' value='${p.passenger_id}' ${p.check_in_status === 'Checked In' ? 'disabled checked' : ''}></td>
            <td style='padding: 0.75rem;'>${p.passenger_name}</td>
            <td style='padding: 0.75rem;'>${p.passport_number || ''}</td>
            <td style='padding: 0.75rem;'>${p.check_in_status}</td>
        `;
    });
    const btnRow = tableBody.insertRow(-1);
    btnRow.innerHTML = `<td colspan='4' style='padding-top:1rem;'><button type='button' class='btn btn-primary' onclick='submitBatchCheckIn(event)'><i class='fas fa-check'></i> Check In Selected</button></td>`;
}

function searchBookingData() {
    console.log('searchBookingData loaded');
    const bookingIdInput = document.getElementById('booking_id');
    const lastNameInput = document.getElementById('last_name');
    const passportInput = document.getElementById('passport_number');
    const byBooking = document.getElementById('search_by_booking').checked;
    const bookingId = bookingIdInput.value.trim();
    const lastName = lastNameInput.value.trim();
    const passportNumber = passportInput.value.trim();
    if (byBooking) {
    if (bookingId) {
        bookingIdInput.style.borderColor = '#fbbf24';
        bookingIdInput.style.backgroundColor = '#fef3c7';
        const formData = new FormData();
        formData.append('search_booking', '1');
        formData.append('booking_id', bookingId);
            formData.append('last_name', lastName);
        fetch('StaffUI.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
                console.log('Booking search AJAX response:', data); // Debug: log AJAX response
                const summaryBtn = document.getElementById('viewSummaryBtn');
                if (data.success && data.passengers && data.passengers.length > 0) {
                if (lastNameInput && !lastName) lastNameInput.value = data.passengers[0].last_name;
                bookingIdInput.style.borderColor = '#10b981';
                bookingIdInput.style.backgroundColor = '#d1fae5';
                if (lastNameInput) {
                    lastNameInput.style.borderColor = '#10b981';
                    lastNameInput.style.backgroundColor = '#d1fae5';
                }
                showNotification(`✅ Booking found: ${data.passengers.length} passenger(s)`, 'success');
                    // Store flight info globally for summary
                    window.currentFlightInfo = data.flight_info || {};
                    window.lastBookingPassengers = data.passengers || [];
                    window.lastBookingFlightInfo = data.flight_info || {};
                    console.log('Set globals:', window.currentFlightInfo, window.lastBookingFlightInfo, window.lastBookingPassengers);
                    // Enable the summary button
                    if (summaryBtn) summaryBtn.disabled = false;
                // Pass flight info if available
                renderPassengerChecklist(data.passengers, data.booking_id, data.flight_info || {
                    flight_number: data.flight_number
                });
            } else {
                    // Disable the summary button if search fails or no passengers
                    if (summaryBtn) summaryBtn.disabled = true;
                bookingIdInput.style.borderColor = '#ef4444';
                bookingIdInput.style.backgroundColor = '#fee2e2';
                if (lastNameInput) {
                    lastNameInput.style.borderColor = '#ef4444';
                    lastNameInput.style.backgroundColor = '#fee2e2';
                }
                showNotification(`❌ ${data.message || 'Booking not found'}`, 'error');
                renderPassengerChecklist([], '', null);
            }
        })
        .catch(error => {
            bookingIdInput.style.borderColor = '#ef4444';
            bookingIdInput.style.backgroundColor = '#fee2e2';
            if (lastNameInput) {
                lastNameInput.style.borderColor = '#ef4444';
                lastNameInput.style.backgroundColor = '#fee2e2';
            }
            showNotification('❌ Error searching booking data', 'error');
            renderPassengerChecklist([], '', null);
        });
    } else {
        bookingIdInput.style.borderColor = '#e5e7eb';
        bookingIdInput.style.backgroundColor = '#ffffff';
        if (lastNameInput) {
            lastNameInput.style.borderColor = '#e5e7eb';
            lastNameInput.style.backgroundColor = '#ffffff';
        }
        renderPassengerChecklist([], '', null);
        }
    } else {
        if (passportNumber) {
            bookingIdInput.style.borderColor = '#e5e7eb';
            bookingIdInput.style.backgroundColor = '#ffffff';
            lastNameInput.style.borderColor = '#e5e7eb';
            lastNameInput.style.backgroundColor = '#ffffff';
            const formData = new FormData();
            formData.append('search_booking', '1');
            formData.append('passport_number', passportNumber);
            fetch('StaffUI.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Booking search AJAX response:', data); // Debug: log AJAX response
                const summaryBtn = document.getElementById('viewSummaryBtn');
                if (data.success && data.passengers && data.passengers.length > 0) {
                    if (lastNameInput && !lastName) lastNameInput.value = data.passengers[0].last_name;
                    bookingIdInput.style.borderColor = '#10b981';
                    bookingIdInput.style.backgroundColor = '#d1fae5';
                    if (lastNameInput) {
                        lastNameInput.style.borderColor = '#10b981';
                        lastNameInput.style.backgroundColor = '#d1fae5';
                    }
                    showNotification(`✅ Booking found: ${data.passengers.length} passenger(s)`, 'success');
                    // Store flight info globally for summary
                    window.currentFlightInfo = data.flight_info || {};
                    window.lastBookingPassengers = data.passengers || [];
                    window.lastBookingFlightInfo = data.flight_info || {};
                    console.log('Set globals:', window.currentFlightInfo, window.lastBookingFlightInfo, window.lastBookingPassengers);
                    // Enable the summary button
                    if (summaryBtn) summaryBtn.disabled = false;
                    // Pass flight info if available
                    renderPassengerChecklist(data.passengers, data.booking_id, data.flight_info || {
                        flight_number: data.flight_number
                    });
                } else {
                    // Disable the summary button if search fails or no passengers
                    if (summaryBtn) summaryBtn.disabled = true;
                    bookingIdInput.style.borderColor = '#ef4444';
                    bookingIdInput.style.backgroundColor = '#fee2e2';
                    if (lastNameInput) {
                        lastNameInput.style.borderColor = '#ef4444';
                        lastNameInput.style.backgroundColor = '#fee2e2';
                    }
                    showNotification(`❌ ${data.message || 'Booking not found'}`, 'error');
                    renderPassengerChecklist([], '', null);
                }
            })
            .catch(error => {
                bookingIdInput.style.borderColor = '#ef4444';
                bookingIdInput.style.backgroundColor = '#fee2e2';
                if (lastNameInput) {
                    lastNameInput.style.borderColor = '#ef4444';
                    lastNameInput.style.backgroundColor = '#fee2e2';
                }
                showNotification('❌ Error searching booking data', 'error');
                renderPassengerChecklist([], '', null);
            });
        } else {
            passportInput.style.borderColor = '#e5e7eb';
            passportInput.style.backgroundColor = '#ffffff';
            renderPassengerChecklist([], '', null);
        }
    }
}

function submitBatchCheckIn(event) {
    event.preventDefault();
    const form = document.getElementById('passengerCheckInTableForm');
    const formData = new FormData(form);
    // Debug: log selected passenger IDs
    console.log('Selected passenger_ids:', formData.getAll('passenger_ids[]'));
    // Debug: log booking_id value from input
    const bookingIdInput = document.getElementById('booking_id');
    if (bookingIdInput) {
        console.log('DEBUG booking_id input value before submit:', bookingIdInput.value);
    } else {
        console.log('DEBUG booking_id input not found');
    }
    formData.append('batch_check_in', '1');
    // Ensure booking_id is always included and up-to-date
    let bookingId = '';
    if (bookingIdInput) {
        bookingId = bookingIdInput.value.trim();
    }
    formData.set('booking_id', bookingId); // set (overwrite) to ensure it's present
    fetch('StaffUI.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`✅ Seat assignment successful for ${data.assignedSeats.length} passenger(s).`, 'success');
            if (data.assignedSeats && data.assignedSeats.length > 0) {
                renderSeatAssignmentSummary(data.assignedSeats, {});
                // Show baggage entry form for these passengers, pass group members for dropdown
                showBaggageEntryForm(data.assignedSeats, window.lastBookingPassengers);
                // Store seat assignment summary globally for summary modal
                window.seatAssignmentSummary = data.assignedSeats;
            }
            // Disable confirm check-in button until baggage is checked in
            let confirmBtn = document.getElementById('confirmCheckInBtn');
            if (confirmBtn) confirmBtn.disabled = true;
        } else {
            showNotification(`❌ ${data.message || 'Seat assignment failed.'}`, 'error');
            document.getElementById('seatAssignmentSummary').style.display = 'none';
        }
    })
    .catch(error => {
        showNotification('❌ Error during seat assignment', 'error');
        document.getElementById('seatAssignmentSummary').style.display = 'none';
    });
}

// Render seat assignment summary in StaffUI style, with name
function renderSeatAssignmentSummary(assignments, nameMap) {
    const container = document.getElementById('seatAssignmentSummary');
    if (!assignments || assignments.length === 0) {
        container.style.display = 'none';
        return;
    }
    let html = `<div class="card" style="max-width:600px; margin:0 auto;">
        <div class="card-header">
            <div class="card-icon passenger-icon"><i class="fas fa-chair"></i></div>
            <div class="card-title">Seat Assignment Summary</div>
        </div>
        <div style="padding:1rem;">
            <table style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th style="text-align:left; padding:0.75rem;">Passport Number</th>
                        <th style="text-align:left; padding:0.75rem;">Name</th>
                        <th style="text-align:left; padding:0.75rem;">Seat Number</th>
                        <th style="text-align:left; padding:0.75rem;">Seat Class</th>
                    </tr>
                </thead>
                <tbody>`;
    assignments.forEach(a => {
        html += `<tr>
            <td style="padding:0.75rem;">${a.passport_number}</td>
            <td style="padding:0.75rem;">${a.passenger_name || (nameMap[a.passenger_id] || '')}</td>
            <td style="padding:0.75rem;">${a.seat_number}</td>
            <td style="padding:0.75rem;">${a.seat_class}</td>
        </tr>`;
    });
    html += `</tbody></table>
        </div>
    </div>`;
    container.innerHTML = html;
    container.style.display = '';
}

function addBaggageItem(event) {
    event.preventDefault();
    const container = document.getElementById('baggage_items_container');
    // Find the first baggage-item as a template
    const template = container.querySelector('.baggage-item');
    if (!template) return;
    const clone = template.cloneNode(true);
    // Clear input values in the clone
    clone.querySelectorAll('input').forEach(input => input.value = '');
    clone.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    container.appendChild(clone);
}

function removeBaggageItem(btn) {
    const item = btn.closest('.baggage-item');
    const container = document.getElementById('baggage_items_container');
    if (container.querySelectorAll('.baggage-item').length > 1) {
        item.remove();
    } else {
        // If only one left, just clear its values
        item.querySelectorAll('input').forEach(input => input.value = '');
        item.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    }
}
</script>

    

<script>
function addSpecialNeedsRow(event) {
    event.preventDefault();
    const tbody = document.getElementById('specialNeedsTableBody');
    const template = tbody.querySelector('tr');
    const clone = template.cloneNode(true);
    clone.querySelectorAll('input').forEach(input => input.value = '');
    clone.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    tbody.appendChild(clone);
}
function removeSpecialNeedsRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('specialNeedsTableBody');
    if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
    } else {
        row.querySelectorAll('input').forEach(input => input.value = '');
        row.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    }
}

</script>

<!-- Special Needs Assistance Panel (hidden by default, shown after baggage check-in) -->


<?php
// ... existing code ...
// Handle final check-in after special needs step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_special_needs_checkin'])) {
    // Save special needs if any
    $passports = $_POST['passport_number'] ?? [];
    $types = $_POST['special_need_type'] ?? [];
    $descs = $_POST['special_need_desc'] ?? [];
    $specialSuccess = 0;
    // Debug: log the arrays being processed
    error_log('Passports: ' . print_r($passports, true));
    error_log('Types: ' . print_r($types, true));
    error_log('Descs: ' . print_r($descs, true));
    for ($i = 0; $i < count($passports); $i++) {
        $passport = trim($passports[$i] ?? '');
        $type = trim($types[$i] ?? '');
        $desc = trim($descs[$i] ?? '');
        // Debug: log each passport being processed
        error_log("Processing special needs for passport: '$passport'");
        if ($passport && $type) {
            // Look up passenger_id from passport number
            $stmtLookup = $dbconnect->prepare("SELECT passenger_id FROM passengers WHERE passport_number = ? LIMIT 1");
            $stmtLookup->execute([$passport]);
            $row = $stmtLookup->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['passenger_id'])) {
                error_log("Found passenger_id: {$row['passenger_id']} for passport: '$passport'");
                $pid = $row['passenger_id'];
                $assistanceId = uniqid('ASST');
                $stmt = $dbconnect->prepare("INSERT INTO assistance_details (assistance_id, passenger_id, need_type, description, status) VALUES (?, ?, ?, ?, 'Requested')");
                if ($stmt->execute([$assistanceId, $pid, $type, $desc])) {
                $specialSuccess++;
                }
            } else {
                error_log("NOT FOUND: Passport number '$passport' not found in passengers table.");
            }
        }
    }
    // Finalize check-in for passengers
    $finalPassengerIds = $_POST['final_passenger_ids'] ?? [];
    $successCount = 0;
    foreach ($finalPassengerIds as $pid) {
        // Mark as checked in (update booking_passengers, create boarding pass, etc.)
        $stmt = $dbconnect->prepare("SELECT booking_id, flight_number, seat_number FROM booking_passengers WHERE passenger_id = ? LIMIT 1");
        $stmt->execute([$pid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $bookingId = $row['booking_id'];
            $flightNumber = $row['flight_number'];
            $seatNumber = $row['seat_number'];
            // Update booking_passengers to set check_in_status
            $stmtUpdate = $dbconnect->prepare("UPDATE booking_passengers SET check_in_status = 'Checked In' WHERE booking_id = ? AND passenger_id = ?");
            $stmtUpdate->execute([$bookingId, $pid]);
            // Insert/update boarding_passes
            $stmtBP = $dbconnect->prepare("INSERT INTO boarding_passes (passenger_id, flight_number, booking_id, seat_number, issue_datetime) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE seat_number = VALUES(seat_number)");
            $stmtBP->execute([$pid, $flightNumber, $bookingId, $seatNumber]);
            $successCount++;
        }
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $successCount > 0,
        'message' => $successCount > 0 ? 'Check-in completed.' : 'No valid passengers to check in.'
    ]);
    exit;
}
// ... existing code ...
?>

<script>
function addSpecialNeedsInputRow(event) {
    event.preventDefault();
    const tbody = document.getElementById('specialNeedsInputTableBody');
    const template = tbody.querySelector('tr');
    const clone = template.cloneNode(true);
    clone.querySelectorAll('input').forEach(input => input.value = '');
    clone.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    tbody.appendChild(clone);
}
function removeSpecialNeedsInputRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('specialNeedsInputTableBody');
    if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
    } else {
        row.querySelectorAll('input').forEach(input => input.value = '');
        row.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    }
}
function submitSpecialNeedsInput(event) {
    event.preventDefault();
    const form = document.getElementById('specialNeedsInputForm');
    const formData = new FormData(form);
    formData.append('submit_special_needs', '1');
    fetch('StaffUI.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Special needs submitted successfully.', 'success');
            form.reset();
        } else {
            showNotification(data.message || 'Error submitting special needs.', 'error');
        }
    })
    .catch(() => {
        showNotification('Error submitting special needs.', 'error');
    });
}
</script>

<script>
function toggleSearchMethod() {
    const byBooking = document.getElementById('search_by_booking').checked;
    // Show/hide booking and last name fields
    document.getElementById('booking_id_cell').style.display = byBooking ? '' : 'none';
    document.getElementById('last_name_cell').style.display = byBooking ? '' : 'none';
    // Show/hide passport number field
    document.getElementById('passport_number_cell').style.display = byBooking ? 'none' : '';
    // Show/hide table headers
    document.getElementById('th_booking_id').style.display = byBooking ? '' : 'none';
    document.getElementById('th_last_name').style.display = byBooking ? '' : 'none';
    document.getElementById('th_passport_number').style.display = byBooking ? 'none' : '';
    // Remove required attribute from booking_id always
    document.getElementById('booking_id').removeAttribute('required');
    // Optionally clear the hidden fields
    if (byBooking) {
        document.getElementById('passport_number').value = '';
    } else {
        document.getElementById('booking_id').value = '';
        document.getElementById('last_name').value = '';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleSearchMethod();
});
</script>

</body>
</html>
