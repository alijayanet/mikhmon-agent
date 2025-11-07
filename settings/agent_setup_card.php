<?php
// Agent Setup Card - Add this to settings page
include_once('./include/db_config.php');

$agentDbStatus = 'not_installed';
try {
    $conn = getDBConnection();
    if ($conn) {
        $stmt = $conn->query("SHOW TABLES LIKE 'agents'");
        if ($stmt->rowCount() > 0) {
            $agentDbStatus = 'installed';
        }
    }
} catch (Exception $e) {
    $agentDbStatus = 'not_configured';
}
?>

<style>
.agent-setup-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin: 20px 0;
    box-shadow: 0 5px 20px rgba(102,126,234,0.3);
}

.agent-setup-card h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
}

.agent-setup-card p {
    opacity: 0.9;
    margin-bottom: 20px;
}

.agent-setup-status {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-installed {
    background: rgba(16, 185, 129, 0.3);
    border: 1px solid rgba(16, 185, 129, 0.5);
}

.status-not-installed {
    background: rgba(239, 68, 68, 0.3);
    border: 1px solid rgba(239, 68, 68, 0.5);
}

.agent-setup-btn {
    background: white;
    color: #667eea;
    padding: 12px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    margin-right: 10px;
    transition: all 0.3s;
}

.agent-setup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.agent-setup-btn.secondary {
    background: rgba(255,255,255,0.2);
    color: white;
}
</style>

<div class="agent-setup-card">
    <h3><i class="fa fa-users"></i> Agent/Reseller System</h3>
    
    <?php if ($agentDbStatus == 'installed'): ?>
        <span class="agent-setup-status status-installed">
            <i class="fa fa-check-circle"></i> Installed
        </span>
        <p>Sistem agent sudah aktif dan siap digunakan</p>
        <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="agent-setup-btn">
            <i class="fa fa-users"></i> Kelola Agent
        </a>
        <a href="../agent/index.php" class="agent-setup-btn secondary" target="_blank">
            <i class="fa fa-sign-in"></i> Agent Login
        </a>
    <?php else: ?>
        <span class="agent-setup-status status-not-installed">
            <i class="fa fa-exclamation-circle"></i> Not Installed
        </span>
        <p>Install database untuk mengaktifkan sistem agent/reseller voucher</p>
        <a href="../install_agent_system.php" class="agent-setup-btn" target="_blank">
            <i class="fa fa-download"></i> Install Agent System
        </a>
        <a href="../AGENT_SYSTEM_README.md" class="agent-setup-btn secondary" target="_blank">
            <i class="fa fa-book"></i> Dokumentasi
        </a>
    <?php endif; ?>
</div>
