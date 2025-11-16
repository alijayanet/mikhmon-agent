<?php
/*
 * WhatsApp Notification Class
 * Send automated notifications via WhatsApp
 */

class WhatsAppNotification {
    private $db;
    private $messageSettings;
    private $lastSendResult = null;
    
    public function __construct() {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }
        $this->db = getDBConnection();
        $this->loadMessageSettings();
    }
    
    /**
     * Load message settings
     */
    private function loadMessageSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'wa_%'");
        
        $this->messageSettings = [
            'header' => '',
            'footer' => '',
            'business_name' => 'WiFi Hotspot',
            'business_phone' => '08123456789',
            'business_address' => 'Jl. Contoh No. 123'
        ];
        
        while ($row = $stmt->fetch()) {
            switch ($row['setting_key']) {
                case 'wa_message_header':
                    $this->messageSettings['header'] = $row['setting_value'];
                    break;
                case 'wa_message_footer':
                    $this->messageSettings['footer'] = $row['setting_value'];
                    break;
                case 'wa_business_name':
                    $this->messageSettings['business_name'] = $row['setting_value'];
                    break;
                case 'wa_business_phone':
                    $this->messageSettings['business_phone'] = $row['setting_value'];
                    break;
                case 'wa_business_address':
                    $this->messageSettings['business_address'] = $row['setting_value'];
                    break;
            }
        }
    }
    
    /**
     * Format message with header and footer
     */
    private function formatMessage($content) {
        $header = $this->messageSettings['header'];
        $footer = $this->messageSettings['footer'];
        
        // Replace variables
        $footer = str_replace('{business_name}', $this->messageSettings['business_name'], $footer);
        $footer = str_replace('{business_phone}', $this->messageSettings['business_phone'], $footer);
        $footer = str_replace('{business_address}', $this->messageSettings['business_address'], $footer);
        
        $message = '';
        if (!empty($header)) {
            $message .= $header . "\n\n";
        }
        $message .= $content;
        if (!empty($footer)) {
            $message .= "\n" . $footer;
        }
        
        return $message;
    }
    
    /**
     * Send WhatsApp message
     */
    private function sendMessage($phone, $message) {
        if (!function_exists('sendWhatsAppMessage')) {
            require_once(__DIR__ . '/../include/whatsapp_config.php');
        }
        $this->lastSendResult = sendWhatsAppMessage($phone, $message);
        return $this->lastSendResult;
    }

    /**
     * Send plain WhatsApp message without additional formatting helpers.
     */
    public function sendPlainMessage(string $phone, string $message): bool
    {
        if (trim($phone) === '' || trim($message) === '') {
            return false;
        }

        $result = $this->sendMessage($phone, $message);

        if (is_bool($result)) {
            return $result;
        }

        if (is_array($result)) {
            if (isset($result['success'])) {
                return (bool)$result['success'];
            }
            if (isset($result['status'])) {
                $status = strtolower((string)$result['status']);
                return in_array($status, ['ok', 'success', 'sent', 'true', '1'], true);
            }
        }

        if (is_numeric($result)) {
            return (bool)$result;
        }

        if (is_string($result)) {
            $normalized = strtolower(trim($result));
            if (in_array($normalized, ['ok', 'success', 'sent', 'true', '1'], true)) {
                return true;
            }
        }

        return !empty($result);
    }

    public function getLastSendResult()
    {
        return $this->lastSendResult;
    }
    
    /**
     * Notify agent about low balance
     */
    public function notifyLowBalance($agentId, $currentBalance, $threshold) {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        $content = "⚠️ *PERINGATAN SALDO RENDAH*\n\n";
        $content .= "Halo *{$agent['agent_name']}*,\n\n";
        $content .= "Saldo Anda saat ini:\n";
        $content .= "💰 Rp " . number_format($currentBalance, 0, ',', '.') . "\n\n";
        $content .= "Batas minimum: Rp " . number_format($threshold, 0, ',', '.') . "\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Silakan lakukan topup agar dapat terus generate voucher.\n\n";
        $content .= "Cara topup:\n";
        $content .= "Ketik: *TOPUP <JUMLAH>*\n";
        $content .= "Contoh: TOPUP 100000";
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $message);
    }
    
    /**
     * Notify admin about topup request
     */
    public function notifyTopupRequest($agentId, $amount, $paymentProof = '') {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        // Get admin numbers
        $adminNumbers = $this->getAdminNumbers();
        if (empty($adminNumbers)) return false;
        
        $content = "🔔 *TOPUP REQUEST BARU*\n\n";
        $content .= "Agent: *{$agent['agent_name']}*\n";
        $content .= "Kode: {$agent['agent_code']}\n";
        $content .= "Phone: {$agent['phone']}\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Jumlah Topup:\n";
        $content .= "💰 *Rp " . number_format($amount, 0, ',', '.') . "*\n\n";
        $content .= "Saldo Saat Ini:\n";
        $content .= "Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        if (!empty($paymentProof)) {
            $content .= "Bukti Transfer: ✅\n\n";
        }
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Silakan proses topup via admin panel.";
        
        $message = $this->formatMessage($content);
        
        // Send to all admin numbers
        $results = [];
        foreach ($adminNumbers as $adminPhone) {
            $results[] = $this->sendMessage($adminPhone, $message);
        }
        
        return in_array(true, $results);
    }
    
    /**
     * Notify customer about expired voucher
     */
    public function notifyVoucherExpired($phone, $username, $profileName) {
        $content = "⏰ *VOUCHER EXPIRED*\n\n";
        $content .= "Voucher Anda telah expired:\n\n";
        $content .= "Username: `$username`\n";
        $content .= "Profile: *$profileName*\n\n";
        $content .= "Silakan hubungi admin untuk perpanjangan.";
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($phone, $message);
    }
    
    /**
     * Notify topup approved
     */
    public function notifyTopupApproved($agentId, $amount, $newBalance) {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        $content = "✅ *TOPUP BERHASIL*\n\n";
        $content .= "Halo *{$agent['agent_name']}*,\n\n";
        $content .= "Topup Anda telah diproses!\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Jumlah Topup:\n";
        $content .= "💰 Rp " . number_format($amount, 0, ',', '.') . "\n\n";
        $content .= "Saldo Baru:\n";
        $content .= "💵 *Rp " . number_format($newBalance, 0, ',', '.')  . "*\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Sekarang Anda dapat generate voucher.\n";
        $content .= "Ketik: *GEN <PROFILE> <QTY>*";
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $message);
    }
    
    /**
     * Send sales report
     */
    public function sendSalesReport($agentId, $period = 'today') {
        $agent = $this->getAgent($agentId);
        if (!$agent) return false;
        
        $stats = $this->getSalesStats($agentId, $period);
        
        $periodText = $period == 'today' ? 'Hari Ini' : ($period == 'week' ? 'Minggu Ini' : 'Bulan Ini');
        
        $content = "📊 *LAPORAN PENJUALAN*\n";
        $content .= "*$periodText*\n\n";
        $content .= "Agent: {$agent['agent_name']}\n";
        $content .= "Kode: {$agent['agent_code']}\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "📈 *Statistik:*\n\n";
        $content .= "• Voucher Terjual: {$stats['total_vouchers']}\n";
        $content .= "• Total Penjualan: Rp " . number_format($stats['total_sales'], 0, ',', '.') . "\n";
        $content .= "• Total Profit: Rp " . number_format($stats['total_profit'], 0, ',', '.') . "\n\n";
        
        if (!empty($stats['by_profile'])) {
            $content .= "📋 *Per Profile:*\n\n";
            foreach ($stats['by_profile'] as $profile) {
                $content .= "• {$profile['profile']}: {$profile['count']} voucher\n";
            }
        }
        
        $content .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "💰 Saldo Saat Ini:\n";
        $content .= "Rp " . number_format($agent['balance'], 0, ',', '.');
        
        $message = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $message);
    }
    
    /**
     * Broadcast message to customers
     */
    public function broadcastToCustomers($agentId, $messageContent) {
        // Get customers who bought from this agent
        $stmt = $this->db->prepare("
            SELECT DISTINCT customer_phone, customer_name 
            FROM agent_vouchers 
            WHERE agent_id = ? AND customer_phone IS NOT NULL AND customer_phone != ''
        ");
        $stmt->execute([$agentId]);
        $customers = $stmt->fetchAll();
        
        if (empty($customers)) return ['success' => false, 'message' => 'Tidak ada customer'];
        
        $content = "📢 *BROADCAST MESSAGE*\n\n";
        $content .= $messageContent;
        
        $message = $this->formatMessage($content);
        
        $successCount = 0;
        foreach ($customers as $customer) {
            if ($this->sendMessage($customer['customer_phone'], $message)) {
                $successCount++;
            }
            usleep(500000); // Delay 0.5 detik antar pesan
        }
        
        return [
            'success' => true,
            'total' => count($customers),
            'sent' => $successCount
        ];
    }
    
    /**
     * Notify agent about successful Digiflazz transaction
     */
    public function notifyDigiflazzSuccess($agentId, array $transactionData, $balanceAfter = null) {
        $agent = $this->getAgent($agentId);
        if (!$agent) {
            return false;
        }

        $productName = $transactionData['product_name'] ?? '-';
        $customerNo = $transactionData['customer_no'] ?? '-';
        $customerName = $transactionData['customer_name'] ?? '';
        $statusRaw = strtoupper($transactionData['status'] ?? 'PENDING');
        $message = $transactionData['message'] ?? '';
        $refId = $transactionData['ref_id'] ?? '-';
        $serialNumber = $transactionData['serial_number'] ?? '';
        $price = (int)($transactionData['price'] ?? 0);

        $icon = '⚡';
        if (in_array($statusRaw, ['SUCCESS', 'SUCCES', 'SUKSES'])) {
            $icon = '✅';
        } elseif (in_array($statusRaw, ['FAILED', 'GAGAL', 'FAILED'])) {
            $icon = '❌';
        } elseif (in_array($statusRaw, ['PENDING', 'PROCESS', 'PROCESSING', 'MENUNGGU'])) {
            $icon = '⏳';
        }

        $content  = "{$icon} *TRANSAKSI DIGIFLAZZ*\n\n";
        $content .= "Halo *{$agent['agent_name']}*, transaksi pembayaran digital telah diproses.\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $content .= "Produk : *{$productName}*\n";
        $content .= "Nomor  : `{$customerNo}`\n";
        if (!empty($customerName)) {
            $content .= "Nama   : {$customerName}\n";
        }
        $content .= "Status : *{$statusRaw}*\n";
        if (!empty($message)) {
            $content .= "Pesan  : {$message}\n";
        }
        $content .= "Ref ID : {$refId}\n";
        $content .= "Biaya  : Rp " . number_format($price, 0, ',', '.') . "\n";
        if (!empty($serialNumber)) {
            $content .= "SN     : `{$serialNumber}`\n";
        }

        $balanceValue = ($balanceAfter !== null) ? $balanceAfter : $agent['balance'];
        $content .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "Saldo tersisa: Rp " . number_format($balanceValue, 0, ',', '.');

        $messageFormatted = $this->formatMessage($content);
        return $this->sendMessage($agent['phone'], $messageFormatted);
    }

    /**
     * Send billing reminder to customer
     */
    public function notifyBillingReminder(string $phone, array $payload): bool
    {
        if (empty($phone)) {
            return false;
        }

        $template = $payload['template'] ?? "Halo {nama},\n\nTagihan WiFi periode {periode} sebesar Rp {jumlah}. Mohon lakukan pembayaran sebelum {jatuh_tempo}.\n\nNomor layanan: {nomor_layanan}";

        $replacements = [
            '{nama}' => $payload['customer_name'] ?? 'Pelanggan',
            '{periode}' => $payload['period'] ?? '-',
            '{jatuh_tempo}' => $payload['due_date'] ?? '-',
            '{jumlah}' => $payload['amount_formatted'] ?? '0',
            '{nomor_layanan}' => $payload['service_number'] ?? '-',
            '{status}' => $payload['status'] ?? '-',
            '{hari}' => $payload['days_remaining'] ?? '0',
            '{portal_url}' => $payload['portal_url'] ?? '-'
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        $message = $this->formatMessage($message);
        return $this->sendMessage($phone, $message);
    }

    /**
     * Notify customer that service is isolated
     */
    public function notifyBillingIsolation(string $phone, array $payload = []): bool
    {
        if (empty($phone)) {
            return false;
        }

        $content  = "⚠️ *LAYANAN DALAM ISOLASI*\n\n";
        $content .= "Halo {nama}, layanan WiFi sementara dinonaktifkan karena tagihan periode {periode} belum dibayar.\n\n";
        $content .= "Mohon lakukan pembayaran sebesar Rp {jumlah} agar layanan dapat diaktifkan kembali.";

        $content = str_replace(
            ['{nama}', '{periode}', '{jumlah}'],
            [
                $payload['customer_name'] ?? 'Pelanggan',
                $payload['period'] ?? '-',
                $payload['amount_formatted'] ?? '0'
            ],
            $content
        );

        $message = $this->formatMessage($content);
        return $this->sendMessage($phone, $message);
    }

    /**
     * Notify customer that service is restored
     */
    public function notifyBillingRestored(string $phone, array $payload = []): bool
    {
        if (empty($phone)) {
            return false;
        }

        $content  = "✅ *LAYANAN AKTIF KEMBALI*\n\n";
        $content .= "Terima kasih {nama}, pembayaran Anda untuk periode {periode} sudah kami terima. Layanan WiFi kini aktif kembali.";

        $content = str_replace(
            ['{nama}', '{periode}'],
            [
                $payload['customer_name'] ?? 'Pelanggan',
                $payload['period'] ?? '-'
            ],
            $content
        );

        $message = $this->formatMessage($content);
        return $this->sendMessage($phone, $message);
    }

    /**
     * Notify customer that invoice has been paid by agent
     */
    public function notifyInvoicePaidByAgent(string $phone, array $payload = []): bool
    {
        if (empty($phone)) {
            return false;
        }

        $content  = "💰 *TAGIHAN DIBAYAR*\n\n";
        $content .= "Halo {nama}, tagihan Anda untuk periode {periode} sebesar Rp {jumlah} telah dibayar oleh agen {agen}.\n\n";
        $content .= "Layanan WiFi Anda kini aktif kembali.";

        $content = str_replace(
            ['{nama}', '{periode}', '{jumlah}', '{agen}'],
            [
                $payload['customer_name'] ?? 'Pelanggan',
                $payload['period'] ?? '-',
                $payload['amount_formatted'] ?? '0',
                $payload['agent_name'] ?? 'Agen'
            ],
            $content
        );

        $message = $this->formatMessage($content);
        return $this->sendMessage($phone, $message);
    }

    /**
     * Get agent data
     */
    private function getAgent($agentId) {
        $stmt = $this->db->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        return $stmt->fetch();
    }
    
    /**
     * Get admin numbers
     */
    private function getAdminNumbers() {
        $stmt = $this->db->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
        $result = $stmt->fetch();
        
        if ($result && !empty($result['setting_value'])) {
            return explode(',', $result['setting_value']);
        }
        
        return [];
    }
    
    /**
     * Get sales statistics
     */
    private function getSalesStats($agentId, $period) {
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = "AND YEARWEEK(created_at) = YEARWEEK(NOW())";
                break;
            case 'month':
                $dateCondition = "AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())";
                break;
        }
        
        // Total stats
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_vouchers,
                SUM(sell_price) as total_sales,
                SUM(sell_price - buy_price) as total_profit
            FROM agent_vouchers 
            WHERE agent_id = ? $dateCondition
        ");
        $stmt->execute([$agentId]);
        $stats = $stmt->fetch();
        
        // By profile
        $stmt = $this->db->prepare("
            SELECT 
                profile_name as profile,
                COUNT(*) as count
            FROM agent_vouchers 
            WHERE agent_id = ? $dateCondition
            GROUP BY profile_name
            ORDER BY count DESC
        ");
        $stmt->execute([$agentId]);
        $stats['by_profile'] = $stmt->fetchAll();
        
        return $stats;
    }
}
