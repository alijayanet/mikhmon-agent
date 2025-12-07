<?php
/*
 * Telegram Webhook Handler for MikhMon
 * Handle incoming Telegram messages for voucher purchase and management
 */

// Disable error display (log only)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/telegram_error.log');

// Load required files
require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../include/telegram_config.php');

// Load MikroTik helper functions
require_once(__DIR__ . '/telegram_mikrotik_helpers.php');
// Load Digiflazz helper functions
require_once(__DIR__ . '/telegram_digiflazz_helpers.php');
// Load WiFi helper functions
require_once(__DIR__ . '/telegram_wifi_helpers.php');

// Check if Telegram is enabled
if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED) {
    http_response_code(200); // Return 200 to prevent Telegram retries
    echo json_encode(['ok' => false, 'description' => 'Telegram bot is disabled']);
    exit;
}

// Get webhook data
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log incoming webhook
logTelegramWebhook($input);

// Process update
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $username = isset($message['from']['username']) ? $message['from']['username'] : '';
    $firstName = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
    $lastName = isset($message['from']['last_name']) ? $message['from']['last_name'] : '';
    $text = isset($message['text']) ? trim($message['text']) : '';
    
    if (!empty($text)) {
        // Process command
        processTelegramCommand($chatId, $text, $username, $firstName, $lastName);
    }
}

// Process callback queries (for inline keyboards)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];
    
    // Handle callback data
    handleCallbackQuery($chatId, $data, $callbackQuery['id']);
}

/**
 * Process Telegram command
 * Reuses WhatsApp command logic with Telegram-specific adaptations
 */
