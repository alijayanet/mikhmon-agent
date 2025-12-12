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
    
    // Handle /generate command for interactive voucher generation
    if (strpos($message, '/generate') === 0) {
        try {
            showTelegramGenerateVoucherMenu($chatId);
        } catch (Exception $e) {
            error_log("Error showing generate menu: " . $e->getMessage());
            sendTelegramMessage($chatId, "❌ Terjadi kesalahan. Silakan gunakan perintah: VOUCHER [nama_paket]");
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
    
    // Handle voucher command (optimized)
    if (strpos($messageLower, 'voucher ') === 0) {
        // Extract profile name
        $parts = explode(' ', $messageLower, 2);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($profileName)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\nGunakan: *VOUCHER <NAMA_PAKET>*\nContoh: *VOUCHER 3K*");
            return;
        }
        
        // Use optimized generation with instant feedback
        generateSingleVoucherOptimized($chatId, $profileName, $isAdmin);
        return;
    }
    
    // Handle VCR command with advanced parsing - VCR [USERNAME] <PROFILE> [NOMER]
    if (strpos($messageLower, 'vcr ') === 0) {
        $rest = trim(str_replace('vcr ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest);
        
        $username = null;
        $profile = null;
        $customerPhone = null;
        
        if (count($parts) == 1) {
            // Format: VCR 3K
            $profile = $parts[0];
        } elseif (count($parts) == 2) {
            // Format: VCR 3K 08123456789 atau VCR user123 3K
            // Check if second part is a phone number (starts with 0 or 62)
            if (preg_match('/^[062]/', $parts[1])) {
                // Format: VCR 3K 08123456789
                $profile = $parts[0];
                $customerPhone = $parts[1];
            } else {
                // Format: VCR user123 3K
                $username = $parts[0];
                $profile = $parts[1];
            }
        } elseif (count($parts) == 3) {
            // Format: VCR user123 3K 08123456789
            $username = $parts[0];
            $profile = $parts[1];
            $customerPhone = $parts[2];
        }
        
        if (empty($profile)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\n*Format VCR:*\n• `VCR <PROFILE>` - Generate voucher otomatis\n• `VCR <USERNAME> <PROFILE>` - Username manual\n• `VCR <PROFILE> <NOMER>` - Kirim ke nomor customer\n• `VCR <USERNAME> <PROFILE> <NOMER>` - Lengkap\n\n*Contoh:*\n• `VCR 3K`\n• `VCR user123 3K`\n• `VCR 3K 081234567890`\n• `VCR user123 3K 081234567890`");
            return;
        }
        
        // Generate voucher with advanced parameters
        purchaseTelegramVoucherAdvanced($chatId, $profile, $isAdmin, $username, $customerPhone);
        return;
    }
    
    // Handle buy command (optimized)
    if (strpos($messageLower, 'beli ') === 0 || strpos($messageLower, 'buy ') === 0) {
        // Extract profile name
        $parts = explode(' ', $messageLower, 2);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($profileName)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\nGunakan: *BELI <NAMA_PAKET>*\nContoh: *BELI 3K*");
            return;
        }
        
        // Use optimized generation with instant feedback
        generateSingleVoucherOptimized($chatId, $profileName, $isAdmin);
        return;
    }
    
    // Handle BULK command for bulk voucher generation
    if (strpos($messageLower, 'bulk ') === 0) {
        $parts = preg_split('/\s+/', $messageLower, 3);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        $quantity = isset($parts[2]) ? intval($parts[2]) : 0;
        
        if (empty($profileName) || $quantity < 2) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\n*Format BULK:*\n• `BULK <PROFILE> <JUMLAH>`\n\n*Contoh:*\n• `BULK 3K 5`\n• `BULK 1JAM 10`\n\n*Minimum:* 2 voucher");
            return;
        }
        
        if ($quantity > 50) {
            sendTelegramMessage($chatId, "❌ *JUMLAH TERLALU BESAR*\n\nMaksimal 50 voucher per transaksi.\nUntuk kebutuhan lebih besar, hubungi administrator.");
            return;
        }
        
        // Generate bulk vouchers
        generateTelegramBulkVouchers($chatId, $profileName, $quantity);
        return;
    }
    
    // Handle BAYAR command for paying customer bills
    if (strpos($messageLower, 'bayar ') === 0) {
        $parts = preg_split('/\s+/', $messageLower, 3);
        $customerIdentifier = isset($parts[1]) ? trim($parts[1]) : '';
        $period = isset($parts[2]) ? trim($parts[2]) : date('Y-m'); // Default current month
        
        if (empty($customerIdentifier)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\n*Format BAYAR:*\n• `BAYAR <NAMA/HP> [PERIODE]`\n\n*Contoh:*\n• `BAYAR 081234567890` - Bayar bulan ini\n• `BAYAR John Doe 2025-12` - Bayar Des 2025\n• `BAYAR 081234567890 2025-11` - Bayar Nov 2025");
            return;
        }
        
        // Process payment
        processTelegramBillPayment($chatId, $customerIdentifier, $period, $isAdmin);
        return;
    }
    
    // Handle TAGIHAN command to check customer bills
    if (strpos($messageLower, 'tagihan ') === 0) {
        $parts = preg_split('/\s+/', $messageLower, 2);
        $customerIdentifier = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($customerIdentifier)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\n*Format TAGIHAN:*\n• `TAGIHAN <NAMA/HP>`\n\n*Contoh:*\n• `TAGIHAN 081234567890`\n• `TAGIHAN John Doe`");
            return;
        }
        
        // Check customer bills
        checkTelegramCustomerBills($chatId, $customerIdentifier);
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

    }
    
    // REG command - Register agent/customer with Telegram
    if (strpos($messageLower, 'reg ') === 0) {
        $phoneNumber = trim(str_replace('reg ', '', $messageLower));
        if (!empty($phoneNumber)) {
            processTelegramAgentRegistration($chatId, $phoneNumber, $username, $firstName, $lastName);
        } else {
            sendTelegramMessage($chatId, "❌ Format salah!\n\n*Format REG:*\n• REG <NOMOR_HP>\n\n*Contoh:*\n• REG 081234567890\n\n*Fungsi:* Menghubungkan akun Telegram Anda dengan nomor HP agent yang sudah terdaftar.");
        }
        return;
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
        
        $message .= "*💳 BILLING*\n\n";
        $message .= "💳 *TAGIHAN <NAMA/HP>* - Cek tagihan\n";
        $message .= "💰 *BAYAR <NAMA/HP>* - Bayar tagihan\n";
        $message .= "📝 *REG <NOMOR_HP>* - Registrasi agent\n";
        $message .= "Contoh: `TAGIHAN John Doe`\n\n";
        
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
        $message .= "💳 *TAGIHAN <NAMA/HP>*\n";
        $message .= "Cek tagihan pelanggan billing\n";
        $message .= "Contoh: `TAGIHAN 081234567890`\n\n";
        $message .= "💰 *BAYAR <NAMA/HP>*\n";
        $message .= "Bayar tagihan pelanggan (Agent)\n";
        $message .= "Contoh: `BAYAR 081234567890`\n\n";
        $message .= "📝 *REG <NOMOR_HP>*\n";
        $message .= "Registrasi nomor agent/pelanggan\n";
        $message .= "Contoh: `REG 081234567890`\n\n";
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
    // ULTRA INSTANT answer callback query (non-blocking)
    fastTelegramCallback($callbackQueryId);
    
    // INSTANT response for common actions
    $parts = explode(':', $data);
    $action = $parts[0];
    
    // Pre-send instant feedback for slow operations
    $instantActions = ['generate_select', 'generate_bulk', 'bulk_generate', 'agent_packages', 'agent_generate', 'agent_quick', 'agent_billing'];
    if (in_array($action, $instantActions)) {
        sendInstantProcessingMessage($chatId, $action, $parts);
    }
    
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
                    // INSTANT response
                    sendTelegramMessage($chatId, "🎫 *GENERATE VOUCHER*\n\n🔄 Sedang membuat voucher untuk paket: $profileName");
                    // Use optimized function for instant feedback
                    generateSingleVoucherOptimized($chatId, $profileName, false);
                } catch (Exception $e) {
                    error_log("Error in agent_buy callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat generate voucher.\n\nSilakan coba lagi atau gunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
        
        // Agent menu callbacks
        case 'agent_quick':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "⚡ *QUICK BUY*\n\n🔄 Memuat paket populer...");
                // Process in background
                showTelegramAgentQuickMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in agent_quick callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: HARGA");
            }
            break;
            
        case 'agent_packages':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "📋 *DAFTAR HARGA AGENT*\n\n🔄 Memuat daftar harga...");
                // Process in background
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
            
        case 'agent_generate':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "🎫 *GENERATE VOUCHER MENU*\n\n🔄 Memuat menu generate voucher...");
                // Process in background
                showTelegramGenerateVoucherMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in agent_generate callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: /generate");
            }
            break;
            
        case 'generate_select':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    // INSTANT response
                    sendTelegramMessage($chatId, "⚙️ *OPSI VOUCHER: $profileName*\n\n🔄 Memuat opsi untuk paket $profileName...");
                    // Process in background
                    showTelegramVoucherOptionsMenu($chatId, $profileName);
                } catch (Exception $e) {
                    error_log("Error in generate_select callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
            
        case 'generate_simple':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    // Use optimized function for instant feedback
                    generateSingleVoucherOptimized($chatId, $profileName, false);
                } catch (Exception $e) {
                    error_log("Error in generate_simple callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
            
        case 'generate_bulk':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    // INSTANT response
                    sendTelegramMessage($chatId, "📦 *BULK VOUCHER: $profileName*\n\n🔄 Memuat opsi bulk untuk paket $profileName...");
                    // Process in background
                    showTelegramBulkVoucherMenu($chatId, $profileName);
                } catch (Exception $e) {
                    error_log("Error in generate_bulk callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
            
        case 'generate_custom':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    // INSTANT response
                    sendTelegramMessage($chatId, "🛠️ *CUSTOM VOUCHER: $profileName*\n\n🔄 Memuat opsi custom untuk paket $profileName...");
                    // Process in background
                    showTelegramCustomVoucherMenu($chatId, $profileName);
                } catch (Exception $e) {
                    error_log("Error in generate_custom callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VCR [username] " . $profileName);
                }
            }
            break;
            
        case 'bulk_generate':
            if (isset($parts[1]) && isset($parts[2])) {
                try {
                    $profileName = $parts[1];
                    $quantity = intval($parts[2]);
                    // INSTANT response
                    sendTelegramMessage($chatId, "🚀 *GENERATE BULK VOUCHER*\n\n🔄 Sedang membuat $quantity voucher untuk paket: $profileName");
                    // Process in background
                    generateTelegramBulkVouchers($chatId, $profileName, $quantity);
                } catch (Exception $e) {
                    error_log("Error in bulk_generate callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: BULK " . $profileName . " " . $parts[2]);
                }
            }
            break;
            
        case 'bulk_custom':
            if (isset($parts[1])) {
                try {
                    $profileName = $parts[1];
                    $message = "📝 *CUSTOM BULK QUANTITY*\n\n";
                    $message .= "Ketik perintah:\n";
                    $message .= "`BULK $profileName [jumlah]`\n\n";
                    $message .= "Contoh: `BULK $profileName 15`";
                    sendTelegramMessage($chatId, $message);
                } catch (Exception $e) {
                    error_log("Error in bulk_custom callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
                }
            }
            break;
            
        case 'agent_billing':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "💳 *BAYAR TAGIHAN PELANGGAN*\n\n🔄 Memuat menu billing...");
                // Process in background
                showTelegramAgentBillingMenu($chatId);
            } catch (Exception $e) {
                error_log("Error in agent_billing callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: TAGIHAN [nama/hp]");
            }
            break;
            
        case 'billing_search':
            try {
                $message = "🔍 *CARI PELANGGAN*\n\n";
                $message .= "Ketik perintah:\n";
                $message .= "`TAGIHAN [nama/hp]`\n\n";
                $message .= "Contoh:\n";
                $message .= "• `TAGIHAN 081234567890`\n";
                $message .= "• `TAGIHAN John Doe`";
                sendTelegramMessage($chatId, $message);
            } catch (Exception $e) {
                error_log("Error in billing_search callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
            }
            break;
            
        case 'billing_pay':
            if (isset($parts[1]) && isset($parts[2])) {
                try {
                    $customerId = intval($parts[1]);
                    $period = $parts[2];
                    // INSTANT response
                    sendTelegramMessage($chatId, "💰 *PROSES PEMBAYARAN*\n\n🔄 Memproses pembayaran tagihan untuk customer ID: $customerId, periode: $period");
                    // Process in background
                    processTelegramBillPaymentById($chatId, $customerId, $period, isTelegramAdmin($chatId));
                } catch (Exception $e) {
                    error_log("Error in billing_pay callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat memproses pembayaran.");
                }
            }
            break;
            
        case 'refresh_packages':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "🔄 *MEMPERBARUI CACHE*\n\nSedang memuat ulang daftar paket...");
                
                // Force refresh packages cache in background
                refreshPackagesAsync($chatId, getTelegramAgentByPhone($chatId));
            } catch (Exception $e) {
                error_log("Error in refresh_packages callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Gagal memperbarui cache.\n\nGunakan perintah: HARGA");
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
                    // INSTANT response
                    sendTelegramMessage($chatId, "🎫 *GENERATE VOUCHER*\n\n🔄 Sedang membuat voucher untuk paket: $profileName");
                    // Use optimized function for admin
                    generateSingleVoucherOptimized($chatId, $profileName, true);
                } catch (Exception $e) {
                    error_log("Error in admin_buy callback: " . $e->getMessage());
                    sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat generate voucher.\n\nSilakan coba lagi atau gunakan perintah: VOUCHER " . $profileName);
                }
            }
            break;
            
        case 'admin_all_packages':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "📋 *SEMUA PAKET*\n\n🔄 Memuat daftar semua paket...");
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
                // INSTANT response
                sendTelegramMessage($chatId, "📡 *PPPoE AKTIF*\n\n🔄 Mengambil data koneksi aktif...");
                // Process in background
                checkTelegramPPPoEActive($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_ppp_active callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PPP");
            }
            break;
            
        case 'admin_ppp_offline':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "📴 *PPPoE OFFLINE*\n\n🔄 Mengambil data user offline...");
                // Process in background
                checkTelegramPPPoEOffline($chatId);
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
                // INSTANT response
                sendTelegramMessage($chatId, "🔌 *PING TEST*\n\n🔄 Testing koneksi ke MikroTik...");
                // Process in background
                checkTelegramMikroTikPing($chatId);
            } catch (Exception $e) {
                error_log("Error in admin_ping callback: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: PING");
            }
            break;
            
        case 'admin_resource':
            try {
                // INSTANT response
                sendTelegramMessage($chatId, "💻 *RESOURCE USAGE*\n\n🔄 Mengambil informasi resource MikroTik...");
                // Process in background
                checkTelegramMikroTikResource($chatId);
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
                // INSTANT response
                sendTelegramMessage($chatId, "ℹ️ *SYSTEM INFO*\n\n🔄 Mengambil informasi sistem...");
                // Process in background
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
    // Check if user is agent first
    $agent = getTelegramAgentByPhone($chatId);
    
    if ($agent) {
        // Send agent-specific price list
        sendTelegramAgentPriceList($chatId, $agent);
    } else {
        // Send general price list
        sendTelegramGeneralPriceList($chatId);
    }
}

