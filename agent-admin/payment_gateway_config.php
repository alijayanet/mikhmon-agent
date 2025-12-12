<?php
/*
 * Payment Gateway Configuration
 * Support: Tripay, Xendit, Midtrans
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateway'])) {
    $gateway_name = $_POST['gateway_name'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_sandbox = isset($_POST['is_sandbox']) ? 1 : 0;
    $api_key = $_POST['api_key'];
    $api_secret = $_POST['api_secret'];
    $merchant_code = $_POST['merchant_code'] ?? '';
    $callback_token = $_POST['callback_token'] ?? '';
    
    // Debug log
    error_log("Payment Gateway Save Attempt: " . json_encode([
        'gateway_name' => $gateway_name,
        'is_active' => $is_active,
        'is_sandbox' => $is_sandbox,
        'api_key' => substr($api_key, 0, 10) . '...',
        'merchant_code' => $merchant_code
    ]));
    
    try {
        $conn = getDBConnection();
        
        $sql = "INSERT INTO payment_gateway_config 
                (gateway_name, is_active, is_sandbox, api_key, api_secret, merchant_code, callback_token)
                VALUES (:gateway_name, :is_active, :is_sandbox, :api_key, :api_secret, :merchant_code, :callback_token)
                ON DUPLICATE KEY UPDATE
                is_active = VALUES(is_active),
                is_sandbox = VALUES(is_sandbox),
                api_key = VALUES(api_key),
                api_secret = VALUES(api_secret),
                merchant_code = VALUES(merchant_code),
                callback_token = VALUES(callback_token)";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':gateway_name' => $gateway_name,
            ':is_active' => $is_active,
            ':is_sandbox' => $is_sandbox,
            ':api_key' => $api_key,
            ':api_secret' => $api_secret,
            ':merchant_code' => $merchant_code,
            ':callback_token' => $callback_token
        ]);
        
        if ($result) {
            $success_message = "Konfigurasi $gateway_name berhasil disimpan!";
            error_log("Payment Gateway Save Success: $gateway_name");
        } else {
            $error_message = "Gagal menyimpan konfigurasi $gateway_name";
            error_log("Payment Gateway Save Failed: $gateway_name");
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("Payment Gateway Save Error: " . $e->getMessage());
    }
}

// Get current configurations
try {
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT * FROM payment_gateway_config");
    $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $gateway_config = [];
    foreach ($gateways as $gw) {
        $gateway_config[$gw['gateway_name']] = $gw;
    }
} catch (Exception $e) {
    $gateway_config = [];
}

$session = $_GET['session'] ?? '';
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-credit-card"></i> Konfigurasi Payment Gateway</h3>
</div>
<div class="card-body">
    
    <!-- Payment Gateway Summary -->
    <div class="row mb-3">
        <div class="col-4 col-box-6">
            <div class="box bg-blue bmh-75 gateway-box" onclick="switchToTab('tripay')" style="cursor: pointer;">
                <h1><?= isset($gateway_config['tripay']) && $gateway_config['tripay']['is_active'] ? 'ON' : 'OFF'; ?>
                    <span style="font-size: 15px;"><?= isset($gateway_config['tripay']) && $gateway_config['tripay']['is_sandbox'] ? 'sandbox' : 'live'; ?></span>
                </h1>
                <div><i class="fa fa-credit-card"></i> Tripay Gateway</div>
            </div>
        </div>
        <div class="col-4 col-box-6">
            <div class="box bg-green bmh-75 gateway-box" onclick="switchToTab('xendit')" style="cursor: pointer;">
                <h1><?= isset($gateway_config['xendit']) && $gateway_config['xendit']['is_active'] ? 'ON' : 'OFF'; ?>
                    <span style="font-size: 15px;"><?= isset($gateway_config['xendit']) && $gateway_config['xendit']['is_sandbox'] ? 'sandbox' : 'live'; ?></span>
                </h1>
                <div><i class="fa fa-credit-card"></i> Xendit Gateway</div>
            </div>
        </div>
        <div class="col-4 col-box-6">
            <div class="box bg-yellow bmh-75 gateway-box" onclick="switchToTab('midtrans')" style="cursor: pointer;">
                <h1><?= isset($gateway_config['midtrans']) && $gateway_config['midtrans']['is_active'] ? 'ON' : 'OFF'; ?>
                    <span style="font-size: 15px;"><?= isset($gateway_config['midtrans']) && $gateway_config['midtrans']['is_sandbox'] ? 'sandbox' : 'live'; ?></span>
                </h1>
                <div><i class="fa fa-credit-card"></i> Midtrans Gateway</div>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <i class="fa fa-check-circle"></i> <?= $success_message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fa fa-exclamation-triangle"></i> <?= $error_message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Debug Info -->
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="alert alert-info">
        <strong>Debug Info:</strong><br>
        POST Data: <?= json_encode($_POST); ?><br>
        Request Method: <?= $_SERVER['REQUEST_METHOD']; ?>
    </div>
    <?php endif; ?>
    
    <!-- Compact Tabs -->
    <div class="text-center mb-3">
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary active" onclick="switchToTab('tripay')" id="tab-tripay">
                <i class="fa fa-credit-card"></i> Tripay
            </button>
            <button type="button" class="btn btn-outline-success" onclick="switchToTab('xendit')" id="tab-xendit">
                <i class="fa fa-credit-card"></i> Xendit
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="switchToTab('midtrans')" id="tab-midtrans">
                <i class="fa fa-credit-card"></i> Midtrans
            </button>
        </div>
    </div>
    
    <div class="tab-content" style="padding-top: 20px;">
        
        <!-- TRIPAY -->
        <div id="tripay" class="tab-pane fade show active">
            <?php
            $tripay = $gateway_config['tripay'] ?? [
                'is_active' => 0,
                'is_sandbox' => 1,
                'api_key' => '',
                'api_secret' => '',
                'merchant_code' => '',
                'callback_token' => ''
            ];
            ?>
            <form method="POST" action="">
                <input type="hidden" name="gateway_name" value="tripay">
                <input type="hidden" name="save_gateway" value="1">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?= $tripay['is_active'] ? 'checked' : ''; ?>>
                                <strong>Aktifkan Tripay</strong>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_sandbox" value="1" <?= $tripay['is_sandbox'] ? 'checked' : ''; ?>>
                                Mode Sandbox (Testing)
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>API Key <span class="text-danger">*</span></label>
                            <input type="text" name="api_key" class="form-control" 
                                   value="<?= htmlspecialchars($tripay['api_key']); ?>" required>
                            <small class="text-muted">Dapatkan di dashboard Tripay</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Private Key <span class="text-danger">*</span></label>
                            <input type="text" name="api_secret" class="form-control" 
                                   value="<?= htmlspecialchars($tripay['api_secret']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Merchant Code <span class="text-danger">*</span></label>
                            <input type="text" name="merchant_code" class="form-control" 
                                   value="<?= htmlspecialchars($tripay['merchant_code']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Callback Token</label>
                            <input type="text" name="callback_token" class="form-control" 
                                   value="<?= htmlspecialchars($tripay['callback_token']); ?>">
                            <small class="text-muted">Untuk validasi callback</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Callback URL:</strong><br>
                            <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/public/callback/tripay.php</code>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Simpan Konfigurasi Tripay
                </button>
            </form>
        </div>
        
        <!-- XENDIT -->
        <div id="xendit" class="tab-pane fade">
            <?php
            $xendit = $gateway_config['xendit'] ?? [
                'is_active' => 0,
                'is_sandbox' => 1,
                'api_key' => '',
                'api_secret' => '',
                'callback_token' => ''
            ];
            ?>
            <form method="POST" action="">
                <input type="hidden" name="gateway_name" value="xendit">
                <input type="hidden" name="save_gateway" value="1">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?= $xendit['is_active'] ? 'checked' : ''; ?>>
                                <strong>Aktifkan Xendit</strong>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_sandbox" value="1" <?= $xendit['is_sandbox'] ? 'checked' : ''; ?>>
                                Mode Sandbox (Testing)
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Secret API Key <span class="text-danger">*</span></label>
                            <input type="text" name="api_key" class="form-control" 
                                   value="<?= htmlspecialchars($xendit['api_key']); ?>" required>
                            <small class="text-muted">Dapatkan di dashboard Xendit</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Callback Token</label>
                            <input type="text" name="callback_token" class="form-control" 
                                   value="<?= htmlspecialchars($xendit['callback_token']); ?>">
                            <small class="text-muted">Untuk validasi webhook</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Webhook URL:</strong><br>
                            <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/public/callback/xendit.php</code>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Simpan Konfigurasi Xendit
                </button>
            </form>
        </div>
        
        <!-- MIDTRANS -->
        <div id="midtrans" class="tab-pane fade">
            <?php
            $midtrans = $gateway_config['midtrans'] ?? [
                'is_active' => 0,
                'is_sandbox' => 1,
                'api_key' => '',
                'api_secret' => '',
                'merchant_code' => ''
            ];
            ?>
            <form method="POST" action="">
                <input type="hidden" name="gateway_name" value="midtrans">
                <input type="hidden" name="save_gateway" value="1">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?= $midtrans['is_active'] ? 'checked' : ''; ?>>
                                <strong>Aktifkan Midtrans</strong>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_sandbox" value="1" <?= $midtrans['is_sandbox'] ? 'checked' : ''; ?>>
                                Mode Sandbox (Testing)
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Server Key <span class="text-danger">*</span></label>
                            <input type="text" name="api_key" class="form-control" 
                                   value="<?= htmlspecialchars($midtrans['api_key']); ?>" required>
                            <small class="text-muted">Dapatkan di dashboard Midtrans</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Client Key <span class="text-danger">*</span></label>
                            <input type="text" name="api_secret" class="form-control" 
                                   value="<?= htmlspecialchars($midtrans['api_secret']); ?>" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Notification URL:</strong><br>
                            <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/public/callback/midtrans.php</code>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Simpan Konfigurasi Midtrans
                </button>
            </form>
        </div>
        
    </div>
    
</div>
</div>
</div>
</div>

<style>
.gateway-box {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.gateway-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: 2px solid rgba(255,255,255,0.3);
}

.gateway-box:active {
    transform: translateY(0);
}

.btn-group .btn.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

.btn-group .btn {
    margin: 0;
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}
</style>

<script>
// Function to switch tabs when box is clicked
function switchToTab(tabId) {
    // Remove active class from all tab buttons and tab panes
    $('.btn-group .btn').removeClass('active');
    $('.tab-pane').removeClass('show active');
    
    // Add active class to clicked tab button and corresponding pane
    $('#tab-' + tabId).addClass('active');
    $('#' + tabId).addClass('show active');
    
    // Smooth scroll to tabs section
    $('html, body').animate({
        scrollTop: $('.btn-group').offset().top - 20
    }, 500);
}

// Initialize button group navigation
$(document).ready(function(){
    // Handle button group clicks
    $('.btn-group .btn').click(function(){
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
    });
    
    // Add click effect to gateway boxes
    $('.gateway-box').on('click', function() {
        $(this).addClass('clicked');
        setTimeout(() => {
            $(this).removeClass('clicked');
        }, 200);
    });
});
</script>