function processTelegramCommand($chatId, $message, $username = '', $firstName = '', $lastName = '') {
    // Log to database
    try {
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->prepare("INSERT INTO telegram_webhook_log (chat_id, username, first_name, last_name, message, command, status) VALUES (?, ?, ?, ?, ?, ?, 'success')");
            $command = explode(' ', $message)[0];
            $stmt->execute([$chatId, $username, $firstName, $lastName, $message, $command]);
        }
    } catch (Exception $e) {
        error_log("Error logging Telegram webhook: " . $e->getMessage());
    }
    
    // Handle Telegram-specific commands
    if (strpos($message, '/start') === 0) {
        try {
            // Check if user is admin
            if (isTelegramAdmin($chatId)) {
                showTelegramAdminMenu($chatId);
            } else {
                sendTelegramWelcome($chatId, $firstName);
            }
        } catch (Exception $e) {
            error_log("Error in /start command: " . $e->getMessage());
            sendTelegramWelcome($chatId, $firstName);
        }
        return;
    }
    
    if (strpos($message, '/help') === 0) {
        sendTelegramHelp($chatId);
        return;
    }
    
    // Handle /menu command for agents
    if (strpos($message, '/menu') === 0) {
        try {
            showTelegramAgentMenu($chatId);
        } catch (Exception $e) {
            error_log("Error showing agent menu: " . $e->getMessage());
            sendTelegramMessage($chatId, "❌ Terjadi kesalahan. Silakan gunakan perintah text: HARGA");
        }
        return;
    }
    
    // Handle other commands
    $messageLower = strtolower($message);
    
    // Remove leading slash from Telegram commands
    if (strpos($messageLower, '/') === 0) {
        $messageLower = substr($messageLower, 1);
    }
    
    // Check if admin
    $isAdmin = isTelegramAdmin($chatId);
    
    // Handle price list
    if (in_array($messageLower, ['harga', 'paket', 'list', 'price'])) {
        sendTelegramPriceList($chatId);
        return;
    }
    
    // Handle voucher command (admin only for now)
    if (strpos($messageLower, 'voucher ') === 0 || strpos($messageLower, 'vcr ') === 0) {
        // Extract profile name
        $parts = explode(' ', $messageLower, 2);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($profileName)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\nGunakan: *VOUCHER <NAMA_PAKET>*\nContoh: *VOUCHER 3K*");
            return;
        }
        
        // Generate voucher
        purchaseTelegramVoucher($chatId, $profileName, $isAdmin);
        return;
    }
    
    // Handle buy command
    if (strpos($messageLower, 'beli ') === 0 || strpos($messageLower, 'buy ') === 0) {
        // Extract profile name
        $parts = explode(' ', $messageLower, 2);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($profileName)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\nGunakan: *BELI <NAMA_PAKET>*\nContoh: *BELI 3K*");
            return;
        }
        
        // Generate voucher (same as VOUCHER command)
        purchaseTelegramVoucher($chatId, $profileName, $isAdmin);
        return;
    }
    
    // Admin-only commands
    if ($isAdmin) {
        // PING - Test MikroTik connection
        if (in_array($messageLower, ['ping', 'cek ping'])) {
            checkTelegramMikroTikPing($chatId);
            return;
        }
        
        // STATUS - Check MikroTik status
        if (in_array($messageLower, ['status', 'cek', 'cek status'])) {
            checkTelegramMikroTikStatus($chatId);
            return;
        }
        
        // RESOURCE - Check MikroTik resources
        if (in_array($messageLower, ['resource', 'res', 'resource mikrotik'])) {
            checkTelegramMikroTikResource($chatId);
            return;
        }
        
        // PPPOE - Check active PPPoE
        if (in_array($messageLower, ['pppoe', 'ppp', 'pppoe aktif', 'ppp aktif'])) {
            checkTelegramPPPoEActive($chatId);
            return;
        }
        
        // PPPOE OFFLINE - Check offline PPPoE
        if (in_array($messageLower, ['pppoe offline', 'ppp offline', 'pppoe mati', 'ppp mati'])) {
            checkTelegramPPPoEOffline($chatId);
            return;
        }
        
        // TAMBAH - Add PPPoE secret
        if (strpos($messageLower, 'tambah ') === 0) {
            $rest = trim(substr($message, 7)); // Remove "TAMBAH "
            $parts = preg_split('/\s+/', $rest, 3);
            
            if (count($parts) >= 3) {
                $username = $parts[0];
                $password = $parts[1];
                $profile = $parts[2];
                addTelegramPPPoESecret($chatId, $username, $password, $profile);
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat: *TAMBAH <username> <password> <profile>*\nContoh: *TAMBAH user123 pass123 profile1*");
            }
            return;
        }
        
        // EDIT - Edit PPPoE profile
        if (strpos($messageLower, 'edit ') === 0) {
            $rest = trim(substr($message, 5)); // Remove "EDIT "
            $parts = preg_split('/\s+/', $rest, 2);
            
            if (count($parts) >= 2) {
                $username = $parts[0];
                $newProfile = $parts[1];
                editTelegramPPPoESecret($chatId, $username, $newProfile);
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat: *EDIT <username> <profile_baru>*\nContoh: *EDIT user123 profile2*");
            }
            return;
        }
        
        // HAPUS - Delete PPPoE secret
        if (strpos($messageLower, 'hapus ') === 0) {
            $username = trim(substr($message, 6)); // Remove "HAPUS "
            
            if (!empty($username)) {
                deleteTelegramPPPoESecret($chatId, $username);
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat: *HAPUS <username>*\nContoh: *HAPUS user123*");
            }
            return;
        }
        
        // ENABLE/DISABLE commands
        if (strpos($messageLower, 'enable ') === 0 || strpos($messageLower, 'disable ') === 0) {
            $isEnable = strpos($messageLower, 'enable ') === 0;
            $rest = trim(substr($message, $isEnable ? 7 : 8)); // Remove "ENABLE " or "DISABLE "
            $parts = preg_split('/\s+/', $rest, 2);
            
            if (count($parts) >= 2) {
                $type = strtolower($parts[0]); // pppoe or hotspot
                $username = $parts[1];
                
                if ($type == 'pppoe' || $type == 'ppp') {
                    if ($isEnable) {
                        enableTelegramPPPoESecret($chatId, $username);
                    } else {
                        disableTelegramPPPoESecret($chatId, $username);
                    }
                } elseif ($type == 'hotspot' || $type == 'hs') {
                    if ($isEnable) {
                        enableTelegramHotspotUser($chatId, $username);
                    } else {
                        disableTelegramHotspotUser($chatId, $username);
                    }
                } else {
                    sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat:\n*ENABLE PPPOE <username>*\n*DISABLE PPPOE <username>*\n*ENABLE HOTSPOT <username>*\n*DISABLE HOTSPOT <username>*");
                }
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat:\n*ENABLE PPPOE <username>*\n*DISABLE PPPOE <username>*\n*ENABLE HOTSPOT <username>*\n*DISABLE HOTSPOT <username>*");
            }
            return;
        }
        
        // SALDO DIGIFLAZZ
        if (in_array($messageLower, ['saldo digiflazz', 'cek saldo digiflazz', 'balance digiflazz'])) {
            checkTelegramDigiflazzBalance($chatId);
            return;
        }
        
        // PENCARIAN DEVICE
        if (strpos($messageLower, 'cariperangkat ') === 0 || strpos($messageLower, 'cari perangkat ') === 0) {
            $query = trim(str_replace(['cariperangkat', 'cari perangkat'], '', $messageLower));
            findTelegramDevice($chatId, $query);
            return;
        }

        // REGISTER AGENT/CUSTOMER
        if (strpos($messageLower, 'reg ') === 0 || strpos($messageLower, 'register ') === 0) {
            $phone = trim(str_replace(['reg ', 'register '], '', $messageLower));
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            if (empty($phone)) {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nGunakan: `REG <NOMOR_HP>`\nContoh: `REG 08123456789`");
                return;
            }
            
            // Basic link logic (for prototype/ MVP parity)
            // Ideally we'd send OTP, but for now we'll match by phone directly if it exists in DB.
            $db = getDBConnection();
            if ($db) {
                // Check Agent
                $stmt = $db->prepare("SELECT id FROM agents WHERE phone LIKE ? OR phone LIKE ? LIMIT 1");
                $stmt->execute(['%'.$phone, '%'.$phone]); // Simple loose match
                $agent = $stmt->fetch();
                
                if ($agent) {
                    $upd = $db->prepare("UPDATE agents SET telegram_chat_id = ? WHERE id = ?");
                    $upd->execute([$chatId, $agent['id']]);
                    sendTelegramMessage($chatId, "✅ *REGISTRASI BERHASIL*\n\nAkun Telegram terhubung sebagai AGEN.");
                    return;
                }
                
                // Check Customer
                $stmt = $db->prepare("SELECT id FROM billing_customers WHERE phone LIKE ? OR phone LIKE ? LIMIT 1");
                $stmt->execute(['%'.$phone, '%'.$phone]);
                $cust = $stmt->fetch();
                
                if ($cust) {
                    $upd = $db->prepare("UPDATE billing_customers SET telegram_chat_id = ? WHERE id = ?");
                    $upd->execute([$chatId, $cust['id']]);
                    sendTelegramMessage($chatId, "✅ *REGISTRASI BERHASIL*\n\nAkun Telegram terhubung sebagai PELANGGAN.");
                    return;
                }
                
                sendTelegramMessage($chatId, "❌ *NOMOR TIDAK DITEMUKAN*\n\nNomor HP tidak terdaftar di sistem.");
            }
            return;
        }
    }
    
    // User commands - PULSA
    if (strpos($messageLower, 'pulsa ') === 0) {
        $parts = explode(' ', $message);
        $sku = $parts[1] ?? '';
        $number = $parts[2] ?? '';
        
        if (empty($sku) || empty($number)) {
             sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nGunakan: `PULSA <SKU> <NOMOR>`");
             return;
        }
        
        purchaseTelegramDigiflazz($chatId, $sku, $number);
        return;
    }
    
    // User commands - GANTI WIFI/SANDI
    if (strpos($messageLower, 'gantiwifi ') === 0) {
        $ssid = trim(substr($message, 10)); // Remove "GANTIWIFI "
        changeTelegramWiFiSSID($chatId, $ssid);
        return;
    }
    
    if (strpos($messageLower, 'gantisandi ') === 0) {
        $pass = trim(substr($message, 11)); // Remove "GANTISANDI "
        changeTelegramWiFiPassword($chatId, $pass);
        return;
    }
    
    // Default response for unknown commands
    sendTelegramMessage($chatId, "❓ Perintah tidak dikenali.\n\nKetik /help untuk melihat daftar perintah yang tersedia.");
}