/**
 * Send agent-specific price list for Telegram
 */
function sendTelegramAgentPriceList($chatId, $agent) {
    try {
        // Load database connection
        if (!function_exists('getDBConnection')) {
            if (file_exists(__DIR__ . '/../include/db_config.php')) {
                require_once(__DIR__ . '/../include/db_config.php');
            } else {
                sendTelegramMessage($chatId, "❌ Database tidak tersedia.");
                return;
            }
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendTelegramMessage($chatId, "❌ Koneksi database gagal.");
            return;
        }
        
        // Load Agent class
        if (!class_exists('Agent')) {
            require_once(__DIR__ . '/../lib/Agent.class.php');
        }
        
        $agentClass = new Agent();
        $agentPrices = $agentClass->getAllAgentPrices($agent['id']);
        
        if (empty($agentPrices)) {
            sendTelegramMessage($chatId, "❌ *HARGA BELUM DISET*\n\nBelum ada harga yang diset untuk agent Anda.\n\nSilakan hubungi admin untuk setting harga.");
            return;
        }
        
        $message = "*💰 DAFTAR HARGA AGENT*\n";
        $message .= "*Agent: {$agent['agent_name']}*\n";
        $message .= "*Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($agentPrices as $price) {
            $profit = $price['sell_price'] - $price['buy_price'];
            $profitPercent = $price['buy_price'] > 0 ? round(($profit / $price['buy_price']) * 100, 1) : 0;
            
            $message .= "*{$price['profile_name']}*\n";
            $message .= "Harga Beli: Rp " . number_format($price['buy_price'], 0, ',', '.') . "\n";
            $message .= "Harga Jual: Rp " . number_format($price['sell_price'], 0, ',', '.') . "\n";
            $message .= "Profit: Rp " . number_format($profit, 0, ',', '.') . " ({$profitPercent}%)\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "*Cara order:*\n";
        $message .= "• VOUCHER <NAMA_PAKET>\n";
        $message .= "• BELI <NAMA_PAKET>\n";
        $message .= "• VCR <NAMA_PAKET>\n\n";
        $message .= "*Contoh:* VOUCHER 1JAM";
        
        sendTelegramMessage($chatId, $message);
        
    } catch (Exception $e) {
        error_log("Error in sendTelegramAgentPriceList: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat mengambil daftar harga.\n\nSilakan coba lagi.");
    }
}

/**
 * Send general price list for Telegram (for non-agents)
 */
