-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 01:55 PM
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
-- Database: `mikhmon_billing`
--

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `agent_code` varchar(20) NOT NULL,
  `agent_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `level` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `commission_percent` decimal(5,2) DEFAULT 0.00,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`id`, `agent_code`, `agent_name`, `phone`, `email`, `password`, `balance`, `status`, `level`, `commission_percent`, `created_by`, `created_at`, `updated_at`, `last_login`, `notes`) VALUES
(1, 'AG001', 'Agent Demo', '081947215703', 'agent@demo.com', '$2y$10$DPgy35C8ZczgLnD3nUbYcO4rqXCJFf8pFPhGv33pk1xsb1OciR3F2', 20000.00, 'active', 'silver', 5.00, 'admin', '2025-11-10 05:50:10', '2025-11-12 07:04:27', '2025-11-12 05:24:57', ''),
(2, 'AG5136', 'tester', 'seed-ag5136', NULL, '', 0.00, 'active', 'bronze', 0.00, NULL, '2025-11-10 06:00:07', '2025-11-10 06:00:07', NULL, NULL),
(3, 'PUBLIC', 'Public Catalog', 'seed-public', NULL, '', 0.00, 'active', 'bronze', 0.00, NULL, '2025-11-10 06:00:07', '2025-11-10 06:00:07', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `agent_billing_payments`
--

CREATE TABLE `agent_billing_payments` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','paid','failed') DEFAULT 'paid',
  `processed_by` varchar(50) DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_billing_payments`
--

INSERT INTO `agent_billing_payments` (`id`, `agent_id`, `invoice_id`, `amount`, `fee`, `status`, `processed_by`, `created_at`) VALUES
(1, 1, 3, 110000.00, 0.00, 'paid', 'system', '2025-11-12 04:19:16'),
(2, 1, 4, 100000.00, 0.00, 'paid', 'system', '2025-11-12 04:24:05'),
(3, 1, 5, 110000.00, 0.00, 'paid', 'system', '2025-11-12 04:34:26'),
(4, 1, 6, 100000.00, 0.00, 'paid', 'system', '2025-11-12 06:36:00'),
(5, 1, 7, 165000.00, -5000.00, 'paid', 'system', '2025-11-12 07:04:27');

-- --------------------------------------------------------

--
-- Table structure for table `agent_commissions`
--

CREATE TABLE `agent_commissions` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `commission_percent` decimal(5,2) NOT NULL,
  `voucher_price` decimal(15,2) NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `earned_at` timestamp NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_prices`
--

CREATE TABLE `agent_prices` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `buy_price` decimal(15,2) NOT NULL,
  `sell_price` decimal(15,2) NOT NULL,
  `stock_limit` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_prices`
--

