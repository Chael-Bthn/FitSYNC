-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2026 at 04:06 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fitsync`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `branch_id` smallint(5) UNSIGNED NOT NULL,
  `check_in_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_logs`
--

INSERT INTO `attendance_logs` (`id`, `user_id`, `branch_id`, `check_in_at`, `notes`) VALUES
(7, 2, 3, '2026-06-12 20:52:17', NULL),
(8, 3, 3, '2026-06-12 21:02:14', NULL),
(9, 5, 1, '2026-06-15 09:34:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `city` varchar(64) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `maps_embed` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `city`, `address`, `maps_embed`, `is_active`, `created_at`) VALUES
(1, 'Main Branch', 'Quezon City', 'Quezon Ave, Quezon City', NULL, 1, '2026-05-19 02:43:50'),
(2, 'Makati Branch', 'Makati', 'J Victor St, Makati', NULL, 1, '2026-05-19 02:43:50'),
(3, 'BGC Branch', 'Taguig', 'Bonifacio High Street, BGC', NULL, 1, '2026-05-19 02:43:50'),
(4, 'Ortigas Branch', 'Pasig', 'Ortigas Ave, Pasig', NULL, 1, '2026-05-19 02:43:50'),
(5, 'Eastwood City Branch', 'Quezon City', 'Eastwood Ave, Libis', NULL, 1, '2026-05-19 02:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `branch_announcements`
--

CREATE TABLE `branch_announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` smallint(5) UNSIGNED DEFAULT NULL,
  `title` varchar(140) NOT NULL,
  `body` text NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branch_announcements`
--

