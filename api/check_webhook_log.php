<?php
/*
 * Check Telegram Webhook Logs
 * Debug tool to see if webhook is receiving messages
 */

session_start();
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
if (!$isLocal && !isset($_SESSION['mikhmon'])) {
    http_response_code(404);
    exit;
}

require_once('../include/db_config.php');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Telegram Webhook Logs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #0088cc; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #0088cc; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .no-data { color: red; font-weight: bold; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>📊 Telegram Webhook Logs</h2>
";

try {
    $db = getDBConnection();
    
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'telegram_webhook_log'");
    if ($tableCheck->rowCount() == 0) {
        echo "<p class='no-data'>❌ Table 'telegram_webhook_log' tidak ditemukan!</p>";
        echo "<p>Jalankan installer untuk membuat tabel.</p>";
        exit;
    }
    
    // Get logs
    $stmt = $db->query("SELECT * FROM telegram_webhook_log ORDER BY created_at DESC LIMIT 20");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "<p class='no-data'>❌ Tidak ada log! Webhook belum menerima pesan dari Telegram.</p>";
        echo "<p><strong>Kemungkinan masalah:</strong></p>";
        echo "<ul>";
        echo "<li>Webhook belum di-set dengan benar</li>";
        echo "<li>Telegram tidak bisa akses webhook URL</li>";
        echo "<li>Ada error di webhook handler sebelum logging</li>";
        echo "</ul>";
    } else {
        echo "<p class='success'>✅ Ditemukan " . count($logs) . " log entries</p>";
        echo "<table>";
        echo "<tr>
                <th>Time</th>
                <th>Chat ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Message</th>
                <th>Command</th>
                <th>Status</th>
              </tr>";
        
        foreach ($logs as $log) {
            $statusClass = $log['status'] == 'success' ? 'success' : 'error';
            echo "<tr>";
            echo "<td>{$log['created_at']}</td>";
            echo "<td>{$log['chat_id']}</td>";
            echo "<td>" . htmlspecialchars($log['username']) . "</td>";
            echo "<td>" . htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($log['message']) . "</td>";
            echo "<td>" . htmlspecialchars($log['command']) . "</td>";
            echo "<td class='$statusClass'>{$log['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check file log
    echo "<h3>📄 File Logs</h3>";
    $webhookLog = __DIR__ . '/../logs/telegram_webhook.log';
    $errorLog = __DIR__ . '/../logs/telegram_error.log';
    
    if (file_exists($webhookLog)) {
        $lines = array_slice(file($webhookLog), -10);
        echo "<h4>telegram_webhook.log (last 10 lines):</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        echo htmlspecialchars(implode('', $lines));
        echo "</pre>";
    } else {
        echo "<p>File telegram_webhook.log tidak ditemukan</p>";
    }
    
    if (file_exists($errorLog)) {
        $lines = array_slice(file($errorLog), -10);
        echo "<h4>telegram_error.log (last 10 lines):</h4>";
        echo "<pre style='background: #fff5f5; padding: 10px; border-radius: 5px; color: red;'>";
        echo htmlspecialchars(implode('', $lines));
        echo "</pre>";
    } else {
        echo "<p>File telegram_error.log tidak ditemukan (bagus, tidak ada error)</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
