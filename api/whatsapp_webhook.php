<?php
/*
 * WhatsApp Webhook Handler for MikhMon
 * Handle incoming WhatsApp messages for voucher purchase
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load required files
include('../include/config.php');
include('../include/whatsapp_config.php');
include('../lib/routeros_api.class.php');
include('../lib/formatbytesbites.php');

// Load database config if available (for admin check)
if (file_exists('../include/db_config.php')) {
    include('../include/db_config.php');
}

// Load WiFi commands module
if (file_exists(__DIR__ . '/whatsapp_wifi_commands.php')) {
    include_once(__DIR__ . '/whatsapp_wifi_commands.php');
}

// IMPORTANT: Save session config before overwriting $data
// $data from config.php contains MikroTik session configuration
if (!isset($data) || !is_array($data)) {
    // Log error if config not loaded
    error_log("WhatsApp Webhook: config.php tidak ter-load dengan benar");
}
$sessionConfig = isset($data) ? $data : array(); // Save session config to separate variable

// Get webhook data
$input = file_get_contents('php://input');
$webhookData = json_decode($input, true);

// Log incoming webhook
logWebhook($input);

// Process webhook based on gateway
$gateway = WHATSAPP_GATEWAY;

switch ($gateway) {
    case 'fonnte':
        processWebhookFonnte($webhookData);
        break;
    case 'wablas':
        processWebhookWablas($webhookData);
        break;
    case 'woowa':
        processWebhookWoowa($webhookData);
        break;
    case 'mpwa':
        processWebhookMPWA($webhookData);
        break;
    default:
        processWebhookCustom($webhookData);
        break;
}

/**
 * Process Fonnte webhook
 */
function processWebhookFonnte($data) {
    if (!isset($data['message']) || !isset($data['sender'])) {
        return;
    }
    
    $phone = $data['sender'];
    $message = strtolower(trim($data['message']));
    
    processCommand($phone, $message);
}

/**
 * Process Wablas webhook
 */
function processWebhookWablas($data) {
    if (!isset($data['message']) || !isset($data['phone'])) {
        return;
    }
    
    $phone = $data['phone'];
    $message = strtolower(trim($data['message']));
    
    processCommand($phone, $message);
}

/**
 * Process WooWA webhook
 */
function processWebhookWoowa($data) {
    if (!isset($data['message']) || !isset($data['from'])) {
        return;
    }
    
    $phone = $data['from'];
    $message = strtolower(trim($data['message']));
    
    processCommand($phone, $message);
}

/**
 * Process MPWA webhook
 */
function processWebhookMPWA($data) {
    // MPWA webhook format: sender, message, device
    if (!isset($data['message']) || !isset($data['sender'])) {
        return;
    }
    
    $phone = $data['sender'];
    $message = strtolower(trim($data['message']));
    
    processCommand($phone, $message);
}

/**
 * Process Custom webhook
 */
function processWebhookCustom($data) {
    // Sesuaikan dengan format webhook gateway Anda
    if (!isset($data['message']) || !isset($data['phone'])) {
        return;
    }
    
    $phone = $data['phone'];
    $message = strtolower(trim($data['message']));
    
    processCommand($phone, $message);
}

/**
 * Load payment settings from database
 */
