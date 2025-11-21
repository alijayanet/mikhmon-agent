<?php
/*
 * Voucher Generator Class
 * Generate username & password based on admin settings
 */

class VoucherGenerator {
    private $db;
    private $settings;
    
    public function __construct() {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        $this->loadSettings();
    }
    
    /**
     * Load voucher settings from database
     */
    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'voucher_%'");
        $this->settings = [];
        
        while ($row = $stmt->fetch()) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Set defaults if not found
        $defaults = [
            'voucher_username_password_same' => '0',
            'voucher_username_type' => 'alphanumeric',
            'voucher_username_length' => '8',
            'voucher_password_type' => 'alphanumeric',
            'voucher_password_length' => '6',
            'voucher_prefix_enabled' => '1',
            'voucher_prefix' => 'AG',
            'voucher_uppercase' => '1'
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }
    }
    
    /**
     * Generate random string based on type
     */
    private function generateRandomString($type, $length) {
        $chars = '';
        
        switch($type) {
            case 'numeric':
                $chars = '0123456789';
                break;
            case 'alpha':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'alphanumeric':
            default:
                $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
        }
        
        $result = '';
        $charsLength = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $result;
    }
    
    /**
     * Generate username
     */
    public function generateUsername() {
        $type = $this->settings['voucher_username_type'];
        $length = intval($this->settings['voucher_username_length']);
        $prefixEnabled = $this->settings['voucher_prefix_enabled'] == '1';
        $prefix = $this->settings['voucher_prefix'];
        $uppercase = $this->settings['voucher_uppercase'] == '1';
        
        // Generate random string
        $username = $this->generateRandomString($type, $length);
        
        // Add prefix if enabled
        if ($prefixEnabled && !empty($prefix)) {
            $username = $prefix . $username;
        }
        
        // Apply case
        if (!$uppercase && $type !== 'numeric') {
            $username = strtolower($username);
        }
        
        return $username;
    }
    
    /**
     * Generate password
     */
    public function generatePassword($username = null) {
        $isSame = $this->settings['voucher_username_password_same'] == '1';
        
        // If username and password should be same
        if ($isSame && $username !== null) {
            return $username;
        }
        
        $type = $this->settings['voucher_password_type'];
        $length = intval($this->settings['voucher_password_length']);
        $uppercase = $this->settings['voucher_uppercase'] == '1';
        
        // Generate random string
        $password = $this->generateRandomString($type, $length);
        
        // Apply case
        if (!$uppercase && $type !== 'numeric') {
            $password = strtolower($password);
        }
        
        return $password;
    }
    
    /**
     * Generate voucher (username + password)
     */
    public function generateVoucher() {
        $username = $this->generateUsername();
        $password = $this->generatePassword($username);
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }
    
    /**
     * Generate multiple vouchers
     */
    public function generateMultipleVouchers($quantity) {
        $vouchers = [];
        $attempts = 0;
        $maxAttempts = $quantity * 10; // Prevent infinite loop
        
        while (count($vouchers) < $quantity && $attempts < $maxAttempts) {
            $voucher = $this->generateVoucher();
            
            // Check for duplicates in current batch
            $isDuplicate = false;
            foreach ($vouchers as $existing) {
                if ($existing['username'] === $voucher['username']) {
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $vouchers[] = $voucher;
            }
            
            $attempts++;
        }
        
        return $vouchers;
    }
    
    /**
     * Check if username exists in MikroTik
     */
    public function isUsernameExists($username, $API) {
        try {
            $users = $API->comm("/ip/hotspot/user/print", [
                "?name" => $username
            ]);
            return !empty($users);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate unique username (check against MikroTik)
     */
    public function generateUniqueUsername($API, $maxAttempts = 10) {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $username = $this->generateUsername();
            
            if (!$this->isUsernameExists($username, $API)) {
                return $username;
            }
            
            $attempts++;
        }
        
        // If still not unique, add timestamp
        return $this->generateUsername() . time();
    }
    
    /**
     * Get current settings
     */
    public function getSettings() {
        return $this->settings;
    }
    
    /**
     * Get setting value
     */
    public function getSetting($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Generate and send voucher for public sales
     * @param int $sale_id - ID from public_sales table
     * @return array - Success status and message
     */
    public function generateAndSend($sale_id) {
        try {
            // Get transaction data
            $stmt = $this->db->prepare("SELECT ps.*, a.agent_code 
                                       FROM public_sales ps
                                       JOIN agents a ON ps.agent_id = a.id
                                       WHERE ps.id = :id");
            $stmt->execute([':id' => $sale_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception('Transaction not found');
            }
            
            if ($transaction['status'] != 'paid') {
                throw new Exception('Transaction not paid');
            }
            
            if (!empty($transaction['voucher_code'])) {
                throw new Exception('Voucher already generated');
            }
            
            // Get MikroTik connection from config
            include_once(__DIR__ . '/../include/config.php');
            include_once(__DIR__ . '/../lib/routeros_api.class.php');
            
            // Find session for this agent
            $session = null;
            foreach ($data as $sess => $sessData) {
                if ($sess != 'mikhmon') {
                    $session = $sess;
                    break;
                }
            }
            
            if (!$session) {
                throw new Exception('MikroTik session not found');
            }
            
            // Connect to MikroTik
            $iphost = explode('!', $data[$session][1])[1];
            $userhost = explode('@|@', $data[$session][2])[1];
            $passwdhost = explode('#|#', $data[$session][3])[1];
            
            $API = new RouterosAPI();
            $API->debug = false;
            
            if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
                throw new Exception('Failed to connect to MikroTik');
            }
            
            // Generate unique username
            $username = $this->generateUniqueUsername($API);
            $password = $this->generatePassword($username);
            
            // Add user to MikroTik
            $API->comm("/ip/hotspot/user/add", [
                "name" => $username,
                "password" => $password,
                "profile" => $transaction['profile_name'],
                "comment" => "up-public sales-" . substr($transaction['transaction_id'], 4)
            ]);
            
            $API->disconnect();
            
            // Update transaction with voucher
            $stmt = $this->db->prepare("UPDATE public_sales SET 
                                       voucher_code = :code,
                                       voucher_password = :pass,
                                       voucher_generated_at = NOW()
                                       WHERE id = :id");
            $stmt->execute([
                ':code' => $username,
                ':pass' => $password,
                ':id' => $sale_id
            ]);
            
            // Send voucher via WhatsApp
            $this->sendVoucherWhatsApp($transaction, $username, $password);
            
            // Update sent timestamp
            $stmt = $this->db->prepare("UPDATE public_sales SET voucher_sent_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $sale_id]);
            
            return [
                'success' => true,
                'message' => 'Voucher generated and sent successfully',
                'voucher_code' => $username,
                'voucher_password' => $password
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send voucher via WhatsApp
     */
    private function sendVoucherWhatsApp($transaction, $username, $password) {
        // Get WhatsApp API settings
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'whatsapp_%'");
        $wa_settings = [];
        while ($row = $stmt->fetch()) {
            $wa_settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $api_url = $wa_settings['whatsapp_api_url'] ?? '';
        $api_key = $wa_settings['whatsapp_api_key'] ?? '';
        
        if (empty($api_url) || empty($api_key)) {
            // WhatsApp not configured, skip
            return false;
        }
        
        // Format message
        $message = "*VOUCHER WiFi*\n\n";
        $message .= "Terima kasih atas pembelian Anda!\n\n";
        $message .= "📦 *Paket:* " . $transaction['profile_name'] . "\n";
        $message .= "💰 *Total:* Rp " . number_format($transaction['total_amount'], 0, ',', '.') . "\n\n";
        $message .= "🔑 *Username:* `" . $username . "`\n";
        $message .= "🔐 *Password:* `" . $password . "`\n\n";
        $message .= "*Cara Menggunakan:*\n";
        $message .= "1. Hubungkan ke WiFi\n";
        $message .= "2. Buka browser\n";
        $message .= "3. Masukkan username & password\n\n";
        $message .= "Voucher berlaku sesuai paket yang dipilih.\n\n";
        $message .= "_Simpan pesan ini untuk referensi Anda._";
        
        // Send via API (Fonnte or similar)
        $phone = $transaction['customer_phone'];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62'
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $api_key
            ]
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return true;
    }
}
