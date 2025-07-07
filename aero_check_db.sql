-- Create AeroCheck Database
CREATE DATABASE IF NOT EXISTS `huan_fitness_pal_db`;
USE `huan_fitness_pal_db`;

-- Passengers table
CREATE TABLE IF NOT EXISTS `passengers` (
    `passenger_id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `contact_info` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Flights table
CREATE TABLE IF NOT EXISTS `flights` (
    `flight_number` VARCHAR(20) PRIMARY KEY,
    `departure_time` DATETIME NOT NULL,
    `destination` VARCHAR(100) NOT NULL,
    `gate` VARCHAR(10) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'On Time'
);

-- Boarding passes table
CREATE TABLE IF NOT EXISTS `boarding_passes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `passenger_id` VARCHAR(50) NOT NULL,
    `flight_number` VARCHAR(20) NOT NULL,
    `seat_number` VARCHAR(10) NOT NULL,
    `qr_code` TEXT,
    `issue_datetime` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE,
    FOREIGN KEY (`flight_number`) REFERENCES `flights`(`flight_number`) ON DELETE CASCADE
);

-- Groups table
CREATE TABLE IF NOT EXISTS `groups` (
    `group_id` VARCHAR(50) PRIMARY KEY,
    `representative_id` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`representative_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE
);

-- Group members table
CREATE TABLE IF NOT EXISTS `group_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` VARCHAR(50) NOT NULL,
    `passenger_id` VARCHAR(50) NOT NULL,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`group_id`) ON DELETE CASCADE,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_group_member` (`group_id`, `passenger_id`)
);

-- Baggage table
CREATE TABLE IF NOT EXISTS `baggage` (
    `baggage_id` VARCHAR(50) PRIMARY KEY,
    `passenger_id` VARCHAR(50) NOT NULL,
    `weight` DECIMAL(5,2) NOT NULL,
    `screening_status` VARCHAR(20) DEFAULT 'Pending',
    `baggage_tag` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE
);

-- Assistance details table
CREATE TABLE IF NOT EXISTS `assistance_details` (
    `assistance_id` VARCHAR(50) PRIMARY KEY,
    `passenger_id` VARCHAR(50) NOT NULL,
    `need_type` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'Requested',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE
);

-- Staff table
CREATE TABLE IF NOT EXISTS `staff` (
    `staff_id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Check-in counters table
CREATE TABLE IF NOT EXISTS `checkin_counters` (
    `counter_id` VARCHAR(50) PRIMARY KEY,
    `location` VARCHAR(100) NOT NULL,
    `assigned_staff_id` VARCHAR(50),
    `is_operational` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`assigned_staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
);

-- Self-service kiosks table
CREATE TABLE IF NOT EXISTS `self_service_kiosks` (
    `kiosk_id` VARCHAR(50) PRIMARY KEY,
    `location` VARCHAR(100) NOT NULL,
    `is_operational` BOOLEAN DEFAULT TRUE
);

-- Flight notifications table
CREATE TABLE IF NOT EXISTS `flight_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `passenger_id` VARCHAR(50) NOT NULL,
    `flight_number` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE,
    FOREIGN KEY (`flight_number`) REFERENCES `flights`(`flight_number`) ON DELETE CASCADE
);

-- Insert sample data for testing
INSERT INTO `passengers` (`passenger_id`, `name`, `contact_info`) VALUES
('P001', 'John Doe', '+60123456789'),
('P002', 'Jane Smith', '+60198765432'),
('P003', 'Bob Johnson', '+60134567890'),
('P004', 'Alice Brown', '+60187654321');

INSERT INTO `flights` (`flight_number`, `departure_time`, `destination`, `gate`, `status`) VALUES
('MH001', '2025-07-08 10:30:00', 'Singapore', 'A1', 'On Time'),
('MH002', '2025-07-08 14:45:00', 'Bangkok', 'B2', 'On Time'),
('MH003', '2025-07-08 18:20:00', 'Jakarta', 'C3', 'Delayed');

INSERT INTO `staff` (`staff_id`, `name`, `role`) VALUES
('S001', 'Mary Wilson', 'Check-in Agent'),
('S002', 'David Lee', 'Supervisor'),
('S003', 'Sarah Chen', 'Special Assistance Agent');

INSERT INTO `checkin_counters` (`counter_id`, `location`, `assigned_staff_id`) VALUES
('C001', 'Terminal 1 - Counter 1', 'S001'),
('C002', 'Terminal 1 - Counter 2', 'S002'),
('C003', 'Terminal 1 - Counter 3', 'S003');

INSERT INTO `self_service_kiosks` (`kiosk_id`, `location`) VALUES
('K001', 'Terminal 1 - Entrance'),
('K002', 'Terminal 1 - Departure Hall'),
('K003', 'Terminal 1 - Gate Area');