INSERT INTO `branch_announcements` (`id`, `branch_id`, `title`, `body`, `starts_at`, `ends_at`, `is_active`, `created_at`) VALUES
(1, 1, 'Holiday operating hours', 'Main Branch will close at 6:00 PM on May 27 for scheduled maintenance.', '2026-05-23 00:00:00', '2026-05-27 18:00:00', 1, '2026-05-19 02:43:50'),
(2, 2, 'Studio room maintenance', 'Makati studio room access may be limited during morning equipment inspection.', '2026-05-23 00:00:00', '2026-05-26 12:00:00', 1, '2026-05-19 02:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `branch_operating_hours`
--

CREATE TABLE `branch_operating_hours` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` smallint(5) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branch_operating_hours`
--

INSERT INTO `branch_operating_hours` (`id`, `branch_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
(1, 1, 1, '06:00:00', '22:00:00', 0),
(2, 1, 2, '06:00:00', '22:00:00', 0),
(3, 1, 3, '06:00:00', '22:00:00', 0),
(4, 1, 4, '06:00:00', '22:00:00', 0),
(5, 1, 5, '06:00:00', '22:00:00', 0),
(6, 1, 6, '08:00:00', '20:00:00', 0),
(7, 1, 7, '08:00:00', '18:00:00', 0),
(8, 2, 1, '06:00:00', '21:00:00', 0),
(9, 2, 2, '06:00:00', '21:00:00', 0),
(10, 2, 3, '06:00:00', '21:00:00', 0),
(11, 2, 4, '06:00:00', '21:00:00', 0),
(12, 2, 5, '06:00:00', '21:00:00', 0),
(13, 2, 6, '08:00:00', '18:00:00', 0),
(14, 2, 7, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `added_at`) VALUES
(11, 5, 1, 1, '2026-06-15 01:59:00');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `trainer_name` varchar(120) DEFAULT NULL,
  `branch_id` smallint(5) UNSIGNED NOT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `capacity` smallint(5) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `title`, `description`, `trainer_name`, `branch_id`, `duration_minutes`, `capacity`, `is_active`, `created_at`) VALUES
(1, 'Strength Basics', 'Foundational barbell and machine work for general strength.', 'Coach Marco', 1, 60, 18, 1, '2026-05-19 02:43:50'),
(2, 'Mobility Flow', 'Low-impact mobility and flexibility session.', 'Coach Ana', 1, 45, 20, 1, '2026-05-19 02:43:50'),
(3, 'HIIT Conditioning', 'Short interval conditioning class for all fitness levels.', 'Coach Lei', 2, 45, 16, 1, '2026-05-19 02:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `class_bookings`
--

CREATE TABLE `class_bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `class_schedule_id` int(10) UNSIGNED NOT NULL,
  `booking_status` enum('booked','cancelled','attended') NOT NULL DEFAULT 'booked',
  `booked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `branch_id` smallint(5) UNSIGNED NOT NULL,
  `scheduled_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('scheduled','cancelled','completed') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `class_id`, `branch_id`, `scheduled_date`, `start_time`, `end_time`, `status`, `created_at`) VALUES
(1, 1, 1, '2026-05-23', '18:00:00', '19:00:00', 'scheduled', '2026-05-19 02:43:50'),
(2, 2, 1, '2026-05-25', '07:00:00', '07:45:00', 'scheduled', '2026-05-19 02:43:50'),
(3, 3, 2, '2026-05-26', '18:30:00', '19:15:00', 'scheduled', '2026-05-19 02:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `subject` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','archived') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 'John Doe', 'member3@fitsync.com', '123456789', 'Inquiry', 'Do you offer free trials?', 'new', '2026-05-26 07:29:39'),
(2, 'Maria Clara', 'member3@fitsync.com', NULL, 'Membership renewal request — Maria Clara', 'Member: Maria Clara (member3@fitsync.com)\nPlan: 12 Months\nStarts: 2027-05-27\nEnds: 2028-05-26\nAmount: ₱7,999.00\nBranch ID: 1\nPayment method: bank_transfer\nReference: RNW-7F93D744', 'new', '2026-05-26 09:10:22'),
(3, 'Maria Clara', 'member3@fitsync.com', NULL, 'Membership renewal request — Maria Clara', 'Member: Maria Clara (member3@fitsync.com)\nPlan: 12 Months\nStarts: 2027-05-27\nEnds: 2028-05-26\nAmount: ₱7,999.00\nBranch ID: 1\nPayment method: bank_transfer\nReference: RNW-77A0DBA1', 'new', '2026-05-26 09:32:53'),
(4, 'John Doe', 'member4@fitsync.com', NULL, 'New registration awaiting approval: John Doe', 'A new member has registered and requires approval.\n\nName: John Doe\nEmail: member4@fitsync.com\nPlan: 1 Month\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-01 20:40:14'),
(5, 'Zander Atenciana', 'member5@fitsync.com', NULL, 'New registration awaiting approval: Zander Atenciana', 'A new member has registered and requires approval.\n\nName: Zander Atenciana\nEmail: member5@fitsync.com\nPlan: 12 Months\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-01 22:40:23'),
(6, 'Rhendel Ancheta', 'member6@fitsync.com', NULL, 'New registration awaiting approval: Rhendel Ancheta', 'A new member has registered and requires approval.\n\nName: Rhendel Ancheta\nEmail: member6@fitsync.com\nPlan: 12 Months\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-09 10:36:23'),
(7, 'Rhendel Ancheta', 'member6@fitsync.com', NULL, 'New registration awaiting approval: Rhendel Ancheta', 'A new member has registered and requires approval.\n\nName: Rhendel Ancheta\nEmail: member6@fitsync.com\nPlan: 12 Months\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-09 11:40:32'),
(8, 'Juan Dela Cruz', 'member1@fitsync.com', NULL, 'New registration awaiting approval: Juan Dela Cruz', 'A new member has registered and requires approval.\n\nName: Juan Dela Cruz\nEmail: member1@fitsync.com\nPlan: 1 Month\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-12 20:50:19'),
(9, 'Pedro Penduco', 'member2@fitsync.com', NULL, 'New registration awaiting approval: Pedro Penduco', 'A new member has registered and requires approval.\n\nName: Pedro Penduco\nEmail: member2@fitsync.com\nPlan: 3 Months\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-12 20:56:40'),
(10, 'Maria Clara', 'member3@fitsync.com', NULL, 'New registration awaiting approval: Maria Clara', 'A new member has registered and requires approval.\n\nName: Maria Clara\nEmail: member3@fitsync.com\nPlan: 6 Months\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-12 21:03:30'),
(11, 'John Doe', 'member4@fitsync.com', NULL, 'New registration awaiting approval: John Doe', 'A new member has registered and requires approval.\n\nName: John Doe\nEmail: member4@fitsync.com\nPlan: 12 Months\nBranch ID: 1\n\nReview and approve from the admin panel.', 'new', '2026-06-15 09:32:49'),
(12, 'John Doe', 'member4@fitsync.com', NULL, 'Membership renewal request — John Doe', 'Member: John Doe (member4@fitsync.com)\nPlan: 1 Month\nStarts: 2027-06-16\nEnds: 2027-07-16\nAmount: ₱999.00\nBranch ID: 1\nPayment method: gcash\nReference: RNW-1C3DE14F', 'new', '2026-06-15 09:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `branch_id` smallint(5) UNSIGNED DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL CHECK (`rating` between 1 and 5),
  `body` text NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `branch_id`, `rating`, `body`, `is_visible`, `created_at`) VALUES