/**
 * Process command using WhatsApp logic but send via Telegram
 * DEPRECATED - Not used anymore, kept for future integration
 */
function processTelegramCommandWithWhatsAppLogic($chatId, $message) {
    // This function is deprecated
    // Will be implemented later when full integration is needed
    sendTelegramMessage($chatId, "⚠️ Fitur ini sedang dalam pengembangan.");
}

/**
 * Send welcome message
 */
function sendTelegramWelcome($chatId, $firstName = '') {
    $name = !empty($firstName) ? $firstName : 'User';
    
    try {
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->query("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_welcome_message'");
            $result = $stmt->fetch();
            if ($result && !empty($result['setting_value'])) {
                $message = str_replace('{name}', $name, $result['setting_value']);
                sendTelegramMessage($chatId, $message);
                return;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting welcome message: " . $e->getMessage());
    }
    
    // Default welcome message
    $message = "🤖 *Selamat datang di Bot MikhMon, $name!*\n\n";
    $message .= "Saya adalah bot untuk pembelian voucher WiFi dan layanan digital.\n\n";
    $message .= "Ketik /help untuk melihat perintah yang tersedia.";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Send help message
 */
function sendTelegramHelp($chatId) {
    // Check if admin
    $isAdmin = isTelegramAdmin($chatId);
    
    if ($isAdmin) {
        $message = "👑 *BANTUAN ADMIN BOT*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "*🎫 VOUCHER & PAKET*\n\n";
        $message .= "📋 *HARGA* - Lihat daftar paket\n";
        $message .= "🎫 *VOUCHER <PAKET>* - Generate voucher\n";
        $message .= "🛒 *BELI <PAKET>* - Generate voucher\n";
        $message .= "Contoh: `VOUCHER 3K`, `BELI 1JAM`\n\n";
        
        $message .= "*� MONITORING*\n\n";
        $message .= "🔌 *PING* - Test koneksi MikroTik\n";
        $message .= "📊 *STATUS* - Cek status MikroTik\n";
        $message .= "� *RESOURCE* - Cek resource server\n";
        $message .= "🌐 *PPPOE* - Cek PPPoE aktif\n";
        $message .= "📴 *PPPOE OFFLINE* - Cek PPPoE offline\n\n";
        
        $message .= "*⚙️ MANAGEMENT*\n\n";
        $message .= "➕ *TAMBAH <user> <pass> <profile>*\n";
        $message .= "✏️ *EDIT <user> <profile>*\n";
        $message .= "🗑️ *HAPUS <user>*\n";
        $message .= "✅ *ENABLE PPPOE <user>*\n";
        $message .= "❌ *DISABLE PPPOE <user>*\n";
        $message .= "✅ *ENABLE HOTSPOT <user>*\n";
        $message .= "❌ *DISABLE HOTSPOT <user>*\n\n";
        
        $message .= "*💰 DIGIFLAZZ*\n\n";
        $message .= "📱 *PULSA <SKU> <NOMER>*\n";
        $message .= "💵 *SALDO DIGIFLAZZ* - Cek saldo\n";
        $message .= "Contoh: `PULSA as10 081234567890`\n\n";
        
        $message .= "*🔐 WIFI*\n\n";
        $message .= "📡 *GANTIWIFI <SSID>*\n";
        $message .= "🔑 *GANTISANDI <PASSWORD>*\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🔑 *ADMIN ACCESS ACTIVE*\n";
        $message .= "❓ */help* - Tampilkan bantuan ini";
    } else {
        $message = "🤖 *BANTUAN BOT VOUCHER*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "*Perintah yang tersedia:*\n\n";
        $message .= "📋 *HARGA* atau *PAKET*\n";
        $message .= "Melihat daftar paket dan harga\n\n";
        $message .= "🛒 *BELI <NAMA_PAKET>*\n";
        $message .= "Membeli voucher\n";
        $message .= "Contoh: `BELI 1JAM`, `BELI 3K`\n\n";
        $message .= "� *PULSA <SKU> <NOMER>*\n";
        $message .= "Beli pulsa/data/e-money\n";
        $message .= "Contoh: `PULSA as10 081234567890`\n\n";
        $message .= "🔐 *GANTIWIFI <SSID>*\n";
        $message .= "Ganti nama WiFi\n";
        $message .= "Contoh: `GANTIWIFI MyWiFi`\n\n";
        $message .= "🔑 *GANTISANDI <PASSWORD>*\n";
        $message .= "Ganti password WiFi\n";
        $message .= "Contoh: `GANTISANDI password123`\n\n";
        $message .= "❓ */help*\n";
        $message .= "Menampilkan bantuan ini\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "_Hubungi admin jika ada kendala_";
    }
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Check if Telegram chat ID is admin
 */
function isTelegramAdmin($chatId) {
    try {
        $db = getDBConnection();
        if (!$db) {
            return false;
        }
        
        $stmt = $db->query("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_admin_chat_ids'");
        $result = $stmt->fetch();
        
        if ($result) {
            $adminChatIds = explode(',', $result['setting_value']);
            $adminChatIds = array_map('trim', $adminChatIds);
            return in_array($chatId, $adminChatIds);
        }
    } catch (Exception $e) {
        error_log("Error checking Telegram admin: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Handle callback query from inline keyboards
 */
function handleCallbackQuery($chatId, $data, $callbackQueryId) {
    // INSTANT answer callback query to remove loading state
    $url = TELEGRAM_API_URL . '/answerCallbackQuery';
    $postData = [
        'callback_query_id' => $callbackQueryId,
        'text' => '⏳ Memproses...',
        'show_alert' => false
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Quick timeout
    curl_exec($ch);
    curl_close($ch);
    
    // Process callback data
    // Format: action:param1:param2
    $parts = explode(':', $data);
    $action = $parts[0];
    
    switch ($action) {
        case 'buy':
            if (isset($parts[1])) {
                $profile = $parts[1];
                processTelegramCommandWithWhatsAppLogic($chatId, "beli $profile");
            }
            break;
        case 'price':
            processTelegramCommandWithWhatsAppLogic($chatId, "harga");
            break;
        case 'help':
            sendTelegramHelp($chatId);
            break;
        case 'agent_buy':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    // Use existing voucher purchase function
                    purchaseTelegramVoucher($chatId, $profileName, false); // false = not admin
                } catch (Exception $e) {
                    error_log("Error in agent_buy callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat generate voucher.\n\nSilakan coba lagi atau gunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
        
        // Agent menu callbacks
        case 'agent_quick':
            try {
                showTelegramAgentQuickMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in agent_quick callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: HARGA");
            }
            break;
            
        case 'agent_packages':
            try {
                showTelegramAgentPackagesMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in agent_packages callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: HARGA");
            }
            break;
            
        case 'agent_balance':
            try {
                $agent = getTelegramAgentByPhone($chatId);
                if ($agent) {
                    $message = "💰 *INFO SALDO AGENT*\n\n";
                    $message .= "Saldo Saat Ini: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
                    $message .= "Status: " . ($agent['status'] == 'active' ? '✅ Aktif' : '❌ Nonaktif') . "\n\n";
                    $message .= "Untuk top up saldo, hubungi administrator.";
                    sendTelegramMessage($chatId, $message);
                } else {
                    sendTelegramMessage($chatId, "❌ Data agent tidak ditemukan.");
                }
            } catch (Exception $e) {
                error_log("Error in agent_balance callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
            }
            break;
            
        case 'agent_menu':
            try {
                showTelegramAgentMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in agent_menu callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: /menu");
            }
            break;
        
        // Admin menu callbacks
        case 'admin_main':
            try {
                showTelegramAdminMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_main callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: /start");
            }
            break;
            
        case 'admin_voucher':
            try {
                showTelegramAdminVoucherMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_voucher callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: HARGA");
            }
            break;
            
        case 'admin_pppoe':
            try {
                showTelegramAdminPPPoEMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_pppoe callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PPP");
            }
            break;
            
        case 'admin_settings':
            try {
                showTelegramAdminSettingsMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_settings callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PING");
            }
            break;
            
        case 'admin_buy':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    // Use existing voucher purchase function for admin (no balance deduction)
                    purchaseTelegramVoucher($chatId, $profileName, true); // true = admin
                } catch (Exception $e) {
                    error_log("Error in admin_buy callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat generate voucher.\n\nSilakan coba lagi atau gunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
            
        case 'admin_all_packages':
            try {
                // Redirect to existing HARGA command (which loads MikroTik data)
                processTelegramCommand($chatId, 'HARGA');
            } catch (Exception $e) {
                error_log("Error in admin_all_packages callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: HARGA");
            }
            break;
            
        case 'admin_help':
            try {
                sendTelegramHelp($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_help callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: /help");
            }
            break;
            
        // PPPoE callbacks - redirect to existing commands
        case 'admin_ppp_active':
            try {
                processTelegramCommand($chatId, 'PPP');
            } catch (Exception $e) {
                error_log("Error in admin_ppp_active callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PPP");
            }
            break;
            
        case 'admin_ppp_offline':
            try {
                processTelegramCommand($chatId, 'PPP OFFLINE');
            } catch (Exception $e) {
                error_log("Error in admin_ppp_offline callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PPP OFFLINE");
            }
            break;
            
        case 'admin_ppp_edit':
            try {
                sendTelegramMessage($chatId, "✏️ *PPP EDIT*\n\nGunakan perintah:\n`EDIT [nama_pppoe] [profile_baru]`\n\nContoh:\n`EDIT user123 3JAM`");
            } catch (Exception $e) {
                error_log("Error in admin_ppp_edit callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: EDIT [nama] [profile]");
            }
            break;
            
        case 'admin_ppp_tools':
            try {
                sendTelegramMessage($chatId, "🔧 *PPP TOOLS*\n\nPerintah yang tersedia:\n• `ENABLE [nama]` - Aktifkan PPPoE\n• `DISABLE [nama]` - Nonaktifkan PPPoE\n• `REMOVE [nama]` - Hapus PPPoE");
            } catch (Exception $e) {
                error_log("Error in admin_ppp_tools callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
            }
            break;
            
        // Settings callbacks - redirect to existing commands
        case 'admin_ping':
            try {
                processTelegramCommand($chatId, 'PING');
            } catch (Exception $e) {
                error_log("Error in admin_ping callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PING");
            }
            break;
            
        case 'admin_resource':
            try {
                processTelegramCommand($chatId, 'RESOURCE');
            } catch (Exception $e) {
                error_log("Error in admin_resource callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: RESOURCE");
            }
            break;
            
        case 'admin_reboot':
            try {
                sendTelegramMessage($chatId, "🔄 *REBOOT SYSTEM*\n\n⚠️ **PERINGATAN**\nReboot akan memutus semua koneksi!\n\nGunakan perintah:\n`REBOOT CONFIRM`\n\nUntuk membatalkan, abaikan pesan ini.");
            } catch (Exception $e) {
                error_log("Error in admin_reboot callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
            }
            break;
            
        case 'admin_info':
            try {
                processTelegramCommand($chatId, 'INFO');
            } catch (Exception $e) {
                error_log("Error in admin_info callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: INFO");
            }
            break;
    }
}

/**
 * Log Telegram webhook
 */
function logTelegramWebhook($data) {
    $logFile = __DIR__ . '/../logs/telegram_webhook.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | " . $data . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Send price list from MikroTik
 */
function sendTelegramPriceList($chatId) {
    // Load session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ Sistem sedang maintenance.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session || !isset($sessionConfig[$session])) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak ditemukan.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    $hotspotname = explode('%', $data[4])[1] ?? $session;
    $currency = explode('&', $data[6])[1] ?? 'Rp';
    
    if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak lengkap.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ Gagal terhubung ke server.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get all profiles
    $profiles = $API->comm("/ip/hotspot/user/profile/print");
    $API->disconnect();
    
    $message = "*📋 DAFTAR PAKET WIFI*\n";
    $message .= "*$hotspotname*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $hasPackages = false;
    foreach ($profiles as $profile) {
        $name = $profile['name'];
        if ($name == 'default' || $name == 'default-encryption') continue;
        
        $ponlogin = $profile['on-login'] ?? '';
        if (empty($ponlogin)) continue;
        
        $parts = explode(",", $ponlogin);
        $validity = $parts[3] ?? '';
        $price = $parts[2] ?? '';
        $sprice = $parts[4] ?? '0';
        
        if (empty($sprice) || $sprice == '0') continue;
        
        if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
        } else {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
        }
        
        $message .= "*$name*\n";
        $message .= "Validity: $validity\n";
        $message .= "Harga: $priceFormatted\n\n";
        $hasPackages = true;
    }
    
    if (!$hasPackages) {
        $message .= "Belum ada paket tersedia.\n\n";
    }
    
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "Cara order:\n";
    $message .= "Ketik: *BELI <NAMA_PAKET>*\n";
    $message .= "Contoh: *BELI 1JAM*";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Generate Telegram voucher credentials
 */
function generateTelegramVoucherCredentials() {
    // Load VoucherGenerator if available
    if (file_exists(__DIR__ . '/../lib/VoucherGenerator.class.php')) {
        include_once(__DIR__ . '/../lib/VoucherGenerator.class.php');
        $voucherGen = new VoucherGenerator();
        $voucher = $voucherGen->generateVoucher();
        return [
            'username' => $voucher['username'],
            'password' => $voucher['password']
        ];
    } else {
        // Fallback generation
        $username = 'tg' . strtolower(substr(md5(time() . rand()), 0, 8));
        $password = strtolower(substr(md5(time() . rand() . 'pass'), 0, 8));
        return [
            'username' => $username,
            'password' => $password
        ];
    }
}

/**
 * Get agent by phone number (for Telegram)
 */
function getTelegramAgentByPhone($phone) {
    if (!function_exists('getDBConnection')) {
        return null;
    }
    
    try {
        $db = getDBConnection();
        
        // Try exact match first
        $stmt = $db->prepare("SELECT * FROM agents WHERE telegram_chat_id = :chat_id LIMIT 1");
        $stmt->execute([':chat_id' => $phone]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agent) {
            return $agent;
        }
        
        // Try with phone number variants
        $stmt = $db->prepare("SELECT * FROM agents WHERE phone = :phone LIMIT 1");
        $stmt->execute([':phone' => $phone]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agent) {
            return $agent;
        }
        
        // Try with different phone formats (remove leading 62, 0, +62)
        $phoneVariants = [];
        $phoneVariants[] = $phone;
        
        if (strpos($phone, '62') === 0) {
            $phoneVariants[] = '0' . substr($phone, 2);
        }
        if (strpos($phone, '0') === 0) {
            $phoneVariants[] = '62' . substr($phone, 1);
        }
        
        foreach ($phoneVariants as $variant) {
            $stmt = $db->prepare("SELECT * FROM agents WHERE phone = :phone LIMIT 1");
            $stmt->execute([':phone' => $variant]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($agent) {
                return $agent;
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting Telegram agent by phone: " . $e->getMessage());
        return null;
    }
}

/**
 * Purchase/Generate voucher for Telegram
 */
function purchaseTelegramVoucher($chatId, $profileName, $isAdmin) {
    // Load session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ Sistem sedang maintenance.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session || !isset($sessionConfig[$session])) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak ditemukan.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    $hotspotname = explode('%', $data[4])[1] ?? $session;
    $dnsname = explode('^', $data[5])[1] ?? $iphost;
    $currency = explode('&', $data[6])[1] ?? 'Rp';
    
    if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak lengkap.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ Gagal terhubung ke server.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get profile
    $profiles = $API->comm("/ip/hotspot/user/profile/print", array(
        "?name" => $profileName
    ));
    
    if (empty($profiles)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ Profile *$profileName* tidak ditemukan.\n\nKetik *HARGA* untuk melihat daftar paket.");
        return;
    }
    
    $profile = $profiles[0];
    $ponlogin = $profile['on-login'] ?? '';
    
    if (empty($ponlogin)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ Profile *$profileName* tidak memiliki konfigurasi harga.");
        return;
    }
    
    $parts = explode(",", $ponlogin);
    $validity = $parts[3] ?? '';
    $price = $parts[2] ?? '0';
    $sprice = $parts[4] ?? '0';
    
    // Set buy price for balance check
    $buyPrice = (float)$sprice;
    
    // Initialize agent variables
    $agent = null;
    $agentId = null;
    $balanceBefore = 0;
    $balanceAfter = 0;
    
    // Check agent/admin authorization
    if (!$isAdmin) {
        // Get agent info
        $agent = getTelegramAgentByPhone($chatId);
        
        if ($agent) {
            $agentId = $agent['id'];
            
            // Validate price
            if ($buyPrice <= 0) {
                $API->disconnect();
                sendTelegramMessage($chatId, "❌ *HARGA TIDAK VALID*\n\nHarga paket *$profileName* belum dikonfigurasi.\nHubungi administrator.");
                return;
            }
            
            // Check balance
            if ($agent['balance'] < $buyPrice) {
                $reply = "❌ *SALDO TIDAK CUKUP*\n\n";
                $reply .= "Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
                $reply .= "Dibutuhkan: Rp " . number_format($buyPrice, 0, ',', '.') . "\n";
                $reply .= "Kurang: Rp " . number_format($buyPrice - $agent['balance'], 0, ',', '.') . "\n\n";
                $reply .= "Silakan topup saldo terlebih dahulu.";
                $API->disconnect();
                sendTelegramMessage($chatId, $reply);
                return;
            }
        } else {
            // Not an agent and not an admin - REJECT
            $API->disconnect();
            $errorMsg = "❌ *AKSES DITOLAK*\n\n";
            $errorMsg .= "Chat ID Anda tidak terdaftar sebagai agent.\n\n";
            $errorMsg .= "Untuk menjadi agent, silakan hubungi administrator.";
            sendTelegramMessage($chatId, $errorMsg);
            return;
        }
    }
    
    // Generate username and password based on settings
    $credentials = generateTelegramVoucherCredentials();
    $username = $credentials['username'];
    $password = $credentials['password'];
    
    // Add user to MikroTik
    $addResult = $API->comm("/ip/hotspot/user/add", array(
        "name" => $username,
        "password" => $password,
        "profile" => $profileName,
        "comment" => "vc-Telegram-" . date('Y-m-d H:i:s')
    ));
    
    $API->disconnect();
    
    if (empty($addResult) || isset($addResult['!trap'])) {
        $error = isset($addResult['!trap'][0]['message']) ? $addResult['!trap'][0]['message'] : 'Unknown error';
        sendTelegramMessage($chatId, "❌ Gagal generate voucher.\n\nError: $error");
        return;
    }
    
    // Deduct balance for agent (only for non-admin)
    if (!$isAdmin && $agent && $agentId) {
        // Load Agent class if not already loaded
        if (!class_exists('Agent')) {
            require_once(__DIR__ . '/../lib/Agent.class.php');
        }
        
        $agentClass = new Agent();
        $deductResult = $agentClass->deductBalance(
            $agentId,
            $buyPrice,
            $profileName,
            $username,
            'Voucher Telegram: ' . $profileName,
            'voucher_telegram'
        );
        
        if ($deductResult['success']) {
            $balanceBefore = $deductResult['balance_before'];
            $balanceAfter = $deductResult['balance_after'];
        } else {
            // Log error but don't fail the transaction (voucher already created)
            error_log("Failed to deduct balance for Telegram agent $agentId: " . $deductResult['message']);
        }
    }
    
    // Format price
    if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
        $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
    } else {
        $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
    }
    
    // Send success message
    $message = "✅ *VOUCHER BERHASIL DI-GENERATE*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "*Profile:* $profileName\n";
    $message .= "*Validity:* $validity\n";
    $message .= "*Harga:* $priceFormatted\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "*Username:* `$username`\n";
    $message .= "*Password:* `$password`\n\n";
    
    // Show balance for agent (not for admin)
    if (!$isAdmin && $balanceAfter > 0) {
        $message .= "💳 Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n\n";
    }
    
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "Login: http://$dnsname/login\n";
    $message .= "Hotspot: *$hotspotname*";
    
    sendTelegramMessage($chatId, $message);
    
    // Log to database if needed
    try {
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->prepare("INSERT INTO telegram_webhook_log (chat_id, username, message, command, status, response) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$chatId, '', "VOUCHER $profileName", 'voucher', 'success', "Generated: $username"]);
        }
    } catch (Exception $e) {
        // Ignore logging errors
    }
}

/**
 * Show admin main menu
 */
function showTelegramAdminMenu($chatId) {
    try {
        $message = "⚙️ *ADMIN CONTROL PANEL*\n\nPilih menu:";
        
        $keyboard = [
            [
                ['text' => '🎫 Voucher', 'callback_data' => 'admin_voucher'],
                ['text' => '🌐 PPPoE', 'callback_data' => 'admin_pppoe']
            ],
            [
                ['text' => '⚙️ Settings', 'callback_data' => 'admin_settings'],
                ['text' => '❓ Help', 'callback_data' => 'admin_help']
            ]
        ];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• HARGA - Lihat paket\n";
        $message .= "• PPP - Status PPPoE\n";
        $message .= "• PING - Test koneksi\n";
        $message .= "• RESOURCE - Info server";
        
        // Send with keyboard, fallback to text if failed
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            $result = sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
            if (!$result['success']) {
                sendTelegramMessage($chatId, $message);
            }
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAdminMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nSilakan gunakan perintah text: HARGA, PPP, PING");
    }
}

/**
 * Show admin voucher submenu (using cache system)
 */
function showTelegramAdminVoucherMenu($chatId) {
    try {
        // Try to get cached packages first
        $packages = getTelegramCachedPackages();
        
        if (empty($packages)) {
            // Fallback to loading message
            sendTelegramMessage($chatId, "⏳ Mengambil data paket...");
            $packages = getTelegramCachedPackages(true); // Force refresh
        }
        
        if (empty($packages)) {
            sendTelegramMessage($chatId, "❌ Tidak ada paket tersedia.\n\nGunakan perintah: HARGA");
            return;
        }
        
        $message = "🎫 *ADMIN VOUCHER MENU*\n\nPilih paket untuk generate voucher:";
        
        // Take first 5 packages (sorted by price)
        $keyboard = [];
        $count = 0;
        foreach ($packages as $package) {
            if ($count >= 5) break; // Limit to 5 packages for admin
            
            $keyboard[] = [['text' => $package['display'], 'callback_data' => 'admin_buy:' . $package['name']]];
            $count++;
        }
        
        // Add navigation buttons
        $keyboard[] = [['text' => '📋 Lihat Semua Paket', 'callback_data' => 'admin_all_packages']];
        $keyboard[] = [['text' => '🔙 Back to Main', 'callback_data' => 'admin_main']];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• VOUCHER [nama_paket]\n";
        $message .= "• HARGA - Lihat semua paket";
        
        // Send response
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAdminVoucherMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nSilakan gunakan perintah: HARGA");
    }
}

/**
 * Show admin PPPoE submenu
 */
function showTelegramAdminPPPoEMenu($chatId) {
    try {
        $message = "🌐 *PPPoE MANAGEMENT*\n\nPilih menu:";
        
        $keyboard = [
            [
                ['text' => '📊 PPP Aktif', 'callback_data' => 'admin_ppp_active'],
                ['text' => '💤 PPP Offline', 'callback_data' => 'admin_ppp_offline']
            ],
            [
                ['text' => '✏️ PPP Edit', 'callback_data' => 'admin_ppp_edit'],
                ['text' => '🔧 PPP Tools', 'callback_data' => 'admin_ppp_tools']
            ],
            [
                ['text' => '🔙 Back to Main', 'callback_data' => 'admin_main']
            ]
        ];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• PPP - Status aktif\n";
        $message .= "• PPP OFFLINE - Status offline\n";
        $message .= "• EDIT [nama] [profile] - Edit PPPoE";
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            $result = sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
            if (!$result['success']) {
                sendTelegramMessage($chatId, $message);
            }
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAdminPPPoEMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nSilakan gunakan perintah: PPP");
    }
}

/**
 * Show admin settings submenu
 */
function showTelegramAdminSettingsMenu($chatId) {
    try {
        $message = "⚙️ *SYSTEM SETTINGS*\n\nPilih menu:";
        
        $keyboard = [
            [
                ['text' => '🏓 Ping Test', 'callback_data' => 'admin_ping'],
                ['text' => '📊 Resource', 'callback_data' => 'admin_resource']
            ],
            [
                ['text' => '🔄 Reboot', 'callback_data' => 'admin_reboot'],
                ['text' => '📋 Info', 'callback_data' => 'admin_info']
            ],
            [
                ['text' => '🔙 Back to Main', 'callback_data' => 'admin_main']
            ]
        ];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• PING - Test koneksi\n";
        $message .= "• RESOURCE - Info server\n";
        $message .= "• INFO - System info";
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            $result = sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
            if (!$result['success']) {
                sendTelegramMessage($chatId, $message);
            }
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAdminSettingsMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nSilakan gunakan perintah: PING, RESOURCE");
    }
}

/**
 * Get cached packages or load from MikroTik
 */
function getTelegramCachedPackages($forceRefresh = false) {
    $cacheFile = __DIR__ . '/../cache/telegram_packages.json';
    $cacheDir = dirname($cacheFile);
    
    // Create cache directory if not exists
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    // Check cache validity (5 minutes)
    $cacheValid = false;
    if (!$forceRefresh && file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        $cacheValid = (time() - $cacheTime) < 300; // 5 minutes
    }
    
    if ($cacheValid) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['packages'])) {
            return $cached['packages'];
        }
    }
    
    // Load fresh data from MikroTik
    try {
        global $data;
        if (!isset($data) || empty($data)) {
            require_once(__DIR__ . '/../include/config.php');
        }
        $sessionConfig = isset($data) ? $data : array();
        
        if (empty($sessionConfig)) {
            return [];
        }
        
        // Get first session
        $sessions = array_keys($sessionConfig);
        $session = null;
        foreach ($sessions as $s) {
            if ($s != 'mikhmon') {
                $session = $s;
                break;
            }
        }
        
        if (!$session || !isset($sessionConfig[$session])) {
            return [];
        }
        
        // Load session config
        $data = $sessionConfig[$session];
        $iphost = explode('!', $data[1])[1] ?? '';
        $userhost = explode('@|@', $data[2])[1] ?? '';
        $passwdhost = explode('#|#', $data[3])[1] ?? '';
        $currency = explode('&', $data[6])[1] ?? 'Rp';
        
        if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
            return [];
        }
        
        // Connect to MikroTik
        require_once(__DIR__ . '/../lib/routeros_api.class.php');
        
        $API = new RouterosAPI();
        $API->debug = false;
        
        if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $API->disconnect();
            return [];
        }
        
        // Get profiles
        $profiles = $API->comm("/ip/hotspot/user/profile/print");
        $API->disconnect();
        
        if (empty($profiles)) {
            return [];
        }
        
        // Process profiles
        $packages = [];
        foreach ($profiles as $profile) {
            $profileName = $profile['name'] ?? '';
            $ponlogin = $profile['on-login'] ?? '';
            
            if (empty($ponlogin) || $profileName === 'default') {
                continue;
            }
            
            $parts = explode(",", $ponlogin);
            $validity = $parts[3] ?? '';
            $sprice = $parts[4] ?? '0';
            
            if (empty($validity) || $sprice <= 0) {
                continue;
            }
            
            // Format price
            if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
                $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
            } else {
                $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
            }
            
            $packages[] = [
                'name' => $profileName,
                'validity' => $validity,
                'price' => (float)$sprice,
                'price_formatted' => $priceFormatted,
                'display' => $profileName . " - " . $priceFormatted
            ];
        }
        
        // Sort by price (cheapest first)
        usort($packages, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        // Cache the result
        $cacheData = [
            'timestamp' => time(),
            'packages' => $packages
        ];
        file_put_contents($cacheFile, json_encode($cacheData));
        
        return $packages;
        
    } catch (Exception $e) {
        error_log("Error loading packages cache: " . $e->getMessage());
        return [];
    }
}

/**
 * Show agent quick buy menu (popular packages only)
 */
function showTelegramAgentQuickMenu($chatId) {
    try {
        // Try to get cached packages first
        $packages = getTelegramCachedPackages();
        
        if (empty($packages)) {
            // Fallback to loading message
            sendTelegramMessage($chatId, "⏳ Mengambil data paket...");
            $packages = getTelegramCachedPackages(true); // Force refresh
        }
        
        if (empty($packages)) {
            sendTelegramMessage($chatId, "❌ Tidak ada paket tersedia.\n\nGunakan perintah: HARGA");
            return;
        }
        
        $message = "⚡ *QUICK BUY*\n\nPaket populer (langsung beli):";
        
        // Take first 4 packages (sorted by price)
        $keyboard = [];
        $count = 0;
        foreach ($packages as $package) {
            if ($count >= 4) break; // Limit to 4 popular packages
            
            $keyboard[] = [['text' => $package['display'], 'callback_data' => 'agent_buy:' . $package['name']]];
            $count++;
        }
        
        // Add navigation buttons
        $keyboard[] = [['text' => '📋 Lihat Semua Paket', 'callback_data' => 'agent_packages']];
        $keyboard[] = [['text' => '🔙 Kembali', 'callback_data' => 'agent_menu']];
        
        // Send response
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message . "\n\nGunakan: VOUCHER [nama_paket]");
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAgentQuickMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan: VOUCHER [nama_paket]");
    }
}

/**
 * Show all agent packages (using cache system)
 */
function showTelegramAgentPackagesMenu($chatId) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // Get packages from cache (force refresh for complete list)
        $packages = getTelegramCachedPackages(true);
        
        if (empty($packages)) {
            sendTelegramMessage($chatId, "❌ Tidak ada paket tersedia.\n\nGunakan perintah: HARGA");
            return;
        }
        
        // Build message and keyboard
        $message = "📋 *SEMUA PAKET TERSEDIA*\n\n";
        $message .= "💰 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        
        $keyboard = [];
        
        foreach ($packages as $package) {
            $keyboard[] = [['text' => $package['display'], 'callback_data' => 'agent_buy:' . $package['name']]];
        }
        
        // Add navigation buttons
        $keyboard[] = [['text' => '🔙 Kembali', 'callback_data' => 'agent_menu']];
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message . "\n\nGunakan: VOUCHER [nama_paket]");
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAgentPackagesMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: HARGA");
    }
}

/**
 * Show agent menu with package selection
 */
function showTelegramAgentMenu($chatId) {
    try {
        // Quick check if user is agent (no heavy operations)
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.\nSilakan hubungi administrator.");
            return;
        }
        
        // INSTANT RESPONSE - Lightweight menu without MikroTik data
        $message = "🎫 *AGENT MENU*\n\n";
        $message .= "💰 Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $message .= "Pilih aksi:";
        
        $keyboard = [
            [['text' => '⚡ Quick Buy', 'callback_data' => 'agent_quick']],
            [['text' => '📋 Lihat Semua Paket', 'callback_data' => 'agent_packages']],
            [['text' => 'ℹ️ Info Saldo', 'callback_data' => 'agent_balance']]
        ];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• VOUCHER [nama_paket] - Beli langsung\n";
        $message .= "• HARGA - Lihat semua paket";
        
        // INSTANT send - no heavy operations
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            $result = sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
            if (!$result['success']) {
                sendTelegramMessage($chatId, $message);
            }
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAgentMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nSilakan gunakan perintah: HARGA");
    }
}

// Return 200 OK to Telegram
http_response_code(200);
echo json_encode(['ok' => true]);
exit;
