<?php
/**
 * Global Fix Utility for MikhMon Billing & Agent Modules
 * ------------------------------------------------------
 * Jalankan file ini sekali untuk memastikan struktur tabel penting
 * (billing, agent, public sales, payment gateway, WhatsApp OTP) sudah
 * sesuai dengan implementasi terbaru beserta data default minimalnya.
 *
 * Keamanan: akses dibatasi dengan parameter ?key=fix-all-2024
 */

$securityKey = $_GET['key'] ?? ($_SERVER['argv'][1] ?? '');
if ($securityKey !== 'fix-all-2024') {
    exit("Access denied. Tambahkan ?key=fix-all-2024 pada URL atau argumen CLI.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(600);
	require_once __DIR__ . '/include/db_config.php';

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    exit("Gagal konek database: " . $e->getMessage() . "\n");
}

/** @var PDO $pdo */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure database exists and is selected (for fresh install)
try {
    $dbName = defined('DB_NAME') ? DB_NAME : 'mikhmon_agents';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
} catch (PDOException $e) {
    exit("Gagal membuat/memilih database: " . $e->getMessage() . "\n");
}

function logMessage(string $message, string $status = 'info'): void
{
    $prefix = [
        'info' => '[*] ',
        'ok' => '[OK] ',
        'warn' => '[!!] ',
        'error' => '[XX] ',
    ][$status] ?? '[*] ';

    echo $prefix . $message . (PHP_SAPI === 'cli' ? "\n" : '<br>');
    flush();
}

function tableExists(PDO $pdo, string $table): bool
{
    $pattern = $pdo->quote($table);
    return (bool)$pdo->query("SHOW TABLES LIKE {$pattern}")->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $pattern = $pdo->quote($column);
    $tableSafe = str_replace('`', '``', $table);
    return (bool)$pdo->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE {$pattern}")->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $pattern = $pdo->quote($index);
    $tableSafe = str_replace('`', '``', $table);
    return (bool)$pdo->query("SHOW INDEX FROM `{$tableSafe}` WHERE Key_name = {$pattern}")->fetchColumn();
}

function ensureTable(PDO $pdo, string $name, string $ddl): void
{
    if (!tableExists($pdo, $name)) {
        logMessage("Membuat tabel {$name} ...", 'info');
        $pdo->exec($ddl);
        logMessage("Tabel {$name} dibuat", 'ok');
    } else {
        logMessage("Tabel {$name} sudah ada", 'info');
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition, ?string $after = null): void
{
    if (!columnExists($pdo, $table, $column)) {
        $tableSafe = str_replace('`', '``', $table);
        $afterSql = $after ? " AFTER `" . str_replace('`', '``', $after) . "`" : '';
        $sql = "ALTER TABLE `{$tableSafe}` ADD COLUMN `{$column}` {$definition}{$afterSql}";
        logMessage("Menambahkan kolom {$table}.{$column} ...", 'warn');
        $pdo->exec($sql);
        logMessage("Kolom {$table}.{$column} ditambahkan", 'ok');
    }
}

function ensureIndex(PDO $pdo, string $table, string $index, string $definition): void
{
    if (!indexExists($pdo, $table, $index)) {
        $tableSafe = str_replace('`', '``', $table);
        logMessage("Menambahkan index {$index} pada {$table} ...", 'warn');
        $pdo->exec("ALTER TABLE `{$tableSafe}` ADD {$definition}");
        logMessage("Index {$index} ditambahkan ke {$table}", 'ok');
    }
}

function upsertSetting(PDO $pdo, string $table, string $key, $value): void
{
    $stmt = $pdo->prepare("INSERT INTO {$table} (setting_key, setting_value) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function ensureAgent(PDO $pdo, string $code, string $name, string $status = 'active'): int
{
    $select = $pdo->prepare("SELECT id, agent_name, status FROM agents WHERE agent_code = :code LIMIT 1");
    $select->execute([':code' => $code]);
    $row = $select->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $id = (int)$row['id'];
        if (($row['agent_name'] ?? '') !== $name || ($row['status'] ?? '') !== $status) {
            $update = $pdo->prepare("UPDATE agents SET agent_name = :name, status = :status WHERE id = :id");
            $update->execute([
                ':name' => $name,
                ':status' => $status,
                ':id' => $id,
            ]);
        }
        return $id;
    }

    $insert = $pdo->prepare("INSERT INTO agents (agent_code, agent_name, status, phone) VALUES (:code, :name, :status, :phone)");
    $insert->execute([
        ':code' => $code,
        ':name' => $name,
        ':status' => $status,
        ':phone' => 'seed-' . strtolower($code),
    ]);

    return (int)$pdo->lastInsertId();
}

logMessage('=== Memulai perbaikan database (Billing + Agent + Public Sales) ===');

/**
 * 1. Pastikan tabel inti Agent/Public Sales tersedia
 */
logMessage('--- Memastikan struktur Agent & Public Sales ---');

$agentTables = [
    'agents' => <<<SQL
CREATE TABLE `agents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_code` VARCHAR(20) NOT NULL,
  `agent_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(20),
  `password` VARCHAR(255),
  `address` TEXT,
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `commission_rate` DECIMAL(5,2) DEFAULT 0.00,
  `status` ENUM('active','inactive','suspended') DEFAULT 'active',
  `level` ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `created_by` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,
  `notes` TEXT,
  UNIQUE KEY `unique_agent_code` (`agent_code`),
  KEY `idx_agent_code` (`agent_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'agent_settings' => <<<SQL
CREATE TABLE `agent_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `setting_type` VARCHAR(20),
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(50),
  CONSTRAINT `fk_agent_settings_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_agent_setting` (`agent_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'agent_prices' => <<<SQL
CREATE TABLE `agent_prices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `buy_price` DECIMAL(15,2) NOT NULL,
  `sell_price` DECIMAL(15,2) NOT NULL,
  `stock_limit` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_agent_prices_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_agent_profile` (`agent_id`, `profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'agent_transactions' => <<<SQL
CREATE TABLE `agent_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `transaction_type` ENUM('topup','generate','refund','commission','penalty') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `balance_before` DECIMAL(15,2) NOT NULL,
  `balance_after` DECIMAL(15,2) NOT NULL,
  `profile_name` VARCHAR(100),
  `voucher_username` VARCHAR(100),
  `voucher_password` VARCHAR(100),
  `quantity` INT,
  `description` TEXT,
  `reference_id` VARCHAR(50),
  `created_by` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  CONSTRAINT `fk_agent_transactions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
  INDEX `idx_agent_date` (`agent_id`, `created_at`),
  INDEX `idx_reference` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'payment_gateway_config' => <<<SQL
CREATE TABLE `payment_gateway_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `gateway_name` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 0,
  `is_sandbox` TINYINT(1) DEFAULT 1,
  `api_key` VARCHAR(255),
  `api_secret` VARCHAR(255),
  `merchant_code` VARCHAR(100),
  `callback_token` VARCHAR(255),
  `config_json` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_gateway` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'agent_profile_pricing' => <<<SQL
CREATE TABLE `agent_profile_pricing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `original_price` DECIMAL(10,2),
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `icon` VARCHAR(50) DEFAULT 'fa-wifi',
  `color` VARCHAR(20) DEFAULT 'blue',
  `sort_order` INT DEFAULT 0,
  `user_type` ENUM('voucher','member') DEFAULT 'voucher',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_agent_profile_pricing_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_agent_profile` (`agent_id`, `profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'public_sales' => <<<SQL
CREATE TABLE `public_sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` VARCHAR(100) NOT NULL,
  `payment_reference` VARCHAR(100),
  `agent_id` INT NOT NULL DEFAULT 1,
  `profile_id` INT NOT NULL DEFAULT 1,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT '',
  `customer_phone` VARCHAR(20) NOT NULL DEFAULT '',
  `customer_email` VARCHAR(100),
  `profile_name` VARCHAR(100) NOT NULL DEFAULT '',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `admin_fee` DECIMAL(10,2) DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `gateway_name` VARCHAR(50) NOT NULL DEFAULT '',
  `payment_method` VARCHAR(50),
  `payment_channel` VARCHAR(50),
  `payment_url` TEXT,
  `qr_url` TEXT,
  `virtual_account` VARCHAR(50),
  `payment_instructions` TEXT,
  `expired_at` DATETIME,
  `paid_at` DATETIME,
  `status` VARCHAR(20) DEFAULT 'pending',
  `voucher_code` VARCHAR(50),
  `voucher_password` VARCHAR(50),
  `voucher_generated_at` DATETIME,
  `voucher_sent_at` DATETIME,
  `ip_address` VARCHAR(50),
  `user_agent` TEXT,
  `callback_data` TEXT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_public_sales_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_public_sales_profile` FOREIGN KEY (`profile_id`) REFERENCES `agent_profile_pricing`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_transaction` (`transaction_id`),
  INDEX `idx_payment_reference` (`payment_reference`),
  INDEX `idx_status` (`status`),
  INDEX `idx_customer_phone` (`customer_phone`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'payment_methods' => <<<SQL
CREATE TABLE `payment_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `gateway_name` VARCHAR(50) NOT NULL,
  `method_code` VARCHAR(50) NOT NULL,
  `method_name` VARCHAR(100) NOT NULL,
  `method_type` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT "",
  `type` VARCHAR(50) NOT NULL DEFAULT "",
  `display_name` VARCHAR(100) NOT NULL DEFAULT "",
  `icon` VARCHAR(100),
  `icon_url` VARCHAR(255),
  `admin_fee_type` ENUM('percentage','fixed','flat','percent') DEFAULT 'fixed',
  `admin_fee_value` DECIMAL(10,2) DEFAULT 0,
  `min_amount` DECIMAL(10,2) DEFAULT 0,
  `max_amount` DECIMAL(12,2) DEFAULT 999999999.99,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `config` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_gateway_method` (`gateway_name`, `method_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'voucher_settings' => <<<SQL
CREATE TABLE `voucher_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) UNIQUE NOT NULL,
  `setting_value` TEXT,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    'site_pages' => <<<SQL
CREATE TABLE `site_pages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `page_slug` VARCHAR(50) UNIQUE NOT NULL,
  `page_title` VARCHAR(200) NOT NULL,
  `page_content` TEXT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
];

foreach ($agentTables as $table => $ddl) {
    ensureTable($pdo, $table, $ddl);
}

// Pastikan kolom-kolom tambahan agent_profile_pricing tersedia (untuk kompatibilitas lama)
ensureColumn($pdo, 'agent_profile_pricing', 'sort_order', 'INT DEFAULT 0', 'color');
ensureColumn($pdo, 'agent_profile_pricing', 'user_type', "ENUM('voucher','member') DEFAULT 'voucher'", 'sort_order');

// Payment methods tambahan kolom jika belum ada
$paymentColumns = [
    'icon_url' => 'VARCHAR(255) DEFAULT NULL AFTER `icon`',
    'config' => 'TEXT AFTER `sort_order`'
];
foreach ($paymentColumns as $column => $definition) {
    if (!columnExists($pdo, 'payment_methods', $column)) {
        logMessage("Menambahkan kolom payment_methods.{$column} ...", 'warn');
        $pdo->exec("ALTER TABLE `payment_methods` ADD COLUMN {$definition}");
        logMessage("Kolom payment_methods.{$column} ditambahkan", 'ok');
    }
}

// Pastikan agent_settings memiliki kolom agent_id (beberapa instalasi lama belum)
if (!columnExists($pdo, 'agent_settings', 'agent_id')) {
    logMessage('Menambahkan kolom agent_settings.agent_id ...', 'warn');
    $pdo->exec("ALTER TABLE `agent_settings` ADD COLUMN `agent_id` INT NOT NULL DEFAULT 1 AFTER `id`");
    $pdo->exec("ALTER TABLE `agent_settings` ADD CONSTRAINT `fk_agent_settings_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE");
    logMessage('Kolom agent_settings.agent_id ditambahkan', 'ok');
}

/**
 * 2. Seed data minimal untuk Agent/Public Sales
 */
logMessage('--- Memastikan data default Agent/Public Sales ---');

$agentDemoId = ensureAgent($pdo, 'AG001', 'Agent Demo');
$agentTesterId = ensureAgent($pdo, 'AG5136', 'tester');
$agentPublicId = ensureAgent($pdo, 'PUBLIC', 'Public Catalog');

logMessage('Pastikan entri agents default tersedia', 'ok');

$defaultAgentSettings = [
    ['agent_id' => $agentDemoId, 'key' => 'admin_whatsapp_numbers', 'value' => '6281234567890'],
    ['agent_id' => $agentDemoId, 'key' => 'agent_can_set_sell_price', 'value' => '1'],
    ['agent_id' => $agentDemoId, 'key' => 'agent_registration_enabled', 'value' => '1'],
    ['agent_id' => $agentDemoId, 'key' => 'min_topup_amount', 'value' => '50000'],
    ['agent_id' => $agentDemoId, 'key' => 'max_topup_amount', 'value' => '10000000'],
    ['agent_id' => $agentDemoId, 'key' => 'commission_enabled', 'value' => '1'],
    ['agent_id' => $agentDemoId, 'key' => 'default_commission_percent', 'value' => '5'],
    ['agent_id' => $agentDemoId, 'key' => 'whatsapp_notification_enabled', 'value' => '1'],
    ['agent_id' => $agentDemoId, 'key' => 'voucher_prefix_agent', 'value' => 'AG'],
    ['agent_id' => $agentDemoId, 'key' => 'whatsapp_gateway_url', 'value' => 'https://api.whatsapp.com'],
    ['agent_id' => $agentDemoId, 'key' => 'whatsapp_token', 'value' => ''],
];

$settingStmt = $pdo->prepare("INSERT INTO agent_settings (agent_id, setting_key, setting_value) VALUES (:agent, :key, :value)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
foreach ($defaultAgentSettings as $row) {
    $settingStmt->execute([
        ':agent' => $row['agent_id'],
        ':key' => $row['key'],
        ':value' => $row['value'],
    ]);
}
logMessage('Agent settings baseline diperbarui', 'ok');

$prices = array_filter([
    $agentDemoId ? [$agentDemoId, '3k', 2000, 3000] : null,
    $agentDemoId ? [$agentDemoId, '5k', 4000, 5000] : null,
    $agentDemoId ? [$agentDemoId, '10k', 7000, 10000] : null,
    $agentTesterId ? [$agentTesterId, '3k', 2000, 3000] : null,
    $agentTesterId ? [$agentTesterId, '5k', 4000, 5000] : null,
    $agentPublicId ? [$agentPublicId, '3k', 0, 3000] : null,
    $agentPublicId ? [$agentPublicId, '5k', 0, 5000] : null,
    $agentPublicId ? [$agentPublicId, '10k', 0, 10000] : null,
]);
$priceStmt = $pdo->prepare("INSERT INTO agent_prices (agent_id, profile_name, buy_price, sell_price)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE buy_price = VALUES(buy_price), sell_price = VALUES(sell_price)");
foreach ($prices as $row) {
    $priceStmt->execute($row);
}
logMessage('Agent prices baseline disinkronisasi', 'ok');

$publicProfiles = array_filter([
    $agentDemoId ? [$agentDemoId, '3k', 'Voucher 1 Hari', 'Voucher hotspot 1 hari', 3000, 0, 'fa-wifi', 'blue', 1] : null,
    $agentDemoId ? [$agentDemoId, '5k', 'Voucher 3 Hari', 'Voucher hotspot 3 hari', 5000, 0, 'fa-wifi', 'indigo', 2] : null,
    $agentDemoId ? [$agentDemoId, '10k', 'Voucher 7 Hari', 'Voucher hotspot 7 hari', 10000, 0, 'fa-wifi', 'purple', 3] : null,
]);
$profileStmt = $pdo->prepare("INSERT INTO agent_profile_pricing
    (agent_id, profile_name, display_name, description, price, is_featured, icon, color, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description),
        price = VALUES(price), is_featured = VALUES(is_featured), icon = VALUES(icon), color = VALUES(color),
        sort_order = VALUES(sort_order)");
foreach ($publicProfiles as $row) {
    $profileStmt->execute($row);
}
logMessage('Public profile pricing baseline disinkronisasi', 'ok');

// Payment methods Tripay default list
$tripayMethods = [
    ['tripay', 'QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 'QRIS', 'qris', 'QRIS (Semua Bank & E-Wallet)', 'fa-qrcode', 'percentage', 0, 10000, 5000000, 1, 1],
    ['tripay', 'BRIVA', 'BRI Virtual Account', 'va', 'BRIVA', 'va', 'BRI Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 2],
    ['tripay', 'BNIVA', 'BNI Virtual Account', 'va', 'BNIVA', 'va', 'BNI Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 3],
    ['tripay', 'BCAVA', 'BCA Virtual Account', 'va', 'BCAVA', 'va', 'BCA Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 4],
    ['tripay', 'MANDIRIVA', 'Mandiri Virtual Account', 'va', 'MANDIRIVA', 'va', 'Mandiri Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 5],
    ['tripay', 'PERMATAVA', 'Permata Virtual Account', 'va', 'PERMATAVA', 'va', 'Permata Virtual Account', 'fa-bank', 'fixed', 4000, 10000, 5000000, 1, 6],
    ['tripay', 'OVO', 'OVO', 'ewallet', 'OVO', 'ewallet', 'OVO', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 7],
    ['tripay', 'DANA', 'DANA', 'ewallet', 'DANA', 'ewallet', 'DANA', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 8],
    ['tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 'SHOPEEPAY', 'ewallet', 'ShopeePay', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 9],
    ['tripay', 'LINKAJA', 'LinkAja', 'ewallet', 'LINKAJA', 'ewallet', 'LinkAja', 'fa-mobile', 'percentage', 2.5, 10000, 2000000, 1, 10],
    ['tripay', 'ALFAMART', 'Alfamart', 'retail', 'ALFAMART', 'retail', 'Alfamart', 'fa-shopping-cart', 'fixed', 5000, 10000, 5000000, 1, 11],
    ['tripay', 'INDOMARET', 'Indomaret', 'retail', 'INDOMARET', 'retail', 'Indomaret', 'fa-shopping-cart', 'fixed', 5000, 10000, 5000000, 1, 12],
];
$methodStmt = $pdo->prepare("INSERT INTO payment_methods
    (gateway_name, method_code, method_name, method_type, name, type, display_name, icon, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE method_name = VALUES(method_name), method_type = VALUES(method_type),
        name = VALUES(name), type = VALUES(type), display_name = VALUES(display_name), icon = VALUES(icon),
        admin_fee_type = VALUES(admin_fee_type), admin_fee_value = VALUES(admin_fee_value),
        min_amount = VALUES(min_amount), max_amount = VALUES(max_amount), is_active = VALUES(is_active),
        sort_order = VALUES(sort_order)");
foreach ($tripayMethods as $method) {
    $methodStmt->execute($method);
}
logMessage('Payment methods Tripay disinkronisasi', 'ok');

$pdo->exec("INSERT INTO payment_gateway_config (gateway_name, is_active, is_sandbox)
    VALUES ('tripay', 1, 1)
    ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), is_sandbox = VALUES(is_sandbox)");
logMessage('Payment gateway config Tripay dipastikan ada', 'ok');

$pdo->exec("INSERT IGNORE INTO site_pages (page_slug, page_title, page_content) VALUES
    ('tos', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan</h3><p>Sesuaikan konten ini.</p>'),
    ('privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3><p>Sesuaikan konten ini.</p>'),
    ('faq', 'FAQ', '<h3>FAQ</h3><p>Sesuaikan konten ini.</p>')");
logMessage('Site pages default tersedia', 'ok');

/**
 * 3. Perbaiki struktur Billing (copy dari fix_billing_module)
 */
logMessage('--- Memastikan struktur Billing ---');

$billingTables = [
    'billing_portal_otps' => <<<SQL
CREATE TABLE `billing_portal_otps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `identifier` VARCHAR(191) NOT NULL,
  `otp_code` VARCHAR(191) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `sent_via` ENUM('whatsapp','sms','email') DEFAULT 'whatsapp',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_identifier` (`customer_id`, `identifier`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'digiflazz_transactions' => <<<SQL
CREATE TABLE `digiflazz_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT,
  `ref_id` VARCHAR(60) NOT NULL,
  `buyer_sku_code` VARCHAR(50) NOT NULL,
  `customer_no` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(100),
  `status` ENUM('pending','success','failed','refund') DEFAULT 'pending',
  `message` VARCHAR(255),
  `price` INT DEFAULT 0,
  `sell_price` INT DEFAULT 0,
  `serial_number` VARCHAR(100),
  `response` TEXT,
  `whatsapp_notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_digiflazz_transactions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE SET NULL,
  INDEX `idx_ref` (`ref_id`),
  INDEX `idx_agent` (`agent_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_profiles' => <<<SQL
CREATE TABLE `billing_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_name` VARCHAR(100) NOT NULL,
  `price_monthly` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `speed_label` VARCHAR(100) DEFAULT NULL,
  `mikrotik_profile_normal` VARCHAR(100) NOT NULL,
  `mikrotik_profile_isolation` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_profile_name` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_customers' => <<<SQL
CREATE TABLE `billing_customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `service_number` VARCHAR(100) DEFAULT NULL,
  `billing_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `is_isolated` TINYINT(1) NOT NULL DEFAULT 0,
  `next_isolation_date` DATE DEFAULT NULL,
  `genieacs_match_mode` ENUM('device_id','phone_tag','pppoe_username') NOT NULL DEFAULT 'device_id',
  `genieacs_pppoe_username` VARCHAR(191) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_profile_id` (`profile_id`),
  KEY `idx_billing_day` (`billing_day`),
  CONSTRAINT `fk_billing_customers_profile` FOREIGN KEY (`profile_id`) REFERENCES `billing_profiles`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_invoices' => <<<SQL
CREATE TABLE `billing_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `profile_snapshot` JSON DEFAULT NULL,
  `period` CHAR(7) NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('draft','unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  `paid_at` DATETIME DEFAULT NULL,
  `payment_channel` VARCHAR(100) DEFAULT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `paid_via` VARCHAR(50) DEFAULT NULL,
  `paid_via_agent_id` INT UNSIGNED DEFAULT NULL,
  `whatsapp_sent_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_billing_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_settings' => <<<SQL
CREATE TABLE `billing_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_logs' => <<<SQL
CREATE TABLE `billing_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED DEFAULT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `event` VARCHAR(100) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_billing_logs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_billing_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'billing_payments' => <<<SQL
CREATE TABLE `billing_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `method` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  CONSTRAINT `fk_billing_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

foreach ($billingTables as $table => $ddl) {
    ensureTable($pdo, $table, $ddl);
}

// Pastikan kolom whatsapp_notified ada pada digiflazz_transactions
ensureColumn($pdo, 'digiflazz_transactions', 'whatsapp_notified', 'TINYINT(1) NOT NULL DEFAULT 0', 'response');

// Genap periksa kolom penting billing_customers
ensureColumn($pdo, 'billing_customers', 'service_number', 'VARCHAR(100) DEFAULT NULL', 'address');
ensureColumn($pdo, 'billing_customers', 'genieacs_match_mode', "ENUM('device_id','phone_tag','pppoe_username') NOT NULL DEFAULT 'device_id'", 'service_number');
ensureColumn($pdo, 'billing_customers', 'genieacs_pppoe_username', 'VARCHAR(191) DEFAULT NULL', 'genieacs_match_mode');
ensureColumn($pdo, 'billing_customers', 'is_isolated', 'TINYINT(1) NOT NULL DEFAULT 0', 'status');
ensureColumn($pdo, 'billing_customers', 'next_isolation_date', 'DATE DEFAULT NULL', 'is_isolated');

// billing_invoices kolom baru
ensureColumn($pdo, 'billing_invoices', 'profile_snapshot', 'JSON DEFAULT NULL', 'customer_id');
ensureColumn($pdo, 'billing_invoices', 'period', "CHAR(7) NOT NULL COMMENT 'Format YYYY-MM'", 'profile_snapshot');
ensureColumn($pdo, 'billing_invoices', 'payment_channel', 'VARCHAR(100) DEFAULT NULL', 'paid_at');
ensureColumn($pdo, 'billing_invoices', 'reference_number', 'VARCHAR(100) DEFAULT NULL', 'payment_channel');
ensureColumn($pdo, 'billing_invoices', 'paid_via', 'VARCHAR(50) DEFAULT NULL', 'reference_number');
ensureColumn($pdo, 'billing_invoices', 'paid_via_agent_id', 'INT UNSIGNED DEFAULT NULL', 'paid_via');
ensureColumn($pdo, 'billing_invoices', 'whatsapp_sent_at', 'DATETIME DEFAULT NULL', 'reference_number');
ensureIndex($pdo, 'billing_invoices', 'uniq_customer_period', 'UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`)');

// Pastikan billing_logs table referensinya tersedia
ensureIndex($pdo, 'billing_logs', 'idx_invoice_id', 'KEY `idx_invoice_id` (`invoice_id`)');
ensureIndex($pdo, 'billing_logs', 'idx_customer_id', 'KEY `idx_customer_id` (`customer_id`)');

// Pastikan otp_code cukup panjang
$otpType = $pdo->query("SHOW COLUMNS FROM `billing_portal_otps` LIKE 'otp_code'")->fetch(PDO::FETCH_ASSOC);
if ($otpType && stripos($otpType['Type'], 'varchar(191)') === false) {
    logMessage('Menyesuaikan kolom billing_portal_otps.otp_code ...', 'warn');
    $pdo->exec("ALTER TABLE `billing_portal_otps` MODIFY `otp_code` VARCHAR(191) NOT NULL");
    logMessage('Kolom otp_code disesuaikan menjadi VARCHAR(191)', 'ok');
}

/**
 * 4. Default billing settings (kontak portal & OTP)
 */
logMessage('--- Memastikan billing_settings untuk portal ---');

$billingDefaults = [
    'billing_portal_contact_heading' => 'Butuh bantuan? Hubungi Admin ISP',
    'billing_portal_contact_whatsapp' => '081234567890',
    'billing_portal_contact_email' => 'support@ispanda.com',
    'billing_portal_contact_body' => "Jam operasional: 08.00 - 22.00",
    'billing_portal_base_url' => '',
    'billing_portal_otp_enabled' => '1',
    'billing_portal_otp_digits' => '6',
    'billing_portal_otp_expiry_minutes' => '5',
    'billing_portal_otp_max_attempts' => '5',
    'billing_reminder_days_before' => '3,1',
    'billing_isolation_delay' => '1',
];

foreach ($billingDefaults as $key => $value) {
    upsertSetting($pdo, 'billing_settings', $key, $value);
}
logMessage('Billing settings dasar diperbarui', 'ok');

/**
 * 4.5. Views, Procedures & Triggers (Advanced)
 */
logMessage('--- Memastikan Views, Procedures & Triggers ---');

// Views
$pdo->exec("CREATE OR REPLACE VIEW agent_summary AS
SELECT 
    a.id,
    a.agent_code,
    a.agent_name,
    a.phone,
    a.balance,
    a.status,
    a.level,
    COUNT(DISTINCT av.id) as total_vouchers,
    COUNT(DISTINCT CASE WHEN av.status = 'used' THEN av.id END) as used_vouchers,
    SUM(CASE WHEN at.transaction_type = 'topup' THEN at.amount ELSE 0 END) as total_topup,
    SUM(CASE WHEN at.transaction_type = 'generate' THEN at.amount ELSE 0 END) as total_spent,
    COALESCE(SUM(ac.commission_amount), 0) as total_commission,
    a.created_at,
    a.last_login
FROM agents a
LEFT JOIN agent_vouchers av ON a.id = av.agent_id
LEFT JOIN agent_transactions at ON a.id = at.agent_id
LEFT JOIN agent_commissions ac ON a.id = ac.agent_id AND ac.status = 'paid'
GROUP BY a.id");

$pdo->exec("CREATE OR REPLACE VIEW daily_agent_sales AS
SELECT 
    DATE(av.created_at) as sale_date,
    a.agent_code,
    a.agent_name,
    av.profile_name,
    COUNT(*) as voucher_count,
    SUM(av.buy_price) as total_buy_price,
    SUM(av.sell_price) as total_sell_price,
    SUM(av.sell_price - av.buy_price) as total_profit
FROM agent_vouchers av
JOIN agents a ON av.agent_id = a.id
WHERE av.status != 'deleted'
GROUP BY DATE(av.created_at), a.id, av.profile_name");

logMessage('Views reporting diperbarui', 'ok');

// Procedures
$pdo->exec("DROP PROCEDURE IF EXISTS topup_agent_balance");
$pdo->exec("CREATE PROCEDURE topup_agent_balance(
    IN p_agent_id INT,
    IN p_amount DECIMAL(15,2),
    IN p_description TEXT,
    IN p_created_by VARCHAR(50)
)
BEGIN
    DECLARE v_balance_before DECIMAL(15,2);
    DECLARE v_balance_after DECIMAL(15,2);
    
    SELECT balance INTO v_balance_before FROM agents WHERE id = p_agent_id;
    SET v_balance_after = v_balance_before + p_amount;
    
    UPDATE agents SET balance = v_balance_after WHERE id = p_agent_id;
    
    INSERT INTO agent_transactions (
        agent_id, transaction_type, amount, 
        balance_before, balance_after, 
        description, created_by
    ) VALUES (
        p_agent_id, 'topup', p_amount,
        v_balance_before, v_balance_after,
        p_description, p_created_by
    );
END");

$pdo->exec("DROP PROCEDURE IF EXISTS deduct_agent_balance");
$pdo->exec("CREATE PROCEDURE deduct_agent_balance(
    IN p_agent_id INT,
    IN p_amount DECIMAL(15,2),
    IN p_profile_name VARCHAR(100),
    IN p_username VARCHAR(100),
    IN p_description TEXT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_balance_before DECIMAL(15,2);
    DECLARE v_balance_after DECIMAL(15,2);
    
    SELECT balance INTO v_balance_before FROM agents WHERE id = p_agent_id;
    
    IF v_balance_before < p_amount THEN
        SET p_success = FALSE;
        SET p_message = 'Saldo tidak mencukupi';
    ELSE
        SET v_balance_after = v_balance_before - p_amount;
        UPDATE agents SET balance = v_balance_after WHERE id = p_agent_id;
        
        INSERT INTO agent_transactions (
            agent_id, transaction_type, amount,
            balance_before, balance_after,
            profile_name, voucher_username,
            description
        ) VALUES (
            p_agent_id, 'generate', p_amount,
            v_balance_before, v_balance_after,
            p_profile_name, p_username,
            p_description
        );
        
        SET p_success = TRUE;
        SET p_message = 'Saldo berhasil dipotong';
    END IF;
END");

logMessage('Stored Procedures diperbarui', 'ok');

// Triggers
$pdo->exec("DROP TRIGGER IF EXISTS after_agent_voucher_insert");
$pdo->exec("CREATE TRIGGER after_agent_voucher_insert
AFTER INSERT ON agent_vouchers
FOR EACH ROW
BEGIN
    DECLARE v_commission_enabled BOOLEAN;
    DECLARE v_commission_percent DECIMAL(5,2);
    
    SELECT CAST(setting_value AS UNSIGNED) INTO v_commission_enabled
    FROM agent_settings WHERE setting_key = 'commission_enabled';
    
    IF v_commission_enabled THEN
        SELECT commission_percent INTO v_commission_percent
        FROM agents WHERE id = NEW.agent_id;
        
        IF v_commission_percent > 0 AND NEW.sell_price IS NOT NULL THEN
            INSERT INTO agent_commissions (
                agent_id, voucher_id, commission_amount,
                commission_percent, voucher_price
            ) VALUES (
                NEW.agent_id, NEW.id, (NEW.sell_price * v_commission_percent / 100),
                v_commission_percent, NEW.sell_price
            );
        END IF;
    END IF;
END");

logMessage('Triggers diperbarui', 'ok');

/**
 * 5. Ringkasan
 */
logMessage('=== Perbaikan selesai ===', 'ok');

$summaryTables = [
    'agents', 'agent_settings', 'agent_prices', 'agent_profile_pricing',
    'payment_methods', 'billing_customers', 'billing_invoices',
    'billing_settings', 'billing_portal_otps'
];
foreach ($summaryTables as $table) {
    if (tableExists($pdo, $table)) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        logMessage("Tabel {$table} berisi {$count} baris", 'info');
    }
}

logMessage('Silakan hapus file ini setelah dipakai demi keamanan.', 'warn');
