-- Creating database
CREATE DATABASE IF NOT EXISTS omnes_immobilier;
USE omnes_immobilier;

-- Create USERS table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client', 'agent') NOT NULL DEFAULT 'client',
    phone VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Client table
CREATE TABLE clients(
    user_id INT PRIMARY KEY,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    financial_info JSON,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Agents table
CREATE TABLE agents(
    user_id INT PRIMARY KEY,
    cv_file_path VARCHAR(255) NOT NULL,
    profile_picture_path VARCHAR(255),
    agency_name VARCHAR(255) NOT NULL,
    agency_address VARCHAR(255) NOT NULL,
    agency_phone VARCHAR(15) NOT NULL,
    agency_email VARCHAR(100) NOT NULL UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Properties table
CREATE TABLE properties(
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    property_type ENUM('house', 'apartment', 'land', 'commercial', 'rental', 'auction') NOT NULL,
    status ENUM('available', 'sold', 'rented') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(user_id) ON DELETE CASCADE
);

-- Create Availability table(for agents)
CREATE TABLE availability(
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    property_id INT NOT NULL,
    available_from DATE NOT NULL,
    available_to DATE NOT NULL,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Create Appointments table
CREATE TABLE appointments(
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    agent_id INT NOT NULL,
    property_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    location VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(user_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(user_id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Messages table for communication between clients and agents
CREATE TABLE messages(
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    property_id INT NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Test data for users
INSERT INTO users (first_name, last_name, email, password_hash, role, phone) VALUES
('Admin', 'One', 'admin@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'admin', '+33123456789'),
('Jean-Pierre', 'Segado', 'jean-pierre@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'agent', '+33600001111'),
('Alice', 'Durand', 'alice@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'client', '+33712345678');

-- Test data for agents
INSERT INTO agents (user_id, cv_file_path, profile_picture_path, agency_name, agency_address, agency_phone, agency_email) VALUES
(2, '/path/to/cv/jean-pierre.pdf', '/path/to/profile/picture/jean-pierre.jpg', 'Omnes Agency', '123 Main St, Paris', '+33123456789', 'b@omnes.fr');

-- Test data for clients
INSERT INTO clients (user_id, address_line1, address_line2, city, state, postal_code, country, financial_info) VALUES
(3, '456 Elm St', NULL, 'Lyon', 'Auvergne-Rhône-Alpes', '69001', 'France', '{"income": 50000, "credit_score": 700}');