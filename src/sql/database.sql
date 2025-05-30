-- Omnes Immobilier Database Schema
-- Create database
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create CLIENTS table
CREATE TABLE clients (
    user_id INT PRIMARY KEY,
    address_line1 VARCHAR(255) NOT NULL DEFAULT '',
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) NOT NULL DEFAULT '',
    state VARCHAR(100) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    country VARCHAR(100) NOT NULL DEFAULT 'France',
    financial_info JSON DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create AGENTS table
CREATE TABLE agents (
    user_id INT PRIMARY KEY,
    cv_file_path VARCHAR(255) DEFAULT '',
    profile_picture_path VARCHAR(255) DEFAULT NULL,
    agency_name VARCHAR(255) NOT NULL DEFAULT 'Independent Agent',
    agency_email VARCHAR(100) DEFAULT '',
    license_number VARCHAR(50) DEFAULT '',
    languages_spoken JSON DEFAULT NULL,
    years_experience INT DEFAULT 0,
    commission_rate DECIMAL(5,2) DEFAULT 0.00,
    bio TEXT DEFAULT NULL,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_sales DECIMAL(15,2) DEFAULT 0.00,
    total_transactions INT DEFAULT 0,
    UNIQUE KEY agency_email (agency_email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create PROPERTIES table
CREATE TABLE properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    images JSON DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL,
    property_type ENUM('house', 'apartment', 'land', 'commercial', 'rental', 'auction') NOT NULL,
    status ENUM('available', 'pending', 'sold', 'rented', 'withdrawn') DEFAULT 'available',
    
    -- Address Information
    address_line1 VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    country VARCHAR(100) DEFAULT 'France',
    
    -- Property Details
    bedrooms INT DEFAULT 0,
    bathrooms INT DEFAULT 0,
    year_built INT DEFAULT 0,
    living_area INT NOT NULL DEFAULT 0,
    
    -- Features
    has_parking TINYINT(1) DEFAULT 0,
    parking_spaces INT DEFAULT 0,
    has_garden TINYINT(1) DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (agent_id) REFERENCES agents(user_id) ON DELETE CASCADE
);

-- Create AGENT_AVAILABILITY table
CREATE TABLE agent_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    specific_date DATE DEFAULT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    availability_type ENUM('weekly', 'exception', 'lunch_break', 'quick_available', 'quick_blocked') DEFAULT 'weekly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(user_id) ON DELETE CASCADE
);