function sendTelegramGeneralPriceList($chatId) {
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
 * Get agent price for specific profile
 */
function getTelegramAgentPrice($agentId, $profileName) {
    try {
        if (!function_exists('getDBConnection')) {
            return null;
        }
        
        $db = getDBConnection();
        if (!$db) {
            return null;
        }
        
        $stmt = $db->prepare("SELECT * FROM agent_prices WHERE agent_id = :agent_id AND profile_name = :profile_name");
        $stmt->execute([':agent_id' => $agentId, ':profile_name' => $profileName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getTelegramAgentPrice: " . $e->getMessage());
        return null;
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
    // Load VoucherGenerator if available
    if (file_exists(__DIR__ . '/../lib/VoucherGenerator.class.php')) {
        require_once(__DIR__ . '/../lib/VoucherGenerator.class.php');
        $voucherGen = new VoucherGenerator();
        $voucher = $voucherGen->generateVoucher();
        $username = $voucher['username'];
        $password = $voucher['password'];
    } else {
        // Fallback generation
        $username = 'tg' . strtolower(substr(md5(time() . rand()), 0, 8));
        $password = strtolower(substr(md5(time() . rand() . 'pass'), 0, 8));
    }
    
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
    
    // Send success message with instant format
    $message = "✅ *VOUCHER BERHASIL DIBUAT*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "🏠 Hotspot: *$hotspotname*\n";
    $message .= "📦 Profile: *$profileName*\n\n";
    $message .= "👤 Username: `$username`\n";
    $message .= "🔑 Password: `$password`\n\n";
    
    // Add session timeout if available
    if (!empty($profile['session-timeout'])) {
        $message .= "Time Limit: " . $profile['session-timeout'] . "\n";
    }
    if (!empty($validity)) {
        $message .= "Validity: $validity\n";
    }
    if (!empty($priceFormatted)) {
        $message .= "Harga: $priceFormatted\n";
    }
    
    // Show balance for agent (not for admin)
    if (!$isAdmin && $balanceAfter > 0) {
        $message .= "\n💳 Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n";
    }
    
    $message .= "\nLogin URL:\n";
    $message .= "http://$dnsname/login?username=$username&password=$password\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "_Terima kasih telah menggunakan layanan kami_";
    
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
        // INSTANT response - send quick loading message first
        sendTelegramMessage($chatId, "🎫 *ADMIN VOUCHER MENU*\n\n🔄 Memuat daftar paket...");
        
        // Try to get cached packages first
        $packages = getTelegramCachedPackages();
        
        if (empty($packages)) {
            // Background refresh
            $packages = getTelegramCachedPackages(true); // Force refresh
        }
        
        if (empty($packages)) {
            sendTelegramMessage($chatId, "❌ Tidak ada paket tersedia.\n\nGunakan perintah: HARGA");
            return;
        }
        
        $message = "🎫 *ADMIN VOUCHER MENU*\n\nPilih paket untuk generate voucher:";
        
        // Take first 5 packages (sorted by price) and show prices
        $keyboard = [];
        $count = 0;
        foreach ($packages as $package) {
            if ($count >= 5) break; // Limit to 5 packages for admin
            
            // Show package with price information
            $displayName = $package['display'];
            if (isset($package['price'])) {
                $displayName .= " - Rp " . number_format($package['price'], 0, ',', '.');
            }
            
            $keyboard[] = [['text' => $displayName, 'callback_data' => 'admin_buy:' . $package['name']]];
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
    
    // Check cache validity (10 minutes for better performance)
    $cacheValid = false;
    if (!$forceRefresh && file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        $cacheValid = (time() - $cacheTime) < 600; // 10 minutes for better responsiveness
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
        
        // Connect to MikroTik with optimized timeout
        require_once(__DIR__ . '/../lib/routeros_api.class.php');
        
        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 2; // Even quicker timeout for better responsiveness
        
        if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $API->disconnect();
            // Return cached data even if expired if connection fails
            if (file_exists($cacheFile)) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if ($cached && isset($cached['packages'])) {
                    return $cached['packages'];
                }
            }
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
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // INSTANT response - send quick loading message
        $loadingMessage = "⚡ *QUICK BUY*\n\n";
        $loadingMessage .= "💳 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $loadingMessage .= "🔄 Memuat paket populer...";
        
        // Send instant loading message
        sendTelegramMessage($chatId, $loadingMessage);
        
        // Load Agent class to get agent prices
        if (!class_exists('Agent')) {
            require_once(__DIR__ . '/../lib/Agent.class.php');
        }
        
        $agentClass = new Agent();
        $agentPrices = $agentClass->getAllAgentPrices($agent['id']);
        
        if (empty($agentPrices)) {
            sendTelegramMessage($chatId, "❌ *HARGA BELUM DISET*\n\nBelum ada harga yang diset untuk agent Anda.\n\nSilakan hubungi admin untuk setting harga.\n\nAtau gunakan perintah: HARGA");
            return;
        }
        
        $message = "⚡ *QUICK BUY*\n\n";
        $message .= "💳 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $message .= "Paket populer (langsung beli):";
        
        // Take first 4 agent prices (popular packages)
        $keyboard = [];
        $count = 0;
        foreach ($agentPrices as $price) {
            if ($count >= 4) break; // Limit to 4 popular packages
            
            $profit = $price['sell_price'] - $price['buy_price'];
            $displayText = $price['profile_name'] . " - Rp " . number_format($price['buy_price'], 0, ',', '.');
            $displayText .= " (+" . number_format($profit, 0, ',', '.') . ")";
            
            $keyboard[] = [['text' => $displayText, 'callback_data' => 'agent_buy:' . $price['profile_name']]];
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
        
        // INSTANT response - send quick loading message
        $loadingMessage = "💰 *DAFTAR HARGA AGENT*\n\n";
        $loadingMessage .= "💳 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $loadingMessage .= "🔄 Memuat daftar harga...";
        
        // Send instant loading message
        sendTelegramMessage($chatId, $loadingMessage);
        
        // Load Agent class to get agent prices
        if (!class_exists('Agent')) {
            require_once(__DIR__ . '/../lib/Agent.class.php');
        }
        
        $agentClass = new Agent();
        $agentPrices = $agentClass->getAllAgentPrices($agent['id']);
        
        if (empty($agentPrices)) {
            sendTelegramMessage($chatId, "❌ *HARGA BELUM DISET*\n\nBelum ada harga yang diset untuk agent Anda.\n\nSilakan hubungi admin untuk setting harga.\n\nAtau gunakan perintah: HARGA");
            return;
        }
        
        // Build message and keyboard with agent prices
        $message = "💰 *DAFTAR HARGA AGENT*\n\n";
        $message .= "💳 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        
        $keyboard = [];
        
        foreach ($agentPrices as $price) {
            $profit = $price['sell_price'] - $price['buy_price'];
            $profitPercent = $price['buy_price'] > 0 ? round(($profit / $price['buy_price']) * 100, 1) : 0;
            
            $displayText = $price['profile_name'] . " - Rp " . number_format($price['buy_price'], 0, ',', '.');
            $displayText .= " (+" . number_format($profit, 0, ',', '.') . ")";
            
            $keyboard[] = [['text' => $displayText, 'callback_data' => 'agent_buy:' . $price['profile_name']]];
            
            $message .= "*{$price['profile_name']}*\n";
            $message .= "Harga Beli: Rp " . number_format($price['buy_price'], 0, ',', '.') . "\n";
            $message .= "Harga Jual: Rp " . number_format($price['sell_price'], 0, ',', '.') . "\n";
            $message .= "Profit: Rp " . number_format($profit, 0, ',', '.') . " ({$profitPercent}%)\n\n";
        }
        
        // Add navigation buttons
        $keyboard[] = [['text' => '🔄 Refresh', 'callback_data' => 'agent_packages']];
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
            [['text' => '🎫 Generate Voucher', 'callback_data' => 'agent_generate']],
            [['text' => '💳 Bayar Tagihan', 'callback_data' => 'agent_billing']],
            [['text' => '📋 Lihat Semua Paket', 'callback_data' => 'agent_packages']],
            [['text' => 'ℹ️ Info Saldo', 'callback_data' => 'agent_balance']]
        ];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• VOUCHER [nama_paket] - Beli langsung\n";
        $message .= "• /generate - Menu generate interaktif\n";
        $message .= "• VCR [username] [paket] [nomer] - Custom\n";
        $message .= "• BAYAR [nama/hp] - Bayar tagihan\n";
        $message .= "• TAGIHAN [nama/hp] - Cek tagihan\n";
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

/**
 * Purchase/Generate voucher for Telegram with advanced parameters
 * @param string $chatId Telegram chat ID
 * @param string $profileName Profile name
 * @param bool $isAdmin Is admin user
 * @param string|null $customUsername Custom username (optional)
 * @param string|null $customerPhone Customer phone number (optional)
 */
function purchaseTelegramVoucherAdvanced($chatId, $profileName, $isAdmin, $customUsername = null, $customerPhone = null) {
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
    
    // Generate or use custom username and password
    if (!empty($customUsername)) {
        // Check if username already exists
        $existingUser = $API->comm("/ip/hotspot/user/print", array("?name" => $customUsername));
        if (!empty($existingUser)) {
            $API->disconnect();
            sendTelegramMessage($chatId, "❌ *USERNAME SUDAH TERDAFTAR*\n\nUsername *$customUsername* sudah digunakan.\nSilakan gunakan username lain.");
            return;
        }
        $username = $customUsername;
        $password = $customUsername; // Username = Password for voucher mode
    } else {
        // Generate username and password based on settings
        // Load VoucherGenerator if available
        if (file_exists(__DIR__ . '/../lib/VoucherGenerator.class.php')) {
            require_once(__DIR__ . '/../lib/VoucherGenerator.class.php');
            $voucherGen = new VoucherGenerator();
            $voucher = $voucherGen->generateVoucher();
            $username = $voucher['username'];
            $password = $voucher['password'];
        } else {
            // Fallback generation
            $username = 'tg' . strtolower(substr(md5(time() . rand()), 0, 8));
            $password = strtolower(substr(md5(time() . rand() . 'pass'), 0, 8));
        }
    }
    
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
    
    // Send success message to agent/admin
    $message = "🎫 *VOUCHER ANDA*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "Hotspot: *$hotspotname*\n";
    $message .= "Profile: *$profileName*\n\n";
    $message .= "Username: `$username`\n";
    $message .= "Password: `$password`\n\n";
    
    // Add session timeout if available
    if (!empty($profile['session-timeout'])) {
        $message .= "Time Limit: " . $profile['session-timeout'] . "\n";
    }
    if (!empty($validity)) {
        $message .= "Validity: $validity\n";
    }
    if (!empty($priceFormatted)) {
        $message .= "Harga: $priceFormatted\n";
    }
    
    // Show balance for agent (not for admin)
    if (!$isAdmin && $balanceAfter > 0) {
        $message .= "\n💳 Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n";
    }
    
    $message .= "\nLogin URL:\n";
    $message .= "http://$dnsname/login?username=$username&password=$password\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "_Terima kasih telah menggunakan layanan kami_";
    
    // Send to agent/admin
    sendTelegramMessage($chatId, $message);
    
    // Send to customer if phone number provided
    if (!empty($customerPhone)) {
        // Normalize customer phone number
        $customerPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (!empty($customerPhone)) {
            // Create customer voucher message
            $customerMessage = "🎫 *VOUCHER WIFI ANDA*\n\n";
            $customerMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
            $customerMessage .= "*Hotspot:* $hotspotname\n";
            $customerMessage .= "*Profile:* $profileName\n";
            $customerMessage .= "*Validity:* $validity\n";
            $customerMessage .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $customerMessage .= "*Username:* `$username`\n";
            $customerMessage .= "*Password:* `$password`\n\n";
            $customerMessage .= "Login URL:\nhttp://$dnsname/login?username=$username&password=$password\n\n";
            $customerMessage .= "_Terima kasih telah menggunakan layanan kami_";
            
            // Send to customer via Telegram (if they have Telegram) or log for WhatsApp sending
            // For now, we'll just log it - you can integrate with WhatsApp gateway here
            error_log("Voucher for customer $customerPhone: $customerMessage");
            
            // Notify agent that voucher was sent to customer
            sendTelegramMessage($chatId, "📤 *Voucher telah dikirim ke customer:* $customerPhone");
        }
    }
}

/**
 * Show interactive generate voucher menu
 */
function showTelegramGenerateVoucherMenu($chatId) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // INSTANT response - send quick loading message first
        sendInstantGenerateMenu($chatId, $agent);
        
        // ULTRA FAST: Try memory cache first, then file cache
        $packages = getUltraFastPackageCache();
        
        if (empty($packages)) {
            // Background refresh (async) - don't wait for it
            // Start async refresh but don't block the response
            refreshPackagesAsync($chatId, $agent);
            return;
        }
        
        // Use separate function for cleaner code
        showTelegramGenerateVoucherMenuWithPackages($chatId, $agent, $packages);
        
    } catch (Exception $e) {
        error_log("Error in showTelegramGenerateVoucherMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VOUCHER [nama_paket]");
    }
}

/**
 * Show voucher options menu for selected package
 */
function showTelegramVoucherOptionsMenu($chatId, $profileName) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // Get package info from cache
        $packages = getTelegramCachedPackages();
        $selectedPackage = null;
        
        foreach ($packages as $package) {
            if ($package['name'] === $profileName) {
                $selectedPackage = $package;
                break;
            }
        }
        
        if (!$selectedPackage) {
            sendTelegramMessage($chatId, "❌ Paket *$profileName* tidak ditemukan.\n\nGunakan perintah: HARGA");
            return;
        }
        
        $message = "🎫 *VOUCHER OPTIONS*\n\n";
        $message .= "📦 Paket: *{$selectedPackage['name']}*\n";
        $message .= "💰 Harga: {$selectedPackage['price_formatted']}\n";
        $message .= "⏰ Validity: {$selectedPackage['validity']}\n\n";
        $message .= "💳 Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $message .= "Pilih jenis generate:";
        
        $keyboard = [
            [['text' => '🎫 Generate 1 Voucher', 'callback_data' => 'generate_simple:' . $profileName]],
            [['text' => '📦 Generate Bulk (2-10)', 'callback_data' => 'generate_bulk:' . $profileName]],
            [['text' => '🔧 Custom Username', 'callback_data' => 'generate_custom:' . $profileName]],
            [['text' => '🔙 Kembali', 'callback_data' => 'agent_generate']]
        ];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• VOUCHER $profileName - Generate 1\n";
        $message .= "• VCR [username] $profileName - Custom\n";
        $message .= "• VCR $profileName [nomer] - Kirim ke customer";
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramVoucherOptionsMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VOUCHER $profileName");
    }
}

/**
 * Show bulk voucher generation menu
 */
function showTelegramBulkVoucherMenu($chatId, $profileName) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // Get package info
        $packages = getTelegramCachedPackages();
        $selectedPackage = null;
        
        foreach ($packages as $package) {
            if ($package['name'] === $profileName) {
                $selectedPackage = $package;
                break;
            }
        }
        
        if (!$selectedPackage) {
            sendTelegramMessage($chatId, "❌ Paket *$profileName* tidak ditemukan.");
            return;
        }
        
        $price = $selectedPackage['price'];
        $balance = $agent['balance'];
        $maxQty = floor($balance / $price);
        
        if ($maxQty < 2) {
            $message = "❌ *SALDO TIDAK CUKUP UNTUK BULK*\n\n";
            $message .= "💰 Saldo Anda: Rp " . number_format($balance, 0, ',', '.') . "\n";
            $message .= "💳 Harga per voucher: {$selectedPackage['price_formatted']}\n";
            $message .= "📊 Maksimal voucher: $maxQty\n\n";
            $message .= "Silakan topup saldo atau pilih generate 1 voucher.";
            sendTelegramMessage($chatId, $message);
            return;
        }
        
        $message = "📦 *BULK GENERATE VOUCHER*\n\n";
        $message .= "📦 Paket: *{$selectedPackage['name']}*\n";
        $message .= "💰 Harga: {$selectedPackage['price_formatted']}\n";
        $message .= "💳 Saldo: Rp " . number_format($balance, 0, ',', '.') . "\n";
        $message .= "📊 Maksimal: $maxQty voucher\n\n";
        $message .= "Pilih jumlah voucher:";
        
        $keyboard = [];
        
        // Generate quantity options (2, 3, 5, 10 or max available)
        $quantities = [2, 3, 5, 10];
        foreach ($quantities as $qty) {
            if ($qty <= $maxQty) {
                $totalCost = $qty * $price;
                $costFormatted = "Rp " . number_format($totalCost, 0, ',', '.');
                $keyboard[] = [['text' => "$qty Voucher ($costFormatted)", 'callback_data' => "bulk_generate:$profileName:$qty"]];
            }
        }
        
        // Add custom quantity option
        if ($maxQty > 10) {
            $keyboard[] = [['text' => "✏️ Custom Quantity (Max: $maxQty)", 'callback_data' => "bulk_custom:$profileName"]];
        }
        
        $keyboard[] = [['text' => '🔙 Kembali', 'callback_data' => 'generate_select:' . $profileName]];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• Ketik: BULK $profileName [jumlah]\n";
        $message .= "• Contoh: BULK $profileName 5";
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramBulkVoucherMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
    }
}