function loadPaymentSettings() {
    static $paymentSettings = null;
    
    if ($paymentSettings !== null) {
        return $paymentSettings;
    }
    
    $paymentSettings = [
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'Nama Pemilik',
        'wa_confirm' => '08123456789'
    ];
    
    if (function_exists('getDBConnection')) {
        try {
            $db = getDBConnection();
            if ($db) {
                $stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'payment_%'");
                while ($row = $stmt->fetch()) {
                    $key = str_replace('payment_', '', $row['setting_key']);
                    switch ($key) {
                        case 'bank_name':
                            $paymentSettings['bank_name'] = $row['setting_value'];
                            break;
                        case 'account_number':
                            $paymentSettings['account_number'] = $row['setting_value'];
                            break;
                        case 'account_name':
                            $paymentSettings['account_name'] = $row['setting_value'];
                            break;
                        case 'wa_confirm':
                            $paymentSettings['wa_confirm'] = $row['setting_value'];
                            break;
                    }
                    
                    // Also handle full key names (backward compatibility)
                    if ($row['setting_key'] == 'payment_bank_name') {
                        $paymentSettings['bank_name'] = $row['setting_value'];
                    } elseif ($row['setting_key'] == 'payment_account_number') {
                        $paymentSettings['account_number'] = $row['setting_value'];
                    } elseif ($row['setting_key'] == 'payment_account_name') {
                        $paymentSettings['account_name'] = $row['setting_value'];
                    } elseif ($row['setting_key'] == 'payment_wa_confirm') {
                        $paymentSettings['wa_confirm'] = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error loading payment settings: " . $e->getMessage());
        }
    }
    
    return $paymentSettings;
}

/**
 * Load voucher settings from database (header and footer)
 */
function loadVoucherSettings() {
    static $voucherSettings = null;
    
    if ($voucherSettings !== null) {
        return $voucherSettings;
    }
    
    $voucherSettings = [
        'header' => "╔═══════════════════╗\n║  🎫  ALIJAYA-NET  ║\n╚═══════════════════╝",
        'footer' => "━━━━━━━━━━━━━━━━━━━━\n📞 Customer Service\nWA: 081947215703\n📍 jl. Pantai Tanjungpura Ujunggebang\n\nTerima kasih! 🙏"
    ];
    
    if (function_exists('getDBConnection')) {
        try {
            $db = getDBConnection();
            if ($db) {
                $stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key IN ('voucher_header_text', 'voucher_footer_text')");
                while ($row = $stmt->fetch()) {
                    if ($row['setting_key'] == 'voucher_header_text') {
                        $voucherSettings['header'] = $row['setting_value'];
                    } elseif ($row['setting_key'] == 'voucher_footer_text') {
                        $voucherSettings['footer'] = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error loading voucher settings: " . $e->getMessage());
        }
    }
    
    return $voucherSettings;
}

/**
 * Process incoming command
 * Only responds to valid commands, ignores invalid ones
 */
function processCommand($phone, $message) {
    $messageTrimmed = trim($message);
    $messageLower = strtolower($messageTrimmed);
    
    // Try WiFi commands first (GANTI WIFI, GANTI SANDI)
    if (function_exists('processWiFiCommand')) {
        if (processWiFiCommand($phone, $messageLower, $messageTrimmed)) {
            return; // WiFi command was processed
        }
    }
    
    // Command: VOUCHER [USERNAME] <PROFILE> [NOMER] - Username dan Password SAMA
    // Example: VOUCHER 3K, VOUCHER 1JAM, VOUCHER 3K 08123456789
    // Example with manual username: VOUCHER user123 3K, VOUCHER user123 3K 08123456789
    if (strpos($messageLower, 'voucher ') === 0) {
        $rest = trim(str_replace('voucher ', '', $messageLower));
        // Parse: bisa "3K", "3K 08123456789", "user123 3K", atau "user123 3K 08123456789"
        $parts = preg_split('/\s+/', $rest);
        
        $username = null;
        $profile = null;
        $customerPhone = null;
        
        if (count($parts) == 1) {
            // Format: VOUCHER 3K
            $profile = $parts[0];
        } elseif (count($parts) == 2) {
            // Format: VOUCHER 3K 08123456789 atau VOUCHER user123 3K
            // Check if second part is a phone number (starts with 0 or 62)
            if (preg_match('/^[062]/', $parts[1])) {
                // Format: VOUCHER 3K 08123456789
                $profile = $parts[0];
                $customerPhone = $parts[1];
            } else {
                // Format: VOUCHER user123 3K
                $username = $parts[0];
                $profile = $parts[1];
            }
        } elseif (count($parts) == 3) {
            // Format: VOUCHER user123 3K 08123456789
            $username = $parts[0];
            $profile = $parts[1];
            $customerPhone = $parts[2];
        }
        
        if (!empty($profile)) {
            purchaseVoucher($phone, $profile, 'voucher', $customerPhone, $username); // Mode: username = password
        }
        return; // Valid command processed
    }
    // Command: VCR [USERNAME] <PROFILE> [NOMER] - Alias untuk VOUCHER
    // Example: VCR 3K, VCR user123 3K 08123456789
    elseif (strpos($messageLower, 'vcr ') === 0) {
        $rest = trim(str_replace('vcr ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest);
        
        $username = null;
        $profile = null;
        $customerPhone = null;
        
        if (count($parts) == 1) {
            $profile = $parts[0];
        } elseif (count($parts) == 2) {
            if (preg_match('/^[062]/', $parts[1])) {
                $profile = $parts[0];
                $customerPhone = $parts[1];
            } else {
                $username = $parts[0];
                $profile = $parts[1];
            }
        } elseif (count($parts) == 3) {
            $username = $parts[0];
            $profile = $parts[1];
            $customerPhone = $parts[2];
        }
        
        if (!empty($profile)) {
            purchaseVoucher($phone, $profile, 'voucher', $customerPhone, $username);
        }
        return;
    }
    // Command: GENERATE [USERNAME] <PROFILE> [NOMER] - Alias untuk VOUCHER
    // Example: GENERATE 3K, GENERATE user123 3K 08123456789
    elseif (strpos($messageLower, 'generate ') === 0) {
        $rest = trim(str_replace('generate ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest);
        
        $username = null;
        $profile = null;
        $customerPhone = null;
        
        if (count($parts) == 1) {
            $profile = $parts[0];
        } elseif (count($parts) == 2) {
            if (preg_match('/^[062]/', $parts[1])) {
                $profile = $parts[0];
                $customerPhone = $parts[1];
            } else {
                $username = $parts[0];
                $profile = $parts[1];
            }
        } elseif (count($parts) == 3) {
            $username = $parts[0];
            $profile = $parts[1];
            $customerPhone = $parts[2];
        }
        
        if (!empty($profile)) {
            purchaseVoucher($phone, $profile, 'voucher', $customerPhone, $username);
        }
        return;
    }
    // Command: MEMBER <PROFILE> - Username dan Password BEDA
    // Example: MEMBER 3K, MEMBER 1JAM
    elseif (strpos($messageLower, 'member ') === 0) {
        $profile = trim(str_replace('member ', '', $messageLower));
        if (!empty($profile)) {
            purchaseVoucher($phone, $profile, 'member'); // Mode: username ≠ password
        }
        return; // Valid command processed
    }
    // Command: BELI <PROFILE> - Default (menggunakan setting voucher)
    // Example: BELI 1JAM, BELI 3JAM, BELI 1HARI
    elseif (strpos($messageLower, 'beli ') === 0) {
        $profile = trim(str_replace('beli ', '', $messageLower));
        if (!empty($profile)) {
            purchaseVoucher($phone, $profile, 'default'); // Mode: default dari settings
        }
        return; // Valid command processed
    }
    // Command: HARGA or PAKET or LIST
    elseif (in_array($messageLower, ['harga', 'paket', 'list'])) {
        sendPriceList($phone);
        return; // Valid command processed
    }
    // Command: HELP or BANTUAN
    elseif (in_array($messageLower, ['help', 'bantuan'])) {
        sendHelp($phone);
        return; // Valid command processed
    }
    // Command: TAGIHAN <NAMA/HP> - Cek tagihan pelanggan
    // Example: TAGIHAN 081234567890, TAGIHAN John Doe
    elseif (strpos($messageLower, 'tagihan ') === 0) {
        $customerIdentifier = trim(str_replace('tagihan ', '', $messageTrimmed));
        if (!empty($customerIdentifier)) {
            checkWhatsAppCustomerBills($phone, $customerIdentifier);
        } else {
            sendWhatsAppMessage($phone, "❌ Format salah!\n\n*Format TAGIHAN:*\n• TAGIHAN <NAMA/HP>\n\n*Contoh:*\n• TAGIHAN 081234567890\n• TAGIHAN John Doe");
        }
        return; // Valid command processed
    }
    // Command: BAYAR <NAMA/HP> [PERIODE] - Bayar tagihan pelanggan
    // Example: BAYAR 081234567890, BAYAR John Doe 2025-12
    elseif (strpos($messageLower, 'bayar ') === 0) {
        $rest = trim(str_replace('bayar ', '', $messageTrimmed));
        $parts = preg_split('/\s+/', $rest, 2);
        $customerIdentifier = $parts[0] ?? '';
        $period = $parts[1] ?? date('Y-m'); // Default current month
        
        if (!empty($customerIdentifier)) {
            processWhatsAppBillPayment($phone, $customerIdentifier, $period);
        } else {
            sendWhatsAppMessage($phone, "❌ Format salah!\n\n*Format BAYAR:*\n• BAYAR <NAMA/HP> [PERIODE]\n\n*Contoh:*\n• BAYAR 081234567890\n• BAYAR John Doe 2025-12");
        }
        return; // Valid command processed
    }
    // Command: REG <NOMOR_HP> - Registrasi nomor agent dengan WhatsApp
    // Example: REG 081234567890
    elseif (strpos($messageLower, 'reg ') === 0) {
        $phoneNumber = trim(str_replace('reg ', '', $messageTrimmed));
        if (!empty($phoneNumber)) {
            processWhatsAppAgentRegistration($phone, $phoneNumber);
        } else {
            sendWhatsAppMessage($phone, "❌ Format salah!\n\n*Format REG:*\n• REG <NOMOR_HP>\n\n*Contoh:*\n• REG 081234567890\n\n*Fungsi:* Menghubungkan akun WhatsApp Anda dengan nomor HP agent yang sudah terdaftar.");
        }
        return; // Valid command processed
    }
    // Command: PULSA <SKU> <NOMER> - Beli produk Digiflazz (pulsa, data, e-money, games)
    // Example: PULSA as10 081234567890, PULSA xl5 087828060222
    elseif (strpos($messageLower, 'pulsa ') === 0) {
        $rest = trim(str_replace('pulsa ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest, 2);
        
        if (count($parts) >= 2) {
            $sku = trim($parts[0]);
            $customerNo = trim($parts[1]);
            purchaseDigiflazz($phone, $sku, $customerNo);
        } else {
            sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: PULSA <SKU> <NOMER>\nContoh: PULSA as10 081234567890\n\nKetik HELP untuk bantuan");
        }
        return; // Valid command processed
    }
    // Command: GANTIWIFI <DEVICE_ID> <SSID_BARU> - Ubah WiFi SSID (detailed format)
    // OR: GANTIWIFI <SSID_BARU> - Ubah WiFi SSID (simple format untuk pelanggan terdaftar)
    // Example: GANTIWIFI 192168001001 ALIJAYA-NET
    // Example: GANTIWIFI ALIJAYA-GUEST
    elseif (strpos($messageLower, 'gantiwifi ') === 0) {
        $rest = trim(str_replace('gantiwifi ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest, 2);
        
        // Determine if this is simple format (1 param) or detailed format (2 params)
        if (count($parts) == 1) {
            // Simple format: GANTIWIFI <SSID_BARU>
            $newSSID = trim($parts[0]);
            if (!empty($newSSID)) {
                changeWiFiSSIDByCustomer($phone, $newSSID);
            }
        } elseif (count($parts) >= 2) {
            // Detailed format: GANTIWIFI <DEVICE_ID> <SSID_BARU>
            $deviceId = trim($parts[0]);
            $newSSID = trim($parts[1]);
            changeWiFiSSID($phone, $deviceId, $newSSID);
        } else {
            sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat 1 (Simple): GANTIWIFI <SSID_BARU>\nContoh: GANTIWIFI ALIJAYA-GUEST\n\nFormat 2 (Detail): GANTIWIFI <DEVICE_ID> <SSID_BARU>\nContoh: GANTIWIFI 192168001001 ALIJAYA-NET");
        }
        return; // Valid command processed
    }
    // Command: GANTISANDI <DEVICE_ID> <PASSWORD_BARU> - Ubah WiFi Password (detailed format)
    // OR: GANTISANDI <PASSWORD_BARU> - Ubah WiFi Password (simple format untuk pelanggan terdaftar)
    // Example: GANTISANDI 192168001001 password123456
    // Example: GANTISANDI password123456
    elseif (strpos($messageLower, 'gantisandi ') === 0) {
        $rest = trim(str_replace('gantisandi ', '', $messageLower));
        $parts = preg_split('/\s+/', $rest, 2);
        
        // Determine if this is simple format (1 param) or detailed format (2 params)
        if (count($parts) == 1) {
            // Simple format: GANTISANDI <PASSWORD_BARU>
            $newPassword = trim($parts[0]);
            if (!empty($newPassword)) {
                changeWiFiPasswordByCustomer($phone, $newPassword);
            }
        } elseif (count($parts) >= 2) {
            // Detailed format: GANTISANDI <DEVICE_ID> <PASSWORD_BARU>
            $deviceId = trim($parts[0]);
            $newPassword = trim($parts[1]);
            changeWiFiPassword($phone, $deviceId, $newPassword);
        } else {
            sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat 1 (Simple): GANTISANDI <PASSWORD_BARU>\nContoh: GANTISANDI password123456\n\nFormat 2 (Detail): GANTISANDI <DEVICE_ID> <PASSWORD_BARU>\nContoh: GANTISANDI 192168001001 password123456");
        }
        return; // Valid command processed
    }
    // Command: CARIPERANGKAT <NOMOR|USERNAME> - Cari Device ID dari nomor atau username
    // Example: CARIPERANGKAT 081234567890, CARIPERANGKAT user123
    elseif (strpos($messageLower, 'cariperangkat ') === 0) {
        $rest = trim(str_replace('cariperangkat ', '', $messageLower));
        
        if (!empty($rest)) {
            findDeviceByPhoneOrUsername($phone, $rest);
        } else {
            sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: CARIPERANGKAT <NOMOR_TELEPON|USERNAME>\nContoh: CARIPERANGKAT 081234567890\nContoh: CARIPERANGKAT user123");
        }
        return; // Valid command processed
    }
    // Command: GANTIWIFI <SSID_BARU> - Ubah WiFi SSID (simple format untuk pelanggan terdaftar - auto lookup)
    // Example: GANTIWIFI ALIJAYA-GUEST
    elseif (strpos($messageLower, 'gantiwifi ') === 0 && strpos($messageLower, 'gantiwifi ') === 0) {
        $newSSID = trim(str_replace('gantiwifi ', '', $messageLower));
        
        // Check if this looks like a device ID format (contains dots or numbers)
        $isDeviceId = (strpos($newSSID, '.') !== false || (strlen($newSSID) > 10 && preg_match('/\d+/', $newSSID)));
        
        if (!$isDeviceId && !empty($newSSID)) {
            // This is simple format - auto lookup customer
            changeWiFiSSIDByCustomer($phone, $newSSID);
        } else {
            // This will be handled by the detailed GANTIWIFI command below
            // Continue to next condition
        }
        return; // Valid command processed
    }
    // Command: GANTISANDI <PASSWORD_BARU> - Ubah WiFi Password (simple format untuk pelanggan terdaftar - auto lookup)
    // Example: GANTISANDI password123456
    elseif (strpos($messageLower, 'gantisandi ') === 0) {
        $newPassword = trim(str_replace('gantisandi ', '', $messageLower));
        
        // Check if this looks like a device ID format
        $isDeviceId = (strpos($newPassword, '.') !== false || (strlen($newPassword) > 32));
        
        if (!$isDeviceId && !empty($newPassword)) {
            // This is simple format - auto lookup customer
            changeWiFiPasswordByCustomer($phone, $newPassword);
        } else {
            // This will be handled by detailed command
            // Continue to next condition
        }
        return; // Valid command processed
    }
    
    // Admin-only commands - Check if admin first
    if (isAdminNumber($phone)) {
        // Command: TAMBAH username password profile - Tambah PPPoE Secret
        // Example: TAMBAH user123 pass123 profile1
        if (strpos($messageLower, 'tambah ') === 0) {
            $rest = trim(str_replace('tambah ', '', $messageLower));
            $parts = preg_split('/\s+/', $rest, 3);
            
            if (count($parts) >= 3) {
                $username = $parts[0];
                $password = $parts[1];
                $profile = $parts[2];
                addPPPoESecret($phone, $username, $password, $profile);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: TAMBAH <username> <password> <profile>\nContoh: TAMBAH user123 pass123 profile1");
            }
            return;
        }
        // Command: EDIT username profile_baru - Edit PPPoE Secret Profile
        // Example: EDIT user123 profile2
        elseif (strpos($messageLower, 'edit ') === 0) {
            $rest = trim(str_replace('edit ', '', $messageLower));
            $parts = preg_split('/\s+/', $rest, 2);
            
            if (count($parts) == 2) {
                $username = $parts[0];
                $newProfile = $parts[1];
                editPPPoESecret($phone, $username, $newProfile);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: EDIT <username> <profile_baru>\nContoh: EDIT user123 profile2");
            }
            return;
        }
        // Command: HAPUS username - Hapus PPPoE Secret
        // Example: HAPUS user123
        elseif (strpos($messageLower, 'hapus ') === 0) {
            $rest = trim(str_replace('hapus ', '', $messageLower));
            $username = trim($rest);
            
            if (!empty($username)) {
                deletePPPoESecret($phone, $username);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: HAPUS <username>\nContoh: HAPUS user123");
            }
            return;
        }
        // Command: PING - Test koneksi ke MikroTik
        elseif (in_array($messageLower, ['ping', 'cek ping'])) {
            checkMikroTikPing($phone);
            return;
        }
        // Command: STATUS or CEK - Cek status MikroTik
        elseif (in_array($messageLower, ['status', 'cek', 'cek status'])) {
            checkMikroTikStatus($phone);
            return;
        }
        // Command: PPPOE or PPP - Cek PPPoE aktif
        elseif (in_array($messageLower, ['pppoe', 'ppp', 'pppoe aktif', 'ppp aktif'])) {
            checkPPPoEActive($phone);
            return;
        }
        // Command: RESOURCE or RES - Cek resource MikroTik
        elseif (in_array($messageLower, ['resource', 'res', 'resource mikrotik'])) {
            checkMikroTikResource($phone);
            return;
        }
        // Command: DISABLE PPPOE username - Disable PPPoE Secret
        // Example: DISABLE PPPOE user123
        elseif (strpos($messageLower, 'disable pppoe ') === 0 || strpos($messageLower, 'disable ppp ') === 0) {
            $rest = trim(str_replace(['disable pppoe ', 'disable ppp '], '', $messageLower));
            $username = trim($rest);
            
            if (!empty($username)) {
                disablePPPoESecret($phone, $username);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: DISABLE PPPOE <username>\nContoh: DISABLE PPPOE user123");
            }
            return;
        }
        // Command: DISABLE HOTSPOT username - Disable Hotspot User
        // Example: DISABLE HOTSPOT user123
        elseif (strpos($messageLower, 'disable hotspot ') === 0) {
            $rest = trim(str_replace('disable hotspot ', '', $messageLower));
            $username = trim($rest);
            
            if (!empty($username)) {
                disableHotspotUser($phone, $username);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: DISABLE HOTSPOT <username>\nContoh: DISABLE HOTSPOT user123");
            }
            return;
        }
        // Command: ENABLE PPPOE username - Enable PPPoE Secret
        // Example: ENABLE PPPOE user123
        elseif (strpos($messageLower, 'enable pppoe ') === 0 || strpos($messageLower, 'enable ppp ') === 0) {
            $rest = trim(str_replace(['enable pppoe ', 'enable ppp '], '', $messageLower));
            $username = trim($rest);
            
            if (!empty($username)) {
                enablePPPoESecret($phone, $username);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: ENABLE PPPOE <username>\nContoh: ENABLE PPPOE user123");
            }
            return;
        }
        // Command: ENABLE HOTSPOT username - Enable Hotspot User
        // Example: ENABLE HOTSPOT user123
        elseif (strpos($messageLower, 'enable hotspot ') === 0) {
            $rest = trim(str_replace('enable hotspot ', '', $messageLower));
            $username = trim($rest);
            
            if (!empty($username)) {
                enableHotspotUser($phone, $username);
            } else {
                sendWhatsAppMessage($phone, "❌ *FORMAT SALAH*\n\nFormat: ENABLE HOTSPOT <username>\nContoh: ENABLE HOTSPOT user123");
            }
            return;
        }
        // Command: PPPOE OFFLINE or PPP OFFLINE - Cek PPPoE yang offline
        // Example: PPPOE OFFLINE, PPP OFFLINE
        elseif (in_array($messageLower, ['pppoe offline', 'ppp offline', 'pppoe mati', 'ppp mati'])) {
            checkPPPoEOffline($phone);
            return;
        }
        // Command: SALDO DIGIFLAZZ - Cek saldo Digiflazz
        // Example: SALDO DIGIFLAZZ
        elseif (in_array($messageLower, ['saldo digiflazz', 'cek saldo digiflazz', 'balance digiflazz'])) {
            checkDigiflazzBalance($phone);
            return;
        }
    }
    
    // Invalid command - ignore (no response sent)
    // Log for monitoring purposes only
    error_log("WhatsApp Webhook: Invalid command ignored from {$phone}: {$messageTrimmed}");
}

/**
 * Check if admin number
 */
function isAdminNumber($phone) {
    // Check if db_config exists
    if (!function_exists('getDBConnection')) {
        return false;
    }
    
    try {
        $db = getDBConnection();
        if (!$db) {
            return false;
        }
        
        $stmt = $db->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
        $result = $stmt->fetch();
        
        if ($result) {
            $adminNumbers = explode(',', $result['setting_value']);
            $adminNumbers = array_map('trim', $adminNumbers);
            return in_array($phone, $adminNumbers);
        }
    } catch (Exception $e) {
        // Log error but don't break
        error_log("Error checking admin number: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Purchase voucher
 * @param string $phone Nomor WhatsApp (agent/admin)
 * @param string $profileName Nama profile MikroTik
 * @param string $mode 'voucher' (username=password), 'member' (username≠password), 'default' (dari settings)
 * @param string|null $customerPhone Nomor WhatsApp pembeli (opsional)
 * @param string|null $manualUsername Username manual/kustom (opsional, jika tidak diisi akan di-generate otomatis)
 */
function purchaseVoucher($phone, $profileName, $mode = 'default', $customerPhone = null, $manualUsername = null) {
    global $sessionConfig;
    
    // Use session config instead of overwritten $data
    $data = $sessionConfig;
    
    // Validate session config is loaded
    if (empty($data) || !is_array($data)) {
        $errorMsg = "❌ *SISTEM ERROR*\n\n";
        $errorMsg .= "Konfigurasi session tidak ter-load.\n";
        $errorMsg .= "Silakan hubungi admin.";
        sendWhatsAppMessage($phone, $errorMsg);
        
        logWebhookError($phone, "BELI $profileName", "Session config tidak ter-load. sessionConfig is empty or not array");
        return;
    }
    
    // Check if admin
    $isAdmin = isAdminNumber($phone);
    
    // Get first session (you can modify this to use specific session)
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        $errorMsg = "❌ *SISTEM ERROR*\n\n";
        $errorMsg .= "Session MikroTik tidak ditemukan.\n";
        $errorMsg .= "Silakan hubungi admin atau coba lagi nanti.";
        sendWhatsAppMessage($phone, $errorMsg);
        
        // Log error with details
        $availableSessions = is_array($data) ? implode(", ", array_keys($data)) : "Tidak ada data";
        $dataCount = is_array($data) ? count($data) : 0;
        logWebhookError($phone, "BELI $profileName", "Session MikroTik tidak ditemukan. Available sessions: $availableSessions, Count: $dataCount");
        return;
    }
    
    // Validate session data exists
    if (!isset($data[$session]) || !is_array($data[$session])) {
        $errorMsg = "❌ *SISTEM ERROR*\n\n";
        $errorMsg .= "Data session tidak ditemukan.\n";
        $errorMsg .= "Silakan hubungi admin.";
        sendWhatsAppMessage($phone, $errorMsg);
        
        logWebhookError($phone, "BELI $profileName", "Data session tidak ditemukan. Session: $session");
        return;
    }
    
    // Load session config with validation
    $iphost = '';
    $userhost = '';
    $passwdhost = '';
    $hotspotname = '';
    $dnsname = '';
    $currency = 'Rp';
    
    $errors = [];
    
    // Check and extract IP (required)
    if (isset($data[$session][1]) && !empty($data[$session][1])) {
        $parts = explode('!', $data[$session][1]);
        if (isset($parts[1])) {
            $iphost = $parts[1];
        } else {
            $errors[] = "IP tidak ditemukan";
        }
    } else {
        $errors[] = "Field [1] IP kosong";
    }
    
    // Check and extract User (required)
    if (isset($data[$session][2]) && !empty($data[$session][2])) {
        $parts = explode('@|@', $data[$session][2]);
        if (isset($parts[1])) {
            $userhost = $parts[1];
        } else {
            $errors[] = "User tidak ditemukan";
        }
    } else {
        $errors[] = "Field [2] User kosong";
    }
    
    // Check and extract Password (required)
    if (isset($data[$session][3]) && !empty($data[$session][3])) {
        $parts = explode('#|#', $data[$session][3]);
        if (isset($parts[1])) {
            $passwdhost = $parts[1];
        } else {
            $errors[] = "Password tidak ditemukan";
        }
    } else {
        $errors[] = "Field [3] Password kosong";
    }
    
    // Check and extract Hotspot Name (required)
    if (isset($data[$session][4]) && !empty($data[$session][4])) {
        $parts = explode('%', $data[$session][4]);
        if (isset($parts[1])) {
            $hotspotname = $parts[1];
        } else {
            $hotspotname = $session; // Fallback to session name
        }
    } else {
        $hotspotname = $session; // Fallback to session name
    }
    
    // Check and extract DNS Name (required)
    if (isset($data[$session][5]) && !empty($data[$session][5])) {
        $parts = explode('^', $data[$session][5]);
        if (isset($parts[1])) {
            $dnsname = $parts[1];
        } else {
            $dnsname = $iphost; // Fallback to IP
        }
    } else {
        $dnsname = $iphost; // Fallback to IP
    }
    
    // Check and extract Currency (optional, default Rp)
    if (isset($data[$session][6]) && !empty($data[$session][6])) {
        $parts = explode('&', $data[$session][6]);
        if (isset($parts[1])) {
            $currency = $parts[1];
        }
    }
    
    // Check if required fields are missing
    if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
        $errorMsg = "❌ *KONFIGURASI SESSION TIDAK LENGKAP*\n\n";
        $errorMsg .= "Field yang hilang:\n";
        foreach ($errors as $error) {
            $errorMsg .= "• $error\n";
        }
        $errorMsg .= "\nSilakan hubungi admin untuk memperbaiki konfigurasi session.";
        
        sendWhatsAppMessage($phone, $errorMsg);
        
        $errorDetails = "Session: $session, Errors: " . implode(", ", $errors);
        $errorDetails .= " | Data count: " . count($data[$session]);
        $errorDetails .= " | Available keys: " . implode(", ", array_keys($data[$session]));
        logWebhookError($phone, "BELI $profileName", $errorDetails);
        return;
    }
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    $connectResult = $API->connect($iphost, $userhost, decrypt($passwdhost));
    
    if (!$connectResult) {
        $errorMsg = "❌ *GAGAL TERHUBUNG KE SERVER*\n\n";
        $errorMsg .= "Tidak dapat terhubung ke MikroTik.\n";
        $errorMsg .= "Kemungkinan penyebab:\n";
        $errorMsg .= "• MikroTik sedang offline\n";
        $errorMsg .= "• Koneksi jaringan bermasalah\n";
        $errorMsg .= "• IP/User/Password salah\n\n";
        $errorMsg .= "Silakan hubungi admin untuk bantuan.";
        
        sendWhatsAppMessage($phone, $errorMsg);
        
        // Log error with details
        logWebhookError($phone, "BELI $profileName", "Gagal connect ke MikroTik: IP=$iphost, User=$userhost");
        return;
    }
    
    // Get profile
    $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => $profileName));
    
    if (empty($getprofile)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "Paket *$profileName* tidak ditemukan.\nKetik *HARGA* untuk melihat daftar paket.");
        return;
    }
    
    $profile = $getprofile[0];
    $ponlogin = $profile['on-login'];
    $validity = explode(",", $ponlogin)[3];
    $price = explode(",", $ponlogin)[2];
    $sprice = explode(",", $ponlogin)[4];
    
    // Check agent balance (only for non-admin)
    $buyPrice = (float)$sprice;
    $agent = null;
    $agentId = null;
    $balanceBefore = 0;
    $balanceAfter = 0;
    
    if (!$isAdmin) {
        // Get agent info (using existing getAgentByPhone function)
        if (function_exists('getAgentByPhone')) {
            $agent = getAgentByPhone($phone);
            
            if ($agent) {
                $agentId = $agent['id'];
                
                // Validate price
                if ($buyPrice <= 0) {
                    $API->disconnect();
                    sendWhatsAppMessage($phone, "❌ *HARGA TIDAK VALID*\n\nHarga paket *$profileName* belum dikonfigurasi.\nHubungi administrator.");
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
                    sendWhatsAppMessage($phone, $reply);
                    return;
                }
            } else {
                // Not an agent and not an admin - REJECT
                $API->disconnect();
                $errorMsg = "❌ *AKSES DITOLAK*\n\n";
                $errorMsg .= "Nomor Anda tidak terdaftar sebagai agent.\n\n";
                $errorMsg .= "Untuk menjadi agent, silakan hubungi administrator.";
                sendWhatsAppMessage($phone, $errorMsg);
                logWebhookError($phone, "BELI $profileName", "Unauthorized access attempt - not an agent or admin");
                return;
            }
        }
    }
    
    // Generate username and password berdasarkan mode
    $username = '';
    $password = '';
    $comment = '';
    
    // Check if manual username is provided
    if (!empty($manualUsername)) {
        // Use manual username
        $username = trim($manualUsername);
        // Validate username (only alphanumeric, underscore, dash)
        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
        
        if (empty($username)) {
            sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK VALID*\n\nUsername hanya boleh mengandung huruf, angka, underscore (_), dan dash (-).");
            $API->disconnect();
            return;
        }
        
        // Generate password based on mode
        if ($mode == 'voucher') {
            // Mode VOUCHER: password = username
            $password = $username;
            $comment = "up-VOUCHER-MANUAL-" . substr($phone, -4) . "-" . date("dmy");
        } elseif ($mode == 'member') {
            // Mode MEMBER: generate password
            if (file_exists('../lib/VoucherGenerator.class.php')) {
                include_once('../lib/VoucherGenerator.class.php');
                $voucherGen = new VoucherGenerator();
                $password = $voucherGen->generatePassword();
            } else {
                $password = randNULC(6);
            }
            $comment = "up-MEMBER-MANUAL-" . substr($phone, -4) . "-" . date("dmy");
        } else {
            // Mode DEFAULT: generate password
            if (file_exists('../lib/VoucherGenerator.class.php')) {
                include_once('../lib/VoucherGenerator.class.php');
                $voucherGen = new VoucherGenerator();
                $password = $voucherGen->generatePassword();
            } else {
                $password = randNULC(6);
            }
            $comment = "up-WA-MANUAL-" . substr($phone, -4) . "-" . date("dmy");
        }
    } else {
        // Auto-generate username and password
        // Load VoucherGenerator if available
        if (file_exists('../lib/VoucherGenerator.class.php')) {
            include_once('../lib/VoucherGenerator.class.php');
            $voucherGen = new VoucherGenerator();
            
            // Override settings berdasarkan mode
            if ($mode == 'voucher') {
                // Mode VOUCHER: username = password
                $username = $voucherGen->generateUsername();
                $password = $username; // Password sama dengan username
                $comment = "up-VOUCHER-" . substr($phone, -4) . "-" . date("dmy");
            } elseif ($mode == 'member') {
                // Mode MEMBER: username ≠ password
                $username = $voucherGen->generateUsername();
                $password = $voucherGen->generatePassword(); // Password berbeda
                $comment = "up-MEMBER-" . substr($phone, -4) . "-" . date("dmy");
            } else {
                // Mode DEFAULT: gunakan settings dari database
                $voucher = $voucherGen->generateVoucher();
                $username = $voucher['username'];
                $password = $voucher['password'];
                $comment = "up-WA-" . substr($phone, -4) . "-" . date("dmy");
            }
        } else {
            // Fallback jika VoucherGenerator tidak ada
            if ($mode == 'voucher') {
                $username = strtolower($profileName) . randNULC(6);
                $password = $username; // Password sama dengan username
                $comment = "up-VOUCHER-" . substr($phone, -4) . "-" . date("dmy");
            } elseif ($mode == 'member') {
                $username = strtolower($profileName) . randNULC(6);
                $password = randNULC(6); // Password berbeda
                $comment = "up-MEMBER-" . substr($phone, -4) . "-" . date("dmy");
            } else {
                $username = strtolower($profileName) . randNULC(6);
                $password = randNULC(6);
                $comment = "up-WA-" . substr($phone, -4) . "-" . date("dmy");
            }
        }
    }
    
    // Check if username already exists (only for manual username)
    if (!empty($manualUsername)) {
        $checkUser = $API->comm("/ip/hotspot/user/print", array("?name" => $username));
        if (!empty($checkUser)) {
            $errorMsg = "❌ *USERNAME SUDAH TERDAFTAR*\n\n";
            $errorMsg .= "Username *$username* sudah digunakan.\n";
            $errorMsg .= "Silakan gunakan username lain.";
            sendWhatsAppMessage($phone, $errorMsg);
            $API->disconnect();
            return;
        }
    }
    
    // Add user to MikroTik
    $API->comm("/ip/hotspot/user/add", array(
        "server" => "all",
        "name" => $username,
        "password" => $password,
        "profile" => $profileName,
        "comment" => $comment,
    ));
    
    $API->disconnect();
    
    // Deduct balance for agent (only for non-admin)
    if (!$isAdmin && $agent && $agentId) {
        // Load Agent class if not already loaded
        if (!class_exists('Agent')) {
            require_once('../lib/Agent.class.php');
        }
        
        $agentClass = new Agent();
        $deductResult = $agentClass->deductBalance(
            $agentId,
            $buyPrice,
            $profileName,
            $username,
            'Voucher WhatsApp: ' . $profileName,
            'voucher_whatsapp'
        );
        
        if ($deductResult['success']) {
            $balanceBefore = $deductResult['balance_before'];
            $balanceAfter = $deductResult['balance_after'];
        } else {
            // Log error but don't fail the transaction (voucher already created)
            error_log("Failed to deduct balance for agent $agentId: " . $deductResult['message']);
        }
    }
    
    // Format price
    if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
        $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
    } else {
        $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
    }
    
    // Send voucher to customer
    $voucherData = [
        'hotspot_name' => $hotspotname,
        'profile' => $profileName,
        'username' => $username,
        'password' => $password,
        'timelimit' => $profile['session-timeout'],
        'datalimit' => '',
        'validity' => $validity,
        'price' => $priceFormatted,
        'login_url' => "http://$dnsname/login?username=$username&password=$password",
        'comment' => $comment
    ];
    
    // Format and send voucher message directly (more reliable)
    $voucherMsg = "🎫 *VOUCHER ANDA*\n\n";
    $voucherMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $voucherMsg .= "Hotspot: *$hotspotname*\n";
    $voucherMsg .= "Profile: *$profileName*\n\n";
    $voucherMsg .= "Username: `$username`\n";
    $voucherMsg .= "Password: `$password`\n\n";
    
    if (!empty($profile['session-timeout'])) {
        $voucherMsg .= "Time Limit: " . $profile['session-timeout'] . "\n";
    }
    if (!empty($validity)) {
        $voucherMsg .= "Validity: $validity\n";
    }
    if (!empty($priceFormatted)) {
        $voucherMsg .= "Harga: $priceFormatted\n";
    }
    
    // Show balance for agent (not for admin)
    if (!$isAdmin && $balanceAfter > 0) {
        $voucherMsg .= "\n💳 Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n";
    }
    
    $voucherMsg .= "\nLogin URL:\n";
    $voucherMsg .= "http://$dnsname/login?username=$username&password=$password\n\n";
    $voucherMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $voucherMsg .= "_Terima kasih telah menggunakan layanan kami_";
    
    // Determine recipient phones
    $recipientPhones = [];
    
    // Always send to agent (the one who made the command)
    $recipientPhones[] = $phone;
    
    // If customer phone is provided, also send to customer
    if (!empty($customerPhone)) {
        // Normalize customer phone number (formatWhatsAppNumber will be called in sendWhatsAppMessage)
        $customerPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (!empty($customerPhone)) {
            $recipientPhones[] = $customerPhone;
        }
    }
    
    // Send voucher to all recipients
    foreach ($recipientPhones as $recipientPhone) {
        $voucherResult = sendWhatsAppMessage($recipientPhone, $voucherMsg);
        
        // Log transaction (only for agent phone)
        if ($recipientPhone == $phone) {
            logWhatsAppTransaction($phone, $username, $voucherResult['success'] ? 'SUCCESS' : 'FAILED', json_encode($voucherResult));
            
            // Log if voucher send failed
            if (!$voucherResult['success']) {
                logWebhookError($phone, "BELI $profileName", "Gagal kirim voucher ke agent. Error: " . ($voucherResult['message'] ?? 'Unknown'));
            }
        } else {
            // Log for customer
            if (!$voucherResult['success']) {
                logWebhookError($phone, "BELI $profileName", "Gagal kirim voucher ke customer ($recipientPhone). Error: " . ($voucherResult['message'] ?? 'Unknown'));
            }
        }
        
        // Small delay between sends
        usleep(300000); // 0.3 second delay
    }
    
    // Log transaction
    logWhatsAppTransaction($phone, $username, 'SUCCESS', json_encode(['profile' => $profile, 'mode' => $mode]));
}

/**
 * Send price list
 */
function sendPriceList($phone) {
    // Check if user is agent first
    $agent = getWhatsAppAgentByPhone($phone);
    
    if ($agent) {
        // Send agent-specific price list
        sendAgentPriceList($phone, $agent);
    } else {
        // Send general price list
        sendGeneralPriceList($phone);
    }
}

/**
 * Send agent-specific price list
 */
function sendAgentPriceList($phone, $agent) {
    try {
        // Load database connection
        if (!function_exists('getDBConnection')) {
            if (file_exists('../include/db_config.php')) {
                require_once('../include/db_config.php');
            } else {
                sendWhatsAppMessage($phone, "❌ Database tidak tersedia.");
                return;
            }
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendWhatsAppMessage($phone, "❌ Koneksi database gagal.");
            return;
        }
        
        // Load Agent class
        if (!class_exists('Agent')) {
            require_once('../lib/Agent.class.php');
        }
        
        $agentClass = new Agent();
        $agentPrices = $agentClass->getAllAgentPrices($agent['id']);
        
        if (empty($agentPrices)) {
            sendWhatsAppMessage($phone, "❌ *HARGA BELUM DISET*\n\nBelum ada harga yang diset untuk agent Anda.\n\nSilakan hubungi admin untuk setting harga.");
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
        
        sendWhatsAppMessage($phone, $message);
        
    } catch (Exception $e) {
        error_log("Error in sendAgentPriceList: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ Terjadi kesalahan saat mengambil daftar harga.\n\nSilakan coba lagi.");
    }
}

/**
 * Send general price list (for non-agents)
 */
function sendGeneralPriceList($phone) {
    global $sessionConfig;
    
    // Use session config instead of overwritten $data
    $data = $sessionConfig;
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "Sistem sedang maintenance.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    $hotspotname = explode('%', $data[$session][4])[1];
    $currency = explode('&', $data[$session][6])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "Gagal terhubung ke server.");
        return;
    }
    
    // Get all profiles
    $profiles = $API->comm("/ip/hotspot/user/profile/print");
    $API->disconnect();
    
    $message = "*📋 DAFTAR PAKET WIFI*\n";
    $message .= "*$hotspotname*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($profiles as $profile) {
        $name = $profile['name'];
        if ($name == 'default' || $name == 'default-encryption') continue;
        
        $ponlogin = $profile['on-login'];
        if (empty($ponlogin)) continue;
        
        $validity = explode(",", $ponlogin)[3];
        $price = explode(",", $ponlogin)[2];
        $sprice = explode(",", $ponlogin)[4];
        
        if (empty($sprice) || $sprice == '0') continue;
        
        if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
        } else {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
        }
        
        $message .= "*$name*\n";
        $message .= "Validity: $validity\n";
        $message .= "Harga: $priceFormatted\n\n";
    }
    
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "Cara order:\n";
    $message .= "Ketik: *BELI <NAMA_PAKET>*\n";
    $message .= "Contoh: *BELI 1JAM*";
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Add PPPoE Secret
 */
function addPPPoESecret($phone, $username, $password, $profile) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Check if username already exists
    $checkUser = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (!empty($checkUser)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME SUDAH ADA*\n\nUsername *$username* sudah terdaftar.");
        return;
    }
    
    // Check if profile exists
    $checkProfile = $API->comm("/ppp/profile/print", array("?name" => $profile));
    if (empty($checkProfile)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *PROFILE TIDAK DITEMUKAN*\n\nProfile *$profile* tidak ada di MikroTik.");
        return;
    }
    
    // Add PPPoE secret
    $API->comm("/ppp/secret/add", array(
        "name" => $username,
        "password" => $password,
        "service" => "pppoe",
        "profile" => $profile
    ));
    
    $API->disconnect();
    
    sendWhatsAppMessage($phone, "✅ *PPPoE SECRET BERHASIL DITAMBAH*\n\nUsername: *$username*\nProfile: *$profile*");
}

/**
 * Edit PPPoE Secret Profile
 */
function editPPPoESecret($phone, $username, $newProfile) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Find user
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    
    // Check if new profile exists
    $checkProfile = $API->comm("/ppp/profile/print", array("?name" => $newProfile));
    if (empty($checkProfile)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *PROFILE TIDAK DITEMUKAN*\n\nProfile *$newProfile* tidak ada di MikroTik.");
        return;
    }
    
    // Update profile
    $API->comm("/ppp/secret/set", array(
        ".id" => $userId,
        "profile" => $newProfile
    ));
    
    // Disconnect active session (jika ada) agar client reconnect dengan profile baru
    $activeSessions = $API->comm("/ppp/active/print", array("?name" => $username));
    if (!empty($activeSessions)) {
        foreach ($activeSessions as $activeSession) {
            $API->comm("/ppp/active/remove", array(
                ".id" => $activeSession['.id']
            ));
        }
        $activeSessionCount = count($activeSessions);
    } else {
        $activeSessionCount = 0;
    }
    
    $API->disconnect();
    
    // Build response message
    $message = "✅ *PROFILE BERHASIL DIUPDATE*\n\nUsername: *$username*\nProfile Baru: *$newProfile*";
    if ($activeSessionCount > 0) {
        $message .= "\n\n✔️ Session aktif ($activeSessionCount) sudah dihapus.\nClient akan reconnect otomatis dengan profile baru.";
    }
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Delete PPPoE Secret
 */
function deletePPPoESecret($phone, $username) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Find user
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    
    // Delete user
    $API->comm("/ppp/secret/remove", array(".id" => $userId));
    
    $API->disconnect();
    
    sendWhatsAppMessage($phone, "✅ *PPPoE SECRET BERHASIL DIHAPUS*\n\nUsername: *$username*");
}

/**
 * Check MikroTik Ping
 */
function checkMikroTikPing($phone) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    
    // Test ping
    $startTime = microtime(true);
    $API = new RouterosAPI();
    $API->debug = false;
    
    $connected = $API->connect($iphost, explode('@|@', $data[$session][2])[1], decrypt(explode('#|#', $data[$session][3])[1]));
    $endTime = microtime(true);
    $pingTime = round(($endTime - $startTime) * 1000, 2);
    
    if ($connected) {
        $API->disconnect();
        $message = "✅ *PING BERHASIL*\n\n";
        $message .= "IP: *$iphost*\n";
        $message .= "Response Time: *{$pingTime} ms*";
    } else {
        $message = "❌ *PING GAGAL*\n\n";
        $message .= "IP: *$iphost*\n";
        $message .= "Tidak dapat terhubung ke MikroTik.";
    }
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Check MikroTik Status
 */
function checkMikroTikStatus($phone) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get identity
    $identity = $API->comm("/system/identity/print");
    $identityName = $identity[0]['name'] ?? 'Unknown';
    
    // Get uptime
    $resource = $API->comm("/system/resource/print");
    $uptime = $resource[0]['uptime'] ?? '0s';
    
    // Get version
    $version = $resource[0]['version'] ?? 'Unknown';
    
    $API->disconnect();
    
    $message = "📊 *STATUS MIKROTIK*\n\n";
    $message .= "Identity: *$identityName*\n";
    $message .= "IP: *$iphost*\n";
    $message .= "Version: *$version*\n";
    $message .= "Uptime: *$uptime*";
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Check PPPoE Active
 */
function checkPPPoEActive($phone) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get active PPPoE connections
    $active = $API->comm("/ppp/active/print");
    $API->disconnect();
    
    $message = "📡 *PPPoE AKTIF*\n\n";
    $message .= "Total: *" . count($active) . " koneksi*\n\n";
    
    if (empty($active)) {
        $message .= "Tidak ada koneksi aktif.";
    } else {
        $count = 0;
        foreach ($active as $conn) {
            $count++;
            if ($count > 10) {
                $message .= "\n... dan " . (count($active) - 10) . " koneksi lainnya";
                break;
            }
            $name = $conn['name'] ?? 'Unknown';
            $address = $conn['address'] ?? 'N/A';
            $uptime = $conn['uptime'] ?? 'N/A';
            $message .= "$count. *$name*\n";
            $message .= "   IP: $address\n";
            $message .= "   Uptime: $uptime\n\n";
        }
    }
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Check MikroTik Resource
 */
function checkMikroTikResource($phone) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get resource
    $resource = $API->comm("/system/resource/print");
    $API->disconnect();
    
    $res = $resource[0];
    
    $cpu = $res['cpu-load'] ?? '0%';
    $cpuCount = $res['cpu-count'] ?? '1';
    $ramTotal = $res['total-memory'] ?? '0';
    $ramUsed = $res['used-memory'] ?? '0';
    $ramFree = $res['free-memory'] ?? '0';
    $ramPercent = $ramTotal > 0 ? round(($ramUsed / $ramTotal) * 100, 1) : 0;
    
    $diskTotal = $res['total-hdd-space'] ?? '0';
    $diskFree = $res['free-hdd-space'] ?? '0';
    $diskUsed = $diskTotal - $diskFree;
    $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;
    
    // Format bytes
    include_once('../lib/formatbytesbites.php');
    $ramTotalFormatted = formatBytes($ramTotal);
    $ramUsedFormatted = formatBytes($ramUsed);
    $ramFreeFormatted = formatBytes($ramFree);
    $diskTotalFormatted = formatBytes($diskTotal);
    $diskUsedFormatted = formatBytes($diskUsed);
    $diskFreeFormatted = formatBytes($diskFree);
    
    $message = "💻 *RESOURCE MIKROTIK*\n\n";
    $message .= "⚙️ *CPU*\n";
    $message .= "Load: *$cpu*\n";
    $message .= "Cores: *$cpuCount*\n\n";
    $message .= "💾 *RAM*\n";
    $message .= "Used: *$ramUsedFormatted* ($ramPercent%)\n";
    $message .= "Free: *$ramFreeFormatted*\n";
    $message .= "Total: *$ramTotalFormatted*\n\n";
    $message .= "💿 *DISK*\n";
    $message .= "Used: *$diskUsedFormatted* ($diskPercent%)\n";
    $message .= "Free: *$diskFreeFormatted*\n";
    $message .= "Total: *$diskTotalFormatted*";
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Disable PPPoE Secret
 */
function disablePPPoESecret($phone, $username) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Find user
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if ($isDisabled) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "ℹ️ *SUDAH DISABLE*\n\nUsername *$username* sudah dalam keadaan disable.");
        return;
    }
    
    // Disable user
    $API->comm("/ppp/secret/set", array(
        ".id" => $userId,
        "disabled" => "yes"
    ));
    
    $API->disconnect();
    
    sendWhatsAppMessage($phone, "✅ *PPPoE SECRET BERHASIL DISABLE*\n\nUsername: *$username*");
}

/**
 * Enable PPPoE Secret
 */
function enablePPPoESecret($phone, $username) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Find user
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if (!$isDisabled) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "ℹ️ *SUDAH ENABLE*\n\nUsername *$username* sudah dalam keadaan enable.");
        return;
    }
    
    // Enable user
    $API->comm("/ppp/secret/set", array(
        ".id" => $userId,
        "disabled" => "no"
    ));
    
    $API->disconnect();
    
    sendWhatsAppMessage($phone, "✅ *PPPoE SECRET BERHASIL ENABLE*\n\nUsername: *$username*");
}

/**
 * Disable Hotspot User
 */
function disableHotspotUser($phone, $username) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Find user
    $users = $API->comm("/ip/hotspot/user/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if ($isDisabled) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "ℹ️ *SUDAH DISABLE*\n\nUsername *$username* sudah dalam keadaan disable.");
        return;
    }
    
    // Disable user
    $API->comm("/ip/hotspot/user/set", array(
        ".id" => $userId,
        "disabled" => "yes"
    ));
    
    $API->disconnect();
    
    sendWhatsAppMessage($phone, "✅ *HOTSPOT USER BERHASIL DISABLE*\n\nUsername: *$username*");
}

