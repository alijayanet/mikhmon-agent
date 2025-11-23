<?php
/**
 * WhatsApp WiFi Commands Handler
 * Handles GANTI WIFI and GANTI SANDI commands via GenieACS
 */

/**
 * Process WiFi-related commands
 * Returns true if command was processed, false otherwise
 */
function processWiFiCommand($phone, $messageLower, $messageTrimmed) {
    // Debug logging
    error_log("processWiFiCommand called - Phone: {$phone}, Message: {$messageLower}");
    
    // Command: GANTI WIFI <SSID_BARU> - Customer format (1 parameter)
    // Command: GANTI WIFI <IDENTIFIER> <SSID_BARU> - Admin format (2 parameters)
    if (strpos($messageLower, 'ganti wifi ') === 0) {
        error_log("GANTI WIFI command detected!");
        $rest = trim(str_replace('ganti wifi ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest, 2);
        
        error_log("Parts count: " . count($parts));
        
        if (count($parts) >= 2) {
            // Admin format: GANTI WIFI <IDENTIFIER> <SSID>
            $identifier = trim($parts[0]);
            $ssid = trim($parts[1]);
            
            error_log("Preparing to call changeCustomerWiFiSSIDByAdmin with ID: $identifier, SSID: $ssid");
            if (function_exists('changeCustomerWiFiSSIDByAdmin')) {
                changeCustomerWiFiSSIDByAdmin($phone, $identifier, $ssid);
            } else {
                error_log("FATAL: changeCustomerWiFiSSIDByAdmin function NOT FOUND!");
                sendWhatsAppMessage($phone, "❌ *SYSTEM ERROR*\n\nFungsi tidak ditemukan. Hubungi developer.");
            }
        } elseif (count($parts) == 1) {
            // Customer format: GANTI WIFI <SSID>
            $ssid = trim($parts[0]);
            
            error_log("Preparing to call changeCustomerWiFiSSID with SSID: $ssid");
            if (function_exists('changeCustomerWiFiSSID')) {
                changeCustomerWiFiSSID($phone, $ssid);
            } else {
                error_log("FATAL: changeCustomerWiFiSSID function NOT FOUND!");
                sendWhatsAppMessage($phone, "❌ *SYSTEM ERROR*\n\nFungsi tidak ditemukan. Hubungi developer.");
            }
        } else {
            sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat:\n• Customer: GANTI WIFI <SSID_BARU>\n• Admin: GANTI WIFI <NOMOR/USERNAME> <SSID_BARU>\n\nContoh:\n• GANTI WIFI MyWiFi\n• GANTI WIFI 081234567890 MyWiFi");
        }
        return true;
    }
    
    // Command: GANTI SANDI <PASSWORD_BARU> - Customer format (1 parameter)
    // Command: GANTI SANDI <IDENTIFIER> <PASSWORD_BARU> - Admin format (2 parameters)
    if (strpos($messageLower, 'ganti sandi ') === 0) {
        error_log("GANTI SANDI command detected!");
        $rest = trim(str_replace('ganti sandi ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest, 2);
        
        error_log("Parts count: " . count($parts));
        
        if (count($parts) >= 2) {
            // Admin format: GANTI SANDI <IDENTIFIER> <PASSWORD>
            $identifier = trim($parts[0]);
            $password = trim($parts[1]);
            
            error_log("Preparing to call changeCustomerWiFiPasswordByAdmin with ID: $identifier");
            if (function_exists('changeCustomerWiFiPasswordByAdmin')) {
                changeCustomerWiFiPasswordByAdmin($phone, $identifier, $password);
            } else {
                error_log("FATAL: changeCustomerWiFiPasswordByAdmin function NOT FOUND!");
            }
        } elseif (count($parts) == 1) {
            // Customer format: GANTI SANDI <PASSWORD>
            $password = trim($parts[0]);
            
            error_log("Preparing to call changeCustomerWiFiPassword");
            if (function_exists('changeCustomerWiFiPassword')) {
                changeCustomerWiFiPassword($phone, $password);
            } else {
                error_log("FATAL: changeCustomerWiFiPassword function NOT FOUND!");
            }
        } else {
            sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat:\n• Customer: GANTI SANDI <PASSWORD_BARU>\n• Admin: GANTI SANDI <NOMOR/USERNAME> <PASSWORD_BARU>\n\nContoh:\n• GANTI SANDI password123\n• GANTI SANDI 081234567890 password123");
        }
        return true;
    }
    
    return false;
}

/**
 * Change customer WiFi SSID (for customer themselves)
 */
function changeCustomerWiFiSSID($phone, $ssid) {
    // Normalize phone number: 62xxx -> 0xxx for GenieACS tag matching
    $normalizedPhone = $phone;
    if (substr($phone, 0, 2) === '62') {
        $normalizedPhone = '0' . substr($phone, 2);
    }
    
    // Validate SSID (1-32 characters)
    if (strlen($ssid) < 1 || strlen($ssid) > 32) {
        sendWhatsAppMessage($phone, "❌ *SSID TIDAK VALID*\n\nSSID harus 1-32 karakter.");
        return;
    }
    
    // Load GenieACS
    loadGenieACSConfig();
    
    // Load database for customer info
    if (!function_exists('getDBConnection')) {
        require_once(__DIR__ . '/../include/db_config.php');
    }
    
    try {
        // Initialize GenieACS
        $genieacs = new GenieACS();
        
        if (!$genieacs->isEnabled()) {
            sendWhatsAppMessage($phone, "❌ *GENIEACS TIDAK AKTIF*\n\nLayanan GenieACS sedang tidak tersedia.\nSilakan hubungi admin.");
            return;
        }
        
        // Query device by phone tag (use normalized phone)
        $query = ['_tags' => $normalizedPhone];
        $devicesResult = $genieacs->getDevices($query);
        
        // Debug logging
        error_log("GANTI WIFI - Phone: {$phone} (normalized: {$normalizedPhone}), Query result: " . json_encode(['success' => $devicesResult['success'], 'count' => count($devicesResult['data'] ?? [])]));
        
        if (!$devicesResult['success'] || empty($devicesResult['data'])) {
            sendWhatsAppMessage($phone, "❌ *DEVICE TIDAK DITEMUKAN*\n\nDevice GenieACS dengan tag nomor Anda tidak ditemukan.\n\nPastikan:\n1. Device sudah terdaftar di GenieACS\n2. Device sudah di-tag dengan nomor: {$normalizedPhone}\n\nSilakan hubungi admin.");
            return;
        }
        
        // Get first device (should only be one per phone number)
        $device = $devicesResult['data'][0];
        $deviceId = $device['_id'] ?? '';
        
        // Debug logging
        error_log("GANTI WIFI - Device ID: {$deviceId}");
        
        if (empty($deviceId)) {
            sendWhatsAppMessage($phone, "❌ *DEVICE ID TIDAK VALID*\n\nSilakan hubungi admin.");
            return;
        }
        
        // Get customer name from database (optional)
        $customerName = 'Customer';
        
        try {
            $conn = getDBConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT name FROM customers WHERE phone = ? LIMIT 1");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $customerName = $row['name'] ?? 'Customer';
                }
            }
        } catch (Exception $e) {
            error_log("Database error (ignoring): " . $e->getMessage());
        }
        
        // Send processing message
        sendWhatsAppMessage($phone, "⏳ *MEMPROSES PERUBAHAN SSID*\n\nMohon tunggu sebentar...");
        
        // Change SSID only (password = null)
        $result = $genieacs->changeWiFi($deviceId, $ssid, null);
        
        // Debug logging
        error_log("GANTI WIFI - API Result: " . json_encode(['success' => $result['success'], 'message' => $result['message'] ?? 'no message']));
        
        if ($result['success']) {
            $message = "✅ *SSID WIFI BERHASIL DIUBAH*\n\n";
            $message .= "Nama: {$customerName}\n";
            $message .= "SSID Baru: *{$ssid}*\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "Perubahan akan diterapkan dalam beberapa saat.\n";
            $message .= "Silakan restart perangkat WiFi Anda jika perlu.";
            
            sendWhatsAppMessage($phone, $message);
            
            // Log the change
            if (function_exists('logWebhook')) {
                logWebhook("SSID changed for {$customerName} (Phone: {$phone}, Device: {$deviceId}) - SSID: {$ssid}");
            }
        } else {
            $errorMsg = $result['message'] ?? 'Gagal mengubah SSID';
            sendWhatsAppMessage($phone, "❌ *GAGAL MENGUBAH SSID*\n\n{$errorMsg}\n\nSilakan hubungi admin.");
            
            if (function_exists('logWebhookError')) {
                logWebhookError($phone, "GANTI WIFI", $errorMsg);
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        sendWhatsAppMessage($phone, "❌ *ERROR*\n\n{$errorMsg}\n\nSilakan hubungi admin.");
        
        if (function_exists('logWebhookError')) {
            logWebhookError($phone, "GANTI WIFI", $errorMsg);
        }
    }
}

/**
 * Change customer WiFi password (for customer themselves)
 */
function changeCustomerWiFiPassword($phone, $password) {
    // Normalize phone number: 62xxx -> 0xxx
    $normalizedPhone = $phone;
    if (substr($phone, 0, 2) === '62') {
        $normalizedPhone = '0' . substr($phone, 2);
    }
    
    // Validate password (min 8 characters for WPA2)
    if (strlen($password) < 8) {
        sendWhatsAppMessage($phone, "❌ *PASSWORD TERLALU PENDEK*\n\nPassword minimal 8 karakter.");
        return;
    }
    
    // Load GenieACS
    loadGenieACSConfig();
    
    // Load database
    if (!function_exists('getDBConnection')) {
        require_once(__DIR__ . '/../include/db_config.php');
    }
    
    try {
        $genieacs = new GenieACS();
        
        if (!$genieacs->isEnabled()) {
            sendWhatsAppMessage($phone, "❌ *GENIEACS TIDAK AKTIF*\n\nLayanan GenieACS sedang tidak tersedia.\nSilakan hubungi admin.");
            return;
        }
        
        // Query device by phone tag
        $query = ['_tags' => $normalizedPhone];
        $devicesResult = $genieacs->getDevices($query);
        
        error_log("GANTI SANDI - Phone: {$phone} (normalized: {$normalizedPhone}), Query result: " . json_encode(['success' => $devicesResult['success'], 'count' => count($devicesResult['data'] ?? [])]));
        
        if (!$devicesResult['success'] || empty($devicesResult['data'])) {
            sendWhatsAppMessage($phone, "❌ *DEVICE TIDAK DITEMUKAN*\n\nDevice GenieACS dengan tag nomor Anda tidak ditemukan.\n\nPastikan:\n1. Device sudah terdaftar di GenieACS\n2. Device sudah di-tag dengan nomor: {$normalizedPhone}\n\nSilakan hubungi admin.");
            return;
        }
        
        $device = $devicesResult['data'][0];
        $deviceId = $device['_id'] ?? '';
        
        error_log("GANTI SANDI - Device ID: {$deviceId}");
        
        if (empty($deviceId)) {
            sendWhatsAppMessage($phone, "❌ *DEVICE ID TIDAK VALID*\n\nSilakan hubungi admin.");
            return;
        }
        
        // Get customer name from database (optional)
        $customerName = 'Customer';
        
        try {
            $conn = getDBConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT name FROM customers WHERE phone = ? LIMIT 1");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $customerName = $row['name'] ?? 'Customer';
                }
            }
        } catch (Exception $e) {
            error_log("Database error (ignoring): " . $e->getMessage());
        }
        
        // Send processing message
        sendWhatsAppMessage($phone, "⏳ *MEMPROSES PERUBAHAN PASSWORD*\n\nMohon tunggu sebentar...");
        
        // Change password only (ssid = null)
        $result = $genieacs->changeWiFi($deviceId, null, $password);
        
        error_log("GANTI SANDI - API Result: " . json_encode(['success' => $result['success'], 'message' => $result['message'] ?? 'no message']));
        
        if ($result['success']) {
            $message = "✅ *PASSWORD WIFI BERHASIL DIUBAH*\n\n";
            $message .= "Nama: {$customerName}\n";
            $message .= "Password Baru: `{$password}`\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "Perubahan akan diterapkan dalam beberapa saat.\n";
            $message .= "Silakan restart perangkat WiFi Anda jika perlu.";
            
            sendWhatsAppMessage($phone, $message);
            
            if (function_exists('logWebhook')) {
                logWebhook("Password changed for {$customerName} (Phone: {$phone}, Device: {$deviceId})");
            }
        } else {
            $errorMsg = $result['message'] ?? 'Gagal mengubah password';
            sendWhatsAppMessage($phone, "❌ *GAGAL MENGUBAH PASSWORD*\n\n{$errorMsg}\n\nSilakan hubungi admin.");
            
            if (function_exists('logWebhookError')) {
                logWebhookError($phone, "GANTI SANDI", $errorMsg);
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        sendWhatsAppMessage($phone, "❌ *ERROR*\n\n{$errorMsg}\n\nSilakan hubungi admin.");
        
        if (function_exists('logWebhookError')) {
            logWebhookError($phone, "GANTI SANDI", $errorMsg);
        }
    }
}

/**
 * Change customer WiFi SSID by admin
 */
function changeCustomerWiFiSSIDByAdmin($adminPhone, $identifier, $ssid) {
    error_log("changeCustomerWiFiSSIDByAdmin called - Admin: $adminPhone, ID: $identifier, SSID: $ssid");
    
    // Normalize identifier phone number: 62xxx -> 0xxx
    $normalizedIdentifier = $identifier;
    if (substr($identifier, 0, 2) === '62') {
        $normalizedIdentifier = '0' . substr($identifier, 2);
    }
    
    // Validate SSID (1-32 characters)
    if (strlen($ssid) < 1 || strlen($ssid) > 32) {
        error_log("SSID validation failed: $ssid");
        sendWhatsAppMessage($adminPhone, "❌ *SSID TIDAK VALID*\n\nSSID harus 1-32 karakter.");
        return;
    }
    
    // Load GenieACS
    loadGenieACSConfig();
    
    // Load database for customer info
    if (!function_exists('getDBConnection')) {
        require_once(__DIR__ . '/../include/db_config.php');
    }
    
    try {
        error_log("Initializing GenieACS...");
        // Initialize GenieACS
        $genieacs = new GenieACS();
        error_log("GenieACS initialized");
        
        if (!$genieacs->isEnabled()) {
            error_log("GenieACS is disabled");
            sendWhatsAppMessage($adminPhone, "❌ *GENIEACS TIDAK AKTIF*\n\nLayanan GenieACS sedang tidak tersedia.\nSilakan hubungi admin.");
            return;
        }
        
        // Query device by identifier tag (normalized)
        $query = ['_tags' => $normalizedIdentifier];
        $devicesResult = $genieacs->getDevices($query);
        
        // Debug logging
        error_log("GANTI WIFI ADMIN - Identifier: {$identifier} (normalized: {$normalizedIdentifier}), Query result: " . json_encode(['success' => $devicesResult['success'], 'count' => count($devicesResult['data'] ?? [])]));
        
        if (!$devicesResult['success'] || empty($devicesResult['data'])) {
            sendWhatsAppMessage($adminPhone, "❌ *DEVICE TIDAK DITEMUKAN*\n\nDevice GenieACS dengan tag identifier tidak ditemukan.\n\nIdentifier: {$normalizedIdentifier}\n\nPastikan:\n1. Device sudah terdaftar di GenieACS\n2. Device sudah di-tag dengan: {$normalizedIdentifier}\n\nSilakan cek GenieACS.");
            return;
        }
        
        // Get first device (should only be one per identifier)
        $device = $devicesResult['data'][0];
        $deviceId = $device['_id'] ?? '';
        
        // Debug logging
        error_log("GANTI WIFI ADMIN - Device ID: {$deviceId}");
        
        if (empty($deviceId)) {
            sendWhatsAppMessage($adminPhone, "❌ *DEVICE ID TIDAK VALID*\n\nSilakan hubungi admin.");
            return;
        }
        
        // Get customer info (optional)
        $customerName = 'Customer';
        $customerPhone = '';
        
        try {
            $conn = getDBConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT name, phone FROM customers WHERE phone = ? OR genieacs_pppoe_username = ? LIMIT 1");
                $stmt->bind_param("ss", $normalizedIdentifier, $normalizedIdentifier);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $customerName = $row['name'] ?? 'Customer';
                    $customerPhone = $row['phone'] ?? '';
                }
            }
        } catch (Exception $e) {
            error_log("Database error (ignoring): " . $e->getMessage());
        }
        
        // Send processing message
        sendWhatsAppMessage($adminPhone, "⏳ *MEMPROSES PERUBAHAN SSID*\n\nCustomer: {$customerName}\nMohon tunggu...");
        
        // Change SSID only (password = null)
        $result = $genieacs->changeWiFi($deviceId, $ssid, null);
        
        // Debug logging
        error_log("GANTI WIFI ADMIN - API Result: " . json_encode(['success' => $result['success'], 'message' => $result['message'] ?? 'no message']));
        
        if ($result['success']) {
            $message = "✅ *SSID WIFI BERHASIL DIUBAH*\n\n";
            $message .= "Customer: {$customerName}\n";
            if (!empty($customerPhone)) {
                $message .= "Phone: {$customerPhone}\n";
            }
            $message .= "SSID Baru: *{$ssid}*\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "Perubahan akan diterapkan dalam beberapa saat.";
            
            sendWhatsAppMessage($adminPhone, $message);
            
            // Notify customer if phone available
            if (!empty($customerPhone)) {
                $customerMsg = "✅ *SSID WIFI ANDA TELAH DIUBAH*\n\n";
                $customerMsg .= "SSID Baru: *{$ssid}*\n\n";
                $customerMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $customerMsg .= "Perubahan dilakukan oleh admin.\n";
                $customerMsg .= "Silakan restart perangkat WiFi Anda jika perlu.";
                
                sendWhatsAppMessage($customerPhone, $customerMsg);
            }
            
            // Log the change
            if (function_exists('logWebhook')) {
                logWebhook("SSID changed by admin for {$customerName} (Identifier: {$normalizedIdentifier}, Device: {$deviceId}) - SSID: {$ssid}");
            }
        } else {
            $errorMsg = $result['message'] ?? 'Gagal mengubah SSID';
            sendWhatsAppMessage($adminPhone, "❌ *GAGAL MENGUBAH SSID*\n\n{$errorMsg}\n\nSilakan coba lagi atau hubungi support.");
            
            if (function_exists('logWebhookError')) {
                logWebhookError($adminPhone, "GANTI WIFI ADMIN", $errorMsg);
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        sendWhatsAppMessage($adminPhone, "❌ *ERROR*\n\n{$errorMsg}\n\nSilakan hubungi support.");
        
        if (function_exists('logWebhookError')) {
            logWebhookError($adminPhone, "GANTI WIFI ADMIN", $errorMsg);
        }
    }
}

/**
 * Change customer WiFi password by admin
 */
function changeCustomerWiFiPasswordByAdmin($adminPhone, $identifier, $password) {
    // Normalize identifier phone number: 62xxx -> 0xxx
    $normalizedIdentifier = $identifier;
    if (substr($identifier, 0, 2) === '62') {
        $normalizedIdentifier = '0' . substr($identifier, 2);
    }
    
    // Validate password (min 8 characters)
    if (strlen($password) < 8) {
        sendWhatsAppMessage($adminPhone, "❌ *PASSWORD TERLALU PENDEK*\n\nPassword minimal 8 karakter.");
        return;
    }
    
    // Load GenieACS
    loadGenieACSConfig();
    
    // Load database
    if (!function_exists('getDBConnection')) {
        require_once(__DIR__ . '/../include/db_config.php');
    }
    
    try {
        $genieacs = new GenieACS();
        
        if (!$genieacs->isEnabled()) {
            sendWhatsAppMessage($adminPhone, "❌ *GENIEACS TIDAK AKTIF*\n\nLayanan GenieACS sedang tidak tersedia.\nSilakan hubungi admin.");
            return;
        }
        
        // Query device by identifier tag
        $query = ['_tags' => $normalizedIdentifier];
        $devicesResult = $genieacs->getDevices($query);
        
        error_log("GANTI SANDI ADMIN - Identifier: {$identifier} (normalized: {$normalizedIdentifier}), Query result: " . json_encode(['success' => $devicesResult['success'], 'count' => count($devicesResult['data'] ?? [])]));
        
        if (!$devicesResult['success'] || empty($devicesResult['data'])) {
            sendWhatsAppMessage($adminPhone, "❌ *DEVICE TIDAK DITEMUKAN*\n\nDevice GenieACS dengan tag identifier tidak ditemukan.\n\nIdentifier: {$normalizedIdentifier}\n\nPastikan:\n1. Device sudah terdaftar di GenieACS\n2. Device sudah di-tag dengan: {$normalizedIdentifier}\n\nSilakan cek GenieACS.");
            return;
        }
        
        $device = $devicesResult['data'][0];
        $deviceId = $device['_id'] ?? '';
        
        error_log("GANTI SANDI ADMIN - Device ID: {$deviceId}");
        
        if (empty($deviceId)) {
            sendWhatsAppMessage($adminPhone, "❌ *DEVICE ID TIDAK VALID*\n\nSilakan hubungi admin.");
            return;
        }
        
        // Get customer info (optional)
        $customerName = 'Customer';
        $customerPhone = '';
        
        try {
            $conn = getDBConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT name, phone FROM customers WHERE phone = ? OR genieacs_pppoe_username = ? LIMIT 1");
                $stmt->bind_param("ss", $normalizedIdentifier, $normalizedIdentifier);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $customerName = $row['name'] ?? 'Customer';
                    $customerPhone = $row['phone'] ?? '';
                }
            }
        } catch (Exception $e) {
            error_log("Database error (ignoring): " . $e->getMessage());
        }
        
        // Send processing message
        sendWhatsAppMessage($adminPhone, "⏳ *MEMPROSES PERUBAHAN PASSWORD*\n\nCustomer: {$customerName}\nMohon tunggu...");
        
        // Change password only (ssid = null)
        $result = $genieacs->changeWiFi($deviceId, null, $password);
        
        error_log("GANTI SANDI ADMIN - API Result: " . json_encode(['success' => $result['success'], 'message' => $result['message'] ?? 'no message']));
        
        if ($result['success']) {
            $message = "✅ *PASSWORD WIFI BERHASIL DIUBAH*\n\n";
            $message .= "Customer: {$customerName}\n";
            if (!empty($customerPhone)) {
                $message .= "Phone: {$customerPhone}\n";
            }
            $message .= "Password Baru: `{$password}`\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "Perubahan akan diterapkan dalam beberapa saat.";
            
            sendWhatsAppMessage($adminPhone, $message);
            
            // Notify customer
            if (!empty($customerPhone)) {
                $customerMsg = "✅ *PASSWORD WIFI ANDA TELAH DIUBAH*\n\n";
                $customerMsg .= "Password Baru: `{$password}`\n\n";
                $customerMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $customerMsg .= "Perubahan dilakukan oleh admin.\n";
                $customerMsg .= "Silakan restart perangkat WiFi Anda jika perlu.";
                
                sendWhatsAppMessage($customerPhone, $customerMsg);
            }
            
            // Log the change
            if (function_exists('logWebhook')) {
                logWebhook("Password changed by admin for {$customerName} (Identifier: {$normalizedIdentifier}, Device: {$deviceId})");
            }
        } else {
            $errorMsg = $result['message'] ?? 'Gagal mengubah password';
            sendWhatsAppMessage($adminPhone, "❌ *GAGAL MENGUBAH PASSWORD*\n\n{$errorMsg}\n\nSilakan coba lagi atau hubungi support.");
            
            if (function_exists('logWebhookError')) {
                logWebhookError($adminPhone, "GANTI SANDI ADMIN", $errorMsg);
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        sendWhatsAppMessage($adminPhone, "❌ *ERROR*\n\n{$errorMsg}\n\nSilakan hubungi support.");
        
        if (function_exists('logWebhookError')) {
            logWebhookError($adminPhone, "GANTI SANDI ADMIN", $errorMsg);
        }
    }
}

/**
 * Helper to load GenieACS config and class with backward compatibility
 */
function loadGenieACSConfig() {
    // Check if already loaded
    if (class_exists('GenieACS')) {
        return true;
    }

    $genieacsConfigPath = __DIR__ . '/../genieacs/config.php';
    if (file_exists($genieacsConfigPath)) {
        require_once($genieacsConfigPath);
        
        // Fallback: Define constants if config.php didn't (backward compatibility)
        // Variables $genieacs_host etc. are available here from the include
        
        if (!defined('GENIEACS_API_URL') && isset($genieacs_host)) {
             $proto = $genieacs_protocol ?? 'http';
             $port = $genieacs_port ?? 7557;
             define('GENIEACS_API_URL', "$proto://$genieacs_host:$port");
        }
        
        if (!defined('GENIEACS_USERNAME') && isset($genieacs_username)) define('GENIEACS_USERNAME', $genieacs_username);
        if (!defined('GENIEACS_PASSWORD') && isset($genieacs_password)) define('GENIEACS_PASSWORD', $genieacs_password);
        if (!defined('GENIEACS_TIMEOUT')) define('GENIEACS_TIMEOUT', $genieacs_timeout ?? 30);
        if (!defined('GENIEACS_ENABLED')) define('GENIEACS_ENABLED', $genieacs_enabled ?? true);
        
        // WiFi Paths
        if (!defined('GENIEACS_WIFI_SSID_PATH')) define('GENIEACS_WIFI_SSID_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
        if (!defined('GENIEACS_WIFI_PASSWORD_PATH')) define('GENIEACS_WIFI_PASSWORD_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase');
        if (!defined('GENIEACS_WIFI_SSID_5G_PATH')) define('GENIEACS_WIFI_SSID_5G_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID');
        if (!defined('GENIEACS_WIFI_PASSWORD_5G_PATH')) define('GENIEACS_WIFI_PASSWORD_5G_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.KeyPassphrase');
    }
    
    $genieacsClassPath = __DIR__ . '/../genieacs/lib/GenieACS.class.php';
    if (file_exists($genieacsClassPath)) {
        require_once($genieacsClassPath);
        return true;
    }
    
    return false;
}
