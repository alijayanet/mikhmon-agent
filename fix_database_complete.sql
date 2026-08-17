-- ============================================================
-- MikhMon Agent System - Comprehensive Database Fix
-- Jalankan: mysql -u root mikhmon-server < fix_database_complete.sql
-- ============================================================

USE `mikhmon-server`;

-- ============================================================
-- BAGIAN 1: TABEL BARU YANG BELUM ADA
-- ============================================================

-- 1a. agent_topup_requests (direferensikan di agent-admin/agent_topup.php)
CREATE TABLE IF NOT EXISTS `agent_topup_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'manual',
  `payment_bank` VARCHAR(50),
  `payment_account` VARCHAR(50),
  `payment_proof` VARCHAR(255),
  `transfer_date` DATE,
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` TEXT,
  `processed_by` VARCHAR(50),
  `processed_at` DATETIME,
  `reference_id` VARCHAR(100),
  `gateway_name` VARCHAR(50),
  `gateway_ref` VARCHAR(100),
  `payment_url` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_agent_id` (`agent_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1b. agent_summary (direferensikan di dashboard/laporan agen)
CREATE TABLE IF NOT EXISTS `agent_summary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `period` CHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `total_transactions` INT DEFAULT 0,
  `total_vouchers` INT DEFAULT 0,
  `total_revenue` DECIMAL(15,2) DEFAULT 0,
  `total_commission` DECIMAL(15,2) DEFAULT 0,
  `total_topup` DECIMAL(15,2) DEFAULT 0,
  `balance_start` DECIMAL(15,2) DEFAULT 0,
  `balance_end` DECIMAL(15,2) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_agent_period` (`agent_id`, `period`),
  INDEX `idx_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1c. payment_records (direferensikan di billing/payment callback)
CREATE TABLE IF NOT EXISTS `payment_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference_id` VARCHAR(100) NOT NULL,
  `transaction_type` ENUM('topup','voucher','billing') DEFAULT 'topup',
  `related_id` INT,
  `agent_id` INT,
  `customer_phone` VARCHAR(20),
  `amount` DECIMAL(15,2) NOT NULL,
  `gateway_name` VARCHAR(50),
  `payment_channel` VARCHAR(50),
  `payment_status` ENUM('pending','paid','failed','expired','refunded') DEFAULT 'pending',
  `gateway_response` TEXT,
  `paid_at` DATETIME,
  `expired_at` DATETIME,
  `callback_received_at` DATETIME,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_reference` (`reference_id`),
  INDEX `idx_agent_id` (`agent_id`),
  INDEX `idx_status` (`payment_status`),
  INDEX `idx_gateway` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1d. whatsapp_settings (direferensikan di settings/whatsapp_settings.php)
CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `description` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1e. system_settings (direferensikan di berbagai modul)
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `setting_type` VARCHAR(20) DEFAULT 'string',
  `description` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1f. genieacs_devices (direferensikan di genieacs/*)
CREATE TABLE IF NOT EXISTS `genieacs_devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `serial_number` VARCHAR(100),
  `oui` VARCHAR(20),
  `product_class` VARCHAR(100),
  `manufacturer` VARCHAR(100),
  `model` VARCHAR(100),
  `firmware` VARCHAR(100),
  `ip_address` VARCHAR(45),
  `mac_address` VARCHAR(17),
  `ssid` VARCHAR(100),
  `ssid_password` VARCHAR(100),
  `ssid_5g` VARCHAR(100),
  `pppoe_username` VARCHAR(100),
  `pppoe_status` ENUM('connected','disconnected','unknown') DEFAULT 'unknown',
  `signal_dbm` INT,
  `status` ENUM('online','offline','unknown') DEFAULT 'unknown',
  `last_seen` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_device_id` (`device_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_pppoe_username` (`pppoe_username`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1g. notification_queue (antrian notifikasi WhatsApp/Telegram)
CREATE TABLE IF NOT EXISTS `notification_queue` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('whatsapp','telegram','email','sms') NOT NULL DEFAULT 'whatsapp',
  `recipient` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `context` VARCHAR(100) COMMENT 'Konteks: invoice_reminder, voucher_sent, topup_approved, dll',
  `related_id` INT,
  `status` ENUM('pending','sent','failed','skipped') DEFAULT 'pending',
  `attempts` INT DEFAULT 0,
  `max_attempts` INT DEFAULT 3,
  `sent_at` DATETIME,
  `scheduled_at` DATETIME,
  `error_message` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1h. cron_log (log eksekusi cronjob billing/isolation)
CREATE TABLE IF NOT EXISTS `cron_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_name` VARCHAR(100) NOT NULL,
  `status` ENUM('success','error','running') DEFAULT 'running',
  `message` TEXT,
  `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME,
  `duration_ms` INT,
  INDEX `idx_job_name` (`job_name`),
  INDEX `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1i. digiflazz_price_markup (konfigurasi markup harga per agen)
CREATE TABLE IF NOT EXISTS `digiflazz_price_markup` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `buyer_sku_code` VARCHAR(50) NOT NULL,
  `markup_type` ENUM('fixed','percentage') DEFAULT 'fixed',
  `markup_value` DECIMAL(10,2) DEFAULT 0,
  `sell_price` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_agent_sku` (`agent_id`, `buyer_sku_code`),
  INDEX `idx_agent_id` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1j. agent_login_logs (log login agen untuk keamanan)
CREATE TABLE IF NOT EXISTS `agent_login_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT,
  `agent_code` VARCHAR(20),
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `status` ENUM('success','failed','locked') DEFAULT 'success',
  `fail_reason` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_agent_id` (`agent_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BAGIAN 2: KOLOM TAMBAHAN YANG MUNGKIN BELUM ADA
-- ============================================================

-- 2a. agents: login_token & token_expires (untuk auth agen panel)
ALTER TABLE `agents`
  ADD COLUMN IF NOT EXISTS `whatsapp_number` VARCHAR(20) DEFAULT NULL AFTER `telegram_chat_id`,
  ADD COLUMN IF NOT EXISTS `login_token` VARCHAR(64) DEFAULT NULL AFTER `last_login`,
  ADD COLUMN IF NOT EXISTS `token_expires_at` DATETIME DEFAULT NULL AFTER `login_token`,
  ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT DEFAULT 0 AFTER `token_expires_at`,
  ADD COLUMN IF NOT EXISTS `locked_until` DATETIME DEFAULT NULL AFTER `failed_login_attempts`;

-- 2b. billing_customers: kolom untuk integrasi GenieACS & PPPoE
ALTER TABLE `billing_customers`
  ADD COLUMN IF NOT EXISTS `mikrotik_username` VARCHAR(100) DEFAULT NULL AFTER `genieacs_pppoe_username`,
  ADD COLUMN IF NOT EXISTS `genieacs_device_id` VARCHAR(255) DEFAULT NULL AFTER `mikrotik_username`,
  ADD COLUMN IF NOT EXISTS `agent_id` INT UNSIGNED DEFAULT NULL AFTER `profile_id`;

-- 2c. billing_invoices: tracking gateway pembayaran
ALTER TABLE `billing_invoices`
  ADD COLUMN IF NOT EXISTS `gateway_name` VARCHAR(50) DEFAULT NULL AFTER `payment_channel`,
  ADD COLUMN IF NOT EXISTS `gateway_ref` VARCHAR(100) DEFAULT NULL AFTER `gateway_name`,
  ADD COLUMN IF NOT EXISTS `payment_proof` VARCHAR(255) DEFAULT NULL AFTER `gateway_ref`;

-- 2d. public_sales: flag notifikasi WA & Telegram
ALTER TABLE `public_sales`
  ADD COLUMN IF NOT EXISTS `whatsapp_sent` TINYINT(1) DEFAULT 0 AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `telegram_sent` TINYINT(1) DEFAULT 0 AFTER `whatsapp_sent`,
  ADD COLUMN IF NOT EXISTS `retry_count` INT DEFAULT 0 AFTER `telegram_sent`;

-- 2e. agent_transactions: kolom untuk transaksi Digiflazz & Topup
ALTER TABLE `agent_transactions`
  ADD COLUMN IF NOT EXISTS `gateway_name` VARCHAR(50) DEFAULT NULL AFTER `reference_id`,
  ADD COLUMN IF NOT EXISTS `source` ENUM('web','whatsapp','telegram','api','system') DEFAULT 'web' AFTER `gateway_name`;

-- 2f. payment_gateway_config: webhook URL (untuk konfigurasi callback)
ALTER TABLE `payment_gateway_config`
  ADD COLUMN IF NOT EXISTS `webhook_url` VARCHAR(255) DEFAULT NULL AFTER `callback_token`,
  ADD COLUMN IF NOT EXISTS `return_url` VARCHAR(255) DEFAULT NULL AFTER `webhook_url`,
  ADD COLUMN IF NOT EXISTS `min_amount` DECIMAL(12,2) DEFAULT 10000 AFTER `return_url`,
  ADD COLUMN IF NOT EXISTS `max_amount` DECIMAL(12,2) DEFAULT 100000000 AFTER `min_amount`;

-- 2g. agents: agent_profile_pricing - user_type perlu ENUM yang lengkap
-- (sudah ada, tidak perlu diubah)

-- ============================================================
-- BAGIAN 3: DEFAULT DATA YANG SERING HILANG
-- ============================================================

-- 3a. whatsapp_settings defaults
INSERT IGNORE INTO `whatsapp_settings` (`setting_key`, `setting_value`, `description`) VALUES
('wa_gateway',          'fonnte',                'Gateway WhatsApp: fonnte/wablas/woowa/mpwa'),
('wa_token',            '',                      'API Token/Key WhatsApp Gateway'),
('wa_sender',           '',                      'Nomor pengirim WhatsApp (format: 628xxx)'),
('wa_enabled',          '0',                     'Aktifkan integrasi WhatsApp (1=ya, 0=tidak)'),
('wa_otp_enabled',      '0',                     'Aktifkan OTP via WhatsApp'),
('wa_notif_topup',      '1',                     'Notif topup approved ke agen'),
('wa_notif_voucher',    '1',                     'Notif voucher ke customer'),
('wa_notif_invoice',    '1',                     'Notif tagihan billing ke pelanggan'),
('wa_notif_overdue',    '1',                     'Notif tagihan jatuh tempo'),
('wa_reminder_days',    '3,1',                   'Hari sebelum jatuh tempo untuk kirim reminder');

-- 3b. system_settings defaults
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('app_name',            'MikhMon Agent',         'string',  'Nama Aplikasi'),
('app_version',         '2.0',                   'string',  'Versi Aplikasi'),
('app_url',             '',                      'string',  'URL Aplikasi (https://domain.com)'),
('maintenance_mode',    '0',                     'boolean', 'Mode maintenance'),
('debug_mode',          '0',                     'boolean', 'Mode debug (1=aktif)'),
('timezone',            'Asia/Jakarta',           'string',  'Zona waktu server'),
('currency',            'IDR',                   'string',  'Mata uang (IDR/USD)'),
('currency_symbol',     'Rp',                    'string',  'Simbol mata uang');

-- 3c. billing_settings defaults yang sering hilang
INSERT IGNORE INTO `billing_settings` (`setting_key`, `setting_value`) VALUES
('grace_period_days',       '3'),
('isolation_auto',          '1'),
('invoice_prefix',          'INV'),
('wa_reminder_enabled',     '1'),
('wa_reminder_days',        '3,1'),
('overdue_action',          'isolate'),
('invoice_generate_day',    '1'),
('whatsapp_template_invoice', ''),
('whatsapp_template_reminder', ''),
('genieacs_url',            ''),
('genieacs_username',       'admin'),
('genieacs_password',       '');

-- 3d. voucher_settings defaults
INSERT IGNORE INTO `voucher_settings` (`setting_key`, `setting_value`, `description`) VALUES
('username_length',         '8',            'Panjang username voucher'),
('password_length',         '6',            'Panjang password voucher'),
('username_type',           'alphanumeric', 'Tipe karakter username: numeric/alpha/alphanumeric'),
('password_type',           'alphanumeric', 'Tipe karakter password: numeric/alpha/alphanumeric'),
('username_uppercase',      '1',            'Gunakan huruf kapital'),
('username_prefix',         '',             'Prefix username voucher'),
('username_same_as_pass',   '0',            'Username sama dengan password'),
('print_template',          'default',      'Template cetak voucher'),
('qr_enabled',              '1',            'Tampilkan QR code di voucher');

-- 3e. telegram_settings defaults
INSERT IGNORE INTO `telegram_settings` (`setting_key`, `setting_value`) VALUES
('telegram_enabled',          '0'),
('telegram_bot_token',        ''),
('telegram_admin_chat_ids',   ''),
('telegram_webhook_mode',     '1'),
('telegram_notif_topup',      '1'),
('telegram_notif_voucher',    '1'),
('telegram_notif_invoice',    '1'),
('telegram_notif_login',      '0');

-- ============================================================
-- BAGIAN 4: INDEX TAMBAHAN UNTUK PERFORMA
-- ============================================================

-- Index untuk pencarian agent berdasarkan telegram
ALTER TABLE `agents`
  ADD INDEX IF NOT EXISTS `idx_telegram_chat_id` (`telegram_chat_id`),
  ADD INDEX IF NOT EXISTS `idx_phone` (`phone`);

-- Index untuk billing_invoices
ALTER TABLE `billing_invoices`
  ADD INDEX IF NOT EXISTS `idx_due_date` (`due_date`),
  ADD INDEX IF NOT EXISTS `idx_period` (`period`);

-- Index untuk billing_customers
ALTER TABLE `billing_customers`
  ADD INDEX IF NOT EXISTS `idx_phone` (`phone`),
  ADD INDEX IF NOT EXISTS `idx_status_billing_day` (`status`, `billing_day`);

-- ============================================================
-- VERIFIKASI HASIL
-- ============================================================
SELECT '============ HASIL AUDIT DATABASE ============' AS info;

SELECT 
  table_name AS `Tabel`,
  table_rows AS `Baris`,
  ROUND((data_length + index_length) / 1024, 1) AS `Ukuran_KB`,
  CASE WHEN table_rows > 0 THEN '✅ Ada Data' ELSE '⚠ Kosong' END AS `Status`
FROM information_schema.tables 
WHERE table_schema = 'mikhmon-server'
ORDER BY table_name;

SELECT 
  COUNT(*) AS total_tables 
FROM information_schema.tables 
WHERE table_schema = 'mikhmon-server';

SELECT '============ SELESAI ============' AS info;