/**
 * Enable Hotspot User
 */
function enableHotspotUser($phone, $username) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Find user
    $users = $API->comm("/ip/hotspot/user/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if (!$isDisabled) {
        $API->disconnect();
        sendWhatsAppMessage($phone, "ℹ️ *SUDAH ENABLE*\n\nUsername *$username* sudah dalam keadaan enable.");
        return;
    }
    
    // Enable user
    $API->comm("/ip/hotspot/user/set", array(
        ".id" => $userId,
        "disabled" => "no"
    ));
    
    $API->disconnect();
    
    sendWhatsAppMessage($phone, "✅ *HOTSPOT USER BERHASIL ENABLE*\n\nUsername: *$username*");
}

/**
 * Check PPPoE Offline (not connected)
 */
function checkPPPoEOffline($phone) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    // Connect to MikroTik
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get all PPPoE secrets
    // Note: Filter by service manually because query might not work as expected
    $allSecrets = $API->comm("/ppp/secret/print");
    
    // Get active PPPoE connections
    $active = $API->comm("/ppp/active/print");
    $activeNames = array();
    foreach ($active as $conn) {
        $name = trim($conn['name'] ?? '');
        if (!empty($name)) {
            $activeNames[] = strtolower($name); // Use lowercase for comparison
        }
    }
    
    // Filter PPPoE secrets and find offline users
    $offlineUsers = array();
    foreach ($allSecrets as $secret) {
        $name = trim($secret['name'] ?? '');
        $service = isset($secret['service']) ? strtolower($secret['service']) : '';
        $disabled = isset($secret['disabled']) && $secret['disabled'] == 'true';
        
        // Skip if empty name
        if (empty($name)) continue;
        
        // Skip if not PPPoE service (should be 'pppoe')
        if ($service != 'pppoe') continue;
        
        // Skip disabled users
        if ($disabled) continue;
        
        // Check if not in active connections (case-insensitive comparison)
        $nameLower = strtolower($name);
        if (!in_array($nameLower, $activeNames)) {
            $profile = $secret['profile'] ?? 'N/A';
            $offlineUsers[] = [
                'name' => $name,
                'profile' => $profile
            ];
        }
    }
    
    $API->disconnect();
    
    $message = "📴 *PPPoE OFFLINE*\n\n";
    $message .= "Total: *" . count($offlineUsers) . " user offline*\n\n";
    
    if (empty($offlineUsers)) {
        $message .= "✅ Semua user PPPoE sedang online.";
    } else {
        $count = 0;
        foreach ($offlineUsers as $user) {
            $count++;
            if ($count > 20) {
                $message .= "\n... dan " . (count($offlineUsers) - 20) . " user lainnya";
                break;
            }
            $message .= "$count. *{$user['name']}*\n";
            $message .= "   Profile: {$user['profile']}\n\n";
        }
    }
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Send help message
 */
