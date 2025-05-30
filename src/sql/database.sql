-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 30, 2025 at 01:58 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `omnes_immobilier`
--

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

DROP TABLE IF EXISTS `agents`;
CREATE TABLE IF NOT EXISTS `agents` (
  `user_id` int NOT NULL,
  `cv_file_path` varchar(255) DEFAULT '',
  `profile_picture_path` varchar(255) DEFAULT NULL,
  `agency_name` varchar(255) NOT NULL DEFAULT 'Independent Agent',
  `agency_email` varchar(100) DEFAULT '',
  `license_number` varchar(50) DEFAULT '',
  `languages_spoken` json DEFAULT NULL,
  `years_experience` int DEFAULT '0',
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `bio` text,
  `average_rating` decimal(3,2) DEFAULT '0.00',
  `total_sales` decimal(15,2) DEFAULT '0.00',
  `total_transactions` int DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `agency_email` (`agency_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`user_id`, `cv_file_path`, `profile_picture_path`, `agency_name`, `agency_email`, `license_number`, `languages_spoken`, `years_experience`, `commission_rate`, `bio`, `average_rating`, `total_sales`, `total_transactions`) VALUES
(2, '/path/to/cv/jean-pierre.pdf', '/path/to/profile/picture/jean-pierre.jpg', 'Omnes Agency', 'b@omnes.fr', '', NULL, 0, 0.00, NULL, 0.00, 0.00, 0),
(5, '', '', 'Independent Agent', 'g@gmail.com', '', NULL, 0, 0.00, NULL, 0.00, 0.00, 0),
(6, '', '', 'Independent Agent', 'ge@gmail.com', '', NULL, 0, 0.00, NULL, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `agent_availability`
--

DROP TABLE IF EXISTS `agent_availability`;
CREATE TABLE IF NOT EXISTS `agent_availability` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `specific_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `availability_type` enum('weekly','exception','lunch_break','quick_available','quick_blocked') DEFAULT 'weekly',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `agent_availability`
--

INSERT INTO `agent_availability` (`id`, `agent_id`, `day_of_week`, `specific_date`, `start_time`, `end_time`, `is_available`, `availability_type`, `created_at`, `updated_at`) VALUES
(1, 5, 'Monday', NULL, '10:00:00', '18:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(2, 5, 'Tuesday', NULL, '10:00:00', '18:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(3, 5, 'Wednesday', NULL, '10:00:00', '18:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(4, 5, 'Thursday', NULL, '10:00:00', '18:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(5, 5, 'Friday', NULL, '10:00:00', '18:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(6, 6, 'Monday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(7, 6, 'Tuesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(8, 6, 'Wednesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(9, 6, 'Thursday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(10, 6, 'Friday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13'),
(11, 6, 'Saturday', NULL, '11:00:00', '15:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `agent_id` int NOT NULL,
  `property_id` int NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `location` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `agent_id` (`agent_id`),
  KEY `property_id` (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `client_id`, `agent_id`, `property_id`, `appointment_date`, `status`, `location`, `created_at`, `updated_at`) VALUES
(1, 4, 5, 1, '2025-05-30 10:00:00', 'scheduled', '123 Boulevard de Paris, Paris', '2025-05-30 11:41:14', '2025-05-30 11:41:14'),
(2, 4, 6, 2, '2025-05-31 11:00:00', 'cancelled', '45 Rue de Famille, Paris', '2025-05-30 11:41:14', '2025-05-30 11:41:14'),
(3, 4, 6, 2, '2025-05-31 12:00:00', 'scheduled', '45 Rue de Famille, Paris', '2025-05-30 11:41:14', '2025-05-30 11:41:14'),
(4, 4, 5, 4, '2025-06-04 14:00:00', 'cancelled', '1 Avenue de la Plage, Saint-Tropez', '2025-05-30 11:41:14', '2025-05-30 11:41:14');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `user_id` int NOT NULL,
  `address_line1` varchar(255) NOT NULL DEFAULT '',
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` varchar(100) NOT NULL DEFAULT '',
  `postal_code` varchar(20) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT 'France',
  `financial_info` json DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`user_id`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `financial_info`) VALUES
(3, '456 Elm St', NULL, 'Lyon', 'Auvergne-Rhône-Alpes', '69001', 'France', '{\"income\": 50000, \"credit_score\": 700}'),
(4, '', '', '', '', '', 'France', '{}');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `property_id` int NOT NULL,
  `message` text NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `reply_to_id` int DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `property_id` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_information`
--

DROP TABLE IF EXISTS `payment_information`;
CREATE TABLE IF NOT EXISTS `payment_information` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `card_type` varchar(50) NOT NULL,
  `card_last_four` varchar(4) NOT NULL,
  `card_holder_name` varchar(100) NOT NULL,
  `expiration_month` tinyint NOT NULL,
  `expiration_year` smallint NOT NULL,
  `billing_address_line1` varchar(255) NOT NULL,
  `billing_city` varchar(100) NOT NULL,
  `billing_state` varchar(100) NOT NULL,
  `billing_postal_code` varchar(20) NOT NULL,
  `billing_country` varchar(100) NOT NULL DEFAULT 'France',
  `is_default` tinyint(1) DEFAULT '0',
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_information`
--

INSERT INTO `payment_information` (`id`, `user_id`, `card_type`, `card_last_four`, `card_holder_name`, `expiration_month`, `expiration_year`, `billing_address_line1`, `billing_city`, `billing_state`, `billing_postal_code`, `billing_country`, `is_default`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, 3, 'Visa', '1234', 'Alice Durand', 12, 2027, '123 Rue de Paris', 'Paris', 'Île-de-France', '75001', 'France', 1, 0, '2025-05-30 13:44:04', '2025-05-30 13:44:04'),
(2, 4, 'Unknown', '4444', 'Georges AL HADDAD', 10, 2028, '2 Rue Theophile', 'Gautier', 'Hauts-de-Seine', '92120', 'France', 1, 0, '2025-05-30 13:51:43', '2025-05-30 13:57:19');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
CREATE TABLE IF NOT EXISTS `properties` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agent_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `images` json DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `property_type` enum('house','apartment','land','commercial','rental','auction') NOT NULL,
  `status` enum('available','pending','sold','rented','withdrawn') DEFAULT 'available',
  `address_line1` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'France',
  `bedrooms` int DEFAULT '0',
  `bathrooms` int DEFAULT '0',
  `year_built` int DEFAULT '0',
  `living_area` int NOT NULL DEFAULT '0',
  `has_parking` tinyint(1) DEFAULT '0',
  `parking_spaces` int DEFAULT '0',
  `has_garden` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `agent_id`, `title`, `description`, `images`, `price`, `property_type`, `status`, `address_line1`, `city`, `state`, `postal_code`, `country`, `bedrooms`, `bathrooms`, `year_built`, `living_area`, `has_parking`, `parking_spaces`, `has_garden`, `created_at`, `updated_at`) VALUES
(1, 5, 'Modern Apartment', '2 bed · 1 bath · 70m² apartment', '[\"../assets/images/property11.jpg\", \"../assets/images/property1-2.jpg\", \"../assets/images/property1-3.jpg\", \"../assets/images/property1-4.jpg\", \"../assets/images/property1-5.jpg\"]', 550000.00, 'apartment', 'available', '123 Boulevard de Paris', 'Paris', 'Île-de-France', '75015', 'France', 2, 1, 0, 70, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-29 12:19:16'),
(2, 6, 'Family House', '4 bed · 3 bath · 150m² house', '[\"../assets/images/property22.jpg\", \"../assets/images/property2-2.jpg\", \"../assets/images/property2-3.jpg\", \"../assets/images/property2-4.jpg\", \"../assets/images/property2-5.jpg\"]', 1200000.00, 'house', 'available', '45 Rue de Famille', 'Paris', 'Île-de-France', '75016', 'France', 4, 3, 0, 150, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-29 12:19:24'),
(3, 6, 'Cozy Studio', '1 bed · 1 bath · 35m² studio', '[\"../assets/images/property33.jpg\", \"../assets/images/property3-2.jpg\", \"../assets/images/property3-3.jpg\", \"../assets/images/property3-4.jpg\", \"../assets/images/property3-5.jpg\"]', 350000.00, 'apartment', 'available', '10 Rue du Studio', 'Paris', 'Île-de-France', '75007', 'France', 1, 1, 0, 35, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-29 12:19:31'),
(4, 5, 'Luxury Villa', '5 bed · 4 bath · 300m² villa', '[\"../assets/images/property44.jpg\", \"../assets/images/property4-2.jpg\", \"../assets/images/property4-3.jpg\", \"../assets/images/property4-4.jpg\", \"../assets/images/property4-5.jpg\"]', 3500000.00, 'house', 'available', '1 Avenue de la Plage', 'Saint-Tropez', 'Provence-Alpes-Côte d\'Azur', '83990', 'France', 5, 4, 0, 300, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-29 12:19:37'),
(5, 2, 'Beachfront Condo', '3 bed · 2 bath · 120m² condo', '[\"../assets/images/property55.jpg\", \"../assets/images/property5-2.jpg\", \"../assets/images/property5-3.jpg\", \"../assets/images/property5-4.jpg\", \"../assets/images/property5-5.jpg\"]', 800000.00, 'apartment', 'available', '5 Promenade des Anglais', 'Nice', 'Provence-Alpes-Côte d\'Azur', '06000', 'France', 3, 2, 0, 120, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 14:57:57'),
(6, 2, 'Building Land', '180m² plot for construction', '[\"../assets/images/property6.jpg\", \"../assets/images/property6-2.jpg\", \"../assets/images/property6-3.jpg\", \"../assets/images/property6-4.jpg\", \"../assets/images/property6-5.jpg\"]', 200000.00, 'land', 'available', 'Rue des Vignes', 'Bordeaux', 'Nouvelle-Aquitaine', '33000', 'France', 0, 0, 0, 180, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 14:54:05'),
(7, 2, 'Historic Mansion', '6 bed · 5 bath · 300m² mansion', '[\"../assets/images/property7.jpg\", \"../assets/images/property7-2.jpg\", \"../assets/images/property7-3.jpg\", \"../assets/images/property7-4.jpg\", \"../assets/images/property7-5.jpg\"]', 2500000.00, 'house', 'available', 'Château de Normandie', 'Bayeux', 'Normandie', '14000', 'France', 6, 5, 0, 300, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 14:54:13'),
(8, 2, 'Modern City Loft', '1 bed · 1 bath · 40m² loft', '[\"../assets/images/property8.jpg\", \"../assets/images/property8-2.jpg\", \"../assets/images/property8-3.jpg\", \"../assets/images/property8-4.jpg\", \"../assets/images/property8-5.jpg\"]', 450000.00, 'apartment', 'available', '789 Rue du Loft', 'Paris', 'Île-de-France', '75015', 'France', 1, 1, 0, 40, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 14:54:24'),
(9, 2, 'Countryside Cottage', '3 bed · 2 bath · 110m² cottage', '[\"../assets/images/property9.jpg\", \"../assets/images/property9-2.jpg\", \"../assets/images/property9-3.jpg\", \"../assets/images/property9-4.jpg\", \"../assets/images/property9-5.jpg\"]', 420000.00, 'house', 'available', '12 Chemin de Campagne', 'Lyon', 'Auvergne-Rhône-Alpes', '69000', 'France', 3, 2, 0, 110, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 14:54:38'),
(10, 2, 'Modern Office Space', '250m² office space', '[\"../assets/images/property1.jpg\", \"../assets/images/property10-2.jpg\", \"../assets/images/property10-3.jpg\", \"../assets/images/property10-4.jpg\", \"../assets/images/property10-5.jpg\"]', 1500000.00, 'commercial', 'available', '99 Boulevard Haussmann', 'Paris', 'Île-de-France', '75008', 'France', 0, 0, 0, 250, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-29 12:03:09'),
(11, 2, 'Retail Storefront', '80m² retail shop', '[\"../assets/images/property11-1.jpg\", \"../assets/images/property11-2.jpg\", \"../assets/images/property11-3.jpg\", \"../assets/images/property11-4.jpg\", \"../assets/images/property11-5.jpg\"]', 600000.00, 'commercial', 'available', '10 Rue Sainte-Catherine', 'Bordeaux', 'Nouvelle-Aquitaine', '33000', 'France', 0, 0, 0, 80, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 14:24:38'),
(12, 2, 'Apartment for Rent', '2 bed · 1 bath · 60m² rental apartment', '[\"../assets/images/property22.jpg\", \"../assets/images/property12-2.jpg\", \"../assets/images/property12-3.jpg\", \"../assets/images/property12-4.jpg\", \"../assets/images/property12-5.jpg\"]', 1800.00, 'rental', 'available', '20 Rue du Faubourg', 'Paris', 'Île-de-France', '75010', 'France', 2, 1, 0, 60, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-30 12:03:52'),
(13, 2, 'Villa for Rent', '4 bed · 3 bath · 200m² rental villa', '[\"../assets/images/property13.jpg\", \"../assets/images/property13-2.jpg\", \"../assets/images/property13-3.jpg\", \"../assets/images/property13-4.jpg\", \"../assets/images/property13-5.jpg\"]', 3500.00, 'rental', 'available', '25 Avenue de la Mer', 'Cannes', 'Provence-Alpes-Côte d\'Azur', '06400', 'France', 4, 3, 0, 200, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-29 10:35:09'),
(14, 2, 'Auction Property', '3 bed · 2 bath · 85m² auction apartment', '[\"../assets/images/property14.jpg\", \"../assets/images/property14-2.jpg\", \"../assets/images/property14-3.jpg\", \"../assets/images/property14-4.jpg\", \"../assets/images/property14-5.jpg\"]', 280000.00, 'auction', 'available', '15 Rue de la République', 'Marseille', 'Provence-Alpes-Côte d\'Azur', '13001', 'France', 3, 2, 0, 85, 0, 0, 0, '2025-05-28 14:15:44', '2025-05-28 15:04:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','client','agent') NOT NULL DEFAULT 'client',
  `phone` varchar(15) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `phone`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'One', 'admin@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'admin', '+33123456789', '2025-05-27 14:39:28', '2025-05-30 11:41:13'),
(2, 'Jean-Pierre', 'Segado', 'jean-pierre@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'agent', '+33600001111', '2025-05-27 14:39:28', '2025-05-30 11:41:13'),
(3, 'Alice', 'Durand', 'alice@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'client', '+33712345678', '2025-05-27 14:39:28', '2025-05-30 11:41:13'),
(4, 'Georges', 'AL Haddad', 'georges.alhaddad28@gmail.com', '$2y$12$R0BFXQGQUjmZ3iDsKN0SG.rkbKHjZl.ETxcp8yOwbgr0dG7bgwsZm', 'client', '+33675748823', '2025-05-27 14:48:05', '2025-05-30 12:45:38'),
(5, 'John', 'Doe', 'g@gmail.com', '$2y$12$w2UsUXcEatODeGGkWFQiT.bFOecSAi33gMYfAAN9Omg78t26ejjv.', 'agent', '0123456789', '2025-05-28 13:00:11', '2025-05-30 11:41:13'),
(6, 'Alex', 'Colinet', 'ge@gmail.com', '$2y$12$.ff6zD4bkt8SjOGmCwrqYeqFz79Ht/eoNOB9AVsz4sIfL2wBdCN4W', 'admin', '0125457885', '2025-05-29 12:16:38', '2025-05-30 11:41:13');

-- --------------------------------------------------------

--
-- Table structure for table `user_favorites`
--

DROP TABLE IF EXISTS `user_favorites`;
CREATE TABLE IF NOT EXISTS `user_favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `property_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_property` (`user_id`,`property_id`),
  KEY `property_id` (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_favorites`
--

INSERT INTO `user_favorites` (`id`, `user_id`, `property_id`, `created_at`) VALUES
(3, 4, 5, '2025-05-30 12:46:16');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agents`
--
ALTER TABLE `agents`
  ADD CONSTRAINT `agents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_availability`
--
ALTER TABLE `agent_availability`
  ADD CONSTRAINT `agent_availability_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_information`
--
ALTER TABLE `payment_information`
  ADD CONSTRAINT `payment_information_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD CONSTRAINT `user_favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_favorites_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
