-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2026 at 07:00 AM
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
(12, 1, 'admin@fitsync.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1, '2026-05-19 12:49:12');

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
  `status` enum('active','expired','cancelled','frozen') NOT NULL DEFAULT 'active',
  `payment_ref` varchar(128) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `user_id`, `plan_id`, `branch_id`, `starts_at`, `ends_at`, `amount_paid`, `payment_method`, `status`, `payment_ref`, `created_at`, `updated_at`) VALUES
(1, 2, 3, 1, '2025-05-09', '2025-11-09', 4799.00, 'gcash', 'active', NULL, '2026-05-19 02:43:50', '2026-05-19 02:43:50'),
(2, 3, 4, 1, '2026-05-19', '2027-05-19', 7999.00, 'gcash', 'active', NULL, '2026-05-19 11:15:10', '2026-05-19 11:15:10');

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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `email`, `password_hash`, `birthdate`, `gender`, `email_verified_at`, `verification_token`, `remember_token`, `profile_photo`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'FitSync', 'Admin', 'admin@fitsync.com', '$2y$12$fjUipSsJhqxbfNt5gYTXvufikLX0YajhMIbyKAaCoUhQW67fHFUqq', NULL, NULL, '2026-05-19 10:04:41', NULL, NULL, NULL, 1, '2026-05-19 12:49:13', '2026-05-19 02:43:50', '2026-05-19 12:49:13'),
(2, 'member', 'Juan', 'Dela Cruz', 'member@fitsync.com', '$2y$12$21xktCEyYMwqsCfwd0RJ3eKmkVxCwt0Es6xlypvgTSK0anfloTS5O', '1998-06-15', NULL, '2026-05-19 10:04:41', NULL, NULL, NULL, 1, '2026-05-19 11:03:05', '2026-05-19 02:43:50', '2026-05-19 11:03:05'),
(3, 'member', 'Michael', 'Bathan', 'mbathan619@gmail.com', '$2y$12$gst8.sp3TdzOFIOSpUeDMOlxdTgp.WiXHdpNt8zRPrF.cFbfIM/ta', '2005-01-01', NULL, NULL, '956ed3911ca2b2d54901a3176e1570fca2b4ffe64402887e2fcef0cda62bc395', NULL, NULL, 1, '2026-05-19 12:05:54', '2026-05-19 11:15:10', '2026-05-19 12:05:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_active` (`is_active`);

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
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
