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