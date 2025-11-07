<?php
/*
 * Agent System Setup - Integrated with MikhMon Settings
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');

$step = $_GET['setup_step'] ?? 'check';
$error = '';
$success = '';
$dbStatus = 'not_installed';

// Check if database already exists
try {
    $conn = getDBConnection();
    if ($conn) {
        $stmt = $conn->query("SHOW TABLES LIKE 'agents'");
        if ($stmt->rowCount() > 0) {
            $dbStatus = 'installed';
        }
    }
} catch (Exception $e) {
    $dbStatus = 'not_configured';
}

// Handle installation
if (isset($_POST['install_agent_db'])) {
    try {
        $host = $_POST['db_host'] ?? 'localhost';
        $user = $_POST['db_user'] ?? 'root';
        $pass = $_POST['db_pass'] ?? '';
        $dbname = $_POST['db_name'] ?? 'mikhmon_agents';
        
        // Connect to MySQL
        $conn = new PDO("mysql:host=$host", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE `$dbname`");
        
        // Read and execute SQL file
        $sqlFile = __DIR__ . '/../database/agent_system.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executed = 0;
            
            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                try {
                    $conn->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
            
            // Update db_config.php
            $configFile = __DIR__ . '/../include/db_config.php';
            $configContent = file_get_contents($configFile);
            
            $configContent = preg_replace("/define\('DB_HOST', '.*?'\);/", "define('DB_HOST', '$host');", $configContent);
            $configContent = preg_replace("/define\('DB_USER', '.*?'\);/", "define('DB_USER', '$user');", $configContent);
            $configContent = preg_replace("/define\('DB_PASS', '.*?'\);/", "define('DB_PASS', '$pass');", $configContent);
            $configContent = preg_replace("/define\('DB_NAME', '.*?'\);/", "define('DB_NAME', '$dbname');", $configContent);
            
            file_put_contents($configFile, $configContent);
            
            $success = "Database agent system berhasil diinstall! ($executed tables created)";
            $dbStatus = 'installed';
            $step = 'success';
        } else {
            throw new Exception("File agent_system.sql tidak ditemukan!");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.setup-header {
    text-align: center;
    margin-bottom: 30px;
}

.setup-header .icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.status-badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    margin: 10px 0;
}

.status-installed {
    background: #d1fae5;
    color: #065f46;
}

.status-not-installed {
    background: #fee2e2;
    color: #991b1b;
}

.status-not-configured {
    background: #fef3c7;
    color: #92400e;
}

.info-box {
    background: #f0f9ff;
    border-left: 4px solid #3b82f6;
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
}

.info-box h4 {
    color: #1e40af;
    margin-bottom: 10px;
}

.info-box ul {
    margin-left: 20px;
    line-height: 1.8;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.feature-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.feature-card i {
    font-size: 32px;
    color: #667eea;
    margin-bottom: 10px;
}

.feature-card h4 {
    margin-bottom: 5px;
    color: #333;
}

.feature-card p {
    font-size: 13px;
    color: #666;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-cog"></i> Agent System Setup</h3>
</div>
<div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if ($dbStatus == 'installed'): ?>
    <!-- Already Installed -->
    <div class="card">
        <div class="card-body">
            <div class="setup-header">
                <div class="icon" style="color: #10b981;">
                    <i class="fa fa-check-circle"></i>
                </div>
                <h2>Agent System Sudah Terinstall</h2>
                <span class="status-badge status-installed">✓ Installed</span>
            </div>

            <div class="info-box">
                <h4><i class="fa fa-info-circle"></i> Status Sistem</h4>
                <ul>
                    <li>Database agent system sudah aktif</li>
                    <li>Semua table sudah terbuat</li>
                    <li>Sistem siap digunakan</li>
                </ul>
            </div>

            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fa fa-users"></i>
                    <h4>Kelola Agent</h4>
                    <p>Tambah & kelola agent/reseller</p>
                </div>
                <div class="feature-card">
                    <i class="fa fa-tags"></i>
                    <h4>Set Harga</h4>
                    <p>Atur harga per profile</p>
                </div>
                <div class="feature-card">
                    <i class="fa fa-money"></i>
                    <h4>Topup Saldo</h4>
                    <p>Kelola saldo agent</p>
                </div>
                <div class="feature-card">
                    <i class="fa fa-ticket"></i>
                    <h4>Generate Voucher</h4>
                    <p>Agent generate voucher</p>
                </div>
            </div>

            <div class="action-buttons">
                <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn btn-primary btn-block">
                    <i class="fa fa-users"></i> Kelola Agent
                </a>
                <a href="../agent/index.php" class="btn btn-success btn-block" target="_blank">
                    <i class="fa fa-sign-in"></i> Agent Login
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Not Installed -->
    <div class="card">
        <div class="card-body">
            <div class="setup-header">
                <div class="icon" style="color: #667eea;">
                    <i class="fa fa-database"></i>
                </div>
                <h2>Setup Agent System</h2>
                <span class="status-badge status-not-installed">⚠ Not Installed</span>
                <p style="color: #666; margin-top: 10px;">Install database untuk mengaktifkan sistem agent/reseller</p>
            </div>

            <div class="info-box">
                <h4><i class="fa fa-star"></i> Fitur Agent System</h4>
                <ul>
                    <li>Kelola agent/reseller voucher</li>
                    <li>Set harga beli & jual per profile</li>
                    <li>Sistem saldo & topup otomatis</li>
                    <li>Generate voucher dengan saldo</li>
                    <li>Komisi otomatis untuk agent</li>
                    <li>WhatsApp notification</li>
                    <li>Mobile responsive agent panel</li>
                    <li>Tracking transaksi lengkap</li>
                </ul>
            </div>

            <form method="POST">
                <h3 style="margin-bottom: 20px;"><i class="fa fa-cog"></i> Konfigurasi Database</h3>
                
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>

                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" class="form-control" value="root" required>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="Kosongkan jika tidak ada password">
                </div>

                <div class="form-group">
                    <label>Nama Database</label>
                    <input type="text" name="db_name" class="form-control" value="mikhmon_agents" required>
                    <small style="color: #666;">Database akan dibuat otomatis jika belum ada</small>
                </div>

                <button type="submit" name="install_agent_db" class="btn btn-primary btn-block">
                    <i class="fa fa-download"></i> Install Agent System
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Documentation -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-book"></i> Dokumentasi</h3>
        </div>
        <div class="card-body">
            <div style="margin-top: 15px;">
                <a href="../AGENT_SYSTEM_README.md" target="_blank" style="color: #667eea; text-decoration: none;">
                    <i class="fa fa-file-text"></i> Agent System README
                </a>
                <br>
                <a href="../INSTALLATION_GUIDE.txt" target="_blank" style="color: #667eea; text-decoration: none; margin-top: 10px; display: inline-block;">
                    <i class="fa fa-file-text"></i> Installation Guide
                </a>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>
