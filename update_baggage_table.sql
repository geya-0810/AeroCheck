-- Add missing columns to baggage table for multiple baggage system
ALTER TABLE `baggage` 
ADD COLUMN `package_id` VARCHAR(50) AFTER `booking_id`,
ADD COLUMN `description` TEXT AFTER `screening_status`,
ADD COLUMN `special_handling` VARCHAR(50) AFTER `description`,
ADD FOREIGN KEY (`package_id`) REFERENCES `baggage_packages`(`package_id`) ON DELETE SET NULL;

-- Update existing baggage records to have default values
UPDATE `baggage` SET 
`package_id` = 'BG20' WHERE `package_id` IS NULL; 