INSERT INTO `agent_prices` (`id`, `agent_id`, `profile_name`, `buy_price`, `sell_price`, `stock_limit`, `created_at`, `updated_at`) VALUES
(1, 1, '3k', 2000.00, 3000.00, 0, '2025-11-10 05:52:55', '2025-11-10 05:52:55'),
(3, 1, '5k', 4000.00, 5000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(4, 1, '10k', 7000.00, 10000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(5, 2, '3k', 2000.00, 3000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(6, 2, '5k', 4000.00, 5000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(7, 3, '3k', 0.00, 3000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(8, 3, '5k', 0.00, 5000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(9, 3, '10k', 0.00, 10000.00, 0, '2025-11-10 06:00:07', '2025-11-10 06:00:07');

-- --------------------------------------------------------

--
-- Table structure for table `agent_profile_pricing`
--

CREATE TABLE `agent_profile_pricing` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `icon` varchar(50) DEFAULT 'fa-wifi',
  `color` varchar(20) DEFAULT 'blue',
  `sort_order` int(11) DEFAULT 0,
  `user_type` enum('voucher','member') DEFAULT 'voucher',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_profile_pricing`
--

INSERT INTO `agent_profile_pricing` (`id`, `agent_id`, `profile_name`, `display_name`, `description`, `price`, `original_price`, `is_active`, `is_featured`, `icon`, `color`, `sort_order`, `user_type`, `created_at`, `updated_at`) VALUES
(1, 1, '3k', 'Voucher 1 Hari', 'Voucher hotspot 1 hari', 3000.00, NULL, 1, 0, 'fa-wifi', 'blue', 1, 'voucher', '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(2, 1, '5k', 'Voucher 3 Hari', 'Voucher hotspot 3 hari', 5000.00, NULL, 1, 0, 'fa-wifi', 'indigo', 2, 'voucher', '2025-11-10 06:00:07', '2025-11-10 06:00:07'),
(3, 1, '10k', 'Voucher 7 Hari', 'Voucher hotspot 7 hari', 10000.00, NULL, 1, 0, 'fa-wifi', 'purple', 3, 'voucher', '2025-11-10 06:00:07', '2025-11-10 06:00:07');

-- --------------------------------------------------------

--
-- Table structure for table `agent_settings`
--

CREATE TABLE `agent_settings` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL DEFAULT 1,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(20) DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_settings`
--

INSERT INTO `agent_settings` (`id`, `agent_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES
(1, 1, 'min_topup_amount', '50000', 'number', 'Minimum amount untuk topup saldo', '2025-11-10 05:50:09', NULL),
(2, 1, 'max_topup_amount', '10000000', 'number', 'Maximum amount untuk topup saldo', '2025-11-10 05:50:09', NULL),
(3, 1, 'auto_approve_topup', '0', 'boolean', 'Auto approve topup request', '2025-11-10 05:50:09', NULL),
(4, 1, 'commission_enabled', '1', 'boolean', 'Enable commission system', '2025-11-10 05:50:09', NULL),
(5, 1, 'default_commission_percent', '5', 'number', 'Default commission percentage', '2025-11-10 05:50:09', NULL),
(6, 1, 'agent_registration_enabled', '1', 'boolean', 'Allow agent self registration', '2025-11-10 05:50:09', NULL),
(7, 1, 'min_balance_alert', '10000', 'number', 'Alert when balance below this amount', '2025-11-10 05:50:09', NULL),
(8, 1, 'whatsapp_notification_enabled', '1', 'boolean', 'Send WhatsApp notification to agents', '2025-11-10 05:50:09', NULL),
(9, 1, 'agent_can_set_sell_price', '1', 'boolean', 'Allow agent to set their own sell price', '2025-11-10 05:50:09', NULL),
(10, 1, 'voucher_prefix_agent', 'AG', 'string', 'Prefix for agent generated vouchers', '2025-11-10 05:50:09', NULL),
(11, 1, 'digiflazz_enabled', '0', 'boolean', 'Enable Digiflazz integration', '2025-11-10 05:50:09', NULL),
(12, 1, 'digiflazz_username', '', 'string', 'Digiflazz buyer username', '2025-11-10 05:50:09', NULL),
(13, 1, 'digiflazz_api_key', '', 'string', 'Digiflazz API key', '2025-11-10 05:50:09', NULL),
(14, 1, 'digiflazz_allow_test', '1', 'boolean', 'Allow Digiflazz testing mode', '2025-11-10 05:50:09', NULL),
(15, 1, 'digiflazz_default_markup_percent', '5', 'number', 'Default markup percent for Digiflazz products', '2025-11-10 05:50:09', NULL),
(16, 1, 'digiflazz_last_sync', NULL, 'datetime', 'Last price list sync timestamp', '2025-11-10 05:50:09', NULL),
(17, 1, 'voucher_username_password_same', '1', 'string', 'Voucher generation setting', '2025-11-10 05:54:17', 'admin'),
(18, 1, 'voucher_username_type', 'numeric', 'string', 'Voucher generation setting', '2025-11-10 05:54:17', 'admin'),
(19, 1, 'voucher_username_length', '5', 'string', 'Voucher generation setting', '2025-11-10 05:54:17', 'admin'),
(20, 1, 'voucher_password_type', 'alphanumeric', 'string', 'Voucher generation setting', '2025-11-10 05:54:17', 'admin'),
(21, 1, 'voucher_password_length', '6', 'string', 'Voucher generation setting', '2025-11-10 05:54:17', 'admin'),
(22, 1, 'voucher_prefix_enabled', '0', 'string', 'Voucher generation setting', '2025-11-10 05:54:41', 'admin'),
(23, 1, 'voucher_prefix', 'AG', 'string', 'Voucher generation setting', '2025-11-10 05:54:18', 'admin'),
(24, 1, 'voucher_uppercase', '1', 'string', 'Voucher generation setting', '2025-11-10 05:54:18', 'admin'),
(25, 1, 'payment_bank_name', 'BRI', 'string', 'Payment information setting', '2025-11-10 05:54:18', 'admin'),
(26, 1, 'payment_account_number', '420601003953531', 'string', 'Payment information setting', '2025-11-10 05:54:18', 'admin'),
(27, 1, 'payment_account_name', 'WARJAYA', 'string', 'Payment information setting', '2025-11-10 05:54:18', 'admin'),
(28, 1, 'payment_wa_confirm', '081947215703', 'string', 'Payment information setting', '2025-11-10 05:54:41', 'admin'),
(41, 1, 'admin_whatsapp_numbers', '6281234567890', 'string', NULL, '2025-11-10 06:00:07', NULL),
(50, 1, 'whatsapp_gateway_url', 'https://api.whatsapp.com', 'string', NULL, '2025-11-10 06:00:07', NULL),
(51, 1, 'whatsapp_token', '', 'string', NULL, '2025-11-10 06:00:07', NULL),
(63, 2, 'billing_fee_amount', '5000', 'string', NULL, '2025-11-12 06:33:29', NULL),
(64, 2, 'billing_sell_price', '0', 'string', NULL, '2025-11-12 06:33:29', NULL),
(75, 1, 'billing_commission_amount', '5000', 'string', NULL, '2025-11-12 07:00:28', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `agent_summary`
-- (See below for the actual view)
--
CREATE TABLE `agent_summary` (
`id` int(11)
,`agent_code` varchar(20)
,`agent_name` varchar(100)
,`phone` varchar(20)
,`balance` decimal(15,2)
,`status` enum('active','inactive','suspended')
,`level` enum('bronze','silver','gold','platinum')
,`total_vouchers` bigint(21)
,`used_vouchers` bigint(21)
,`total_topup` decimal(37,2)
,`total_spent` decimal(37,2)
,`total_commission` decimal(37,2)
,`created_at` timestamp
,`last_login` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `agent_topup_requests`
--

CREATE TABLE `agent_topup_requests` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `bank_name` varchar(50) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` varchar(50) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `agent_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_transactions`
--

CREATE TABLE `agent_transactions` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `transaction_type` enum('topup','generate','refund','commission','penalty','digiflazz') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `profile_name` varchar(100) DEFAULT NULL,
  `voucher_username` varchar(100) DEFAULT NULL,
  `voucher_password` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `description` text DEFAULT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_transactions`
--

INSERT INTO `agent_transactions` (`id`, `agent_id`, `transaction_type`, `amount`, `balance_before`, `balance_after`, `profile_name`, `voucher_username`, `voucher_password`, `quantity`, `description`, `reference_id`, `created_by`, `created_at`, `ip_address`, `user_agent`) VALUES
(1, 1, 'topup', 500000.00, 100000.00, 600000.00, NULL, NULL, NULL, 1, '', NULL, 'alijaya', '2025-11-10 05:53:26', NULL, NULL),
(2, 1, '', 110000.00, 600000.00, 490000.00, 'billing_payment', '3', NULL, 1, 'Payment for invoice 3', '3', 'system', '2025-11-12 04:19:16', NULL, NULL),
(3, 1, '', 100000.00, 490000.00, 390000.00, 'billing_payment', '4', NULL, 1, 'Payment for invoice 4', '4', 'system', '2025-11-12 04:24:05', NULL, NULL),
(4, 1, '', 110000.00, 390000.00, 280000.00, 'billing_payment', '5', NULL, 1, 'Payment for invoice 5', '5', 'system', '2025-11-12 04:34:26', NULL, NULL),
(5, 1, '', 100000.00, 280000.00, 180000.00, 'billing_payment', '6', NULL, 1, 'Payment for invoice 6', '6', 'system', '2025-11-12 06:36:00', NULL, NULL),
(6, 1, '', 160000.00, 180000.00, 20000.00, 'billing_payment', '7', NULL, 1, 'Payment for invoice 7', '7', 'system', '2025-11-12 07:04:27', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `agent_vouchers`
--

CREATE TABLE `agent_vouchers` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `buy_price` decimal(15,2) NOT NULL,
  `sell_price` decimal(15,2) DEFAULT NULL,
  `status` enum('active','used','expired','deleted') DEFAULT 'active',
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `sent_via` enum('web','whatsapp','manual') DEFAULT 'web',
  `sent_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `agent_vouchers`
--
DELIMITER $$
CREATE TRIGGER `after_agent_voucher_insert` AFTER INSERT ON `agent_vouchers` FOR EACH ROW BEGIN
    -- Calculate commission if enabled
    DECLARE v_commission_enabled BOOLEAN;
    DECLARE v_commission_percent DECIMAL(5,2);
    DECLARE v_commission_amount DECIMAL(15,2);
    
    SELECT CAST(setting_value AS UNSIGNED) INTO v_commission_enabled
    FROM agent_settings WHERE setting_key = 'commission_enabled';
    
    IF v_commission_enabled THEN
        SELECT commission_percent INTO v_commission_percent
        FROM agents WHERE id = NEW.agent_id;
        
        IF v_commission_percent > 0 AND NEW.sell_price IS NOT NULL THEN
            SET v_commission_amount = (NEW.sell_price * v_commission_percent / 100);
            
            INSERT INTO agent_commissions (
                agent_id, voucher_id, commission_amount,
                commission_percent, voucher_price
            ) VALUES (
                NEW.agent_id, NEW.id, v_commission_amount,
                v_commission_percent, NEW.sell_price
            );
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `billing_customers`
--

CREATE TABLE `billing_customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `service_number` varchar(100) DEFAULT NULL,
  `billing_day` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_isolated` tinyint(1) NOT NULL DEFAULT 0,
  `next_isolation_date` date DEFAULT NULL,
  `genieacs_match_mode` enum('device_id','phone_tag','pppoe_username') NOT NULL DEFAULT 'device_id',
  `genieacs_pppoe_username` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_customers`
--

INSERT INTO `billing_customers` (`id`, `profile_id`, `name`, `phone`, `email`, `address`, `service_number`, `billing_day`, `status`, `is_isolated`, `next_isolation_date`, `genieacs_match_mode`, `genieacs_pppoe_username`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'juragan alijaya', '081947215703', 'alijayanet@gmail.com', 'Jln. Pantai Tanjungpura Desa Ujunggebang Kecamatan Sukra - Indramayu', '', 1, 'active', 0, NULL, 'pppoe_username', 'cecep', '', '2025-11-10 06:21:44', NULL),
(2, 1, 'tester3', '087828060111', 'alijaya@test.com', '', '', 1, 'active', 0, NULL, 'pppoe_username', 'server_umi', '', '2025-11-12 04:10:29', NULL),
(3, 1, 'Alijaya Hotspot', '087828060222', 'alijayanet@gmail.com', 'Jln. Pantai Tanjungpura Desa Ujunggebang Kecamatan Sukra - Indramayu', '', 1, 'active', 0, NULL, 'pppoe_username', 'alijayanet@gmail.com', '', '2025-11-12 04:20:16', NULL),
(4, 1, 'Alijaya Hotspot3', '087828060333', 'alijayanet@gmail.com', '', '', 1, 'active', 0, NULL, 'pppoe_username', 'alijayanet@gmail.com', '', '2025-11-12 04:33:00', NULL),
(5, 1, 'Alijaya Hotspot4', '087828060444', 'alijayanet@gmail.com', 'Jln. Pantai Tanjungpura Desa Ujunggebang Kecamatan Sukra - Indramayu', '', 1, 'active', 0, NULL, 'pppoe_username', 'alijayanet@gmail.com', '', '2025-11-12 06:34:44', NULL),
(6, 2, 'Alijaya Hotspot5', '087828060555', 'alijayanet@gmail.com', 'Jln. Pantai Tanjungpura Desa Ujunggebang Kecamatan Sukra - Indramayu', '', 1, 'active', 0, NULL, 'pppoe_username', 'alijayanet@gmail.com', '', '2025-11-12 07:03:45', NULL),
(7, 1, 'tester 6', '087828060666', 'alijayanet@gmail.com', 'pantai tanjungpura\ndesa Ujunggebang Kecamatan Sukra Kabupaten Indramayu Jawa Barat', '', 1, 'active', 0, NULL, 'pppoe_username', 'alijayanet3@gmail.com', '', '2025-11-12 07:38:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `billing_invoices`
--

CREATE TABLE `billing_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `profile_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_snapshot`)),
  `period` char(7) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  `paid_at` datetime DEFAULT NULL,
  `payment_channel` varchar(100) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `paid_via` varchar(50) DEFAULT NULL,
  `paid_via_agent_id` int(10) UNSIGNED DEFAULT NULL,
  `whatsapp_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_invoices`
--

INSERT INTO `billing_invoices` (`id`, `customer_id`, `profile_snapshot`, `period`, `due_date`, `amount`, `status`, `paid_at`, `payment_channel`, `reference_number`, `paid_via`, `paid_via_agent_id`, `whatsapp_sent_at`, `created_at`, `updated_at`) VALUES
(1, 1, '[]', '2025-11', '2025-11-18', 110000.00, 'paid', '2025-11-12 01:06:39', 'admin_manual', 'ADMIN-1762909589421', NULL, NULL, NULL, '2025-11-11 04:29:20', '2025-11-12 01:06:39'),
(3, 2, '[]', '2025-11', '2025-11-19', 110000.00, 'paid', '2025-11-12 05:19:16', 'agent_balance', 'AG-AG001', 'agent_balance', 1, NULL, '2025-11-12 04:11:02', '2025-11-12 04:19:16'),
(4, 3, '[]', '2025-11', '2025-11-19', 100000.00, 'paid', '2025-11-12 05:24:05', 'agent_balance', 'AG-AG001', 'agent_balance', 1, NULL, '2025-11-12 04:22:16', '2025-11-12 04:24:05'),
(5, 4, '[]', '2025-11', '2025-11-19', 110000.00, 'paid', '2025-11-12 05:34:27', 'agent_balance', 'AG-AG001', 'agent_balance', 1, NULL, '2025-11-12 04:34:06', '2025-11-12 04:34:27'),
(6, 5, '[]', '2025-11', '2025-11-19', 100000.00, 'paid', '2025-11-12 07:36:01', 'agent_balance', 'AG-AG001', 'agent_balance', 1, NULL, '2025-11-12 06:35:20', '2025-11-12 06:36:01'),
(7, 6, '[]', '2025-11', '2025-11-19', 165000.00, 'paid', '2025-11-12 08:04:27', 'agent_balance', 'AG-AG001', 'agent_balance', 1, NULL, '2025-11-12 07:04:05', '2025-11-12 07:04:27'),
(8, 7, '[]', '2025-11', '2025-11-19', 110000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-12 07:38:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `billing_logs`
--

CREATE TABLE `billing_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `event` varchar(100) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_logs`
--

INSERT INTO `billing_logs` (`id`, `invoice_id`, `customer_id`, `event`, `metadata`, `created_at`) VALUES
(2, 1, 1, 'invoice_paid', '{\"channel\":\"admin_manual\",\"reference\":\"ADMIN-1762909589421\",\"paid_via\":null,\"paid_via_agent_id\":null}', '2025-11-12 01:06:40'),
(3, 3, 2, 'invoice_paid', '{\"channel\":\"agent_balance\",\"reference\":\"AG-AG001\",\"paid_via\":\"agent_balance\",\"paid_via_agent_id\":1}', '2025-11-12 04:19:16'),
(4, NULL, 2, 'customer_isolation_restored', '{\"reason\":\"invoice_paid_by_agent\"}', '2025-11-12 04:19:16'),
(5, 3, 2, 'invoice_paid_by_agent', '{\"agent_id\":1,\"amount\":\"110000.00\",\"fee\":0}', '2025-11-12 04:19:16'),
(6, 4, 3, 'invoice_paid', '{\"channel\":\"agent_balance\",\"reference\":\"AG-AG001\",\"paid_via\":\"agent_balance\",\"paid_via_agent_id\":1}', '2025-11-12 04:24:05'),
(7, NULL, 3, 'customer_isolation_restored', '{\"reason\":\"invoice_paid_by_agent\"}', '2025-11-12 04:24:05'),
(8, 4, 3, 'invoice_paid_by_agent', '{\"agent_id\":1,\"amount\":\"100000.00\",\"fee\":0}', '2025-11-12 04:24:05'),
(9, 5, 4, 'invoice_paid', '{\"channel\":\"agent_balance\",\"reference\":\"AG-AG001\",\"paid_via\":\"agent_balance\",\"paid_via_agent_id\":1}', '2025-11-12 04:34:27'),
(10, NULL, 4, 'customer_isolation_restored', '{\"reason\":\"invoice_paid_by_agent\"}', '2025-11-12 04:34:27'),
(11, 5, 4, 'invoice_paid_by_agent', '{\"agent_id\":1,\"amount\":\"110000.00\",\"fee\":0}', '2025-11-12 04:34:27'),
(12, 6, 5, 'invoice_paid', '{\"channel\":\"agent_balance\",\"reference\":\"AG-AG001\",\"paid_via\":\"agent_balance\",\"paid_via_agent_id\":1}', '2025-11-12 06:36:01'),
(13, NULL, 5, 'customer_isolation_restored', '{\"reason\":\"invoice_paid_by_agent\"}', '2025-11-12 06:36:01'),
(14, 6, 5, 'invoice_paid_by_agent', '{\"agent_id\":1,\"amount\":\"100000.00\",\"fee\":0}', '2025-11-12 06:36:01'),
(15, 7, 6, 'invoice_paid', '{\"channel\":\"agent_balance\",\"reference\":\"AG-AG001\",\"paid_via\":\"agent_balance\",\"paid_via_agent_id\":1}', '2025-11-12 07:04:27'),
(16, NULL, 6, 'customer_isolation_restored', '{\"reason\":\"invoice_paid_by_agent\"}', '2025-11-12 07:04:27'),
(17, 7, 6, 'invoice_paid_by_agent', '{\"agent_id\":1,\"amount\":\"165000.00\",\"fee\":-5000}', '2025-11-12 07:04:27');

-- --------------------------------------------------------

--
-- Table structure for table `billing_payments`
--

CREATE TABLE `billing_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `method` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_payments`
--

INSERT INTO `billing_payments` (`id`, `invoice_id`, `amount`, `payment_date`, `method`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 110000.00, '2025-11-12 01:06:39', 'admin_manual', NULL, NULL, '2025-11-12 01:06:40'),
(2, 3, 110000.00, '2025-11-12 05:19:16', 'agent_balance', 'Paid by agent: Agent Demo', 0, '2025-11-12 04:19:16'),
(3, 4, 100000.00, '2025-11-12 05:24:05', 'agent_balance', 'Paid by agent: Agent Demo', 0, '2025-11-12 04:24:05'),
(4, 5, 110000.00, '2025-11-12 05:34:26', 'agent_balance', 'Paid by agent: Agent Demo', 0, '2025-11-12 04:34:26'),
(5, 6, 100000.00, '2025-11-12 07:36:00', 'agent_balance', 'Paid by agent: Agent Demo', 0, '2025-11-12 06:36:00'),
(6, 7, 165000.00, '2025-11-12 08:04:27', 'agent_balance', 'Paid by agent: Agent Demo', 0, '2025-11-12 07:04:27');

-- --------------------------------------------------------

--
-- Table structure for table `billing_portal_otps`
--

CREATE TABLE `billing_portal_otps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `identifier` varchar(191) NOT NULL,
  `otp_code` varchar(191) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `sent_via` enum('whatsapp','sms','email') DEFAULT 'whatsapp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_portal_otps`
--

INSERT INTO `billing_portal_otps` (`id`, `customer_id`, `identifier`, `otp_code`, `expires_at`, `attempts`, `max_attempts`, `sent_via`, `created_at`) VALUES
(1, 1, '081947215703', '$2y$10$Tyz9S7cIfUJimM0H8JgrEeA2X29TNN25jLWZnaS.11Vp0rKe2OKqK', '2025-11-10 13:27:07', 0, 5, 'whatsapp', '2025-11-10 06:22:07');

-- --------------------------------------------------------

--
-- Table structure for table `billing_profiles`
--

CREATE TABLE `billing_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `price_monthly` decimal(12,2) NOT NULL DEFAULT 0.00,
  `speed_label` varchar(100) DEFAULT NULL,
  `mikrotik_profile_normal` varchar(100) NOT NULL,
  `mikrotik_profile_isolation` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_profiles`
--

INSERT INTO `billing_profiles` (`id`, `profile_name`, `price_monthly`, `speed_label`, `mikrotik_profile_normal`, `mikrotik_profile_isolation`, `description`, `created_at`, `updated_at`) VALUES
(1, 'BRONZE', 110000.00, 'Upto 5Mbps', 'BRONZE', 'ISOLIR', '', '2025-11-10 06:21:05', NULL),
(2, 'SILVER', 165000.00, 'Upto 5Mbps', 'silver', 'isolir', '', '2025-11-12 07:03:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `billing_settings`
--

CREATE TABLE `billing_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_settings`
--

INSERT INTO `billing_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('billing_isolation_delay', '1', '2025-11-10 06:00:09'),
('billing_portal_base_url', '', '2025-11-10 06:00:09'),
('billing_portal_contact_body', 'Jam operasional: 08.00 - 22.00', '2025-11-10 06:00:09'),
('billing_portal_contact_email', 'support@ispanda.com', '2025-11-10 06:00:09'),
('billing_portal_contact_heading', 'Butuh bantuan? Hubungi Admin ISP', '2025-11-10 06:00:09'),
('billing_portal_contact_whatsapp', '081234567890', '2025-11-10 06:00:09'),
('billing_portal_otp_digits', '6', '2025-11-10 06:00:09'),
('billing_portal_otp_enabled', '1', '2025-11-11 21:54:33'),
('billing_portal_otp_expiry_minutes', '5', '2025-11-10 06:00:09'),
('billing_portal_otp_max_attempts', '5', '2025-11-10 06:00:09'),
('billing_reminder_days_before', '3,1', '2025-11-10 06:00:09');

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_agent_sales`
-- (See below for the actual view)
--
CREATE TABLE `daily_agent_sales` (
`sale_date` date
,`agent_code` varchar(20)
,`agent_name` varchar(100)
,`profile_name` varchar(100)
,`voucher_count` bigint(21)
,`total_buy_price` decimal(37,2)
,`total_sell_price` decimal(37,2)
,`total_profit` decimal(38,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `digiflazz_products`
--

CREATE TABLE `digiflazz_products` (
  `id` int(11) NOT NULL,
  `buyer_sku_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `type` enum('prepaid','postpaid') DEFAULT 'prepaid',
  `price` int(11) NOT NULL,
  `buyer_price` int(11) DEFAULT NULL,
  `seller_price` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `desc_header` varchar(150) DEFAULT NULL,
  `desc_footer` text DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `allow_markup` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `digiflazz_transactions`
--

CREATE TABLE `digiflazz_transactions` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `ref_id` varchar(60) NOT NULL,
  `buyer_sku_code` varchar(50) NOT NULL,
  `customer_no` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `status` enum('pending','success','failed','refund') DEFAULT 'pending',
  `message` varchar(255) DEFAULT NULL,
  `price` int(11) DEFAULT 0,
  `sell_price` int(11) DEFAULT 0,
  `serial_number` varchar(100) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `whatsapp_notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateway_config`
--

CREATE TABLE `payment_gateway_config` (
  `id` int(11) NOT NULL,
  `gateway_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_sandbox` tinyint(1) DEFAULT 1,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `merchant_code` varchar(100) DEFAULT NULL,
  `callback_token` varchar(255) DEFAULT NULL,
  `config_json` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_gateway_config`
--

INSERT INTO `payment_gateway_config` (`id`, `gateway_name`, `is_active`, `is_sandbox`, `api_key`, `api_secret`, `merchant_code`, `callback_token`, `config_json`, `created_at`, `updated_at`) VALUES
(1, 'tripay', 1, 1, NULL, NULL, NULL, NULL, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `gateway_name` varchar(50) NOT NULL,
  `method_code` varchar(50) NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `method_type` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `type` varchar(50) NOT NULL DEFAULT '',
  `display_name` varchar(100) NOT NULL DEFAULT '',
  `icon` varchar(100) DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `admin_fee_type` enum('percentage','fixed','flat','percent') DEFAULT 'fixed',
  `admin_fee_value` decimal(10,2) DEFAULT 0.00,
  `min_amount` decimal(10,2) DEFAULT 0.00,
  `max_amount` decimal(12,2) DEFAULT 999999999.99,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `config` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `gateway_name`, `method_code`, `method_name`, `method_type`, `name`, `type`, `display_name`, `icon`, `icon_url`, `admin_fee_type`, `admin_fee_value`, `min_amount`, `max_amount`, `is_active`, `sort_order`, `config`, `created_at`, `updated_at`) VALUES
(1, 'tripay', 'QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 'QRIS', 'qris', 'QRIS (Semua Bank & E-Wallet)', 'fa-qrcode', NULL, 'percentage', 0.00, 10000.00, 5000000.00, 1, 1, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(2, 'tripay', 'BRIVA', 'BRI Virtual Account', 'va', 'BRIVA', 'va', 'BRI Virtual Account', 'fa-bank', NULL, 'fixed', 4000.00, 10000.00, 5000000.00, 1, 2, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(3, 'tripay', 'BNIVA', 'BNI Virtual Account', 'va', 'BNIVA', 'va', 'BNI Virtual Account', 'fa-bank', NULL, 'fixed', 4000.00, 10000.00, 5000000.00, 1, 3, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(4, 'tripay', 'BCAVA', 'BCA Virtual Account', 'va', 'BCAVA', 'va', 'BCA Virtual Account', 'fa-bank', NULL, 'fixed', 4000.00, 10000.00, 5000000.00, 1, 4, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(5, 'tripay', 'MANDIRIVA', 'Mandiri Virtual Account', 'va', 'MANDIRIVA', 'va', 'Mandiri Virtual Account', 'fa-bank', NULL, 'fixed', 4000.00, 10000.00, 5000000.00, 1, 5, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(6, 'tripay', 'PERMATAVA', 'Permata Virtual Account', 'va', 'PERMATAVA', 'va', 'Permata Virtual Account', 'fa-bank', NULL, 'fixed', 4000.00, 10000.00, 5000000.00, 1, 6, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(7, 'tripay', 'OVO', 'OVO', 'ewallet', 'OVO', 'ewallet', 'OVO', 'fa-mobile', NULL, 'percentage', 2.50, 10000.00, 2000000.00, 1, 7, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(8, 'tripay', 'DANA', 'DANA', 'ewallet', 'DANA', 'ewallet', 'DANA', 'fa-mobile', NULL, 'percentage', 2.50, 10000.00, 2000000.00, 1, 8, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(9, 'tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 'SHOPEEPAY', 'ewallet', 'ShopeePay', 'fa-mobile', NULL, 'percentage', 2.50, 10000.00, 2000000.00, 1, 9, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(10, 'tripay', 'LINKAJA', 'LinkAja', 'ewallet', 'LINKAJA', 'ewallet', 'LinkAja', 'fa-mobile', NULL, 'percentage', 2.50, 10000.00, 2000000.00, 1, 10, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(11, 'tripay', 'ALFAMART', 'Alfamart', 'retail', 'ALFAMART', 'retail', 'Alfamart', 'fa-shopping-cart', NULL, 'fixed', 5000.00, 10000.00, 5000000.00, 1, 11, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(12, 'tripay', 'INDOMARET', 'Indomaret', 'retail', 'INDOMARET', 'retail', 'Indomaret', 'fa-shopping-cart', NULL, 'fixed', 5000.00, 10000.00, 5000000.00, 1, 12, NULL, '2025-11-10 06:00:08', '2025-11-10 06:00:08');

-- --------------------------------------------------------

--
-- Table structure for table `public_sales`
--

CREATE TABLE `public_sales` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `agent_id` int(11) NOT NULL DEFAULT 1,
  `profile_id` int(11) NOT NULL DEFAULT 1,
  `customer_name` varchar(100) NOT NULL DEFAULT '',
  `customer_phone` varchar(20) NOT NULL DEFAULT '',
  `customer_email` varchar(100) DEFAULT NULL,
  `profile_name` varchar(100) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `admin_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gateway_name` varchar(50) NOT NULL DEFAULT '',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_channel` varchar(50) DEFAULT NULL,
  `payment_url` text DEFAULT NULL,
  `qr_url` text DEFAULT NULL,
  `virtual_account` varchar(50) DEFAULT NULL,
  `payment_instructions` text DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `voucher_code` varchar(50) DEFAULT NULL,
  `voucher_password` varchar(50) DEFAULT NULL,
  `voucher_generated_at` datetime DEFAULT NULL,
  `voucher_sent_at` datetime DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `callback_data` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `public_sales`
--

INSERT INTO `public_sales` (`id`, `transaction_id`, `payment_reference`, `agent_id`, `profile_id`, `customer_name`, `customer_phone`, `customer_email`, `profile_name`, `price`, `admin_fee`, `total_amount`, `gateway_name`, `payment_method`, `payment_channel`, `payment_url`, `qr_url`, `virtual_account`, `payment_instructions`, `expired_at`, `paid_at`, `status`, `voucher_code`, `voucher_password`, `voucher_generated_at`, `voucher_sent_at`, `ip_address`, `user_agent`, `callback_data`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'TRX-1762824564-48634', NULL, 1, 3, 'maul', '628989718552', 'mauljasmay@gmail.com', '10k', 10000.00, 0.00, 10000.00, 'tripay', 'QRIS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, '43.243.142.218', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/22F76 Safari/604.1', NULL, NULL, '2025-11-11 01:29:24', '2025-11-11 01:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `site_pages`
--

CREATE TABLE `site_pages` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(50) NOT NULL,
  `page_title` varchar(200) NOT NULL,
  `page_content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_pages`
--

INSERT INTO `site_pages` (`id`, `page_slug`, `page_title`, `page_content`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'tos', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan</h3><p>Sesuaikan konten ini.</p>', 1, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(2, 'privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3><p>Sesuaikan konten ini.</p>', 1, '2025-11-10 06:00:08', '2025-11-10 06:00:08'),
(3, 'faq', 'FAQ', '<h3>FAQ</h3><p>Sesuaikan konten ini.</p>', 1, '2025-11-10 06:00:08', '2025-11-10 06:00:08');

-- --------------------------------------------------------

--
-- Table structure for table `voucher_settings`
--

CREATE TABLE `voucher_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `agent_summary`
--
DROP TABLE IF EXISTS `agent_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_al52memi2d`@`localhost` SQL SECURITY DEFINER VIEW `agent_summary`  AS SELECT `a`.`id` AS `id`, `a`.`agent_code` AS `agent_code`, `a`.`agent_name` AS `agent_name`, `a`.`phone` AS `phone`, `a`.`balance` AS `balance`, `a`.`status` AS `status`, `a`.`level` AS `level`, count(distinct `av`.`id`) AS `total_vouchers`, count(distinct case when `av`.`status` = 'used' then `av`.`id` end) AS `used_vouchers`, sum(case when `at`.`transaction_type` = 'topup' then `at`.`amount` else 0 end) AS `total_topup`, sum(case when `at`.`transaction_type` = 'generate' then `at`.`amount` else 0 end) AS `total_spent`, coalesce(sum(`ac`.`commission_amount`),0) AS `total_commission`, `a`.`created_at` AS `created_at`, `a`.`last_login` AS `last_login` FROM (((`agents` `a` left join `agent_vouchers` `av` on(`a`.`id` = `av`.`agent_id`)) left join `agent_transactions` `at` on(`a`.`id` = `at`.`agent_id`)) left join `agent_commissions` `ac` on(`a`.`id` = `ac`.`agent_id` and `ac`.`status` = 'paid')) GROUP BY `a`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `daily_agent_sales`
--
DROP TABLE IF EXISTS `daily_agent_sales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_al52memi2d`@`localhost` SQL SECURITY DEFINER VIEW `daily_agent_sales`  AS SELECT cast(`av`.`created_at` as date) AS `sale_date`, `a`.`agent_code` AS `agent_code`, `a`.`agent_name` AS `agent_name`, `av`.`profile_name` AS `profile_name`, count(0) AS `voucher_count`, sum(`av`.`buy_price`) AS `total_buy_price`, sum(`av`.`sell_price`) AS `total_sell_price`, sum(`av`.`sell_price` - `av`.`buy_price`) AS `total_profit` FROM (`agent_vouchers` `av` join `agents` `a` on(`av`.`agent_id` = `a`.`id`)) WHERE `av`.`status` <> 'deleted' GROUP BY cast(`av`.`created_at` as date), `a`.`id`, `av`.`profile_name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `agent_code` (`agent_code`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_agent_code` (`agent_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `agent_billing_payments`
--
ALTER TABLE `agent_billing_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_agent_invoice` (`agent_id`,`invoice_id`),
  ADD KEY `fk_abp_invoice` (`invoice_id`);

--
-- Indexes for table `agent_commissions`
--
ALTER TABLE `agent_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `agent_prices`
--
ALTER TABLE `agent_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_agent_profile` (`agent_id`,`profile_name`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_profile` (`profile_name`);

--
-- Indexes for table `agent_profile_pricing`
--
ALTER TABLE `agent_profile_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_agent_profile` (`agent_id`,`profile_name`);

--
-- Indexes for table `agent_settings`
--
ALTER TABLE `agent_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `fk_agent_settings_agent` (`agent_id`);

--
-- Indexes for table `agent_topup_requests`
--
ALTER TABLE `agent_topup_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_at` (`requested_at`);

--
-- Indexes for table `agent_transactions`
--
ALTER TABLE `agent_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_reference` (`reference_id`),
  ADD KEY `idx_agent_transactions_date` (`created_at`,`agent_id`);

--
-- Indexes for table `agent_vouchers`
--
ALTER TABLE `agent_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer_phone` (`customer_phone`),
  ADD KEY `idx_agent_vouchers_date` (`created_at`,`agent_id`),
  ADD KEY `idx_agent_vouchers_profile` (`profile_name`,`status`);

--
-- Indexes for table `billing_customers`
--
ALTER TABLE `billing_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_profile_id` (`profile_id`),
  ADD KEY `idx_billing_day` (`billing_day`);

--
-- Indexes for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `billing_logs`
--
ALTER TABLE `billing_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_id` (`invoice_id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `billing_payments`
--
ALTER TABLE `billing_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_id` (`invoice_id`);

--
-- Indexes for table `billing_portal_otps`
--
ALTER TABLE `billing_portal_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_identifier` (`customer_id`,`identifier`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `billing_profiles`
--
ALTER TABLE `billing_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_profile_name` (`profile_name`);

--
-- Indexes for table `billing_settings`
--
ALTER TABLE `billing_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `digiflazz_products`
--
ALTER TABLE `digiflazz_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sku` (`buyer_sku_code`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_brand` (`brand`);

--
-- Indexes for table `digiflazz_transactions`
--
ALTER TABLE `digiflazz_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ref` (`ref_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payment_gateway_config`
--
ALTER TABLE `payment_gateway_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_gateway` (`gateway_name`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_gateway_method` (`gateway_name`,`method_code`);

--
-- Indexes for table `public_sales`
--
ALTER TABLE `public_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_transaction` (`transaction_id`),
  ADD KEY `fk_public_sales_agent` (`agent_id`),
  ADD KEY `fk_public_sales_profile` (`profile_id`),
  ADD KEY `idx_payment_reference` (`payment_reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer_phone` (`customer_phone`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `site_pages`
--
ALTER TABLE `site_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_slug` (`page_slug`);

--
-- Indexes for table `voucher_settings`
--
ALTER TABLE `voucher_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `agent_billing_payments`
--
ALTER TABLE `agent_billing_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `agent_commissions`
--
ALTER TABLE `agent_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_prices`
--
ALTER TABLE `agent_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `agent_profile_pricing`
--
ALTER TABLE `agent_profile_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `agent_settings`
--
ALTER TABLE `agent_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `agent_topup_requests`
--
ALTER TABLE `agent_topup_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_transactions`
--
ALTER TABLE `agent_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `agent_vouchers`
--
ALTER TABLE `agent_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_customers`
--
ALTER TABLE `billing_customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `billing_logs`
--
ALTER TABLE `billing_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `billing_payments`
--
ALTER TABLE `billing_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `billing_portal_otps`
--
ALTER TABLE `billing_portal_otps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `billing_profiles`
--
ALTER TABLE `billing_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `digiflazz_products`
--
ALTER TABLE `digiflazz_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `digiflazz_transactions`
--
ALTER TABLE `digiflazz_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateway_config`
--
ALTER TABLE `payment_gateway_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `public_sales`
--
ALTER TABLE `public_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_pages`
--
ALTER TABLE `site_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `voucher_settings`
--
ALTER TABLE `voucher_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agent_billing_payments`
--
ALTER TABLE `agent_billing_payments`
  ADD CONSTRAINT `fk_abp_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_abp_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_commissions`
--
ALTER TABLE `agent_commissions`
  ADD CONSTRAINT `agent_commissions_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_commissions_ibfk_2` FOREIGN KEY (`voucher_id`) REFERENCES `agent_vouchers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `agent_prices`
--
ALTER TABLE `agent_prices`
  ADD CONSTRAINT `agent_prices_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_profile_pricing`
--
ALTER TABLE `agent_profile_pricing`
  ADD CONSTRAINT `fk_agent_profile_pricing_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_settings`
--
ALTER TABLE `agent_settings`
  ADD CONSTRAINT `fk_agent_settings_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_topup_requests`
--
ALTER TABLE `agent_topup_requests`
  ADD CONSTRAINT `agent_topup_requests_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_transactions`
--
ALTER TABLE `agent_transactions`
  ADD CONSTRAINT `agent_transactions_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_vouchers`
--
ALTER TABLE `agent_vouchers`
  ADD CONSTRAINT `agent_vouchers_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_vouchers_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `agent_transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing_customers`
--
ALTER TABLE `billing_customers`
  ADD CONSTRAINT `fk_billing_customers_profile` FOREIGN KEY (`profile_id`) REFERENCES `billing_profiles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  ADD CONSTRAINT `fk_billing_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `billing_logs`
--
ALTER TABLE `billing_logs`
  ADD CONSTRAINT `fk_billing_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billing_logs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `billing_payments`
--
ALTER TABLE `billing_payments`
  ADD CONSTRAINT `fk_billing_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `digiflazz_transactions`
--
ALTER TABLE `digiflazz_transactions`
  ADD CONSTRAINT `digiflazz_transactions_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `public_sales`
--
ALTER TABLE `public_sales`
  ADD CONSTRAINT `fk_public_sales_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_public_sales_profile` FOREIGN KEY (`profile_id`) REFERENCES `agent_profile_pricing` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
