<?php
/*
 * Digiflazz Client
 * Handle API integration for digital products (pulsa, data, utilities)
 */

class DigiflazzClient {
    private $db;
    private $settings = [
        'enabled' => false,
        'username' => '',
        'api_key' => '',
        'allow_test' => true,
        'default_markup_nominal' => 0,
        'last_sync' => null,
        'webhook_secret' => ''
    ];

    private const BASE_URL = 'https://api.digiflazz.com/v1';

    public function __construct() {
        if (!function_exists('getDBConnection')) {
            require_once(__DIR__ . '/../include/db_config.php');
        }

        $this->db = getDBConnection();
        if (!$this->db) {
            throw new Exception('Database connection unavailable');
        }

        $this->loadSettings();
    }

    /**
     * Load Digiflazz settings from agent_settings table
     */
    private function loadSettings(): void {
        $query = "SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'digiflazz_%'";
        $stmt = $this->db->query($query);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['setting_key']) {
                case 'digiflazz_enabled':
                    $this->settings['enabled'] = $row['setting_value'] == '1';
                    break;
                case 'digiflazz_username':
                    $this->settings['username'] = trim($row['setting_value']);
                    break;
                case 'digiflazz_api_key':
                    $this->settings['api_key'] = trim($row['setting_value']);
                    break;
                case 'digiflazz_allow_test':
                    $this->settings['allow_test'] = $row['setting_value'] != '0';
                    break;
                case 'digiflazz_default_markup_nominal':
                    $this->settings['default_markup_nominal'] = (int)$row['setting_value'];
                    break;
                case 'digiflazz_default_markup_percent':
                    // Backward compatibility: treat legacy percent setting as nominal value
                    $this->settings['default_markup_nominal'] = (int)$row['setting_value'];
                    break;
                case 'digiflazz_last_sync':
                    $this->settings['last_sync'] = $row['setting_value'];
                    break;
                case 'digiflazz_webhook_secret':
                    $this->settings['webhook_secret'] = trim($row['setting_value']);
                    break;
                case 'digiflazz_webhook_id':
                    $this->settings['webhook_id'] = trim($row['setting_value']);
                    break;
            }
        }
    }

    public function isEnabled(): bool {
        return $this->settings['enabled'] && !empty($this->settings['username']) && !empty($this->settings['api_key']);
    }

    public function getSettings(): array {
        return $this->settings;
    }

    /**
     * Generate signature for Digiflazz request
     */
    private function generateSign(array $payload): string {
        $username = $this->settings['username'];
        $apiKey = $this->settings['api_key'];

        if (isset($payload['cmd']) && $payload['cmd'] === 'deposit') {
            return md5($username . $apiKey . 'depo');
        }

        if (isset($payload['ref_id'])) {
            return md5($username . $apiKey . $payload['ref_id']);
        }

        if (isset($payload['commands'])) {
            return md5($username . $apiKey . $payload['commands']);
        }

        if (isset($payload['cmd'])) {
            return md5($username . $apiKey . $payload['cmd']);
        }

        // Fallback to username+apiKey
        return md5($username . $apiKey);
    }

    /**
     * Perform POST request to Digiflazz API
     */
    private function postRequest(string $endpoint, array $payload, bool $allowTestOverride = false): array {
        if (!$this->isEnabled()) {
            throw new Exception('Digiflazz belum dikonfigurasi. Lengkapi username/API key.');
        }

        $payload['username'] = $this->settings['username'];
        $payload['sign'] = $this->generateSign($payload);

        if ($allowTestOverride && $this->settings['allow_test'] && $endpoint !== 'cek-saldo') {
            $payload['testing'] = true;
        }

        $url = self::BASE_URL . '/' . ltrim($endpoint, '/');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception('Koneksi ke Digiflazz gagal: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Response Digiflazz tidak valid.');
        }

        return $decoded;
    }

    /**
     * Cek saldo / deposit Digiflazz
     */
    public function checkBalance(): array {
        $payload = ['cmd' => 'deposit'];
        $response = $this->postRequest('cek-saldo', $payload);

        if (!isset($response['data']['deposit'])) {
            error_log('Digiflazz balance error: ' . json_encode($response));
            throw new Exception($response['message'] ?? 'Tidak dapat mendapatkan saldo.');
        }

        return [
            'success' => true,
            'balance' => (float)$response['data']['deposit'],
            'raw' => $response
        ];
    }

    /**
     * Ambil daftar harga dari Digiflazz dan simpan ke database
     */
    public function syncPriceList(): array {
        $report = [
            'prepaid' => 0,
            'postpaid' => 0
        ];

        $this->db->beginTransaction();
        try {
            $report['prepaid'] = $this->syncCategory('prepaid');
            $report['postpaid'] = $this->syncCategory('pasca');

            // Update last sync timestamp
            $stmt = $this->db->prepare("INSERT INTO agent_settings (setting_key, setting_value, setting_type, description, updated_by)
                    VALUES ('digiflazz_last_sync', NOW(), 'datetime', 'Last price list sync timestamp', 'system')
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = 'system'");
            $stmt->execute();

            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Sinkronisasi harga berhasil',
                'report' => $report
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function syncCategory(string $cmd): int {
        $payload = ['cmd' => $cmd];
        $response = $this->postRequest('price-list', $payload, false);

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new Exception('Response price list tidak valid.');
        }

        $count = 0;
        $stmt = $this->db->prepare("INSERT INTO digiflazz_products (
                buyer_sku_code, product_name, brand, category, type, price, buyer_price, seller_price, status, desc_header, desc_footer, icon_url, allow_markup
            ) VALUES (
                :buyer_sku_code, :product_name, :brand, :category, :type, :price, :buyer_price, :seller_price, :status, :desc_header, :desc_footer, :icon_url, :allow_markup
            ) ON DUPLICATE KEY UPDATE
                product_name = VALUES(product_name),
                brand = VALUES(brand),
                category = VALUES(category),
                type = VALUES(type),
                price = VALUES(price),
                buyer_price = VALUES(buyer_price),
                seller_price = VALUES(seller_price),
                status = VALUES(status),
                desc_header = VALUES(desc_header),
                desc_footer = VALUES(desc_footer),
                icon_url = VALUES(icon_url),
                allow_markup = VALUES(allow_markup),
                updated_at = CURRENT_TIMESTAMP");

        foreach ($response['data'] as $item) {
            if (!isset($item['buyer_sku_code'])) {
                continue;
            }

            $status = isset($item['status']) ? (int)$item['status'] : 1;
            $stmt->execute([
                ':buyer_sku_code' => $item['buyer_sku_code'],
                ':product_name' => $item['product_name'] ?? ($item['product_name'] ?? $item['buyer_sku_code']),
                ':brand' => $item['brand'] ?? ($item['brand_name'] ?? null),
                ':category' => $item['category'] ?? null,
                ':type' => $cmd === 'prepaid' ? 'prepaid' : 'postpaid',
                ':price' => isset($item['price']) ? (int)$item['price'] : 0,
                ':buyer_price' => isset($item['buyer_price']) ? (int)$item['buyer_price'] : null,
                ':seller_price' => isset($item['seller_price']) ? (int)$item['seller_price'] : null,
                ':status' => $status === 0 ? 'inactive' : 'active',
                ':desc_header' => $item['desc'] ?? null,
                ':desc_footer' => $item['desc'] ?? null,
                ':icon_url' => $item['icon_url'] ?? null,
                ':allow_markup' => 1
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Buat transaksi topup/purchase ke Digiflazz
     */
    public function createTransaction(array $params): array {
        $required = ['buyer_sku_code', 'customer_no', 'ref_id'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new Exception("Parameter $field wajib diisi");
            }
        }

        $payload = [
            'commands' => 'topup',
            'buyer_sku_code' => $params['buyer_sku_code'],
            'customer_no' => $params['customer_no'],
            'ref_id' => $params['ref_id']
        ];

        if (!empty($params['customer_name'])) {
            $payload['customer_name'] = $params['customer_name'];
        }

        if (!empty($params['testing']) && $this->settings['allow_test']) {
            $payload['testing'] = true;
        }

        $response = $this->postRequest('transaction', $payload, false);

        if (!isset($response['data'])) {
            throw new Exception($response['message'] ?? 'Transaksi gagal diproses');
        }

        return $response['data'];
    }

    /**
     * Utility to generate unique ref_id for transactions
     */
    public function generateRefId(string $prefix = 'DF'): string {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . random_int(100, 999);
    }

    /**
     * Cek tagihan pascabayar (PLN, PDAM, dll)
     */
    public function checkBill(string $productCode, string $customerNumber, ?string $refId = null): array {
        $refId = $refId ?: $this->generateRefId('INQ');

        $payload = [
            'commands' => 'inq-pasca',
            'buyer_sku_code' => $productCode,
            'customer_no' => $customerNumber,
            'ref_id' => $refId
        ];

        $response = $this->postRequest('cek-tagihan', $payload, false);

        if (!isset($response['data'])) {
            throw new Exception($response['message'] ?? 'Respon tagihan tidak valid');
        }

        return [
            'success' => true,
            'data' => $response['data'],
            'ref_id' => $refId,
            'raw' => $response
        ];
    }

    /**
     * Bayar tagihan pascabayar
     */
    public function payBill(string $refId, string $productCode, string $customerNumber, int $amount): array {
        $payload = [
            'commands' => 'pay-pasca',
            'buyer_sku_code' => $productCode,
            'customer_no' => $customerNumber,
            'ref_id' => $refId,
            'amount' => $amount
        ];

        $response = $this->postRequest('transaction', $payload, false);

        if (!isset($response['data'])) {
            throw new Exception($response['message'] ?? 'Pembayaran tagihan gagal');
        }

        return [
            'success' => true,
            'data' => $response['data'],
            'raw' => $response
        ];
    }

    /**
     * Cek status transaksi Digiflazz
     */
    public function checkTransaction(string $refId): array {
        $payload = [
            'commands' => 'status',
            'ref_id' => $refId
        ];

        $response = $this->postRequest('transaction', $payload, false);

        if (!isset($response['data'])) {
            throw new Exception($response['message'] ?? 'Status transaksi tidak ditemukan');
        }

        return [
            'success' => true,
            'data' => $response['data'],
            'raw' => $response
        ];
    }

    /**
     * Wrapper transaksi dengan retry otomatis
     */
    public function createTransactionWithRetry(array $params, int $maxRetry = 2): array {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetry) {
            try {
                if (($params['type'] ?? 'prepaid') === 'postpaid') {
                    return $this->payBill(
                        $params['ref_id'],
                        $params['buyer_sku_code'],
                        $params['customer_no'],
                        (int)$params['amount']
                    );
                }

                return $this->createTransaction($params);
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                $retryable = ['timeout', 'server busy', 'koneksi', 'request timed out', 'internal server error'];
                $message = strtolower($e->getMessage());
                $shouldRetry = false;

                foreach ($retryable as $keyword) {
                    if (strpos($message, $keyword) !== false) {
                        $shouldRetry = true;
                        break;
                    }
                }

                if (!$shouldRetry || $attempt > $maxRetry) {
                    break;
                }

                usleep(400000 * $attempt); // incremental backoff
            }
        }

        throw $lastException ?: new Exception('Transaksi gagal.');
    }
}
