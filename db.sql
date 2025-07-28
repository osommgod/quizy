-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 28, 2025 at 10:10 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `moodzy_quizy_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_config`
--

CREATE TABLE `app_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_config`
--

INSERT INTO `app_config` (`config_key`, `config_value`) VALUES
('app_update', '1.025.45v'),
('app_version', '1.025.45v'),
('contest_interval', '30000'),
('contest_reward', '10.00'),
('credit_value', '1000'),
('maintenance_mode', 'off'),
('min_withdraw_amount', '50'),
('quiz_limit', '10'),
('quiz_reward', '5.00'),
('rtsgain_contest', '50'),
('rtsgain_quiz', '100'),
('signup_bonuspoints', '5.00'),
('signup_point', '10.00'),
('withdrawal_enabled', 'true');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(100) NOT NULL,
  `reason` text DEFAULT NULL,
  `blocked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_attempts`
--

CREATE TABLE `failed_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(100) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `failed_attempts`
--

INSERT INTO `failed_attempts` (`id`, `ip_address`, `endpoint`, `reason`, `attempted_at`) VALUES
(1, '::1', 'user_store', 'Invalid API Key', '2025-06-17 22:31:07'),
(2, '::1', 'user_store', 'Invalid API Key', '2025-06-17 22:31:08'),
(3, '::1', 'user_store', 'Invalid API Key', '2025-06-17 22:31:22'),
(4, '::1', '', 'Blocked IP tried to register', '2025-06-17 23:20:10'),
(5, '::1', '', 'Blocked IP tried to register', '2025-06-17 23:20:13'),
(6, '::1', '', 'Blocked IP tried to register', '2025-06-17 23:20:14'),
(7, '::1', '', 'Blocked IP tried to register', '2025-06-20 16:40:16');

-- --------------------------------------------------------

--
-- Table structure for table `links_config`
--

CREATE TABLE `links_config` (
  `link_key` varchar(100) NOT NULL,
  `link_value` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `links_config`
--

INSERT INTO `links_config` (`link_key`, `link_value`, `updated_at`) VALUES
('app_link', 'https://play.google.com/store/apps/details?id=moodzy.quizy', '2025-06-17 22:05:14'),
('how_work', 'https://moodzy.com/how', '2025-06-17 22:05:14'),
('instagram', 'https://instagram.com/moodzyquizy', '2025-06-17 22:05:14'),
('mail', 'support@moodzy.com', '2025-06-17 22:05:14'),
('privacy_policy', 'https://moodzy.com/privacy', '2025-06-17 22:05:14'),
('telegram', 'https://t.me/moodzyquizy', '2025-06-17 22:05:14'),
('t_and_c', 'https://moodzy.com/terms', '2025-06-17 22:05:14'),
('x', 'https://x.com/moodzyquizy', '2025-06-17 22:05:14');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `sl` bigint(25) NOT NULL,
  `user_deviceid` varchar(250) NOT NULL,
  `notifications` text DEFAULT NULL COMMENT 'Stores JSON array like ["item1","item2"]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`sl`, `user_deviceid`, `notifications`) VALUES
(1, 'device_123', '[]'),
(2, 'device_456', '[]'),
(3, '4104b6e24746a348', '[]'),
(105, 'device_4565', '[\"Welcome to Moodzy Quiz!\\nYour account has been created successfully.\"]'),
(106, '7d1ca32278b9e49e', '[]'),
(107, '7059f53a3e320e0b', '[\"Welcome to Moodzy Quiz!\\nYour account has been created successfully.\"]'),
(108, '2a13db4d293d56ef', '[\"Welcome to Moodzy Quiz!\\nYour account has been created successfully.\"]'),
(109, '019110c5677aee07', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `sl` int(11) NOT NULL,
  `app_version` int(11) NOT NULL,
  `app_update` int(11) NOT NULL,
  `quiz_limit` int(11) NOT NULL,
  `minimum_withdraw` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`sl`, `app_version`, `app_update`, `quiz_limit`, `minimum_withdraw`) VALUES
(1, 1, 0, 10, 50);

-- --------------------------------------------------------

--
-- Table structure for table `user_data`
--

CREATE TABLE `user_data` (
  `user_count` bigint(25) NOT NULL,
  `user_deviceid` varchar(250) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_points` decimal(10,2) GENERATED ALWAYS AS (round((`user_credits` + `quiz_credit` + `contest_credit`) / 1000,2)) STORED,
  `user_credits` int(11) DEFAULT 5000,
  `quiz_credit` int(11) DEFAULT 0,
  `contest_credit` int(11) DEFAULT 0,
  `user_rts` int(25) NOT NULL DEFAULT 10,
  `user_followers` int(25) NOT NULL DEFAULT 0,
  `user_following` int(25) NOT NULL DEFAULT 0,
  `user_bonuspoints` decimal(10,2) DEFAULT 5.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_ip` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_data`
--