/**
 * Show custom voucher generation menu
 */
function showTelegramCustomVoucherMenu($chatId, $profileName) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // Get package info
        $packages = getTelegramCachedPackages();
        $selectedPackage = null;
        
        foreach ($packages as $package) {
            if ($package['name'] === $profileName) {
                $selectedPackage = $package;
                break;
            }
        }
        
        if (!$selectedPackage) {
            sendTelegramMessage($chatId, "❌ Paket *$profileName* tidak ditemukan.");
            return;
        }
        
        $message = "🔧 *CUSTOM VOUCHER GENERATION*\n\n";
        $message .= "📦 Paket: *{$selectedPackage['name']}*\n";
        $message .= "💰 Harga: {$selectedPackage['price_formatted']}\n";
        $message .= "⏰ Validity: {$selectedPackage['validity']}\n\n";
        $message .= "💳 Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        
        $message .= "🔧 *CUSTOM OPTIONS:*\n\n";
        $message .= "*Format VCR Command:*\n";
        $message .= "• `VCR [username] $profileName` - Custom username\n";
        $message .= "• `VCR $profileName [nomer]` - Kirim ke customer\n";
        $message .= "• `VCR [username] $profileName [nomer]` - Lengkap\n\n";
        
        $message .= "*Contoh:*\n";
        $message .= "• `VCR user123 $profileName`\n";
        $message .= "• `VCR $profileName 081234567890`\n";
        $message .= "• `VCR user123 $profileName 081234567890`\n\n";
        
        $message .= "Ketik perintah VCR sesuai kebutuhan Anda.";
        
        $keyboard = [
            [['text' => '🎫 Generate Normal', 'callback_data' => 'generate_simple:' . $profileName]],
            [['text' => '🔙 Kembali', 'callback_data' => 'generate_select:' . $profileName]]
        ];
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramCustomVoucherMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.");
    }
}

/**
 * Show generate voucher menu with packages (helper function)
 */
function showTelegramGenerateVoucherMenuWithPackages($chatId, $agent, $packages) {
    try {
        $message = "🎫 *GENERATE VOUCHER MENU*\n\n";
        $message .= "💰 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $message .= "Pilih paket untuk generate voucher:";
        
        $keyboard = [];
        
        // Show up to 6 packages
        $count = 0;
        foreach ($packages as $package) {
            if ($count >= 6) break;
            
            $keyboard[] = [['text' => $package['display'], 'callback_data' => 'generate_select:' . $package['name']]];
            $count++;
        }
        
        // Add navigation buttons
        if (count($packages) > 6) {
            $keyboard[] = [['text' => '📋 Lihat Semua Paket', 'callback_data' => 'agent_packages']];
        }
        $keyboard[] = [['text' => '🔄 Refresh', 'callback_data' => 'refresh_packages']];
        $keyboard[] = [['text' => '🔙 Kembali', 'callback_data' => 'agent_menu']];
        
        // Add text alternative
        $message .= "\n\n📝 *Atau gunakan perintah text:*\n";
        $message .= "• VOUCHER [nama_paket] - Generate 1 voucher\n";
        $message .= "• VCR [username] [paket] - Custom username\n";
        $message .= "• VCR [paket] [nomer] - Kirim ke customer";
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramGenerateVoucherMenuWithPackages: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: VOUCHER [nama_paket]");
    }
}

