-- Create database
CREATE DATABASE IF NOT EXISTS `dupli-db`;
USE `dupli-db`;

-- Table for received beneficiaries (master list)
CREATE TABLE IF NOT EXISTS received_beneficiaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    birthday DATE NOT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    batch_reference VARCHAR(100)
);

-- Table for checking history
CREATE TABLE IF NOT EXISTS check_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(255),
    check_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_checked INT,
    duplicates_found INT,
    clean_records INT
);

-- Table for duplicate records (temporary storage)
CREATE TABLE IF NOT EXISTS duplicate_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    check_id INT,
    name VARCHAR(255),
    barangay VARCHAR(100),
    birthday DATE,
    match_type VARCHAR(50),
    FOREIGN KEY (check_id) REFERENCES check_history(id) ON DELETE CASCADE
);

-- Table for user authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO users (username, email, password, first_name, last_name, role) 
VALUES ('admin', 'admin@duplichecker.local', '$2y$10$Y9jH8Kqj2L5mN3P0X6V2e.8Q4F1W5A9S3D7G2H6K9M1B4C7R0N3E', 'Admin', 'User', 'admin');

-- Indexes for better performance
CREATE INDEX idx_name ON received_beneficiaries(name);
CREATE INDEX idx_barangay ON received_beneficiaries(barangay);
CREATE INDEX idx_birthday ON received_beneficiaries(birthday);

-- Table structure for rice_beneficiaries
CREATE TABLE `rice_beneficiaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `batch_reference` varchar(100) DEFAULT NULL,
  `rice_type` varchar(50) DEFAULT 'Regular',
  `quantity` decimal(10,2) DEFAULT 0.00,
  `distribution_date` date DEFAULT NULL,
  `status` enum('pending','distributed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rice_batch` (`batch_reference`),
  KEY `idx_rice_barangay` (`barangay`),
  KEY `idx_rice_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for rice distribution history
CREATE TABLE `rice_distribution_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_reference` varchar(100) NOT NULL,
  `total_beneficiaries` int(11) DEFAULT 0,
  `total_rice_distributed` decimal(10,2) DEFAULT 0.00,
  `distribution_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rice_dist_batch` (`batch_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `rice_beneficiaries` 
ADD COLUMN `sector` varchar(255) DEFAULT NULL AFTER `status`;

ALTER TABLE `rice_distribution_history` 
ADD COLUMN `barangay_stats` json DEFAULT NULL,
ADD COLUMN `sector_stats` json DEFAULT NULL;