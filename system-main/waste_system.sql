-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 01, 2026 at 07:11 AM
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
-- Database: `waste_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `collector_account`
--

CREATE TABLE `collector_account` (
  `ID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` int(11) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collector_account`
--

INSERT INTO `collector_account` (`ID`, `name`, `username`, `password`) VALUES
(1, 'lanceallen', 1111, '123');

-- --------------------------------------------------------

--
-- Table structure for table `eco_points`
--

CREATE TABLE `eco_points` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `house_address` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resident_payments`
--

CREATE TABLE `resident_payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Paid','Unpaid') NOT NULL DEFAULT 'Unpaid',
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_fee` decimal(10,2) NOT NULL DEFAULT 150.00,
  `subscription_status` varchar(50) NOT NULL DEFAULT 'Pending Approval',
  `payment_status` varchar(50) NOT NULL DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `service_fee`, `subscription_status`, `payment_status`, `created_at`) VALUES
(1, 4, 150.00, 'Pending Approval', 'Unpaid', '2026-10-31 00:57:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `users` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `users`, `password`, `role`) VALUES
(1, 'Micah galang', 'micahg', '$2y$10$/bzjTXhaS3gfBZqIfhZMyOgXhPq6v/s03Jc9VKuWAzztUVmEGm7Re', 'admin'),
(2, 'james', 'jamest', '$2y$10$0ScarMneqO1XlbiaUGglmunlkEZeJXLI7KuzYBO7l1f1x3Wi9Fhny', 'residents'),
(3, 'Eryza', 'ery', '$2y$10$X.fXmiVtW/S9sp1Pwgn8cOa6va7EnRCms59MdlVCdxyKvRBkRUxG6', 'residents'),
(4, 'lance', 'allen', '$2y$10$jiEGRpWsdtAwCV7bPBJ1G.ldlSqobIx64rmQBOT9h.EIc0ilwYoCu', 'residents'),
(5, 'lancee', 'allenz', '$2y$10$kdk2tdutAuiy5qQpkPZFZuwCetnBNgebSSmOBVn.eR02bNOHUvakG', 'collector'),
(6, 'Vincent Natividad', 'vince', '$2y$10$pUoJndwNX5n4FLjN1EOa0.uLFYJnCpTPS1QcH/yb9fT/Aq0Dy5Lg.', 'residents'),
(7, 'Vincent Natividad', 'vincent', '$2y$10$2GAmEsQJ2e/A6NjZmXX1G.LekFkvf7tZpXtsy.qQJECOzimYce9u2', 'collector'),
(12, 'Vincent Natividad', 'admin', '$2y$10$bUpkMh3ghhoYT1lxeBfwj.k4dXudnFo2euXvYHk7w5J7UCaf9q5z2', 'admin'),
(13, 'Vincent Natividad', 'resident123', '$2y$10$rm78gk81F9.x0ZuKIPatPev8mQltr/TwNxW7dN9jxesr2IHC1YgNS', 'residents');

-- --------------------------------------------------------

--
-- Table structure for table `waste_collections`
--

CREATE TABLE `waste_collections` (
  `id` int(11) NOT NULL,
  `household_id` int(11) NOT NULL,
  `collector_id` int(11) DEFAULT NULL,
  `collection_date` date NOT NULL,
  `segregation_status` enum('Properly Segregated','Not Properly Segregated') DEFAULT NULL,
  `collection_status` enum('Pending','Collected') DEFAULT 'Pending',
  `eco_points_awarded` int(11) DEFAULT 0,
  `collected_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `collector_account`
--
ALTER TABLE `collector_account`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `eco_points`
--
ALTER TABLE `eco_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `collection_id` (`collection_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `resident_payments`
--
ALTER TABLE `resident_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `waste_collections`
--
ALTER TABLE `waste_collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_household_collection` (`household_id`,`collection_date`),
  ADD KEY `collector_id` (`collector_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `collector_account`
--
ALTER TABLE `collector_account`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `eco_points`
--
ALTER TABLE `eco_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident_payments`
--
ALTER TABLE `resident_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `waste_collections`
--
ALTER TABLE `waste_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `eco_points`
--
ALTER TABLE `eco_points`
  ADD CONSTRAINT `eco_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eco_points_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `waste_collections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `households_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resident_payments`
--
ALTER TABLE `resident_payments`
  ADD CONSTRAINT `resident_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `waste_collections`
--
ALTER TABLE `waste_collections`
  ADD CONSTRAINT `waste_collections_ibfk_1` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `waste_collections_ibfk_2` FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