/**
 * Generate bulk vouchers for Telegram agent
 */
function generateTelegramBulkVouchers($chatId, $profileName, $quantity) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // Validate quantity
        if ($quantity < 2 || $quantity > 50) {
            sendTelegramMessage($chatId, "❌ *JUMLAH TIDAK VALID*\n\nMinimal: 2 voucher\nMaksimal: 50 voucher");
            return;
        }
        
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
        $totalCost = $buyPrice * $quantity;
        
        // Validate price
        if ($buyPrice <= 0) {
            $API->disconnect();
            sendTelegramMessage($chatId, "❌ *HARGA TIDAK VALID*\n\nHarga paket *$profileName* belum dikonfigurasi.\nHubungi administrator.");
            return;
        }
        
        // Check balance
        if ($agent['balance'] < $totalCost) {
            $reply = "❌ *SALDO TIDAK CUKUP*\n\n";
            $reply .= "Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
            $reply .= "Dibutuhkan: Rp " . number_format($totalCost, 0, ',', '.') . "\n";
            $reply .= "Kurang: Rp " . number_format($totalCost - $agent['balance'], 0, ',', '.') . "\n\n";
            $reply .= "Silakan topup saldo terlebih dahulu.";
            $API->disconnect();
            sendTelegramMessage($chatId, $reply);
            return;
        }
        
        // Send instant processing message with progress
        $processingMsg = "🚀 *BULK VOUCHER DIMULAI*\n\n";
        $processingMsg .= "📦 Paket: *$profileName*\n";
        $processingMsg .= "🔢 Jumlah: *$quantity* voucher\n";
        $processingMsg .= "💰 Total: Rp " . number_format($totalCost, 0, ',', '.') . "\n\n";
        $processingMsg .= "⚡ *Memproses...* (0/$quantity)";
        
        $sentMessage = sendTelegramMessage($chatId, $processingMsg);
        
        // Load VoucherGenerator if available
        if (file_exists(__DIR__ . '/../lib/VoucherGenerator.class.php')) {
            require_once(__DIR__ . '/../lib/VoucherGenerator.class.php');
            $voucherGen = new VoucherGenerator();
        }
        
        // Load Agent class
        if (!class_exists('Agent')) {
            require_once(__DIR__ . '/../lib/Agent.class.php');
        }
        $agentClass = new Agent();
        
        $generatedVouchers = [];
        $successCount = 0;
        $failedCount = 0;
        $totalDeducted = 0;
        
        // Generate vouchers with progress updates
        $lastProgressUpdate = 0;
        for ($i = 0; $i < $quantity; $i++) {
            // Update progress every 5 vouchers or at key points
            if ($i > 0 && ($i % 5 == 0 || $i == $quantity - 1)) {
                $progressMsg = "⚡ *Memproses...* ($i/$quantity)";
                // Quick progress update (non-blocking)
                if (time() - $lastProgressUpdate > 2) { // Max 1 update per 2 seconds
                    sendTelegramMessage($chatId, $progressMsg);
                    $lastProgressUpdate = time();
                }
            }
            try {
                // Generate username and password
                if (isset($voucherGen)) {
                    $voucher = $voucherGen->generateVoucher();
                    $username = $voucher['username'];
                    $password = $voucher['password'];
                } else {
                    // Fallback generation
                    $username = 'tg' . strtolower(substr(md5(time() . rand() . $i), 0, 8));
                    $password = strtolower(substr(md5(time() . rand() . $i . 'pass'), 0, 8));
                }
                
                // Add user to MikroTik
                $addResult = $API->comm("/ip/hotspot/user/add", array(
                    "name" => $username,
                    "password" => $password,
                    "profile" => $profileName,
                    "comment" => "vc-Telegram-Bulk-" . date('Y-m-d H:i:s')
                ));
                
                if (empty($addResult) || isset($addResult['!trap'])) {
                    $failedCount++;
                    continue;
                }
                
                // Deduct balance
                $deductResult = $agentClass->deductBalance(
                    $agent['id'],
                    $buyPrice,
                    $profileName,
                    $username,
                    "Bulk Voucher Telegram: $profileName (#" . ($i + 1) . ")",
                    'voucher_telegram_bulk'
                );
                
                if ($deductResult['success']) {
                    $generatedVouchers[] = [
                        'username' => $username,
                        'password' => $password,
                        'profile' => $profileName
                    ];
                    $successCount++;
                    $totalDeducted += $buyPrice;
                } else {
                    $failedCount++;
                    // Remove user from MikroTik if balance deduction failed
                    $API->comm("/ip/hotspot/user/remove", array(
                        "numbers" => $username
                    ));
                }
                
            } catch (Exception $e) {
                error_log("Error generating voucher #" . ($i + 1) . ": " . $e->getMessage());
                $failedCount++;
            }
        }
        
        $API->disconnect();
        
        // Get updated balance
        $updatedAgent = $agentClass->getAgentById($agent['id']);
        
        // Format price
        if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
            $totalCostFormatted = $currency . " " . number_format($totalDeducted, 0, ",", ".");
        } else {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
            $totalCostFormatted = $currency . " " . number_format($totalDeducted, 2);
        }
        
        // Send result message
        $message = "🎫 *BULK VOUCHER RESULT*\n\n";
        $message .= "✅ Berhasil: *$successCount* voucher\n";
        if ($failedCount > 0) {
            $message .= "❌ Gagal: *$failedCount* voucher\n";
        }
        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📦 Paket: *$profileName*\n";
        $message .= "💰 Harga per voucher: $priceFormatted\n";
        $message .= "💳 Total biaya: $totalCostFormatted\n";
        $message .= "💳 Saldo tersisa: Rp " . number_format($updatedAgent['balance'], 0, ',', '.') . "\n\n";
        
        if (!empty($generatedVouchers)) {
            $message .= "🎫 *DAFTAR VOUCHER:*\n\n";
            
            foreach ($generatedVouchers as $index => $v) {
                $message .= "*#" . ($index + 1) . "*\n";
                $message .= "Username: `{$v['username']}`\n";
                $message .= "Password: `{$v['password']}`\n\n";
                
                // Limit display to 10 vouchers to avoid message too long
                if ($index >= 9 && count($generatedVouchers) > 10) {
                    $remaining = count($generatedVouchers) - 10;
                    $message .= "... dan $remaining voucher lainnya\n\n";
                    break;
                }
            }
            
            $message .= "\n🌐 Login URL:\n";
            $message .= "http://$dnsname\n\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "_Terima kasih telah menggunakan layanan kami_";
        
        sendTelegramMessage($chatId, $message);
        
        // If there are many vouchers, send them as a file or separate message
        if (count($generatedVouchers) > 10) {
            $voucherList = "📋 *DAFTAR LENGKAP VOUCHER*\n\n";
            $voucherList .= "Paket: *$profileName*\n";
            $voucherList .= "Jumlah: *$successCount* voucher\n\n";
            
            foreach ($generatedVouchers as $index => $v) {
                $voucherList .= ($index + 1) . ". {$v['username']} | {$v['password']}\n";
            }
            
            $voucherList .= "\nLogin URL: http://$dnsname";
            
            sendTelegramMessage($chatId, $voucherList);
        }
        
    } catch (Exception $e) {
        error_log("Error in generateTelegramBulkVouchers: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat generate bulk voucher.\n\nSilakan coba lagi atau hubungi administrator.");
    }
}

/**
 * Ultra fast callback response (non-blocking)
 */
function fastTelegramCallback($callbackQueryId) {
    // Fire and forget - ultra fast response
    $url = TELEGRAM_API_URL . '/answerCallbackQuery';
    $postData = json_encode([
        'callback_query_id' => $callbackQueryId,
        'text' => '✅',
        'show_alert' => false
    ]);
    
    // Ultra fast non-blocking request
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $postData,
            'timeout' => 0.1 // Ultra fast timeout
        ]
    ]);
    
    // Fire and forget
    @file_get_contents($url, false, $context);
}

/**
 * Send instant processing message for slow operations
 */
function sendInstantProcessingMessage($chatId, $action, $parts) {
    $messages = [
        'generate_select' => '⚡ Memuat opsi...',
        'generate_bulk' => '📦 Menyiapkan bulk...',
        'bulk_generate' => '🚀 Memulai generate...',
        'agent_packages' => '📋 Memuat paket...',
        'agent_generate' => '🎫 Menyiapkan menu generate...',
        'agent_quick' => '⚡ Menyiapkan menu cepat...',
        'agent_billing' => '💳 Menyiapkan menu billing...'
    ];
    
    $message = $messages[$action] ?? '⏳ Memproses...';
    
    // Ultra fast message send
    $url = TELEGRAM_API_URL . '/sendMessage';
    $postData = json_encode([
        'chat_id' => $chatId,
        'text' => $message
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $postData,
            'timeout' => 0.2
        ]
    ]);
    
    @file_get_contents($url, false, $context);
}

/**
 * Refresh packages asynchronously in background
 */