function sendHelp($phone) {
    $message = "*🤖 BANTUAN BOT VOUCHER*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "*Perintah yang tersedia:*\n\n";
    $message .= "📋 *HARGA* atau *PAKET*\n";
    $message .= "Melihat daftar paket dan harga\n\n";
    $message .= "🎫 *VOUCHER [USERNAME] <NAMA_PAKET> [NOMER]*\n";
    $message .= "Membeli voucher (Username = Password)\n";
    $message .= "Contoh: VOUCHER 3K\n";
    $message .= "Contoh dengan nomor: VOUCHER 3K 08123456789\n";
    $message .= "Contoh username manual: VOUCHER user123 3K\n";
    $message .= "Contoh lengkap: VOUCHER user123 3K 08123456789\n";
    $message .= "Voucher akan dikirim ke nomor pembeli dan agent\n\n";
    $message .= "⚡ *VCR [USERNAME] <NAMA_PAKET> [NOMER]*\n";
    $message .= "Perintah singkat untuk VOUCHER\n";
    $message .= "Contoh: VCR 3K, VCR user123 3K 08123456789\n\n";
    $message .= "⚙️ *GENERATE [USERNAME] <NAMA_PAKET> [NOMER]*\n";
    $message .= "Alias untuk VOUCHER\n";
    $message .= "Contoh: GENERATE 3K, GENERATE user123 3K 08123456789\n\n";
    $message .= "👤 *MEMBER <NAMA_PAKET>*\n";
    $message .= "Membeli member (Username ≠ Password)\n";
    $message .= "Contoh: MEMBER 3K\n\n";
    $message .= "🛒 *BELI <NAMA_PAKET>*\n";
    $message .= "Membeli voucher (menggunakan setting default)\n";
    $message .= "Contoh: BELI 1JAM\n\n";
    $message .= "🔍 *CARIPERANGKAT <NOMOR|USERNAME>*\n";
    $message .= "Cari Device ID dari nomor telepon atau username PPPoE\n";
    $message .= "Contoh: CARIPERANGKAT 081234567890\n";
    $message .= "Contoh: CARIPERANGKAT user123\n\n";
    $message .= "📡 *GANTI WIFI <SSID_BARU>*\n";
    $message .= "Ubah WiFi SSID ONU Anda (untuk pelanggan terdaftar)\n";
    $message .= "Contoh: GANTI WIFI ALIJAYA-GUEST\n\n";
    $message .= "📡 *GANTI WIFI <NOMOR/USERNAME> <SSID_BARU>*\n";
    $message .= "Ubah WiFi SSID Pelanggan (Admin)\n";
    $message .= "Contoh: GANTI WIFI 081234567890 ALIJAYA-NET\n\n";
    $message .= "🔐 *GANTI SANDI <PASSWORD_BARU>*\n";
    $message .= "Ubah WiFi Password ONU Anda (untuk pelanggan terdaftar)\n";
    $message .= "Contoh: GANTI SANDI password123456\n\n";
    $message .= "🔐 *GANTI SANDI <NOMOR/USERNAME> <PASSWORD_BARU>*\n";
    $message .= "Ubah WiFi Password Pelanggan (Admin)\n";
    $message .= "Contoh: GANTI SANDI 081234567890 password123456\n\n";
    
    $message .= "💳 *TAGIHAN <NAMA/HP>*\n";
    $message .= "Cek tagihan pelanggan billing\n";
    $message .= "Contoh: TAGIHAN 081234567890\n";
    $message .= "Contoh: TAGIHAN John Doe\n\n";
    
    $message .= "💰 *BAYAR <NAMA/HP> [PERIODE]*\n";
    $message .= "Bayar tagihan pelanggan (Admin/Agent)\n";
    $message .= "Contoh: BAYAR 081234567890\n";
    $message .= "Contoh: BAYAR John Doe 2025-12\n\n";
    
    $message .= "📝 *REG <NOMOR_HP>*\n";
    $message .= "Registrasi nomor agent/pelanggan\n";
    $message .= "Contoh: REG 081234567890\n\n";
    
    // Admin-only commands
    $isAdmin = isWhatsAppAdmin($phone);
    if ($isAdmin) {
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "*🔐 PERINTAH ADMIN*\n\n";
        $message .= "➕ *TAMBAH <username> <password> <profile>*\n";
        $message .= "Tambah PPPoE Secret\n";
        $message .= "Contoh: TAMBAH user123 pass123 profile1\n\n";
        $message .= "✏️ *EDIT <username> <profile_baru>*\n";
        $message .= "Edit profile PPPoE Secret\n";
        $message .= "Contoh: EDIT user123 profile2\n\n";
        $message .= "🗑️ *HAPUS <username>*\n";
        $message .= "Hapus PPPoE Secret\n";
        $message .= "Contoh: HAPUS user123\n\n";
        $message .= "📡 *PING* atau *CEK PING*\n";
        $message .= "Test koneksi ke MikroTik\n\n";
        $message .= "📊 *STATUS* atau *CEK*\n";
        $message .= "Cek status MikroTik (Identity, Version, Uptime)\n\n";
        $message .= "🔌 *PPPOE* atau *PPP*\n";
        $message .= "Cek koneksi PPPoE aktif\n\n";
        $message .= "💻 *RESOURCE* atau *RES*\n";
        $message .= "Cek resource MikroTik (CPU, RAM, Disk)\n\n";
        $message .= "🔒 *DISABLE PPPOE <username>*\n";
        $message .= "Disable PPPoE Secret\n";
        $message .= "Contoh: DISABLE PPPOE user123\n\n";
        $message .= "🔓 *ENABLE PPPOE <username>*\n";
        $message .= "Enable PPPoE Secret\n";
        $message .= "Contoh: ENABLE PPPOE user123\n\n";
        $message .= "🔒 *DISABLE HOTSPOT <username>*\n";
        $message .= "Disable Hotspot User\n";
        $message .= "Contoh: DISABLE HOTSPOT user123\n\n";
        $message .= "🔓 *ENABLE HOTSPOT <username>*\n";
        $message .= "Enable Hotspot User\n";
        $message .= "Contoh: ENABLE HOTSPOT user123\n\n";
        $message .= "📴 *PPPOE OFFLINE* atau *PPP OFFLINE*\n";
        $message .= "Cek PPPoE yang tidak terkoneksi\n\n";
        $message .= "💰 *SALDO DIGIFLAZZ*\n";
        $message .= "Cek saldo Digiflazz saat ini\n";
        $message .= "Contoh: SALDO DIGIFLAZZ\n\n";
    }
    
    $message .= "❓ *HELP*\n";
    $message .= "Menampilkan bantuan ini\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "_Hubungi admin jika ada kendala_";
    
    sendWhatsAppMessage($phone, $message);
}

