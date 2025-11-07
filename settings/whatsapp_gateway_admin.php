<?php
/*
 * WhatsApp Gateway Admin Panel
 * Self-Hosted Gateway Management
 */

// Session already started in index.php
// Get session from URL
$session = $_GET['session'] ?? '';

include('./include/config.php');
include('./include/whatsapp_config.php');

// Gateway API settings
$gatewayUrl = SELFHOSTED_API_URL;
$gatewayBaseUrl = str_replace('/api/send', '', $gatewayUrl);
$apiKey = SELFHOSTED_API_KEY;

// Function to call gateway API
function callGatewayAPI($endpoint, $method = 'GET', $data = null) {
    global $gatewayBaseUrl, $apiKey;
    
    // Check if gateway URL is configured
    if (empty($gatewayBaseUrl) || $gatewayBaseUrl === 'http://localhost:3000' || strpos($gatewayBaseUrl, 'localhost') !== false) {
        return ['success' => false, 'message' => 'Gateway URL not configured'];
    }
    
    $url = $gatewayBaseUrl . $endpoint;
    $curl = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5, // Reduced timeout
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $apiKey,
            'Content-Type: application/json'
        ],
    ];
    
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        if ($data) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    }
    
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['success' => false, 'message' => 'Connection error: ' . $error];
    }
    
    if ($httpcode !== 200) {
        return ['success' => false, 'message' => 'HTTP error: ' . $httpcode];
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Invalid JSON response'];
    }
    
    return $result ?: ['success' => false, 'message' => 'Empty response'];
}

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'logout') {
        $result = callGatewayAPI('/api/logout', 'POST');
        $message = $result['message'] ?? 'Logout request sent';
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'restart') {
        $result = callGatewayAPI('/api/restart', 'POST');
        $message = $result['message'] ?? 'Restart request sent';
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'test') {
        $phone = $_POST['test_phone'] ?? '';
        $testMessage = $_POST['test_message'] ?? 'Test message from MikhMon';
        
        if ($phone) {
            $result = callGatewayAPI('/api/send', 'POST', [
                'phone' => $phone,
                'message' => $testMessage
            ]);
            $message = $result['message'] ?? 'Test message sent';
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }
}

// Get gateway status with error handling
try {
    $status = callGatewayAPI('/api/status');
    $qrData = callGatewayAPI('/api/qr');
    $health = callGatewayAPI('/health');
    
    $isConnected = $status['connected'] ?? false;
    $connectionStatus = $status['status'] ?? 'unknown';
    $phoneNumber = $status['phone'] ?? 'N/A';
    $hasQR = $qrData['success'] ?? false;
    $qrCode = $qrData['qrCode'] ?? null;
} catch (Exception $e) {
    // Fallback if API not available
    $isConnected = false;
    $connectionStatus = 'error';
    $phoneNumber = 'N/A';
    $hasQR = false;
    $qrCode = null;
    $health = [];
}
?>

<!-- WhatsApp Gateway Admin Content -->
<style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: white !important;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
            color: white !important;
        }
        .back-btn {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white !important;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white !important;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white !important;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            color: #333 !important;
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333 !important;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600 !important;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-connected {
            background: #d4edda !important;
            color: #155724 !important;
        }
        .status-disconnected {
            background: #f8d7da !important;
            color: #721c24 !important;
        }
        .status-qr {
            background: #fff3cd !important;
            color: #856404 !important;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666 !important;
            font-size: 14px;
        }
        .info-value {
            color: #333 !important;
            font-weight: 600;
            font-size: 14px;
        }
        .qr-container {
            text-align: center;
            padding: 20px;
        }
        .qr-container img {
            max-width: 300px;
            border: 3px solid #667eea;
            border-radius: 10px;
            padding: 10px;
            background: white;
        }
        .qr-instructions {
            margin-top: 15px;
            padding: 15px;
            background: #e7f3ff !important;
            border-radius: 8px;
            color: #004085 !important;
            font-size: 14px;
            line-height: 1.6;
        }
        .qr-instructions strong {
            color: #004085 !important;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            margin: 5px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333 !important;
            font-weight: 600;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: #fff !important;
            color: #333 !important;
        }
        .form-control:focus {
            background: #fff !important;
            color: #333 !important;
            border-color: #667eea;
            outline: none;
        }
        .form-control::placeholder {
            color: #999 !important;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda !important;
            color: #155724 !important;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da !important;
            color: #721c24 !important;
            border: 1px solid #f5c6cb;
        }
        .card p {
            color: #555 !important;
            line-height: 1.6;
        }
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s;
        }
        .refresh-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
    </style>

