-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2026 at 03:44 PM
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
(4, 3, 4, '2026-06-06 20:41:32', NULL);

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
  `branch_id` smallint(5) UNSIGNED NOT NULL,
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
(5, 'Zander Atenciana', 'member5@fitsync.com', NULL, 'New registration awaiting approval: Zander Atenciana', 'A new member has registered and requires approval.\n\nName: Zander Atenciana\nEmail: member5@fitsync.com\nPlan: 12 Months\nBranch ID: 3\n\nReview and approve from the admin panel.', 'new', '2026-06-01 22:40:23');

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
(55, 2, 'member@fitsync.com', '10.96.211.28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 1, '2026-06-08 21:34:36');

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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `user_id`, `plan_id`, `branch_id`, `starts_at`, `ends_at`, `amount_paid`, `payment_method`, `payment_status`, `status`, `payment_ref`, `created_at`, `updated_at`) VALUES
(5, 2, 1, 3, '2026-05-26', '2026-06-25', 999.00, 'cash', 'paid', 'active', NULL, '2026-05-26 09:00:13', '2026-05-26 09:00:13'),
(6, 3, 2, 4, '2026-05-26', '2026-08-24', 2699.00, 'credit_card', 'paid', 'active', NULL, '2026-05-26 09:01:46', '2026-05-26 10:19:31'),
(7, 4, 3, 4, '2026-05-26', '2026-11-22', 4799.00, 'cash', 'paid', 'active', NULL, '2026-05-26 09:02:41', '2026-05-26 09:02:41'),
(8, 5, 4, 1, '2026-05-26', '2027-05-26', 7999.00, 'bank_transfer', 'paid', 'active', NULL, '2026-05-26 09:03:49', '2026-05-26 09:51:08'),
(9, 5, 4, 1, '2027-05-27', '2028-05-26', 7999.00, 'bank_transfer', 'paid', 'active', 'RNW-220FE8E2', '2026-05-26 09:10:22', '2026-05-26 09:51:06'),
(10, 5, 4, 1, '2027-05-27', '2028-05-26', 7999.00, 'bank_transfer', 'paid', 'active', 'RNW-85CBDD2D', '2026-05-26 09:32:53', '2026-05-26 09:51:04'),
(11, 6, 1, 3, '2026-06-01', '2026-07-01', 999.00, 'gcash', 'paid', 'active', 'APR-1-20260601144104', '2026-06-01 20:40:14', '2026-06-02 00:00:19');

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
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `remember_token` varchar(128) DEFAULT NULL,
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

INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `email`, `password_hash`, `birthdate`, `gender`, `email_verified_at`, `verification_token`, `remember_token`, `profile_photo`, `is_active`, `last_login_at`, `created_at`, `updated_at`, `is_approved`) VALUES
(1, 'admin', 'FitSync', 'Admin', 'admin@fitsync.com', '$2y$12$fjUipSsJhqxbfNt5gYTXvufikLX0YajhMIbyKAaCoUhQW67fHFUqq', NULL, NULL, '2026-05-19 10:04:41', NULL, NULL, NULL, 1, '2026-06-08 20:05:27', '2026-05-19 02:43:50', '2026-06-08 20:05:27', 1),
(2, 'member', 'Juan', 'Dela Cruz', 'member@fitsync.com', '$2y$12$nmaTKg0EuRoOhdlOzNRFvOsmbwBPdhGTDgS6onsWs1Lb2SUZCk7tC', '2010-05-01', 'male', NULL, '01c4a90c7e9243b174ce673b7fd9e37d75a381e1feb35c5f40ca84129dfb832e', NULL, NULL, 1, '2026-06-08 21:34:36', '2026-05-26 09:00:13', '2026-06-08 21:34:36', 1),
(3, 'member', 'John', 'Doe', 'member1@fitsync.com', '$2y$12$7UYLNybn/mm.9zaETT4qsOrVHXHrypIbdah3gxURwrEKi7z8FMZr6', '2010-05-02', 'male', NULL, 'b54816ab62f86a6e3cf0be5a478318885b8deb25e1793c13c6996aa264c8925a', NULL, NULL, 1, '2026-06-08 21:25:08', '2026-05-26 09:01:46', '2026-06-08 21:25:08', 1),
(4, 'member', 'Pedro', 'Penduco', 'member2@fitsync.com', '$2y$12$1OSqEWWCh70wGT9pciRDBupYagf.zoQpOnXSxgvbIU4kAMMcwY50K', '2010-05-03', 'male', NULL, 'fa39b920d88ce562f9b96b650c5c4138cb50f9beba35f8b66e3357c1846f6625', NULL, NULL, 1, '2026-06-08 20:08:45', '2026-05-26 09:02:41', '2026-06-08 20:08:45', 1),
(5, 'member', 'Maria', 'Clara', 'member3@fitsync.com', '$2y$12$mhmrga1/M8/czTwxXh20sOVL1lmIjCdoKhQ3mrvjSop88.8/BQ3r6', '2010-05-04', 'female', NULL, 'e25390c9720ddf3a5e7a3358c34e049f2d6f2beac9216d457a9a791b3bd39c83', NULL, NULL, 1, '2026-05-26 09:32:00', '2026-05-26 09:03:49', '2026-05-26 09:47:37', 1),
(6, 'member', 'John', 'Doe', 'member4@fitsync.com', '$2y$12$jV4ZvhN1npLhCa43drioxefr9d9vELpE/VRcmxK.9eMsdrfhAvwqi', '1999-01-06', 'male', NULL, '9f7ff6a9217353756a706b8cc8b28c5a7d2335dd41087060a0bb8430dc42d0d3', NULL, NULL, 1, '2026-06-01 22:57:48', '2026-06-01 20:40:14', '2026-06-01 22:57:48', 1);

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
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pr_token` (`token`),
  ADD KEY `idx_pr_user` (`user_id`),
  ADD KEY `idx_pr_expires` (`expires_at`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  ADD CONSTRAINT `fk_branch_announcements_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