/**
 * Log webhook data
 */
function logWebhook($data) {
    $logFile = '../logs/webhook_log.txt';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log Rotation: Check if file exists and is larger than 10MB
    if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
        rename($logFile, $logFile . '.bak');
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | " . $data . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Log webhook error
 */
function logWebhookError($phone, $command, $error) {
    $logFile = '../logs/webhook_error_log.txt';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | Phone: $phone | Command: $command | Error: $error\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Purchase Digiflazz product (pulsa, data, e-money, games)
 * Supports both admin (no balance deduction) and agent (with balance deduction)
 */
function purchaseDigiflazz($phone, $sku, $customerNo) {
    // Load required classes
    if (!class_exists('DigiflazzClient')) {
        require_once('../lib/DigiflazzClient.class.php');
    }
    if (!class_exists('Agent')) {
        require_once('../lib/Agent.class.php');
    }
    
    try {
        // Initialize Digiflazz client
        $digiflazz = new DigiflazzClient();
        
        if (!$digiflazz->isEnabled()) {
            sendWhatsAppMessage($phone, "❌ *DIGIFLAZZ TIDAK AKTIF*\n\nLayanan Digiflazz belum dikonfigurasi.\nHubungi administrator.");
            return;
        }
        
        // Get product by SKU
        $product = getDigiflazzProductBySKU($sku);
        
        if (!$product) {
            sendWhatsAppMessage($phone, "❌ *PRODUK TIDAK DITEMUKAN*\n\nSKU: `{$sku}`\n\nPastikan kode SKU benar.\nKetik HELP untuk daftar SKU.");
            return;
        }
        
        // Clean customer number (remove non-digit characters only, keep original format)
        $customerNo = preg_replace('/[^0-9]/', '', $customerNo);
        
        // Validate minimum length
        if (strlen($customerNo) < 10) {
            sendWhatsAppMessage($phone, "❌ *NOMOR TIDAK VALID*\n\nNomor: {$customerNo}\n\nPastikan nomor tujuan benar.");
            return;
        }
        
        // Check if user is admin or agent
        $isAdmin = isAdminNumber($phone);
        $agent = null;
        $agentId = null;
        
        if (!$isAdmin) {
            // Try to get agent by phone
            $agent = getAgentByPhone($phone);
            
            if (!$agent) {
                sendWhatsAppMessage($phone, "❌ *AKSES DITOLAK*\n\nFitur ini hanya untuk Admin & Agent.\n\nHubungi administrator untuk registrasi agent.");
                return;
            }
            
            $agentId = $agent['id'];
            
            // Check agent status
            if ($agent['status'] !== 'active') {
                sendWhatsAppMessage($phone, "❌ *AKUN TIDAK AKTIF*\n\nAkun agent Anda tidak aktif.\nHubungi administrator.");
                return;
            }
        }
        
        // Calculate price
        $digiflazzSettings = $digiflazz->getSettings();
        $defaultMarkup = isset($digiflazzSettings['default_markup_nominal']) ? (int)$digiflazzSettings['default_markup_nominal'] : 0;
        
        $basePrice = (int)$product['price'];
        if ($basePrice <= 0 && isset($product['buyer_price'])) {
            $basePrice = (int)$product['buyer_price'];
        }
        
        $sellPrice = $basePrice;
        
        // Apply markup for agent only
        if (!$isAdmin) {
            if (!empty($product['seller_price']) && (int)$product['seller_price'] > 0) {
                $sellPrice = (int)$product['seller_price'];
            } elseif ($defaultMarkup > 0) {
                $sellPrice = $basePrice + $defaultMarkup;
            }
            
            if ($sellPrice < $basePrice) {
                $sellPrice = $basePrice;
            }
            
            // Check agent balance
            if ($agent['balance'] < $sellPrice) {
                $balanceFormatted = number_format($agent['balance'], 0, ',', '.');
                $priceFormatted = number_format($sellPrice, 0, ',', '.');
                sendWhatsAppMessage($phone, "❌ *SALDO TIDAK MENCUKUPI*\n\n💳 Saldo Anda: Rp {$balanceFormatted}\n💰 Total Bayar: Rp {$priceFormatted}\n\n📊 Silakan topup saldo terlebih dahulu.");
                return;
            }
        }
        
        // Generate ref_id
        $refIdPrefix = $isAdmin ? 'DFADM' : 'DFAG' . ($agent['agent_code'] ?? $agentId);
        $refId = $digiflazz->generateRefId($refIdPrefix);
        
        // Create transaction payload
        $payload = [
            'buyer_sku_code' => $product['buyer_sku_code'],
            'customer_no' => $customerNo,
            'ref_id' => $refId
        ];
        
        // Send notification: processing
        $processingMsg = "⏳ *MEMPROSES TRANSAKSI*\n\n";
        $processingMsg .= "📦 Produk: {$product['product_name']}\n";
        $processingMsg .= "📱 Nomor: {$customerNo}\n";
        $processingMsg .= "💰 Harga: Rp " . number_format($sellPrice, 0, ',', '.') . "\n\n";
        $processingMsg .= "⏱️ Mohon tunggu...";
        sendWhatsAppMessage($phone, $processingMsg);
        
        // Execute Digiflazz transaction
        $digiflazzResponse = $digiflazz->createTransactionWithRetry($payload);
        
        // Parse response
        $digiflazzData = $digiflazzResponse;
        if (isset($digiflazzResponse['data']) && is_array($digiflazzResponse['data'])) {
            $digiflazzData = $digiflazzResponse['data'];
        }
        
        $finalRefId = $digiflazzData['ref_id'] ?? $refId;
        $status = strtolower($digiflazzData['status'] ?? 'pending');
        $serialNumber = $digiflazzData['sn'] ?? ($digiflazzData['serial_number'] ?? '');
        $message = $digiflazzData['message'] ?? '';
        
        // Check if transaction failed
        $failureStatuses = ['failed', 'fail', 'gagal', 'refund', 'refunded', 'cancel', 'cancelled', 'canceled', 'expired', 'error'];
        $isFailure = in_array($status, $failureStatuses, true);
        
        // Deduct balance for agent (only if not failed)
        $transactionId = null;
        $balanceBefore = 0;
        $balanceAfter = 0;
        
        if (!$isFailure) {
            if ($isAdmin) {
                // Admin: no balance deduction, record with amount = 0
                if (function_exists('getDBConnection')) {
                    try {
                        $db = getDBConnection();
                        $stmt = $db->prepare("INSERT INTO agent_transactions (
                            agent_id, transaction_type, amount, balance_before, balance_after, 
                            profile_name, voucher_username, description, reference_id, created_at
                        ) VALUES (
                            1, 'digiflazz_admin', 0, 0, 0, :profile_name, :ref_id, :description, :ref_id, NOW()
                        )");
                        $stmt->execute([
                            ':profile_name' => $product['product_name'],
                            ':ref_id' => $finalRefId,
                            ':description' => 'Digiflazz order (Admin): ' . $product['product_name']
                        ]);
                        $transactionId = $db->lastInsertId();
                    } catch (Exception $e) {
                        error_log("Error recording admin Digiflazz transaction: " . $e->getMessage());
                    }
                }
            } else {
                // Agent: deduct balance
                $agentClass = new Agent();
                $deductResult = $agentClass->deductBalance(
                    $agentId,
                    $sellPrice,
                    $product['product_name'],
                    $finalRefId,
                    'Digiflazz order: ' . $product['product_name'],
                    'digiflazz'
                );
                
                if (!$deductResult['success']) {
                    sendWhatsAppMessage($phone, "❌ *GAGAL POTONG SALDO*\n\n" . $deductResult['message']);
                    return;
                }
                
                $transactionId = $deductResult['transaction_id'];
                $balanceBefore = $deductResult['balance_before'];
                $balanceAfter = $deductResult['balance_after'];
            }
        }
        
        // Save to digiflazz_transactions table
        if (function_exists('getDBConnection')) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("INSERT INTO digiflazz_transactions (
                    agent_id, ref_id, buyer_sku_code, customer_no, status, message, 
                    price, sell_price, serial_number, response, created_at
                ) VALUES (
                    :agent_id, :ref_id, :sku, :customer_no, :status, :message, 
                    :price, :sell_price, :serial, :response, NOW()
                )");
                
                $stmt->execute([
                    ':agent_id' => $isAdmin ? 1 : $agentId,
                    ':ref_id' => $finalRefId,
                    ':sku' => $product['buyer_sku_code'],
                    ':customer_no' => $customerNo,
                    ':status' => $status,
                    ':message' => $message,
                    ':price' => $basePrice,
                    ':sell_price' => $sellPrice,
                    ':serial' => $serialNumber,
                    ':response' => json_encode($digiflazzResponse)
                ]);
            } catch (Exception $e) {
                error_log("Error saving Digiflazz transaction: " . $e->getMessage());
            }
        }
        
        // Log before sending result (for debugging)
        error_log("Digiflazz Result - Status: {$status}, Message: {$message}, Serial: {$serialNumber}, IsAdmin: " . ($isAdmin ? 'Yes' : 'No') . ", BalanceAfter: {$balanceAfter}");
        
        // Send result notification
        sendDigiflazzResult($phone, $product, $customerNo, $sellPrice, $serialNumber, $status, $message, $isAdmin, $balanceAfter, $finalRefId);
        
        // Log transaction
        logWhatsAppTransaction($phone, $finalRefId, 'SUCCESS', json_encode(['sku' => $sku, 'status' => $status]));
        
    } catch (Exception $e) {
        error_log("Digiflazz purchase error: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ *TRANSAKSI GAGAL*\n\n" . $e->getMessage() . "\n\nSilakan coba lagi atau hubungi administrator.");
        logWebhookError($phone, "PULSA {$sku} {$customerNo}", $e->getMessage());
    }
}

