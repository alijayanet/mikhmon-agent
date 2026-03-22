<?php
/*
 * View Telegram Send Debug Log
 */

session_start();
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
if (!$isLocal && !isset($_SESSION['mikhmon'])) {
    http_response_code(404);
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Telegram Send Debug Log</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        h2 { color: #4ec9b0; }
        pre { background: #252526; padding: 15px; border-radius: 5px; overflow-x: auto; line-height: 1.6; }
        .refresh { background: #0e639c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .refresh:hover { background: #1177bb; }
    </style>
</head>
<body>
    <h2>📤 Telegram Send Debug Log</h2>
    <a href='?' class='refresh'>🔄 Refresh</a>
";

$logFile = __DIR__ . '/../logs/telegram_send_debug.log';

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (empty($content)) {
        echo "<p style='color: #ce9178;'>Log file is empty. No messages have been sent yet.</p>";
    } else {
        echo "<pre>" . htmlspecialchars($content) . "</pre>";
    }
    
    echo "<p>File size: " . filesize($logFile) . " bytes</p>";
    echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "</p>";
} else {
    echo "<p style='color: #f48771;'>❌ Log file not found: $logFile</p>";
    echo "<p>This means sendTelegramMessage() has never been called.</p>";
}

echo "<hr>";
echo "<p><a href='check_webhook_log.php' style='color: #4ec9b0;'>📊 View Webhook Logs</a></p>";
echo "<p><a href='test_webhook_manual.php' style='color: #4ec9b0;'>🧪 Test Webhook Manually</a></p>";

echo "</body></html>";
?>
