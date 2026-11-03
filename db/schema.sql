-- Jothidam Astrology Chatbot Database Schema

CREATE DATABASE IF NOT EXISTS `astro_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `astro_db`;

-- Bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `dob` DATE NOT NULL,
  `tob` TIME NOT NULL,
  `pob` VARCHAR(150) NOT NULL,
  `lat` DECIMAL(9, 6) DEFAULT NULL,
  `lon` DECIMAL(9, 6) DEFAULT NULL,
  `slot_date` DATE NOT NULL,
  `slot_time` TIME NOT NULL,
  `question` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `chart_json` LONGTEXT DEFAULT NULL,
  `report_text` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_slot` (`slot_date`, `slot_time`),
  INDEX `idx_status` (`status`),
  INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slot Locks table for atomic transaction lock (prevents double-booking)
CREATE TABLE IF NOT EXISTS `slot_locks` (
  `slot_date` DATE NOT NULL,
  `slot_time` TIME NOT NULL,
  `booking_id` INT NOT NULL,
  `locked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`slot_date`, `slot_time`),
  CONSTRAINT `fk_slot_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `screenshot_path` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(10, 2) NOT NULL DEFAULT 500.00,
  `upi_ref` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