/**
 * Get Digiflazz product by SKU code (case-insensitive)
 */
function getDigiflazzProductBySKU($sku) {
    if (!function_exists('getDBConnection')) {
        return null;
    }
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM digiflazz_products 
                              WHERE LOWER(buyer_sku_code) = LOWER(:sku) 
                              AND status = 'active' 
                              LIMIT 1");
        $stmt->execute([':sku' => $sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $product ?: null;
    } catch (Exception $e) {
        error_log("Error getting Digiflazz product: " . $e->getMessage());
        return null;
    }
}

/**
 * Get agent by phone number
 */
function getAgentByPhone($phone) {
    if (!function_exists('getDBConnection')) {
        return null;
    }
    
    try {
        $db = getDBConnection();
        
        // Try exact match first
        $stmt = $db->prepare("SELECT * FROM agents WHERE phone = :phone LIMIT 1");
        $stmt->execute([':phone' => $phone]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agent) {
            return $agent;
        }
        
        // Try with different formats (remove leading 62, 0, +62)
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
        error_log("Error getting agent by phone: " . $e->getMessage());
        return null;
    }
}

/**
 * Send Digiflazz transaction result via WhatsApp
 */
function sendDigiflazzResult($phone, $product, $customerNo, $price, $serialNumber, $status, $message, $isAdmin, $balanceAfter, $refId = '') {
    // Load voucher settings for header and footer
    $voucherSettings = loadVoucherSettings();
    
    $statusIcon = '✅';
    $statusText = 'BERHASIL';
    
    if (in_array(strtolower($status), ['pending', 'process'])) {
        $statusIcon = '⏳';
        $statusText = 'DIPROSES';
    } elseif (in_array(strtolower($status), ['failed', 'gagal', 'error'])) {
        $statusIcon = '❌';
        $statusText = 'GAGAL';
    }
    
    // Header from settings
    $resultMsg = $voucherSettings['header'] . "\n\n";
    
    $resultMsg .= "{$statusIcon} *TRANSAKSI {$statusText}*\n\n";
    $resultMsg .= "Halo!\n";
    $resultMsg .= "Transaksi pembayaran digital Anda telah ";
    
    if ($statusText === 'BERHASIL') {
        $resultMsg .= "berhasil diproses.\n\n";
    } elseif ($statusText === 'DIPROSES') {
        $resultMsg .= "sedang diproses.\n\n";
    } else {
        $resultMsg .= "gagal.\n\n";
    }
    
    $resultMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $resultMsg .= "Produk : *{$product['product_name']}*\n";
    $resultMsg .= "Nomor  : {$customerNo}\n";
    
    if (!empty($message)) {
        $resultMsg .= "Pesan  : {$message}\n";
    }
    
    if (!empty($refId)) {
        $resultMsg .= "Ref ID : {$refId}\n";
    }
    
    if (!empty($serialNumber)) {
        $resultMsg .= "SN     : {$serialNumber}\n";
    }
    
    $resultMsg .= "Biaya  : Rp " . number_format($price, 0, ',', '.') . "\n\n";
    
    // Show balance for agent
    if (!$isAdmin && $balanceAfter > 0) {
        $resultMsg .= "💳 Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n\n";
    }
    
    $resultMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $resultMsg .= "Terima kasih telah menggunakan layanan kami.\n";
    
    // Footer from settings
    $resultMsg .= $voucherSettings['footer'];
    
    // Log before sending (for debugging)
    error_log("Sending Digiflazz result to {$phone}: Status={$statusText}, Length=" . strlen($resultMsg));
    
    sendWhatsAppMessage($phone, $resultMsg);
}

/**
 * Check Digiflazz Balance (Admin Only)
 */
function checkDigiflazzBalance($phone) {
    // Load required classes
    if (!class_exists('DigiflazzClient')) {
        require_once('../lib/DigiflazzClient.class.php');
    }
    
    try {
        // Initialize Digiflazz client
        $digiflazz = new DigiflazzClient();
        
        if (!$digiflazz->isEnabled()) {
            sendWhatsAppMessage($phone, "❌ *DIGIFLAZZ TIDAK AKTIF*\n\nLayanan Digiflazz belum dikonfigurasi atau sedang dinonaktifkan.");
            return;
        }
        
        // Check balance
        $balanceData = $digiflazz->checkBalance();
        
        if (!$balanceData['success']) {
            sendWhatsAppMessage($phone, "❌ *GAGAL CEK SALDO*\n\nTidak dapat mengambil data saldo dari Digiflazz.\nSilakan coba lagi nanti.");
            return;
        }
        
        $balance = $balanceData['balance'];
        $timestamp = date('d/m/Y H:i:s');
        
        // Format message
        $message = "💰 *SALDO DIGIFLAZZ*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "Saldo Saat Ini:\n";
        $message .= "*Rp " . number_format($balance, 0, ',', '.') . "*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📅 Dicek pada: {$timestamp}\n";
        $message .= "🔄 Ketik *SALDO DIGIFLAZZ* untuk cek ulang";
        
        sendWhatsAppMessage($phone, $message);
        
    } catch (Exception $e) {
        $errorMsg = "❌ *ERROR CEK SALDO*\n\n";
        $errorMsg .= "Terjadi kesalahan saat mengecek saldo Digiflazz.\n\n";
        $errorMsg .= "Error: " . $e->getMessage();
        
        sendWhatsAppMessage($phone, $errorMsg);
        error_log("Digiflazz balance check error: " . $e->getMessage());
    }
}

/**
 * Find and get PPPoE/Hotspot username from WhatsApp phone number
 * Returns username if customer found, null otherwise
 */
function getCustomerUsernameByPhone($phone) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        return null;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        return null;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    try {
        // Check database first for phone lookup
        if (function_exists('getDBConnection')) {
            try {
                $db = getDBConnection();
                if ($db) {
                    // Normalize phone numbers for comparison
                    $phoneVariants = [];
                    $phoneVariants[] = $phone;
                    
                    if (strpos($phone, '62') === 0) {
                        $phoneVariants[] = '0' . substr($phone, 2);
                    }
                    if (strpos($phone, '0') === 0) {
                        $phoneVariants[] = '62' . substr($phone, 1);
                    }
                    
                    // Search in billing_customers
                    foreach ($phoneVariants as $variant) {
                        $stmt = $db->prepare("SELECT username FROM billing_customers WHERE phone = :phone OR phone = :phone2 LIMIT 1");
                        $stmt->execute([':phone' => $variant, ':phone2' => $variant]);
                        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($customer && !empty($customer['username'])) {
                            return $customer['username'];
                        }
                    }
                }
            } catch (Exception $e) {
                // Continue without database
            }
        }
        
        // If not in database, return null
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting customer username: " . $e->getMessage());
        return null;
    }
}

/**
 * Change WiFi SSID for registered customer (auto lookup by phone)
 */
function changeWiFiSSIDByCustomer($phone, $newSSID) {
    // Validate SSID
    if (strlen($newSSID) < 3 || strlen($newSSID) > 32) {
        sendWhatsAppMessage($phone, "❌ *SSID TIDAK VALID*\n\nSSID harus 3-32 karakter.\nSSID Anda: {$newSSID} (" . strlen($newSSID) . " karakter)");
        return;
    }
    
    // Check for special characters
    if (preg_match('/[<>&"\'`]/', $newSSID)) {
        sendWhatsAppMessage($phone, "❌ *SSID TIDAK VALID*\n\nSSID tidak boleh mengandung karakter spesial: < > & \" ' `");
        return;
    }
    
    // Get customer username from phone
    $username = getCustomerUsernameByPhone($phone);
    
    if (!$username) {
        sendWhatsAppMessage($phone, "❌ *AKUN TIDAK TERDAFTAR*\n\nNomor WhatsApp Anda tidak terdaftar sebagai pelanggan.\n\nSilakan hubungi admin atau gunakan perintah:\nCARI PERANGKAT <nomor|username>");
        return;
    }
    
    // Use username as device ID
    changeWiFiSSID($phone, $username, $newSSID);
}

/**
 * Change WiFi Password for registered customer (auto lookup by phone)
 */
function changeWiFiPasswordByCustomer($phone, $newPassword) {
    // Validate password
    if (strlen($newPassword) < 8 || strlen($newPassword) > 32) {
        sendWhatsAppMessage($phone, "❌ *PASSWORD TIDAK VALID*\n\nPassword harus 8-32 karakter.\nPassword Anda: " . str_repeat('*', strlen($newPassword)) . " (" . strlen($newPassword) . " karakter)");
        return;
    }
    
    // Check for special characters
    if (preg_match('/[<>&"\'`]/', $newPassword)) {
        sendWhatsAppMessage($phone, "❌ *PASSWORD TIDAK VALID*\n\nPassword tidak boleh mengandung karakter spesial: < > & \" ' `");
        return;
    }
    
    // Get customer username from phone
    $username = getCustomerUsernameByPhone($phone);
    
    if (!$username) {
        sendWhatsAppMessage($phone, "❌ *AKUN TIDAK TERDAFTAR*\n\nNomor WhatsApp Anda tidak terdaftar sebagai pelanggan.\n\nSilakan hubungi admin atau gunakan perintah:\nCARI PERANGKAT <nomor|username>");
        return;
    }
    
    // Use username as device ID
    changeWiFiPassword($phone, $username, $newPassword);
}

/**
 * Find Device ID by Phone Number or PPPoE Username
 */
