-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 04:06 AM
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
-- Database: `project`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `created_at`, `is_archived`) VALUES
(1, 'Electrical Supplies', '2026-04-16 01:44:32', 0),
(2, 'Construction Materials', '2026-04-16 01:44:32', 0),
(3, 'Plumbing Supplies', '2026-04-16 01:44:32', 0),
(4, 'Tools & Equipment', '2026-04-16 01:44:32', 0),
(5, 'Paint & Finishes', '2026-04-16 01:44:32', 0),
(6, 'Fasteners', '2026-04-16 01:44:32', 0),
(8, 'Tiles', '2026-05-05 02:04:31', 0);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `variation` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `supplier_price` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `stock` decimal(10,4) DEFAULT NULL,
  `reorder_level` decimal(10,4) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sku` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL,
  `last_updated_by` varchar(100) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `expiring` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `brand`, `variation`, `category_id`, `subcategory_id`, `unit`, `supplier_price`, `selling_price`, `stock`, `reorder_level`, `image_path`, `description`, `sku`, `created_at`, `created_by`, `last_updated_by`, `is_archived`, `expiring`) VALUES
(1, 'GI C Purlins', 'Local', '2x3x1.2mm', 2, 6, 'pcs', 1400.00, 1500.00, 63.0000, 10.0000, NULL, NULL, 'CPL-2X3-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(2, 'GI C Purlins', 'Local', '2x3x1.5mm', 2, 6, 'pcs', 0.00, 1600.00, 16.0000, 10.0000, NULL, NULL, 'CPL-2X3-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(3, 'GI C Purlins', 'Local', '2x3x2.0mm', 2, 6, 'length', 250.00, 0.00, 3.0000, 10.0000, NULL, 'GI C Purlins size 2x3x2.0mm', 'CPL-2X3-20', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(4, 'GI C Purlins', 'Local', '2x4x1.2mm', 2, 6, 'length', 1600.00, 0.00, 8.0000, 10.0000, NULL, NULL, 'CPL-2X4-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(5, 'GI C Purlins', 'Local', '2x4x1.5mm', 2, 6, 'length', 2530.00, 0.00, 10.0000, 10.0000, NULL, NULL, 'CPL-2X4-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(6, 'GI C Purlins', 'Local', '2x4x2.0mm', 2, 6, 'length', 2400.00, 0.00, 10.0000, 10.0000, NULL, 'GI C Purlins size 2x4x2.0mm', 'CPL-2X4-20', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(7, 'GI C Purlins', 'Local', '2x6x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'GI C Purlins size 2x6x1.2mm', 'CPL-2X6-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(8, 'GI C Purlins', 'Local', '2x6x1.5mm', 2, 6, 'length', 1799.99, 0.00, 20.0000, 10.0000, NULL, 'GI C Purlins size 2x6x1.5mm', 'CPL-2X6-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(9, 'GI C Purlins', 'Local', '2x6x2.0mm', 2, 6, 'length', 1719.99, 0.00, 10.0000, 10.0000, NULL, 'GI C Purlins size 2x6x2.0mm', 'CPL-2X6-20', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(10, 'Tubular Steel', 'Local', '1x1x1.2mm', 2, 6, 'length', 2500.00, 0.00, 18.0000, 10.0000, NULL, 'Tubular steel 1x1x1.2mm', 'TUB-1X1-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(11, 'Tubular Steel', 'Local', '1x1x1.5mm', 2, 6, 'length', 500.00, 0.00, 20.0000, 10.0000, NULL, 'Tubular steel 1x1x1.5mm', 'TUB-1X1-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(12, 'Tubular Steel', 'Local', '1x2x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 1x2x1.2mm', 'TUB-1X2-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(13, 'Tubular Steel', 'Local', '1x2x1.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 1x2x1.5mm', 'TUB-1X2-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(14, 'Tubular Steel', 'Local', '1x3x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 1x3x1.2mm', 'TUB-1X3-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(15, 'Tubular Steel', 'Local', '1x3x1.5mm', 2, 6, 'length', 1700.00, 0.00, 1.0000, 10.0000, NULL, 'Tubular steel 1x3x1.5mm', 'TUB-1X3-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(16, 'Tubular Steel', 'Local', '2x2x1.2mm', 2, 6, 'length', 800.00, 0.00, 1.0000, 10.0000, NULL, 'Tubular steel 2x2x1.2mm', 'TUB-2X2-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(17, 'Tubular Steel', 'Local', '2x2x1.5mm', 2, 6, 'length', 2600.00, 0.00, 12.0000, 10.0000, NULL, 'Tubular steel 2x2x1.5mm', 'TUB-2X2-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(18, 'Tubular Steel', 'Local', '2x2x2.0mm', 2, 6, 'length', 2400.00, 0.00, 1.0000, 10.0000, NULL, 'Tubular steel 2x2x2.0mm', 'TUB-2X2-20', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(19, 'Tubular Steel', 'Local', '2x3x1.2mm', 2, 6, 'length', 1100.00, 0.00, 10.0000, 10.0000, NULL, 'Tubular steel 2x3x1.2mm', 'TUB-2X3-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(20, 'Tubular Steel', 'Local', '2x3x1.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 2x3x1.5mm', 'TUB-2X3-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(21, 'Tubular Steel', 'Local', '2x4x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 2x4x1.2mm', 'TUB-2X4-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(22, 'Tubular Steel', 'Local', '2x4x1.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 2x4x1.5mm', 'TUB-2X4-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(23, 'Tubular Steel', 'Local', '2x6x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 2x6x1.2mm', 'TUB-2X6-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(24, 'Tubular Steel', 'Local', '2x6x1.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 2x6x1.5mm', 'TUB-2X6-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(25, 'Tubular Steel', 'Local', '3x3x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 3x3x1.2mm', 'TUB-3X3-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(26, 'Tubular Steel', 'Local', '3x3x1.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 3x3x1.5mm', 'TUB-3X3-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(27, 'Tubular Steel', 'Local', '3x3x2.0mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 3x3x2.0mm', 'TUB-3X3-20', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(28, 'Tubular Steel', 'Local', '4x4x1.2mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 4x4x1.2mm', 'TUB-4X4-12', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(29, 'Tubular Steel', 'Local', '4x4x1.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 4x4x1.5mm', 'TUB-4X4-15', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(30, 'Tubular Steel', 'Local', '4x4x2.0mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Tubular steel 4x4x2.0mm', 'TUB-4X4-20', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(31, 'Lite Metal Frame', 'Local', 'Single furring 0.3mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Light metal frame single furring', 'LMF-SF-03', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(32, 'Lite Metal Frame', 'Local', 'Single furring 0.4mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Light metal frame single furring', 'LMF-SF-04', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(33, 'Lite Metal Frame', 'Local', 'Double furring 0.3mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Light metal frame double furring', 'LMF-DF-03', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(34, 'Lite Metal Frame', 'Local', 'Double furring 0.4mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Light metal frame double furring', 'LMF-DF-04', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(35, 'Lite Metal Frame', 'Local', 'Metal studs 0.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Metal studs framing', 'LMF-MS-05', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(36, 'Lite Metal Frame', 'Local', 'Metal studs 0.6mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Metal studs framing', 'LMF-MS-06', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(37, 'Lite Metal Frame', 'Local', 'Metal track 0.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Metal track system', 'LMF-MT-05', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(38, 'Lite Metal Frame', 'Local', 'Metal track 0.6mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Metal track system', 'LMF-MT-06', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(39, 'Lite Metal Frame', 'Local', 'Carrying Channel 0.5mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Carrying channel frame', 'LMF-CC-05', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(40, 'Lite Metal Frame', 'Local', 'Wall angle 0.3mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Wall angle support', 'LMF-WA-03', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(41, 'Lite Metal Frame', 'Local', 'Wall angle 0.4mm', 2, 6, 'length', 0.00, 0.00, 0.0000, 10.0000, NULL, 'Wall angle support', 'LMF-WA-04', '2026-04-16 06:49:55', 'admin', 'admin', 0, 0),
(42, 'Angle Bar', 'Foreign', '2x3x4', 2, 6, 'pcs', 2300.00, 1800.00, 190.0000, 20.0000, NULL, NULL, 'CONST-LOC-2X3-001', '2026-04-16 11:36:37', 'admin', 'admin', 0, 0),
(43, 'GI C Purlins 2x3x1.6mm', '', '', 2, 6, 'pcs', 2530.00, 1750.00, 13.0000, 30.0000, NULL, NULL, 'CONST-001', '2026-05-02 07:00:40', 'admin', NULL, 0, 0),
(44, 'GI C Purlins 2x3x1.7mm', 'Local', '', 2, 6, 'pcs', 2600.00, 1850.00, 1.0000, 30.0000, NULL, NULL, 'CONST-001', '2026-05-02 07:03:43', 'admin', 'admin', 0, 0),
(45, 'Wire Nails 1\"', '', '', 6, 26, 'kg', 160.00, 175.00, 63.3576, 20.0000, NULL, 'Thin trim, small crafts', 'FASTE-001', '2026-05-04 17:05:05', 'admin', NULL, 0, 0),
(46, 'Wire Nails 6\"', '', '', 6, 26, 'kg', 200.00, 255.00, 10.0000, 5.0000, NULL, NULL, 'FASTE-001', '2026-05-05 01:13:06', 'admin', NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_deletion_log`
--

