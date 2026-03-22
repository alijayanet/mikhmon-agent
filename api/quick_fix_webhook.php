<?php
/**
 * Quick Fix: Set Telegram Webhook
 * Script ini akan otomatis set webhook URL yang benar
 */

session_start();
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
if (!$isLocal && !isset($_SESSION['mikhmon'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== QUICK FIX: SET TELEGRAM WEBHOOK ===\n\n";

// Load config
require_once(__DIR__ . '/../include/telegram_config.php');

$botToken = TELEGRAM_BOT_TOKEN;
if (empty($botToken)) {
    echo "❌ Bot token not configured!\n";
    exit;
}

// Determine webhook URL
// Try to get from current request or use default
$protocol = 'https://';
$host = 'billing.alijaya.net'; // Default host

// If running from web, use current host
if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
}

$webhookUrl = $protocol . $host . '/api/telegram_webhook.php';

echo "Bot Token: " . substr($botToken, 0, 10) . "...\n";
echo "Webhook URL: $webhookUrl\n\n";

// Step 1: Delete old webhook
echo "Step 1: Deleting old webhook...\n";
$url = "https://api.telegram.org/bot{$botToken}/deleteWebhook?drop_pending_updates=true";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "   ✅ Old webhook deleted!\n";
    echo "   ✅ Pending updates cleared!\n\n";
} else {
    echo "   ⚠️  " . ($result['description'] ?? 'Unknown error') . "\n\n";
}

// Step 2: Set new webhook
echo "Step 2: Setting new webhook...\n";
$url = "https://api.telegram.org/bot{$botToken}/setWebhook";

$data = [
    'url' => $webhookUrl,
    'drop_pending_updates' => true,
    'allowed_updates' => ['message', 'callback_query']
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "   ✅ Webhook set successfully!\n";
    echo "   URL: $webhookUrl\n\n";
} else {
    echo "   ❌ Failed: " . ($result['description'] ?? 'Unknown error') . "\n\n";
    exit;
}

// Step 3: Verify webhook
echo "Step 3: Verifying webhook...\n";
$url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
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
    
    if (empty($info['last_error_message']) && $info['pending_update_count'] == 0) {
        echo "\n✅✅✅ WEBHOOK SET SUCCESSFULLY! ✅✅✅\n\n";
        echo "🎉 Bot is now ready to receive messages!\n\n";
        echo "📋 NEXT STEPS:\n";
        echo "1. Open Telegram and find @mikrotik4011bot\n";
        echo "2. Send /start to test\n";
        echo "3. Configure admin chat ID in settings\n\n";
    } else {
        echo "\n⚠️  Webhook set but may have issues\n";
        if (!empty($info['last_error_message'])) {
            echo "Error: " . $info['last_error_message'] . "\n";
        }
    }
}

echo "\n=== DONE ===\n";
?>
