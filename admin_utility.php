<?php
/**
 * MikhMon Admin Utility - All-in-One Installation & Fix Tool
 * Menggabungkan semua installer dan fix dalam satu interface
 * 
 * @version 2.0
 * @author MikhMon Team
 * @date 2024-11-15
 */

// Security check
$security_key = $_GET['key'] ?? '';
if ($security_key !== 'mikhmon-admin-2024') {
    die('Access denied. Add ?key=mikhmon-admin-2024 to URL');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);
date_default_timezone_set('Asia/Jakarta');

// Include database config
require_once __DIR__ . '/include/db_config.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikhMon Admin Utility - Installation & Fix Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #333;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 16px; }
        .content { padding: 30px; }
        .section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 24px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .status-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .status-card:hover { transform: translateY(-5px); }
        .status-card h3 {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }
        .status-card .value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .status-info { color: #17a2b8; }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 5px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .btn-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .btn-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        .table tr:hover { background: #f8f9fa; }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .badge-success { background: #28a745; }
        .badge-danger { background: #dc3545; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .log-area {
            background: #1e1e1e;
            color: #0f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .log-entry {
            margin: 5px 0;
            padding: 5px;
            border-left: 3px solid transparent;
        }
        .log-success { border-left-color: #28a745; }
        .log-error { border-left-color: #dc3545; color: #f88; }
        .log-warning { border-left-color: #ffc107; color: #ff0; }
        .log-info { border-left-color: #17a2b8; color: #8ff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ MikhMon Admin Utility</h1>
            <p>All-in-One Installation & Fix Tool v2.0</p>
        </div>

        <div class="content">
            <?php
            // Get action
            $action = $_GET['action'] ?? 'check';

            // Initialize database connection
            try {
                $pdo = getDBConnection();
                if (!$pdo) {
                    throw new Exception("Gagal koneksi database. Periksa konfigurasi di include/db_config.php");
                }
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '<h3>❌ Database Connection Error</h3>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p><strong>Langkah perbaikan:</strong></p>';
                echo '<ol>';
                echo '<li>Periksa file <code>include/db_config.php</code></li>';
                echo '<li>Pastikan MySQL service berjalan</li>';
                echo '<li>Verifikasi username, password, dan nama database</li>';
                echo '</ol>';
                echo '</div>';
                echo '</div></div></body></html>';
                exit;
            }

            // Helper functions
            function tableExists($pdo, $table) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    return $stmt->rowCount() > 0;
                } catch (Exception $e) {
                    return false;
                }
            }

            function columnExists($pdo, $table, $column) {
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                    return $stmt->rowCount() > 0;
                } catch (Exception $e) {
                    return false;
                }
            }

            function getTableCount($pdo, $table) {
                try {
                    if (!tableExists($pdo, $table)) return 0;
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                    return $stmt->fetchColumn();
                } catch (Exception $e) {
                    return 0;
                }
            }

            function logMessage($message, $type = 'info') {
                $icon = [
                    'success' => '✅',
                    'error' => '❌',
                    'warning' => '⚠️',
                    'info' => 'ℹ️'
                ][$type] ?? 'ℹ️';
                
                echo "<div class='log-entry log-$type'>$icon $message</div>";
                flush();
                ob_flush();
            }

            // Check system status
            function checkSystemStatus($pdo) {
                $status = [
                    'database_connected' => true,
                    'tables' => [],
                    'missing_tables' => [],
                    'incomplete_tables' => [],
                    'data_status' => [],
                    'issues' => []
                ];

                // Required tables
                $requiredTables = [
                    'agents' => ['id', 'agent_code', 'agent_name', 'balance', 'status'],
                    'agent_settings' => ['id', 'agent_id', 'setting_key', 'setting_value'],
                    'agent_prices' => ['id', 'agent_id', 'profile_name', 'price'],
                    'agent_transactions' => ['id', 'agent_id', 'transaction_type', 'amount'],
                    'agent_profile_pricing' => ['id', 'agent_id', 'profile_name', 'price', 'is_active'],
                    'payment_gateway_config' => ['id', 'gateway_name', 'is_active'],
                    'payment_methods' => ['id', 'gateway_name', 'method_code', 'method_name', 'method_type'],
                    'public_sales' => ['id', 'agent_id', 'profile_id', 'customer_name', 'total_amount', 'status'],
                    'billing_profiles' => ['id', 'profile_name', 'price', 'billing_cycle'],
                    'billing_customers' => ['id', 'profile_id', 'name', 'status'],
                    'billing_invoices' => ['id', 'customer_id', 'amount', 'status'],
                    'digiflazz_transactions' => ['id', 'agent_id', 'ref_id', 'buyer_sku_code', 'status'],
                    'voucher_settings' => ['id', 'setting_key', 'setting_value'],
                    'site_pages' => ['id', 'page_key', 'page_title', 'content']
                ];

                // Check each table
                foreach ($requiredTables as $table => $requiredColumns) {
                    if (tableExists($pdo, $table)) {
                        $status['tables'][] = $table;
                        
                        // Check columns
                        $missingColumns = [];
                        foreach ($requiredColumns as $column) {
                            if (!columnExists($pdo, $table, $column)) {
                                $missingColumns[] = $column;
                            }
                        }
                        
                        if (!empty($missingColumns)) {
                            $status['incomplete_tables'][$table] = $missingColumns;
                            $status['issues'][] = "Table '$table' missing columns: " . implode(', ', $missingColumns);
                        }

                        // Check data count
                        $status['data_status'][$table] = getTableCount($pdo, $table);
                    } else {
                        $status['missing_tables'][] = $table;
                        $status['issues'][] = "Table '$table' does not exist";
                    }
                }

                return $status;
            }

            // Display dashboard
            if ($action === 'check') {
                echo '<div class="section">';
                echo '<h2>📊 System Status Check</h2>';
                
                $status = checkSystemStatus($pdo);
                
                // Summary cards
                echo '<div class="status-grid">';
                
                echo '<div class="status-card">';
                echo '<h3>Database Connection</h3>';
                echo '<div class="value status-ok">✓ Connected</div>';
                echo '<p>Host: ' . DB_HOST . '<br>Database: ' . DB_NAME . '</p>';
                echo '</div>';
                
                echo '<div class="status-card">';
                echo '<h3>Tables Installed</h3>';
                $tableCount = count($status['tables']);
                $totalRequired = 14;
                $colorClass = $tableCount >= $totalRequired ? 'status-ok' : 'status-warning';
                echo "<div class='value $colorClass'>$tableCount / $totalRequired</div>";
                echo '<p>Required tables found</p>';
                echo '</div>';
                
                echo '<div class="status-card">';
                echo '<h3>Issues Found</h3>';
                $issueCount = count($status['issues']);
                $colorClass = $issueCount === 0 ? 'status-ok' : 'status-error';
                echo "<div class='value $colorClass'>$issueCount</div>";
                echo '<p>Problems detected</p>';
                echo '</div>';
                
                echo '<div class="status-card">';
                echo '<h3>System Health</h3>';
                $health = $issueCount === 0 ? 'Excellent' : ($issueCount < 5 ? 'Good' : 'Needs Fix');
                $colorClass = $issueCount === 0 ? 'status-ok' : ($issueCount < 5 ? 'status-warning' : 'status-error');
                echo "<div class='value $colorClass'>$health</div>";
                echo '<p>Overall status</p>';
                echo '</div>';
                
                echo '</div>';

                // Issues details
                if (!empty($status['issues'])) {
                    echo '<div class="alert alert-warning">';
                    echo '<h3>⚠️ Issues Detected</h3>';
                    echo '<ul>';
                    foreach ($status['issues'] as $issue) {
                        echo '<li>' . htmlspecialchars($issue) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }

                // Tables status
                echo '<h3 style="margin-top: 30px;">📋 Tables Status</h3>';
                echo '<table class="table">';
                echo '<thead><tr><th>Table Name</th><th>Status</th><th>Records</th><th>Issues</th></tr></thead>';
                echo '<tbody>';
                
                $allTables = array_keys([
                    'agents' => 1, 'agent_settings' => 1, 'agent_prices' => 1, 
                    'agent_transactions' => 1, 'agent_profile_pricing' => 1,
                    'payment_gateway_config' => 1, 'payment_methods' => 1, 'public_sales' => 1,
                    'billing_profiles' => 1, 'billing_customers' => 1, 'billing_invoices' => 1,
                    'digiflazz_transactions' => 1, 'voucher_settings' => 1, 'site_pages' => 1
                ]);
                
                foreach ($allTables as $table) {
                    echo '<tr>';
                    echo '<td><strong>' . $table . '</strong></td>';
                    
                    if (in_array($table, $status['tables'])) {
                        if (isset($status['incomplete_tables'][$table])) {
                            echo '<td><span class="badge badge-warning">Incomplete</span></td>';
                            echo '<td>' . $status['data_status'][$table] . '</td>';
                            echo '<td>Missing columns: ' . implode(', ', $status['incomplete_tables'][$table]) . '</td>';
                        } else {
                            echo '<td><span class="badge badge-success">OK</span></td>';
                            echo '<td>' . $status['data_status'][$table] . '</td>';
                            echo '<td>-</td>';
                        }
                    } else {
                        echo '<td><span class="badge badge-danger">Missing</span></td>';
                        echo '<td>0</td>';
                        echo '<td>Table not found</td>';
                    }
                    
                    echo '</tr>';
                }
                
                echo '</tbody></table>';

                // Action buttons
                echo '<h3 style="margin-top: 30px;">🔧 Quick Actions</h3>';
                echo '<div style="margin: 20px 0;">';
                
                if (!empty($status['missing_tables'])) {
                    echo '<a href="?key=mikhmon-admin-2024&action=install_all" class="btn btn-primary">🚀 Install All Missing Tables</a>';
                }
                
                if (!empty($status['incomplete_tables'])) {
                    echo '<a href="?key=mikhmon-admin-2024&action=fix_columns" class="btn btn-warning">🔨 Fix Incomplete Tables</a>';
                }
                
                if (empty($status['issues'])) {
                    echo '<a href="?key=mikhmon-admin-2024&action=optimize" class="btn btn-success">⚡ Optimize Database</a>';
                }
                
                echo '<a href="?key=mikhmon-admin-2024&action=install_data" class="btn btn-info">📦 Install Sample Data</a>';
                echo '<a href="?key=mikhmon-admin-2024&action=backup" class="btn btn-danger">💾 Backup Database</a>';
                echo '</div>';

                echo '</div>';

                // What's installed / not installed
                echo '<div class="section">';
                echo '<h2>📦 Module Status</h2>';
                
                $modules = [
                    'Agent System' => ['agents', 'agent_settings', 'agent_prices'],
                    'Payment Gateway' => ['payment_gateway_config', 'payment_methods'],
                    'Public Sales' => ['public_sales', 'agent_profile_pricing'],
                    'Billing System' => ['billing_profiles', 'billing_customers', 'billing_invoices'],
                    'Digiflazz Integration' => ['digiflazz_transactions'],
                    'Voucher Settings' => ['voucher_settings'],
                    'Website Pages' => ['site_pages']
                ];

                echo '<table class="table">';
                echo '<thead><tr><th>Module</th><th>Status</th><th>Tables</th><th>Action</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($modules as $moduleName => $moduleTables) {
                    $allInstalled = true;
                    $installedCount = 0;
                    
                    foreach ($moduleTables as $table) {
                        if (!in_array($table, $status['tables'])) {
                            $allInstalled = false;
                        } else {
                            $installedCount++;
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td><strong>' . $moduleName . '</strong></td>';
                    
                    if ($allInstalled) {
                        echo '<td><span class="badge badge-success">Installed</span></td>';
                        echo '<td>' . $installedCount . '/' . count($moduleTables) . ' tables</td>';
                        echo '<td>-</td>';
                    } else {
                        echo '<td><span class="badge badge-danger">Not Installed</span></td>';
                        echo '<td>' . $installedCount . '/' . count($moduleTables) . ' tables</td>';
                        $moduleKey = strtolower(str_replace(' ', '_', $moduleName));
                        echo '<td><a href="?key=mikhmon-admin-2024&action=install_module&module=' . $moduleKey . '" class="btn btn-primary btn-sm">Install</a></td>';
                    }
                    
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                echo '</div>';
            }

            // Install all tables
            elseif ($action === 'install_all') {
                echo '<div class="section">';
                echo '<h2>🚀 Installing All Tables</h2>';
                echo '<div class="log-area">';
                
                try {
                    $pdo->beginTransaction();
                    
                    logMessage('Starting comprehensive installation...', 'info');
                    
                    // 1. Agents table
                    if (!tableExists($pdo, 'agents')) {
                        logMessage('Creating agents table...', 'info');
                        $pdo->exec("CREATE TABLE `agents` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `agent_code` VARCHAR(20) UNIQUE NOT NULL,
                            `agent_name` VARCHAR(100) NOT NULL,
                            `contact_person` VARCHAR(100),
                            `phone` VARCHAR(20),
                            `email` VARCHAR(100),
                            `address` TEXT,
                            `status` ENUM('active', 'inactive') DEFAULT 'active',
                            `commission_rate` DECIMAL(5,2) DEFAULT 0,
                            `balance` DECIMAL(15,2) DEFAULT 0,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            KEY `idx_status` (`status`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Agents table created', 'success');
                    }
                    
                    // 2. Agent Settings
                    if (!tableExists($pdo, 'agent_settings')) {
                        logMessage('Creating agent_settings table...', 'info');
                        $pdo->exec("CREATE TABLE `agent_settings` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `agent_id` INT NOT NULL DEFAULT 1,
                            `setting_key` VARCHAR(100) NOT NULL,
                            `setting_value` TEXT,
                            `setting_type` VARCHAR(20) DEFAULT 'string',
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
                            KEY `idx_agent_id` (`agent_id`),
                            KEY `idx_setting_key` (`setting_key`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Agent settings table created', 'success');
                    }
                    
                    // 3. Agent Prices
                    if (!tableExists($pdo, 'agent_prices')) {
                        logMessage('Creating agent_prices table...', 'info');
                        $pdo->exec("CREATE TABLE `agent_prices` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `agent_id` INT NOT NULL,
                            `profile_name` VARCHAR(100) NOT NULL,
                            `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
                            `selling_price` DECIMAL(10,2) NOT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
                            UNIQUE KEY `unique_agent_profile` (`agent_id`, `profile_name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Agent prices table created', 'success');
                    }
                    
                    // 4. Agent Transactions
                    if (!tableExists($pdo, 'agent_transactions')) {
                        logMessage('Creating agent_transactions table...', 'info');
                        $pdo->exec("CREATE TABLE `agent_transactions` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `agent_id` INT NOT NULL,
                            `transaction_type` ENUM('topup','generate','refund','commission','penalty') NOT NULL,
                            `amount` DECIMAL(15,2) NOT NULL,
                            `balance_before` DECIMAL(15,2) NOT NULL,
                            `balance_after` DECIMAL(15,2) NOT NULL,
                            `profile_name` VARCHAR(100),
                            `voucher_username` VARCHAR(100),
                            `voucher_password` VARCHAR(100),
                            `quantity` INT(11),
                            `description` TEXT,
                            `reference_id` VARCHAR(50),
                            `created_by` VARCHAR(50),
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `ip_address` VARCHAR(45),
                            `user_agent` TEXT,
                            FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
                            INDEX `idx_agent_date` (`agent_id`, `created_at`),
                            INDEX `idx_reference` (`reference_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Agent transactions table created', 'success');
                    }
                    
                    // 5. Payment Gateway Config
                    if (!tableExists($pdo, 'payment_gateway_config')) {
                        logMessage('Creating payment_gateway_config table...', 'info');
                        $pdo->exec("CREATE TABLE `payment_gateway_config` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `gateway_name` VARCHAR(50) NOT NULL,
                            `is_active` TINYINT(1) DEFAULT 0,
                            `is_sandbox` TINYINT(1) DEFAULT 1,
                            `api_key` VARCHAR(255),
                            `api_secret` VARCHAR(255),
                            `merchant_code` VARCHAR(100),
                            `callback_token` VARCHAR(255),
                            `config_json` TEXT,
                            `provider` VARCHAR(50) DEFAULT 'tripay',
                            `callback_url` VARCHAR(255) DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY `unique_gateway` (`gateway_name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Payment gateway config table created', 'success');
                    }
                    
                    // 6. Agent Profile Pricing
                    if (!tableExists($pdo, 'agent_profile_pricing')) {
                        logMessage('Creating agent_profile_pricing table...', 'info');
                        $pdo->exec("CREATE TABLE `agent_profile_pricing` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `agent_id` INT NOT NULL,
                            `profile_name` VARCHAR(100) NOT NULL,
                            `display_name` VARCHAR(100) NOT NULL,
                            `description` TEXT,
                            `price` DECIMAL(10,2) NOT NULL,
                            `original_price` DECIMAL(10,2),
                            `is_active` TINYINT(1) DEFAULT 1,
                            `is_featured` TINYINT(1) DEFAULT 0,
                            `category` VARCHAR(50),
                            `icon` VARCHAR(50),
                            `color` VARCHAR(20),
                            `sort_order` INT DEFAULT 0,
                            `user_type` ENUM('voucher','member') DEFAULT 'voucher',
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
                            UNIQUE KEY `unique_agent_profile` (`agent_id`, `profile_name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Agent profile pricing table created', 'success');
                    }
                    
                    // 7. Public Sales
                    if (!tableExists($pdo, 'public_sales')) {
                        logMessage('Creating public_sales table...', 'info');
                        $pdo->exec("CREATE TABLE `public_sales` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `transaction_id` VARCHAR(100) UNIQUE NOT NULL,
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
                            `payment_reference` VARCHAR(100),
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
                            FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE,
                            FOREIGN KEY (`profile_id`) REFERENCES `agent_profile_pricing`(`id`) ON DELETE CASCADE,
                            INDEX `idx_transaction_id` (`transaction_id`),
                            INDEX `idx_payment_reference` (`payment_reference`),
                            INDEX `idx_status` (`status`),
                            INDEX `idx_customer_phone` (`customer_phone`),
                            INDEX `idx_created_at` (`created_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Public sales table created', 'success');
                    }
                    
                    // 8. Payment Methods
                    if (!tableExists($pdo, 'payment_methods')) {
                        logMessage('Creating payment_methods table...', 'info');
                        $pdo->exec("CREATE TABLE `payment_methods` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `gateway_name` VARCHAR(50) NOT NULL DEFAULT 'tripay',
                            `method_code` VARCHAR(50) NOT NULL,
                            `method_name` VARCHAR(100) NOT NULL,
                            `method_type` VARCHAR(20) NOT NULL,
                            `name` VARCHAR(100) NOT NULL,
                            `type` VARCHAR(50) NOT NULL,
                            `display_name` VARCHAR(100),
                            `icon` VARCHAR(100),
                            `icon_url` TEXT,
                            `admin_fee_type` ENUM('percentage','fixed','flat','percent') DEFAULT 'fixed',
                            `admin_fee_value` DECIMAL(10,2) DEFAULT 0,
                            `min_amount` DECIMAL(10,2) DEFAULT 0,
                            `max_amount` DECIMAL(12,2) DEFAULT 99999999.99,
                            `is_active` TINYINT(1) DEFAULT 1,
                            `sort_order` INT DEFAULT 0,
                            `config` TEXT,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY `unique_gateway_method` (`gateway_name`, `method_code`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Payment methods table created', 'success');
                    }
                    
                    // 9. Billing Profiles
                    if (!tableExists($pdo, 'billing_profiles')) {
                        logMessage('Creating billing_profiles table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_profiles` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `profile_name` VARCHAR(100) NOT NULL,
                            `description` TEXT,
                            `price` DECIMAL(10,2) NOT NULL,
                            `billing_cycle` INT NOT NULL DEFAULT 30,
                            `grace_period` INT DEFAULT 3,
                            `is_active` TINYINT(1) DEFAULT 1,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY `uniq_profile_name` (`profile_name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing profiles table created', 'success');
                    }
                    
                    // 10. Billing Customers
                    if (!tableExists($pdo, 'billing_customers')) {
                        logMessage('Creating billing_customers table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_customers` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `profile_id` INT UNSIGNED NOT NULL,
                            `name` VARCHAR(150) NOT NULL,
                            `phone` VARCHAR(32) DEFAULT NULL,
                            `email` VARCHAR(150) DEFAULT NULL,
                            `address` TEXT,
                            `service_number` VARCHAR(100) DEFAULT NULL,
                            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                            `billing_day` TINYINT UNSIGNED NOT NULL DEFAULT 20,
                            `auto_isolation` TINYINT(1) NOT NULL DEFAULT 1,
                            `last_bill_date` DATE,
                            `next_bill_date` DATE,
                            `is_isolated` TINYINT(1) NOT NULL DEFAULT 0,
                            `next_isolation_date` DATE DEFAULT NULL,
                            `genieacs_match_mode` ENUM('device_id','phone_tag','pppoe_username') NOT NULL DEFAULT 'device_id',
                            `genieacs_pppoe_username` VARCHAR(100),
                            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                            KEY `idx_profile_id` (`profile_id`),
                            KEY `idx_billing_day` (`billing_day`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing customers table created', 'success');
                    }
                    
                    // 11. Billing Invoices
                    if (!tableExists($pdo, 'billing_invoices')) {
                        logMessage('Creating billing_invoices table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_invoices` (
                            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `customer_id` INT UNSIGNED NOT NULL,
                            `profile_snapshot` JSON DEFAULT NULL,
                            `period` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
                            `due_date` DATE NOT NULL,
                            `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                            `status` ENUM('draft','unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
                            `paid_at` DATETIME DEFAULT NULL,
                            `payment_channel` VARCHAR(100) DEFAULT NULL,
                            `reference_number` VARCHAR(100) DEFAULT NULL,
                            `whatsapp_sent_at` DATETIME DEFAULT NULL,
                            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `uniq_customer_period` (`customer_id`,`period`),
                            KEY `idx_status` (`status`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing invoices table created', 'success');
                    }
                    
                    // 12. Digiflazz Transactions
                    if (!tableExists($pdo, 'digiflazz_transactions')) {
                        logMessage('Creating digiflazz_transactions table...', 'info');
                        $pdo->exec("CREATE TABLE `digiflazz_transactions` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `agent_id` INT DEFAULT NULL,
                            `ref_id` VARCHAR(50) UNIQUE NOT NULL,
                            `buyer_sku_code` VARCHAR(50) NOT NULL,
                            `customer_no` VARCHAR(50) NOT NULL,
                            `price` DECIMAL(10,2) NOT NULL,
                            `message` TEXT,
                            `status` ENUM('pending','success','failed','cancelled') DEFAULT 'pending',
                            `rc` VARCHAR(10),
                            `sn` TEXT,
                            `balance_before` DECIMAL(15,2),
                            `balance_after` DECIMAL(15,2),
                            `transaction_time` DATETIME,
                            `whatsapp_notified` TINYINT(1) DEFAULT 0,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            KEY `idx_ref_id` (`ref_id`),
                            KEY `idx_agent_id` (`agent_id`),
                            INDEX `idx_status` (`status`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Digiflazz transactions table created', 'success');
                    }
                    
                    // 13. Voucher Settings
                    if (!tableExists($pdo, 'voucher_settings')) {
                        logMessage('Creating voucher_settings table...', 'info');
                        $pdo->exec("CREATE TABLE `voucher_settings` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                            `setting_value` TEXT,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Voucher settings table created', 'success');
                    }
                    
                    // 14. Site Pages
                    if (!tableExists($pdo, 'site_pages')) {
                        logMessage('Creating site_pages table...', 'info');
                        $pdo->exec("CREATE TABLE `site_pages` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `page_key` VARCHAR(50) NOT NULL UNIQUE,
                            `page_title` VARCHAR(200) NOT NULL,
                            `content` TEXT,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Site pages table created', 'success');
                    }
                    
                    // Additional Billing tables
                    if (!tableExists($pdo, 'billing_settings')) {
                        logMessage('Creating billing_settings table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_settings` (
                            `setting_key` VARCHAR(100) NOT NULL,
                            `setting_value` TEXT DEFAULT NULL,
                            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`setting_key`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing settings table created', 'success');
                    }
                    
                    if (!tableExists($pdo, 'billing_payments')) {
                        logMessage('Creating billing_payments table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_payments` (
                            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `invoice_id` BIGINT UNSIGNED NOT NULL,
                            `amount` DECIMAL(12,2) NOT NULL,
                            `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `method` VARCHAR(100) DEFAULT NULL,
                            `notes` TEXT DEFAULT NULL,
                            `created_by` INT UNSIGNED DEFAULT NULL,
                            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_invoice_id` (`invoice_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing payments table created', 'success');
                    }
                    
                    if (!tableExists($pdo, 'billing_logs')) {
                        logMessage('Creating billing_logs table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_logs` (
                            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `invoice_id` BIGINT UNSIGNED DEFAULT NULL,
                            `customer_id` INT UNSIGNED DEFAULT NULL,
                            `event` VARCHAR(100) NOT NULL,
                            `metadata` JSON DEFAULT NULL,
                            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_invoice_id` (`invoice_id`),
                            KEY `idx_customer_id` (`customer_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing logs table created', 'success');
                    }
                    
                    if (!tableExists($pdo, 'billing_portal_otps')) {
                        logMessage('Creating billing_portal_otps table...', 'info');
                        $pdo->exec("CREATE TABLE `billing_portal_otps` (
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
                            KEY `idx_customer_id` (`customer_id`),
                            KEY `idx_identifier` (`identifier`),
                            KEY `idx_expires_at` (`expires_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        logMessage('✓ Billing portal OTPs table created', 'success');
                    }
                    
                    // Add foreign key constraints after all tables are created
                    try {
                        logMessage('Adding foreign key constraints...', 'info');
                        
                        // Add foreign key to billing_invoices
                        if (tableExists($pdo, 'billing_invoices') && tableExists($pdo, 'billing_customers')) {
                            $pdo->exec("ALTER TABLE `billing_invoices` ADD CONSTRAINT `fk_billing_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
                            logMessage('✓ Foreign key added to billing_invoices', 'success');
                        }
                        
                        // Add foreign key to billing_customers
                        if (tableExists($pdo, 'billing_customers') && tableExists($pdo, 'billing_profiles')) {
                            $pdo->exec("ALTER TABLE `billing_customers` ADD CONSTRAINT `fk_billing_customers_profile` FOREIGN KEY (`profile_id`) REFERENCES `billing_profiles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE");
                            logMessage('✓ Foreign key added to billing_customers', 'success');
                        }
                        
                        // Add foreign key to billing_payments
                        if (tableExists($pdo, 'billing_payments') && tableExists($pdo, 'billing_invoices')) {
                            $pdo->exec("ALTER TABLE `billing_payments` ADD CONSTRAINT `fk_billing_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
                            logMessage('✓ Foreign key added to billing_payments', 'success');
                        }
                        
                        // Add foreign key to billing_logs
                        if (tableExists($pdo, 'billing_logs') && tableExists($pdo, 'billing_customers')) {
                            $pdo->exec("ALTER TABLE `billing_logs` ADD CONSTRAINT `fk_billing_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `billing_customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                            logMessage('✓ Foreign key added to billing_logs', 'success');
                        }
                        
                        // Add foreign key to billing_logs for invoice_id
                        if (tableExists($pdo, 'billing_logs') && tableExists($pdo, 'billing_invoices')) {
                            $pdo->exec("ALTER TABLE `billing_logs` ADD CONSTRAINT `fk_billing_logs_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                            logMessage('✓ Foreign key added to billing_logs for invoice_id', 'success');
                        }
                        
                    } catch (Exception $e) {
                        logMessage('Warning: Could not add foreign key constraints - ' . $e->getMessage(), 'warning');
                        // Continue with installation even if foreign keys fail
                    }
                    
                    $pdo->commit();
                    logMessage('All tables created successfully!', 'success');
                    
                    echo '</div>';
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Installation Complete!</h3>';
                    echo '<p>All required tables have been created successfully.</p>';
                    echo '<p><a href="?key=mikhmon-admin-2024&action=check" class="btn btn-primary">View Status</a></p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    logMessage('Error: ' . $e->getMessage(), 'error');
                    echo '</div>';
                    echo '<div class="alert alert-danger">';
                    echo '<h3>❌ Installation Failed</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }

            // Fix columns
            elseif ($action === 'fix_columns') {
                echo '<div class="section">';
                echo '<h2>🔨 Fixing Incomplete Tables</h2>';
                echo '<div class="log-area">';
                
                try {
                    logMessage('Starting column fix process...', 'info');
                    
                    $status = checkSystemStatus($pdo);
                    
                    foreach ($status['incomplete_tables'] as $table => $missingColumns) {
                        logMessage("Fixing table: $table", 'info');
                        
                        foreach ($missingColumns as $column) {
                            // Add column based on table and column name
                            $definition = '';
                            $after = null;
                            
                            // Common columns
                            if ($column === 'agent_id') {
                                $definition = 'INT NOT NULL DEFAULT 1';
                            } elseif ($column === 'profile_id') {
                                $definition = 'INT NOT NULL DEFAULT 1';
                            } elseif ($column === 'status') {
                                $definition = "VARCHAR(20) DEFAULT 'pending'";
                            } elseif ($column === 'gateway_name') {
                                $definition = "VARCHAR(50) NOT NULL DEFAULT 'tripay'";
                            } elseif ($column === 'method_code') {
                                $definition = 'VARCHAR(50) NOT NULL';
                            } elseif ($column === 'method_name') {
                                $definition = 'VARCHAR(100) NOT NULL';
                            } elseif ($column === 'method_type') {
                                $definition = 'VARCHAR(20) NOT NULL';
                            } elseif ($column === 'is_active') {
                                $definition = 'TINYINT(1) DEFAULT 1';
                            } elseif ($column === 'price' && $table === 'agent_prices') {
                                $definition = 'DECIMAL(10,2) NOT NULL DEFAULT 0';
                                $after = 'profile_name';
                            } elseif ($column === 'price' && $table === 'billing_profiles') {
                                $definition = 'DECIMAL(10,2) NOT NULL';
                                $after = 'profile_name';
                            } elseif ($column === 'billing_cycle' && $table === 'billing_profiles') {
                                $definition = 'INT NOT NULL DEFAULT 30';
                                $after = 'price';
                            } elseif ($column === 'page_key' && $table === 'site_pages') {
                                $definition = 'VARCHAR(50) NOT NULL';
                                $after = 'id';
                            } elseif ($column === 'content' && $table === 'site_pages') {
                                $definition = 'TEXT';
                                $after = 'page_title';
                            } elseif ($column === 'selling_price') {
                                $definition = 'DECIMAL(10,2) NOT NULL';
                            } elseif ($column === 'cost_price') {
                                $definition = 'DECIMAL(10,2) NOT NULL DEFAULT 0';
                            }
                            
                            if ($definition) {
                                $alterSQL = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
                                if ($after) {
                                    $alterSQL .= " AFTER `$after`";
                                }
                                $pdo->exec($alterSQL);
                                logMessage("✓ Added column: $table.$column", 'success');
                            } else {
                                logMessage("⚠ Skipped unknown column: $table.$column", 'warning');
                            }
                        }
                    }
                    
                    logMessage('Column fix process completed!', 'success');
                    
                    echo '</div>';
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Fix Complete!</h3>';
                    echo '<p>All missing columns have been added.</p>';
                    echo '<p><a href="?key=mikhmon-admin-2024&action=check" class="btn btn-primary">View Status</a></p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    logMessage('Error: ' . $e->getMessage(), 'error');
                    echo '</div>';
                    echo '<div class="alert alert-danger">';
                    echo '<h3>❌ Fix Failed</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }

            // Install sample data
            elseif ($action === 'install_data') {
                echo '<div class="section">';
                echo '<h2>📦 Installing Sample Data</h2>';
                echo '<div class="log-area">';
                
                try {
                    logMessage('Starting sample data installation...', 'info');
                    
                    // 1. Insert agents
                    logMessage('Inserting sample agents...', 'info');
                    $pdo->exec("INSERT IGNORE INTO agents (id, agent_code, agent_name, balance, status) VALUES 
                        (1, 'AG001', 'Agent Demo', 100000, 'active'),
                        (2, 'AG5136', 'Tester', 50000, 'active'),
                        (3, 'PUBLIC', 'Public Catalog', 0, 'active')");
                    logMessage('✓ Sample agents inserted', 'success');
                    
                    // 2. Insert agent settings
                    logMessage('Inserting agent settings...', 'info');
                    $settings = [
                        ['voucher_format', 'USER-{RANDOM}'],
                        ['voucher_length', '8'],
                        ['voucher_password_format', '{RANDOM}'],
                        ['voucher_password_length', '6'],
                        ['admin_whatsapp_numbers', '628123456789']
                    ];
                    $stmt = $pdo->prepare("INSERT IGNORE INTO agent_settings (agent_id, setting_key, setting_value) VALUES (1, ?, ?)");
                    foreach ($settings as $setting) {
                        $stmt->execute($setting);
                    }
                    logMessage('✓ Agent settings inserted', 'success');
                    
                    // 3. Insert agent profile pricing
                    logMessage('Inserting profile pricing...', 'info');
                    $profiles = [
                        [1, '3k', 'Voucher 1 Hari', 3000, 1, 0],
                        [1, '5k', 'Voucher 2 Hari', 5000, 1, 0],
                        [1, '10k', 'Voucher 5 Hari', 10000, 1, 1],
                        [1, '15k', 'Voucher 7 Hari', 15000, 1, 0],
                        [1, '25k', 'Voucher 15 Hari', 25000, 1, 0],
                        [1, '50k', 'Voucher 30 Hari', 50000, 1, 0]
                    ];
                    $stmt = $pdo->prepare("INSERT IGNORE INTO agent_profile_pricing 
                        (agent_id, profile_name, display_name, price, is_active, is_featured) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($profiles as $profile) {
                        $stmt->execute($profile);
                    }
                    logMessage('✓ Profile pricing inserted', 'success');
                    
                    // 4. Insert payment methods
                    logMessage('Inserting payment methods...', 'info');
                    $pdo->exec("DELETE FROM payment_methods");
                    $methods = [
                        ['tripay', 'QRIS', 'QRIS (Semua Bank & E-Wallet)', 'qris', 'flat', 0, 10000, 5000000, 1, 1],
                        ['tripay', 'BRIVA', 'BRI Virtual Account', 'va', 'flat', 4000, 10000, 5000000, 1, 2],
                        ['tripay', 'BNIVA', 'BNI Virtual Account', 'va', 'flat', 4000, 10000, 5000000, 1, 3],
                        ['tripay', 'BCAVA', 'BCA Virtual Account', 'va', 'flat', 4000, 10000, 5000000, 1, 4],
                        ['tripay', 'OVO', 'OVO', 'ewallet', 'percentage', 2.5, 10000, 2000000, 1, 5],
                        ['tripay', 'DANA', 'DANA', 'ewallet', 'percentage', 2.5, 10000, 2000000, 1, 6],
                        ['tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 'percentage', 2.5, 10000, 2000000, 1, 7],
                        ['tripay', 'ALFAMART', 'Alfamart', 'retail', 'flat', 5000, 10000, 5000000, 1, 8]
                    ];
                    $stmt = $pdo->prepare("INSERT INTO payment_methods 
                        (gateway_name, method_code, method_name, method_type, admin_fee_type, admin_fee_value, min_amount, max_amount, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($methods as $method) {
                        $stmt->execute($method);
                    }
                    logMessage('✓ Payment methods inserted', 'success');
                    
                    // 5. Insert payment gateway config
                    logMessage('Inserting gateway config...', 'info');
                    $pdo->exec("INSERT IGNORE INTO payment_gateway_config 
                        (gateway_name, is_active, is_sandbox, api_key, api_secret, merchant_code) VALUES 
                        ('tripay', 1, 1, 'DEV-APIKEY-HERE', 'YOUR-PRIVATE-KEY', 'YOUR-MERCHANT-CODE')");
                    logMessage('✓ Gateway config inserted', 'success');
                    
                    // 6. Insert voucher settings
                    logMessage('Inserting voucher settings...', 'info');
                    $voucherSettings = [
                        ['voucher_header_text', 'Internet Voucher'],
                        ['voucher_footer_text', 'Terima kasih telah berlangganan'],
                        ['voucher_show_qr', '1'],
                        ['voucher_paper_size', 'A4']
                    ];
                    $stmt = $pdo->prepare("INSERT IGNORE INTO voucher_settings (setting_key, setting_value) VALUES (?, ?)");
                    foreach ($voucherSettings as $setting) {
                        $stmt->execute($setting);
                    }
                    logMessage('✓ Voucher settings inserted', 'success');
                    
                    // 7. Insert site pages
                    logMessage('Inserting site pages...', 'info');
                    $pages = [
                        ['terms', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan</h3><p>Silakan isi dengan syarat dan ketentuan Anda.</p>'],
                        ['privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3><p>Silakan isi dengan kebijakan privasi Anda.</p>'],
                        ['faq', 'FAQ', '<h3>FAQ</h3><p>Silakan isi dengan pertanyaan yang sering diajukan.</p>']
                    ];
                    $stmt = $pdo->prepare("INSERT IGNORE INTO site_pages (page_key, page_title, content) VALUES (?, ?, ?)");
                    foreach ($pages as $page) {
                        $stmt->execute($page);
                    }
                    logMessage('✓ Site pages inserted', 'success');
                    
                    logMessage('Sample data installation completed!', 'success');
                    
                    echo '</div>';
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Data Installation Complete!</h3>';
                    echo '<p>All sample data has been inserted successfully.</p>';
                    echo '<p><a href="?key=mikhmon-admin-2024&action=check" class="btn btn-primary">View Status</a></p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    logMessage('Error: ' . $e->getMessage(), 'error');
                    echo '</div>';
                    echo '<div class="alert alert-danger">';
                    echo '<h3>❌ Installation Failed</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // Optimize database
            elseif ($action === 'optimize') {
                echo '<div class="section">';
                echo '<h2>⚡ Optimizing Database</h2>';
                echo '<div class="log-area">';
                
                try {
                    logMessage('Starting database optimization...', 'info');
                    
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($tables as $table) {
                        $optimizeStmt = $pdo->query("OPTIMIZE TABLE `$table`");
                        $optimizeStmt->fetchAll(); // Fetch all results to avoid unbuffered query issues
                        logMessage("✓ Optimized table: $table", 'success');
                    }
                    
                    logMessage('Database optimization completed!', 'success');
                    
                    echo '</div>';
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Optimization Complete!</h3>';
                    echo '<p>All tables have been optimized.</p>';
                    echo '<p><a href="?key=mikhmon-admin-2024&action=check" class="btn btn-primary">View Status</a></p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    logMessage('Error: ' . $e->getMessage(), 'error');
                    echo '</div>';
                    echo '<div class="alert alert-danger">';
                    echo '<h3>❌ Optimization Failed</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // Backup database
            elseif ($action === 'backup') {
                echo '<div class="section">';
                echo '<h2>💾 Backing Up Database</h2>';
                echo '<div class="log-area">';
                
                try {
                    logMessage('Starting database backup...', 'info');
                    
                    // Get all table names
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Generate backup filename
                    $date = date('Y-m-d_H-i-s');
                    $filename = 'mikhmon_backup_' . $date . '.sql';
                    
                    // Start building SQL dump
                    $sqlDump = "-- MikhMon Database Backup\n";
                    $sqlDump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
                    $sqlDump .= "-- Database: " . DB_NAME . "\n\n";
                    
                    foreach ($tables as $table) {
                        try {
                            logMessage("Backing up table: $table", 'info');
                            
                            // Get table creation SQL
                            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
                            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
                            $sqlDump .= "\n-- Table structure for table `$table`\n";
                            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
                            $sqlDump .= $createRow[1] . ";\n\n";
                            
                            // Get table data
                            $dataStmt = $pdo->query("SELECT * FROM `$table`");
                            $rowCount = $dataStmt->rowCount();
                            
                            if ($rowCount > 0) {
                                $sqlDump .= "-- Dumping data for table `$table`\n";
                                
                                // Get column names
                                $columnStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                                $columns = $columnStmt->fetchAll(PDO::FETCH_COLUMN);
                                $columnList = '`' . implode('`, `', $columns) . '`';
                                
                                $sqlDump .= "INSERT INTO `$table` ($columnList) VALUES\n";
                                
                                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                                $rowStrings = [];
                                
                                foreach ($rows as $row) {
                                    $values = [];
                                    foreach ($row as $value) {
                                        if ($value === null) {
                                            $values[] = 'NULL';
                                        } else {
                                            $values[] = $pdo->quote($value);
                                        }
                                    }
                                    $rowStrings[] = "(" . implode(', ', $values) . ")";
                                }
                                
                                $sqlDump .= implode(",\n", $rowStrings) . ";\n";
                            }
                            
                            logMessage("✓ Backed up table: $table ($rowCount rows)", 'success');
                        } catch (Exception $e) {
                            logMessage("⚠ Warning: Could not backup table $table - " . $e->getMessage(), 'warning');
                            // Continue with other tables
                            continue;
                        }
                    }
                    
                    // Save to file
                    $backupDir = __DIR__ . '/backups';
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    
                    $filepath = $backupDir . '/' . $filename;
                    file_put_contents($filepath, $sqlDump);
                    
                    logMessage('Database backup completed!', 'success');
                    logMessage('Backup saved to: ' . $filepath, 'info');
                    
                    echo '</div>';
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Backup Complete!</h3>';
                    echo '<p>Database backup has been successfully created.</p>';
                    echo '<p><strong>File:</strong> ' . htmlspecialchars($filename) . '</p>';
                    echo '<p><strong>Location:</strong> backups/' . htmlspecialchars($filename) . '</p>';
                    echo '<p><a href="?key=mikhmon-admin-2024&action=check" class="btn btn-primary">View Status</a></p>';
                    echo '</div>';
                    
                } catch (Exception $e) {
                    logMessage('Error: ' . $e->getMessage(), 'error');
                    echo '</div>';
                    echo '<div class="alert alert-danger">';
                    echo '<h3>❌ Backup Failed</h3>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
            ?>
        </div>

        <div class="footer">
            <p><strong>MikhMon Admin Utility v2.0</strong></p>
            <p>All-in-One Installation & Fix Tool | © 2024 MikhMon Team</p>
            <p style="margin-top: 10px; color: #999; font-size: 12px;">
                ⚠️ <strong>Security Warning:</strong> Hapus file ini setelah instalasi selesai untuk keamanan!
            </p>
        </div>
    </div>
</body>
</html>