function refreshPackagesAsync($chatId, $agent) {
    // This function runs in background to refresh packages without blocking user response
    try {
        // Background refresh
        $packages = getTelegramCachedPackages(true);
        
        if (!empty($packages)) {
            // Update menu with loaded packages
            showTelegramGenerateVoucherMenuWithPackages($chatId, $agent, $packages);
        } else {
            // If still no packages, send error message
            $errorMessage = "🎫 *GENERATE VOUCHER MENU*\n\n";
            $errorMessage .= "💰 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
            $errorMessage .= "❌ *GAGAL MEMUAT PAKET*\n\n";
            $errorMessage .= "Tidak dapat menghubungi MikroTik.\n";
            $errorMessage .= "Silakan coba beberapa saat lagi atau gunakan perintah:\n";
            $errorMessage .= "• VOUCHER [nama_paket]\n";
            $errorMessage .= "• HARGA";
            
            $keyboard = [
                [['text' => '🔄 Refresh', 'callback_data' => 'refresh_packages']],
                [['text' => '🔙 Kembali', 'callback_data' => 'agent_menu']]
            ];
            
            if (function_exists('sendTelegramMessageWithKeyboard')) {
                sendTelegramMessageWithKeyboard($chatId, $errorMessage, $keyboard);
            } else {
                sendTelegramMessage($chatId, $errorMessage);
            }
        }
    } catch (Exception $e) {
        error_log("Error in refreshPackagesAsync: " . $e->getMessage());
        // Don't send error to user in async function to avoid duplicate messages
    }
}

/**
 * Ultra fast package cache with memory storage
 */
function getUltraFastPackageCache() {
    static $memoryCache = null;
    static $cacheTime = 0;
    
    // Memory cache valid for 2 minutes
    if ($memoryCache !== null && (time() - $cacheTime) < 120) {
        return $memoryCache;
    }
    
    // Try prewarmed memory cache first (ultra fast)
    $memoryCacheFile = __DIR__ . '/../cache/telegram_packages_memory.json';
    if (file_exists($memoryCacheFile)) {
        $memoryCacheTime = filemtime($memoryCacheFile);
        if ((time() - $memoryCacheTime) < 300) { // 5 minutes
            $cached = json_decode(file_get_contents($memoryCacheFile), true);
            if ($cached && is_array($cached)) {
                $memoryCache = $cached;
                $cacheTime = time();
                return $memoryCache;
            }
        }
    }
    
    // Fallback to regular file cache
    $cacheFile = __DIR__ . '/../cache/telegram_packages.json';
    if (file_exists($cacheFile)) {
        $fileCacheTime = filemtime($cacheFile);
        // Use cache if less than 10 minutes old
        if ((time() - $fileCacheTime) < 600) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['packages'])) {
                $memoryCache = $cached['packages'];
                $cacheTime = time();
                return $memoryCache;
            }
        }
    }
    
    return [];
}

/**
 * Send instant generate menu without waiting for packages
 */
function sendInstantGenerateMenu($chatId, $agent) {
    $message = "🎫 *GENERATE VOUCHER*\n\n";
    $message .= "💰 Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
    $message .= "⚡ *QUICK ACTIONS:*\n\n";
    $message .= "📝 *Perintah Cepat:*\n";
    $message .= "• `VOUCHER 1JAM` - Generate 1 jam\n";
    $message .= "• `VOUCHER 3K` - Generate 3000\n";
    $message .= "• `VOUCHER 5K` - Generate 5000\n";
    $message .= "• `BULK 3K 5` - Generate 5 voucher\n";
    $message .= "• `HARGA` - Lihat semua paket\n\n";
    $message .= "⏳ _Memuat menu lengkap..._";
    
    $keyboard = [
        [['text' => '🔄 Refresh', 'callback_data' => 'refresh_packages']],
        [['text' => '🔙 Kembali', 'callback_data' => 'agent_menu']]
    ];
    
    if (function_exists('sendTelegramMessageWithKeyboard')) {
        sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
    } else {
        sendTelegramMessage($chatId, $message);
    }
}

/**
 * Optimized single voucher generation with instant feedback
 */
function generateSingleVoucherOptimized($chatId, $profileName, $isAdmin = false) {
    // INSTANT feedback
    $quickMsg = "⚡ *PROCESSING*\n\n";
    $quickMsg .= "📦 $profileName\n";
    $quickMsg .= "🔄 Generating...";
    
    // Ultra fast message
    $url = TELEGRAM_API_URL . '/sendMessage';
    $postData = json_encode([
        'chat_id' => $chatId,
        'text' => $quickMsg
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $postData,
            'timeout' => 0.1
        ]
    ]);
    
    @file_get_contents($url, false, $context);
    
    // Then process normally
    purchaseTelegramVoucher($chatId, $profileName, $isAdmin);
}

/**
 * Show agent billing menu
 */
function showTelegramAgentBillingMenu($chatId) {
    try {
        // Check if user is agent
        $agent = getTelegramAgentByPhone($chatId);
        if (!$agent) {
            sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai agent.");
            return;
        }
        
        // INSTANT response - send quick loading message
        $loadingMessage = "💳 *BAYAR TAGIHAN PELANGGAN*\n\n";
        $loadingMessage .= "💰 Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $loadingMessage .= "🔄 Memuat menu billing...";
        
        // Send instant loading message
        sendTelegramMessage($chatId, $loadingMessage);
        
        $message = "💳 *BAYAR TAGIHAN PELANGGAN*\n\n";
        $message .= "💰 Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $message .= "🔍 *Cara mencari pelanggan:*\n\n";
        $message .= "📝 *Perintah yang tersedia:*\n";
        $message .= "• `TAGIHAN [nama/hp]` - Cek tagihan\n";
        $message .= "• `BAYAR [nama/hp]` - Bayar tagihan bulan ini\n";
        $message .= "• `BAYAR [nama/hp] [periode]` - Bayar periode tertentu\n\n";
        
        $message .= "*Contoh:*\n";
        $message .= "• `TAGIHAN 081234567890`\n";
        $message .= "• `BAYAR John Doe`\n";
        $message .= "• `BAYAR 081234567890 2025-12`\n\n";
        
        $message .= "⚠️ *Catatan:*\n";
        $message .= "Pembayaran akan memotong saldo Anda dan mengubah status tagihan menjadi LUNAS.";
        
        $keyboard = [
            [['text' => '🔍 Cari Pelanggan', 'callback_data' => 'billing_search']],
            [['text' => '🔙 Kembali', 'callback_data' => 'agent_menu']]
        ];
        
        if (function_exists('sendTelegramMessageWithKeyboard')) {
            sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in showTelegramAgentBillingMenu: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan.\n\nGunakan perintah: TAGIHAN [nama/hp]");
    }
}

/**
 * Check customer bills
 */
function checkTelegramCustomerBills($chatId, $customerIdentifier) {
    try {
        // Load BillingService
        if (!class_exists('BillingService')) {
            require_once(__DIR__ . '/../lib/BillingService.class.php');
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendTelegramMessage($chatId, "❌ Koneksi database gagal.");
            return;
        }
        
        // Search customer by name or phone
        $stmt = $db->prepare(
            "SELECT bc.*, bp.profile_name, bp.price " .
            "FROM billing_customers bc " .
            "LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id " .
            "WHERE bc.name LIKE :identifier OR bc.phone LIKE :identifier " .
            "LIMIT 5"
        );
        $stmt->execute([':identifier' => '%' . $customerIdentifier . '%']);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($customers)) {
            sendTelegramMessage($chatId, "❌ *PELANGGAN TIDAK DITEMUKAN*\n\nTidak ada pelanggan dengan nama atau nomor HP: *$customerIdentifier*");
            return;
        }
        
        if (count($customers) > 1) {
            // Multiple customers found
            $message = "🔍 *DITEMUKAN " . count($customers) . " PELANGGAN*\n\n";
            
            foreach ($customers as $index => $customer) {
                $message .= "*" . ($index + 1) . ". {$customer['name']}*\n";
                $message .= "HP: {$customer['phone']}\n";
                $message .= "Profile: {$customer['profile_name']}\n";
                $message .= "Status: " . ($customer['is_isolated'] ? '❌ Terisolir' : '✅ Aktif') . "\n\n";
            }
            
            $message .= "Gunakan nama lengkap atau nomor HP yang lebih spesifik.";
            sendTelegramMessage($chatId, $message);
            return;
        }
        
        // Single customer found
        $customer = $customers[0];
        
        // Get unpaid invoices
        $stmt = $db->prepare(
            "SELECT * FROM billing_invoices " .
            "WHERE customer_id = :customer_id AND status IN ('unpaid', 'overdue') " .
            "ORDER BY period DESC LIMIT 6"
        );
        $stmt->execute([':customer_id' => $customer['id']]);
        $unpaidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $message = "💳 *INFO TAGIHAN PELANGGAN*\n\n";
        $message .= "👤 Nama: *{$customer['name']}*\n";
        $message .= "📞 HP: {$customer['phone']}\n";
        $message .= "📦 Profile: {$customer['profile_name']}\n";
        $message .= "💰 Tarif: Rp " . number_format($customer['price'], 0, ',', '.') . "/bulan\n";
        $message .= "📊 Status: " . ($customer['is_isolated'] ? '❌ Terisolir' : '✅ Aktif') . "\n\n";
        
        if (empty($unpaidInvoices)) {
            $message .= "✅ *TIDAK ADA TAGIHAN TERTUNGGAK*\n\n";
            $message .= "Semua tagihan sudah lunas.";
        } else {
            $message .= "❌ *TAGIHAN TERTUNGGAK: " . count($unpaidInvoices) . "*\n\n";
            
            $totalUnpaid = 0;
            $keyboard = [];
            
            foreach ($unpaidInvoices as $invoice) {
                $totalUnpaid += $invoice['amount'];
                $dueDate = date('d/m/Y', strtotime($invoice['due_date']));
                $period = date('M Y', strtotime($invoice['period'] . '-01'));
                
                $message .= "📅 *$period*\n";
                $message .= "Jumlah: Rp " . number_format($invoice['amount'], 0, ',', '.') . "\n";
                $message .= "Jatuh tempo: $dueDate\n";
                $message .= "Status: " . ucfirst($invoice['status']) . "\n\n";
                
                // Add payment button
                $keyboard[] = [['text' => "Bayar $period", 'callback_data' => "billing_pay:{$customer['id']}:{$invoice['period']}"]];
            }
            
            $message .= "💰 *Total Tertunggak: Rp " . number_format($totalUnpaid, 0, ',', '.') . "*\n\n";
            $message .= "📝 *Cara bayar:*\n";
            $message .= "• Klik tombol di bawah, atau\n";
            $message .= "• Ketik: `BAYAR {$customer['phone']}`";
            
            $keyboard[] = [['text' => '🔙 Kembali', 'callback_data' => 'agent_billing']];
        }
        
        if (isset($keyboard) && !empty($keyboard)) {
            if (function_exists('sendTelegramMessageWithKeyboard')) {
                sendTelegramMessageWithKeyboard($chatId, $message, $keyboard);
            } else {
                sendTelegramMessage($chatId, $message);
            }
        } else {
            sendTelegramMessage($chatId, $message);
        }
        
    } catch (Exception $e) {
        error_log("Error in checkTelegramCustomerBills: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat mengecek tagihan.\n\nSilakan coba lagi.");
    }
}

