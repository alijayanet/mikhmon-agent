<?php
/**
 * Telegram Bot Diagnostic Tool
 * Check semua kemungkinan masalah kenapa bot tidak merespon
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== TELEGRAM BOT DIAGNOSTIC ===\n\n";

// 1. Check database connection
echo "1. Checking Database Connection...\n";
require_once(__DIR__ . '/../include/db_config.php');
$db = getDBConnection();
if ($db) {
    echo "   ✅ Database Connected\n\n";
} else {
    echo "   ❌ Database Connection FAILED!\n\n";
    exit;
}

// 2. Check telegram_settings table
echo "2. Checking telegram_settings table...\n";
try {
    $stmt = $db->query("SELECT * FROM telegram_settings");
    if ($stmt) {
        echo "   ✅ Table exists\n";
        echo "   Settings:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "      - {$row['setting_key']}: {$row['setting_value']}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// 3. Load Telegram config
echo "3. Loading Telegram Config...\n";
require_once(__DIR__ . '/../include/telegram_config.php');
echo "   TELEGRAM_ENABLED: " . (TELEGRAM_ENABLED ? '✅ TRUE' : '❌ FALSE') . "\n";
echo "   TELEGRAM_BOT_TOKEN: " . (empty(TELEGRAM_BOT_TOKEN) ? '❌ EMPTY' : '✅ ' . substr(TELEGRAM_BOT_TOKEN, 0, 10) . '...') . "\n";
echo "   TELEGRAM_WEBHOOK_MODE: " . (TELEGRAM_WEBHOOK_MODE ? 'YES' : 'NO') . "\n\n";

// 4. Check if bot token is valid
echo "4. Testing Telegram Bot Token...\n";
if (!empty(TELEGRAM_BOT_TOKEN)) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getMe";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($httpCode == 200 && isset($result['ok']) && $result['ok']) {
        echo "   ✅ Bot Token VALID\n";
        echo "   Bot Info:\n";
        echo "      - Name: " . $result['result']['first_name'] . "\n";
        echo "      - Username: @" . $result['result']['username'] . "\n";
        echo "      - ID: " . $result['result']['id'] . "\n";
    } else {
        echo "   ❌ Bot Token INVALID or ERROR\n";
        echo "   Response: $response\n";
    }
} else {
    echo "   ❌ Bot token is EMPTY\n";
}
echo "\n";

// 5. Check webhook
echo "5. Checking Webhook Info...\n";
if (!empty(TELEGRAM_BOT_TOKEN)) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getWebhookInfo";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if (isset($result['result'])) {
        $info = $result['result'];
        echo "   Webhook URL: " . ($info['url'] ?? 'NOT SET') . "\n";
        echo "   Pending Updates: " . ($info['pending_update_count'] ?? 0) . "\n";
        echo "   Last Error: " . ($info['last_error_message'] ?? 'NONE') . "\n";
        echo "   Last Error Date: " . (isset($info['last_error_date']) ? date('Y-m-d H:i:s', $info['last_error_date']) : 'N/A') . "\n";
        
        if (!empty($info['last_error_message'])) {
            echo "   ❌ WEBHOOK ERROR DETECTED!\n";
        } else {
            echo "   ✅ No webhook errors\n";
        }
    }
}
echo "\n";

// 6. Check required files
echo "6. Checking Required Files...\n";
$files = [
    '/telegram_mikrotik_helpers.php',
    '/telegram_digiflazz_helpers.php',
    '/telegram_wifi_helpers.php'
];
foreach ($files as $file) {
    $path = __DIR__ . $file;
    if (file_exists($path)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file NOT FOUND\n";
    }
}
echo "\n";

// 7. Check logs directory
echo "7. Checking Logs Directory...\n";
$logsDir = __DIR__ . '/../logs';
if (is_dir($logsDir) && is_writable($logsDir)) {
    echo "   ✅ Logs directory exists and writable\n";
    
    // List recent log files
    $logFiles = glob($logsDir . '/*.log');
    if ($logFiles) {
        echo "   Recent log files:\n";
        foreach ($logFiles as $logFile) {
            $size = filesize($logFile);
            $modified = date('Y-m-d H:i:s', filemtime($logFile));
            echo "      - " . basename($logFile) . " (" . number_format($size) . " bytes, modified: $modified)\n";
        }
    }
} else {
    echo "   ❌ Logs directory not writable or doesn't exist\n";
}
echo "\n";

// 8. Test send message
echo "8. Test Send Message Function...\n";
try {
    // You can replace this with your own chat ID for testing
    // $testChatId = 567858628; // Replace with your chat ID
    // $result = sendTelegramMessage($testChatId, "🔧 Test message from diagnostic tool!");
    // if ($result['success']) {
    //     echo "   ✅ Message sent successfully!\n";
    // } else {
    //     echo "   ❌ Failed to send: " . $result['message'] . "\n";
    // }
    echo "   ⚠️  Skipped (uncomment code to test)\n";
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}
echo "\n";

// 9. Check database tables
echo "9. Checking Database Tables...\n";
$tables = ['telegram_webhook_log', 'telegram_settings', 'billing_customers', 'agents'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✅ $table ({$row['count']} rows)\n";
    } catch (Exception $e) {
        echo "   ❌ $table: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 10. Summary
echo "=== SUMMARY ===\n";
if (TELEGRAM_ENABLED && !empty(TELEGRAM_BOT_TOKEN)) {
    echo "✅ Bot should be OPERATIONAL\n";
    echo "\nIf bot still not responding, check:\n";
    echo "1. Webhook URL is correctly set in Telegram\n";
    echo "2. Server can receive HTTPS requests (SSL certificate valid)\n";
    echo "3. Check telegram_error.log for recent errors\n";
    echo "4. Try sending a test message via Telegram to trigger webhook\n";
} else {
    echo "❌ Bot is NOT operational. Fix issues above first!\n";
    
    if (!TELEGRAM_ENABLED) {
        echo "\n🔧 TO ENABLE BOT:\n";
        echo "UPDATE telegram_settings SET setting_value = '1' WHERE setting_key = 'telegram_enabled';\n";
    }
    
    if (empty(TELEGRAM_BOT_TOKEN)) {
        echo "\n🔧 TO SET BOT TOKEN:\n";
        echo "UPDATE telegram_settings SET setting_value = 'YOUR_BOT_TOKEN' WHERE setting_key = 'telegram_bot_token';\n";
    }
}

echo "\n=== END OF DIAGNOSTIC ===\n";