function findDeviceByPhoneOrUsername($phone, $query) {
    global $sessionConfig;
    
    $data = $sessionConfig;
    if (empty($data) || !is_array($data)) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nKonfigurasi tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    try {
        // Connect to MikroTik
        $API = new RouterosAPI();
        $API->debug = false;
        
        if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
            sendWhatsAppMessage($phone, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
            return;
        }
        
        $results = [];
        
        // Search in PPPoE secrets by username
        $pppoeSecrets = $API->comm("/ppp/secret/print");
        
        foreach ($pppoeSecrets as $secret) {
            $username = trim($secret['name'] ?? '');
            
            if (empty($username)) continue;
            
            // Check if query matches username
            if (strtolower($username) === strtolower($query)) {
                $results[] = [
                    'type' => 'PPPoE Username',
                    'value' => $username,
                    'profile' => $secret['profile'] ?? 'N/A',
                    'service' => $secret['service'] ?? 'N/A',
                    'disabled' => ($secret['disabled'] ?? 'false') == 'true' ? 'Yes' : 'No'
                ];
            }
        }
        
        // Search in hotspot users
        $hotspotUsers = $API->comm("/ip/hotspot/user/print");
        
        foreach ($hotspotUsers as $huser) {
            $username = trim($huser['name'] ?? '');
            
            if (empty($username)) continue;
            
            // Check if query matches hotspot username
            if (strtolower($username) === strtolower($query)) {
                $results[] = [
                    'type' => 'Hotspot User',
                    'value' => $username,
                    'profile' => $huser['profile'] ?? 'N/A',
                    'server' => $huser['server'] ?? 'N/A',
                    'disabled' => ($huser['disabled'] ?? 'false') == 'true' ? 'Yes' : 'No'
                ];
            }
        }
        
        // Search by phone number - check against comments or custom fields
        // Try to find from database if available
        if (function_exists('getDBConnection')) {
            try {
                $db = getDBConnection();
                if ($db) {
                    // Search in billing customers
                    $stmt = $db->prepare("SELECT * FROM billing_customers WHERE phone LIKE :query OR username LIKE :query LIMIT 10");
                    $queryParam = '%' . $query . '%';
                    $stmt->execute([':query' => $queryParam]);
                    
                    while ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $results[] = [
                            'type' => 'Billing Customer',
                            'phone' => $customer['phone'] ?? 'N/A',
                            'username' => $customer['username'] ?? 'N/A',
                            'status' => $customer['status'] ?? 'N/A',
                            'service_type' => $customer['service_type'] ?? 'N/A'
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Error searching database: " . $e->getMessage());
            }
        }
        
        $API->disconnect();
        
        // Display results
        if (empty($results)) {
            sendWhatsAppMessage($phone, "❌ *TIDAK DITEMUKAN*\n\nTidak ada hasil untuk: `{$query}`\n\nSilakan periksa kembali nomor atau username Anda.");
            return;
        }
        
        $resultMsg = "🔍 *HASIL PENCARIAN: {$query}*\n\n";
        $resultMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $count = 0;
        foreach ($results as $result) {
            $count++;
            if ($count > 10) {
                $resultMsg .= "\n... dan " . (count($results) - 10) . " hasil lainnya";
                break;
            }
            
            $resultMsg .= "📋 *Hasil #" . $count . "*\n";
            $resultMsg .= "Tipe: " . ($result['type'] ?? 'N/A') . "\n";
            
            if ($result['type'] === 'PPPoE Username') {
                $resultMsg .= "Username: `{$result['value']}`\n";
                $resultMsg .= "Profile: {$result['profile']}\n";
                $resultMsg .= "Status: " . ($result['disabled'] === 'Yes' ? '❌ Disabled' : '✅ Active') . "\n";
            } elseif ($result['type'] === 'Hotspot User') {
                $resultMsg .= "Username: `{$result['value']}`\n";
                $resultMsg .= "Profile: {$result['profile']}\n";
                $resultMsg .= "Status: " . ($result['disabled'] === 'Yes' ? '❌ Disabled' : '✅ Active') . "\n";
            } elseif ($result['type'] === 'Billing Customer') {
                $resultMsg .= "Username: `{$result['username']}`\n";
                $resultMsg .= "Phone: {$result['phone']}\n";
                $resultMsg .= "Status: {$result['status']}\n";
                $resultMsg .= "Service: {$result['service_type']}\n";
            }
            
            $resultMsg .= "\n";
        }
        
        $resultMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $resultMsg .= "💡 *Catatan:*\n";
        $resultMsg .= "Untuk mengubah WiFi, gunakan username sebagai DEVICE_ID:\n";
        $resultMsg .= "GANTIWIFI <username> SSID_BARU\n";
        $resultMsg .= "GANTISANDI <username> PASSWORD_BARU\n\n";
        $resultMsg .= "Atau hubungi admin untuk mendapatkan Device ID GenieACS.";
        
        sendWhatsAppMessage($phone, $resultMsg);
        
        // Log transaction
        logWhatsAppTransaction($phone, $query, 'FOUND', json_encode(['results_count' => count($results)]));
        
    } catch (Exception $e) {
        error_log("Find device error: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ *ERROR PENCARIAN*\n\n" . $e->getMessage());
    }
}

/**
 * Change WiFi SSID via GenieACS API (atau gunakan username jika ada mapping)
 */
function changeWiFiSSID($phone, $deviceId, $newSSID) {
    // Validate SSID
    if (strlen($newSSID) < 3 || strlen($newSSID) > 32) {
        sendWhatsAppMessage($phone, "❌ *SSID TIDAK VALID*\n\nSSID harus 3-32 karakter.\nSSID Anda: {$newSSID} (" . strlen($newSSID) . " karakter)");
        return;
    }
    
    // Remove special characters that might break the request
    if (preg_match('/[<>&"\'`]/', $newSSID)) {
        sendWhatsAppMessage($phone, "❌ *SSID TIDAK VALID*\n\nSSID tidak boleh mengandung karakter spesial: < > & \" ' `");
        return;
    }
    
    try {
        // Try using GenieACS API functions (same as web interface)
        if (function_exists('genieacs_create_task')) {
            // Use existing GenieACS function that's already tested
            $task = [
                "name" => "setParameterValues",
                "parameterValues" => [
                    ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $newSSID, 'xsd:string']
                ]
            ];
            
            $result = genieacs_create_task($deviceId, $task, true); // true = connection_request to trigger device connect
            
            if (isset($result['error'])) {
                error_log("WiFi SSID change error (API function): " . $result['error']);
                sendWhatsAppMessage($phone, "❌ *GAGAL UBAH SSID*\n\nError: " . $result['error']);
                return;
            }
            
            // Success
            $successMsg = "✅ *SSID BERHASIL DIUBAH*\n\n";
            $successMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $successMsg .= "📱 Device ID: {$deviceId}\n";
            $successMsg .= "📡 SSID Baru: *{$newSSID}*\n\n";
            $successMsg .= "⏳ Perubahan akan diproses dalam beberapa detik.\n";
            $successMsg .= "Perangkat akan boot ulang otomatis.";
            
            sendWhatsAppMessage($phone, $successMsg);
            
            // Log transaction
            logWhatsAppTransaction($phone, $deviceId, 'SUCCESS', json_encode(['action' => 'change_ssid', 'device_id' => $deviceId, 'new_ssid' => $newSSID]));
            return;
        }
    } catch (Exception $e) {
        error_log("WiFi SSID change (API function) exception: " . $e->getMessage());
        // Fall through to curl method if function doesn't exist
    }
    
    // Fallback: Use direct CURL method (if genieacs_create_task not available)
    try {
        // Call GenieACS API via curl (same pattern as save_wifi.php)
        // Use the endpoint: /devices/{device_id}/tasks?connection_request
        $genieacs_base = 'http://192.168.8.89:7557/api';
        $genieacs_url = $genieacs_base . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';
        
        // Create task payload
        $task_payload = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $newSSID, 'xsd:string']
            ]
        ];
        
        // Initialize curl with proper timeout
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $genieacs_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($task_payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Check for curl errors
        if (!empty($curl_error)) {
            error_log("WiFi SSID change curl error: {$curl_error} (Device: {$deviceId})");
            sendWhatsAppMessage($phone, "⚠️ *PROSES LAMBAT*\n\nGenieACS membutuhkan waktu lebih lama.\n\n🔄 Perintah sedang diproses di server.\nMohon tunggu beberapa saat dan cek status device.\n\nDevice ID: {$deviceId}");
            return;
        }
        
        // Check response - accept 200, 201, 202
        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 202) {
            error_log("WiFi SSID change error: HTTP {$http_code}, Response: {$response} (Device: {$deviceId})");
            sendWhatsAppMessage($phone, "⚠️ *STATUS TIDAK PASTI*\n\nServer merespons kode: {$http_code}\n\nPerubahan mungkin sedang diproses.\n\nDevice ID: {$deviceId}\nSSID Baru: {$newSSID}\n\n⏳ Tunggu 10-15 detik dan cek status device.");
            return;
        }
        
        // Success response
        $successMsg = "✅ *SSID BERHASIL DIUBAH*\n\n";
        $successMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $successMsg .= "📱 Device ID: {$deviceId}\n";
        $successMsg .= "📡 SSID Baru: *{$newSSID}*\n\n";
        $successMsg .= "⏳ Perubahan akan diproses dalam beberapa detik.\n";
        $successMsg .= "Perangkat akan boot ulang otomatis.";
        
        sendWhatsAppMessage($phone, $successMsg);
        
        // Log transaction
        logWhatsAppTransaction($phone, $deviceId, 'SUCCESS', json_encode(['action' => 'change_ssid', 'device_id' => $deviceId, 'new_ssid' => $newSSID]));
        
    } catch (Exception $e) {
        error_log("WiFi SSID change exception: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ *ERROR MENGUBAH SSID*\n\n" . $e->getMessage());
    }
}

/**
 * Change WiFi Password via GenieACS API
 */
function changeWiFiPassword($phone, $deviceId, $newPassword) {
    // Validate password
    if (strlen($newPassword) < 8 || strlen($newPassword) > 32) {
        sendWhatsAppMessage($phone, "❌ *PASSWORD TIDAK VALID*\n\nPassword harus 8-32 karakter.\nPassword Anda: " . str_repeat('*', strlen($newPassword)) . " (" . strlen($newPassword) . " karakter)");
        return;
    }
    
    // Remove special characters that might break the request
    if (preg_match('/[<>&"\'`]/', $newPassword)) {
        sendWhatsAppMessage($phone, "❌ *PASSWORD TIDAK VALID*\n\nPassword tidak boleh mengandung karakter spesial: < > & \" ' `");
        return;
    }
    
    try {
        // Try using GenieACS API functions (same as web interface)
        if (function_exists('genieacs_create_task')) {
            // Use existing GenieACS function that's already tested
            $task = [
                "name" => "setParameterValues",
                "parameterValues" => [
                    // Huawei, ZTE, FiberHome format
                    ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $newPassword, 'xsd:string'],
                    // Alternate format for some manufacturers
                    ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', $newPassword, 'xsd:string']
                ]
            ];
            
            $result = genieacs_create_task($deviceId, $task, true); // true = connection_request to trigger device connect
            
            if (isset($result['error'])) {
                error_log("WiFi password change error (API function): " . $result['error']);
                sendWhatsAppMessage($phone, "❌ *GAGAL UBAH PASSWORD*\n\nError: " . $result['error']);
                return;
            }
            
            // Success
            $successMsg = "✅ *PASSWORD BERHASIL DIUBAH*\n\n";
            $successMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $successMsg .= "📱 Device ID: {$deviceId}\n";
            $successMsg .= "🔐 Password Baru: " . str_repeat('*', strlen($newPassword)) . "\n\n";
            $successMsg .= "⏳ Perubahan akan diproses dalam beberapa detik.\n";
            $successMsg .= "Perangkat akan boot ulang otomatis.";
            
            sendWhatsAppMessage($phone, $successMsg);
            
            // Log transaction
            logWhatsAppTransaction($phone, $deviceId, 'SUCCESS', json_encode(['action' => 'change_password', 'device_id' => $deviceId]));
            return;
        }
    } catch (Exception $e) {
        error_log("WiFi password change (API function) exception: " . $e->getMessage());
        // Fall through to curl method if function doesn't exist
    }
    
    // Fallback: Use direct CURL method (if genieacs_create_task not available)
    try {
        // Call GenieACS API via curl
        $genieacs_base = 'http://192.168.8.89:7557/api';
        $genieacs_url = $genieacs_base . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';
        
        // Create task payload - try multiple password paths for different ONU brands
        $task_payload = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                // Huawei, ZTE, FiberHome format
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $newPassword, 'xsd:string'],
                // Alternate format for some manufacturers
                ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', $newPassword, 'xsd:string']
            ]
        ];
        
        // Initialize curl with longer timeout (30 seconds for GenieACS processing)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $genieacs_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($task_payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);  // Increased from 10 to 30 seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);  // Connection timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Check for curl errors
        if (!empty($curl_error)) {
            // Try to provide more specific error info
            if (strpos($curl_error, 'timed out') !== false) {
                sendWhatsAppMessage($phone, "⚠️ *PROSES LAMBAT*\n\nGenieACS membutuhkan waktu lebih lama.\n\n🔄 Perintah sedang diproses di server.\nMohon tunggu beberapa saat dan cek status device.\n\nDevice ID: {$deviceId}");
            } else {
                sendWhatsAppMessage($phone, "❌ *GAGAL UBAH PASSWORD*\n\nKoneksi ke GenieACS failed:\n{$curl_error}");
            }
            error_log("WiFi password change curl error: {$curl_error} (Device: {$deviceId})");
            return;
        }
        
        // Check response - accept 200, 201, 202
        if ($http_code !== 200 && $http_code !== 201 && $http_code !== 202) {
            error_log("WiFi password change error: HTTP {$http_code}, Response: {$response} (Device: {$deviceId})");
            sendWhatsAppMessage($phone, "⚠️ *STATUS TIDAK PASTI*\n\nServer merespons kode: {$http_code}\n\nPerubahan mungkin sedang diproses.\n\nDevice ID: {$deviceId}\n\n⏳ Tunggu 10-15 detik dan cek status device.");
            return;
        }
        
        // Success response
        $successMsg = "✅ *PASSWORD BERHASIL DIUBAH*\n\n";
        $successMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $successMsg .= "📱 Device ID: {$deviceId}\n";
        $successMsg .= "🔐 Password Baru: " . str_repeat('*', strlen($newPassword)) . "\n\n";
        $successMsg .= "⏳ Perubahan akan diproses dalam beberapa detik.\n";
        $successMsg .= "Perangkat akan boot ulang otomatis.";
        
        sendWhatsAppMessage($phone, $successMsg);
        
        // Log transaction
        logWhatsAppTransaction($phone, $deviceId, 'SUCCESS', json_encode(['action' => 'change_password', 'device_id' => $deviceId]));
        
    } catch (Exception $e) {
        error_log("WiFi password change exception: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ *ERROR MENGUBAH PASSWORD*\n\n" . $e->getMessage());
    }
}

/**
 * Check customer bills via WhatsApp
 */
function checkWhatsAppCustomerBills($phone, $customerIdentifier) {
    try {
        // Load database connection
        if (!function_exists('getDBConnection')) {
            if (file_exists('../include/db_config.php')) {
                require_once('../include/db_config.php');
            } else {
                sendWhatsAppMessage($phone, "❌ Database tidak tersedia.");
                return;
            }
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendWhatsAppMessage($phone, "❌ Koneksi database gagal.");
            return;
        }
        
        // Search customer by name or phone
        $stmt = $db->prepare(
            "SELECT bc.*, bp.profile_name, bp.price_monthly as price " .
            "FROM billing_customers bc " .
            "LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id " .
            "WHERE bc.name LIKE :identifier OR bc.phone LIKE :identifier " .
            "LIMIT 5"
        );
        $stmt->execute([':identifier' => '%' . $customerIdentifier . '%']);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($customers)) {
            sendWhatsAppMessage($phone, "❌ *PELANGGAN TIDAK DITEMUKAN*\n\nTidak ada pelanggan dengan nama atau nomor HP: *$customerIdentifier*");
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
            sendWhatsAppMessage($phone, $message);
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
            
            foreach ($unpaidInvoices as $invoice) {
                $totalUnpaid += $invoice['amount'];
                $dueDate = date('d/m/Y', strtotime($invoice['due_date']));
                $period = date('M Y', strtotime($invoice['period'] . '-01'));
                
                $message .= "📅 *$period*\n";
                $message .= "Jumlah: Rp " . number_format($invoice['amount'], 0, ',', '.') . "\n";
                $message .= "Jatuh tempo: $dueDate\n";
                $message .= "Status: " . ucfirst($invoice['status']) . "\n\n";
            }
            
            $message .= "💰 *Total Tertunggak: Rp " . number_format($totalUnpaid, 0, ',', '.') . "*\n\n";
            $message .= "📝 *Cara bayar:*\n";
            $message .= "• BAYAR {$customer['phone']} - Bayar bulan ini\n";
            $message .= "• BAYAR {$customer['phone']} 2025-12 - Bayar periode tertentu";
        }
        
        sendWhatsAppMessage($phone, $message);
        
    } catch (Exception $e) {
        error_log("Error in checkWhatsAppCustomerBills: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ Terjadi kesalahan saat mengecek tagihan.\n\nSilakan coba lagi.");
    }
}