/**
 * Process bill payment by customer identifier
 */
function processTelegramBillPayment($chatId, $customerIdentifier, $period, $isAdmin) {
    try {
        $db = getDBConnection();
        if (!$db) {
            sendTelegramMessage($chatId, "❌ Koneksi database gagal.");
            return;
        }
        
        // Search customer
        $stmt = $db->prepare(
            "SELECT bc.*, bp.profile_name, bp.price " .
            "FROM billing_customers bc " .
            "LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id " .
            "WHERE bc.name LIKE :identifier OR bc.phone LIKE :identifier " .
            "LIMIT 1"
        );
        $stmt->execute([':identifier' => '%' . $customerIdentifier . '%']);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            sendTelegramMessage($chatId, "❌ *PELANGGAN TIDAK DITEMUKAN*\n\nTidak ada pelanggan dengan nama atau nomor HP: *$customerIdentifier*");
            return;
        }
        
        // Process payment by customer ID
        processTelegramBillPaymentById($chatId, $customer['id'], $period, $isAdmin);
        
    } catch (Exception $e) {
        error_log("Error in processTelegramBillPayment: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat memproses pembayaran.");
    }
}

/**
 * Process bill payment by customer ID
 */
function processTelegramBillPaymentById($chatId, $customerId, $period, $isAdmin) {
    try {
        // Load BillingService
        if (!class_exists('BillingService')) {
            require_once(__DIR__ . '/../lib/BillingService.class.php');
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendTelegramMessage($chatId, "❌ Koneksi database gagal.");
            return;
        }
        
        // Get customer info
        $stmt = $db->prepare(
            "SELECT bc.*, bp.profile_name, bp.price " .
            "FROM billing_customers bc " .
            "LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id " .
            "WHERE bc.id = :customer_id"
        );
        $stmt->execute([':customer_id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            sendTelegramMessage($chatId, "❌ Pelanggan tidak ditemukan.");
            return;
        }
        
        // Get invoice
        $stmt = $db->prepare(
            "SELECT * FROM billing_invoices " .
            "WHERE customer_id = :customer_id AND period = :period"
        );
        $stmt->execute([':customer_id' => $customerId, ':period' => $period]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            sendTelegramMessage($chatId, "❌ Tagihan untuk periode $period tidak ditemukan.");
            return;
        }
        
        if ($invoice['status'] === 'paid') {
            sendTelegramMessage($chatId, "✅ Tagihan periode $period sudah lunas.\n\nDibayar pada: " . date('d/m/Y H:i', strtotime($invoice['paid_at'])));
            return;
        }
        
        // Send processing message
        $processingMsg = "⏳ *MEMPROSES PEMBAYARAN*\n\n";
        $processingMsg .= "👤 Pelanggan: {$customer['name']}\n";
        $processingMsg .= "📅 Periode: $period\n";
        $processingMsg .= "💰 Jumlah: Rp " . number_format($invoice['amount'], 0, ',', '.') . "\n\n";
        $processingMsg .= "🔄 Sedang diproses...";
        sendTelegramMessage($chatId, $processingMsg);
        
        if ($isAdmin) {
            // Admin payment (no balance deduction)
            $stmt = $db->prepare(
                "UPDATE billing_invoices SET " .
                "status = 'paid', paid_at = NOW(), payment_channel = 'admin_telegram', " .
                "reference_number = :ref_number " .
                "WHERE id = :invoice_id"
            );
            $refNumber = 'ADMIN-TG-' . time();
            $stmt->execute([':ref_number' => $refNumber, ':invoice_id' => $invoice['id']]);
            
            $paymentMethod = 'Admin (Telegram)';
            
        } else {
            // Agent payment (with balance deduction)
            $agent = getTelegramAgentByPhone($chatId);
            if (!$agent) {
                sendTelegramMessage($chatId, "❌ Anda tidak terdaftar sebagai agent.");
                return;
            }
            
            // Check balance
            if ($agent['balance'] < $invoice['amount']) {
                $reply = "❌ *SALDO TIDAK CUKUP*\n\n";
                $reply .= "Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
                $reply .= "Dibutuhkan: Rp " . number_format($invoice['amount'], 0, ',', '.') . "\n";
                $reply .= "Kurang: Rp " . number_format($invoice['amount'] - $agent['balance'], 0, ',', '.') . "\n\n";
                $reply .= "Silakan topup saldo terlebih dahulu.";
                sendTelegramMessage($chatId, $reply);
                return;
            }
            
            // Load Agent class
            if (!class_exists('Agent')) {
                require_once(__DIR__ . '/../lib/Agent.class.php');
            }
            
            $agentClass = new Agent();
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Deduct agent balance
                $deductResult = $agentClass->deductBalance(
                    $agent['id'],
                    $invoice['amount'],
                    'billing_payment',
                    $customer['name'],
                    "Bayar tagihan {$customer['name']} periode $period",
                    'billing_payment'
                );
                
                if (!$deductResult['success']) {
                    $db->rollBack();
                    sendTelegramMessage($chatId, "❌ Gagal memotong saldo: " . $deductResult['message']);
                    return;
                }
                
                // Update invoice status
                $stmt = $db->prepare(
                    "UPDATE billing_invoices SET " .
                    "status = 'paid', paid_at = NOW(), payment_channel = 'agent_telegram', " .
                    "reference_number = :ref_number, paid_via_agent_id = :agent_id " .
                    "WHERE id = :invoice_id"
                );
                $refNumber = 'AG-TG-' . $agent['agent_code'] . '-' . time();
                $stmt->execute([
                    ':ref_number' => $refNumber,
                    ':agent_id' => $agent['id'],
                    ':invoice_id' => $invoice['id']
                ]);
                
                // Record agent billing payment
                $stmt = $db->prepare(
                    "INSERT INTO agent_billing_payments (agent_id, invoice_id, amount, status, processed_by) " .
                    "VALUES (:agent_id, :invoice_id, :amount, 'paid', 'telegram')"
                );
                $stmt->execute([
                    ':agent_id' => $agent['id'],
                    ':invoice_id' => $invoice['id'],
                    ':amount' => $invoice['amount']
                ]);
                
                $db->commit();
                $paymentMethod = "Agent {$agent['agent_name']} (Telegram)";
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error in agent payment transaction: " . $e->getMessage());
                sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat memproses pembayaran.");
                return;
            }
        }
        
        // Restore customer profile if isolated
        if ($customer['is_isolated']) {
            restoreTelegramCustomerProfile($customer);
        }
        
        // Send success message
        $successMsg = "✅ *PEMBAYARAN BERHASIL*\n\n";
        $successMsg .= "👤 Pelanggan: *{$customer['name']}*\n";
        $successMsg .= "📞 HP: {$customer['phone']}\n";
        $successMsg .= "📅 Periode: *$period*\n";
        $successMsg .= "💰 Jumlah: *Rp " . number_format($invoice['amount'], 0, ',', '.') . "*\n";
        $successMsg .= "💳 Metode: $paymentMethod\n";
        $successMsg .= "📝 Referensi: `$refNumber`\n\n";
        
        if (!$isAdmin) {
            $updatedAgent = $agentClass->getAgentById($agent['id']);
            $successMsg .= "💳 Saldo tersisa: Rp " . number_format($updatedAgent['balance'], 0, ',', '.') . "\n\n";
        }
        
        if ($customer['is_isolated']) {
            $successMsg .= "✅ *Pelanggan telah dikembalikan ke profile aktif.*\n\n";
        }
        
        $successMsg .= "✨ _Pembayaran telah dicatat dalam sistem._";
        
        sendTelegramMessage($chatId, $successMsg);
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error in processTelegramBillPaymentById: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat memproses pembayaran.\n\nSilakan coba lagi.");
    }
}