CREATE TABLE `product_deletion_log` (
  `log_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `deleted_by` varchar(100) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_history`
--

CREATE TABLE `product_history` (
  `history_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_username` varchar(50) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_history`
--

INSERT INTO `product_history` (`history_id`, `product_id`, `user_username`, `action_type`, `description`, `created_at`) VALUES
(1, 1, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-16 06:54:57'),
(2, 1, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-16 06:58:41'),
(3, 1, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-16 07:21:50'),
(4, 2, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-16 07:32:20'),
(5, 2, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-16 07:34:48'),
(6, 42, 'admin', 'Created', 'Product \'Angle Bar\' created.', '2026-04-16 11:36:37'),
(7, 42, 'admin', 'Updated', 'Product \'Angle Bar\' updated.', '2026-04-16 11:39:10'),
(8, 42, 'admin', 'Updated', 'Product \'Angle Bar\' updated.', '2026-04-16 11:40:39'),
(9, 2, 'admin', 'STOCK OUT', 'Deducted 20 units. Reason: Damage', '2026-04-17 05:03:50'),
(10, 2, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-23 15:30:11'),
(11, 4, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-23 15:35:50'),
(12, 5, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-04-23 15:36:04'),
(13, 42, 'admin', 'STOCK OUT', 'Deducted 3 units. Reason: Damage', '2026-04-28 15:19:36'),
(14, 2, 'admin', 'Updated', 'Product \'GI C Purlins\' updated.', '2026-05-02 01:02:04'),
(15, 43, 'admin', 'Created', 'Product \'GI C Purlins 2x3x1.6mm\' created.', '2026-05-02 07:00:41'),
(16, 44, 'admin', 'Created', 'Product \'GI C Purlins 2x3x1.7mm\' created.', '2026-05-02 07:03:43'),
(17, 42, 'admin', 'STOCK OUT', 'Deducted 3 units. Reason: Damage, RUST', '2026-05-02 07:38:24'),
(18, 44, 'admin', 'Updated', 'Product \'GI C Purlins 2x3x1.7mm\' updated.', '2026-05-04 16:10:58'),
(19, 45, 'admin', 'Created', 'Product \'Wire Nails 1\"\' created.', '2026-05-04 17:05:05'),
(20, 46, 'admin', 'Created', 'Product \'Wire Nails 6\"\' created.', '2026-05-05 01:13:06');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `return_id` int(11) NOT NULL,
  `original_sale_group_id` int(11) NOT NULL,
  `return_reason` text DEFAULT NULL,
  `processed_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `returns`
--

INSERT INTO `returns` (`return_id`, `original_sale_group_id`, `return_reason`, `processed_by`, `created_at`) VALUES
(1, 3, 'damage', 'staff', '2026-04-16 10:58:22'),
(2, 20, 'Test', 'admin', '2026-05-01 17:12:38'),
(3, 21, 'Change of mind', 'admin', '2026-05-01 17:41:49'),
(4, 25, 'change of mind', 'admin', '2026-05-02 00:33:53'),
(5, 26, 'test', 'admin', '2026-05-02 01:03:09');

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

CREATE TABLE `return_items` (
  `return_item_id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,4) DEFAULT NULL,
  `unit_price_at_sale` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_items`
--

INSERT INTO `return_items` (`return_item_id`, `return_id`, `product_id`, `quantity`, `unit_price_at_sale`) VALUES
(1, 1, 1, 2.0000, 0.00),
(2, 2, 42, 3.0000, 0.00),
(3, 3, 42, 1.0000, 0.00),
(4, 3, 1, 1.0000, 0.00),
(5, 3, 2, 2.0000, 0.00),
(6, 4, 42, 3.0000, 0.00),
(7, 4, 1, 5.0000, 0.00),
(8, 4, 2, 9.0000, 0.00),
(9, 5, 42, 1.0000, 0.00),
(10, 5, 1, 2.0000, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `sale_group_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,4) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `sale_group_id`, `product_id`, `quantity`, `total_price`, `sale_date`) VALUES
(1, 1, 1, 15.0000, 22500.00, '2026-04-16 07:38:36'),
(2, 1, 2, 10.0000, 16000.00, '2026-04-16 07:38:36'),
(3, 2, 1, 5.0000, 7500.00, '2026-04-16 08:05:11'),
(4, 3, 1, 5.0000, 7500.00, '2026-04-16 08:21:40'),
(5, 3, 2, 2.0000, 3200.00, '2026-04-16 08:21:40'),
(6, 4, 42, 1.0000, 1800.00, '2026-04-21 13:22:29'),
(7, 5, 42, 2.0000, 3600.00, '2026-04-23 14:33:16'),
(8, 6, 42, 1.0000, 1800.00, '2026-04-23 14:36:25'),
(9, 7, 1, 1.0000, 1500.00, '2026-04-23 14:38:10'),
(10, 8, 1, 1.0000, 1500.00, '2026-04-23 14:40:16'),
(11, 9, 2, 1.0000, 1600.00, '2026-04-23 14:42:47'),
(12, 10, 3, 1.0000, 0.00, '2026-04-23 14:44:00'),
(13, 11, 1, 1.0000, 1500.00, '2026-04-23 14:44:45'),
(14, 11, 42, 1.0000, 1800.00, '2026-04-23 14:44:45'),
(15, 12, 2, 3.0000, 4800.00, '2026-04-23 14:47:04'),
(16, 12, 42, 5.0000, 9000.00, '2026-04-23 14:47:04'),
(17, 13, 42, 1.0000, 1800.00, '2026-04-23 14:47:34'),
(18, 14, 42, 1.0000, 1800.00, '2026-04-23 14:48:01'),
(19, 15, 1, 1.0000, 1500.00, '2026-04-23 14:48:44'),
(20, 15, 2, 1.0000, 1600.00, '2026-04-23 14:48:44'),
(21, 16, 42, 1.0000, 1800.00, '2026-04-23 14:49:23'),
(22, 17, 1, 1.0000, 1500.00, '2026-04-23 15:01:21'),
(23, 18, 1, 1.0000, 1500.00, '2026-04-23 15:06:53'),
(24, 19, 42, 1.0000, 1800.00, '2026-04-23 15:08:20'),
(25, 20, 42, 3.0000, 5400.00, '2026-05-01 17:12:05'),
(26, 21, 1, 3.0000, 4500.00, '2026-05-01 17:36:28'),
(27, 21, 2, 5.0000, 8000.00, '2026-05-01 17:36:28'),
(28, 21, 42, 2.0000, 3600.00, '2026-05-01 17:36:28'),
(29, 22, 42, 1.0000, 1800.00, '2026-05-01 17:48:48'),
(30, 23, 1, 2.0000, 3000.00, '2026-05-01 17:53:46'),
(31, 23, 2, 5.0000, 8000.00, '2026-05-01 17:53:46'),
(32, 23, 42, 4.0000, 7200.00, '2026-05-01 17:53:46'),
(33, 24, 1, 10.0000, 15000.00, '2026-05-01 18:00:44'),
(34, 24, 2, 7.0000, 11200.00, '2026-05-01 18:00:44'),
(35, 24, 42, 5.0000, 9000.00, '2026-05-01 18:00:44'),
(36, 25, 1, 5.0000, 7500.00, '2026-05-01 23:56:45'),
(37, 25, 2, 9.0000, 14400.00, '2026-05-01 23:56:45'),
(38, 25, 42, 3.0000, 5400.00, '2026-05-01 23:56:45'),
(39, 26, 1, 5.0000, 7500.00, '2026-05-02 00:12:06'),
(40, 26, 2, 2.0000, 3200.00, '2026-05-02 00:12:06'),
(41, 26, 42, 3.0000, 5400.00, '2026-05-02 00:12:06'),
(42, 27, 42, 20.0000, 36000.00, '2026-05-02 04:23:48'),
(43, 28, 1, 2.0000, 3000.00, '2026-05-04 16:24:25'),
(44, 28, 4, 2.0000, 0.00, '2026-05-04 16:24:25'),
(45, 28, 42, 2.0000, 3600.00, '2026-05-04 16:24:25'),
(46, 29, 45, 0.5000, 87.50, '2026-05-04 17:13:25'),
(47, 30, 42, 5.0000, 9000.00, '2026-05-05 01:02:42'),
(48, 30, 45, 0.5710, 99.92, '2026-05-05 01:02:42'),
(49, 31, 45, 0.5714, 100.00, '2026-05-05 01:21:35'),
(50, 32, 45, 50.0000, 8750.00, '2026-05-05 01:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `sale_groups`
--

CREATE TABLE `sale_groups` (
  `sale_group_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_contact` varchar(50) DEFAULT NULL,
  `customer_address` varchar(255) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_groups`
--

INSERT INTO `sale_groups` (`sale_group_id`, `user_id`, `customer_name`, `customer_contact`, `customer_address`, `discount_amount`, `total_amount`, `created_by`, `created_at`) VALUES
(1, 3, 'Walk-in Customer', NULL, NULL, 0.00, 38500.00, 'admin', '2026-04-16 07:38:36'),
(2, 3, 'Mark Rosales', '0964946412', 'Gango, Libona', 0.00, 7500.00, 'admin', '2026-04-16 08:05:11'),
(3, 2, 'John Caramoan', '09304313', 'Uptown', 0.00, 10700.00, 'staff', '2026-04-16 08:21:40'),
(4, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1800.00, 'admin', '2026-04-21 13:22:29'),
(5, 5, 'Rosales', NULL, 'Bango', 0.00, 3600.00, 'admin', '2026-04-23 14:33:16'),
(6, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1800.00, 'admin', '2026-04-23 14:36:25'),
(7, 2, 'Walk-in Customer', NULL, NULL, 0.00, 1500.00, 'staff', '2026-04-23 14:38:10'),
(8, 2, 'Walk-in Customer', NULL, NULL, 0.00, 1500.00, 'staff', '2026-04-23 14:40:16'),
(9, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1600.00, 'admin', '2026-04-23 14:42:47'),
(10, 5, 'Walk-in Customer', NULL, NULL, 0.00, 0.00, 'admin', '2026-04-23 14:44:00'),
(11, 5, 'Walk-in Customer', NULL, NULL, 0.00, 3300.00, 'admin', '2026-04-23 14:44:45'),
(12, 5, 'Walk-in Customer', NULL, NULL, 0.00, 13800.00, 'admin', '2026-04-23 14:47:04'),
(13, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1800.00, 'admin', '2026-04-23 14:47:34'),
(14, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1800.00, 'admin', '2026-04-23 14:48:01'),
(15, 5, 'Walk-in Customer', NULL, NULL, 0.00, 3100.00, 'admin', '2026-04-23 14:48:44'),
(16, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1800.00, 'admin', '2026-04-23 14:49:23'),
(17, 2, 'Walk-in Customer', NULL, NULL, 0.00, 1500.00, 'staff', '2026-04-23 15:01:21'),
(18, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1500.00, 'admin', '2026-04-23 15:06:53'),
(19, 5, 'Walk-in Customer', NULL, NULL, 0.00, 1800.00, 'admin', '2026-04-23 15:08:20'),
(20, 5, 'Walk-in Customer', NULL, NULL, 0.00, 5400.00, 'admin', '2026-05-01 17:12:05'),
(21, 5, 'Walk-in Customer', NULL, NULL, 3000.00, 13100.00, 'admin', '2026-05-01 17:36:28'),
(22, 5, 'Mark Rosales', NULL, 'Indahag', 0.00, 1800.00, 'admin', '2026-05-01 17:48:48'),
(23, 2, 'Lee', NULL, NULL, 1200.00, 17000.00, 'staff', '2026-05-01 17:53:46'),
(24, 2, 'Walk-in Customer', NULL, NULL, 2800.00, 32400.00, 'staff', '2026-05-01 18:00:44'),
(25, 2, 'Walk-in Customer', NULL, NULL, 12000.00, 15300.00, 'staff', '2026-05-01 23:56:44'),
(26, 2, 'Walk-in Customer', NULL, NULL, 2550.00, 13550.00, 'staff', '2026-05-02 00:12:06'),
(27, 2, 'Walk-in Customer', NULL, NULL, 2481.54, 33518.46, 'staff', '2026-05-02 04:23:48'),
(28, 2, 'Walk-in Customer', NULL, NULL, 0.00, 6600.00, 'staff', '2026-05-04 16:24:25'),
(29, 2, 'Walk-in Customer', NULL, NULL, 0.00, 87.50, 'staff', '2026-05-04 17:13:25'),
(30, 2, 'Walk-in Customer', NULL, NULL, 1500.00, 7599.92, 'staff', '2026-05-05 01:02:42'),
(31, 2, 'Walk-in Customer', NULL, NULL, 0.00, 100.00, 'staff', '2026-05-05 01:21:35'),
(32, 2, 'Walk-in Customer', NULL, NULL, 0.00, 8750.00, 'staff', '2026-05-05 01:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `sale_payments`
--

CREATE TABLE `sale_payments` (
  `payment_id` int(11) NOT NULL,
  `sale_group_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Should match the grand total of items in the sales table for this group',
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `payment_type` varchar(50) NOT NULL DEFAULT 'CASH',
  `cash_given` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_payments`
--

INSERT INTO `sale_payments` (`payment_id`, `sale_group_id`, `total_amount`, `discount_amount`, `payment_type`, `cash_given`, `change_amount`, `payment_date`) VALUES
(1, 1, 38500.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-16 07:38:36'),
(2, 2, 7500.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-16 08:05:11'),
(3, 3, 10700.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-16 08:21:40'),
(4, 4, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-21 13:22:29'),
(5, 5, 3600.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:33:16'),
(6, 6, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:36:25'),
(7, 7, 1500.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:38:10'),
(8, 8, 1500.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:40:16'),
(9, 9, 1600.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:42:47'),
(10, 10, 0.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:44:00'),
(11, 11, 3300.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:44:45'),
(12, 12, 13800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:47:04'),
(13, 13, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:47:34'),
(14, 14, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:48:01'),
(15, 15, 3100.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:48:44'),
(16, 16, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 14:49:23'),
(17, 17, 1500.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 15:01:21'),
(18, 18, 1500.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 15:06:53'),
(19, 19, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-04-23 15:08:20'),
(20, 20, 5400.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-01 17:12:05'),
(21, 21, 13100.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-01 17:36:28'),
(22, 22, 1800.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-01 17:48:48'),
(23, 23, 17000.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-01 17:53:46'),
(24, 24, 32400.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-01 18:00:44'),
(25, 25, 15300.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-01 23:56:45'),
(26, 26, 13550.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-02 00:12:06'),
(27, 27, 33518.46, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-02 04:23:48'),
(28, 28, 6600.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-04 16:24:25'),
(29, 29, 87.50, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-04 17:13:25'),
(30, 30, 7599.92, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-05 01:02:42'),
(31, 31, 100.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-05 01:21:35'),
(32, 32, 8750.00, 0.00, 'PHYSICAL_CASH', 0.00, 0.00, '2026-05-05 01:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `stock_history`
--

CREATE TABLE `stock_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `movement_type` varchar(20) DEFAULT 'IN',
  `supplier_id` int(11) DEFAULT NULL,
  `transaction_type` varchar(50) NOT NULL DEFAULT 'STOCK_IN',
  `quantity` decimal(10,4) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier_price` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `item_desc` varchar(255) DEFAULT NULL,
  `stocked_by` varchar(100) NOT NULL,
  `stock_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_history`
--

INSERT INTO `stock_history` (`id`, `product_id`, `batch_id`, `movement_type`, `supplier_id`, `transaction_type`, `quantity`, `expiry_date`, `supplier_price`, `total_cost`, `item_desc`, `stocked_by`, `stock_date`, `created_at`) VALUES
(1, 1, 1, 'IN', 2, 'STOCK_IN', 20.0000, NULL, 1300.00, 26000.00, '', 'admin', '2026-04-16 14:54:07', '2026-04-16 06:54:07'),
(2, 1, 2, 'IN', 1, 'STOCK_IN', 30.0000, NULL, 1400.00, 42000.00, '12312', 'admin', '2026-04-16 15:18:52', '2026-04-16 07:18:52'),
(3, 2, 3, 'IN', 2, 'STOCK_IN', 30.0000, NULL, 1800.00, 54000.00, '', 'admin', '2026-04-16 15:34:08', '2026-04-16 07:34:08'),
(4, 1, 4, 'IN', 2, 'STOCK_IN', 1.0000, NULL, 1350.00, 1350.00, '', 'admin', '2026-04-16 18:44:37', '2026-04-16 10:44:37'),
(5, 1, 5, 'IN', 3, 'STOCK_IN', 30.0000, NULL, 1320.00, 39600.00, '', 'admin', '2026-04-16 19:17:26', '2026-04-16 11:17:26'),
(6, 3, 6, 'IN', 2, 'STOCK_IN', 4.0000, NULL, 250.00, 1000.00, '1231', 'admin', '2026-04-16 19:18:14', '2026-04-16 11:18:14'),
(7, 42, 7, 'IN', 2, 'STOCK_IN', 50.0000, NULL, 2300.00, 115000.00, '2314231', 'admin', '2026-04-17 12:52:10', '2026-04-17 04:52:10'),
(8, 2, 8, 'IN', 2, 'STOCK_IN', 40.0000, NULL, 1800.00, 72000.00, '', 'admin', '2026-04-17 12:52:52', '2026-04-17 04:52:52'),
(9, 1, 9, 'IN', 1, 'STOCK_IN', 30.0000, NULL, 1400.00, 42000.00, '', 'admin', '2026-04-17 12:53:42', '2026-04-17 04:53:42'),
(10, 42, 10, 'IN', 1, 'STOCK_IN', 200.0000, NULL, 2300.00, 460000.00, '548731', 'staff', '2026-05-02 12:10:53', '2026-05-02 04:10:53'),
(11, 10, 11, 'IN', 1, 'STOCK_IN', 8.0000, NULL, 2500.00, 20000.00, '1255213', 'staff', '2026-05-02 12:14:03', '2026-05-02 04:14:03'),
(12, 4, 12, 'IN', 3, 'STOCK_IN', 10.0000, NULL, 1600.00, 16000.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(13, 5, 12, 'IN', 3, 'STOCK_IN', 10.0000, NULL, 2530.00, 25300.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(14, 6, 12, 'IN', 3, 'STOCK_IN', 10.0000, NULL, 2400.00, 24000.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(15, 8, 12, 'IN', 3, 'STOCK_IN', 20.0000, NULL, 1799.99, 35999.80, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(16, 9, 12, 'IN', 3, 'STOCK_IN', 10.0000, NULL, 1719.99, 17199.90, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(17, 15, 12, 'IN', 3, 'STOCK_IN', 1.0000, NULL, 1700.00, 1700.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(18, 16, 12, 'IN', 3, 'STOCK_IN', 1.0000, NULL, 800.00, 800.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(19, 17, 12, 'IN', 3, 'STOCK_IN', 2.0000, NULL, 1200.00, 2400.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(20, 18, 12, 'IN', 3, 'STOCK_IN', 1.0000, NULL, 2400.00, 2400.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(21, 19, 12, 'IN', 3, 'STOCK_IN', 10.0000, NULL, 1100.00, 11000.00, '1354321', 'staff', '2026-05-02 12:26:24', '2026-05-02 04:26:24'),
(22, 10, 13, 'IN', 5, 'STOCK_IN', 10.0000, NULL, 2500.00, 25000.00, '24546', 'admin', '2026-05-05 00:44:39', '2026-05-04 16:44:39'),
(23, 17, 13, 'IN', 5, 'STOCK_IN', 10.0000, NULL, 2600.00, 26000.00, '24546', 'admin', '2026-05-05 00:44:39', '2026-05-04 16:44:39'),
(24, 43, 14, 'IN', 6, 'STOCK_IN', 10.0000, NULL, 2530.00, 25300.00, '245', 'admin', '2026-05-05 00:45:11', '2026-05-04 16:45:11'),
(25, 44, 15, 'IN', 7, 'STOCK_IN', 1.0000, NULL, 2600.00, 2600.00, '', 'admin', '2026-05-05 00:46:32', '2026-05-04 16:46:32'),
(26, 43, 16, 'IN', 8, 'STOCK_IN', 3.0000, NULL, 2530.00, 7590.00, '', 'admin', '2026-05-05 00:47:45', '2026-05-04 16:47:45'),
(27, 11, 17, 'IN', 8, 'STOCK_IN', 20.0000, NULL, 500.00, 10000.00, '24212', 'admin', '2026-05-05 00:48:48', '2026-05-04 16:48:48'),
(28, 45, 18, 'IN', 1, 'STOCK_IN', 15.0000, NULL, 160.00, 2400.00, '26413', 'admin', '2026-05-05 01:12:16', '2026-05-04 17:12:16'),
(29, 46, 19, 'IN', 5, 'STOCK_IN', 10.0000, NULL, 200.00, 2000.00, '5120.0', 'staff', '2026-05-05 09:46:52', '2026-05-05 01:46:52'),
(30, 45, 20, 'IN', 1, 'STOCK_IN', 100.0000, NULL, 160.00, 16000.00, '', 'staff', '2026-05-05 09:59:18', '2026-05-05 01:59:18');

-- --------------------------------------------------------

--
-- Table structure for table `stock_in_batches`
--

CREATE TABLE `stock_in_batches` (
  `batch_id` int(11) NOT NULL,
  `reference_no` varchar(255) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `stocked_by` varchar(100) NOT NULL,
  `stock_in_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_in_batches`
--

INSERT INTO `stock_in_batches` (`batch_id`, `reference_no`, `supplier_id`, `stocked_by`, `stock_in_date`) VALUES
(1, '', 2, 'admin', '2026-04-16 14:54:07'),
(2, '12312', 1, 'admin', '2026-04-16 15:18:52'),
(3, '', 2, 'admin', '2026-04-16 15:34:08'),
(4, '', 2, 'admin', '2026-04-16 18:44:37'),
(5, '', 3, 'admin', '2026-04-16 19:17:26'),
(6, '1231', 2, 'admin', '2026-04-16 19:18:14'),
(7, '2314231', 2, 'admin', '2026-04-17 12:52:10'),
(8, '', 2, 'admin', '2026-04-17 12:52:52'),
(9, '', 1, 'admin', '2026-04-17 12:53:42'),
(10, '548731', 1, 'staff', '2026-05-02 12:10:53'),
(11, '1255213', 1, 'staff', '2026-05-02 12:14:03'),
(12, '1354321', 3, 'staff', '2026-05-02 12:26:24'),
(13, '24546', 5, 'admin', '2026-05-05 00:44:39'),
(14, '245', 6, 'admin', '2026-05-05 00:45:11'),
(15, '', 7, 'admin', '2026-05-05 00:46:32'),
(16, '', 8, 'admin', '2026-05-05 00:47:45'),
(17, '24212', 8, 'admin', '2026-05-05 00:48:48'),
(18, '26413', 1, 'admin', '2026-05-05 01:12:16'),
(19, '5120.0', 5, 'staff', '2026-05-05 09:46:52'),
(20, '', 1, 'staff', '2026-05-05 09:59:18');

-- --------------------------------------------------------

--
-- Table structure for table `stock_out`
--

CREATE TABLE `stock_out` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,4) DEFAULT NULL,
  `supplier_price` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `stocked_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_out`
--

INSERT INTO `stock_out` (`id`, `product_id`, `quantity`, `supplier_price`, `total_cost`, `reason`, `stocked_by`, `created_at`) VALUES
(1, 2, 20.0000, 1800.00, 36000.00, 'Damage', 'admin', '2026-04-17 13:03:50'),
(2, 42, 3.0000, 2300.00, 6900.00, 'Damage', 'admin', '2026-04-28 23:19:36'),
(3, 42, 3.0000, 2300.00, 6900.00, 'Damage, RUST', 'admin', '2026-05-02 15:38:24');

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `subcategory_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_name` varchar(255) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`subcategory_id`, `category_id`, `subcategory_name`, `image_path`, `created_at`, `is_archived`) VALUES
(1, 1, 'Wires & Cables', NULL, '2026-04-16 06:48:00', 0),
(2, 1, 'Switches & Outlets', NULL, '2026-04-16 06:48:00', 0),
(3, 1, 'Lighting', NULL, '2026-04-16 06:48:00', 0),
(4, 1, 'Circuit Breakers', NULL, '2026-04-16 06:48:00', 0),
(5, 1, 'Electrical Boxes & Panels', NULL, '2026-04-16 06:48:00', 0),
(6, 2, 'Steel', NULL, '2026-04-16 06:48:00', 0),
(7, 2, 'Cement', NULL, '2026-04-16 06:48:00', 0),
(8, 2, 'Aggregates (Sand & Gravel)', NULL, '2026-04-16 06:48:00', 0),
(9, 2, 'Bricks & Blocks', NULL, '2026-04-16 06:48:00', 0),
(10, 2, 'Wood & Lumber', NULL, '2026-04-16 06:48:00', 0),
(11, 3, 'Pipes', NULL, '2026-04-16 06:48:00', 0),
(12, 3, 'Fittings', NULL, '2026-04-16 06:48:00', 0),
(13, 3, 'Valves', NULL, '2026-04-16 06:48:00', 0),
(14, 3, 'Water Closets & Fixtures', NULL, '2026-04-16 06:48:00', 0),
(15, 3, 'Sealants & Adhesives', NULL, '2026-04-16 06:48:00', 0),
(16, 4, 'Hand Tools', NULL, '2026-04-16 06:48:00', 0),
(17, 4, 'Power Tools', NULL, '2026-04-16 06:48:00', 0),
(18, 4, 'Measuring Tools', NULL, '2026-04-16 06:48:00', 0),
(19, 4, 'Safety Equipment', NULL, '2026-04-16 06:48:00', 0),
(20, 4, 'Construction Equipment', NULL, '2026-04-16 06:48:00', 0),
(21, 5, 'Paint', NULL, '2026-04-16 06:48:00', 0),
(22, 5, 'Primer', NULL, '2026-04-16 06:48:00', 0),
(23, 5, 'Varnish', NULL, '2026-04-16 06:48:00', 0),
(24, 5, 'Thinner & Solvents', NULL, '2026-04-16 06:48:00', 0),
(25, 5, 'Waterproofing', NULL, '2026-04-16 06:48:00', 0),
(26, 6, 'Nails', NULL, '2026-04-16 06:48:00', 0),
(27, 6, 'Screws', NULL, '2026-04-16 06:48:00', 0),
(28, 6, 'Bolts & Nuts', NULL, '2026-04-16 06:48:00', 0),
(29, 6, 'Anchors', NULL, '2026-04-16 06:48:00', 0),
(30, 6, 'Washers', NULL, '2026-04-16 06:48:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `contact_no`, `email`, `address`, `item_description`, `notes`, `created_at`) VALUES
(1, 'Mackun Hardware', 'Chandru Kishan Lanas', '09555555555', 'chandrukishan21@gmail.com', 'test', NULL, '', '2026-04-16 02:45:57'),
(2, 'CDO BUILDERS', 'Chandru Kishan Lanas', '09555555555', 'chandrukishan21@gmail.com', 'test', NULL, '', '2026-04-16 02:47:24'),
(3, 'BBB Hardware', 'Chandru Kishan Lanas', '090944', 'chandrukishan21@gmail.com', 'test', NULL, '', '2026-04-16 03:15:48'),
(5, 'Supplier 1', 'Chandru Kishan Lanas', '0905121346', 'chandrukishan21@gmail.com', 'Balubal', NULL, '', '2026-05-04 16:42:10'),
(6, 'Supplier 2', 'Chandru Kishan Lanas', '09463164', 'chandrukishan21@gmail.com', 'lapasan', NULL, '', '2026-05-04 16:42:54'),
(7, 'supplier 3', 'Chandru Kishan Lanas', '090413131', 'chandrukishan21@gmail.com', 'bulua', NULL, '', '2026-05-04 16:43:13'),
(8, 'supplier 4', 'Chandru Kishan Lanas', '04643100612', 'chandrukishan21@gmail.com', 'Kauswagan, Cdeo', NULL, '', '2026-05-04 16:43:31');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_logged_in` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `role`, `created_at`, `is_logged_in`, `last_login`) VALUES
(2, 'staff', 'Sidro Santo', '$2y$10$sqgslE2u0tqWrP6YA6uOGum.d2sry7gMgdUEDlSnPJs0An.qfLmHS', 'staff', '2026-04-16 03:18:50', 1, '2026-05-05 08:42:56'),
(5, 'admin', 'user admin', '$2y$10$XVhSHoNdTMRdfwAadAwXnOobWp0l4HGLwNiTXjs3q2J0634oji/XO', 'admin', '2026-04-16 16:22:13', 1, '2026-05-05 08:44:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `fk_products_subcategory` (`subcategory_id`);

--
-- Indexes for table `product_deletion_log`
--
ALTER TABLE `product_deletion_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_deleted_product_id` (`product_id`),
  ADD KEY `idx_deleted_by` (`deleted_by`);

--
-- Indexes for table `product_history`
--
ALTER TABLE `product_history`
  ADD PRIMARY KEY (`history_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `original_sale_group_id` (`original_sale_group_id`);

--
-- Indexes for table `return_items`
--
ALTER TABLE `return_items`
  ADD PRIMARY KEY (`return_item_id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sale_groups`
--
ALTER TABLE `sale_groups`
  ADD PRIMARY KEY (`sale_group_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_sale_group` (`sale_group_id`);

--
-- Indexes for table `stock_history`
--
ALTER TABLE `stock_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `stock_in_batches`
--
ALTER TABLE `stock_in_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `stock_out`
--
ALTER TABLE `stock_out`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`subcategory_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `product_deletion_log`
--
ALTER TABLE `product_deletion_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_history`
--
ALTER TABLE `product_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `return_items`
--
ALTER TABLE `return_items`
  MODIFY `return_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `sale_groups`
--
ALTER TABLE `sale_groups`
  MODIFY `sale_group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `stock_history`
--
ALTER TABLE `stock_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `stock_in_batches`
--
ALTER TABLE `stock_in_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `stock_out`
--
ALTER TABLE `stock_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `subcategory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`subcategory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `fk_sale_group` FOREIGN KEY (`sale_group_id`) REFERENCES `sale_groups` (`sale_group_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_history`
--
ALTER TABLE `stock_history`
  ADD CONSTRAINT `stock_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `stock_history_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_history_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `stock_in_batches` (`batch_id`);

--
-- Constraints for table `stock_in_batches`
--
ALTER TABLE `stock_in_batches`
  ADD CONSTRAINT `stock_in_batches_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
