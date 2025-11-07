<?php
/*
 * WhatsApp Settings Page for MikhMon
 */

session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

// Get session from URL
$session = $_GET['session'];
if (empty($session)) {
    header("Location:../admin.php?id=sessions");
    exit;
}

include('./include/config.php');
include('./include/readcfg.php');
include('./include/whatsapp_config.php');

// Handle form submission
if (isset($_POST['save_whatsapp_settings'])) {
    $gateway = $_POST['gateway'];
    $enabled = isset($_POST['enabled']) ? 'true' : 'false';
    $format_62 = isset($_POST['format_62']) ? 'true' : 'false';
    
    $fonnte_token = $_POST['fonnte_token'];
    $wablas_url = $_POST['wablas_url'];
    $wablas_token = $_POST['wablas_token'];
    $woowa_token = $_POST['woowa_token'];
    $mpwa_url = $_POST['mpwa_url'];
    $mpwa_token = $_POST['mpwa_token'];
    $mpwa_sender = $_POST['mpwa_sender'];
    $custom_url = $_POST['custom_url'];
    $custom_token = $_POST['custom_token'];
    
    // Read current config
    $configFile = './include/whatsapp_config.php';
    $content = file_get_contents($configFile);
    
    // Update values
    $content = preg_replace("/define\('WHATSAPP_GATEWAY', '.*?'\);/", "define('WHATSAPP_GATEWAY', '$gateway');", $content);
    $content = preg_replace("/define\('WHATSAPP_ENABLED', .*?\);/", "define('WHATSAPP_ENABLED', $enabled);", $content);
    $content = preg_replace("/define\('WHATSAPP_FORMAT_62', .*?\);/", "define('WHATSAPP_FORMAT_62', $format_62);", $content);
    
    $content = preg_replace("/define\('FONNTE_TOKEN', '.*?'\);/", "define('FONNTE_TOKEN', '$fonnte_token');", $content);
    $content = preg_replace("/define\('WABLAS_API_URL', '.*?'\);/", "define('WABLAS_API_URL', '$wablas_url');", $content);
    $content = preg_replace("/define\('WABLAS_TOKEN', '.*?'\);/", "define('WABLAS_TOKEN', '$wablas_token');", $content);
    $content = preg_replace("/define\('WOOWA_TOKEN', '.*?'\);/", "define('WOOWA_TOKEN', '$woowa_token');", $content);
    
    // MPWA config - add if not exists
    if (strpos($content, "MPWA_API_URL") === false) {
        $content = str_replace("?>", "\n// MPWA Gateway\ndefine('MPWA_API_URL', '$mpwa_url');\ndefine('MPWA_TOKEN', '$mpwa_token');\ndefine('MPWA_SENDER', '$mpwa_sender');\n\n?>", $content);
    } else {
        $content = preg_replace("/define\('MPWA_API_URL', '.*?'\);/", "define('MPWA_API_URL', '$mpwa_url');", $content);
        $content = preg_replace("/define\('MPWA_TOKEN', '.*?'\);/", "define('MPWA_TOKEN', '$mpwa_token');", $content);
        $content = preg_replace("/define\('MPWA_SENDER', '.*?'\);/", "define('MPWA_SENDER', '$mpwa_sender');", $content);
    }
    
    $content = preg_replace("/define\('CUSTOM_API_URL', '.*?'\);/", "define('CUSTOM_API_URL', '$custom_url');", $content);
    $content = preg_replace("/define\('CUSTOM_API_TOKEN', '.*?'\);/", "define('CUSTOM_API_TOKEN', '$custom_token');", $content);
    
    file_put_contents($configFile, $content);
    
    $success_message = "Pengaturan WhatsApp berhasil disimpan!";
}

// Test WhatsApp
if (isset($_POST['test_whatsapp'])) {
    $test_number = $_POST['test_number'];
    $test_message = "Test pesan dari MikhMon WhatsApp Integration\n\nJika Anda menerima pesan ini, konfigurasi WhatsApp sudah benar! ✅";
    
    $result = sendWhatsAppMessage($test_number, $test_message);
    
    if ($result['success']) {
        $test_result = "✅ Pesan berhasil dikirim ke $test_number";
    } else {
        $test_result = "❌ Gagal mengirim pesan: " . $result['message'];
    }
}
?>

