-- Social Donor Platform Database Schema
-- MySQL Database Schema

-- Create Database
CREATE DATABASE IF NOT EXISTS socialdonor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE socialdonor;

-- Donors Table
CREATE TABLE IF NOT EXISTS donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    cell_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    address TEXT NOT NULL,
    race ENUM('Black African', 'White', 'Coloured', 'Indian/Asian') NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    emergency_contact VARCHAR(100),
    emergency_number VARCHAR(20),
    profile_photo VARCHAR(255),
    password_hash VARCHAR(255) NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    last_donation DATE,
    donation_count INT DEFAULT 0,
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    cell_number VARCHAR(20),
    role ENUM('Super Admin', 'Admin', 'Moderator') NOT NULL DEFAULT 'Admin',
    permissions TEXT,
    verification_document VARCHAR(255),
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Blood Requests Table
CREATE TABLE IF NOT EXISTS blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(100) NOT NULL,
    patient_surname VARCHAR(100) NOT NULL,
    hospital_name VARCHAR(150) NOT NULL,
    location TEXT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_needed INT NOT NULL DEFAULT 1,
    urgency ENUM('Critical', 'High', 'Moderate', 'Low') NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    medical_reason TEXT,
    status ENUM('Pending', 'In Progress', 'Fulfilled', 'Cancelled') DEFAULT 'Pending',
    assigned_donor_id INT NULL,
    notes TEXT,
    requested_by INT NOT NULL,
    fulfilled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_donor_id) REFERENCES donors(id),
    FOREIGN KEY (requested_by) REFERENCES admins(id)
);

-- Donations Table
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    donation_date DATE NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    location VARCHAR(200) NOT NULL,
    units_donated INT DEFAULT 1,
    status ENUM('Completed', 'Pending', 'Cancelled') DEFAULT 'Completed',
    staff_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (staff_id) REFERENCES admins(id)
);

-- Dashboard Stats Table
CREATE TABLE IF NOT EXISTS dashboard_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_requests INT DEFAULT 0,
    total_received INT DEFAULT 0,
    in_stock INT DEFAULT 0,
    active_donors INT DEFAULT 0,
    pending_requests INT DEFAULT 0,
    fulfilled_today INT DEFAULT 0,
    date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Urgent Places Table
CREATE TABLE IF NOT EXISTS urgent_places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    area VARCHAR(150) NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    urgency ENUM('Critical', 'High', 'Moderate', 'Low') NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    contact_number VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Alerts Table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    target_group ENUM('All Donors', 'Specific Blood Type', 'Location Based', 'All Admins') NOT NULL,
    target_criteria TEXT,
    is_urgent BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES admins(id)
);

-- Notification Queue Table
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('Donor', 'Admin') NOT NULL,
    notification_type ENUM('Blood Request', 'Alert', 'Appointment Reminder', 'System') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT FALSE,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES donors(id) ON DELETE CASCADE
);

-- Sessions Table for enhanced security
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    user_type ENUM('Donor', 'Admin') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES donors(id) ON DELETE CASCADE
);

-- Blood Inventory Table
CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL UNIQUE,
    units_available INT DEFAULT 0,
    units_reserved INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit Log Table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_type ENUM('Donor', 'Admin', 'System') NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for better performance
CREATE INDEX idx_donors_email ON donors(email);
CREATE INDEX idx_donors_blood_type ON donors(blood_type);
CREATE INDEX idx_donors_status ON donors(status);
CREATE INDEX idx_blood_requests_status ON blood_requests(status);
CREATE INDEX idx_blood_requests_blood_type ON blood_requests(blood_type);
CREATE INDEX idx_blood_requests_urgency ON blood_requests(urgency);
CREATE INDEX idx_donations_donor_id ON donations(donor_id);
CREATE INDEX idx_donations_date ON donations(donation_date);
CREATE INDEX idx_sessions_session_id ON user_sessions(session_id);
CREATE INDEX idx_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_alerts_target_group ON alerts(target_group);
CREATE INDEX idx_notification_queue_user_id ON notification_queue(user_id);

-- Insert default blood inventory levels
INSERT IGNORE INTO blood_inventory (blood_type, units_available) VALUES
('A+', 50),
('A-', 30),
('B+', 45),
('B-', 25),
('AB+', 20),
('AB-', 15),
('O+', 60),
('O-', 40);

-- Insert default admin (password: Admin@12345)
INSERT IGNORE INTO admins (username, full_name, surname, email, cell_number, role, permissions, password_hash) VALUES
('sysadmin', 'System', 'Administrator', 'admin@socialdonor.org', '+27 11 000 0000', 'Super Admin', 'Can approve donor records, issue urgent alerts, and view request analytics.', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample urgent places
INSERT IGNORE INTO urgent_places (name, area, blood_type, urgency, latitude, longitude, contact_number, address) VALUES
('Charlotte Maxeke Hospital', 'Johannesburg CBD', 'O-', 'Critical', -26.1884, 28.0398, '+27 11 123 4567', '17 Joubert St, Johannesburg'),
('Chris Hani Baragwanath', 'Soweto', 'A+', 'High', -26.2564, 27.9427, '+27 11 987 6543', '26 Chris Hani Rd, Soweto'),
('Helen Joseph Hospital', 'Auckland Park', 'B-', 'High', -26.1812, 28.0104, '+27 11 456 7890', '14 Perth Rd, Auckland Park'),
('Tembisa Hospital', 'Tembisa', 'AB-', 'Critical', -25.9943, 28.2228, '+27 11 321 6549', '87 Tembisa Rd, Tembisa'),
('Rahima Moosa Hospital', 'Coronationville', 'O+', 'Moderate', -26.1699, 27.9964, '+27 11 654 3210', '31 Market St, Coronationville');

-- Initialize dashboard stats
INSERT IGNORE INTO dashboard_stats (total_requests, total_received, in_stock, active_donors, pending_requests, fulfilled_today) VALUES
(0, 0, 285, 0, 0, 0);