<div class="container">
        <div class="header">
            <h1><i class="fa fa-whatsapp"></i> WhatsApp Gateway Admin</h1>
            <p>Self-Hosted WhatsApp Gateway Management Panel</p>
            <a href="./?session=<?= $session; ?>&hotspot=whatsapp-settings" class="back-btn">
                <i class="fa fa-arrow-left"></i> Back to Settings
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <i class="fa fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Connection Status -->
            <div class="card">
                <h2><i class="fa fa-signal"></i> Connection Status</h2>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="status-badge status-<?= $isConnected ? 'connected' : ($connectionStatus === 'qr_ready' ? 'qr' : 'disconnected') ?>">
                        <?= $isConnected ? 'Connected' : ucfirst($connectionStatus) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value"><?= $phoneNumber ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gateway URL</span>
                    <span class="info-value" style="font-size: 12px;"><?= $gatewayBaseUrl ?></span>
                </div>
                <?php if (isset($health['uptime'])): ?>
                <div class="info-row">
                    <span class="info-label">Uptime</span>
                    <span class="info-value"><?= gmdate("H:i:s", $health['uptime']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="card">
                <h2><i class="fa fa-cogs"></i> Actions</h2>
                <form method="POST" action="?hotspot=whatsapp-gateway&session=<?= $session; ?>&action=logout" style="display: inline;">
                    <button type="submit" class="btn btn-danger" <?= !$isConnected ? 'disabled' : '' ?>>
                        <i class="fa fa-sign-out"></i> Logout WhatsApp
                    </button>
                </form>
                <form method="POST" action="?hotspot=whatsapp-gateway&session=<?= $session; ?>&action=restart" style="display: inline;">
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-refresh"></i> Restart Connection
                    </button>
                </form>
                <a href="?hotspot=whatsapp-gateway&session=<?= $session; ?>&refresh=1" class="btn btn-primary">
                    <i class="fa fa-sync"></i> Refresh Status
                </a>
            </div>

            <!-- Test Message -->
            <div class="card">
                <h2><i class="fa fa-paper-plane"></i> Test Message</h2>
                <form method="POST" action="?hotspot=whatsapp-gateway&session=<?= $session; ?>&action=test">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="test_phone" class="form-control" placeholder="08123456789" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="test_message" class="form-control" rows="3" placeholder="Test message...">Test message from MikhMon WhatsApp Gateway</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= !$isConnected ? 'disabled' : '' ?>>
                        <i class="fa fa-send"></i> Send Test Message
                    </button>
                </form>
            </div>
        </div>

        <!-- QR Code Section -->
        <?php if (!$isConnected && $hasQR && $qrCode): ?>
        <div class="card">
            <h2><i class="fa fa-qrcode"></i> Scan QR Code</h2>
            <div class="qr-container">
                <img src="<?= $qrCode ?>" alt="QR Code">
                <div class="qr-instructions">
                    <strong>Cara Scan QR Code:</strong><br>
                    1. Buka WhatsApp di HP Anda<br>
                    2. Tap Menu (⋮) → Linked Devices<br>
                    3. Tap "Link a Device"<br>
                    4. Scan QR code di atas
                </div>
            </div>
        </div>
        <?php elseif (!$isConnected && $connectionStatus === 'disconnected'): ?>
        <div class="card">
            <h2><i class="fa fa-exclamation-triangle"></i> Not Connected</h2>
            <p style="text-align: center; padding: 20px; color: #666;">
                WhatsApp Gateway is not connected. Please wait for QR code or restart the service.
            </p>
            <div style="text-align: center;">
                <a href="?hotspot=whatsapp-gateway&session=<?= $session; ?>&refresh=1" class="btn btn-primary">
                    <i class="fa fa-refresh"></i> Refresh Page
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Configuration Info -->
        <div class="card">
            <h2><i class="fa fa-info-circle"></i> Configuration</h2>
            <div class="info-row">
                <span class="info-label">Gateway Type</span>
                <span class="info-value">Self-Hosted (Baileys)</span>
            </div>
            <div class="info-row">
                <span class="info-label">API Endpoint</span>
                <span class="info-value" style="font-size: 12px;"><?= SELFHOSTED_API_URL ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Current Gateway</span>
                <span class="info-value"><?= WHATSAPP_GATEWAY ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">WhatsApp Enabled</span>
                <span class="info-value"><?= WHATSAPP_ENABLED ? 'Yes' : 'No' ?></span>
            </div>
        </div>
    </div>

    <div class="refresh-btn" onclick="location.reload();" title="Refresh">
        <i class="fa fa-refresh"></i>
    </div>

<script>
    // Auto refresh every 10 seconds if not connected
    <?php if (!$isConnected): ?>
    setTimeout(function() {
        location.reload();
    }, 10000);
    <?php endif; ?>
</script>