<!-- WhatsApp Settings Content -->
<style>
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            color: inherit !important;
        }
        
        .container h1 {
            color: inherit !important;
            font-weight: 600 !important;
            margin-bottom: 20px;
        }
        
        .card {
            background: #fff !important;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #333 !important;
        }
        
        .card h3 {
            color: #333 !important;
            font-weight: 600 !important;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .card h4 {
            color: #333 !important;
            font-weight: 600 !important;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .card p {
            color: #555 !important;
            line-height: 1.6;
        }
        
        .card ol, .card ul {
            color: #333 !important;
        }
        
        .card li {
            color: #555 !important;
            margin-bottom: 8px;
            line-height: 1.6;
        }
        
        .card strong {
            color: #333 !important;
            font-weight: 600;
        }
        
        .card small {
            color: #666 !important;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .form-group input[type="checkbox"] + label {
            display: inline;
            font-weight: normal !important;
            margin-left: 8px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff !important;
            color: #333 !important;
        }
        
        .form-group input:focus, .form-group select:focus {
            background: #fff !important;
            color: #333 !important;
            border-color: #007bff;
            outline: none;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #007bff;
            color: white !important;
        }
        
        .btn-success {
            background: #28a745;
            color: white !important;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724 !important;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460 !important;
            border: 1px solid #bee5eb;
        }
        
        .gateway-config {
            display: none;
            padding: 15px;
            background: #f8f9fa !important;
            border-radius: 4px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .gateway-config.active {
            display: block;
        }
        
        .gateway-config h4 {
            color: #333 !important;
            margin-top: 0;
        }
        
        .gateway-config label {
            color: #333 !important;
        }
        
        .webhook-url {
            background: #f8f9fa !important;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            word-break: break-all;
            color: #333 !important;
            border: 1px solid #ddd;
        }
        
        .card a {
            color: #007bff !important;
            text-decoration: none;
        }
        
        .card a:hover {
            text-decoration: underline;
        }
    </style>

<div class="container">
        <h1>⚙️ WhatsApp Integration Settings</h1>
        
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($test_result)): ?>
        <div class="alert alert-info"><?= $test_result; ?></div>
        <?php endif; ?>
        
        <!-- Webhook URL Info -->
        <div class="card">
            <h3>📡 Webhook URL</h3>
            <p>Gunakan URL ini sebagai webhook di gateway WhatsApp Anda:</p>
            <div class="webhook-url">
                <?= 'http://' . $_SERVER['HTTP_HOST'] . '/mikhmon/api/whatsapp_webhook.php'; ?>
            </div>
        </div>
        
        <!-- Main Settings Form -->
        <div class="card">
            <h3>🔧 Konfigurasi WhatsApp</h3>
            <form method="POST">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="enabled" <?= WHATSAPP_ENABLED ? 'checked' : ''; ?>>
                        Aktifkan Notifikasi WhatsApp
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="format_62" <?= WHATSAPP_FORMAT_62 ? 'checked' : ''; ?>>
                        Format Nomor dengan 62 (jika tidak dicentang akan menggunakan 08)
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Pilih Gateway WhatsApp:</label>
                    <select name="gateway" id="gateway" onchange="showGatewayConfig()">
                        <option value="fonnte" <?= WHATSAPP_GATEWAY == 'fonnte' ? 'selected' : ''; ?>>Fonnte.com</option>
                        <option value="wablas" <?= WHATSAPP_GATEWAY == 'wablas' ? 'selected' : ''; ?>>Wablas.com</option>
                        <option value="woowa" <?= WHATSAPP_GATEWAY == 'woowa' ? 'selected' : ''; ?>>WooWA.id</option>
                        <option value="mpwa" <?= WHATSAPP_GATEWAY == 'mpwa' ? 'selected' : ''; ?>>MPWA (M-Pedia)</option>
                        <option value="custom" <?= WHATSAPP_GATEWAY == 'custom' ? 'selected' : ''; ?>>Custom Gateway</option>
                    </select>
                </div>
                
                <!-- Fonnte Config -->
                <div id="config-fonnte" class="gateway-config">
                    <h4>Konfigurasi Fonnte.com</h4>
                    <div class="form-group">
                        <label>Token Fonnte:</label>
                        <input type="text" name="fonnte_token" value="<?= FONNTE_TOKEN; ?>" placeholder="Masukkan token dari fonnte.com">
                    </div>
                    <p><small>Dapatkan token di: <a href="https://fonnte.com" target="_blank">fonnte.com</a></small></p>
                </div>
                
                <!-- Wablas Config -->
                <div id="config-wablas" class="gateway-config">
                    <h4>Konfigurasi Wablas.com</h4>
                    <div class="form-group">
                        <label>API URL Wablas:</label>
                        <input type="text" name="wablas_url" value="<?= WABLAS_API_URL; ?>" placeholder="https://DOMAIN_ANDA.wablas.com/api/send-message">
                    </div>
                    <div class="form-group">
                        <label>Token Wablas:</label>
                        <input type="text" name="wablas_token" value="<?= WABLAS_TOKEN; ?>" placeholder="Masukkan token dari wablas.com">
                    </div>
                    <p><small>Dapatkan token di: <a href="https://wablas.com" target="_blank">wablas.com</a></small></p>
                </div>
                
                <!-- WooWA Config -->
                <div id="config-woowa" class="gateway-config">
                    <h4>Konfigurasi WooWA.id</h4>
                    <div class="form-group">
                        <label>Token WooWA:</label>
                        <input type="text" name="woowa_token" value="<?= WOOWA_TOKEN; ?>" placeholder="Masukkan token dari woowa.id">
                    </div>
                    <p><small>Dapatkan token di: <a href="https://woowa.id" target="_blank">woowa.id</a></small></p>
                </div>
                
                <!-- MPWA Config -->
                <div id="config-mpwa" class="gateway-config">
                    <h4>Konfigurasi MPWA (M-Pedia)</h4>
                    <div class="form-group">
                        <label>URL MPWA Server:</label>
                        <input type="text" name="mpwa_url" value="<?= defined('MPWA_API_URL') ? MPWA_API_URL : ''; ?>" placeholder="http://localhost:8000 atau https://mpwa-anda.com">
                        <small>URL server MPWA Anda (tanpa trailing slash)</small>
                    </div>
                    <div class="form-group">
                        <label>API Token:</label>
                        <input type="text" name="mpwa_token" value="<?= defined('MPWA_TOKEN') ? MPWA_TOKEN : ''; ?>" placeholder="Masukkan token dari MPWA">
                        <small>Token API dari dashboard MPWA</small>
                    </div>
                    <div class="form-group">
                        <label>Sender Number (Device):</label>
                        <input type="text" name="mpwa_sender" value="<?= defined('MPWA_SENDER') ? MPWA_SENDER : ''; ?>" placeholder="628123456789">
                        <small><strong>PENTING:</strong> Nomor WhatsApp yang terdaftar di MPWA (format 62xxx)</small>
                    </div>
                    <p><small>Info lebih lanjut: <a href="https://m-pedia.my.id" target="_blank">m-pedia.my.id</a></small></p>
                    <div class="alert alert-info">
                        <strong>⚠️ Catatan MPWA:</strong>
                        <ul>
                            <li>Pastikan nomor sender sudah terdaftar dan terkoneksi di dashboard MPWA</li>
                            <li>Webhook URL harus didaftarkan di MPWA dashboard</li>
                            <li>Format webhook MPWA: <code>/api/whatsapp_webhook.php</code></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Custom Config -->
                <div id="config-custom" class="gateway-config">
                    <h4>Konfigurasi Custom Gateway</h4>
                    <div class="form-group">
                        <label>API URL:</label>
                        <input type="text" name="custom_url" value="<?= CUSTOM_API_URL; ?>" placeholder="https://api.gateway-anda.com/send">
                    </div>
                    <div class="form-group">
                        <label>API Token:</label>
                        <input type="text" name="custom_token" value="<?= CUSTOM_API_TOKEN; ?>" placeholder="Masukkan token API">
                    </div>
                </div>
                
                <button type="submit" name="save_whatsapp_settings" class="btn btn-primary">💾 Simpan Pengaturan</button>
            </form>
        </div>
        
        <!-- Test WhatsApp -->
        <div class="card">
            <h3>🧪 Test WhatsApp</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Nomor WhatsApp Test:</label>
                    <input type="text" name="test_number" placeholder="08123456789 atau 628123456789" required>
                </div>
                <button type="submit" name="test_whatsapp" class="btn btn-success">📤 Kirim Pesan Test</button>
            </form>
        </div>
        
        <!-- Documentation -->
        <div class="card">
            <h3>📖 Dokumentasi</h3>
            <h4>Cara Menggunakan:</h4>
            <ol>
                <li>Pilih gateway WhatsApp yang Anda gunakan</li>
                <li>Masukkan token/kredensial dari gateway tersebut</li>
                <li>Aktifkan notifikasi WhatsApp</li>
                <li>Simpan pengaturan</li>
                <li>Test dengan mengirim pesan test</li>
                <li>Salin Webhook URL dan daftarkan di dashboard gateway Anda</li>
            </ol>
            
            <h4>Perintah Bot WhatsApp:</h4>
            <ul>
                <li><strong>HARGA</strong> atau <strong>PAKET</strong> - Melihat daftar paket</li>
                <li><strong>VOUCHER [NAMA_PAKET]</strong> - Membeli voucher dengan username dan password sama (contoh: VOUCHER 3K)</li>
                <li><strong>MEMBER [NAMA_PAKET]</strong> - Membeli voucher dengan username dan password berbeda (contoh: MEMBER 3K)</li>
                <li><strong>BELI [NAMA_PAKET]</strong> - Membeli voucher (perintah default, contoh: BELI 1JAM)</li>
                <li><strong>HELP</strong> - Bantuan</li>
            </ul>
            
            <p><small><strong>Catatan:</strong> Format username dan password voucher dapat dikonfigurasi di menu <strong>Agent/Reseller → Format Voucher</strong></small></p>
            
            <h4>Fitur Otomatis:</h4>
            <ul>
                <li>✅ Notifikasi voucher otomatis saat generate user</li>
                <li>✅ Bot WhatsApp untuk pembelian voucher</li>
                <li>✅ Log transaksi WhatsApp</li>
                <li>✅ Support multiple gateway</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="./?session=<?= $session; ?>" class="btn btn-primary">← Kembali ke Dashboard</a>
        </div>
</div>

<script>
        function showGatewayConfig() {
            // Hide all configs
            document.querySelectorAll('.gateway-config').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show selected config
            const gateway = document.getElementById('gateway').value;
            const configEl = document.getElementById('config-' + gateway);
            if (configEl) {
                configEl.classList.add('active');
            }
        }
        
        // Show initial config
        showGatewayConfig();
    </script>
