-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 01, 2025 at 05:03 PM
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
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `last_name` varchar(50) NOT NULL DEFAULT '',
  `phone` varchar(15) NOT NULL DEFAULT '',
  `cv_file_path` varchar(255) DEFAULT '',
  `profile_picture_path` varchar(255) DEFAULT NULL,
  `agency_name` varchar(255) NOT NULL DEFAULT 'Independent Agent',
  `agency_email` varchar(100) DEFAULT '',
  `years_experience` int DEFAULT '0',
  `average_rating` decimal(3,2) DEFAULT '0.00',
  `total_sales` decimal(15,2) DEFAULT '0.00',
  `total_transactions` int DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `agency_email` (`agency_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`user_id`, `first_name`, `last_name`, `phone`, `cv_file_path`, `profile_picture_path`, `agency_name`, `agency_email`, `years_experience`, `average_rating`, `total_sales`, `total_transactions`) VALUES
(6, 'Alex', 'Colinet', '0125457885', '', '', 'Independent Agent', 'ge@gmail.com', 0, 0.00, 0.00, 0),
(8, 'Claire', 'Dubois', '', 'uploads/agent_cvs/cv_1748782835_b9768fb333e40d40.pdf', NULL, 'Independent Agent', 'c@gmail.com', 2, 0.00, 0.00, 0),
(9, 'Antoine', 'Lefevre', '', 'uploads/agent_cvs/cv_1748783736_3f06774d2a6072b8.pdf', '', 'Omnes Immobilier', 'test@gmail.com', 1, 0.00, 0.00, 0),
(10, 'Sophie', 'Moreau', '', 'uploads/agent_cvs/cv_1748787896_689d0c58dbecc0bb.pdf', NULL, 'Omnes Immobilier', 'sophie@gmail.com', 0, 0.00, 0.00, 0),
(11, 'Alain', 'Marnier', '', 'uploads/agent_cvs/cv_1748793045_22953710c89b3fa5.pdf', '', 'Omnes Immobilier', 'georges.alhaddad28@gmail.com', 5, 0.00, 0.00, 0),
(12, 'Camille', 'Bernard', '', 'uploads/agent_cvs/cv_1748793094_c4634dd4a7bacfb4.pdf', '', 'Independent Agent', 'camille@omnes.fr', 2, 0.00, 0.00, 0);

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
  `user_id` int DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `agent_availability`
--

INSERT INTO `agent_availability` (`id`, `agent_id`, `day_of_week`, `specific_date`, `start_time`, `end_time`, `is_available`, `availability_type`, `created_at`, `updated_at`, `user_id`, `notes`) VALUES
(6, 6, 'Monday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13', NULL, NULL),
(7, 6, 'Tuesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13', NULL, NULL),
(8, 6, 'Wednesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13', NULL, NULL),
(9, 6, 'Thursday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13', NULL, NULL),
(10, 6, 'Friday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13', NULL, NULL),
(11, 6, 'Saturday', NULL, '11:00:00', '15:00:00', 1, 'weekly', '2025-05-30 11:41:13', '2025-05-30 11:41:13', NULL, NULL),
(17, 11, 'Monday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(18, 11, 'Monday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(19, 11, 'Tuesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(20, 11, 'Tuesday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(21, 11, 'Wednesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(22, 11, 'Wednesday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(23, 11, 'Thursday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(24, 11, 'Thursday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(25, 11, 'Friday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(26, 11, 'Friday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:21', '2025-06-01 16:57:21', NULL, NULL),
(27, 9, 'Monday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(28, 9, 'Monday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(29, 9, 'Tuesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(30, 9, 'Tuesday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(31, 9, 'Wednesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(32, 9, 'Wednesday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(33, 9, 'Thursday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(34, 9, 'Thursday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(35, 9, 'Friday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(36, 9, 'Friday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:57:41', '2025-06-01 16:57:41', NULL, NULL),
(37, 12, 'Monday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(38, 12, 'Monday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(39, 12, 'Tuesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(40, 12, 'Tuesday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(41, 12, 'Wednesday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(42, 12, 'Wednesday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(43, 12, 'Thursday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(44, 12, 'Thursday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(45, 12, 'Friday', NULL, '09:00:00', '17:00:00', 1, 'weekly', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL),
(46, 12, 'Friday', NULL, '12:00:00', '13:00:00', 0, 'lunch_break', '2025-06-01 16:58:16', '2025-06-01 16:58:16', NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `client_id`, `agent_id`, `property_id`, `appointment_date`, `status`, `location`, `created_at`, `updated_at`) VALUES
(2, 4, 6, 2, '2025-05-31 11:00:00', 'cancelled', '45 Rue de Famille, Paris', '2025-05-30 11:41:14', '2025-05-30 11:41:14'),
(3, 4, 6, 2, '2025-05-31 12:00:00', 'cancelled', '45 Rue de Famille, Paris', '2025-05-30 11:41:14', '2025-05-30 17:18:15');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `property_id`, `message`, `subject`, `reply_to_id`, `sent_at`, `is_read`) VALUES
(8, 4, 11, 6, 'Bonsoir Alain', 'Test', NULL, '2025-06-01 16:56:25', 1),
(9, 11, 4, 6, 'Bonsoir Georges !\r\nTest1 2 3', 'Re: Test', 8, '2025-06-01 16:56:58', 0);

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `agent_id`, `title`, `description`, `images`, `price`, `property_type`, `status`, `address_line1`, `city`, `state`, `postal_code`, `country`, `bedrooms`, `bathrooms`, `year_built`, `living_area`, `has_parking`, `parking_spaces`, `has_garden`, `created_at`, `updated_at`) VALUES
(1, 9, 'Modern Apartment', '2 bed · 1 bath · 70m² apartment', '[\"../assets/images/property33.jpg\", \"../assets/images/property3-2.jpg\", \"../assets/images/property3-3.jpg\", \"../assets/images/property3-4.jpg\", \"../assets/images/property3-5.jpg\"]', 550000.00, 'apartment', 'available', '123 Boulevard de Paris', 'Paris', 'Île-de-France', '75015', 'France', 2, 1, 2010, 70, 0, 0, 0, '2025-05-28 14:15:44', '2025-06-01 15:27:16'),
(2, 11, 'Family House', '4 bed · 3 bath · 150m² house', '[\"../assets/images/property11.jpg\", \"../assets/images/property1-2.jpg\", \"../assets/images/property1-3.jpg\", \"../assets/images/property1-4.jpg\", \"../assets/images/property1-5.jpg\"]', 1200000.00, 'house', 'available', '45 Rue de Famille', 'Paris', 'Île-de-France', '75016', 'France', 4, 3, 2008, 150, 0, 0, 0, '2025-05-28 14:15:44', '2025-06-01 15:51:58'),
(3, 12, 'Cozy Studio', '1 bed · 1 bath · 35m² studio', '[\"../assets/images/property44.jpg\", \"../assets/images/property4-2.jpg\", \"../assets/images/property4-3.jpg\", \"../assets/images/property4-4.jpg\", \"../assets/images/property4-5.jpg\"]', 350000.00, 'apartment', 'available', '10 Rue du Studio', 'Paris', 'Île-de-France', '75007', 'France', 1, 1, 2014, 35, 0, 0, 0, '2025-05-28 14:15:44', '2025-06-01 15:52:12'),
(4, 8, 'Luxury Villa', '5 bed · 4 bath · 300m² villa', '[\"../assets/images/property13.jpg\", \"../assets/images/property13-2.jpg\", \"../assets/images/property13-3.jpg\", \"../assets/images/property13-4.jpg\", \"../assets/images/property13-5.jpg\"]', 3500000.00, 'house', 'available', '1 Avenue de la Plage', 'Saint-Tropez', 'Provence-Alpes-Côte d\'Azur', '83990', 'France', 5, 4, 1998, 300, 0, 0, 0, '2025-05-28 14:15:44', '2025-06-01 16:25:14'),
(5, 10, 'Beachfront Condo', '3 bed · 2 bath · 120m² condo', '[\"../assets/images/property8.jpg\", \"../assets/images/property8-2.jpg\", \"../assets/images/property8-3.jpg\", \"../assets/images/property8-4.jpg\", \"../assets/images/property8-5.jpg\"]', 800000.00, 'apartment', 'available', '5 Promenade des Anglais', 'Nice', 'Provence-Alpes-Côte d\'Azur', '06000', 'France', 3, 2, 1800, 120, 0, 0, 0, '2025-05-28 14:15:44', '2025-06-01 16:25:51'),
(6, 11, 'Building Land', '180m² plot for construction', '[\"../assets/images/property55.jpg\", \"../assets/images/property5-2.jpg\", \"../assets/images/property5-3.jpg\", \"../assets/images/property5-4.jpg\", \"../assets/images/property5-5.jpg\"]', 200000.00, 'land', 'available', 'Rue des Vignes', 'Bordeaux', 'Nouvelle-Aquitaine', '33000', 'France', 0, 0, 1997, 180, 0, 0, 0, '2025-05-28 14:15:44', '2025-06-01 16:41:16'),
(7, 11, 'Historic Mansion', '6 bed · 5 bath · 300m² mansion', '[\"../assets/images/property6.jpg\", \"../assets/images/property6-2.jpg\", \"../assets/images/property6-3.jpg\", \"../assets/images/property6-4.jpg\", \"../assets/images/property6-5.jpg\"]', 2500000.00, 'house', 'available', 'Château de Normandie', 'Bayeux', 'Normandie', '14000', 'France', 6, 5, 0, 300, 0, 0, 0, '2025-05-28 12:15:44', '2025-06-01 16:59:44'),
(8, 12, 'Modern City Loft', '1 bed · 1 bath · 40m² loft', '[\"../assets/images/property15.jpg\", \"../assets/images/property15-2.jpg\", \"../assets/images/property15-3.jpg\", \"../assets/images/property15-4.jpg\", \"../assets/images/property15-5.jpg\"]', 450000.00, 'apartment', 'available', '789 Rue du Loft', 'Paris', 'Île-de-France', '75015', 'France', 1, 1, 0, 40, 0, 0, 0, '2025-05-28 12:15:44', '2025-06-01 17:00:47'),
(9, 9, 'Countryside Cottage', '3 bed · 2 bath · 110m² cottage', '[\"../assets/images/property9.jpg\", \"../assets/images/property9-2.jpg\", \"../assets/images/property9-3.jpg\", \"../assets/images/property9-4.jpg\", \"../assets/images/property9-5.jpg\"]', 420000.00, 'house', 'available', '12 Chemin de Campagne', 'Lyon', 'Auvergne-Rhône-Alpes', '69000', 'France', 3, 2, 0, 110, 0, 0, 0, '2025-05-28 12:15:44', '2025-05-28 12:54:38'),
(10, 10, 'Modern Office Space', '250m² office space', '[\"../assets/images/property7.jpg\", \"../assets/images/property7-2.jpg\", \"../assets/images/property7-3.jpg\", \"../assets/images/property7-4.jpg\", \"../assets/images/property7-5.jpg\"]', 1500000.00, 'commercial', 'available', '99 Boulevard Haussmann', 'Paris', 'Île-de-France', '75008', 'France', 0, 0, 0, 250, 0, 0, 0, '2025-05-28 12:15:44', '2025-06-01 17:01:31'),
(11, 11, 'Retail Storefront', '80m² retail shop', '[\"../assets/images/property11-1.jpg\", \"../assets/images/property11-2.jpg\", \"../assets/images/property11-3.jpg\", \"../assets/images/property11-4.jpg\", \"../assets/images/property11-5.jpg\"]', 600000.00, 'commercial', 'available', '10 Rue Sainte-Catherine', 'Bordeaux', 'Nouvelle-Aquitaine', '33000', 'France', 0, 0, 0, 80, 0, 0, 0, '2025-05-28 12:15:44', '2025-05-28 12:24:38'),
(12, 12, 'Apartment for Rent', '2 bed · 1 bath · 60m² rental apartment', '[\"../assets/images/property22.jpg\", \"../assets/images/property12-2.jpg\", \"../assets/images/property12-3.jpg\", \"../assets/images/property12-4.jpg\", \"../assets/images/property12-5.jpg\"]', 1800.00, 'rental', 'available', '20 Rue du Faubourg', 'Paris', 'Île-de-France', '75010', 'France', 2, 1, 0, 60, 0, 0, 0, '2025-05-28 12:15:44', '2025-05-30 10:03:52'),
(13, 9, 'Villa for Rent', '4 bed · 3 bath · 200m² rental villa', '[\"../assets/images/property22.jpg\", \"../assets/images/property2-2.jpg\", \"../assets/images/property2-3.jpg\", \"../assets/images/property2-4.jpg\", \"../assets/images/property2-5.jpg\"]', 3500.00, 'rental', 'available', '25 Avenue de la Mer', 'Cannes', 'Provence-Alpes-Côte d\'Azur', '06400', 'France', 4, 3, 0, 200, 0, 0, 0, '2025-05-28 12:15:44', '2025-06-01 17:03:02'),
(14, 10, 'Auction Property', '3 bed · 2 bath · 85m² auction apartment', '[\"../assets/images/property14.jpg\", \"../assets/images/property14-2.jpg\", \"../assets/images/property14-3.jpg\", \"../assets/images/property14-4.jpg\", \"../assets/images/property14-5.jpg\"]', 280000.00, 'auction', 'available', '15 Rue de la République', 'Marseille', 'Provence-Alpes-Côte d\'Azur', '13001', 'France', 3, 2, 0, 85, 0, 0, 0, '2025-05-28 12:15:44', '2025-05-28 13:04:38');

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `phone`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'One', 'admin@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'admin', '+33123456789', '2025-05-27 14:39:28', '2025-05-30 11:41:13'),
(3, 'Alice', 'Durand', 'alice@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaB1b2KzzZWyUWAK1ez.Yi2E5aG', 'client', '+33712345678', '2025-05-27 14:39:28', '2025-05-30 11:41:13'),
(4, 'Georges', 'AL Haddad', 'georges.alhaddad28@gmail.com', '$2y$12$R0BFXQGQUjmZ3iDsKN0SG.rkbKHjZl.ETxcp8yOwbgr0dG7bgwsZm', 'client', '+33675748823', '2025-05-27 14:48:05', '2025-06-01 16:50:03'),
(6, 'Alex', 'Colinet', 'ge@gmail.com', '$2y$12$.ff6zD4bkt8SjOGmCwrqYeqFz79Ht/eoNOB9AVsz4sIfL2wBdCN4W', 'admin', '0125457885', '2025-05-29 12:16:38', '2025-06-01 15:45:28'),
(8, 'Claire', 'Dubois', 'c@gmail.com', '$2y$12$7W1faaUm7WgQwnaIt1MBsuzhXsl2GdfIoc4CfPWBu4EZX0sbYySY.', 'agent', '0123456789', '2025-06-01 13:00:35', '2025-06-01 13:00:35'),
(9, 'Antoine', 'Lefevre', 'ant@gmail.com', '$2y$10$aL9qoT9ldcjnE86sXIyffumm3JLhdXGZIUDVh6VaT4taSEBhmsoTG', 'agent', '0123456789', '2025-06-01 13:15:37', '2025-06-01 16:57:32'),
(10, 'Sophie', 'Moreau', 'sophie@gmail.com', '$2y$12$JciGWKIAewVehtQ6V7YnKOsXBMiDGdZjNdx22Slw4zixPz6JC2HIu', 'agent', '0123456789', '2025-06-01 14:24:56', '2025-06-01 14:24:56'),
(11, 'Alain', 'Marnier', 'alain@omnes.fr', '$2y$10$tHOiUMRej2JjRzFQd4XrDu.7Dh2RrixRqE7ZnlxjzwYF5RL0MN4Qm', 'agent', '0123456789', '2025-06-01 15:50:45', '2025-06-01 16:56:40'),
(12, 'Camille', 'Bernard', 'camille@omnes.fr', '$2y$10$ZZNO18rL5OtZEwtDXAtMye1izcCu9ssx2rFWCu7Tjf2q1jtWBzNdG', 'agent', '0198765432', '2025-06-01 15:51:34', '2025-06-01 16:57:52');

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_favorites`
--

INSERT INTO `user_favorites` (`id`, `user_id`, `property_id`, `created_at`) VALUES
(3, 4, 5, '2025-05-30 12:46:16'),
(7, 4, 2, '2025-06-01 09:20:18'),
(8, 4, 3, '2025-06-01 09:44:41');

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
