-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 12, 2026 at 07:55 AM
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
(2, 7, 0, 'Warehosue A - Section 1', '2026-05-12 04:08:05');

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
(3, 3, 13, '2026-04-15', '2026-04-20', 'delivered', 19, 12350.00, '2026-04-20 10:07:27'),
(4, 2, 13, '2026-05-13', '2026-05-26', 'pending', NULL, 5280.00, '2026-05-12 05:13:03'),
(5, 5, 13, '2026-05-12', '2026-05-14', 'pending', NULL, 840.00, '2026-05-12 05:24:54');

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
(1, 4, 4, 'Pipe Connectors', 11, 480.00, 5280.00),
(2, 5, 1, 'Steel Bars (10mm)', 4, 210.00, 840.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(32) NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `unit`, `stock_quantity`, `reorder_level`, `created_at`) VALUES
(1, 'SKU-00001', 'Steel Bars (10mm)', 'pcs', 50, 10, '2026-04-21 10:59:21'),
(2, 'SKU-00002', 'Cement Bags', 'bags', 100, 20, '2026-04-21 10:59:21'),
(3, 'SKU-00003', 'PVC Pipes (3m)', 'pcs', 30, 5, '2026-04-21 10:59:21'),
(4, 'SKU-00004', 'Pipe Connectors', 'pcs', 100, 15, '2026-04-21 10:59:21'),
(5, 'SKU-00005', 'Sealant', 'pcs', 20, 5, '2026-04-21 10:59:21'),
(6, 'SKU-00006', 'Warehouse Shelves', 'pcs', 5, 2, '2026-04-21 10:59:21'),
(7, 'SKU-00007', 'Storage Boxes', 'pcs', 50, 10, '2026-04-21 10:59:21'),
(8, 'SKU-00008', 'Sample Product', NULL, 0, 0, '2026-04-28 14:06:54');

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
(3, 'Global Ware Solutions INC.', 'Carlos Delos Reyes', 'global.warehouse@gmail.com', '09111234567', 'Subis, Bataan', 'inactive', '2026-04-20 09:51:09'),
(4, 'NorthBuild Trading', 'Ana Reyes', 'ana@panorth.com', '09301234567', 'Dagupan City, Pangasinan', 'active', '2026-05-12 01:41:08'),
(5, 'doof', 'nicolass', 'doof@gmail.com', '09121212121', 'Santa Rita, Olongapo City', 'active', '2026-05-12 03:50:19'),
(6, 'neo', 'neo', 'neo@gmail.com', '09999999', 'Donot St', 'active', '2026-05-12 04:01:42'),
(7, 'bert', 'ro', 'bert@gmail.com', '099', 'ddneideni', 'active', '2026-05-12 04:10:21');

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
  `contact_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `products_offered` text DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `documents_json` longtext DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password_hash` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_applications`
--

INSERT INTO `supplier_applications` (`id`, `company_name`, `contact_person`, `email`, `phone`, `contact_number`, `address`, `products_offered`, `document_name`, `document_path`, `documents_json`, `status`, `reviewed_by`, `reviewed_at`, `notes`, `created_at`, `password_hash`) VALUES
(1, 'Tarlac Steel Corp.', 'Ben Santos', 'ben@steel.com', '09201234567', NULL, 'Tarlac City, Philippines', NULL, NULL, NULL, NULL, 'rejected', 8, '2026-05-12 03:50:31', NULL, '2026-04-21 10:59:21', NULL),
(2, 'NorthBuild Trading', 'Ana Reyes', 'ana@panorth.com', '09301234567', NULL, 'Dagupan City, Pangasinan', NULL, NULL, NULL, NULL, 'approved', 8, '2026-05-12 01:41:08', NULL, '2026-04-21 10:59:21', NULL),
(3, 'doof', 'nicolass', 'doof@gmail.com', '', '09121212121', 'Santa Rita, Olongapo City', 'Printer paper', 'DRAFTMEMOImplementingPolicyandGuidelinesontheFacultyandProgramChairPerformanceEvaluationSystemPES.pdf', '/uploads/supplier-docs/20260512012818_4c4709db.pdf', NULL, 'approved', 8, '2026-05-12 03:50:34', '', '2026-05-12 01:28:18', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC'),
(4, 'neo', 'neo', 'neo@gmail.com', '', '09999999', 'Donot St', 'two seaets', '651902348_1430337804974568_5067961851679792849_n.png', '/uploads/supplier-docs/20260512035429_229e957a.png', '[{\"name\":\"651902348_1430337804974568_5067961851679792849_n.png\",\"path\":\"\\/uploads\\/supplier-docs\\/20260512035429_229e957a.png\"},{\"name\":\"507435375_1228778212307645_7262896952838361921_n.jpg\",\"path\":\"\\/uploads\\/supplier-docs\\/20260512035429_8f2bff55.jpg\"}]', 'approved', 8, '2026-05-12 04:01:42', '', '2026-05-12 03:54:29', '$2y$12$GXDztL8CKkINsHbgoN5l/.q2PIE8wUnn1oMji5c1zdM7pgLxw/Sby'),
(5, 'bert', 'ro', 'bert@gmail.com', '', '099', 'ddneideni', 'neifenfbeif', '202312275.pdf', '/uploads/supplier-docs/20260512040957_ed41e5f7.pdf', '[{\"name\":\"202312275.pdf\",\"path\":\"\\/uploads\\/supplier-docs\\/20260512040957_ed41e5f7.pdf\"}]', 'approved', 8, '2026-05-12 04:10:21', '', '2026-05-12 04:09:57', '$2y$12$teUy7giI8zgfRw3gBTeoJ.8WKrXeP9DbaoQhOvTr6nHKpkfFaxp7K');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`id`, `supplier_id`, `product_id`, `unit_price`, `created_at`, `updated_at`) VALUES
(29, 1, 1, 210.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(30, 1, 2, 98.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(31, 1, 5, 130.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(32, 2, 3, 720.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(33, 2, 4, 480.00, '2026-05-12 05:07:46', '2026-05-12 05:13:03'),
(34, 2, 10, 450.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(35, 3, 6, 1100.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(36, 3, 7, 100.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(37, 3, 8, 60.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(38, 4, 9, 120.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(39, 4, 11, 200.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(40, 5, 1, 210.00, '2026-05-12 05:07:46', '2026-05-12 05:24:54'),
(41, 5, 5, 130.00, '2026-05-12 05:07:46', '2026-05-12 05:07:46'),
(42, 6, 7, 100.00, '2026-05-12 05:07:46', '2026-05-12 05:08:00');

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
(8, 'Admin User', 'admin@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-05-12 04:10:08', '2026-04-20 06:24:15', '2026-05-12 04:10:08'),
(9, 'dench_admin', 'dench@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-04-28 14:06:02', '2026-04-20 06:24:15', '2026-04-28 14:06:02'),
(10, 'carl_admin', 'carl@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-05-12 03:15:06', '2026-04-20 06:24:15', '2026-05-12 03:15:06'),
(11, 'robert_admin', 'robert@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'admin', 1, NULL, '2026-04-20 10:31:53', '2026-04-20 06:24:15', '2026-04-20 10:31:53'),
(12, 'carl_viewer', 'carl+1@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'viewer', 1, NULL, '2026-05-12 03:16:55', '2026-04-20 06:24:15', '2026-05-12 03:16:55'),
(13, 'Procurement User', 'procurement@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'procurement', 1, NULL, '2026-05-12 05:41:51', '2026-04-20 06:24:15', '2026-05-12 05:41:51'),
(14, 'Warehouse User', 'warehouse@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, '2026-05-12 04:06:30', '2026-04-20 06:24:15', '2026-05-12 04:06:30'),
(15, 'qwerty', 'qwerty@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'viewer', 1, NULL, '2026-04-21 03:39:37', '2026-04-20 09:44:19', '2026-04-21 03:39:37'),
(16, 'Sys_Owner', 'owner@email.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'superadmin', 1, NULL, '2026-04-21 04:05:10', '2026-04-21 03:31:51', '2026-04-21 04:05:10'),
(17, 'carl_proc', 'carl@scm.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'procurement', 1, NULL, '2026-04-21 03:31:51', '2026-04-21 03:31:51', '2026-04-21 03:31:51'),
(18, 'robert_ware', 'robert@scm.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, '2026-04-22 08:14:15', '2026-04-21 03:31:51', '2026-04-22 08:14:15'),
(19, 'dench_ware', 'dench@scm.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'warehouse', 1, NULL, '2026-04-21 04:29:53', '2026-04-21 03:31:51', '2026-04-21 04:29:53'),
(20, 'John Dela Cruz', 'abc.supplies@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'supplier', 1, 1, '2026-05-12 05:41:07', '2026-04-21 03:37:45', '2026-05-12 05:41:07'),
(21, 'Maria Grace Piattos', 'prime.source@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'supplier', 1, 2, '2026-05-12 03:21:02', '2026-04-21 03:37:45', '2026-05-12 03:21:02'),
(22, 'Carlos Delos Reyes', 'global.warehouse@gmail.com', '$2y$12$iuoDF/gucd.tigsUZNwVb.aDlkhNHAQOmJGrvscaGBsFzo6bUx0JC', 'supplier', 1, 3, '2026-05-12 03:33:43', '2026-04-21 03:37:45', '2026-05-12 03:33:43'),
(23, 'neo', 'neo@gmail.com', '$2y$12$GXDztL8CKkINsHbgoN5l/.q2PIE8wUnn1oMji5c1zdM7pgLxw/Sby', 'supplier', 1, 6, NULL, '2026-05-12 04:01:42', '2026-05-12 04:01:42'),
(24, 'ro', 'bert@gmail.com', '$2y$12$teUy7giI8zgfRw3gBTeoJ.8WKrXeP9DbaoQhOvTr6nHKpkfFaxp7K', 'supplier', 1, 7, '2026-05-12 04:10:32', '2026-05-12 04:10:21', '2026-05-12 04:10:32');

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`);

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
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_supplier_product` (`supplier_id`,`product_id`),
  ADD KEY `fk_supplier_products_product` (`product_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurements`
--
ALTER TABLE `procurements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `procurement_items`
--
ALTER TABLE `procurement_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supplier_applications`
--
ALTER TABLE `supplier_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
-- Constraints for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `fk_supplier_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supplier_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_supplier_user` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