INSERT INTO `user_data` (`user_count`, `user_deviceid`, `user_name`, `user_credits`, `quiz_credit`, `contest_credit`, `user_rts`, `user_followers`, `user_following`, `user_bonuspoints`, `created_at`, `user_ip`) VALUES
(1, 'device_123', NULL, 1000, 1000, 1000, 1, 12, 5, 20.00, '2025-06-17 22:19:12', NULL),
(2, 'device_456', NULL, 1000, 10000, 0, 0, 3, 8, 5.50, '2025-06-17 22:19:12', NULL),
(3, 'tsetid1242452', NULL, 800, 0, 0, 0, 0, 0, 5.00, '2025-06-17 22:33:40', NULL),
(4, 'tsetid1242452t', NULL, 400, 0, 0, 0, 0, 0, 5.00, '2025-06-17 22:35:35', '::1'),
(5, 'tsetid1242452t5', NULL, 5000, 0, 0, 0, 0, 0, 5.00, '2025-06-17 23:15:56', '::1'),
(6, '4104b6e24746a348', 'User568435', 10000, 2500, 11000, 0, 0, 0, 500.00, '2025-06-17 23:24:36', '192.168.194.237'),
(108, 'device_4565', 'User817302', 5000, 0, 0, 1000, 0, 0, 1500.00, '2025-06-20 16:58:54', '::1'),
(109, '7d1ca32278b9e49e', 'User978072', 5000, 0, 0, 1000, 0, 0, 5.00, '2025-07-14 16:58:42', '172.30.247.116'),
(110, '7059f53a3e320e0b', 'User291642', 5000, 0, 0, 1000, 0, 0, 5.00, '2025-07-14 18:00:20', '172.30.247.116'),
(111, '2a13db4d293d56ef', 'User941908', 5000, 0, 0, 1000, 0, 0, 5.00, '2025-07-14 19:54:43', '172.30.247.116'),
(112, '019110c5677aee07', 'User361553', 5000, 0, 1500, 1500, 0, 0, 5.00, '2025-07-15 18:31:01', '172.30.247.183');

-- --------------------------------------------------------

--
-- Table structure for table `withdraw`
--

CREATE TABLE `withdraw` (
  `sl` bigint(25) NOT NULL,
  `user_deviceid` varchar(250) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `redeem_id` varchar(100) DEFAULT NULL,
  `redeem_method` varchar(100) DEFAULT NULL,
  `sim_circle` varchar(100) DEFAULT NULL,
  `date_time` datetime NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `closing_balance` decimal(10,2) NOT NULL,
  `status` enum('Pending','Rejected','Success') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `withdraw`
--

INSERT INTO `withdraw` (`sl`, `user_deviceid`, `transaction_id`, `redeem_id`, `redeem_method`, `sim_circle`, `date_time`, `amount`, `closing_balance`, `status`) VALUES
(21, '4104b6e24746a348', '2450034505', ' 91-7436825713  (Prepaid)', 'Recharge', 'Jio (Chandigarh)', '2025-07-15 17:16:18', 100.00, 23.00, 'Pending'),
(22, '4104b6e24746a348', '2116062848', 'ommkalyan3@gmail.com (Google Redeem Code)', 'Gift Card', 'NA', '2025-07-15 17:16:43', 100.00, 23.00, 'Pending'),
(23, '4104b6e24746a348', '1400332389', 'ommkalyan3@gmail.com (Google Redeem Code)', 'Gift Card', 'NA', '2025-07-15 17:17:12', 100.00, 23.00, 'Pending'),
(24, '4104b6e24746a348', '7755786402', ' 91-7436825713  (Prepaid)', 'Recharge', 'Jio (Chandigarh)', '2025-07-15 17:17:17', 100.00, 23.00, 'Pending');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_config`
--
ALTER TABLE `app_config`
  ADD PRIMARY KEY (`config_key`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indexes for table `failed_attempts`
--
ALTER TABLE `failed_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `links_config`
--
ALTER TABLE `links_config`
  ADD PRIMARY KEY (`link_key`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`sl`),
  ADD KEY `user_deviceid` (`user_deviceid`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`sl`);

--
-- Indexes for table `user_data`
--
ALTER TABLE `user_data`
  ADD PRIMARY KEY (`user_count`),
  ADD UNIQUE KEY `ID` (`user_deviceid`),
  ADD UNIQUE KEY `user_name` (`user_name`);

--
-- Indexes for table `withdraw`
--
ALTER TABLE `withdraw`
  ADD PRIMARY KEY (`sl`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `failed_attempts`
--
ALTER TABLE `failed_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `sl` bigint(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `user_data`
--
ALTER TABLE `user_data`
  MODIFY `user_count` bigint(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `withdraw`
--
ALTER TABLE `withdraw`
  MODIFY `sl` bigint(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `user_deviceid` FOREIGN KEY (`user_deviceid`) REFERENCES `user_data` (`user_deviceid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
