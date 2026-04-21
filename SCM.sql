-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 21, 2026 at 01:07 PM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `target_table`, `target_id`, `description`, `created_at`) VALUES
(1, 13, 'created_procurement', 'procurements', 1, 'Created procurement order #1 for ADB Industrial Supplies', '2026-04-21 10:59:21'),
(2, 13, 'created_procurement', 'procurements', 2, 'Created procurement order #2 for Primalirina Source Co Ltd.', '2026-04-21 10:59:21'),
(3, 13, 'created_procurement', 'procurements', 3, 'Created procurement order #3 for Global Ware Solutions INC.', '2026-04-21 10:59:21'),
(4, 19, 'updated_shipment', 'shipments', 3, 'Marked shipment TRK-1003 as delivered', '2026-04-21 10:59:21'),
(5, 19, 'updated_procurement', 'procurements', 3, 'Marked procurement #3 as delivered, received_by set', '2026-04-21 10:59:21');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `stock_quantity`, `location`, `last_updated`) VALUES
(1, 6, 5, 'Warehouse A - Section 1', '2026-04-21 10:59:21'),
(2, 7, 50, 'Warehouse A - Section 2', '2026-04-21 10:59:21');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 20, 'New Purchase Order', 'You have a new purchase order #1 from ADB Industrial Supplies. Please review and confirm.', 0, '2026-04-21 10:59:21'),
(2, 21, 'Order Approved', 'Your order #2 has been approved. Please prepare shipment.', 0, '2026-04-21 10:59:21'),
(3, 14, 'Shipment Delivered', 'Shipment TRK-1003 has been marked as delivered. Please update inventory.', 1, '2026-04-21 10:59:21');

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
  `received_by` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procurements`
--

INSERT INTO `procurements` (`id`, `supplier_id`, `created_by`, `order_date`, `expected_delivery`, `status`, `received_by`, `total_amount`, `created_at`) VALUES
(1, 1, 13, '2026-04-18', '2026-04-25', 'pending', NULL, 15000.00, '2026-04-20 10:07:27'),
(2, 2, 13, '2026-04-17', '2026-04-22', 'approved', NULL, 8700.00, '2026-04-20 10:07:27'),
(3, 3, 13, '2026-04-15', '2026-04-20', 'delivered', 19, 12350.00, '2026-04-20 10:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_items`
--

