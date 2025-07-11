<?php
// Include database connection
require_once 'databaseconnect.php';

// Get database connection
$dbconnect = include 'databaseconnect.php';

// Set page title and styling
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AeroCheck - Booking Data</title>
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
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .header h1 {
            color: #2563eb;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #6b7280;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            opacity: 0.9;
        }

        .section {
            margin-bottom: 3rem;
        }

        .section h2 {
            color: #2563eb;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .table-container {
            overflow-x: auto;
            background: #f8fafc;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #2563eb;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f1f5f9;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #166534;
        }

        .status-checked-in {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-not-checked-in {
            background: #fef3c7;
            color: #92400e;
        }

        .status-economy {
            background: #f3f4f6;
            color: #374151;
        }

        .status-business {
            background: #fef3c7;
            color: #92400e;
        }

        .status-first-class {
            background: #fce7f3;
            color: #be185d;
        }

        .group-badge {
            background: #818cf8;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        .search-box {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-box input {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            flex: 1;
        }

        .search-box button {
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }

        .search-box button:hover {
            background: #1d4ed8;
        }

        .filter-options {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .filter-options select {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: white;
        }

        .export-btn {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            margin-left: auto;
        }

        .export-btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plane"></i> AeroCheck Booking Data</h1>
            <p>Comprehensive booking information and passenger details</p>
        </div>

        <?php
        try {
            // Get overall statistics
            $stats = [];
            
            // Total bookings
            $stmt = $dbconnect->query("SELECT COUNT(*) as total FROM bookings");
            $stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Checked in passengers
            $stmt = $dbconnect->query("SELECT COUNT(*) as checked_in FROM booking_passengers WHERE check_in_status = 'Checked In'");
            $stats['checked_in'] = $stmt->fetch(PDO::FETCH_ASSOC)['checked_in'];
            
            // Not checked in passengers
            $stmt = $dbconnect->query("SELECT COUNT(*) as not_checked_in FROM booking_passengers WHERE check_in_status = 'Not Checked In'");
            $stats['not_checked_in'] = $stmt->fetch(PDO::FETCH_ASSOC)['not_checked_in'];
            
            // Group bookings
            $stmt = $dbconnect->query("SELECT COUNT(*) as group_bookings FROM bookings WHERE is_group_booking = TRUE");
            $stats['group_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['group_bookings'];
            
            // Individual bookings
            $stmt = $dbconnect->query("SELECT COUNT(*) as individual_bookings FROM bookings WHERE is_group_booking = FALSE");
            $stats['individual_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['individual_bookings'];
            
            // Today's bookings
            $stmt = $dbconnect->query("SELECT COUNT(*) as today_bookings FROM bookings WHERE DATE(booking_date) = CURDATE()");
            $stats['today_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_bookings'];
        ?>
        
        <!-- Statistics Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $stats['total_bookings'] ?></h3>
                <p>Total Bookings</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['checked_in'] ?></h3>
                <p>Checked In Passengers</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['not_checked_in'] ?></h3>
                <p>Pending Check-ins</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['group_bookings'] ?></h3>
                <p>Group Bookings</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['individual_bookings'] ?></h3>
                <p>Individual Bookings</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['today_bookings'] ?></h3>
                <p>Today's Bookings</p>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by booking ID, passenger name, or flight number...">
            <button onclick="filterTable()"><i class="fas fa-search"></i> Search</button>
            <button class="export-btn" onclick="exportToCSV()"><i class="fas fa-download"></i> Export CSV</button>
        </div>

        <div class="filter-options">
            <select id="statusFilter" onchange="filterTable()">
                <option value="">All Check-in Status</option>
                <option value="Checked In">Checked In</option>
                <option value="Not Checked In">Not Checked In</option>
            </select>
            <select id="fareClassFilter" onchange="filterTable()">
                <option value="">All Fare Classes</option>
                <option value="Economy">Economy</option>
                <option value="Business">Business</option>
                <option value="First Class">First Class</option>
            </select>
            <select id="bookingTypeFilter" onchange="filterTable()">
                <option value="">All Booking Types</option>
                <option value="Individual">Individual</option>
                <option value="Group">Group</option>
            </select>
        </div>

        <!-- Detailed Booking Data Section -->
        <div class="section">
            <h2><i class="fas fa-list"></i> Detailed Booking Information</h2>
            <div class="table-container">
                <table id="bookingTable">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Flight Number</th>
                            <th>Passenger Name</th>
                            <th>Passenger ID</th>
                            <th>Booking Date</th>
                            <th>Fare Class</th>
                            <th>Booking Type</th>
                            <th>Check-in Status</th>
                            <th>Seat Number</th>
                            <th>Baggage Package</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get detailed booking data with passenger information
                        $stmt = $dbconnect->query("
                            SELECT 
                                b.booking_id,
                                b.flight_number,
                                b.booking_date,
                                b.status as booking_status,
                                b.is_group_booking,
                                b.fare_class,
                                p.passenger_id,
                                CONCAT(p.first_name, ' ', p.last_name) as passenger_name,
                                p.contact_phone,
                                bp.seat_number,
                                bp.check_in_status,
                                bp.purchased_baggage_package_id,
                                bp.additional_baggage_pieces,
                                bp.additional_baggage_weight_kg,
                                f.destination,
                                f.departure_time,
                                f.gate
                            FROM bookings b
                            JOIN booking_passengers bp ON b.booking_id = bp.booking_id
                            JOIN passengers p ON bp.passenger_id = p.passenger_id
                            JOIN flights f ON b.flight_number = f.flight_number
                            ORDER BY b.booking_date DESC, b.booking_id, p.last_name
                        ");
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $bookingType = $row['is_group_booking'] ? 'Group' : 'Individual';
                            $bookingTypeClass = $row['is_group_booking'] ? 'group-badge' : '';
                            
                            echo "<tr>";
                            echo "<td><strong>{$row['booking_id']}</strong></td>";
                            echo "<td>{$row['flight_number']} → {$row['destination']}</td>";
                            echo "<td>{$row['passenger_name']}</td>";
                            echo "<td>{$row['passenger_id']}</td>";
                            echo "<td>" . date('M d, Y H:i', strtotime($row['booking_date'])) . "</td>";
                            echo "<td><span class='status-badge status-{$row['fare_class']}'>{$row['fare_class']}</span></td>";
                            echo "<td><span class='status-badge {$bookingTypeClass}'>{$bookingType}</span></td>";
                            echo "<td><span class='status-badge status-" . strtolower(str_replace(' ', '-', $row['check_in_status'])) . "'>{$row['check_in_status']}</span></td>";
                            echo "<td>" . ($row['seat_number'] ?: 'Not Assigned') . "</td>";
                            echo "<td>" . ($row['purchased_baggage_package_id'] ?: 'None') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Flight Summary Section -->
        <div class="section">
            <h2><i class="fas fa-plane-departure"></i> Flight Summary</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Flight Number</th>
                            <th>Destination</th>
                            <th>Departure Time</th>
                            <th>Gate</th>
                            <th>Total Bookings</th>
                            <th>Checked In</th>
                            <th>Pending</th>
                            <th>Check-in Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $dbconnect->query("
                            SELECT 
                                f.flight_number,
                                f.destination,
                                f.departure_time,
                                f.gate,
                                COUNT(bp.id) as total_passengers,
                                SUM(CASE WHEN bp.check_in_status = 'Checked In' THEN 1 ELSE 0 END) as checked_in,
                                SUM(CASE WHEN bp.check_in_status = 'Not Checked In' THEN 1 ELSE 0 END) as pending
                            FROM flights f
                            LEFT JOIN bookings b ON f.flight_number = b.flight_number
                            LEFT JOIN booking_passengers bp ON b.booking_id = bp.booking_id
                            GROUP BY f.flight_number
                            ORDER BY f.departure_time
                        ");
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $checkInRate = $row['total_passengers'] > 0 ? 
                                round(($row['checked_in'] / $row['total_passengers']) * 100, 1) : 0;
                            
                            echo "<tr>";
                            echo "<td><strong>{$row['flight_number']}</strong></td>";
                            echo "<td>{$row['destination']}</td>";
                            echo "<td>" . date('M d, Y H:i', strtotime($row['departure_time'])) . "</td>";
                            echo "<td>{$row['gate']}</td>";
                            echo "<td>{$row['total_passengers']}</td>";
                            echo "<td><span class='status-badge status-checked-in'>{$row['checked_in']}</span></td>";
                            echo "<td><span class='status-badge status-not-checked-in'>{$row['pending']}</span></td>";
                            echo "<td><strong>{$checkInRate}%</strong></td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
        } catch (PDOException $e) {
            echo "<div style='background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
            echo "<strong>Database Error:</strong> " . $e->getMessage();
            echo "</div>";
        }
        ?>
    </div>

    <script>
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const fareClassFilter = document.getElementById('fareClassFilter').value;
            const bookingTypeFilter = document.getElementById('bookingTypeFilter').value;
            
            const table = document.getElementById('bookingTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const cells = row.getElementsByTagName('td');
                const bookingId = cells[0].textContent.toLowerCase();
                const flightNumber = cells[1].textContent.toLowerCase();
                const passengerName = cells[2].textContent.toLowerCase();
                const fareClass = cells[5].textContent;
                const bookingType = cells[6].textContent;
                const checkInStatus = cells[7].textContent;
                
                let showRow = true;
                
                // Search filter
                if (searchTerm && !bookingId.includes(searchTerm) && 
                    !flightNumber.includes(searchTerm) && 
                    !passengerName.includes(searchTerm)) {
                    showRow = false;
                }
                
                // Status filter
                if (statusFilter && checkInStatus !== statusFilter) {
                    showRow = false;
                }
                
                // Fare class filter
                if (fareClassFilter && fareClass !== fareClassFilter) {
                    showRow = false;
                }
                
                // Booking type filter
                if (bookingTypeFilter && bookingType !== bookingTypeFilter) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            }
        }
        
        function exportToCSV() {
            const table = document.getElementById('bookingTable');
            const rows = table.getElementsByTagName('tr');
            let csv = [];
            
            for (let row of rows) {
                const cells = row.getElementsByTagName('td');
                let rowData = [];
                for (let cell of cells) {
                    rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
                }
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'booking_data_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Add event listener for search input
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
    </script>
</body>
</html> 