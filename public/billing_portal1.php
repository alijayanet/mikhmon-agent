<?php
/*
 * Billing Portal - Customer self-service page
 */

session_start();

date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');
require_once(__DIR__ . '/../lib/PublicPayment.class.php');

// Load GenieACS config in GLOBAL scope so constants are available everywhere
$genieacsConfigPath = __DIR__ . '/../genieacs/config.php';
if (file_exists($genieacsConfigPath)) {
    require_once($genieacsConfigPath);
    
    // Define constants from variables if not already defined
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
}

// Absolute fallback - ensure constants exist
if (!defined('GENIEACS_API_URL')) define('GENIEACS_API_URL', 'http://localhost:7557');
if (!defined('GENIEACS_USERNAME')) define('GENIEACS_USERNAME', '');
if (!defined('GENIEACS_PASSWORD')) define('GENIEACS_PASSWORD', '');
if (!defined('GENIEACS_TIMEOUT')) define('GENIEACS_TIMEOUT', 30);
if (!defined('GENIEACS_ENABLED')) define('GENIEACS_ENABLED', false);
if (!defined('GENIEACS_WIFI_SSID_PATH')) define('GENIEACS_WIFI_SSID_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
if (!defined('GENIEACS_WIFI_PASSWORD_PATH')) define('GENIEACS_WIFI_PASSWORD_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase');

/**
 * Load GenieACS class (config already loaded in global scope)
 */
function loadGenieACSForPortal() {
    if (class_exists('GenieACS')) {
        return true;
    }

    // Load GenieACS class file
    $genieacsClassPath = __DIR__ . '/../genieacs/lib/GenieACS.class.php';
    if (file_exists($genieacsClassPath)) {
        require_once($genieacsClassPath);
        return true;
    }
    
    return false;
}

if (!isset($_SESSION['billing_portal_customer_id']) || (int)$_SESSION['billing_portal_customer_id'] <= 0) {
    header('Location: billing_login.php');
    exit;
}

$activeCustomerId = (int)$_SESSION['billing_portal_customer_id'];

$wifiFeedback = [
    'ssid' => ['error' => null, 'success' => null],
    'password' => ['error' => null, 'success' => null],
];

$paymentFeedback = [
    'error' => null,
    'success' => null,
];

try {
    $billingService = new BillingService();
    $customer = $billingService->getCustomerById($activeCustomerId);

    if (!$customer) {
        unset($_SESSION['billing_portal_customer_id']);
        $_SESSION['billing_portal_flash_error'] = 'Data pelanggan tidak ditemukan. Silakan login kembali.';
        header('Location: billing_login.php');
        exit;
    }

    $profile = $billingService->getProfileById((int)$customer['profile_id']);
    $invoices = $billingService->listInvoices(['customer_id' => (int)$customer['id']], 12);
    $outstanding = array_filter($invoices, static fn ($invoice) => in_array($invoice['status'], ['unpaid', 'overdue'], true));
    $outstandingTotal = array_reduce($outstanding, static function ($carry, $invoice) {
        $amount = is_numeric($invoice['amount'] ?? null) ? (float)$invoice['amount'] : 0.0;
        return $carry + $amount;
    }, 0.0);
    // $deviceSnapshot = $billingService->getCustomerDeviceSnapshot((int)$customer['id']); // Now populated from GenieACS below
    $deviceSnapshot = []; // Will be populated from GenieACS

    // Fetch current WiFi settings from GenieACS
    $currentWiFi = [
        'ssid' => null,
        'password' => null,
        'enabled' => null,
        'error' => null
    ];
    
    $wifiDebug = []; // Debug info - will remove after confirmation
    
    try {
        // Load config first
        loadGenieACSForPortal();
        $wifiDebug[] = "Config loaded";
        
        // Check if GenieACS class is available
        if (!class_exists('GenieACS')) {
            throw new Exception('GenieACS class tidak tersedia.');
        }
        $wifiDebug[] = "GenieACS class available";
        
        $genieacs = new GenieACS();
        $wifiDebug[] = "GenieACS instantiated";
        
        if ($genieacs->isEnabled()) {
            $wifiDebug[] = "GenieACS is enabled";
            
            // Normalize phone
            $phone = $customer['phone'] ?? '';
            $wifiDebug[] = "Customer phone: $phone";
            
            $normalizedPhone = $phone;
            if (substr($phone, 0, 2) === '62') {
                $normalizedPhone = '0' . substr($phone, 2);
            }
            $wifiDebug[] = "Normalized phone: $normalizedPhone";
            
            // Query device by phone tag WITHOUT projection to get all data including VirtualParameters
            $query = ['_tags' => $normalizedPhone];
            $devicesResult = $genieacs->getDevices($query); // No projection - get all data
            $wifiDebug[] = "Query without projection to get VirtualParameters";
            
            $wifiDebug[] = "Device query success: " . ($devicesResult['success'] ? 'YES' : 'NO');
            $wifiDebug[] = "Devices found: " . count($devicesResult['data'] ?? []);
            
            if ($devicesResult['success'] && !empty($devicesResult['data'])) {
                $device = $devicesResult['data'][0];
                $wifiDebug[] = "Device ID: " . ($device['_id'] ?? 'N/A');
                
                // Use VirtualParameters like admin page does
                $ssidPath = 'VirtualParameters.SSID';
                $ssidPathAlt = 'VirtualParameters.SSID_ALL';
                $passwordPath = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase';
                
                // Check what keys exist in device
                $wifiDebug[] = "Device has " . count($device) . " parameters";
                
                // Show all parameter keys for debugging
                $paramKeys = array_keys($device);
                $wifiDebug[] = "Parameter keys: " . implode(', ', array_slice($paramKeys, 0, 20)); // Show first 20
                
                // Debug VirtualParameters content
                if (isset($device['VirtualParameters'])) {
                    $vpKeys = array_keys($device['VirtualParameters']);
                    $wifiDebug[] = "VirtualParameters has " . count($vpKeys) . " keys: " . implode(', ', array_slice($vpKeys, 0, 15));
                } else {
                    $wifiDebug[] = "VirtualParameters not found in device";
                }
                
                // Parse SSID from VirtualParameters - try multiple possible names
                $ssidFound = false;
                
                // Try common SSID parameter names
                $possibleSSIDKeys = ['SSID', 'SSID_ALL', 'WlanSSID', 'wlanSSID', 'ssid', 'Ssid'];
                foreach ($possibleSSIDKeys as $key) {
                    if (isset($device['VirtualParameters'][$key])) {
                        $currentWiFi['ssid'] = $device['VirtualParameters'][$key]['_value'] ?? $device['VirtualParameters'][$key];
                        $wifiDebug[] = "SSID found in VirtualParameters.$key: " . ($currentWiFi['ssid'] ?? 'NULL');
                        $ssidFound = true;
                        break;
                    }
                }
                
                if (!$ssidFound) {
                    $wifiDebug[] = "SSID not found in VirtualParameters (tried: " . implode(', ', $possibleSSIDKeys) . ")";
                    
                    // Try to get from raw TR-069 path as fallback
                    if (isset($device['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['SSID'])) {
                        $currentWiFi['ssid'] = $device['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['SSID']['_value'] ?? null;
                        $wifiDebug[] = "SSID found in raw TR-069 path: " . ($currentWiFi['ssid'] ?? 'NULL');
                    } else {
                        $wifiDebug[] = "SSID not found in raw TR-069 path either";
                    }
                }
                
                // Get password from WlanPassword VirtualParameter
                if (isset($device['VirtualParameters']['WlanPassword'])) {
                    $currentWiFi['password'] = $device['VirtualParameters']['WlanPassword']['_value'] ?? $device['VirtualParameters']['WlanPassword'];
                    $wifiDebug[] = "Password found in VirtualParameters.WlanPassword: SET";
                } else {
                    $wifiDebug[] = "WlanPassword not found in VirtualParameters";
                }
                
                // WiFi enabled status
                if (isset($device['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['Enable'])) {
                    $currentWiFi['enabled'] = $device['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['Enable']['_value'] ?? null;
                    $wifiDebug[] = "WiFi enabled: " . ($currentWiFi['enabled'] ?? 'NULL');
                }
                
                // Populate ONU device snapshot from GenieACS data
                $deviceSnapshot = [
                    'status' => 'online', // Device found means online
                    'device_id' => $device['_id'] ?? 'N/A',
                    'pppoe_username' => $device['VirtualParameters']['pppoeUsername']['_value'] ?? $device['VirtualParameters']['pppoeUsername'] ?? 'N/A',
                    'connected_devices' => $device['VirtualParameters']['activedevices']['_value'] ?? $device['VirtualParameters']['activedevices'] ?? 'N/A',
                    'rx_power' => $device['VirtualParameters']['RXPower']['_value'] ?? $device['VirtualParameters']['RXPower'] ?? 'N/A',
                    'temperature' => $device['VirtualParameters']['gettemp']['_value'] ?? $device['VirtualParameters']['gettemp'] ?? 'N/A',
                    'serial_number' => $device['VirtualParameters']['getSerialNumber']['_value'] ?? $device['VirtualParameters']['getSerialNumber'] ?? 'N/A',
                    'pppoe_ip' => $device['VirtualParameters']['pppoeIP']['_value'] ?? $device['VirtualParameters']['pppoeIP'] ?? 'N/A',
                ];
                $wifiDebug[] = "ONU data populated from GenieACS";
            } else {
                $wifiDebug[] = "No device found with tag: $normalizedPhone";
                // Set empty deviceSnapshot if no device found
                $deviceSnapshot = [];
            }
        } else {
            $wifiDebug[] = "GenieACS is DISABLED";
        }
    } catch (Throwable $e) {
        $wifiDebug[] = "ERROR: " . $e->getMessage();
        error_log("Billing Portal WiFi fetch error: " . $e->getMessage());
        // Only show error if it's not about missing config
        if (strpos($e->getMessage(), 'GENIEACS_API_URL') === false) {
            $currentWiFi['error'] = $e->getMessage();
        }
    }

    // Handle payment request
    if (isset($_POST['pay_invoice'])) {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        
        if ($invoiceId > 0) {
            // Verify invoice belongs to customer
            $invoice = $billingService->getInvoiceById($invoiceId);
            if ($invoice && (int)$invoice['customer_id'] === (int)$customer['id']) {
                // Check if invoice is unpaid
                if (in_array($invoice['status'], ['unpaid', 'overdue'])) {
                    // Redirect to payment selection page
                    $_SESSION['billing_payment_invoice'] = $invoice;
                    header('Location: billing_payment.php');
                    exit;
                } else {
                    $paymentFeedback['error'] = 'Invoice ini sudah dibayar.';
                }
            } else {
                $paymentFeedback['error'] = 'Invoice tidak ditemukan atau bukan milik Anda.';
            }
        } else {
            $paymentFeedback['error'] = 'Parameter tidak valid.';
        }
    }

    // Handle WiFi changes
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_ssid'])) {
            $newSsid = trim($_POST['ssid'] ?? '');

            if ($newSsid === '' || mb_strlen($newSsid) < 3 || mb_strlen($newSsid) > 32) {
                $wifiFeedback['ssid']['error'] = 'SSID harus 3-32 karakter.';
            } else {
                try {
                    loadGenieACSForPortal();
                    $genieacs = new GenieACS();
                    
                    if (!$genieacs->isEnabled()) {
                        throw new Exception('GenieACS tidak aktif.');
                    }
                    
                    // Normalize phone: 62xxx -> 0xxx
                    $phone = $customer['phone'] ?? '';
                    $normalizedPhone = $phone;
                    if (substr($phone, 0, 2) === '62') {
                        $normalizedPhone = '0' . substr($phone, 2);
                    }
                    
                    // Query device by phone tag
                    $query = ['_tags' => $normalizedPhone];
                    $devicesResult = $genieacs->getDevices($query);
                    
                    if (!$devicesResult['success'] || empty($devicesResult['data'])) {
                        throw new Exception('Device tidak ditemukan. Pastikan nomor HP terdaftar di GenieACS.');
                    }
                    
                    $device = $devicesResult['data'][0];
                    $deviceId = $device['_id'] ?? '';
                    
                    if (empty($deviceId)) {
                        throw new Exception('Device ID tidak valid.');
                    }
                    
                    // Change SSID
                    $result = $genieacs->changeWiFi($deviceId, $newSsid, null);
                    
                    if ($result['success']) {
                        $wifiFeedback['ssid']['success'] = 'SSID berhasil diubah. Perubahan akan diterapkan dalam beberapa saat.';
                    } else {
                        throw new Exception($result['message'] ?? 'Gagal mengubah SSID');
                    }
                } catch (Throwable $e) {
                    $wifiFeedback['ssid']['error'] = $e->getMessage();
                }
            }
        }

        if (isset($_POST['change_password'])) {
            $newPassword = trim($_POST['password'] ?? '');

            if ($newPassword === '' || mb_strlen($newPassword) < 8) {
                $wifiFeedback['password']['error'] = 'Password minimal 8 karakter.';
            } else {
                try {
                    loadGenieACSForPortal();
                    $genieacs = new GenieACS();
                    
                    if (!$genieacs->isEnabled()) {
                        throw new Exception('GenieACS tidak aktif.');
                    }
                    
                    // Normalize phone: 62xxx -> 0xxx
                    $phone = $customer['phone'] ?? '';
                    $normalizedPhone = $phone;
                    if (substr($phone, 0, 2) === '62') {
                        $normalizedPhone = '0' . substr($phone, 2);
                    }
                    
                    // Query device by phone tag
                    $query = ['_tags' => $normalizedPhone];
                    $devicesResult = $genieacs->getDevices($query);
                    
                    if (!$devicesResult['success'] || empty($devicesResult['data'])) {
                        throw new Exception('Device tidak ditemukan. Pastikan nomor HP terdaftar di GenieACS.');
                    }
                    
                    $device = $devicesResult['data'][0];
                    $deviceId = $device['_id'] ?? '';
                    
                    if (empty($deviceId)) {
                        throw new Exception('Device ID tidak valid.');
                    }
                    
                    // Change password
                    $result = $genieacs->changeWiFi($deviceId, null, $newPassword);
                    
                    if ($result['success']) {
                        $wifiFeedback['password']['success'] = 'Password berhasil diubah. Perubahan akan diterapkan dalam beberapa saat.';
                    } else {
                        throw new Exception($result['message'] ?? 'Gagal mengubah password');
                    }
                } catch (Throwable $e) {
                    $wifiFeedback['password']['error'] = $e->getMessage();
                }
            }
        }
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Terjadi kesalahan: ' . htmlspecialchars($e->getMessage());
    exit;
}

$theme = 'default';
$themecolor = '#3a4149';
if (file_exists(__DIR__ . '/../include/theme.php')) {
    include(__DIR__ . '/../include/theme.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Pelanggan - <?= htmlspecialchars($customer['name']); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    <style>
        body { background-color: #ecf0f5; font-family: 'Source Sans Pro', Arial, sans-serif; }
        .wrapper { max-width: 940px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.1); margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
        .card-header .header-info { flex:1 1 auto; }
        .logout-link { background:#f3f4f6; border-radius:6px; padding:8px 14px; font-size:13px; color:#1f2937; text-decoration:none; border:1px solid #cbd5f5; transition: all 0.2s; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .logout-link:hover { background:#e5e7eb; color:#0f172a; }
        .card-body { padding: 20px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .box { border-radius: 4px; padding: 18px; color: #fff; min-height: 110px; }
        .box h2 { margin: 0 0 6px; font-size: 26px; }
        .badge-status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
        .badge-status.paid { background: #d1fae5; color: #065f46; }
        .badge-status.unpaid { background: #fee2e2; color: #991b1b; }
        .badge-status.overdue { background: #fde68a; color: #92400e; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 10px; font-size: 13px; }
        .invoice-table th { background: #f3f4f6; font-weight: 600; }
        .form-control[disabled] { background: #f3f4f6; }
        .btn { cursor: pointer; }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: #ffffff;
            line-height: 1.55;
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            max-width: min(100%, 560px);
            box-sizing: border-box;
            flex-wrap: wrap;
        }
        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 10px 0 0 10px;
        }
        .alert-info {
            color: #075985;
        }
        .alert-info::before {
            background: #0ea5e9;
        }
        .alert-warning {
            color: #92400e;
        }
        .alert-warning::before {
            background: #d97706;
        }
        .alert-success {
            color: #166534;
        }
        .alert-success::before {
            background: #22c55e;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
        .wifi-card-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-top: 10px; }
        .wifi-action { background: #f9fafb; border-radius: 12px; padding: 18px; border: 1px solid #e5e7eb; box-shadow: inset 0 0 0 1px rgba(15,23,42,0.03); }
        .wifi-action h4 { margin-top: 0; font-weight: 600; color: #0f172a; }
        .wifi-action .btn { margin-top: 12px; }
        .wifi-action small.note { display: block; margin-top: 8px; color: #6b7280; }
        .wifi-feedback { margin-bottom: 12px; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
        .wifi-feedback.success { background: #dcfce7; color: #166534; }
        .wifi-feedback.error { background: #fee2e2; color: #991b1b; }
        .device-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .device-stat { background: #f9fafb; border-radius: 10px; padding: 16px; border: 1px solid #e5e7eb; box-shadow: inset 0 0 0 1px rgba(15,23,42,0.03); }
        .device-stat .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
        .device-stat .value { margin-top: 6px; font-size: 20px; font-weight: 600; color: #0f172a; }
        .device-stat .value.small { font-size: 16px; }
        .device-status.offline { color: #991b1b; }
        .device-status.online { color: #047857; }
        .btn-success {
            background: #00a65a;
            border-color: #008d4c;
            color: #fff;
        }
        .btn-success:hover {
            background: #008d4c;
            border-color: #00733e;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        @media (max-width: 520px) {
            .device-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .wrapper { padding: 10px; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="card-header">
            <div class="header-info">
                <h2><i class="fa fa-user-circle"></i> Selamat datang, <?= htmlspecialchars($customer['name']); ?></h2>
                <p style="margin:0; color:#6b7280;">Nomor layanan: <?= htmlspecialchars($customer['service_number']); ?></p>
                <p style="margin:0; color:#9ca3af; font-size:13px;">Telepon terdaftar: <?= htmlspecialchars($customer['phone'] ?: '-'); ?></p>
            </div>
            <a href="billing_logout.php" class="logout-link"><i class="fa fa-sign-out"></i> Keluar</a>
        </div>
        <div class="card-body">
            <div class="summary-grid">
                <div class="box" style="background:#3c8dbc;">
                    <h2><?= htmlspecialchars($profile['profile_name'] ?? 'Tidak diketahui'); ?></h2>
                    <p style="margin:0;">Harga: Rp <?= number_format($profile['price_monthly'] ?? 0, 0, ',', '.'); ?>/bulan</p>
                    <p style="margin:0;">Isolasi otomatis tanggal <?= str_pad((int)$customer['billing_day'], 2, '0', STR_PAD_LEFT); ?> setiap bulan</p>
                </div>
                <div class="box" style="background:#00a65a;">
                    <h2><?= count($outstanding); ?></h2>
                    <p style="margin:0;">Tagihan belum dibayar</p>
                </div>
                <div class="box" style="background:#f39c12;">
                    <h2><?= htmlspecialchars(strtoupper($customer['status'])); ?></h2>
                    <p style="margin:0;">Status akun</p>
                    <p style="margin:0;">Kondisi: <?= (int)$customer['is_isolated'] === 1 ? 'TERISOLASI' : 'NORMAL'; ?></p>
                </div>
                <div class="box" style="background:#dd4b39;">
                    <h2>Rp <?= number_format($outstandingTotal, 0, ',', '.'); ?></h2>
                    <p style="margin:0;">Total saldo tagihan</p>
                    <p style="margin:0;">Gabungan invoice belum dibayar</p>
                </div>
            </div>

            <?php if (!empty($outstanding)): ?>
                <div class="alert alert-warning">
                    <strong>Perhatian:</strong> Ada tagihan yang belum dibayar. Silakan lakukan pembayaran agar layanan kembali normal.
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <strong>Terima kasih!</strong> Tidak ada tagihan tertunda saat ini.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($deviceSnapshot)): ?>
        <?php
        $snapshotStatus = $deviceSnapshot['status'] ?? null;
        $statusLabel = $snapshotStatus ? strtoupper($snapshotStatus) : 'TIDAK DIKETAHUI';
        $statusClass = $snapshotStatus === 'online' ? 'online' : ($snapshotStatus === 'offline' ? 'offline' : '');
        $connectedDevices = $deviceSnapshot['connected_devices'] ?? null;
        $connectedLabel = $connectedDevices !== null ? $connectedDevices : 'N/A';
        $rxPowerRaw = $deviceSnapshot['rx_power'] ?? null;
        $rxLabel = ($rxPowerRaw !== null && $rxPowerRaw !== 'N/A' && $rxPowerRaw !== '') ? $rxPowerRaw . ' dBm' : 'N/A';
        $temperatureRaw = $deviceSnapshot['temperature'] ?? null;
        $temperatureLabel = ($temperatureRaw !== null && $temperatureRaw !== 'N/A' && $temperatureRaw !== '') ? $temperatureRaw . '°C' : 'N/A';
        $pppoeLabel = $deviceSnapshot['pppoe_username'] ?? 'N/A';
        ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-signal"></i> Informasi ONU</h3>
                <p style="margin:0; color:#6b7280;">Data ditarik langsung dari GenieACS untuk memantau perangkat Anda.</p>
            </div>
            <div class="card-body">
                <div class="device-stats-grid">
                    <div class="device-stat">
                        <div class="label">Status ONU</div>
                        <div class="value device-status <?= htmlspecialchars($statusClass); ?>"><?= htmlspecialchars($statusLabel); ?></div>
                        <small style="color:#6b7280;">Terhubung ke ACS sebagai <?= htmlspecialchars($deviceSnapshot['device_id'] ?? 'N/A'); ?></small>
                    </div>
                    
                    <?php if (!empty($currentWiFi['ssid'])): ?>
                    <div class="device-stat">
                        <div class="label">SSID Aktif</div>
                        <div class="value small" style="color: #0369a1;"><?= htmlspecialchars($currentWiFi['ssid']); ?></div>
                        <small style="color:#6b7280;">Nama WiFi yang terlihat</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="device-stat">
                        <div class="label">PPPoE Username</div>
                        <div class="value small"><?= htmlspecialchars($pppoeLabel ?: 'N/A'); ?></div>
                    </div>
                    <div class="device-stat">
                        <div class="label">Perangkat Terkoneksi</div>
                        <div class="value"><?= htmlspecialchars((string)$connectedLabel); ?></div>
                        <small style="color:#6b7280;">Termasuk perangkat WiFi aktif</small>
                    </div>
                    <div class="device-stat">
                        <div class="label">RX Power</div>
                        <div class="value"><?= htmlspecialchars($rxLabel); ?></div>
                        <small style="color:#6b7280;">Semakin dekat ke 0 dBm semakin baik</small>
                    </div>
                    <?php if ($temperatureLabel !== 'N/A'): ?>
                    <div class="device-stat">
                        <div class="label">Suhu Perangkat</div>
                        <div class="value"><?= htmlspecialchars($temperatureLabel); ?></div>
                        <small style="color:#6b7280;">Suhu perangkat saat ini</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-file-text-o"></i> Riwayat Tagihan</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Nominal</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                            <th>Dibayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 20px; color:#6b7280;">
                                    Belum ada invoice yang tercatat.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <?php $status = strtolower($invoice['status'] ?? 'unpaid'); ?>
                                <tr>
                                    <td><?= htmlspecialchars($invoice['period']); ?></td>
                                    <td>Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></td>
                                    <td><?= date('d M Y', strtotime($invoice['due_date'])); ?></td>
                                    <td><span class="badge-status <?= $status; ?>"><?= ucfirst($status); ?></span></td>
                                    <td><?= $invoice['paid_at'] ? date('d M Y H:i', strtotime($invoice['paid_at'])) : '-'; ?></td>
                                    <td>
                                        <?php if (in_array($status, ['unpaid', 'overdue'])): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id']; ?>">
                                                <button type="submit" name="pay_invoice" class="btn btn-success btn-sm">
                                                    <i class="fa fa-credit-card"></i> Bayar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-wifi"></i> Kelola WiFi</h3>
            <p style="margin:0; color:#6b7280;">Perubahan SSID dan password dikirim lewat GenieACS. Gunakan formulir terpisah di bawah ini.</p>
        </div>
        <div class="card-body">
            <?php if ((int)$customer['is_isolated'] === 1): ?>
                <div class="alert alert-info">
                    Akun Anda sedang dalam kondisi isolasi. Pastikan tagihan sudah dibayar agar profil normal dikembalikan.
                </div>
            <?php endif; ?>

            <?php if ($currentWiFi['ssid'] || $currentWiFi['password']): ?>
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <h4 style="margin-top: 0; color: #0c4a6e; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-info-circle"></i> Pengaturan WiFi Saat Ini
                    </h4>
                    <div class="device-stats-grid">
                        <?php if ($currentWiFi['ssid']): ?>
                        <div class="device-stat">
                            <div class="label">SSID Aktif</div>
                            <div class="value small" style="color: #0369a1;"><?= htmlspecialchars($currentWiFi['ssid']); ?></div>
                            <small style="color:#6b7280;">Nama WiFi yang terlihat</small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($currentWiFi['password']): ?>
                        <div class="device-stat">
                            <div class="label">Password Aktif</div>
                            <div class="value small" style="color: #0369a1; font-family: monospace;">
                                <span id="wifi-password-display">••••••••</span>
                                <button type="button" onclick="togglePasswordVisibility()" style="background: none; border: none; cursor: pointer; color: #0284c7; margin-left: 8px; font-size: 14px;">
                                    <i class="fa fa-eye" id="password-toggle-icon"></i>
                                </button>
                                <span id="wifi-password-actual" style="display: none;"><?= htmlspecialchars($currentWiFi['password']); ?></span>
                            </div>
                            <small style="color:#6b7280;">Klik ikon mata untuk tampilkan</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <script>
                function togglePasswordVisibility() {
                    const display = document.getElementById('wifi-password-display');
                    const actual = document.getElementById('wifi-password-actual');
                    const icon = document.getElementById('password-toggle-icon');
                    
                    if (display.textContent === '••••••••') {
                        display.textContent = actual.textContent;
                        icon.className = 'fa fa-eye-slash';
                    } else {
                        display.textContent = '••••••••';
                        icon.className = 'fa fa-eye';
                    }
                }
                </script>
            <?php elseif ($currentWiFi['error']): ?>
                <div class="alert alert-warning">
                    <strong>Perhatian:</strong> Tidak dapat mengambil data WiFi saat ini. <?= htmlspecialchars($currentWiFi['error']); ?>
                </div>
            <?php endif; ?>

            <div class="wifi-card-split">
                <div class="wifi-action">
                    <h4><i class="fa fa-wifi"></i> Ubah SSID</h4>
                    <?php if ($wifiFeedback['ssid']['error']): ?>
                        <div class="wifi-feedback error"><strong>Gagal:</strong> <?= htmlspecialchars($wifiFeedback['ssid']['error']); ?></div>
                    <?php elseif ($wifiFeedback['ssid']['success']): ?>
                        <div class="wifi-feedback success"><strong>Sukses:</strong> <?= htmlspecialchars($wifiFeedback['ssid']['success']); ?></div>
                    <?php endif; ?>
                    <form method="post" class="wifi-form">
                        <input type="hidden" name="change_ssid" value="1">
                        <div class="form-group">
                            <label>SSID Baru</label>
                            <input type="text" name="ssid" class="form-control" placeholder="Nama WiFi" minlength="3" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-paper-plane"></i> Kirim SSID Baru
                        </button>
                        <small class="note">SSID minimal 3 karakter. Router mungkin butuh beberapa menit untuk menerapkan perubahan.</small>
                    </form>
                </div>
                <div class="wifi-action">
                    <h4><i class="fa fa-lock"></i> Ubah Password</h4>
                    <?php if ($wifiFeedback['password']['error']): ?>
                        <div class="wifi-feedback error"><strong>Gagal:</strong> <?= htmlspecialchars($wifiFeedback['password']['error']); ?></div>
                    <?php elseif ($wifiFeedback['password']['success']): ?>
                        <div class="wifi-feedback success"><strong>Sukses:</strong> <?= htmlspecialchars($wifiFeedback['password']['success']); ?></div>
                    <?php endif; ?>
                    <form method="post" class="wifi-form">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fa fa-shield"></i> Kirim Password Baru
                        </button>
                        <small class="note">Setidaknya 8 karakter kombinasi huruf & angka untuk keamanan terbaik.</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>