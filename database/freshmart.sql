-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 17, 2026 at 04:17 AM
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
-- Database: `freshmart`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(50) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Malaysia',
  `type` enum('SHIPPING','BILLING','BOTH') NOT NULL DEFAULT 'BOTH',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `recipient_name`, `phone`, `line1`, `line2`, `city`, `state`, `postcode`, `country`, `type`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 3, 'Home', 'Cherry Tan', '+60123334444', 'Block A-3-12, Cyberia SmartHomes', NULL, 'Cyberjaya', 'Selangor', '63000', 'Malaysia', 'BOTH', 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(2, 5, 'Home', 'william', '01172737367', '122', NULL, 'Cyberjaya', 'Selangor', '63000', 'Malaysia', 'BOTH', 1, '2026-06-07 15:57:36', '2026-06-07 15:57:36'),
(3, 7, 'Home', 'Aisyah Rahman', '+60122345671', 'No 12, Jalan SS15/4', NULL, 'Subang Jaya', 'Selangor', '47500', 'Malaysia', 'BOTH', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(4, 8, 'Home', 'Lim Wei Jie', '+60122345672', 'B-8-3, Pangsapuri Cyberia', NULL, 'Cyberjaya', 'Selangor', '63000', 'Malaysia', 'BOTH', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(5, 9, 'Home', 'Muthu Raj', '+60122345673', '45, Jalan Setia Impian U13', NULL, 'Shah Alam', 'Selangor', '40170', 'Malaysia', 'BOTH', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(6, 10, 'Home', 'Nurul Huda', '+60122345674', 'A-12-7, Residensi Putrajaya', NULL, 'Putrajaya', 'Putrajaya', '62000', 'Malaysia', 'BOTH', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(7, 11, 'Home', 'Daniel Tan', '+60122345675', '20, Jalan Puchong Perdana', NULL, 'Puchong', 'Selangor', '47100', 'Malaysia', 'BOTH', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(8, 12, 'Home', 'Siti Aminah', '+60122345676', 'C-5-2, Apartment Seri Kembangan', NULL, 'Seri Kembangan', 'Selangor', '43300', 'Malaysia', 'BOTH', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(10, 7, 'Home', 'Aisyah Rahman', '+60122345671', 'No 12, Jalan SS15/4', NULL, 'Subang Jaya', 'Selangor', '47500', 'Malaysia', 'BOTH', 1, '2026-07-16 20:23:26', '2026-07-16 20:23:26'),
(11, 8, 'Home', 'Lim Wei Jie', '+60122345672', 'B-8-3, Pangsapuri Cyberia', NULL, 'Cyberjaya', 'Selangor', '63000', 'Malaysia', 'BOTH', 1, '2026-07-16 20:23:26', '2026-07-16 20:23:26'),
(12, 9, 'Home', 'Muthu Raj', '+60122345673', '45, Jalan Setia Impian U13', NULL, 'Shah Alam', 'Selangor', '40170', 'Malaysia', 'BOTH', 1, '2026-07-16 20:23:26', '2026-07-16 20:23:26'),
(13, 10, 'Home', 'Nurul Huda', '+60122345674', 'A-12-7, Residensi Putrajaya', NULL, 'Putrajaya', 'Putrajaya', '62000', 'Malaysia', 'BOTH', 1, '2026-07-16 20:23:26', '2026-07-16 20:23:26'),
(14, 11, 'Home', 'Daniel Tan', '+60122345675', '20, Jalan Puchong Perdana', NULL, 'Puchong', 'Selangor', '47100', 'Malaysia', 'BOTH', 1, '2026-07-16 20:23:26', '2026-07-16 20:23:26'),
(15, 12, 'Home', 'Siti Aminah', '+60122345676', 'C-5-2, Apartment Seri Kembangan', NULL, 'Seri Kembangan', 'Selangor', '43300', 'Malaysia', 'BOTH', 1, '2026-07-16 20:23:26', '2026-07-16 20:23:26');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'USER_ACTIVATE', 'user', 4, NULL, NULL, NULL, NULL, '2026-05-28 19:36:07'),
(2, 1, 'RETAILER_APPROVED', 'retailer', 2, NULL, '{\"reason\":\"\"}', NULL, NULL, '2026-05-28 19:36:13'),
(3, 2, 'CSV_EXPORT', 'retailer_report', NULL, NULL, '{\"from\":\"2026-05-12\",\"to\":\"2026-06-11\"}', NULL, NULL, '2026-06-11 04:55:06'),
(4, 2, 'CSV_EXPORT', 'retailer_report', NULL, NULL, '{\"from\":\"2026-05-31\",\"to\":\"2026-06-30\"}', NULL, NULL, '2026-06-29 20:46:23'),
(5, 1, 'ORDER_STATUS_CHANGE', 'order', 6, NULL, '{\"to\":\"DELIVERED\"}', NULL, NULL, '2026-07-05 08:07:27'),
(6, 2, 'CSV_EXPORT', 'retailer_report', NULL, NULL, '{\"from\":\"2026-06-09\",\"to\":\"2026-07-09\"}', NULL, NULL, '2026-07-09 08:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `guest_session_id` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `guest_session_id`, `created_at`, `updated_at`, `expires_at`) VALUES
(1, NULL, 'db678527b768cb7b9df2d766d56d8c32', '2026-05-27 11:05:58', '2026-05-27 11:05:58', '2026-05-28 11:05:58'),
(2, 5, NULL, '2026-06-07 15:57:05', '2026-06-07 15:57:05', NULL),
(3, NULL, '402523fa53aa7328c0032e2c9ec88a45', '2026-06-24 10:21:29', '2026-06-24 10:21:29', '2026-06-25 10:21:29');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price_snapshot` decimal(10,2) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `quantity`, `price_snapshot`, `added_at`, `updated_at`) VALUES
(1, 1, 3, 1.00, 12.90, '2026-05-27 11:05:58', '2026-05-27 11:05:58'),
(7, 3, 19, 1.00, 8.90, '2026-06-24 10:21:29', '2026-06-24 10:21:29'),
(21, 2, 65, 1.00, 5.50, '2026-07-17 02:15:48', '2026-07-17 02:15:48');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `default_shelf_life_days` int(11) NOT NULL DEFAULT 7,
  `decay_exponent` decimal(3,2) NOT NULL DEFAULT 1.00,
  `decay_rationale` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `default_shelf_life_days`, `decay_exponent`, `decay_rationale`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Vegetables', 'vegetables', 'Fresh local and imported vegetables', 'carrot', 7, 1.50, 'Mixed transpiration + respiration', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(2, 'Fruits', 'fruits', 'Seasonal Malaysian and imported fruits', 'apple', 10, 1.10, 'Slow respiration; hardy varieties last longer', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(3, 'Dairy', 'dairy', 'Milk, yoghurt, cheese, and butter', 'milk', 14, 1.30, 'Refrigerated; microbial growth controlled', 3, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(4, 'Meat', 'meat', 'Fresh halal chicken, beef, and lamb', 'beef', 5, 2.30, 'Microbial growth + lipid oxidation', 4, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(5, 'Seafood', 'seafood', 'Fresh and frozen fish and shellfish', 'fish', 4, 2.50, 'Rapid bacterial growth + trimethylamine accumulation', 5, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(6, 'Bakery', 'bakery', 'Fresh bread, pastries, and baked goods', 'cookie', 3, 2.00, 'Staling follows Avrami kinetics; texture degrades fast', 6, 0, '2026-05-27 11:03:29', '2026-07-16 20:23:14'),
(7, 'Eggs & Tofu', 'eggs-tofu', 'Eggs, tofu, and soy products', 'egg', 14, 1.00, 'Near-linear decay under refrigeration', 7, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(8, 'Herbs & Spice', 'herbs-spice', 'Fresh herbs, ginger, garlic, chillies', 'leaf', 10, 1.80, 'Wilting via transpiration is exponential', 8, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `freshness_config`
--

CREATE TABLE `freshness_config` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level_name` enum('VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE') NOT NULL,
  `min_percent` decimal(5,2) NOT NULL,
  `max_percent` decimal(5,2) NOT NULL,
  `color_hex` varchar(7) NOT NULL,
  `label_en` varchar(50) NOT NULL,
  `auto_discount_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `alert_retailer` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `freshness_config`
--

INSERT INTO `freshness_config` (`id`, `level_name`, `min_percent`, `max_percent`, `color_hex`, `label_en`, `auto_discount_pct`, `alert_retailer`, `display_order`, `updated_at`) VALUES
(1, 'VERY_FRESH', 75.00, 100.00, '#4a5a3a', 'Very Fresh', 0.00, 0, 1, '2026-05-27 11:03:29'),
(2, 'FRESH', 50.00, 74.99, '#7a8467', 'Fresh', 0.00, 0, 2, '2026-05-27 11:03:29'),
(3, 'ENJOY_SOON', 25.00, 49.99, '#c9a55a', 'Enjoy Soon', 0.00, 1, 3, '2026-05-27 11:03:29'),
(4, 'LAST_CHANCE', 0.01, 24.99, '#b85c38', 'Last Chance', 15.00, 1, 4, '2026-05-27 11:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stock_batch_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_type` enum('RESTOCK','SOLD','EXPIRED','DAMAGED','ADJUSTMENT','RETURNED','RECALLED') NOT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `quantity_after` decimal(10,2) NOT NULL,
  `related_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `stock_batch_id`, `user_id`, `movement_type`, `quantity_change`, `quantity_after`, `related_order_id`, `reason`, `created_at`) VALUES
(1, 1, 2, 'RESTOCK', 50.00, 50.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(2, 2, 2, 'RESTOCK', 40.00, 35.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(3, 3, 2, 'RESTOCK', 20.00, 18.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(4, 4, 2, 'RESTOCK', 60.00, 60.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(5, 5, 2, 'RESTOCK', 30.00, 28.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(6, 6, 2, 'RESTOCK', 30.00, 25.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(7, 7, 2, 'RESTOCK', 100.00, 100.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(8, 8, 2, 'RESTOCK', 40.00, 35.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(9, 9, 2, 'RESTOCK', 60.00, 55.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(10, 10, 2, 'RESTOCK', 40.00, 40.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(11, 11, 2, 'RESTOCK', 25.00, 25.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(12, 12, 2, 'RESTOCK', 15.00, 12.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(13, 13, 2, 'RESTOCK', 20.00, 18.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(14, 14, 2, 'RESTOCK', 12.00, 10.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(17, 17, 2, 'RESTOCK', 100.00, 100.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(18, 18, 2, 'RESTOCK', 25.00, 22.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(19, 19, 2, 'RESTOCK', 50.00, 50.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(20, 20, 2, 'RESTOCK', 50.00, 50.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(21, 21, 2, 'RESTOCK', 25.00, 25.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(22, 22, 2, 'RESTOCK', 30.00, 30.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(23, 23, 2, 'RESTOCK', 15.00, 15.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(24, 24, 2, 'RESTOCK', 50.00, 50.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(25, 25, 2, 'RESTOCK', 20.00, 20.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(26, 26, 2, 'RESTOCK', 15.00, 15.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(27, 27, 2, 'RESTOCK', 25.00, 25.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(28, 28, 2, 'RESTOCK', 18.00, 18.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(31, 31, 2, 'RESTOCK', 30.00, 30.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(32, 32, 2, 'RESTOCK', 40.00, 40.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(33, 33, 2, 'RESTOCK', 40.00, 40.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(34, 34, 2, 'RESTOCK', 20.00, 20.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(35, 35, 2, 'RESTOCK', 30.00, 30.00, NULL, 'Initial stock from seed', '2026-05-27 11:03:29'),
(36, 19, 5, 'SOLD', -1.00, 49.00, 1, 'Order #1 fulfilled from batch GAR-B001', '2026-06-07 15:57:36'),
(37, 19, 5, 'SOLD', -1.00, 48.00, 2, 'Order #2 fulfilled from batch GAR-B001', '2026-06-11 04:45:06'),
(38, 11, 5, 'SOLD', -1.00, 24.00, 2, 'Order #2 fulfilled from batch CKB-B001', '2026-06-11 04:45:06'),
(39, 6, 5, 'SOLD', -1.00, 24.00, 2, 'Order #2 fulfilled from batch MAN-B001', '2026-06-11 04:45:06'),
(40, 25, 2, 'ADJUSTMENT', 1.00, 21.00, NULL, 'too low for the price', '2026-06-11 04:53:37'),
(41, 19, 5, 'SOLD', -1.00, 47.00, 3, 'Order #3 fulfilled from batch GAR-B001', '2026-06-24 10:21:43'),
(42, 19, 5, 'SOLD', -1.00, 46.00, 4, 'Order #4 fulfilled from batch GAR-B001', '2026-06-24 19:36:23'),
(43, 9, 5, 'SOLD', -1.00, 54.00, 5, 'Order #5 fulfilled from batch MLK-B001', '2026-06-29 20:41:10'),
(44, 6, 5, 'SOLD', -1.00, 23.00, 6, 'Order #6 fulfilled from batch MAN-B001', '2026-07-03 15:08:19'),
(45, 6, 5, 'SOLD', -1.00, 22.00, 7, 'Order #7 fulfilled from batch MAN-B001', '2026-07-05 08:27:05'),
(46, 1, 5, 'SOLD', -1.00, 49.00, 8, 'Order #8 fulfilled from batch LET-B001', '2026-07-05 10:14:10'),
(47, 8, 5, 'SOLD', -1.00, 34.00, 9, 'Order #9 fulfilled from batch BAN-B001', '2026-07-05 10:15:24'),
(48, 21, 5, 'SOLD', -1.00, 24.00, 10, 'Order #10 fulfilled from batch CUC-B001', '2026-07-09 08:56:54'),
(49, 3, 5, 'SOLD', -1.00, 17.00, 10, 'Order #10 fulfilled from batch TOM-B001', '2026-07-09 08:56:54'),
(50, 11, 5, 'SOLD', -1.00, 23.00, 10, 'Order #10 fulfilled from batch CKB-B001', '2026-07-09 08:56:54'),
(51, 8, 5, 'SOLD', -2.00, 32.00, 10, 'Order #10 fulfilled from batch BAN-B001', '2026-07-09 08:56:54'),
(54, 8, 5, 'RETURNED', 1.00, 33.00, 9, 'Order #9 cancelled by customer', '2026-07-17 02:15:16');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('ORDER_UPDATE','EXPIRY_ALERT','APPROVAL','REVIEW_REPLY','PROMO','SYSTEM') NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link`, `is_read`, `read_at`, `created_at`) VALUES
(1, 4, 'APPROVAL', 'Account APPROVED', 'Welcome! Your retailer account has been approved. You can now log in and start selling.', NULL, 0, NULL, '2026-05-28 19:36:13'),
(2, 5, 'ORDER_UPDATE', 'Order FM-20260607-7367 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=1', 1, '2026-07-05 08:29:42', '2026-06-07 15:57:36'),
(3, 5, 'ORDER_UPDATE', 'Order FM-20260611-0763 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=2', 1, '2026-07-05 08:29:42', '2026-06-11 04:45:06'),
(4, 5, 'ORDER_UPDATE', 'Order is now PROCESSING', 'Your order status has been updated to PROCESSING.', '/shop/orders.php?id=2', 1, '2026-07-05 08:29:42', '2026-06-11 04:54:14'),
(5, 5, 'ORDER_UPDATE', 'Order is now QUALITY_CHECK', 'Your order status has been updated to QUALITY_CHECK.', '/shop/orders.php?id=2', 1, '2026-07-05 08:29:42', '2026-06-11 04:54:17'),
(6, 5, 'ORDER_UPDATE', 'Order is now PACKED', 'Your order status has been updated to PACKED.', '/shop/orders.php?id=2', 1, '2026-07-05 08:29:42', '2026-06-11 04:54:20'),
(7, 5, 'ORDER_UPDATE', 'Order FM-20260624-0115 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=3', 1, '2026-07-05 08:29:42', '2026-06-24 10:21:43'),
(8, 5, 'ORDER_UPDATE', 'Order FM-20260625-3036 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=4', 1, '2026-07-05 08:29:42', '2026-06-24 19:36:23'),
(9, 5, 'ORDER_UPDATE', 'Order FM-20260630-1470 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=5', 1, '2026-07-05 08:29:42', '2026-06-29 20:41:10'),
(10, 5, 'ORDER_UPDATE', 'Order FM-20260703-5186 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=6', 1, '2026-07-05 08:29:42', '2026-07-03 15:08:19'),
(11, 5, 'ORDER_UPDATE', 'Order is now DELIVERED', 'Your order status was updated to DELIVERED.', '/shop/orders.php?id=6', 1, '2026-07-05 08:29:42', '2026-07-05 08:07:27'),
(12, 5, 'ORDER_UPDATE', 'Order FM-20260705-3514 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=7', 1, '2026-07-05 08:29:42', '2026-07-05 08:27:05'),
(13, 5, 'ORDER_UPDATE', 'Order FM-20260705-2318 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=8', 1, '2026-07-09 08:51:45', '2026-07-05 10:14:10'),
(14, 5, 'ORDER_UPDATE', 'Order FM-20260705-6175 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=9', 1, '2026-07-09 08:51:45', '2026-07-05 10:15:24'),
(15, 5, 'ORDER_UPDATE', 'Order FM-20260709-8822 placed!', 'Your order is now being processed. Estimated delivery in 2 days.', '/shop/orders.php?id=10', 1, '2026-07-09 08:57:48', '2026-07-09 08:56:54'),
(16, 2, 'ORDER_UPDATE', 'New refund request', 'Order FM-DEMO-0002 has a refund request awaiting your review.', '/retailer/refunds.php', 0, NULL, '2026-07-16 21:31:59'),
(17, 2, 'ORDER_UPDATE', 'Order cancelled', 'Order FM-20260705-6175 was cancelled by the customer and stock returned.', '/retailer/orders.php', 0, NULL, '2026-07-17 02:15:16');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `shipping_address_id` bigint(20) UNSIGNED NOT NULL,
  `preferred_delivery_date` date DEFAULT NULL,
  `billing_address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `promo_code_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `retailer_payout` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PLACED',
  `notes` text DEFAULT NULL,
  `placed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `shipping_address_id`, `preferred_delivery_date`, `billing_address_id`, `promo_code_id`, `subtotal`, `discount_amount`, `shipping_fee`, `tax_amount`, `total`, `commission_rate`, `commission_amount`, `retailer_payout`, `status`, `notes`, `placed_at`, `updated_at`) VALUES
(1, 'FM-20260607-7367', 5, 2, '2026-06-08', 2, NULL, 8.90, 0.00, 5.00, 0.00, 13.90, 10.00, 0.89, 8.01, 'PLACED', NULL, '2026-06-07 15:57:36', '2026-07-16 21:07:50'),
(2, 'FM-20260611-0763', 5, 2, '2026-06-12', 2, 1, 50.70, 5.07, 0.00, 0.00, 45.63, 10.00, 4.56, 41.07, 'PACKED', NULL, '2026-06-11 04:45:06', '2026-07-16 21:07:50'),
(3, 'FM-20260624-0115', 5, 2, '2026-06-25', 2, NULL, 8.90, 0.00, 5.00, 0.00, 13.90, 10.00, 0.89, 8.01, 'PLACED', NULL, '2026-06-24 10:21:43', '2026-07-16 21:07:50'),
(4, 'FM-20260625-3036', 5, 2, '2026-06-26', 2, NULL, 8.90, 0.00, 5.00, 0.00, 13.90, 10.00, 0.89, 8.01, 'PLACED', NULL, '2026-06-24 19:36:23', '2026-07-16 21:07:50'),
(5, 'FM-20260630-1470', 5, 2, '2026-07-01', 2, NULL, 7.50, 0.00, 5.00, 0.00, 12.50, 10.00, 0.75, 6.75, 'PLACED', NULL, '2026-06-29 20:41:10', '2026-07-16 21:07:50'),
(6, 'FM-20260703-5186', 5, 2, '2026-07-04', 2, NULL, 18.90, 0.00, 5.00, 0.00, 23.90, 10.00, 1.89, 17.01, 'DELIVERED', NULL, '2026-07-03 15:08:19', '2026-07-16 21:07:50'),
(7, 'FM-20260705-3514', 5, 2, '2026-07-06', 2, NULL, 18.90, 0.00, 5.00, 0.00, 23.90, 10.00, 1.89, 17.01, 'PLACED', NULL, '2026-07-05 08:27:05', '2026-07-16 21:07:50'),
(8, 'FM-20260705-2318', 5, 2, '2026-07-06', 2, NULL, 4.90, 0.00, 5.00, 0.00, 9.90, 10.00, 0.49, 4.41, 'PLACED', NULL, '2026-07-05 10:14:10', '2026-07-16 21:07:50'),
(9, 'FM-20260705-6175', 5, 2, '2026-07-06', 2, NULL, 6.50, 0.00, 5.00, 0.00, 11.50, 10.00, 0.00, 0.00, 'CANCELLED', '\n[Cancelled by customer: i take the wrong order]', '2026-07-05 10:15:24', '2026-07-17 02:15:16'),
(10, 'FM-20260709-8822', 5, 2, '2026-07-12', 2, NULL, 56.70, 0.00, 0.00, 0.00, 56.70, 10.00, 5.67, 51.03, 'PLACED', NULL, '2026-07-09 08:56:54', '2026-07-16 21:07:50'),
(11, 'FM-DEMO-0001', 7, 3, '2026-07-12', NULL, NULL, 18.90, 0.00, 5.00, 0.00, 23.90, 10.00, 1.89, 17.01, 'DELIVERED', NULL, '2026-07-10 16:00:00', '2026-07-16 21:07:50'),
(12, 'FM-DEMO-0002', 8, 4, '2026-07-13', NULL, NULL, 4.90, 0.00, 5.00, 0.00, 9.90, 10.00, 0.49, 4.41, 'DELIVERED', NULL, '2026-07-11 16:00:00', '2026-07-16 21:07:50'),
(13, 'FM-DEMO-0003', 9, 5, '2026-07-10', NULL, NULL, 88.00, 0.00, 5.00, 0.00, 93.00, 10.00, 8.80, 79.20, 'DELIVERED', NULL, '2026-07-08 16:00:00', '2026-07-16 21:07:50'),
(14, 'FM-DEMO-0004', 10, 6, '2026-07-14', NULL, NULL, 12.90, 0.00, 5.00, 0.00, 17.90, 10.00, 1.29, 11.61, 'DELIVERED', NULL, '2026-07-12 16:00:00', '2026-07-16 21:07:50'),
(15, 'FM-DEMO-0005', 11, 7, '2026-07-11', NULL, NULL, 15.90, 0.00, 5.00, 0.00, 20.90, 10.00, 1.59, 14.31, 'DELIVERED', NULL, '2026-07-09 16:00:00', '2026-07-16 21:07:50'),
(16, 'FM-DEMO-0006', 12, 8, '2026-07-15', NULL, NULL, 22.90, 0.00, 5.00, 0.00, 27.90, 10.00, 2.29, 20.61, 'DELIVERED', NULL, '2026-07-13 16:00:00', '2026-07-16 21:07:50'),
(17, 'FM-DEMO-0007', 3, 1, '2026-07-08', NULL, NULL, 6.50, 0.00, 5.00, 0.00, 11.50, 10.00, 0.65, 5.85, 'DELIVERED', NULL, '2026-07-06 16:00:00', '2026-07-16 21:07:50'),
(18, 'FM-DEMO-0008', 3, 1, '2026-07-06', NULL, NULL, 7.50, 0.00, 5.00, 0.00, 12.50, 10.00, 0.75, 6.75, 'DELIVERED', NULL, '2026-07-04 16:00:00', '2026-07-16 21:07:50');

-- --------------------------------------------------------

--
-- Table structure for table `order_history`
--

CREATE TABLE `order_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_history`
--

INSERT INTO `order_history` (`id`, `order_id`, `previous_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
(1, 1, NULL, 'PLACED', 5, 'Order placed', '2026-06-07 15:57:36'),
(2, 2, NULL, 'PLACED', 5, 'Order placed', '2026-06-11 04:45:06'),
(3, 2, 'PLACED', 'PROCESSING', 2, 'Updated by retailer', '2026-06-11 04:54:14'),
(4, 2, 'PROCESSING', 'QUALITY_CHECK', 2, 'Updated by retailer', '2026-06-11 04:54:17'),
(5, 2, 'QUALITY_CHECK', 'PACKED', 2, 'Updated by retailer', '2026-06-11 04:54:20'),
(6, 3, NULL, 'PLACED', 5, 'Order placed', '2026-06-24 10:21:43'),
(7, 4, NULL, 'PLACED', 5, 'Order placed', '2026-06-24 19:36:23'),
(8, 5, NULL, 'PLACED', 5, 'Order placed', '2026-06-29 20:41:10'),
(9, 6, NULL, 'PLACED', 5, 'Order placed', '2026-07-03 15:08:19'),
(10, 6, 'PLACED', 'DELIVERED', 1, 'Changed by admin', '2026-07-05 08:07:27'),
(11, 7, NULL, 'PLACED', 5, 'Order placed', '2026-07-05 08:27:05'),
(12, 8, NULL, 'PLACED', 5, 'Order placed', '2026-07-05 10:14:10'),
(13, 9, NULL, 'PLACED', 5, 'Order placed', '2026-07-05 10:15:24'),
(14, 10, NULL, 'PLACED', 5, 'Order placed', '2026-07-09 08:56:54');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `stock_batch_id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `freshness_at_order` enum('VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE') NOT NULL,
  `expiry_at_order` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `stock_batch_id`, `product_name`, `quantity`, `unit_price`, `subtotal`, `freshness_at_order`, `expiry_at_order`) VALUES
(1, 1, 19, 19, 'Local Garlic (250g)', 1.00, 8.90, 8.90, 'ENJOY_SOON', '2026-06-26'),
(2, 2, 19, 19, 'Local Garlic (250g)', 1.00, 8.90, 8.90, 'VERY_FRESH', '2026-07-11'),
(3, 2, 11, 11, 'Free-Range Chicken Breast', 1.00, 22.90, 22.90, 'VERY_FRESH', '2026-06-16'),
(4, 2, 6, 6, 'Harum Manis Mango', 1.00, 16.07, 16.07, 'LAST_CHANCE', '2026-06-12'),
(5, 3, 19, 19, 'Local Garlic (250g)', 1.00, 8.90, 8.90, 'ENJOY_SOON', '2026-07-11'),
(6, 4, 19, 19, 'Local Garlic (250g)', 1.00, 8.90, 8.90, 'ENJOY_SOON', '2026-06-28'),
(7, 5, 9, 9, 'Fresh Full Cream Milk 1L', 1.00, 7.50, 7.50, 'ENJOY_SOON', '2026-07-07'),
(8, 6, 6, 6, 'Harum Manis Mango', 1.00, 18.90, 18.90, 'VERY_FRESH', '2026-07-21'),
(9, 7, 6, 6, 'Harum Manis Mango', 1.00, 18.90, 18.90, 'VERY_FRESH', '2026-07-21'),
(10, 8, 1, 1, 'Butterhead Lettuce', 1.00, 4.90, 4.90, 'FRESH', '2026-07-19'),
(11, 9, 8, 8, 'Pisang Berangan', 1.00, 6.50, 6.50, 'VERY_FRESH', '2026-07-23'),
(12, 10, 21, 21, 'Japanese Cucumber', 1.00, 7.90, 7.90, 'ENJOY_SOON', '2026-07-21'),
(13, 10, 3, 3, 'Cherry Tomatoes', 1.00, 12.90, 12.90, 'ENJOY_SOON', '2026-07-19'),
(14, 10, 11, 11, 'Free-Range Chicken Breast', 1.00, 22.90, 22.90, 'ENJOY_SOON', '2026-07-20'),
(15, 10, 8, 8, 'Pisang Berangan', 2.00, 6.50, 13.00, 'FRESH', '2026-07-23'),
(16, 11, 6, 6, 'Harum Manis Mango', 1.00, 18.90, 18.90, 'FRESH', '2026-07-28'),
(17, 12, 1, 1, 'Butterhead Lettuce', 1.00, 4.90, 4.90, 'FRESH', '2026-07-17'),
(18, 13, 13, 13, 'Atlantic Salmon Fillet', 1.00, 88.00, 88.00, 'FRESH', '2026-07-24'),
(19, 14, 3, 3, 'Cherry Tomatoes', 1.00, 12.90, 12.90, 'FRESH', '2026-07-19'),
(21, 16, 11, 11, 'Free-Range Chicken Breast', 1.00, 22.90, 22.90, 'FRESH', '2026-08-02'),
(22, 17, 8, 8, 'Pisang Berangan', 1.00, 6.50, 6.50, 'FRESH', '2026-08-04'),
(23, 18, 9, 9, 'Fresh Full Cream Milk 1L', 1.00, 7.50, 7.50, 'FRESH', '2026-08-04'),
(31, 15, 4, 4, 'Cameron Carrots', 1.00, 5.50, 5.50, 'FRESH', '2026-07-28');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method` enum('FPX','CREDIT_CARD','EWALLET','BANK_TRANSFER','COD') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `payment_method`, `amount`, `status`, `transaction_ref`, `paid_at`, `created_at`) VALUES
(1, 1, 'FPX', 13.90, 'SUCCESS', 'SIM-6C31FD869A40', '2026-06-07 15:57:36', '2026-06-07 15:57:36'),
(2, 2, 'FPX', 45.63, 'SUCCESS', 'SIM-269821FDDCC3', '2026-06-11 04:45:06', '2026-06-11 04:45:06'),
(3, 3, 'FPX', 13.90, 'SUCCESS', 'SIM-103C971277D4', '2026-06-24 10:21:43', '2026-06-24 10:21:43'),
(4, 4, 'FPX', 13.90, 'SUCCESS', 'SIM-02477F0C7346', '2026-06-24 19:36:23', '2026-06-24 19:36:23'),
(5, 5, 'FPX', 12.50, 'SUCCESS', 'SIM-70F262B80203', '2026-06-29 20:41:10', '2026-06-29 20:41:10'),
(6, 6, 'FPX', 23.90, 'SUCCESS', 'SIM-854C261AC17E', '2026-07-03 15:08:19', '2026-07-03 15:08:19'),
(7, 7, 'FPX', 23.90, 'SUCCESS', 'SIM-FD727DC813E3', '2026-07-05 08:27:05', '2026-07-05 08:27:05'),
(8, 8, 'FPX', 9.90, 'SUCCESS', 'SIM-EA24A142DE13', '2026-07-05 10:14:10', '2026-07-05 10:14:10'),
(9, 9, 'FPX', 11.50, 'REFUNDED', 'SIM-EED6F5A0515D', '2026-07-05 10:15:24', '2026-07-05 10:15:24'),
(10, 10, 'EWALLET', 56.70, 'SUCCESS', 'SIM-E3E15BCF0866', '2026-07-09 08:56:54', '2026-07-09 08:56:54'),
(11, 11, 'FPX', 23.90, 'SUCCESS', 'SIM-40B861', '2026-07-10 16:00:00', '2026-07-16 20:10:35'),
(12, 12, 'FPX', 9.90, 'SUCCESS', 'SIM-518FF3', '2026-07-11 16:00:00', '2026-07-16 20:10:35'),
(13, 13, 'FPX', 93.00, 'SUCCESS', 'SIM-EE6086', '2026-07-08 16:00:00', '2026-07-16 20:10:35'),
(14, 14, 'FPX', 17.90, 'SUCCESS', 'SIM-3149D7', '2026-07-12 16:00:00', '2026-07-16 20:10:35'),
(15, 15, 'FPX', 20.90, 'SUCCESS', 'SIM-2AA8D0', '2026-07-09 16:00:00', '2026-07-16 20:10:35'),
(16, 16, 'FPX', 27.90, 'SUCCESS', 'SIM-D70E29', '2026-07-13 16:00:00', '2026-07-16 20:10:35'),
(17, 17, 'FPX', 11.50, 'SUCCESS', 'SIM-7E0988', '2026-07-06 16:00:00', '2026-07-16 20:10:35'),
(18, 18, 'FPX', 12.50, 'SUCCESS', 'SIM-3C182D', '2026-07-04 16:00:00', '2026-07-16 20:10:35');

-- --------------------------------------------------------

--
-- Table structure for table `platform_statistics`
--

CREATE TABLE `platform_statistics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stat_date` date NOT NULL,
  `total_orders` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_customers_active` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `new_signups` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `products_expired` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `products_last_chance` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `waste_kg_saved` decimal(10,2) NOT NULL DEFAULT 0.00,
  `avg_order_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `retailer_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `subcategory_id` bigint(20) UNSIGNED DEFAULT NULL,
  `unit_type_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(280) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `shelf_life_days` int(11) DEFAULT NULL,
  `decay_exponent_override` decimal(3,2) DEFAULT NULL,
  `min_order_qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `low_stock_threshold` decimal(10,2) NOT NULL DEFAULT 10.00,
  `origin` varchar(100) DEFAULT NULL,
  `storage_instruction` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `view_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `retailer_id`, `category_id`, `subcategory_id`, `unit_type_id`, `sku`, `name`, `slug`, `description`, `base_price`, `shelf_life_days`, `decay_exponent_override`, `min_order_qty`, `low_stock_threshold`, `origin`, `storage_instruction`, `is_active`, `is_featured`, `view_count`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 1, 'VEG-LET-001', 'Butterhead Lettuce', 'butterhead-lettuce', 'Crisp local butterhead lettuce, ideal for salads and wraps. Grown pesticide-free in Cameron Highlands.', 6.90, 5, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate at 1-4°C. Keep in plastic bag.', 1, 1, 14, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(2, 1, 1, 1, 1, 'VEG-BOK-002', 'Baby Bok Choy', 'baby-bok-choy', 'Tender baby bok choy, perfect for stir-frying with garlic. Local highland variety.', 4.50, 5, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate. Wash before use.', 1, 0, 1, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(3, 1, 1, 3, 1, 'VEG-TOM-003', 'Cherry Tomatoes', 'cherry-tomatoes', 'Sweet cherry tomatoes, vine-ripened. Great for salads and snacking.', 9.90, 7, NULL, 0.50, 10.00, 'Cameron Highlands', 'Store at room temperature until ripe.', 1, 1, 7, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(4, 1, 1, 2, 1, 'VEG-CAR-004', 'Cameron Carrots', 'cameron-carrots', 'Sweet orange carrots straight from Cameron Highlands. Earthy flavour, crunchy texture.', 4.50, 14, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate in crisper drawer.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(5, 1, 1, 1, 1, 'VEG-SPI-005', 'Baby Spinach', 'baby-spinach', 'Tender young spinach leaves, washed and ready to eat.', 5.90, 5, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate. Use within 5 days.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(6, 1, 2, 4, 1, 'FRU-MAN-001', 'Harum Manis Mango', 'harum-manis-mango', 'Sweet Malaysian Harum Manis mangoes. Aromatic with smooth, fibre-free flesh.', 16.90, 7, NULL, 0.50, 10.00, 'Perlis', 'Ripen at room temperature, refrigerate when ripe.', 1, 1, 11, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(7, 1, 2, 5, 1, 'FRU-APP-002', 'Royal Gala Apples', 'royal-gala-apples', 'Sweet and crispy Royal Gala apples imported from New Zealand.', 9.90, 21, NULL, 0.50, 10.00, 'New Zealand', 'Refrigerate for longer shelf life.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:23:21', NULL),
(8, 1, 2, 4, 1, 'FRU-BAN-003', 'Pisang Berangan', 'pisang-berangan', 'Sweet local bananas, perfect for snacking or smoothies.', 6.50, 7, NULL, 0.50, 10.00, 'Pahang', 'Store at room temperature.', 1, 1, 10, '2026-05-27 11:03:29', '2026-07-16 20:27:50', NULL),
(9, 1, 3, 6, 8, 'DAI-MLK-001', 'Fresh Full Cream Milk 1L', 'fresh-full-cream-milk-1l', 'Locally produced fresh full cream milk from grass-fed cows.', 7.50, 10, NULL, 1.00, 10.00, 'Selangor', 'Keep refrigerated below 4°C.', 1, 1, 2, '2026-05-27 11:03:29', '2026-07-05 08:38:07', NULL),
(10, 1, 3, 8, 3, 'DAI-YOG-002', 'Greek Yogurt 500g', 'greek-yogurt-500g', 'Thick and creamy Greek-style yogurt, plain unsweetened.', 11.90, 14, NULL, 1.00, 10.00, 'Selangor', 'Keep refrigerated. Best before printed date.', 1, 0, 2, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(11, 1, 4, 9, 1, 'MET-CKB-001', 'Free-Range Chicken Breast', 'free-range-chicken-breast', 'Halal-certified free-range chicken breast. No hormones, no antibiotics.', 18.90, 5, NULL, 0.50, 10.00, 'Johor', 'Keep refrigerated below 4°C. Cook within 5 days.', 1, 1, 4, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(12, 1, 4, 10, 1, 'MET-BEF-002', 'Australian Beef Striploin', 'australian-beef-striploin', 'Premium Australian beef striploin, marbled and tender.', 62.00, 7, NULL, 0.50, 10.00, 'Australia', 'Keep refrigerated below 4°C. Freeze if not used within 5 days.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(13, 1, 5, 11, 1, 'SEA-SAL-001', 'Atlantic Salmon Fillet', 'atlantic-salmon-fillet', 'Fresh Norwegian Atlantic salmon fillet, skin-on, pin-bones removed.', 78.00, 3, NULL, 0.50, 10.00, 'Norway', 'Keep refrigerated on ice. Use within 3 days.', 1, 1, 4, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(14, 1, 5, 12, 1, 'SEA-PRA-002', 'Tiger Prawns (Medium)', 'tiger-prawns-medium', 'Fresh local tiger prawns, head-on. Sweet and meaty.', 42.00, 3, NULL, 0.50, 10.00, 'Sabah', 'Keep on ice. Cook within 2 days.', 1, 0, 1, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(17, 1, 7, NULL, 6, 'EGG-CHK-001', 'Grade A Chicken Eggs (Dozen)', 'grade-a-chicken-eggs', 'Fresh Grade A chicken eggs from local farms.', 11.90, 21, NULL, 1.00, 10.00, 'Johor', 'Refrigerate immediately. Use within 3 weeks.', 1, 0, 2, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(18, 1, 8, 15, 5, 'HRB-COR-001', 'Fresh Coriander (Bunch)', 'fresh-coriander-bunch', 'Bright, aromatic fresh coriander leaves. Perfect for curries and garnish.', 2.50, 5, NULL, 1.00, 10.00, 'Cameron Highlands', 'Refrigerate with stems in water.', 1, 0, 0, '2026-05-27 11:03:29', '2026-05-27 11:03:29', NULL),
(19, 1, 8, 16, 2, 'HRB-GAR-002', 'Local Garlic (250g)', 'local-garlic-250g', 'Pungent fresh garlic bulbs. Essential aromatic for Malaysian cooking.', 6.90, 30, NULL, 100.00, 10.00, 'Cameron Highlands', 'Store in cool dry place.', 1, 1, 6, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(20, 1, 1, 2, 1, 'VEG-POT-006', 'Cameron Potatoes', 'cameron-potatoes', 'Locally grown potatoes from Cameron Highlands. All-purpose variety.', 5.50, 21, NULL, 0.50, 10.00, 'Cameron Highlands', 'Store in cool, dark place.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(21, 1, 1, 3, 1, 'VEG-CUC-007', 'Japanese Cucumber', 'japanese-cucumber', 'Crisp, mild Japanese cucumbers. Great for salads.', 5.50, 10, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate in plastic bag.', 1, 0, 1, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(22, 1, 2, 5, 4, 'FRU-DRG-004', 'Honeydew Melon', 'honeydew-melon', 'Sweet, juicy honeydew melon. Refreshing and naturally hydrating.', 8.90, 10, NULL, 1.00, 10.00, 'Australia', 'Refrigerate after cutting.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(23, 1, 3, 7, 3, 'DAI-CHE-003', 'Cheddar Cheese Block 250g', 'cheddar-cheese-block', 'Aged cheddar with rich, sharp flavour.', 19.90, 30, NULL, 1.00, 10.00, 'New Zealand', 'Refrigerate. Wrap tightly after opening.', 0, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', '2026-07-09 08:53:52'),
(24, 1, 3, 6, 8, 'DAI-MLK-004', 'Low Fat Milk 1L', 'low-fat-milk', 'Fresh pasteurised low-fat milk. Same taste, less fat.', 6.90, 7, NULL, 1.00, 10.00, 'Selangor', 'Keep refrigerated below 4 degrees.', 1, 0, 0, '2026-05-27 11:03:29', '2026-05-27 11:03:29', NULL),
(25, 1, 4, 9, 1, 'MET-CKT-003', 'Free-Range Chicken Thigh', 'free-range-chicken-thigh', 'Juicy free-range chicken thigh, perfect for grilling and stewing.', 14.90, 4, NULL, 0.50, 10.00, 'Johor', 'Refrigerate. Cook within 2 days.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(26, 1, 4, 10, 3, 'MET-BEF-004', 'Beef Minced 500g', 'beef-minced', 'Fresh Australian beef minced, ideal for bolognese and burgers.', 32.90, 3, NULL, 1.00, 10.00, 'Australia', 'Refrigerate. Cook thoroughly.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:42', NULL),
(27, 1, 5, 11, 1, 'SEA-MAC-003', 'Fresh Mackerel', 'fresh-mackerel', 'Whole fresh mackerel from local waters. Rich in omega-3.', 16.90, 2, NULL, 0.50, 10.00, 'Pulau Pangkor', 'Keep on ice. Cook same day.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(28, 1, 5, 12, 1, 'SEA-SQD-004', 'Squid (Sotong)', 'squid-sotong', 'Fresh squid, perfect for stir-fries and grilling.', 28.00, 2, NULL, 0.50, 10.00, 'Terengganu', 'Keep refrigerated. Clean before cooking.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(31, 1, 7, NULL, 6, 'EGG-OMG-002', 'Omega-3 Chicken Eggs (Dozen)', 'omega-3-chicken-eggs', 'Premium omega-3 enriched eggs from grain-fed hens.', 16.90, 21, NULL, 1.00, 10.00, 'Johor', 'Refrigerate immediately.', 1, 0, 1, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(32, 1, 7, NULL, 3, 'TOF-SLK-003', 'Silken Tofu (300g)', 'silken-tofu', 'Smooth, custard-like silken tofu.', 3.50, 14, NULL, 1.00, 10.00, 'Selangor', 'Keep refrigerated.', 1, 0, 0, '2026-05-27 11:03:29', '2026-05-27 11:03:29', NULL),
(33, 1, 7, NULL, 3, 'TOF-FRM-004', 'Firm Tofu (400g)', 'firm-tofu', 'Firm tofu perfect for stir-fries, grilling, and curries.', 4.20, 14, NULL, 1.00, 10.00, 'Selangor', 'Keep refrigerated.', 1, 0, 0, '2026-05-27 11:03:29', '2026-05-27 11:03:29', NULL),
(34, 1, 8, 16, 3, 'HRB-GIN-003', 'Fresh Ginger (200g)', 'fresh-ginger', 'Aromatic fresh ginger root for cooking and tea.', 4.90, 21, NULL, 1.00, 10.00, 'Cameron Highlands', 'Store in dry place.', 1, 0, 0, '2026-05-27 11:03:29', '2026-07-16 20:29:43', NULL),
(35, 1, 8, 15, 5, 'HRB-LMG-004', 'Lemongrass (Serai)', 'lemongrass-serai', 'Fresh lemongrass stalks. Essential for Malaysian curries and tom yam.', 3.50, 14, NULL, 1.00, 10.00, 'Cameron Highlands', 'Refrigerate or freeze.', 1, 0, 0, '2026-05-27 11:03:29', '2026-05-27 11:03:29', NULL),
(36, 1, 1, 1, 1, 'VEG-KAI-008', 'Kai Lan (Chinese Broccoli)', 'kai-lan', 'Crisp Chinese broccoli with tender stems and dark leaves. Great for stir-fry with oyster sauce.', 5.50, 5, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate at 1-4°C.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:29:42', NULL),
(37, 1, 1, 3, 1, 'VEG-BRJ-009', 'Brinjal (Terung)', 'brinjal', 'Glossy purple brinjal, firm and fresh. Perfect for sambal terung or curries.', 5.80, 8, NULL, 0.50, 10.00, 'Johor', 'Store in a cool, dry place.', 1, 0, 1, '2026-07-16 15:29:59', '2026-07-16 20:23:21', NULL),
(38, 1, 1, 2, 1, 'VEG-SWP-010', 'Sweet Potato (Keledek)', 'sweet-potato', 'Orange-fleshed sweet potato, naturally sweet. Great roasted, steamed, or in soups.', 4.50, 21, NULL, 0.50, 10.00, 'Pahang', 'Store in a cool, dark, ventilated place.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:23:21', NULL),
(39, 1, 1, 3, 1, 'VEG-CHL-011', 'Red Chillies (Cili Merah)', 'red-chillies', 'Fresh red chillies with a bold kick. Essential for sambal and Malaysian cooking.', 14.90, 10, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate in a dry container.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:29:42', NULL),
(40, 1, 1, 1, 1, 'VEG-CAB-012', 'Round Cabbage', 'round-cabbage', 'Firm round cabbage, crunchy and versatile. Good for stir-fry, soup, or coleslaw.', 3.90, 14, NULL, 0.50, 10.00, 'Cameron Highlands', 'Refrigerate in crisper drawer.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:23:21', NULL),
(41, 1, 2, 1, 4, 'FRU-PAP-005', 'Sekaki Papaya', 'sekaki-papaya', 'Sweet Malaysian Sekaki papaya, rich orange flesh. High in vitamin C. Approx. 1.2–1.8 kg each.', 6.50, 7, NULL, 1.00, 10.00, 'Johor', 'Ripen at room temperature, then refrigerate.', 1, 1, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(42, 1, 2, 1, 3, 'FRU-WTM-006', 'Seedless Watermelon', 'seedless-watermelon', 'Juicy seedless watermelon, crisp and refreshing. Sold per piece.', 12.00, 10, NULL, 1.00, 10.00, 'Terengganu', 'Refrigerate after cutting.', 1, 1, 2, '2026-07-16 15:29:59', '2026-07-17 02:15:30', NULL),
(43, 1, 2, 2, 3, 'FRU-ORG-007', 'Sunkist Oranges', 'sunkist-oranges', 'Imported Sunkist navel oranges, sweet and seedless. Perfect for juicing or snacking. Pack of about 6 oranges (approx. 1 kg).', 12.90, 21, NULL, 1.00, 10.00, 'USA', 'Refrigerate to extend freshness.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:29:42', NULL),
(44, 1, 2, 1, 4, 'FRU-PIN-008', 'Josapine Pineapple', 'josapine-pineapple', 'Fragrant Josapine pineapple, sweet with low acidity. A Malaysian favourite. Approx. 1.5–2 kg each.', 7.50, 7, NULL, 1.00, 10.00, 'Johor', 'Store at room temperature until ripe.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(45, 1, 2, 2, 3, 'FRU-GRP-009', 'Red Seedless Grapes', 'red-seedless-grapes', 'Sweet imported red seedless grapes. Crunchy and great for snacking. Pack of approx. 500 g.', 15.90, 10, NULL, 1.00, 10.00, 'Australia', 'Refrigerate. Wash before eating.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(46, 1, 3, 1, 3, 'DAI-BTR-005', 'Salted Butter Block 250g', 'salted-butter', 'Creamy salted butter, perfect for baking and spreading. New Zealand dairy. 250 g block.', 13.90, 30, NULL, 1.00, 10.00, 'New Zealand', 'Keep refrigerated.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(47, 1, 3, 3, 8, 'DAI-YOG-006', 'Strawberry Yogurt Drink 700g', 'strawberry-yogurt-drink', 'Smooth strawberry yogurt drink, probiotic-rich. A refreshing everyday treat. 700 g bottle.', 8.50, 21, NULL, 1.00, 10.00, 'Malaysia', 'Keep refrigerated. Shake well.', 1, 0, 1, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(48, 1, 3, 2, 3, 'DAI-MOZ-007', 'Mozzarella Cheese 200g', 'mozzarella-cheese', 'Stretchy mozzarella, ideal for pizza and baked dishes. 200 g pack.', 16.90, 30, NULL, 1.00, 10.00, 'Italy', 'Keep refrigerated. Use within 3 days of opening.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(49, 1, 4, 1, 1, 'MET-CKW-005', 'Chicken Whole Leg', 'chicken-whole-leg', 'Fresh halal chicken whole leg, juicy and versatile. Great for roasting or curry.', 11.90, 5, NULL, 0.50, 10.00, 'Malaysia', 'Keep refrigerated. Cook within 2 days.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:29:42', NULL),
(50, 1, 4, 2, 3, 'MET-LMB-006', 'Lamb Shoulder Cubes 500g', 'lamb-shoulder-cubes', 'Tender lamb shoulder cubes, halal. Perfect for curries and stews.', 42.90, 5, NULL, 1.00, 10.00, 'Australia', 'Keep refrigerated or freeze.', 1, 1, 1, '2026-07-16 15:29:59', '2026-07-17 02:15:27', NULL),
(51, 1, 4, 1, 3, 'MET-CKM-007', 'Chicken Minced 500g', 'chicken-minced', 'Fresh halal minced chicken, lean and versatile. For meatballs, patties, and more.', 13.90, 4, NULL, 1.00, 10.00, 'Malaysia', 'Keep refrigerated. Cook within 2 days.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:39:18', NULL),
(52, 1, 5, 1, 1, 'SEA-SIA-005', 'Siakap (Sea Bass) Whole', 'siakap-sea-bass', 'Fresh whole siakap (sea bass), cleaned and scaled. Sweet, firm flesh.', 32.90, 3, NULL, 0.50, 10.00, 'Malaysia', 'Keep on ice. Cook same day for best taste.', 1, 1, 1, '2026-07-16 15:29:59', '2026-07-16 20:29:43', NULL),
(53, 1, 5, 2, 4, 'SEA-CRB-006', 'Mud Crab (Ketam)', 'mud-crab', 'Live-caught mud crab, meaty and sweet. Perfect for chilli crab or steaming. Approx. 400–600 g each.', 38.90, 2, NULL, 1.00, 10.00, 'Malaysia', 'Keep cold. Cook same day.', 1, 0, 1, '2026-07-16 15:29:59', '2026-07-16 20:29:43', NULL),
(54, 1, 5, 1, 3, 'SEA-KMB-007', 'Kembung Fish 500g', 'kembung-fish', 'Fresh kembung (Indian mackerel), a Malaysian staple. Great fried or in curry.', 15.90, 3, NULL, 1.00, 10.00, 'Malaysia', 'Keep on ice. Cook within a day.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:39:18', NULL),
(55, 1, 5, 2, 3, 'SEA-CCK-008', 'Cockles (Kerang) 500g', 'cockles-kerang', 'Fresh cockles, essential for char kuey teow and laksa. Cleaned and ready.', 7.90, 2, NULL, 1.00, 10.00, 'Malaysia', 'Keep cold. Use same day.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:39:18', NULL),
(59, 1, 7, NULL, 7, 'EGG-QIL-005', 'Quail Eggs (20 pcs)', 'quail-eggs', 'Fresh quail eggs, delicate and nutritious. Great for snacks and garnishing. Box of 20 eggs.', 8.90, 21, NULL, 1.00, 10.00, 'Malaysia', 'Keep refrigerated.', 1, 0, 1, '2026-07-16 15:29:59', '2026-07-16 20:29:43', NULL),
(62, 1, 8, 1, 5, 'HRB-MNT-005', 'Fresh Mint Leaves (Pudina)', 'fresh-mint-leaves', 'Aromatic fresh mint, ideal for drinks, salads, and garnishing. Fresh bunch, approx. 100 g.', 3.50, 7, NULL, 1.00, 10.00, 'Cameron Highlands', 'Refrigerate wrapped in damp paper.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(63, 1, 8, 2, 3, 'HRB-TUR-006', 'Fresh Turmeric (Kunyit) 200g', 'fresh-turmeric', 'Fresh turmeric root, earthy and vibrant. A cornerstone of Malaysian spice. Approx. 200 g pack.', 4.90, 14, NULL, 1.00, 10.00, 'Malaysia', 'Store in a cool, dry place or refrigerate.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:29:43', NULL),
(64, 1, 8, 1, 5, 'HRB-CUR-007', 'Curry Leaves (Daun Kari)', 'curry-leaves', 'Fragrant fresh curry leaves, essential for dhal, curries, and tempering. Fresh bunch, approx. 50 g.', 2.90, 7, NULL, 1.00, 10.00, 'Malaysia', 'Refrigerate in an airtight bag.', 1, 0, 0, '2026-07-16 15:29:59', '2026-07-16 20:27:43', NULL),
(65, 1, 8, 2, 3, 'HRB-GLC-008', 'Galangal (Lengkuas) 200g', 'galangal', 'Fresh galangal, sharp and citrusy. Key aromatic for rendang and tom yum. Approx. 200 g pack.', 5.50, 14, NULL, 1.00, 10.00, 'Malaysia', 'Store in a cool, dry place.', 1, 0, 1, '2026-07-16 15:29:59', '2026-07-17 02:15:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `alt_text`, `is_primary`, `display_order`, `created_at`) VALUES
(1, 1, 'products/lettuce.jpg', 'Butterhead Lettuce', 1, 1, '2026-05-27 11:03:29'),
(2, 2, 'products/bokchoy.jpg', 'Baby Bok Choy', 1, 1, '2026-05-27 11:03:29'),
(3, 3, 'products/tomato.jpg', 'Cherry Tomatoes', 1, 1, '2026-05-27 11:03:29'),
(4, 4, 'products/carrot.jpg', 'Cameron Carrots', 1, 1, '2026-05-27 11:03:29'),
(5, 5, 'products/spinach.jpg', 'Baby Spinach', 1, 1, '2026-05-27 11:03:29'),
(6, 6, 'products/mango.jpg', 'Harum Manis Mango', 1, 1, '2026-05-27 11:03:29'),
(7, 7, 'products/apple.jpg', 'Royal Gala Apples', 1, 1, '2026-05-27 11:03:29'),
(8, 8, 'products/banana.jpg', 'Pisang Berangan', 1, 1, '2026-05-27 11:03:29'),
(9, 9, 'products/milk.jpg', 'Fresh Full Cream Milk', 1, 1, '2026-05-27 11:03:29'),
(10, 10, 'products/yogurt.jpg', 'Greek Yogurt', 1, 1, '2026-05-27 11:03:29'),
(11, 11, 'products/chicken.jpg', 'Chicken Breast', 1, 1, '2026-05-27 11:03:29'),
(12, 12, 'products/beef.jpg', 'Beef Striploin', 1, 1, '2026-05-27 11:03:29'),
(13, 13, 'products/salmon.jpg', 'Atlantic Salmon', 1, 1, '2026-05-27 11:03:29'),
(14, 14, 'products/prawns.jpg', 'Tiger Prawns', 1, 1, '2026-05-27 11:03:29'),
(17, 17, 'products/eggs.jpg', 'Grade A Eggs', 1, 1, '2026-05-27 11:03:29'),
(18, 18, 'products/coriander.jpg', 'Fresh Coriander', 1, 1, '2026-05-27 11:03:29'),
(19, 19, 'products/garlic.jpg', 'Local Garlic', 1, 1, '2026-05-27 11:03:29'),
(20, 20, 'products/potatoes.jpg', 'Cameron Potatoes', 1, 1, '2026-05-27 11:03:29'),
(21, 21, 'products/cucumber.jpg', 'Japanese Cucumber', 1, 1, '2026-05-27 11:03:29'),
(22, 22, 'products/honeydew.jpg', 'Honeydew Melon', 1, 1, '2026-05-27 11:03:29'),
(23, 23, 'products/cheese.jpg', 'Cheddar Cheese', 1, 1, '2026-05-27 11:03:29'),
(24, 24, 'products/milk-lowfat.jpg', 'Low Fat Milk', 1, 1, '2026-05-27 11:03:29'),
(25, 25, 'products/chicken-thigh.jpg', 'Chicken Thigh', 1, 1, '2026-05-27 11:03:29'),
(26, 26, 'products/beef-minced.jpg', 'Beef Minced', 1, 1, '2026-05-27 11:03:29'),
(27, 27, 'products/mackerel.jpg', 'Fresh Mackerel', 1, 1, '2026-05-27 11:03:29'),
(28, 28, 'products/squid.jpg', 'Squid Sotong', 1, 1, '2026-05-27 11:03:29'),
(31, 31, 'products/omega-eggs.jpg', 'Omega-3 Eggs', 1, 1, '2026-05-27 11:03:29'),
(32, 32, 'products/tofu-silken.jpg', 'Silken Tofu', 1, 1, '2026-05-27 11:03:29'),
(33, 33, 'products/tofu-firm.jpg', 'Firm Tofu', 1, 1, '2026-05-27 11:03:29'),
(34, 34, 'products/ginger.jpg', 'Fresh Ginger', 1, 1, '2026-05-27 11:03:29'),
(35, 35, 'products/lemongrass.jpg', 'Lemongrass', 1, 1, '2026-05-27 11:03:29'),
(36, 36, 'products/veg-kai-008.jpg', 'Kai Lan (Chinese Broccoli)', 1, 1, '2026-07-16 15:30:00'),
(37, 37, 'products/veg-brj-009.jpg', 'Brinjal (Terung)', 1, 1, '2026-07-16 15:30:00'),
(38, 38, 'products/veg-swp-010.jpg', 'Sweet Potato (Keledek)', 1, 1, '2026-07-16 15:30:00'),
(39, 39, 'products/veg-chl-011.jpg', 'Red Chillies (Cili Merah)', 1, 1, '2026-07-16 15:30:00'),
(40, 40, 'products/veg-cab-012.jpg', 'Round Cabbage', 1, 1, '2026-07-16 15:30:00'),
(41, 41, 'products/fru-pap-005.jpg', 'Sekaki Papaya', 1, 1, '2026-07-16 15:30:00'),
(42, 42, 'products/fru-wtm-006.jpg', 'Seedless Watermelon', 1, 1, '2026-07-16 15:30:00'),
(43, 43, 'products/fru-org-007.jpg', 'Sunkist Oranges', 1, 1, '2026-07-16 15:30:00'),
(44, 44, 'products/fru-pin-008.jpg', 'Josapine Pineapple', 1, 1, '2026-07-16 15:30:00'),
(45, 45, 'products/fru-grp-009.jpg', 'Red Seedless Grapes', 1, 1, '2026-07-16 15:30:00'),
(46, 46, 'products/dai-btr-005.jpg', 'Salted Butter Block 250g', 1, 1, '2026-07-16 15:30:00'),
(47, 47, 'products/dai-yog-006.jpg', 'Strawberry Yogurt Drink 700g', 1, 1, '2026-07-16 15:30:00'),
(48, 48, 'products/dai-moz-007.jpg', 'Mozzarella Cheese 200g', 1, 1, '2026-07-16 15:30:00'),
(49, 49, 'products/met-ckw-005.jpg', 'Chicken Whole Leg', 1, 1, '2026-07-16 15:30:00'),
(50, 50, 'products/met-lmb-006.jpg', 'Lamb Shoulder Cubes 500g', 1, 1, '2026-07-16 15:30:00'),
(51, 51, 'products/met-ckm-007.jpg', 'Chicken Minced 500g', 1, 1, '2026-07-16 15:30:00'),
(52, 52, 'products/sea-sia-005.jpg', 'Siakap (Sea Bass) Whole', 1, 1, '2026-07-16 15:30:00'),
(53, 53, 'products/sea-crb-006.jpg', 'Mud Crab (Ketam)', 1, 1, '2026-07-16 15:30:00'),
(54, 54, 'products/sea-kmb-007.jpg', 'Kembung Fish 500g', 1, 1, '2026-07-16 15:30:00'),
(55, 55, 'products/sea-cck-008.jpg', 'Cockles (Kerang) 500g', 1, 1, '2026-07-16 15:30:00'),
(59, 59, 'products/egg-qil-005.jpg', 'Quail Eggs (20 pcs)', 1, 1, '2026-07-16 15:30:00'),
(62, 62, 'products/hrb-mnt-005.jpg', 'Fresh Mint Leaves (Pudina)', 1, 1, '2026-07-16 15:30:00'),
(63, 63, 'products/hrb-tur-006.jpg', 'Fresh Turmeric (Kunyit) 200g', 1, 1, '2026-07-16 15:30:00'),
(64, 64, 'products/hrb-cur-007.jpg', 'Curry Leaves (Daun Kari)', 1, 1, '2026-07-16 15:30:00'),
(65, 65, 'products/hrb-glc-008.jpg', 'Galangal (Lengkuas) 200g', 1, 1, '2026-07-16 15:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar_path` varchar(500) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('M','F','OTHER') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`id`, `user_id`, `full_name`, `phone`, `avatar_path`, `date_of_birth`, `gender`, `created_at`, `updated_at`) VALUES
(1, 1, 'Platform Administrator', '+60123456789', NULL, NULL, NULL, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(2, 2, 'Lim Wei Ming', '+60195551234', NULL, NULL, NULL, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(3, 3, 'Cherry Tan', '+60123334444', NULL, NULL, NULL, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(4, 4, 'Sim William', 'weeliam666@gmail.com', NULL, NULL, NULL, '2026-05-28 04:07:06', '2026-05-28 04:07:06'),
(5, 5, 'william', '', NULL, NULL, NULL, '2026-05-28 04:27:37', '2026-05-28 04:27:37'),
(6, 6, 'CY', '+6011234567', NULL, NULL, NULL, '2026-07-09 08:52:53', '2026-07-09 08:52:53'),
(7, 7, 'Aisyah Rahman', '+60122345671', NULL, NULL, 'F', '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(8, 8, 'Lim Wei Jie', '+60122345672', NULL, NULL, 'M', '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(9, 9, 'Muthu Raj', '+60122345673', NULL, NULL, 'M', '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(10, 10, 'Nurul Huda', '+60122345674', NULL, NULL, 'F', '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(11, 11, 'Daniel Tan', '+60122345675', NULL, NULL, 'M', '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(12, 12, 'Siti Aminah', '+60122345676', NULL, NULL, 'F', '2026-07-16 20:10:35', '2026-07-16 20:10:35'),
(13, 13, 'Tan Green Valley', '+60126667890', NULL, NULL, NULL, '2026-07-16 21:31:59', '2026-07-16 21:31:59');

-- --------------------------------------------------------

--
-- Table structure for table `promo_codes`
--

CREATE TABLE `promo_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('PERCENTAGE','FIXED_AMOUNT') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `user_limit` int(11) NOT NULL DEFAULT 1,
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promo_codes`
--

INSERT INTO `promo_codes` (`id`, `code`, `description`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `usage_limit`, `usage_count`, `user_limit`, `starts_at`, `expires_at`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'WELCOME10', '10% off your first order (max RM20)', 'PERCENTAGE', 10.00, 30.00, 20.00, 1000, 1, 1, '2026-06-11 04:45:06', '2026-08-25 11:03:29', 1, 1, '2026-05-27 11:03:29', '2026-06-11 04:45:06'),
(2, 'FRESH5', 'RM5 off orders above RM50', 'FIXED_AMOUNT', 5.00, 50.00, NULL, NULL, 0, 3, '2026-07-05 09:00:28', '2026-06-26 11:03:29', 1, 1, '2026-05-27 11:03:29', '2026-07-05 09:00:28');

-- --------------------------------------------------------

--
-- Table structure for table `promo_code_usages`
--

CREATE TABLE `promo_code_usages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `promo_code_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refund_requests`
--

CREATE TABLE `refund_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `retailer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scope` enum('FULL','PARTIAL') NOT NULL,
  `reason` enum('NOT_FRESH','DAMAGED','MISSING_ITEM','WRONG_ITEM','OTHER') NOT NULL,
  `detail` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('REQUESTED','ESCALATED','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'REQUESTED',
  `handled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decision_note` text DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refund_requests`
--

INSERT INTO `refund_requests` (`id`, `order_id`, `user_id`, `retailer_id`, `scope`, `reason`, `detail`, `amount`, `status`, `handled_by`, `decision_note`, `escalated_at`, `resolved_at`, `created_at`, `updated_at`) VALUES
(1, 12, 8, 1, 'PARTIAL', 'NOT_FRESH', 'The lettuce arrived wilted and had brown edges. Requesting a refund for it.', 4.90, 'REQUESTED', NULL, NULL, NULL, NULL, '2026-07-16 21:31:59', '2026-07-16 21:31:59');

-- --------------------------------------------------------

--
-- Table structure for table `refund_request_items`
--

CREATE TABLE `refund_request_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `refund_request_id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `line_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refund_request_items`
--

INSERT INTO `refund_request_items` (`id`, `refund_request_id`, `order_item_id`, `quantity`, `line_amount`) VALUES
(1, 1, 17, 1.00, 4.90);

-- --------------------------------------------------------

--
-- Table structure for table `retailers`
--

CREATE TABLE `retailers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `business_reg_no` varchar(50) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `approval_status` enum('PENDING','APPROVED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  `use_custom_discounts` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `commission_rate` decimal(5,2) DEFAULT NULL COMMENT 'Override platform commission %; NULL = use global'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `retailers`
--

INSERT INTO `retailers` (`id`, `user_id`, `company_name`, `business_reg_no`, `contact_phone`, `business_address`, `approval_status`, `use_custom_discounts`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `updated_at`, `commission_rate`) VALUES
(1, 2, 'Cameron Fresh Sdn Bhd', '202301012345', '+60195551234', 'Lot 42, Cameron Highlands, Pahang 39000', 'APPROVED', 1, 1, '2026-05-27 11:03:29', NULL, '2026-05-27 11:03:29', '2026-07-16 15:00:40', NULL),
(2, 4, 'William Fruit', '1222211233', 'weeliam666@gmail.com', '18,jalan perdana 42', 'APPROVED', 0, 1, '2026-05-28 19:36:13', NULL, '2026-05-28 04:07:06', '2026-05-28 19:36:13', NULL),
(3, 13, 'Green Valley Organic Farm', '202401055678', '+60126667890', 'Lot 88, Jalan Organik, Sepang, Selangor 43900', 'PENDING', 0, NULL, NULL, NULL, '2026-07-16 21:31:59', '2026-07-16 21:31:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `retailer_freshness_discounts`
--

CREATE TABLE `retailer_freshness_discounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `retailer_id` bigint(20) UNSIGNED NOT NULL,
  `level_name` varchar(20) NOT NULL,
  `discount_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `retailer_freshness_discounts`
--

INSERT INTO `retailer_freshness_discounts` (`id`, `retailer_id`, `level_name`, `discount_pct`, `created_at`, `updated_at`) VALUES
(1, 1, 'VERY_FRESH', 0.00, '2026-07-16 15:00:38', '2026-07-16 15:00:38'),
(2, 1, 'FRESH', 0.00, '2026-07-16 15:00:38', '2026-07-16 15:00:38'),
(3, 1, 'ENJOY_SOON', 10.00, '2026-07-16 15:00:38', '2026-07-16 15:12:50'),
(4, 1, 'LAST_CHANCE', 20.00, '2026-07-16 15:00:38', '2026-07-16 15:12:50');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `helpful_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `order_id`, `rating`, `title`, `body`, `is_approved`, `helpful_count`, `created_at`, `updated_at`) VALUES
(1, 7, 6, 11, 5, 'Sweetest mangoes ever', 'The Harum Manis mangoes were incredibly sweet and arrived perfectly ripe. The freshness score was spot on. Will order again!', 1, 4, '2026-07-12 16:00:00', '2026-07-16 20:10:35'),
(2, 8, 1, 12, 5, 'So fresh and crisp', 'Butterhead lettuce came crisp and clean, no wilting at all. Love that I can see the freshness percentage before buying.', 1, 0, '2026-07-13 16:00:00', '2026-07-16 20:10:35'),
(3, 9, 13, 13, 4, 'Good quality salmon', 'Salmon fillet was fresh and delivery was on time. Packaging kept it cold. Quality was great.', 1, 5, '2026-07-10 16:00:00', '2026-07-16 20:10:35'),
(4, 10, 3, 14, 5, 'Perfect for my cooking', 'Cherry tomatoes were juicy and firm. Grabbed them on a Last Chance discount and still very fresh. Great value and less waste!', 1, 0, '2026-07-14 16:00:00', '2026-07-16 20:10:35'),
(6, 12, 11, 16, 5, 'Fresh halal chicken', 'Free-range chicken breast was fresh and clean, no funny smell. The freshness meter gives me real confidence buying meat online.', 1, 0, '2026-07-15 16:00:00', '2026-07-16 20:10:35'),
(7, 3, 8, 17, 5, 'Kids love these', 'Pisang Berangan is always a hit at home. Fresh bunch, no bruising. The app makes weekly grocery so convenient.', 1, 0, '2026-07-08 16:00:00', '2026-07-16 20:10:35'),
(8, 3, 9, 18, 4, 'Fresh and creamy', 'Full cream milk arrived well within date and tasted fresh. Delivery was prompt. Happy customer.', 1, 1, '2026-07-06 16:00:00', '2026-07-16 20:10:35'),
(16, 11, 4, 15, 4, 'Sweet and crunchy carrots', 'Cameron carrots were sweet, firm and clean. Great for juicing and stir-fry. Delivered fresh and on time. Would buy again.', 1, 0, '2026-07-11 16:00:00', '2026-07-16 20:23:27');

-- --------------------------------------------------------

--
-- Table structure for table `review_replies`
--

CREATE TABLE `review_replies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `review_id` bigint(20) UNSIGNED NOT NULL,
  `retailer_id` bigint(20) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `carrier` varchar(50) NOT NULL DEFAULT 'FreshMart Express',
  `estimated_delivery` date DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `order_id`, `tracking_number`, `carrier`, `estimated_delivery`, `shipped_at`, `delivered_at`, `notes`) VALUES
(1, 1, 'FM-ABAF4CEEAF', 'FreshMart Express', '2026-06-09', NULL, NULL, NULL),
(2, 2, 'FM-14CDCA7950', 'FreshMart Express', '2026-06-13', NULL, NULL, NULL),
(3, 3, 'FM-82DA41AFA3', 'FreshMart Express', '2026-06-26', NULL, NULL, NULL),
(4, 4, 'FM-E6FD3368E1', 'FreshMart Express', '2026-06-27', NULL, NULL, NULL),
(5, 5, 'FM-A43E95CC8D', 'FreshMart Express', '2026-07-02', NULL, NULL, NULL),
(6, 6, 'FM-33E6490B8B', 'FreshMart Express', '2026-07-05', NULL, NULL, NULL),
(7, 7, 'FM-EEF3CD6800', 'FreshMart Express', '2026-07-07', NULL, NULL, NULL),
(8, 8, 'FM-400354D7AA', 'FreshMart Express', '2026-07-07', NULL, NULL, NULL),
(9, 9, 'FM-A839D1A6EC', 'FreshMart Express', '2026-07-07', NULL, NULL, NULL),
(10, 10, 'FM-BF0667E266', 'FreshMart Express', '2026-07-11', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_batches`
--

CREATE TABLE `stock_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_code` varchar(50) NOT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `original_quantity` decimal(10,2) NOT NULL,
  `quantity_remaining` decimal(10,2) NOT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `selling_price_override` decimal(10,2) DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `status` enum('ACTIVE','DEPLETED','EXPIRED','RECALLED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_batches`
--

INSERT INTO `stock_batches` (`id`, `product_id`, `supplier_id`, `batch_code`, `received_date`, `expiry_date`, `original_quantity`, `quantity_remaining`, `cost_per_unit`, `selling_price_override`, `storage_location`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'LET-B001', '2026-06-30', '2026-07-17', 50.00, 49.00, 3.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:19:13'),
(2, 2, 1, 'BOK-B001', '2026-06-30', '2026-07-18', 40.00, 35.00, 2.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:19:13'),
(3, 3, 1, 'TOM-B001', '2026-06-30', '2026-07-19', 20.00, 17.00, 9.00, NULL, 'Shelf A2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:19:13'),
(4, 4, 1, 'CAR-B001', '2026-07-10', '2026-07-28', 60.00, 60.00, 4.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(5, 5, 1, 'SPI-B001', '2026-07-09', '2026-07-28', 30.00, 28.00, 5.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(6, 6, 1, 'MAN-B001', '2026-07-17', '2026-07-31', 25.00, 25.00, 13.00, NULL, 'Shelf B1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 21:51:49'),
(7, 7, 1, 'APP-B001', '2026-07-14', '2026-08-03', 100.00, 100.00, 7.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(8, 8, 1, 'BAN-B001', '2026-07-14', '2026-08-04', 40.00, 33.00, 4.50, NULL, 'Shelf B2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-17 02:15:16'),
(9, 9, 1, 'MLK-B001', '2026-07-14', '2026-08-04', 60.00, 54.00, 5.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(10, 10, 1, 'YOG-B001', '2026-07-14', '2026-08-03', 40.00, 40.00, 9.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(11, 11, 1, 'CKB-B001', '2026-07-15', '2026-08-02', 25.00, 23.00, 16.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(12, 12, 1, 'BEF-B001', '2026-07-06', '2026-07-24', 15.00, 12.00, 48.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(13, 13, 1, 'SAL-B001', '2026-07-04', '2026-07-24', 20.00, 18.00, 65.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(14, 14, 1, 'PRA-B001', '2026-07-09', '2026-07-29', 12.00, 10.00, 36.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(17, 17, 1, 'EGG-B001', '2026-07-14', '2026-08-07', 100.00, 100.00, 10.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(18, 18, 1, 'COR-B001', '2026-07-15', '2026-08-02', 25.00, 22.00, 1.50, NULL, 'Shelf C1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(19, 19, 1, 'GAR-B001', '2026-07-15', '2026-08-02', 50.00, 46.00, 6.50, NULL, 'Shelf C2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(20, 20, 1, 'POT-B001', '2026-07-15', '2026-08-02', 50.00, 50.00, 4.00, NULL, 'Shelf A1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(21, 21, 1, 'CUC-B001', '2026-07-15', '2026-08-03', 25.00, 24.00, 5.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(22, 22, 1, 'MEL-B001', '2026-07-05', '2026-07-24', 30.00, 30.00, 8.00, NULL, 'Shelf B1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(23, 23, 1, 'CHE-B001', '2026-07-06', '2026-07-23', 15.00, 15.00, 15.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(24, 24, 1, 'MLK-B002', '2026-07-09', '2026-07-28', 50.00, 50.00, 4.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(25, 25, 1, 'CKT-B001', '2026-07-10', '2026-07-28', 20.00, 21.00, 13.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(26, 26, 1, 'BEF-B002', '2026-07-10', '2026-07-28', 15.00, 15.00, 20.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(27, 27, 1, 'MAC-B001', '2026-07-14', '2026-08-03', 25.00, 25.00, 12.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(28, 28, 1, 'SQD-B001', '2026-07-14', '2026-08-03', 18.00, 18.00, 22.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(31, 31, 1, 'OMG-B001', '2026-07-15', '2026-08-02', 30.00, 30.00, 12.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(32, 32, 1, 'SLK-B001', '2026-07-05', '2026-07-24', 40.00, 40.00, 2.20, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(33, 33, 1, 'FRM-B001', '2026-07-02', '2026-07-26', 40.00, 40.00, 2.80, NULL, 'Cold Room 1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-03 13:21:13'),
(34, 34, 1, 'GIN-B001', '2026-07-10', '2026-07-28', 20.00, 20.00, 4.50, NULL, 'Shelf C2', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(35, 35, 1, 'LMG-B001', '2026-07-10', '2026-07-28', 30.00, 30.00, 2.00, NULL, 'Shelf C1', 'ACTIVE', '2026-05-27 11:03:29', '2026-07-16 14:18:21'),
(36, 36, 1, 'KAI-B001', '2026-07-15', '2026-07-20', 40.00, 38.00, 2.80, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(37, 37, 1, 'BRJ-B001', '2026-07-14', '2026-07-22', 35.00, 30.00, 3.50, NULL, 'Shelf A2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(38, 38, 1, 'SWP-B001', '2026-07-16', '2026-08-06', 80.00, 80.00, 3.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(39, 39, 1, 'CHL-B001', '2026-07-09', '2026-07-19', 20.00, 12.00, 6.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(40, 40, 1, 'CAB-B001', '2026-07-16', '2026-07-30', 50.00, 50.00, 2.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(41, 41, 1, 'PAP-B001', '2026-07-15', '2026-07-22', 40.00, 36.00, 4.50, NULL, 'Shelf B1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(42, 42, 1, 'WTM-B001', '2026-07-16', '2026-07-26', 30.00, 30.00, 8.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(43, 43, 1, 'ORG-B001', '2026-07-13', '2026-08-03', 90.00, 85.00, 8.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(44, 44, 1, 'PIN-B001', '2026-07-11', '2026-07-18', 30.00, 22.00, 5.00, NULL, 'Shelf B2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(45, 45, 1, 'GRP-B001', '2026-07-14', '2026-07-24', 40.00, 35.00, 11.00, NULL, 'Cold Room 2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(46, 46, 1, 'BTR-B001', '2026-07-16', '2026-08-15', 60.00, 58.00, 10.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(47, 47, 1, 'YGD-B001', '2026-07-13', '2026-08-03', 50.00, 44.00, 6.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(48, 48, 1, 'MOZ-B001', '2026-07-10', '2026-08-09', 40.00, 36.00, 12.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(49, 49, 1, 'CKW-B001', '2026-07-16', '2026-07-21', 45.00, 42.00, 7.50, NULL, 'Cold Room 3', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(50, 50, 1, 'LMB-B001', '2026-07-15', '2026-07-20', 25.00, 20.00, 26.00, NULL, 'Cold Room 3', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(51, 51, 1, 'CKM-B001', '2026-07-13', '2026-07-17', 30.00, 18.00, 8.50, NULL, 'Cold Room 3', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(52, 52, 1, 'SIA-B001', '2026-07-16', '2026-07-19', 25.00, 22.00, 22.00, NULL, 'Ice Bay 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(53, 53, 1, 'CRB-B001', '2026-07-16', '2026-07-18', 15.00, 12.00, 36.00, NULL, 'Ice Bay 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(54, 54, 1, 'KMB-B001', '2026-07-15', '2026-07-18', 40.00, 30.00, 9.00, NULL, 'Ice Bay 2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(55, 55, 1, 'CCK-B001', '2026-07-15', '2026-07-17', 35.00, 20.00, 6.00, NULL, 'Ice Bay 2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(59, 59, 1, 'QIL-B001', '2026-07-12', '2026-08-02', 60.00, 54.00, 5.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(62, 62, 1, 'MNT-B001', '2026-07-14', '2026-07-21', 30.00, 26.00, 2.50, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(63, 63, 1, 'TUR-B001', '2026-07-16', '2026-07-30', 40.00, 40.00, 5.00, NULL, 'Shelf A2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(64, 64, 1, 'CUR-B001', '2026-07-13', '2026-07-20', 25.00, 20.00, 2.00, NULL, 'Cold Room 1', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(65, 65, 1, 'GLC-B001', '2026-07-16', '2026-07-30', 35.00, 35.00, 4.00, NULL, 'Shelf A2', 'ACTIVE', '2026-07-16 15:30:00', '2026-07-16 15:30:00'),
(66, 6, 1, 'MAN-B002', '2026-07-01', '2026-07-19', 20.00, 12.00, 13.00, NULL, 'Shelf B2', 'ACTIVE', '2026-07-16 21:51:49', '2026-07-16 21:51:49');

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `category_id`, `name`, `slug`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Leafy Greens', 'leafy-greens', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(2, 1, 'Root Vegetables', 'root-vegetables', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(3, 1, 'Fruiting Veg', 'fruiting-veg', 3, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(4, 2, 'Tropical', 'tropical', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(5, 2, 'Imported', 'imported', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(6, 3, 'Milk', 'milk', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(7, 3, 'Cheese', 'cheese', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(8, 3, 'Yogurt', 'yogurt', 3, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(9, 4, 'Chicken', 'chicken', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(10, 4, 'Beef', 'beef', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(11, 5, 'Fish', 'fish', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(12, 5, 'Shellfish', 'shellfish', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(13, 6, 'Bread', 'bread', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(14, 6, 'Pastries', 'pastries', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(15, 8, 'Fresh Herbs', 'fresh-herbs', 1, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29'),
(16, 8, 'Aromatics', 'aromatics', 2, 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `retailer_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `retailer_id`, `name`, `contact_person`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Highland Farms Co-op', 'Ahmad Bin Hassan', '+60195551234', NULL, 'Tringkap, Cameron Highlands, Pahang', 1, '2026-05-27 11:03:29', '2026-05-27 11:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`config_key`, `config_value`, `description`, `updated_by`, `updated_at`) VALUES
('commission_rate', '10.00', 'Platform commission on retailer sales (%)', NULL, '2026-07-16 21:07:49'),
('currency', 'MYR', 'Default currency', NULL, '2026-05-27 11:03:29'),
('currency_symbol', 'RM', 'Currency prefix', NULL, '2026-05-27 11:03:29'),
('freshness_recalc_minutes', '5', 'How often cron runs', NULL, '2026-05-27 11:03:29'),
('guest_cart_hours', '24', 'Hours a guest cart persists', NULL, '2026-05-27 11:03:29'),
('maintenance_mode', '0', '1 to enable maintenance mode', NULL, '2026-05-27 11:03:29'),
('product_image_max_count', '5', 'Max images per product', NULL, '2026-05-27 11:03:29'),
('product_image_max_size', '5242880', 'Max image size in bytes (5MB)', NULL, '2026-05-27 11:03:29'),
('shipping_fee_default', '5.00', 'Default shipping fee (MYR)', NULL, '2026-05-27 11:03:29'),
('shipping_free_threshold', '50.00', 'Free shipping above this amount', NULL, '2026-05-27 11:03:29'),
('site_email', 'support@freshmart.my', 'Support email', NULL, '2026-05-27 11:03:29'),
('site_name', 'FreshMart', 'Display name', NULL, '2026-05-27 11:03:29'),
('tax_rate', '0.00', 'Sales tax (0 for now)', NULL, '2026-05-27 11:03:29'),
('timezone', 'Asia/Kuala_Lumpur', 'System timezone', NULL, '2026-05-27 11:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `unit_types`
--

CREATE TABLE `unit_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_weight` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `unit_types`
--

INSERT INTO `unit_types` (`id`, `code`, `name`, `is_weight`, `display_order`) VALUES
(1, 'kg', 'Kilogram', 1, 1),
(2, 'g', 'Gram', 1, 2),
(3, 'pack', 'Pack', 0, 3),
(4, 'piece', 'Piece', 0, 4),
(5, 'bunch', 'Bunch', 0, 5),
(6, 'dozen', 'Dozen', 0, 6),
(7, 'box', 'Box', 0, 7),
(8, 'bottle', 'Bottle', 0, 8),
(9, 'loaf', 'Loaf', 0, 9);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('CUSTOMER','RETAILER','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
  `status` enum('ACTIVE','SUSPENDED','PENDING') NOT NULL DEFAULT 'PENDING',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `status`, `email_verified`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'admin@freshmart.my', '$2y$10$dsccTPCmeXQNtoEr01hjeetUqIncYnQl3enbJLyM2eeMCcxqiBIT6', 'ADMIN', 'ACTIVE', 1, '2026-05-27 11:03:29', '2026-05-28 04:14:40', NULL),
(2, 'retailer@cameron.my', '$2y$10$j/Lc.TO5BdbVllMcM9nDJuCh1FFPAbT8TyRSHDftB1qo0MObhE5Bi', 'RETAILER', 'ACTIVE', 1, '2026-05-27 11:03:29', '2026-05-28 19:33:29', NULL),
(3, 'cherry@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-05-27 11:03:29', '2026-05-28 04:14:40', NULL),
(4, 'weeliam666@gmail.com', '$2y$10$C8MHonr8wzgQ59/q6WAU/Oux.BYeAfw1oFctDZ6sVQrh7QrnpEwS6', 'RETAILER', 'ACTIVE', 0, '2026-05-28 04:07:06', '2026-05-28 19:36:07', NULL),
(5, 'william@gmail.com', '$2y$10$uceRmxvZYl2mH.UmbA1DbOzrgpA6DKd5Ej0HK3vu8IaT2kGK/aqUi', 'CUSTOMER', 'ACTIVE', 0, '2026-05-28 04:27:37', '2026-05-28 04:27:37', NULL),
(6, 'ccyy2233@gmail.com', '$2y$10$VRYeVNslsC/rptvbcXYqqOswJ3FLbFNGdJleOsLql2ctkc1LicdKi', 'CUSTOMER', 'ACTIVE', 0, '2026-07-09 08:52:53', '2026-07-09 08:52:53', NULL),
(7, 'aisyah.rahman@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35', NULL),
(8, 'wei.jie.lim@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35', NULL),
(9, 'muthu.raj@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35', NULL),
(10, 'nurul.huda@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35', NULL),
(11, 'daniel.tan@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35', NULL),
(12, 'siti.aminah@example.my', '$2b$10$lhzSoVhzLfKCNZWtgAbXAOd.1bPFgT1X9zr5VstX7y51mvBpGCDIO', 'CUSTOMER', 'ACTIVE', 1, '2026-07-16 20:10:35', '2026-07-16 20:10:35', NULL),
(13, 'greenvalley@demo.my', '$2y$10$uceRmxvZYl2mH.UmbA1DbOzrgpA6DKd5Ej0HK3vu8IaT2kGK/aqUi', 'RETAILER', 'PENDING', 1, '2026-07-16 21:31:59', '2026-07-16 21:31:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(1, 3, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(2, 5, 11.50, '2026-07-16 21:01:38', '2026-07-17 02:15:16'),
(3, 6, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(4, 7, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(5, 8, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(6, 9, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(7, 10, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(8, 11, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(9, 12, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(10, 2, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(11, 4, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(12, 1, 0.00, '2026-07-16 21:01:38', '2026-07-16 21:01:38'),
(13, 13, 0.00, '2026-07-16 21:31:59', '2026-07-16 21:31:59');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wallet_id` bigint(20) UNSIGNED NOT NULL,
  `direction` enum('CREDIT','DEBIT') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `reason` enum('REFUND','ORDER_PAYMENT','TOPUP','ADJUSTMENT') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `wallet_id`, `direction`, `amount`, `balance_after`, `reason`, `reference`, `description`, `created_at`) VALUES
(1, 2, 'CREDIT', 11.50, 11.50, 'REFUND', 'FM-20260705-6175', 'Cancelled order FM-20260705-6175', '2026-07-17 02:15:16');

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `created_at`) VALUES
(1, 5, '2026-05-28 04:27:37'),
(2, 6, '2026-07-09 08:52:53');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wishlist_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist_items`
--

INSERT INTO `wishlist_items` (`id`, `wishlist_id`, `product_id`, `added_at`) VALUES
(3, 1, 10, '2026-07-16 19:41:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_addresses_user` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_carts_user` (`user_id`),
  ADD KEY `idx_carts_guest` (`guest_session_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_product` (`cart_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_categories_slug` (`slug`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_verif_token` (`token_hash`);

--
-- Indexes for table `freshness_config`
--
ALTER TABLE `freshness_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level_name` (`level_name`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_invlog_batch` (`stock_batch_id`),
  ADD KEY `idx_invlog_type` (`movement_type`),
  ADD KEY `idx_invlog_order` (`related_order_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_notif_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `shipping_address_id` (`shipping_address_id`),
  ADD KEY `billing_address_id` (`billing_address_id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_placed` (`placed_at`);

--
-- Indexes for table `order_history`
--
ALTER TABLE `order_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_ohistory_order` (`order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `stock_batch_id` (`stock_batch_id`),
  ADD KEY `idx_oitems_order` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_resets_token` (`token_hash`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_order` (`order_id`);

--
-- Indexes for table `platform_statistics`
--
ALTER TABLE `platform_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stat_date` (`stat_date`),
  ADD KEY `idx_stats_date` (`stat_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `subcategory_id` (`subcategory_id`),
  ADD KEY `unit_type_id` (`unit_type_id`),
  ADD KEY `idx_products_retailer` (`retailer_id`),
  ADD KEY `idx_products_category` (`category_id`),
  ADD KEY `idx_products_active` (`is_active`),
  ADD KEY `idx_products_featured` (`is_featured`);
ALTER TABLE `products` ADD FULLTEXT KEY `idx_products_search` (`name`,`description`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pimages_product` (`product_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_promos_code` (`code`),
  ADD KEY `idx_promos_dates` (`starts_at`,`expires_at`);

--
-- Indexes for table `promo_code_usages`
--
ALTER TABLE `promo_code_usages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `idx_promousage_promo` (`promo_code_id`),
  ADD KEY `idx_promousage_user` (`user_id`);

--
-- Indexes for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_refund_order` (`order_id`),
  ADD KEY `idx_refund_user` (`user_id`),
  ADD KEY `idx_refund_retailer` (`retailer_id`),
  ADD KEY `idx_refund_status` (`status`);

--
-- Indexes for table `refund_request_items`
--
ALTER TABLE `refund_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rri_request` (`refund_request_id`),
  ADD KEY `fk_rri_order_item` (`order_item_id`);

--
-- Indexes for table `retailers`
--
ALTER TABLE `retailers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `business_reg_no` (`business_reg_no`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_retailers_approval` (`approval_status`);

--
-- Indexes for table `retailer_freshness_discounts`
--
ALTER TABLE `retailer_freshness_discounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_retailer_level` (`retailer_id`,`level_name`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_user_product_order` (`user_id`,`product_id`,`order_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_reviews_product` (`product_id`);

--
-- Indexes for table `review_replies`
--
ALTER TABLE `review_replies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `review_id` (`review_id`),
  ADD KEY `retailer_id` (`retailer_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_expires` (`expires_at`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`);

--
-- Indexes for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_batches_product_expiry` (`product_id`,`expiry_date`,`status`),
  ADD KEY `idx_batches_status` (`status`),
  ADD KEY `idx_batches_expiry` (`expiry_date`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subcat_slug` (`category_id`,`slug`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `retailer_id` (`retailer_id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`config_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `unit_types`
--
ALTER TABLE `unit_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wallet_user` (`user_id`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wtxn_wallet` (`wallet_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wishlist_product` (`wishlist_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `freshness_config`
--
ALTER TABLE `freshness_config`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_history`
--
ALTER TABLE `order_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `platform_statistics`
--
ALTER TABLE `platform_statistics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `promo_code_usages`
--
ALTER TABLE `promo_code_usages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund_requests`
--
ALTER TABLE `refund_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `refund_request_items`
--
ALTER TABLE `refund_request_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `retailers`
--
ALTER TABLE `retailers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `retailer_freshness_discounts`
--
ALTER TABLE `retailer_freshness_discounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_replies`
--
ALTER TABLE `review_replies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `stock_batches`
--
ALTER TABLE `stock_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `unit_types`
--
ALTER TABLE `unit_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`stock_batch_id`) REFERENCES `stock_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`billing_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_history`
--
ALTER TABLE `order_history`
  ADD CONSTRAINT `order_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`stock_batch_id`) REFERENCES `stock_batches` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_4` FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD CONSTRAINT `promo_codes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `promo_code_usages`
--
ALTER TABLE `promo_code_usages`
  ADD CONSTRAINT `promo_code_usages_ibfk_1` FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promo_code_usages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `promo_code_usages_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD CONSTRAINT `fk_refund_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_refund_retailer` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_refund_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refund_request_items`
--
ALTER TABLE `refund_request_items`
  ADD CONSTRAINT `fk_rri_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rri_request` FOREIGN KEY (`refund_request_id`) REFERENCES `refund_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `retailers`
--
ALTER TABLE `retailers`
  ADD CONSTRAINT `retailers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `retailers_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `retailer_freshness_discounts`
--
ALTER TABLE `retailer_freshness_discounts`
  ADD CONSTRAINT `fk_rfd_retailer` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `review_replies`
--
ALTER TABLE `review_replies`
  ADD CONSTRAINT `review_replies_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_replies_ibfk_2` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `stock_batches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`retailer_id`) REFERENCES `retailers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_config`
--
ALTER TABLE `system_config`
  ADD CONSTRAINT `system_config_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wtxn_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `wishlist_items_ibfk_1` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