CREATE TABLE `procurement_items` (
  `id` int(11) NOT NULL,
  `procurement_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procurement_items`
--

INSERT INTO `procurement_items` (`id`, `procurement_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 'Steel Bars (10mm)', 50, 200.00, 10000.00),
(2, 1, 2, 'Cement Bags', 100, 50.00, 5000.00),
(3, 2, 3, 'PVC Pipes (3m)', 30, 120.00, 3600.00),
(4, 2, 4, 'Pipe Connectors', 100, 25.00, 2500.00),
(5, 2, 5, 'Sealant', 20, 130.00, 2600.00),
(6, 3, 6, 'Warehouse Shelves', 5, 1500.00, 7500.00),
(7, 3, 7, 'Storage Boxes', 50, 97.00, 4850.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `unit`, `stock_quantity`, `reorder_level`, `created_at`) VALUES
(1, 'Steel Bars (10mm)', 'pcs', 50, 10, '2026-04-21 10:59:21'),
(2, 'Cement Bags', 'bags', 100, 20, '2026-04-21 10:59:21'),
(3, 'PVC Pipes (3m)', 'pcs', 30, 5, '2026-04-21 10:59:21'),
(4, 'Pipe Connectors', 'pcs', 100, 15, '2026-04-21 10:59:21'),
(5, 'Sealant', 'pcs', 20, 5, '2026-04-21 10:59:21'),
(6, 'Warehouse Shelves', 'pcs', 5, 2, '2026-04-21 10:59:21'),
(7, 'Storage Boxes', 'pcs', 50, 10, '2026-04-21 10:59:21');

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
  `status` enum('pending','active','inactive') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `status`, `created_at`) VALUES
(1, 'ADB Industrial Supplies', 'John Dela Cruz', 'ADBsupplies@gmail.com.ph', '09123456799', 'Santa Rita, Philippines', 'active', '2026-04-20 09:51:09'),
(2, 'Primalirina Source Co Ltd.', 'Maria Grace Piattos', 'prime@gmail.com', '09181234324', 'Gordon Heights, Pampanga', 'active', '2026-04-20 09:51:09'),
(3, 'Global Ware Solutions INC.', 'Carlos Delos Reyes', 'global.warehouse@gmail.com', '09111234567', 'Subis, Bataan', 'inactive', '2026-04-20 09:51:09');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_applications`
--

CREATE TABLE `supplier_applications` (
  `id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_applications`
--

INSERT INTO `supplier_applications` (`id`, `company_name`, `contact_person`, `email`, `phone`, `address`, `status`, `reviewed_by`, `reviewed_at`, `notes`, `created_at`) VALUES
(1, 'Tarlac Steel Corp.', 'Ben Santos', 'ben@steel.com', '09201234567', 'Tarlac City, Philippines', 'pending', NULL, NULL, NULL, '2026-04-21 10:59:21'),
(2, 'NorthBuild Trading', 'Ana Reyes', 'ana@panorth.com', '09301234567', 'Dagupan City, Pangasinan', 'pending', NULL, NULL, NULL, '2026-04-21 10:59:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','procurement','warehouse','supplier','viewer') DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT 1,
  `supplier_id` int(11) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `supplier_id`, `last_login`, `created_at`, `updated_at`) VALUES
(8, 'Admin User', 'admin@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:10'),
(9, 'dench_admin', 'dench@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:08'),
(10, 'carl_admin', 'carl@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-04-21 11:00:47', '2026-04-20 06:24:15', '2026-04-21 11:00:47'),
(11, 'robert_admin', 'robert@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-04-20 10:31:53', '2026-04-20 06:24:15', '2026-04-20 10:31:53'),
(12, 'carl_viewer', 'carl+1@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'viewer', 1, NULL, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:13'),
(13, 'Procurement User', 'procurement@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'procurement', 1, NULL, NULL, '2026-04-20 06:24:15', '2026-04-20 10:22:16'),
(14, 'Warehouse User', 'warehouse@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, NULL, '2026-04-20 06:24:15', '2026-04-20 10:21:57'),
(15, 'qwerty', 'qwerty@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'viewer', 1, NULL, '2026-04-21 03:39:37', '2026-04-20 09:44:19', '2026-04-21 03:39:37'),
(16, 'Sys_Owner', 'owner@email.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'superadmin', 1, NULL, '2026-04-21 04:05:10', '2026-04-21 03:31:51', '2026-04-21 04:05:10'),
(17, 'carl_proc', 'carl@scm.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'procurement', 1, NULL, '2026-04-21 03:31:51', '2026-04-21 03:31:51', '2026-04-21 03:31:51'),
(18, 'robert_ware', 'robert@scm.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, '2026-04-21 03:31:51', '2026-04-21 03:31:51', '2026-04-21 03:31:51'),
(19, 'dench_ware', 'dench@scm.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, '2026-04-21 04:29:53', '2026-04-21 03:31:51', '2026-04-21 04:29:53'),
(20, 'John Dela Cruz', 'abc.supplies@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'supplier', 1, 1, NULL, '2026-04-21 03:37:45', '2026-04-21 11:00:36'),
(21, 'Maria Grace Piattos', 'prime.source@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'supplier', 1, 2, '2026-04-21 04:27:54', '2026-04-21 03:37:45', '2026-04-21 11:00:26'),
(22, 'Carlos Delos Reyes', 'global.warehouse@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'supplier', 1, 3, '2026-04-21 03:37:45', '2026-04-21 03:37:45', '2026-04-21 11:00:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_user` (`user_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_product` (`product_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`user_id`);

--
-- Indexes for table `procurements`
--
ALTER TABLE `procurements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_procurement_supplier` (`supplier_id`),
  ADD KEY `fk_procurement_user` (`created_by`),
  ADD KEY `fk_received_by_user` (`received_by`);

--
-- Indexes for table `procurement_items`
--
ALTER TABLE `procurement_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_procurement` (`procurement_id`),
  ADD KEY `fk_procurement_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shipments_tracking_number` (`tracking_number`),
  ADD KEY `fk_shipments_procurement` (`procurement_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_applications`
--
ALTER TABLE `supplier_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_application_reviewer` (`reviewed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_supplier_user` (`supplier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `supplier_applications`
--
ALTER TABLE `supplier_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `procurements`
--
ALTER TABLE `procurements`
  ADD CONSTRAINT `fk_procurement_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_procurement_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_received_by_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `procurement_items`
--
ALTER TABLE `procurement_items`
  ADD CONSTRAINT `fk_items_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_procurement_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `fk_shipments_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_applications`
--
ALTER TABLE `supplier_applications`
  ADD CONSTRAINT `fk_application_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_supplier_user` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