/**
 * Restore customer profile from isolation
 */
function restoreTelegramCustomerProfile($customer) {
    try {
        // Load session config
        global $data;
        if (!isset($data) || empty($data)) {
            require_once(__DIR__ . '/../include/config.php');
        }
        $sessionConfig = isset($data) ? $data : array();
        
        if (empty($sessionConfig)) {
            error_log("No session config for profile restoration");
            return false;
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
        
        if (!$session) {
            error_log("No MikroTik session found for profile restoration");
            return false;
        }
        
        // Load session config
        $sessionData = $sessionConfig[$session];
        $iphost = explode('!', $sessionData[1])[1] ?? '';
        $userhost = explode('@|@', $sessionData[2])[1] ?? '';
        $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
        
        if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
            error_log("Incomplete session config for profile restoration");
            return false;
        }
        
        // Connect to MikroTik
        require_once(__DIR__ . '/../lib/routeros_api.class.php');
        
        $API = new RouterosAPI();
        $API->debug = false;
        
        if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
            error_log("Failed to connect to MikroTik for profile restoration");
            return false;
        }
        
        // Find PPPoE user
        $pppoeUsers = $API->comm("/ppp/secret/print", array(
            "?name" => $customer['genieacs_pppoe_username']
        ));
        
        if (!empty($pppoeUsers)) {
            $pppoeUser = $pppoeUsers[0];
            $currentProfile = $pppoeUser['profile'] ?? '';
            
            // Check if user is isolated (profile contains 'isolir' or similar)
            if (stripos($currentProfile, 'isolir') !== false || stripos($currentProfile, 'block') !== false) {
                // Restore to original profile
                $API->comm("/ppp/secret/set", array(
                    ".id" => $pppoeUser['.id'],
                    "profile" => $customer['profile_name'] // Restore to customer's profile
                ));
                
                error_log("Restored customer {$customer['name']} from profile $currentProfile to {$customer['profile_name']}");
            }
        }
        
        $API->disconnect();
        
        // Update customer isolation status
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->prepare("UPDATE billing_customers SET is_isolated = 0 WHERE id = :id");
            $stmt->execute([':id' => $customer['id']]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error in restoreTelegramCustomerProfile: " . $e->getMessage());
        return false;
    }
}

/**
 * Process Telegram agent registration
 */
function processTelegramAgentRegistration($chatId, $phoneNumber, $username = '', $firstName = '', $lastName = '') {
    try {
        // Clean phone number
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Normalize phone number formats
        if (substr($cleanPhone, 0, 2) === '62') {
            $cleanPhone = '0' . substr($cleanPhone, 2);
        } elseif (substr($cleanPhone, 0, 1) === '1' && strlen($cleanPhone) > 10) {
            // Remove country code if it looks like +1 format
            $cleanPhone = '0' . $cleanPhone;
        }
        
        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
            sendTelegramMessage($chatId, "❌ *NOMOR HP TIDAK VALID*\n\nFormat nomor HP tidak sesuai.\n\n*Contoh format yang benar:*\n• 081234567890\n• 08123456789\n• 6281234567890");
            return;
        }
        
        // Load database connection
        if (!function_exists('getDBConnection')) {
            if (file_exists(__DIR__ . '/../include/db_config.php')) {
                require_once(__DIR__ . '/../include/db_config.php');
            } else {
                sendTelegramMessage($chatId, "❌ Database tidak tersedia.");
                return;
            }
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendTelegramMessage($chatId, "❌ Koneksi database gagal.");
            return;
        }
        
        // Send processing message
        sendTelegramMessage($chatId, "🔍 *MENCARI NOMOR HP...*\n\nMencari nomor: $cleanPhone\n\n⏳ Mohon tunggu...");
        
        // Check if this chat_id is already registered
        $stmt = $db->prepare("SELECT id, agent_name, phone FROM agents WHERE telegram_chat_id = :chat_id");
        $stmt->execute([':chat_id' => $chatId]);
        $existingAgent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingAgent) {
            sendTelegramMessage($chatId, "⚠️ *SUDAH TERDAFTAR*\n\nAkun Telegram Anda sudah terhubung dengan:\n\n👤 **{$existingAgent['agent_name']}**\n📞 {$existingAgent['phone']}\n\nJika ingin mengganti, hubungi administrator.");
            return;
        }
        
        // Search for agent by phone number (flexible matching)
        $stmt = $db->prepare(
            "SELECT id, agent_code, agent_name, phone, telegram_chat_id, status FROM agents " .
            "WHERE (phone = :phone1 OR phone = :phone2 OR phone = :phone3 OR phone = :phone4) " .
            "AND status = 'active' LIMIT 1"
        );
        $stmt->execute([
            ':phone1' => $cleanPhone,
            ':phone2' => '62' . substr($cleanPhone, 1),
            ':phone3' => '+62' . substr($cleanPhone, 1),
            ':phone4' => $phoneNumber // Original input
        ]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agent) {
            // Also check billing customers
            $stmt = $db->prepare(
                "SELECT id, name, phone, telegram_chat_id FROM billing_customers " .
                "WHERE (phone = :phone1 OR phone = :phone2 OR phone = :phone3 OR phone = :phone4) " .
                "AND status = 'active' LIMIT 1"
            );
            $stmt->execute([
                ':phone1' => $cleanPhone,
                ':phone2' => '62' . substr($cleanPhone, 1),
                ':phone3' => '+62' . substr($cleanPhone, 1),
                ':phone4' => $phoneNumber
            ]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                // Check if customer already has telegram_chat_id
                if (!empty($customer['telegram_chat_id']) && $customer['telegram_chat_id'] != $chatId) {
                    sendTelegramMessage($chatId, "❌ *NOMOR SUDAH TERDAFTAR*\n\nNomor HP ini sudah terhubung dengan akun Telegram lain.\n\nJika ini adalah akun Anda, hubungi administrator.");
                    return;
                }
                
                // Update customer telegram_chat_id
                $stmt = $db->prepare("UPDATE billing_customers SET telegram_chat_id = :chat_id, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':chat_id' => $chatId, ':id' => $customer['id']]);
                
                sendTelegramMessage($chatId, "✅ *REGISTRASI BERHASIL*\n\n🎉 **Selamat datang, {$customer['name']}!**\n\n👤 **Status:** PELANGGAN\n📞 **HP:** {$customer['phone']}\n💬 **Telegram:** Terhubung\n\n📋 **Fitur yang tersedia:**\n• TAGIHAN [nama/hp] - Cek tagihan\n• HARGA - Lihat paket\n\n🤖 Akun Telegram Anda sekarang terhubung dengan sistem billing.");
                return;
            }
            
            sendTelegramMessage($chatId, "❌ *NOMOR TIDAK DITEMUKAN*\n\nNomor HP **$cleanPhone** tidak terdaftar dalam sistem.\n\n📝 **Kemungkinan penyebab:**\n• Nomor belum didaftarkan sebagai agent\n• Format nomor tidak sesuai\n• Status agent tidak aktif\n\n💡 **Solusi:**\n• Pastikan nomor HP sudah terdaftar\n• Hubungi administrator untuk registrasi\n• Coba format nomor lain (dengan/tanpa kode negara)");
            return;
        }
        
        // Check if agent already has telegram_chat_id
        if (!empty($agent['telegram_chat_id']) && $agent['telegram_chat_id'] != $chatId) {
            sendTelegramMessage($chatId, "❌ *NOMOR SUDAH TERDAFTAR*\n\nNomor HP ini sudah terhubung dengan akun Telegram lain.\n\nJika ini adalah akun Anda, hubungi administrator.");
            return;
        }
        
        // Update agent with telegram info
        $stmt = $db->prepare(
            "UPDATE agents SET telegram_chat_id = :chat_id, telegram_username = :username, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([
            ':chat_id' => $chatId,
            ':username' => !empty($username) ? '@' . $username : null,
            ':id' => $agent['id']
        ]);
        
        // Success message
        $message = "✅ *REGISTRASI BERHASIL*\n\n";
        $message .= "🎉 **Selamat datang, {$agent['agent_name']}!**\n\n";
        $message .= "👤 **Status:** AGENT\n";
        $message .= "🏷️ **Kode:** {$agent['agent_code']}\n";
        $message .= "📞 **HP:** {$agent['phone']}\n";
        $message .= "💬 **Telegram:** Terhubung\n\n";
        $message .= "🎫 **Fitur Agent:**\n";
        $message .= "• /menu - Menu interaktif\n";
        $message .= "• HARGA - Lihat harga agent\n";
        $message .= "• VOUCHER [paket] - Generate voucher\n";
        $message .= "• BAYAR [nama/hp] - Bayar tagihan\n";
        $message .= "• TAGIHAN [nama/hp] - Cek tagihan\n\n";
        $message .= "🤖 **Akun Telegram Anda sekarang terhubung dengan sistem agent!**\n\n";
        $message .= "💡 Ketik /menu untuk mulai menggunakan fitur agent.";
        
        sendTelegramMessage($chatId, $message);
        
        // Log successful registration
        error_log("Telegram agent registration successful: Agent {$agent['agent_name']} (ID: {$agent['id']}) registered with chat_id: $chatId");
        
    } catch (Exception $e) {
        error_log("Error in processTelegramAgentRegistration: " . $e->getMessage());
        sendTelegramMessage($chatId, "❌ Terjadi kesalahan saat registrasi.\n\nSilakan coba lagi atau hubungi administrator.");
    }
}

// Return 200 OK to Telegram
http_response_code(200);
echo json_encode(['ok' => true]);
exit;
