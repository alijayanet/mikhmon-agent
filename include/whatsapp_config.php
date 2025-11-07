<?php
/*
 * WhatsApp Integration for MikhMon
 * Supports multiple WhatsApp Gateway APIs
 */

// ===== KONFIGURASI WHATSAPP GATEWAY =====
// Pilih gateway yang Anda gunakan: 'fonnte', 'wablas', 'woowa', 'custom', 'selfhosted'
define('WHATSAPP_GATEWAY', 'mpwa');

// Konfigurasi untuk Fonnte.com
define('FONNTE_API_URL', 'https://api.fonnte.com/send');
define('FONNTE_TOKEN', 'wik2cbw4NaURsKqycqL6');

// Konfigurasi untuk Wablas.com
define('WABLAS_API_URL', 'https://DOMAIN_ANDA.wablas.com/api/send-message');
define('WABLAS_TOKEN', 'TOKEN_WABLAS_ANDA');

// Konfigurasi untuk WooWA.id
define('WOOWA_API_URL', 'https://api.woowa.id/api/v1/send-message');
define('WOOWA_TOKEN', 'TOKEN_WOOWA_ANDA');

// Konfigurasi MPWA (M-Pedia WhatsApp Gateway)
define('MPWA_API_URL', 'https://wa.alijaya.net/send-message');
define('MPWA_TOKEN', 'JPweqBKzCUX6MqHAIZN9iXK6Y1B9qD');
define('MPWA_SENDER', '6287820851413'); // Nomor WA yang terdaftar di MPWA

// Konfigurasi Custom (sesuaikan dengan gateway Anda)
define('CUSTOM_API_URL', 'URL_GATEWAY_ANDA');
define('CUSTOM_API_TOKEN', 'TOKEN_ANDA');

// Konfigurasi Self-Hosted Gateway (Baileys)
define('SELFHOSTED_API_URL', 'http://localhost:3000/api/send');
define('SELFHOSTED_API_KEY', '1959a60c590d27d8d0d33441fe2ca20b8b93c15e255633f64f6d02de83c4e480');

// Enable/Disable WhatsApp notification
define('WHATSAPP_ENABLED', true);

// Format nomor WhatsApp (true = 62xxx, false = 08xxx)
define('WHATSAPP_FORMAT_62', true);

/**
 * Format nomor WhatsApp
 */
function formatWhatsAppNumber($number) {
    // Hapus karakter non-numeric
    $number = preg_replace('/[^0-9]/', '', $number);
    
    if (WHATSAPP_FORMAT_62) {
        // Format ke 62xxx
        if (substr($number, 0, 1) == '0') {
            $number = '62' . substr($number, 1);
        } elseif (substr($number, 0, 2) != '62') {
            $number = '62' . $number;
        }
    } else {
        // Format ke 08xxx
        if (substr($number, 0, 2) == '62') {
            $number = '0' . substr($number, 2);
        } elseif (substr($number, 0, 1) != '0') {
            $number = '0' . $number;
        }
    }
    
    return $number;
}

/**
 * Kirim pesan WhatsApp
 */
function sendWhatsAppMessage($number, $message) {
    if (!WHATSAPP_ENABLED) {
        return ['success' => false, 'message' => 'WhatsApp notification disabled'];
    }
    
    $number = formatWhatsAppNumber($number);
    
    switch (WHATSAPP_GATEWAY) {
        case 'fonnte':
            return sendViaFonnte($number, $message);
        case 'wablas':
            return sendViaWablas($number, $message);
        case 'woowa':
            return sendViaWoowa($number, $message);
        case 'mpwa':
            return sendViaMPWA($number, $message);
        case 'selfhosted':
            return sendViaSelfHosted($number, $message);
        case 'custom':
            return sendViaCustom($number, $message);
        default:
            return ['success' => false, 'message' => 'Invalid gateway'];
    }
}

/**
 * Kirim via Fonnte.com
 */
function sendViaFonnte($number, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target' => $number,
            'message' => $message,
            'countryCode' => '62'
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . FONNTE_TOKEN
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $result = json_decode($response, true);
    return [
        'success' => ($httpcode == 200 && isset($result['status']) && $result['status'] == true),
        'response' => $result,
        'message' => isset($result['reason']) ? $result['reason'] : 'Unknown error'
    ];
}

/**
 * Kirim via Wablas.com
 */
function sendViaWablas($number, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => WABLAS_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'phone' => $number,
            'message' => $message
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . WABLAS_TOKEN,
            'Content-Type: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $result = json_decode($response, true);
    return [
        'success' => ($httpcode == 200 && isset($result['status']) && $result['status'] == true),
        'response' => $result,
        'message' => isset($result['message']) ? $result['message'] : 'Unknown error'
    ];
}

