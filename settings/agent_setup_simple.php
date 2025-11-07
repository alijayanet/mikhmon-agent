<?php
/*
 * Agent System Setup - Simple Version
 * Integrated with MikhMon theme and working properly
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');

$dbStatus = 'checking';
$agentCount = 0;
$settingsCount = 0;
$pricesCount = 0;

// Check database status
try {
    include_once('./include/db_config.php');
    $conn = getDBConnection();
    
    if ($conn) {
        // Check if agents table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'agents'");
        if ($stmt->rowCount() > 0) {
            $dbStatus = 'installed';
            
            // Get counts
            $stmt = $conn->query("SELECT COUNT(*) FROM agents");
            $agentCount = $stmt->fetchColumn();
            
            $stmt = $conn->query("SELECT COUNT(*) FROM agent_settings");
            $settingsCount = $stmt->fetchColumn();
            
            $stmt = $conn->query("SELECT COUNT(*) FROM agent_prices");
            $pricesCount = $stmt->fetchColumn();
        } else {
            $dbStatus = 'not_installed';
        }
    } else {
        $dbStatus = 'connection_error';
    }
} catch (Exception $e) {
    $dbStatus = 'error';
    $errorMessage = $e->getMessage();
}

?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-users"></i> Agent System Setup</h3>
            </div>
            <div class="card-body">
                
                <!-- Status Card -->
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> Agent System Status</h5>
                    
                    <?php if ($dbStatus === 'installed'): ?>
                        <div class="box box-bordered bg-green">
                            <div class="box-group">
                                <div class="box-group-icon">
                                    <i class="fa fa-check-circle"></i>
                                </div>
                                <div class="box-group-area">
                                    <span>
                                        <strong>Agent System Installed & Active</strong><br>
                                        System sudah berjalan dengan baik dan siap digunakan
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-4">
                                <div class="box bmh-75 box-bordered bg-blue">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-users"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong><?= $agentCount; ?></strong><br>
                                                Total Agents
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="box bmh-75 box-bordered bg-orange">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-cog"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong><?= $settingsCount; ?></strong><br>
                                                Settings
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="box bmh-75 box-bordered bg-purple">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-tags"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong><?= $pricesCount; ?></strong><br>
                                                Prices
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($dbStatus === 'not_installed'): ?>
                        <div class="box box-bordered bg-yellow">
                            <div class="box-group">
                                <div class="box-group-icon">
                                    <i class="fa fa-exclamation-triangle"></i>
                                </div>
                                <div class="box-group-area">
                                    <span>
                                        <strong>Agent System Not Installed</strong><br>
                                        Database tables belum dibuat. Gunakan installer untuk setup sistem agent.
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($dbStatus === 'connection_error'): ?>
                        <div class="box box-bordered bg-red">
                            <div class="box-group">
                                <div class="box-group-icon">
                                    <i class="fa fa-times-circle"></i>
                                </div>
                                <div class="box-group-area">
                                    <span>
                                        <strong>Database Connection Error</strong><br>
                                        Tidak dapat terhubung ke database. Periksa konfigurasi database di <code>include/db_config.php</code>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="box box-bordered bg-red">
                            <div class="box-group">
                                <div class="box-group-icon">
                                    <i class="fa fa-times-circle"></i>
                                </div>
                                <div class="box-group-area">
                                    <span>
                                        <strong>Database Error</strong><br>
                                        Error: <?= htmlspecialchars($errorMessage ?? 'Unknown error'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            
                            <?php if ($dbStatus === 'installed'): ?>
                            <!-- System is installed -->
                            <div class="col-3">
                                <div class="box bmh-75 box-bordered bg-blue pointer" onclick="window.location='./?hotspot=agent-list&session=<?= $session; ?>'">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-list"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Daftar Agent</strong><br>
                                                Kelola semua agent
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="box bmh-75 box-bordered bg-green pointer" onclick="window.location='./?hotspot=agent-add&session=<?= $session; ?>'">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-user-plus"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Tambah Agent</strong><br>
                                                Buat agent baru
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="box bmh-75 box-bordered bg-orange pointer" onclick="window.location='./?hotspot=agent-prices&session=<?= $session; ?>'">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-tags"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Harga Agent</strong><br>
                                                Atur pricing
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="box bmh-75 box-bordered bg-purple pointer" onclick="window.location='./?hotspot=agent-transactions&session=<?= $session; ?>'">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-history"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Transaksi</strong><br>
                                                Riwayat transaksi
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php else: ?>
                            <!-- System not installed -->
                            <div class="col-4">
                                <div class="box bmh-75 box-bordered bg-green pointer" onclick="window.open('install_database_bulletproof.php?key=mikhmon-install-2024', '_blank')">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-download"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Install Database</strong><br>
                                                Bulletproof installer
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="box bmh-75 box-bordered bg-blue pointer" onclick="window.open('install_database_ultimate.php?key=mikhmon-install-2024', '_blank')">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-cog"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Ultimate Installer</strong><br>
                                                Alternative installer
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="box bmh-75 box-bordered bg-orange pointer" onclick="window.open('DEPLOYMENT_GUIDE_SIMPLE.md', '_blank')">
                                    <div class="box-group">
                                        <div class="box-group-icon">
                                            <i class="fa fa-book"></i>
                                        </div>
                                        <div class="box-group-area">
                                            <span>
                                                <strong>Documentation</strong><br>
                                                Setup guide
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-info-circle"></i> System Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Database Status</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Connection</td>
                                        <td>
                                            <?php if ($dbStatus === 'installed' || $dbStatus === 'not_installed'): ?>
                                                <span class="badge badge-success">Connected</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Agent Tables</td>
                                        <td>
                                            <?php if ($dbStatus === 'installed'): ?>
                                                <span class="badge badge-success">Installed</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Not Installed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Status</td>
                                        <td>
                                            <?php 
                                            switch($dbStatus) {
                                                case 'installed':
                                                    echo '<span class="badge badge-success">Ready</span>';
                                                    break;
                                                case 'not_installed':
                                                    echo '<span class="badge badge-warning">Setup Required</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge badge-danger">Error</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Features Available</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fa fa-check text-success"></i> Agent Management</li>
                                    <li><i class="fa fa-check text-success"></i> Pricing Control</li>
                                    <li><i class="fa fa-check text-success"></i> Transaction History</li>
                                    <li><i class="fa fa-check text-success"></i> Public Voucher Sales</li>
                                    <li><i class="fa fa-check text-success"></i> Payment Gateway Integration</li>
                                    <li><i class="fa fa-check text-success"></i> WhatsApp Notifications</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Installation Guide -->
                <?php if ($dbStatus !== 'installed'): ?>
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-exclamation-triangle"></i> Installation Required</h3>
                    </div>
                    <div class="card-body">
                        <h5>Setup Steps:</h5>
                        <ol>
                            <li><strong>Database Configuration:</strong> Pastikan <code>include/db_config.php</code> sudah dikonfigurasi dengan benar</li>
                            <li><strong>Run Installer:</strong> Gunakan <strong>Bulletproof Installer</strong> untuk setup database yang error-free</li>
                            <li><strong>Verify Installation:</strong> Refresh halaman ini untuk melihat status terbaru</li>
                            <li><strong>Start Managing:</strong> Mulai mengelola agent dan pricing</li>
                        </ol>
                        
                        <div class="alert alert-info">
                            <strong>Recommended:</strong> Gunakan <strong>Bulletproof Installer</strong> untuk deployment yang bebas error.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<!-- Using MikhMon native styles -->
