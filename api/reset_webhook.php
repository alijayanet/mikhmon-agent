<?php
/**
 * Reset Telegram Webhook
 * Untuk clear pending updates yang error
 */

header('Content-Type: text/plain; charset=utf-8');
require_once(__DIR__ . '/../include/telegram_config.php');

echo "=== TELEGRAM WEBHOOK RESET ===\n\n";

$botToken = TELEGRAM_BOT_TOKEN;
if (empty($botToken)) {
    echo "❌ Bot token not configured!\n";
    exit;
}

// Step 1: Delete webhook
echo "1. Deleting webhook...\n";
$url = "https://api.telegram.org/bot{$botToken}/deleteWebhook?drop_pending_updates=true";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['ok']) {
    echo "   ✅ Webhook deleted!\n";
    echo "   ✅ Pending updates cleared!\n\n";
} else {
    echo "   ❌ Failed: " . ($result['description'] ?? 'Unknown error') . "\n\n";
}

// Step 2: Set new webhook
echo "2. Setting new webhook...\n";
$webhookUrl = "https://billing.alijaya.net/api/telegram_webhook.php";
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
    echo "   Webhook URL: {$webhookUrl}\n\n";
} else {
    echo "   ❌ Failed: " . ($result['description'] ?? 'Unknown error') . "\n\n";
}

// Step 3: Verify webhook
echo "3. Verifying webhook...\n";
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
        echo "\n✅✅✅ WEBHOOK RESET SUCCESSFUL! ✅✅✅\n";
        echo "\nBot should now respond to commands!\n";
        echo "Try sending: /start\n";
    } else {
        echo "\n⚠️  Webhook set but may have issues\n";
    }
}

echo "\n=== END ===\n";
?>
