-- Create AeroCheck Database
CREATE DATABASE IF NOT EXISTS `aero_check_db`;
USE `aero_check_db`;

-- Passengers table
CREATE TABLE IF NOT EXISTS `passengers` (
    `passenger_id` VARCHAR(50) PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `passport_number` VARCHAR(50) UNIQUE,
    `contact_phone` VARCHAR(100),
    `email_address` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Flights table
CREATE TABLE IF NOT EXISTS `flights` (
    `flight_number` VARCHAR(20) PRIMARY KEY,
    `departure_time` DATETIME NOT NULL,
    `destination` VARCHAR(100) NOT NULL,
    `gate` VARCHAR(10) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'On Time',
    `capacity` ENUM('182', '110') NOT NULL DEFAULT '182'
);

-- Bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
    `booking_id` VARCHAR(50) PRIMARY KEY,
    `flight_number` VARCHAR(20) NOT NULL,
    `booking_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(20) DEFAULT 'Confirmed',
    `is_group_booking` BOOLEAN NOT NULL DEFAULT FALSE,
    `fare_class` VARCHAR(50) NOT NULL DEFAULT 'Economy',
    FOREIGN KEY (`flight_number`) REFERENCES `flights`(`flight_number`) ON DELETE CASCADE
);

-- Baggage_Packages table
CREATE TABLE IF NOT EXISTS `baggage_packages` (
    `package_id` VARCHAR(50) PRIMARY KEY,
    `package_name` VARCHAR(100) NOT NULL, 
    `additional_weight_kg` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL, 
    `description` TEXT
);

-- Seats table
CREATE TABLE IF NOT EXISTS `seats` (
    `seat_id` VARCHAR(50) PRIMARY KEY,
    `flight_number` VARCHAR(20) NOT NULL,
    `row` INT(2) NOT NULL,
    `column` VARCHAR(1) NOT NULL,
    `seat_number` VARCHAR(10) NOT NULL, 
    `seat_class` ENUM('Economy', 'Business', 'First') NOT NULL DEFAULT 'Economy', 
    `is_premium` BOOLEAN DEFAULT FALSE, 
    `status` VARCHAR(20) NOT NULL DEFAULT 'Available', 
    FOREIGN KEY (`flight_number`) REFERENCES `flights`(`flight_number`) ON DELETE CASCADE,
    UNIQUE KEY `unique_flight_seat` (`flight_number`, `row`, `column`) 
);

-- booking_Passengers table 
CREATE TABLE IF NOT EXISTS `booking_passengers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` VARCHAR(50) NOT NULL,
    `passenger_id` VARCHAR(50) NOT NULL,
    `seat_number` VARCHAR(10),
    `assigned_seat_id` VARCHAR(50),
    `check_in_status` VARCHAR(20) DEFAULT 'Not Checked In',
    `purchased_baggage_package_id` VARCHAR(50),
    `additional_baggage_pieces` INT DEFAULT 0,
    `additional_baggage_weight_kg` DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE,
    FOREIGN KEY (`purchased_baggage_package_id`) REFERENCES `baggage_packages`(`package_id`),
    FOREIGN KEY (`assigned_seat_id`) REFERENCES `seats`(`seat_id`),
    UNIQUE KEY `unique_booking_passenger` (`booking_id`, `passenger_id`)
);

-- Boarding passes table
CREATE TABLE IF NOT EXISTS `boarding_passes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `passenger_id` VARCHAR(50) NOT NULL,
    `flight_number` VARCHAR(20) NOT NULL,
    `booking_id` VARCHAR(50) NOT NULL,
    `seat_number` VARCHAR(10) NOT NULL,
    `qr_code` TEXT,
    `issue_datetime` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE,
    FOREIGN KEY (`flight_number`) REFERENCES `flights`(`flight_number`) ON DELETE CASCADE,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE
);

-- Groups table
CREATE TABLE IF NOT EXISTS `groups` (
    `group_id` VARCHAR(50) PRIMARY KEY,
    `booking_id` VARCHAR(50) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE
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
    `booking_id` VARCHAR(50) NOT NULL,
    `package_id` VARCHAR(50) DEFAULT NULL,
    `weight_kg` DECIMAL(5,2) NOT NULL,
    `baggage_tag` VARCHAR(50),
    `screening_status` VARCHAR(20) DEFAULT 'Pending',
    `description` TEXT,
    `special_handling` VARCHAR(50) DEFAULT NULL,    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`passenger_id`) REFERENCES `passengers`(`passenger_id`) ON DELETE CASCADE,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `baggage_packages`(`package_id`) ON DELETE SET NULL
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