(1, NULL, 5, 5, 'Equipment are solid and facilities are well-maintained!', 1, '2026-05-26 07:28:23');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `email`, `ip_address`, `user_agent`, `success`, `created_at`) VALUES
(1, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 10:45:18'),
(2, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 10:57:17'),
(3, 2, 'member@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 10:57:40'),
(4, 2, 'member@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 11:03:04'),
(5, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 11:15:36'),
(6, 3, 'mbathan619@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 11:18:17'),
(7, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 11:18:30'),
(8, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 11:58:35'),
(9, 3, 'mbathan619@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 12:05:54'),
(10, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 12:09:54'),
(11, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 12:48:31'),
(12, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 12:49:12'),
(13, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:22:39'),
(14, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:26:23'),
(15, 4, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:27:21'),
(16, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:28:31'),
(17, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:29:56'),
(18, 4, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:33:18'),
(19, 3, 'mbathan619@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:35:22'),
(20, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:36:19'),
(21, 2, 'member@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:43:58'),
(22, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 07:48:23'),
(23, 4, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 08:40:53'),
(24, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 08:55:54'),
(25, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:00:32'),
(26, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:09:35'),
(27, 5, 'member3@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:10:11'),
(28, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:10:32'),
(29, 5, 'member3@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:16:47'),
(30, 5, 'member3@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:17:26'),
(31, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:19:07'),
(32, 5, 'member3@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:32:00'),
(33, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-26 09:33:03'),
(34, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-28 12:16:23'),
(35, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 20:38:44'),
(36, 6, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, '2026-06-01 20:40:26'),
(37, 6, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, '2026-06-01 20:40:34'),
(38, 6, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, '2026-06-01 20:40:35'),
(39, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 20:40:43'),
(40, 6, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 20:58:04'),
(41, 7, 'member5@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 22:54:29'),
(42, 7, 'member5@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 22:56:39'),
(43, 6, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 22:57:47'),
(44, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-01 23:39:54'),
(45, 7, 'member5@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, '2026-06-02 00:05:16'),
(46, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-02 00:05:26'),
(47, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 1, '2026-06-06 20:38:52'),
(48, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-06 20:39:44'),
(49, 3, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-06 20:41:22'),
(50, 4, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-06 20:42:49'),
(51, 1, 'admin@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-08 20:05:27'),
(52, 4, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-08 20:07:26'),
(53, 4, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-08 20:08:45'),
(54, 3, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-08 21:25:07'),
(55, 2, 'member@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-08 21:34:36'),
(56, 1, 'admin@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-09 10:34:22'),
(57, 2, 'member@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-09 10:34:46'),
(58, 1, 'admin@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-09 11:09:53'),
(59, 8, 'member6@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-09 11:15:37'),
(60, 8, 'member6@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-09 11:25:00'),
(61, 1, 'admin@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-09 11:29:56'),
(62, 8, 'member6@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-09 11:30:18'),
(63, NULL, 'member6@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 0, '2026-06-09 11:39:48'),
(64, 2, 'member@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-09 11:41:52'),
(65, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-12 20:30:22'),
(66, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-12 20:32:20'),
(67, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-12 20:50:42'),
(68, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-06-12 20:51:30'),
(69, 1, 'admin@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 1, '2026-06-12 20:51:46'),
(70, 1, 'admin@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 1, '2026-06-12 21:01:28'),
(71, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 13:01:46'),
(72, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 13:02:42'),
(73, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 13:05:59'),
(74, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 14:28:25'),
(75, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 14:42:29'),
(76, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 14:54:02'),
(77, 3, 'member2@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 14:57:06'),
(78, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 14:57:16'),
(79, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 14:59:38'),
(80, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:08:52'),
(81, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:10:08'),
(82, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:12:59'),
(83, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:16:03'),
(84, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:17:23'),
(85, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:20:47'),
(86, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:21:08'),
(87, NULL, 'admin@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 0, '2026-06-14 15:28:38'),
(88, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:28:41'),
(89, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 15:37:24'),
(90, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 16:28:19'),
(91, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 16:37:52'),
(92, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 16:38:46'),
(93, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 16:38:52'),
(94, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-14 16:45:01'),
(95, 2, 'member1@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:17:54'),
(96, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:18:16'),
(97, 4, 'member3@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:20:54'),
(98, 1, 'admin@fitsync.com', '10.205.91.197', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 1, '2026-06-15 09:27:35'),
(99, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:42:51'),
(100, 5, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:43:05'),
(101, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:43:33'),
(102, 5, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:44:33'),
(103, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:50:16'),
(104, 5, 'member4@fitsync.com', '10.205.91.197', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 1, '2026-06-15 09:55:00'),
(105, 5, 'member4@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 1, '2026-06-15 09:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `memberships`
--

CREATE TABLE `memberships` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `plan_id` smallint(5) UNSIGNED NOT NULL,
  `branch_id` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `starts_at` date NOT NULL,
  `ends_at` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','debit_card','gcash','maya','bank_transfer','cash') NOT NULL DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'paid',
  `status` enum('pending','active','expired','cancelled','frozen') NOT NULL DEFAULT 'active',
  `payment_ref` varchar(128) DEFAULT NULL,
  `proof_file_path` varchar(255) DEFAULT NULL,
  `proof_uploaded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `user_id`, `plan_id`, `branch_id`, `starts_at`, `ends_at`, `amount_paid`, `payment_method`, `payment_status`, `status`, `payment_ref`, `proof_file_path`, `proof_uploaded_at`, `created_at`, `updated_at`) VALUES
(15, 2, 1, 3, '2026-06-12', '2026-07-12', 999.00, 'gcash', 'paid', 'active', NULL, 'uploads/payment_proofs/proof_6a2c00888b1669.26652716.png', '2026-06-12 14:50:16', '2026-06-12 20:50:19', '2026-06-12 20:51:55'),
(16, 3, 2, 3, '2026-06-12', '2026-09-10', 2699.00, 'gcash', 'paid', 'active', NULL, 'uploads/payment_proofs/proof_6a2c02068ea6d5.34678487.png', '2026-06-12 14:56:38', '2026-06-12 20:56:40', '2026-06-12 21:02:00'),
(17, 4, 3, 3, '2026-06-12', '2026-12-09', 4799.00, 'gcash', 'paid', 'active', NULL, 'uploads/payment_proofs/proof_6a2c039ee593e4.59611117.png', '2026-06-12 15:03:26', '2026-06-12 21:03:30', '2026-06-15 09:20:36'),
(18, 5, 4, 1, '2026-06-15', '2027-06-15', 7999.00, 'gcash', 'paid', 'active', NULL, 'uploads/payment_proofs/proof_6a2f563f7a2289.89126701.png', '2026-06-15 03:32:47', '2026-06-15 09:32:49', '2026-06-15 09:33:44'),
(19, 5, 1, 1, '2027-06-16', '2027-07-16', 999.00, 'gcash', 'paid', 'active', 'RNW-F9CBBABE', NULL, NULL, '2026-06-15 09:36:32', '2026-06-15 09:36:54');

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `slug` varchar(16) NOT NULL,
  `label` varchar(32) NOT NULL,
  `duration_days` smallint(5) UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  `features_json` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`id`, `slug`, `label`, `duration_days`, `price`, `original_price`, `features_json`, `is_active`, `sort_order`) VALUES
(1, '1mo', '1 Month', 30, 999.00, 1299.00, NULL, 1, 1),
(2, '3mo', '3 Months', 90, 2699.00, 3897.00, NULL, 1, 2),
(3, '6mo', '6 Months', 180, 4799.00, 7794.00, NULL, 1, 3),
(4, '12mo', '12 Months', 365, 7999.00, 15588.00, NULL, 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `member_notes`
--

CREATE TABLE `member_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `note_body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','processing','out_for_delivery','delivered','ready_for_pickup','picked_up','cancelled','completed') NOT NULL DEFAULT 'pending',
  `fulfillment_method` enum('delivery','pickup') NOT NULL DEFAULT 'delivery',
  `delivery_fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  `shipping_provider` varchar(40) DEFAULT NULL,
  `delivery_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_address`)),
  `pickup_branch_id` smallint(5) UNSIGNED DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` varchar(20) DEFAULT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',
  `payment_status` enum('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  `proof_of_payment` varchar(255) DEFAULT NULL,
  `order_notes` text DEFAULT NULL,
  `recipient_name` varchar(160) DEFAULT NULL,
  `recipient_contact` varchar(30) DEFAULT NULL,
  `recipient_email` varchar(160) DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `status`, `fulfillment_method`, `delivery_fee`, `shipping_provider`, `delivery_address`, `pickup_branch_id`, `pickup_date`, `pickup_time`, `payment_method`, `payment_status`, `proof_of_payment`, `order_notes`, `recipient_name`, `recipient_contact`, `recipient_email`, `cancel_reason`, `created_at`, `updated_at`) VALUES
(1, 2, 849.00, 'completed', 'delivery', 150.00, NULL, '{\"region\":\"Provincial\",\"province\":\"-\",\"city\":\"-\",\"barangay\":\"-\",\"street\":\"26A 301 Gov Carpio Ave\",\"zip\":\"-\",\"landmark\":\"-\",\"notes\":\"\"}', NULL, NULL, NULL, 'gcash', 'paid', 'uploads/proof/proof_2_1781418467_8ecf30b2.png', '', 'Michael Bathan', '09472100006', 'member1@fitsync.com', NULL, '2026-06-14 06:27:55', '2026-06-14 07:09:44'),
(2, 2, 1499.00, 'cancelled', 'pickup', 0.00, NULL, NULL, 1, '2026-06-15', '10:00 AM', 'gcash', 'pending', 'uploads/proof/proof_2_1781421619_fc49e793.png', '', 'Michael Bathan', '09472100006', 'member1@fitsync.com', NULL, '2026-06-14 07:20:24', '2026-06-14 07:28:30'),
(3, 2, 7495.00, 'completed', 'pickup', 0.00, NULL, NULL, 2, '2026-06-20', '8:00 AM', 'cash_on_pickup', 'pending', NULL, '', 'Michael Bathan', '09472100006', 'member1@fitsync.com', NULL, '2026-06-14 08:38:27', '2026-06-14 08:40:45'),
(4, 5, 699.00, 'completed', 'pickup', 0.00, NULL, NULL, 1, '2026-06-20', '1:00 PM', 'cash_on_pickup', 'paid', NULL, '', 'John Doe', '09197670403', 'member4@fitsync.com', NULL, '2026-06-15 01:41:21', '2026-06-15 01:44:46');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 2, 1, 699.00),
(2, 2, 1, 1, 1499.00),
(3, 3, 1, 5, 1499.00),
(4, 4, 2, 1, 699.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_reviews`
--

CREATE TABLE `order_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'Supplement',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `category`, `price`, `image`, `is_active`, `created_at`) VALUES
(1, 'Whey Protein', 'Premium whey protein isolate with 25g of protein per serving. Fast-absorbing formula ideal for post-workout recovery. Available in Chocolate, Vanilla, and Strawberry flavors.', 'Supplement', 1499.00, 'assets/WHEY_PROTEIN.png', 1, '2026-06-14 05:01:29'),
(2, 'Creatine Monohydrate', 'Pure micronized creatine monohydrate. Proven to increase strength, power output, and muscle volume. Unflavored — mixes easily with any drink. 300g per container.', 'Supplement', 699.00, 'assets/CREATINE.png', 1, '2026-06-14 05:01:29'),
(3, 'Mass Gainer', 'High-calorie mass gainer with 50g protein and 250g carbs per serving. Designed for hardgainers who struggle to meet caloric goals. Rich chocolate flavor with easy mixability.', 'Supplement', 1899.00, 'assets/MASS_GAINER.png', 1, '2026-06-14 05:01:29'),
(4, 'Pre-Workout', 'High-stim pre-workout formula with 300mg caffeine, beta-alanine, and L-citrulline. Explosive energy, intense focus, and skin-splitting pumps. 30 servings.', 'Supplement', 999.00, 'assets/PREWORKOUT.png', 1, '2026-06-14 05:01:29'),
(5, 'BCAA', 'Branched-chain amino acids in a 2:1:1 ratio (Leucine, Isoleucine, Valine). Reduces muscle breakdown during training and accelerates recovery. Refreshing fruit punch flavor.', 'Supplement', 799.00, 'assets/BCAA.png', 1, '2026-06-14 05:01:29'),
(6, 'Multivitamins', 'Complete daily multivitamin formula with 23 essential vitamins and minerals. Supports immune function, energy metabolism, and overall health. 90 tablets per bottle.', 'Supplement', 549.00, 'assets/MULTIVITAMIN.png', 1, '2026-06-14 05:01:29'),
(7, 'Fish Oil', 'High-potency omega-3 fish oil with 1000mg EPA and DHA per softgel. Supports heart health, joint mobility, and cognitive function. Enteric-coated to prevent fishy aftertaste.', 'Supplement', 449.00, 'assets/FISH_OIL.png', 1, '2026-06-14 05:01:29'),
(8, 'Shaker Bottle', 'BPA-free 700ml shaker bottle with stainless steel mixing ball. Leak-proof lid, measurement markings, and a wide mouth for easy cleaning. Available in Black and White.', 'Equipment', 299.00, 'assets/SHAKER_BOTTLE.png', 1, '2026-06-14 05:01:29'),
(9, 'Gym Towel', 'Microfiber gym towel with fast-drying technology. Ultra-absorbent, lightweight, and compact. FitSync logo embroidered on corner. 40x80cm — perfect gym bag size.', 'Equipment', 249.00, 'assets/GYM_TOWEL.png', 1, '2026-06-14 05:01:29'),
(10, 'Resistance Bands', 'Set of 5 resistance bands in varying tensions (5–50 lbs). Made from premium latex for durability. Ideal for warm-ups, mobility work, and accessory exercises. Includes carry pouch.', 'Equipment', 599.00, 'assets/RESISTANCE_BAND.png', 1, '2026-06-14 05:01:29'),
(11, 'Lifting Straps', 'Heavy-duty cotton lifting straps with neoprene wrist padding. Improves grip during deadlifts, rows, and shrugs. Sold as a pair. Adjustable length fits all wrist sizes.', 'Equipment', 349.00, 'assets/LIFTING_STRAPS.png', 1, '2026-06-14 05:01:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_stocks`
--

CREATE TABLE `product_stocks` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `branch_id` smallint(5) UNSIGNED NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_stocks`
--

INSERT INTO `product_stocks` (`id`, `product_id`, `branch_id`, `stock`) VALUES
(1, 1, 1, 50),
(2, 2, 1, 49),
(3, 3, 1, 50),
(4, 4, 1, 50),
(5, 5, 1, 50),
(6, 6, 1, 50),
(7, 7, 1, 50),
(8, 8, 1, 50),
(9, 9, 1, 50),
(10, 10, 1, 50),
(11, 11, 1, 50),
(13, 11, 2, 20),
(14, 11, 3, 20),
(15, 11, 4, 20),
(16, 11, 5, 20),
(23, 10, 2, 20),
(24, 10, 3, 20),
(25, 10, 4, 20),
(26, 10, 5, 20),
(28, 9, 2, 20),
(29, 9, 3, 20),
(30, 9, 4, 20),
(31, 9, 5, 20),
(33, 8, 2, 20),
(34, 8, 3, 20),
(35, 8, 4, 20),
(36, 8, 5, 20),
(38, 7, 2, 20),
(39, 7, 3, 20),
(40, 7, 4, 20),
(41, 7, 5, 20),
(43, 6, 2, 20),
(44, 6, 3, 20),
(45, 6, 4, 20),
(46, 6, 5, 20),
(48, 5, 2, 20),
(49, 5, 3, 20),
(50, 5, 4, 20),
(51, 5, 5, 20),
(53, 4, 2, 20),
(54, 4, 3, 20),
(55, 4, 4, 20),
(56, 4, 5, 20),
(58, 3, 2, 20),
(59, 3, 3, 20),
(60, 3, 4, 20),
(61, 3, 5, 20),
(63, 2, 2, 20),
(64, 2, 3, 20),
(65, 2, 4, 20),
(66, 2, 5, 20),
(68, 1, 2, 15),
(69, 1, 3, 20),
(70, 1, 4, 20),
(71, 1, 5, 20);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `first_name` varchar(64) NOT NULL,
  `last_name` varchar(64) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('male','female','nonbinary','other') DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `verification_token` varchar(128) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_approved` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `email`, `password_hash`, `birthdate`, `gender`, `email_verified_at`, `verification_token`, `profile_photo`, `is_active`, `last_login_at`, `created_at`, `updated_at`, `is_approved`) VALUES
(1, 'admin', 'FitSync', 'Admin', 'admin@fitsync.com', '$2y$12$fjUipSsJhqxbfNt5gYTXvufikLX0YajhMIbyKAaCoUhQW67fHFUqq', NULL, NULL, '2026-05-19 10:04:41', NULL, NULL, 1, '2026-06-15 09:50:16', '2026-05-19 02:43:50', '2026-06-15 09:50:16', 1),
(2, 'member', 'Juan', 'Dela Cruz', 'member1@fitsync.com', '$2y$12$c0MT9Z9xvxJERnn2C2x4duzR.TFqOrl/RiMMpsfdmPwQe7i174Lky', '2009-01-15', 'male', NULL, '96e9b99159e029db6e6dd2e7089b63bfd9a9aedaea5a8895efed27b574e68530', NULL, 1, '2026-06-15 09:17:54', '2026-06-12 20:50:16', '2026-06-15 09:17:54', 1),
(3, 'member', 'Pedro', 'Penduco', 'member2@fitsync.com', '$2y$12$kMC8XcQRo4d8lMyjpZBxp.F1K1L68dGuZ/0I47POVwmOKOmyu4EUK', '2008-07-09', 'male', NULL, '67cc39967895d5c94da05dd2d4fa45244323bff2440a7b3b03cf362c73d0bd8a', NULL, 1, '2026-06-14 14:57:07', '2026-06-12 20:56:38', '2026-06-14 14:57:07', 1),
(4, 'member', 'Maria', 'Clara', 'member3@fitsync.com', '$2y$12$NCj5GOHNWCZF3bw.opW0KO93IbtauBSFaesCpb3pXklBUPX5DEvE.', '2005-08-28', 'female', NULL, 'ba5f86807014be1c5b2130117ee6260c997356886bc86f82c411c351301ac4a7', NULL, 1, '2026-06-15 09:20:54', '2026-06-12 21:03:27', '2026-06-15 09:20:54', 1),
(5, 'member', 'John', 'Doe', 'member4@fitsync.com', '$2y$12$d7HmYiH3fpDqtjA1wMZUa.ZX0dKsJxYlBDz.2MI114ct71.VP4liu', '2009-03-04', 'male', NULL, '2f67e921a8a3214573f7ca7aec883f049e04fc512951480144448d90936b44da', NULL, 1, '2026-06-15 09:59:38', '2026-06-15 09:32:47', '2026-06-15 09:59:38', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_user` (`user_id`),
  ADD KEY `idx_att_check_in` (`check_in_at`),
  ADD KEY `idx_att_branch` (`branch_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_active` (`is_active`);

--
-- Indexes for table `branch_announcements`
--
ALTER TABLE `branch_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_announcements_branch_dates` (`branch_id`,`starts_at`,`ends_at`),
  ADD KEY `idx_branch_announcements_active` (`is_active`);

--
-- Indexes for table `branch_operating_hours`
--
ALTER TABLE `branch_operating_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_branch_day` (`branch_id`,`day_of_week`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_classes_branch` (`branch_id`),
  ADD KEY `idx_classes_active` (`is_active`);

--
-- Indexes for table `class_bookings`
--
ALTER TABLE `class_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class_bookings_user` (`user_id`),
  ADD KEY `idx_class_bookings_schedule` (`class_schedule_id`),
  ADD KEY `idx_class_bookings_status` (`booking_status`),
  ADD KEY `idx_class_bookings_booked_at` (`booked_at`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class_schedules_class` (`class_id`),
  ADD KEY `idx_class_schedules_branch_date` (`branch_id`,`scheduled_date`),
  ADD KEY `idx_class_schedules_status` (`status`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_status` (`status`),
  ADD KEY `idx_contact_created` (`created_at`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fb_user` (`user_id`),
  ADD KEY `idx_fb_branch` (`branch_id`),
  ADD KEY `idx_fb_rating` (`rating`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ll_user` (`user_id`),
  ADD KEY `idx_ll_success` (`success`),
  ADD KEY `idx_ll_created` (`created_at`);

--
-- Indexes for table `memberships`
--
ALTER TABLE `memberships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mem_user` (`user_id`),
  ADD KEY `idx_mem_status` (`status`),
  ADD KEY `idx_mem_payment_status` (`payment_status`),
  ADD KEY `idx_mem_starts` (`starts_at`),
  ADD KEY `idx_mem_ends` (`ends_at`),
  ADD KEY `fk_mem_plan` (`plan_id`),
  ADD KEY `fk_mem_branch` (`branch_id`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plan_slug` (`slug`);

--
-- Indexes for table `member_notes`
--
ALTER TABLE `member_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_notes_member` (`member_id`),
  ADD KEY `idx_member_notes_admin` (`admin_id`),
  ADD KEY `idx_member_notes_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_fulfillment` (`fulfillment_method`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `order_reviews`
--
ALTER TABLE `order_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_review` (`order_id`,`user_id`),
  ADD KEY `idx_order_reviews_user` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `product_stocks`
--
ALTER TABLE `product_stocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_branch` (`product_id`,`branch_id`),
  ADD KEY `fk_prod_stock_branch` (`branch_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_email` (`email`),
  ADD KEY `idx_user_role` (`role`),
  ADD KEY `idx_user_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `branch_announcements`
--
ALTER TABLE `branch_announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `branch_operating_hours`
--
ALTER TABLE `branch_operating_hours`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `class_bookings`
--
ALTER TABLE `class_bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `member_notes`
--
ALTER TABLE `member_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_reviews`
--
ALTER TABLE `order_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `product_stocks`
--
ALTER TABLE `product_stocks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `fk_att_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_announcements`
--
ALTER TABLE `branch_announcements`
  ADD CONSTRAINT `fk_branch_announcements_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `branch_operating_hours`
--
ALTER TABLE `branch_operating_hours`
  ADD CONSTRAINT `fk_branch_operating_hours_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_classes_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `class_bookings`
--
ALTER TABLE `class_bookings`
  ADD CONSTRAINT `fk_class_bookings_schedule` FOREIGN KEY (`class_schedule_id`) REFERENCES `class_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_class_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `fk_class_schedules_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_class_schedules_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_fb_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fb_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `memberships`
--
ALTER TABLE `memberships`
  ADD CONSTRAINT `fk_mem_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_mem_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`),
  ADD CONSTRAINT `fk_mem_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `member_notes`
--
ALTER TABLE `member_notes`
  ADD CONSTRAINT `fk_member_notes_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_notes_member` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_reviews`
--
ALTER TABLE `order_reviews`
  ADD CONSTRAINT `fk_order_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_stocks`
--
ALTER TABLE `product_stocks`
  ADD CONSTRAINT `fk_prod_stock_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prod_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
