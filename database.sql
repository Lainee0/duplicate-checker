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
    batch_reference VARCHAR(100),
    UNIQUE KEY unique_beneficiary (name, barangay, birthday)
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

-- Indexes for better performance
CREATE INDEX idx_name ON received_beneficiaries(name);
CREATE INDEX idx_barangay ON received_beneficiaries(barangay);
CREATE INDEX idx_birthday ON received_beneficiaries(birthday);