/**
 * Process bill payment via WhatsApp
 */
function processWhatsAppBillPayment($phone, $customerIdentifier, $period) {
    try {
        // Load database connection
        if (!function_exists('getDBConnection')) {
            if (file_exists('../include/db_config.php')) {
                require_once('../include/db_config.php');
            } else {
                sendWhatsAppMessage($phone, "❌ Database tidak tersedia.");
                return;
            }
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendWhatsAppMessage($phone, "❌ Koneksi database gagal.");
            return;
        }
        
        // Check if user is admin or agent
        $isAdmin = isWhatsAppAdmin($phone);
        $agent = null;
        
        if (!$isAdmin) {
            $agent = getWhatsAppAgentByPhone($phone);
            if (!$agent) {
                sendWhatsAppMessage($phone, "❌ *AKSES DITOLAK*\n\nAnda tidak terdaftar sebagai admin atau agent.");
                return;
            }
        }
        
        // Search customer
        $stmt = $db->prepare(
            "SELECT bc.*, bp.profile_name, bp.price_monthly as price " .
            "FROM billing_customers bc " .
            "LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id " .
            "WHERE bc.name LIKE :identifier OR bc.phone LIKE :identifier " .
            "LIMIT 1"
        );
        $stmt->execute([':identifier' => '%' . $customerIdentifier . '%']);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            sendWhatsAppMessage($phone, "❌ *PELANGGAN TIDAK DITEMUKAN*\n\nTidak ada pelanggan dengan nama atau nomor HP: *$customerIdentifier*");
            return;
        }
        
        // Get invoice
        $stmt = $db->prepare(
            "SELECT * FROM billing_invoices " .
            "WHERE customer_id = :customer_id AND period = :period"
        );
        $stmt->execute([':customer_id' => $customer['id'], ':period' => $period]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            sendWhatsAppMessage($phone, "❌ Tagihan untuk periode $period tidak ditemukan.");
            return;
        }
        
        if ($invoice['status'] === 'paid') {
            sendWhatsAppMessage($phone, "✅ Tagihan periode $period sudah lunas.\n\nDibayar pada: " . date('d/m/Y H:i', strtotime($invoice['paid_at'])));
            return;
        }
        
        // Send processing message
        $processingMsg = "⏳ *MEMPROSES PEMBAYARAN*\n\n";
        $processingMsg .= "👤 Pelanggan: {$customer['name']}\n";
        $processingMsg .= "📅 Periode: $period\n";
        $processingMsg .= "💰 Jumlah: Rp " . number_format($invoice['amount'], 0, ',', '.') . "\n\n";
        $processingMsg .= "🔄 Sedang diproses...";
        sendWhatsAppMessage($phone, $processingMsg);
        
        if ($isAdmin) {
            // Admin payment (no balance deduction)
            $stmt = $db->prepare(
                "UPDATE billing_invoices SET " .
                "status = 'paid', paid_at = NOW(), payment_channel = 'admin_whatsapp', " .
                "reference_number = :ref_number " .
                "WHERE id = :invoice_id"
            );
            $refNumber = 'ADMIN-WA-' . time();
            $stmt->execute([':ref_number' => $refNumber, ':invoice_id' => $invoice['id']]);
            
            $paymentMethod = 'Admin (WhatsApp)';
            
        } else {
            // Agent payment (with balance deduction)
            // Check balance
            if ($agent['balance'] < $invoice['amount']) {
                $reply = "❌ *SALDO TIDAK CUKUP*\n\n";
                $reply .= "Saldo Anda: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
                $reply .= "Dibutuhkan: Rp " . number_format($invoice['amount'], 0, ',', '.') . "\n";
                $reply .= "Kurang: Rp " . number_format($invoice['amount'] - $agent['balance'], 0, ',', '.') . "\n\n";
                $reply .= "Silakan topup saldo terlebih dahulu.";
                sendWhatsAppMessage($phone, $reply);
                return;
            }
            
            // Load Agent class
            if (!class_exists('Agent')) {
                require_once('../lib/Agent.class.php');
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
                    sendWhatsAppMessage($phone, "❌ Gagal memotong saldo: " . $deductResult['message']);
                    return;
                }
                
                // Update invoice status
                $stmt = $db->prepare(
                    "UPDATE billing_invoices SET " .
                    "status = 'paid', paid_at = NOW(), payment_channel = 'agent_whatsapp', " .
                    "reference_number = :ref_number, paid_via_agent_id = :agent_id " .
                    "WHERE id = :invoice_id"
                );
                $refNumber = 'AG-WA-' . $agent['agent_code'] . '-' . time();
                $stmt->execute([
                    ':ref_number' => $refNumber,
                    ':agent_id' => $agent['id'],
                    ':invoice_id' => $invoice['id']
                ]);
                
                // Record agent billing payment
                $stmt = $db->prepare(
                    "INSERT INTO agent_billing_payments (agent_id, invoice_id, amount, status, processed_by, payment_method) " .
                    "VALUES (:agent_id, :invoice_id, :amount, 'paid', 'whatsapp', 'agent_balance')"
                );
                $stmt->execute([
                    ':agent_id' => $agent['id'],
                    ':invoice_id' => $invoice['id'],
                    ':amount' => $invoice['amount']
                ]);
                
                $db->commit();
                $paymentMethod = "Agent {$agent['agent_name']} (WhatsApp)";
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error in agent payment transaction: " . $e->getMessage());
                sendWhatsAppMessage($phone, "❌ Terjadi kesalahan saat memproses pembayaran.");
                return;
            }
        }
        
        // Restore customer profile if isolated
        if ($customer['is_isolated']) {
            restoreWhatsAppCustomerProfile($customer);
        }
        
        // Send success message
        $successMsg = "✅ *PEMBAYARAN BERHASIL*\n\n";
        $successMsg .= "👤 Pelanggan: *{$customer['name']}*\n";
        $successMsg .= "📞 HP: {$customer['phone']}\n";
        $successMsg .= "📅 Periode: *$period*\n";
        $successMsg .= "💰 Jumlah: *Rp " . number_format($invoice['amount'], 0, ',', '.') . "*\n";
        $successMsg .= "💳 Metode: $paymentMethod\n";
        $successMsg .= "📝 Referensi: `$refNumber`\n\n";
        
        if (!$isAdmin && $agent) {
            $updatedAgent = $agentClass->getAgentById($agent['id']);
            $successMsg .= "💳 Saldo tersisa: Rp " . number_format($updatedAgent['balance'], 0, ',', '.') . "\n\n";
        }
        
        if ($customer['is_isolated']) {
            $successMsg .= "✅ *Pelanggan telah dikembalikan ke profile aktif.*\n\n";
        }
        
        $successMsg .= "✨ _Pembayaran telah dicatat dalam sistem._";
        
        sendWhatsAppMessage($phone, $successMsg);
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error in processWhatsAppBillPayment: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ Terjadi kesalahan saat memproses pembayaran.\n\nSilakan coba lagi.");
    }
}

/**
 * Restore customer profile from isolation via WhatsApp
 */
function restoreWhatsAppCustomerProfile($customer) {
    try {
        // Load session config
        global $sessionConfig;
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
        error_log("Error in restoreWhatsAppCustomerProfile: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if WhatsApp user is admin
 */
function isWhatsAppAdmin($phone) {
    // Load admin phone numbers from config or database
    $adminPhones = ['6281947215703', '081947215703']; // Add your admin numbers here
    
    // Remove country code variations for comparison
    $cleanPhone = preg_replace('/^(\+62|62|0)/', '', $phone);
    
    foreach ($adminPhones as $adminPhone) {
        $cleanAdminPhone = preg_replace('/^(\+62|62|0)/', '', $adminPhone);
        if ($cleanPhone === $cleanAdminPhone) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get agent price for specific profile (WhatsApp)
 */
function getWhatsAppAgentPrice($agentId, $profileName) {
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
        error_log("Error in getWhatsAppAgentPrice: " . $e->getMessage());
        return null;
    }
}

/**
 * Get agent by WhatsApp phone number
 */
function getWhatsAppAgentByPhone($phone) {
    try {
        if (!function_exists('getDBConnection')) {
            return null;
        }
        
        $db = getDBConnection();
        if (!$db) {
            return null;
        }
        
        // Clean phone number for comparison
        $cleanPhone = preg_replace('/^(\+62|62|0)/', '', $phone);
        
        $stmt = $db->prepare(
            "SELECT * FROM agents WHERE " .
            "(phone LIKE :phone1 OR phone LIKE :phone2 OR phone LIKE :phone3) " .
            "AND status = 'active'"
        );
        $stmt->execute([
            ':phone1' => '%' . $cleanPhone,
            ':phone2' => '%62' . $cleanPhone,
            ':phone3' => '%0' . $cleanPhone
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error in getWhatsAppAgentByPhone: " . $e->getMessage());
        return null;
    }
}

/**
 * Process WhatsApp agent registration
 */
function processWhatsAppAgentRegistration($phone, $phoneNumber) {
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
            sendWhatsAppMessage($phone, "❌ *NOMOR HP TIDAK VALID*\n\nFormat nomor HP tidak sesuai.\n\n*Contoh format yang benar:*\n• 081234567890\n• 08123456789\n• 6281234567890");
            return;
        }
        
        // Load database connection
        if (!function_exists('getDBConnection')) {
            if (file_exists('../include/db_config.php')) {
                require_once('../include/db_config.php');
            } else {
                sendWhatsAppMessage($phone, "❌ Database tidak tersedia.");
                return;
            }
        }
        
        $db = getDBConnection();
        if (!$db) {
            sendWhatsAppMessage($phone, "❌ Koneksi database gagal.");
            return;
        }
        
        // Send processing message
        sendWhatsAppMessage($phone, "🔍 *MENCARI NOMOR HP...*\n\nMencari nomor: $cleanPhone\n\n⏳ Mohon tunggu...");
        
        // Clean sender phone for comparison
        $senderPhone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($senderPhone, 0, 2) === '62') {
            $senderPhone = '0' . substr($senderPhone, 2);
        }
        
        // Check if this phone is already registered as agent
        $stmt = $db->prepare("SELECT id, agent_name, phone FROM agents WHERE phone = :phone");
        $stmt->execute([':phone' => $senderPhone]);
        $existingAgent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingAgent) {
            sendWhatsAppMessage($phone, "⚠️ *SUDAH TERDAFTAR*\n\nNomor WhatsApp Anda sudah terdaftar sebagai:\n\n👤 **{$existingAgent['agent_name']}**\n📞 {$existingAgent['phone']}\n\nJika ingin mengganti, hubungi administrator.");
            return;
        }
        
        // Search for agent by phone number (flexible matching)
        $stmt = $db->prepare(
            "SELECT id, agent_code, agent_name, phone, status FROM agents " .
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
                "SELECT id, name, phone FROM billing_customers " .
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
                sendWhatsAppMessage($phone, "✅ *REGISTRASI BERHASIL*\n\n🎉 **Selamat datang, {$customer['name']}!**\n\n👤 **Status:** PELANGGAN\n📞 **HP:** {$customer['phone']}\n💬 **WhatsApp:** Terhubung\n\n📋 **Fitur yang tersedia:**\n• TAGIHAN [nama/hp] - Cek tagihan\n• HARGA - Lihat paket\n\n🤖 Akun WhatsApp Anda sekarang terhubung dengan sistem billing.");
                return;
            }
            
            sendWhatsAppMessage($phone, "❌ *NOMOR TIDAK DITEMUKAN*\n\nNomor HP **$cleanPhone** tidak terdaftar dalam sistem.\n\n📝 **Kemungkinan penyebab:**\n• Nomor belum didaftarkan sebagai agent\n• Format nomor tidak sesuai\n• Status agent tidak aktif\n\n💡 **Solusi:**\n• Pastikan nomor HP sudah terdaftar\n• Hubungi administrator untuk registrasi\n• Coba format nomor lain (dengan/tanpa kode negara)");
            return;
        }
        
        // Check if the requesting phone matches the agent phone
        if ($senderPhone !== $cleanPhone && $senderPhone !== ('62' . substr($cleanPhone, 1))) {
            sendWhatsAppMessage($phone, "❌ *NOMOR TIDAK COCOK*\n\nAnda hanya bisa registrasi nomor HP Anda sendiri.\n\n📱 **Nomor WhatsApp Anda:** $senderPhone\n🔍 **Nomor yang dicari:** $cleanPhone\n\n💡 Gunakan: `REG $senderPhone`");
            return;
        }
        
        // Success message
        $message = "✅ *REGISTRASI BERHASIL*\n\n";
        $message .= "🎉 **Selamat datang, {$agent['agent_name']}!**\n\n";
        $message .= "👤 **Status:** AGENT\n";
        $message .= "🏷️ **Kode:** {$agent['agent_code']}\n";
        $message .= "📞 **HP:** {$agent['phone']}\n";
        $message .= "💬 **WhatsApp:** Terhubung\n\n";
        $message .= "🎫 **Fitur Agent:**\n";
        $message .= "• HARGA - Lihat harga agent\n";
        $message .= "• VOUCHER [paket] - Generate voucher\n";
        $message .= "• BAYAR [nama/hp] - Bayar tagihan\n";
        $message .= "• TAGIHAN [nama/hp] - Cek tagihan\n\n";
        $message .= "🤖 **Akun WhatsApp Anda sekarang terhubung dengan sistem agent!**\n\n";
        $message .= "💡 Ketik HARGA untuk melihat daftar paket agent.";
        
        sendWhatsAppMessage($phone, $message);
        
        // Log successful registration
        error_log("WhatsApp agent registration successful: Agent {$agent['agent_name']} (ID: {$agent['id']}) confirmed with phone: $phone");
        
    } catch (Exception $e) {
        error_log("Error in processWhatsAppAgentRegistration: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ Terjadi kesalahan saat registrasi.\n\nSilakan coba lagi atau hubungi administrator.");
    }
}

// Return success response
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);

