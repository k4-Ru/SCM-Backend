-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 20, 2026 at 12:42 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `SCM`
--

-- --------------------------------------------------------

--
-- Table structure for table `procurements`
--

CREATE TABLE `procurements` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status` enum('pending','approved','shipped','delivered','cancelled') DEFAULT 'pending',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procurements`
--

INSERT INTO `procurements` (`id`, `supplier_id`, `created_by`, `order_date`, `expected_delivery`, `status`, `total_amount`, `created_at`) VALUES
(1, 1, 13, '2026-04-18', '2026-04-25', 'pending', 15000.00, '2026-04-20 10:07:27'),
(2, 2, 13, '2026-04-17', '2026-04-22', 'approved', 8700.00, '2026-04-20 10:07:27'),
(3, 3, 13, '2026-04-15', '2026-04-20', 'delivered', 12350.00, '2026-04-20 10:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_items`
--

CREATE TABLE `procurement_items` (
  `id` int(11) NOT NULL,
  `procurement_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procurement_items`
--

INSERT INTO `procurement_items` (`id`, `procurement_id`, `product_name`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 'Steel Bars (10mm)', 50, 200.00, 10000.00),
(2, 1, 'Cement Bags', 100, 50.00, 5000.00),
(3, 2, 'PVC Pipes (3m)', 30, 120.00, 3600.00),
(4, 2, 'Pipe Connectors', 100, 25.00, 2500.00),
(5, 2, 'Sealant', 20, 130.00, 2600.00),
(6, 3, 'Warehouse Shelves', 5, 1500.00, 7500.00),
(7, 3, 'Storage Boxes', 50, 97.00, 4850.00);

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL,
  `procurement_id` int(11) NOT NULL,
  `tracking_number` varchar(100) NOT NULL,
  `carrier` varchar(150) NOT NULL,
  `origin` varchar(150) NOT NULL,
  `destination` varchar(150) NOT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','shipped','in_transit','delivered','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `procurement_id`, `tracking_number`, `carrier`, `origin`, `destination`, `shipped_at`, `expected_delivery`, `delivered_at`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'TRK-1001', 'LBC Express', 'Tarlac City', 'San Roque, Tarlac', NULL, '2026-04-25', NULL, 'pending', 'Waiting for supplier dispatch', '2026-04-20 10:09:34', '2026-04-20 10:09:34'),
(2, 2, 'TRK-1002', 'J&T Express', 'Angeles City, Pampanga', 'San Roque, Tarlac', '2026-04-18 10:30:00', '2026-04-22', NULL, 'in_transit', 'Left sorting facility', '2026-04-20 10:09:34', '2026-04-20 10:09:34'),
(3, 3, 'TRK-1003', 'CDR Supply Chain', 'Clark Freeport Zone', 'San Roque, Tarlac', '2026-04-16 08:00:00', '2026-04-20', '2026-04-20 14:00:00', 'delivered', 'Received in good condition', '2026-04-20 10:09:34', '2026-04-20 10:09:47');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `status`, `created_at`) VALUES
(1, 'ABC Industrial Supplies', 'John Dela Cruz', 'abc.supplies@example.com', '09171234567', 'Tarlac City, Philippines', 'active', '2026-04-20 09:51:09'),
(2, 'Prime Source Trading', 'Maria Grace Piattos', 'prime.source@example.com', '09181234567', 'Angeles City, Pampanga', 'active', '2026-04-20 09:51:09'),
(3, 'Global Warehouse Solutions', 'Carlos Del osReyes', 'global.warehouse@example.com', '09191234567', 'Clark Freeport Zone, Pampanga', 'inactive', '2026-04-20 09:51:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','procurement','warehouse','viewer','supplier') DEFAULT 'viewer',
  `supplier_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(8, 'Admin User', 'admin@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:10'),
(9, 'dench_admin', 'dench@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:08'),
(10, 'carl_admin', 'carl@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, '2026-04-20 10:31:38', '2026-04-20 06:24:15', '2026-04-20 10:31:38'),
(11, 'robert_admin', 'robert@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, '2026-04-20 10:31:53', '2026-04-20 06:24:15', '2026-04-20 10:31:53'),
(12, 'carl_viewer', 'carl+1@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'viewer', 1, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:13'),
(13, 'Procurement User', 'procurement@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'procurement', 1, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:16'),
(14, 'Warehouse User', 'warehouse@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, '2026-04-20 06:24:15', '2026-04-20 10:21:57'),
(15, 'qwerty', 'qwerty@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'viewer', 1, '2026-04-20 10:11:41', '2026-04-20 09:44:19', '2026-04-20 10:12:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `procurements`
--
ALTER TABLE `procurements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_procurement_supplier` (`supplier_id`),
  ADD KEY `fk_procurement_user` (`created_by`);

--
-- Indexes for table `procurement_items`
--
ALTER TABLE `procurement_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_procurement` (`procurement_id`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shipments_tracking_number` (`tracking_number`),
  ADD UNIQUE KEY `uq_shipments_procurement_id` (`procurement_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_users_supplier` (`supplier_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `procurements`
--
ALTER TABLE `procurements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `procurement_items`
--
ALTER TABLE `procurement_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `procurements`
--
ALTER TABLE `procurements`
  ADD CONSTRAINT `fk_procurement_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_procurement_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `procurement_items`
--
ALTER TABLE `procurement_items`
  ADD CONSTRAINT `fk_items_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `fk_shipments_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
