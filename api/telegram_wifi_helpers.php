<?php
/**
 * Telegram Helper Functions for WiFi Management (GenieACS)
 */

// Include GenieACS library if available (or use raw CURL as fallback as seen in WA webhook)
require_once __DIR__ . '/../include/db_config.php';

/**
 * Change WiFi SSID via Telegram
 */
function changeTelegramWiFiSSID($chatId, $ssid) {
    // Validate SSID
    if (strlen($ssid) < 3 || strlen($ssid) > 32) {
        sendTelegramMessage($chatId, "❌ *SSID TIDAK VALID*\n\nPanjang harus 3-32 karakter.");
        return;
    }
    if (preg_match('/[<>&"\'`]/', $ssid)) {
        sendTelegramMessage($chatId, "❌ *SSID TIDAK VALID*\n\nKarakter spesial tidak diperbolehkan.");
        return;
    }

    // Find Customer Device ID
    $deviceId = getTelegramDeviceID($chatId);
    
    if (!$deviceId) {
        sendTelegramMessage($chatId, "❌ *DEVICE TIDAK DITEMUKAN*\n\nAkun Telegram Anda tidak terhubung ke pelanggan WiFi.\nHubungi Admin.");
        return;
    }

    // Call GenieACS Update
    updateGenieACSParameter($chatId, $deviceId, 'SSID', $ssid);
}

/**
 * Change WiFi Password via Telegram
 */
function changeTelegramWiFiPassword($chatId, $password) {
    // Validate Password
    if (strlen($password) < 8 || strlen($password) > 32) {
        sendTelegramMessage($chatId, "❌ *PASSWORD TIDAK VALID*\n\nPanjang harus 8-32 karakter.");
        return;
    }
    if (preg_match('/[<>&"\'`]/', $password)) {
        sendTelegramMessage($chatId, "❌ *PASSWORD TIDAK VALID*\n\nKarakter spesial tidak diperbolehkan.");
        return;
    }

    // Find Customer Device ID
    $deviceId = getTelegramDeviceID($chatId);
    
    if (!$deviceId) {
        sendTelegramMessage($chatId, "❌ *DEVICE TIDAK DITEMUKAN*\n\nAkun Telegram Anda tidak terhubung ke pelanggan WiFi.\nHubungi Admin.");
        return;
    }

    // Call GenieACS Update
    updateGenieACSParameter($chatId, $deviceId, 'Password', $password);
}

/**
 * Helper to get Device ID (Username) from Telegram Chat ID
 */
function getTelegramDeviceID($chatId) {
    $db = getDBConnection();
    if (!$db) return null;

    // Check billing_customers - use genieacs_pppoe_username or service_number as device ID
    $stmt = $db->prepare("SELECT genieacs_pppoe_username, service_number FROM billing_customers WHERE telegram_chat_id = ? LIMIT 1");
    $stmt->execute([$chatId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        // Priority: genieacs_pppoe_username first, then service_number
        if (!empty($customer['genieacs_pppoe_username'])) {
            return $customer['genieacs_pppoe_username'];
        } elseif (!empty($customer['service_number'])) {
            return $customer['service_number'];
        }
    }

    return null;
}

/**
 * Execute GenieACS Update (Adapted from WhatsApp Webhook)
 */
function updateGenieACSParameter($chatId, $deviceId, $type, $value) {
    // Config GenieACS (Hardcoded for now based on WA webhook, should be in config)
    $genieacs_base = 'http://192.168.8.89:7557/api'; 
    $genieacs_url = $genieacs_base . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';

    $parameterName = '';
    $tasks = [];

    if ($type == 'SSID') {
        $tasks = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $value, 'xsd:string']
            ]
        ];
    } elseif ($type == 'Password') {
        $tasks = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $value, 'xsd:string'],
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', $value, 'xsd:string']
            ]
        ];
    }

    // Execute CURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $genieacs_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tasks));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    sendTelegramMessage($chatId, "⏳ *MEMPROSES PERMINTAAN*\n\nMohon tunggu sebentar...");
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || ($httpCode < 200 || $httpCode > 202)) {
        error_log("GenieACS Error: $error | Code: $httpCode | Resp: $response");
        sendTelegramMessage($chatId, "⚠️ *STATUS TIDAK PASTI*\n\nServer merespon lambat atau error.\nCode: $httpCode\nSilakan cek manual dalam 1-2 menit.");
        return;
    }

    sendTelegramMessage($chatId, "✅ *BERHASIL*\n\n$type telah diubah menjadi: `$value`\nPerangkat akan restart otomatis.");
}

/**
 * Search Device for Admin
 */
function findTelegramDevice($chatId, $query) {
    if (!isTelegramAdmin($chatId)) {
        return; // Silent fail or denied msg
    }
    
    //Reuse findDeviceByPhoneOrUsername logic from WA but return formatted msg for Telegram
    // Since we don't have direct access to that function unless we include whatsapp_webhook.php (bad idea),
    // we basically replicate the search logic or refactor it.
    // For now, let's implement a basic DB search + MikroTik search if possible.
    
    // Assuming simple search for now
    $db = getDBConnection();
    if ($db) {
        $stmt = $db->prepare("SELECT * FROM billing_customers WHERE username LIKE ? OR phone LIKE ? LIMIT 5");
        $like = "%$query%";
        $stmt->execute([$like, $like]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            sendTelegramMessage($chatId, "❌ *TIDAK DITEMUKAN*\n\nTidak ada data pelanggan cocok di database.");
            return;
        }
        
        $msg = "🔍 *HASIL PENCARIAN*\n\n";
        foreach ($results as $res) {
            $msg .= "👤 *" . $res['username'] . "*\n";
            $msg .= "📱 " . $res['phone'] . "\n";
            $msg .= "-------------------\n";
        }
        $msg .= "\nGunakan Username untuk Ganti WiFi.";
        sendTelegramMessage($chatId, $msg);
    }
}
?>