/**
 * Kirim via WooWA.id
 */
function sendViaWoowa($number, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => WOOWA_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'phone_number' => $number,
            'message' => $message
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WOOWA_TOKEN,
            'Content-Type: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $result = json_decode($response, true);
    return [
        'success' => ($httpcode == 200 && isset($result['success']) && $result['success'] == true),
        'response' => $result,
        'message' => isset($result['message']) ? $result['message'] : 'Unknown error'
    ];
}

/**
 * Kirim via MPWA (M-Pedia WhatsApp Gateway)
 */
function sendViaMPWA($number, $message) {
    $curl = curl_init();
    
    // MPWA API endpoint - URL sudah include /send-message
    $url = MPWA_API_URL;
    
    // MPWA menggunakan api_key di body, bukan Authorization header
    $payload = [
        'api_key' => MPWA_TOKEN,      // API Key di body
        'sender' => MPWA_SENDER,       // Nomor WA yang terdaftar di MPWA
        'number' => $number,           // Nomor tujuan
        'message' => $message          // Pesan
    ];
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return [
            'success' => false,
            'message' => 'Connection error: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    
    // Debug: tampilkan full response jika ada error
    if ($httpcode != 200 || !isset($result['status']) || $result['status'] != true) {
        $errorMsg = 'HTTP ' . $httpcode . ': ';
        if (isset($result['message'])) {
            $errorMsg .= $result['message'];
        } elseif (isset($result['error'])) {
            $errorMsg .= $result['error'];
        } elseif (isset($result['data'])) {
            $errorMsg .= json_encode($result['data']);
        } else {
            $errorMsg .= $response;
        }
        
        return [
            'success' => false,
            'response' => $result,
            'message' => $errorMsg
        ];
    }
    
    // MPWA response format
    return [
        'success' => true,
        'response' => $result,
        'message' => isset($result['message']) ? $result['message'] : 'Message sent successfully'
    ];
}

/**
 * Kirim via Self-Hosted Gateway (Baileys)
 */
function sendViaSelfHosted($number, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SELFHOSTED_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'phone' => $number,
            'message' => $message
        ]),
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . SELFHOSTED_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return [
            'success' => false,
            'message' => 'Connection error: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    return [
        'success' => ($httpcode == 200 && isset($result['success']) && $result['success']),
        'response' => $result,
        'message' => isset($result['message']) ? $result['message'] : 'Unknown error'
    ];
}

/**
 * Kirim via Custom Gateway
 */
function sendViaCustom($number, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => CUSTOM_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'phone' => $number,
            'message' => $message
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . CUSTOM_API_TOKEN,
            'Content-Type: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    $result = json_decode($response, true);
    return [
        'success' => ($httpcode == 200),
        'response' => $result,
        'message' => 'Custom gateway response'
    ];
}

/**
 * Format pesan voucher untuk WhatsApp
 */
function formatVoucherMessage($data) {
    $message = "*🎫 VOUCHER WIFI HOTSPOT*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "*Hotspot:* " . $data['hotspot_name'] . "\n";
    $message .= "*Profile:* " . $data['profile'] . "\n\n";
    $message .= "*Username:* `" . $data['username'] . "`\n";
    $message .= "*Password:* `" . $data['password'] . "`\n\n";
    
    if (!empty($data['timelimit'])) {
        $message .= "*Time Limit:* " . $data['timelimit'] . "\n";
    }
    if (!empty($data['datalimit'])) {
        $message .= "*Data Limit:* " . $data['datalimit'] . "\n";
    }
    if (!empty($data['validity'])) {
        $message .= "*Validity:* " . $data['validity'] . "\n";
    }
    if (!empty($data['price'])) {
        $message .= "*Harga:* " . $data['price'] . "\n";
    }
    
    $message .= "\n*Login URL:*\n";
    $message .= $data['login_url'] . "\n\n";
    
    if (!empty($data['comment'])) {
        $message .= "*Kode:* " . $data['comment'] . "\n";
    }
    
    $message .= "\n━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "_Terima kasih telah menggunakan layanan kami_\n";
    $message .= "_Voucher ini berlaku sesuai ketentuan yang tertera_";
    
    return $message;
}

/**
 * Kirim notifikasi voucher ke customer
 */
function sendVoucherNotification($phone, $voucherData) {
    $message = formatVoucherMessage($voucherData);
    return sendWhatsAppMessage($phone, $message);
}

/**
 * Log WhatsApp transaction
 */
function logWhatsAppTransaction($phone, $username, $status, $response = '') {
    $logFile = '../logs/whatsapp_log.txt';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | Phone: $phone | User: $username | Status: $status | Response: $response\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
} 