-- Create APPOINTMENTS table
CREATE TABLE appointments (
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

-- Create MESSAGES table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    property_id INT NOT NULL,
    message TEXT NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    reply_to_id INT DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Create USER_FAVORITES table
CREATE TABLE user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    property_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_property (user_id, property_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- Insert initial users
INSERT INTO users (id, first_name, last_name, email, password_hash, role, phone, created_at) VALUES
(1, 'Admin', 'One', 'admin@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'admin', '+33123456789', '2025-05-27 16:39:28'),
(2, 'Jean-Pierre', 'Segado', 'jean-pierre@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'agent', '+33600001111', '2025-05-27 16:39:28'),
(3, 'Alice', 'Durand', 'alice@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'client', '+33712345678', '2025-05-27 16:39:28'),
(4, 'Georges', 'AL Haddad', 'georges.alhaddad28@gmail.com', '$2y$12$R0BFXQGQUjmZ3iDsKN0SG.rkbKHjZl.ETxcp8yOwbgr0dG7bgwsZm', 'client', '+33675748823', '2025-05-27 16:48:05'),
(5, 'John', 'Doe', 'g@gmail.com', '$2y$12$w2UsUXcEatODeGGkWFQiT.bFOecSAi33gMYfAAN9Omg78t26ejjv.', 'agent', '0123456789', '2025-05-28 15:00:11'),
(6, 'Alex', 'Colinet', 'ge@gmail.com', '$2y$12$.ff6zD4bkt8SjOGmCwrqYeqFz79Ht/eoNOB9AVsz4sIfL2wBdCN4W', 'admin', '0125457885', '2025-05-29 14:16:38');

-- Insert agents data (removed references to agency_address and agency_phone which don't exist in the schema)
INSERT INTO agents (user_id, cv_file_path, profile_picture_path, agency_name, agency_email) VALUES
(2, '/path/to/cv/jean-pierre.pdf', '/path/to/profile/picture/jean-pierre.jpg', 'Omnes Agency', 'b@omnes.fr'),
(5, '', '', 'Independent Agent', 'g@gmail.com'),
(6, '', '', 'Independent Agent', 'ge@gmail.com');

-- Insert clients data
INSERT INTO clients (user_id, address_line1, address_line2, city, state, postal_code, country, financial_info) VALUES
(3, '456 Elm St', NULL, 'Lyon', 'Auvergne-Rhône-Alpes', '69001', 'France', '{\"income\": 50000, \"credit_score\": 700}'),
(4, '', '', '', '', '', 'France', '{}');

-- Insert agent availability
INSERT INTO agent_availability (agent_id, day_of_week, start_time, end_time, is_available) VALUES
(5, 'Monday', '10:00:00', '18:00:00', 1),
(5, 'Tuesday', '10:00:00', '18:00:00', 1),
(5, 'Wednesday', '10:00:00', '18:00:00', 1),
(5, 'Thursday', '10:00:00', '18:00:00', 1),
(5, 'Friday', '10:00:00', '18:00:00', 1),
(6, 'Monday', '09:00:00', '17:00:00', 1),
(6, 'Tuesday', '09:00:00', '17:00:00', 1),
(6, 'Wednesday', '09:00:00', '17:00:00', 1),
(6, 'Thursday', '09:00:00', '17:00:00', 1),
(6, 'Friday', '09:00:00', '17:00:00', 1),
(6, 'Saturday', '11:00:00', '15:00:00', 1);

-- Insert sample properties (removed references to fields that no longer exist in the schema)
INSERT INTO properties (
    id, agent_id, title, description, images, price,
    address_line1, city, state, postal_code, country,
    property_type, status, created_at, updated_at,
    bedrooms, bathrooms, living_area
) VALUES
(1, 5, 'Modern Apartment', '2 bed · 1 bath · 70m² apartment',
 '[\"../assets/images/property11.jpg\",\"../assets/images/property1-2.jpg\",\"../assets/images/property1-3.jpg\",\"../assets/images/property1-4.jpg\",\"../assets/images/property1-5.jpg\"]',
 550000.00, '123 Boulevard de Paris', 'Paris', 'Île-de-France', '75015', 'France',
 'apartment', 'available', '2025-05-28 16:15:44', '2025-05-29 14:19:16',
 2, 1, 70),
(2, 6, 'Family House', '4 bed · 3 bath · 150m² house',
 '[\"../assets/images/property22.jpg\",\"../assets/images/property2-2.jpg\",\"../assets/images/property2-3.jpg\",\"../assets/images/property2-4.jpg\",\"../assets/images/property2-5.jpg\"]',
 1200000.00, '45 Rue de Famille', 'Paris', 'Île-de-France', '75016', 'France',
 'house', 'available', '2025-05-28 16:15:44', '2025-05-29 14:19:24',
 4, 3, 150),
(3, 6, 'Cozy Studio', '1 bed · 1 bath · 35m² studio',
 '[\"../assets/images/property33.jpg\",\"../assets/images/property3-2.jpg\",\"../assets/images/property3-3.jpg\",\"../assets/images/property3-4.jpg\",\"../assets/images/property3-5.jpg\"]',
 350000.00, '10 Rue du Studio', 'Paris', 'Île-de-France', '75007', 'France',
 'apartment', 'available', '2025-05-28 16:15:44', '2025-05-29 14:19:31',
 1, 1, 35),
(4, 5, 'Luxury Villa', '5 bed · 4 bath · 300m² villa',
 '[\"../assets/images/property44.jpg\",\"../assets/images/property4-2.jpg\",\"../assets/images/property4-3.jpg\",\"../assets/images/property4-4.jpg\",\"../assets/images/property4-5.jpg\"]',
 3500000.00, '1 Avenue de la Plage', 'Saint-Tropez', 'Provence-Alpes-Côte d\'Azur', '83990', 'France',
 'house', 'available', '2025-05-28 16:15:44', '2025-05-29 14:19:37',
 5, 4, 300),
(5, 2, 'Beachfront Condo', '3 bed · 2 bath · 120m² condo',
 '[\"../assets/images/property55.jpg\",\"../assets/images/property5-2.jpg\",\"../assets/images/property5-3.jpg\",\"../assets/images/property5-4.jpg\",\"../assets/images/property5-5.jpg\"]',
 800000.00, '5 Promenade des Anglais', 'Nice', 'Provence-Alpes-Côte d\'Azur', '06000', 'France',
 'apartment', 'available', '2025-05-28 16:15:44', '2025-05-28 16:57:57',
 3, 2, 120),
(6, 2, 'Building Land', '180m² plot for construction',
 '[\"../assets/images/property6.jpg\",\"../assets/images/property6-2.jpg\",\"../assets/images/property6-3.jpg\",\"../assets/images/property6-4.jpg\",\"../assets/images/property6-5.jpg\"]',
 200000.00, 'Rue des Vignes', 'Bordeaux', 'Nouvelle-Aquitaine', '33000', 'France',
 'land', 'available', '2025-05-28 16:15:44', '2025-05-28 16:54:05',
 0, 0, 180),
(7, 2, 'Historic Mansion', '6 bed · 5 bath · 300m² mansion',
 '[\"../assets/images/property7.jpg\",\"../assets/images/property7-2.jpg\",\"../assets/images/property7-3.jpg\",\"../assets/images/property7-4.jpg\",\"../assets/images/property7-5.jpg\"]',
 2500000.00, 'Château de Normandie', 'Bayeux', 'Normandie', '14000', 'France',
 'house', 'available', '2025-05-28 16:15:44', '2025-05-28 16:54:13',
 6, 5, 300),
(8, 2, 'Modern City Loft', '1 bed · 1 bath · 40m² loft',
 '[\"../assets/images/property8.jpg\",\"../assets/images/property8-2.jpg\",\"../assets/images/property8-3.jpg\",\"../assets/images/property8-4.jpg\",\"../assets/images/property8-5.jpg\"]',
 450000.00, '789 Rue du Loft', 'Paris', 'Île-de-France', '75015', 'France',
 'apartment', 'available', '2025-05-28 16:15:44', '2025-05-28 16:54:24',
 1, 1, 40),
(9, 2, 'Countryside Cottage', '3 bed · 2 bath · 110m² cottage',
 '[\"../assets/images/property9.jpg\",\"../assets/images/property9-2.jpg\",\"../assets/images/property9-3.jpg\",\"../assets/images/property9-4.jpg\",\"../assets/images/property9-5.jpg\"]',
 420000.00, '12 Chemin de Campagne', 'Lyon', 'Auvergne-Rhône-Alpes', '69000', 'France',
 'house', 'available', '2025-05-28 16:15:44', '2025-05-28 16:54:38',
 3, 2, 110),
(10, 2, 'Modern Office Space', '250m² office space',
 '[\"../assets/images/property1.jpg\",\"../assets/images/property10-2.jpg\",\"../assets/images/property10-3.jpg\",\"../assets/images/property10-4.jpg\",\"../assets/images/property10-5.jpg\"]',
 1500000.00, '99 Boulevard Haussmann', 'Paris', 'Île-de-France', '75008', 'France',
 'commercial', 'available', '2025-05-28 16:15:44', '2025-05-29 14:03:09',
 0, 0, 250),
(11, 2, 'Retail Storefront', '80m² retail shop',
 '[\"../assets/images/property11-1.jpg\",\"../assets/images/property11-2.jpg\",\"../assets/images/property11-3.jpg\",\"../assets/images/property11-4.jpg\",\"../assets/images/property11-5.jpg\"]',
 600000.00, '10 Rue Sainte-Catherine', 'Bordeaux', 'Nouvelle-Aquitaine', '33000', 'France',
 'commercial', 'available', '2025-05-28 16:15:44', '2025-05-28 16:24:38',
 0, 0, 80),
(12, 2, 'Apartment for Rent', '2 bed · 1 bath · 60m² rental apartment',
 '[\"../assets/images/property12.jpg\",\"../assets/images/property12-2.jpg\",\"../assets/images/property12-3.jpg\",\"../assets/images/property12-4.jpg\",\"../assets/images/property12-5.jpg\"]',
 1800.00, '20 Rue du Faubourg', 'Paris', 'Île-de-France', '75010', 'France',
 'rental', 'available', '2025-05-28 16:15:44', '2025-05-29 14:03:24',
 2, 1, 60),
(13, 2, 'Villa for Rent', '4 bed · 3 bath · 200m² rental villa',
 '[\"../assets/images/property13.jpg\",\"../assets/images/property13-2.jpg\",\"../assets/images/property13-3.jpg\",\"../assets/images/property13-4.jpg\",\"../assets/images/property13-5.jpg\"]',
 3500.00, '25 Avenue de la Mer', 'Cannes', 'Provence-Alpes-Côte d\'Azur', '06400', 'France',
 'rental', 'available', '2025-05-28 16:15:44', '2025-05-29 12:35:09',
 4, 3, 200),
(14, 2, 'Auction Property', '3 bed · 2 bath · 85m² auction apartment',
 '[\"../assets/images/property14.jpg\",\"../assets/images/property14-2.jpg\",\"../assets/images/property14-3.jpg\",\"../assets/images/property14-4.jpg\",\"../assets/images/property14-5.jpg\"]',
 280000.00, '15 Rue de la République', 'Marseille', 'Provence-Alpes-Côte d\'Azur', '13001', 'France',
 'auction', 'available', '2025-05-28 16:15:44', '2025-05-28 17:04:38',
 3, 2, 85);

-- Insert sample appointments
INSERT INTO appointments (id, client_id, agent_id, property_id, appointment_date, status, location) VALUES
(1, 4, 5, 1, '2025-05-30 10:00:00', 'scheduled', '123 Boulevard de Paris, Paris'),
(2, 4, 6, 2, '2025-05-31 11:00:00', 'cancelled', '45 Rue de Famille, Paris'),
(3, 4, 6, 2, '2025-05-31 12:00:00', 'scheduled', '45 Rue de Famille, Paris'),
(4, 4, 5, 4, '2025-06-04 14:00:00', 'cancelled', '1 Avenue de la Plage, Saint-Tropez');

-- Insert user favorites
INSERT INTO user_favorites (user_id, property_id, created_at) VALUES
(4, 1, '2025-05-29 16:46:56');