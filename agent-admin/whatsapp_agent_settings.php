<?php
/*
 * Admin Panel - WhatsApp Agent Settings
 * Manage admin numbers and message templates
 */

include_once('./include/db_config.php');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_telegram'])) {
    try {
        // Query database directly to get fresh bot token (avoid require_once cache)
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_bot_token'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $botToken = $result['setting_value'] ?? '';
        
        if (empty($botToken)) {
            $error = 'Bot token belum dikonfigurasi. Silakan isi bot token terlebih dahulu.';
        } else {
            // Test connection to Telegram API
            $url = "https://api.telegram.org/bot" . $botToken . "/getMe";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                $error = 'Connection failed: ' . $curlError;
            } elseif ($httpCode == 200) {
                $result = json_decode($response, true);
                if (isset($result['ok']) && $result['ok']) {
                    $botInfo = $result['result'];
                    $success = '✅ Connection successful! Bot: @' . $botInfo['username'] . ' (' . $botInfo['first_name'] . ')';
                } else {
                    $error = 'Connection failed: ' . ($result['description'] ?? 'Unknown error');
                }
            } else {
                $error = 'Connection failed: HTTP ' . $httpCode;
            }
        }
    } catch (Exception $e) {
        $error = 'Error testing connection: ' . $e->getMessage();
    }
}

// Handle set webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_webhook'])) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_bot_token'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $botToken = $result['setting_value'] ?? '';
        
        if (empty($botToken)) {
            $error = 'Bot token belum dikonfigurasi.';
        } else {
            $webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/telegram_webhook.php';
            $url = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            if (isset($result['ok']) && $result['ok']) {
                $success = '✅ Webhook set successfully! URL: ' . $webhookUrl;
            } else {
                $error = 'Failed to set webhook: ' . ($result['description'] ?? 'Unknown error');
            }
        }
    } catch (Exception $e) {
        $error = 'Error setting webhook: ' . $e->getMessage();
    }
}

// Handle delete webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_webhook'])) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_bot_token'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $botToken = $result['setting_value'] ?? '';
        
        if (empty($botToken)) {
            $error = 'Bot token belum dikonfigurasi.';
        } else {
            $url = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            if (isset($result['ok']) && $result['ok']) {
                $success = '✅ Webhook deleted successfully!';
            } else {
                $error = 'Failed to delete webhook: ' . ($result['description'] ?? 'Unknown error');
            }
        }
    } catch (Exception $e) {
        $error = 'Error deleting webhook: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $db = getDBConnection();
        $db->beginTransaction();
        
        // Admin Numbers
        $adminNumbers = $_POST['admin_numbers'] ?? '';
        if (!empty($adminNumbers)) {
            $numbers = explode(',', $adminNumbers);
            $numbers = array_map('trim', $numbers);
            $numbers = array_filter($numbers);
            
            // Validate format
            $validNumbers = [];
            foreach ($numbers as $number) {
                if (preg_match('/^62\d{9,13}$/', $number)) {
                    $validNumbers[] = $number;
                }
            }
            
            $numbersString = implode(',', $validNumbers);
            
            $stmt = $db->prepare("
                INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) 
                VALUES (1, 'admin_whatsapp_numbers', ?, 'string', 'Admin WhatsApp numbers', 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$numbersString, $numbersString]);
        }
        
        // WhatsApp API Settings (for public voucher)
        $apiSettings = [
            'whatsapp_api_url' => $_POST['whatsapp_api_url'] ?? '',
            'whatsapp_api_key' => $_POST['whatsapp_api_key'] ?? ''
        ];
        
        foreach ($apiSettings as $key => $value) {
            $description = $key === 'whatsapp_api_url' ? 'WhatsApp API Gateway URL for public voucher' : 'WhatsApp API Key/Token';
            $stmt = $db->prepare("
                INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) 
                VALUES (1, ?, ?, 'string', ?, 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$key, $value, $description, $value]);
        }
        
        // Telegram Bot Settings
        $telegramSettings = [
            'telegram_enabled' => isset($_POST['telegram_enabled']) ? '1' : '0',
            'telegram_bot_token' => trim($_POST['telegram_bot_token'] ?? ''),
            'telegram_admin_chat_ids' => trim($_POST['telegram_admin_chat_ids'] ?? ''),
            'telegram_webhook_mode' => isset($_POST['telegram_webhook_mode']) ? '1' : '0'
        ];
        
        // Save to telegram_settings table
        $telegramStmt = $db->prepare("
            INSERT INTO telegram_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        foreach ($telegramSettings as $key => $value) {
            $telegramStmt->execute([$key, $value, $value]);
        }
        
        // Message Settings
        $settings = [
            'wa_message_header' => $_POST['message_header'] ?? '',
            'wa_message_footer' => $_POST['message_footer'] ?? '',
            'wa_business_name' => $_POST['business_name'] ?? '',
            'wa_business_phone' => $_POST['business_phone'] ?? '',
            'wa_business_address' => $_POST['business_address'] ?? '',
            'wa_enable_emoji' => isset($_POST['enable_emoji']) ? '1' : '0',
            'wa_enable_formatting' => isset($_POST['enable_formatting']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) 
                VALUES (1, ?, ?, 'string', 'WhatsApp message setting', 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $db->commit();
        $success = 'Pengaturan berhasil disimpan!';
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

// Load current settings
$db = getDBConnection();
$stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'admin_whatsapp_numbers' OR setting_key LIKE 'wa_%' OR setting_key LIKE 'whatsapp_api_%'");
$currentSettings = [];
while ($row = $stmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

// Load Telegram settings
$telegramStmt = $db->query("SELECT setting_key, setting_value FROM telegram_settings");
while ($row = $telegramStmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'admin_whatsapp_numbers' => '',
    'whatsapp_api_url' => 'https://api.fonnte.com/send',
    'whatsapp_api_key' => '',
    'wa_message_header' => '╔═══════════════════╗
║  🎫 VOUCHER WIFI  ║
╚═══════════════════╝',
    'wa_message_footer' => '
━━━━━━━━━━━━━━━━━━━━
📞 Customer Service
WA: {business_phone}
📍 {business_address}

Terima kasih! 🙏',
    'wa_business_name' => 'WiFi Hotspot',
    'wa_business_phone' => '08123456789',
    'wa_business_address' => 'Jl. Contoh No. 123',
    'wa_enable_emoji' => '1',
    'wa_enable_formatting' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $value;
    }
}

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Ensure card headers and labels are visible */
.card-header h3 {
    color: inherit !important;
    font-weight: 600 !important;
    margin: 0 !important;
}

.card-body {
    color: inherit !important;
}

.form-group label {
    color: inherit !important;
    font-weight: 600 !important;
    margin-bottom: 8px !important;
    display: block !important;
}

.help-text {
    font-size: 12px;
    color: #999 !important;
    margin-top: 5px;
    line-height: 1.5;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.checkbox-group label {
    margin: 0;
    font-weight: normal !important;
    cursor: pointer;
    color: inherit !important;
}

textarea.form-control {
    font-family: monospace;
    resize: vertical;
    min-height: 120px;
    color: #333 !important;
    background: #fff !important;
}

textarea.form-control:focus {
    color: #333 !important;
    background: #fff !important;
}

input.form-control {
    color: #333 !important;
    background: #fff !important;
}

input.form-control:focus {
    color: #333 !important;
    background: #fff !important;
}

.preview-box {
    background: #f8f9fa !important;
    border: 2px solid #25D366;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}

.preview-box h4 {
    margin-top: 0;
    color: #25D366 !important;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600 !important;
}

.preview-content {
    background: #fff !important;
    color: #333 !important;
    padding: 15px;
    border-radius: 8px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    white-space: pre-wrap;
    font-size: 14px;
    line-height: 1.6;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.admin-numbers-list {
    background: #f8f9fa !important;
    padding: 15px;
    border-radius: 5px;
    margin-top: 10px;
    border: 1px solid #ddd;
}

.admin-numbers-list strong {
    color: #333 !important;
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
}

.admin-numbers-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.admin-numbers-list li {
    padding: 8px 12px;
    background: #fff !important;
    color: #333 !important;
    margin: 5px 0;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #ddd;
}

.admin-numbers-list li span {
    color: #333 !important;
    font-weight: 500;
}

.admin-numbers-list li i {
    color: #25D366 !important;
}

.variable-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.variable-tag {
    background: #e3f2fd !important;
    color: #1976d2 !important;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-family: monospace;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #1976d2;
}

.variable-tag:hover {
    background: #1976d2 !important;
    color: #fff !important;
}

@media (max-width: 768px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-whatsapp"></i> Pengaturan WhatsApp Agent</h3>
</div>
<div class="card-body">

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="settings-grid">
            <!-- Admin Numbers -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-shield"></i> Nomor Admin</h3>
                </div>
                <div class="card-body">
                <div class="form-group">
                    <label>Nomor WhatsApp Admin</label>
                    <textarea name="admin_numbers" class="form-control" rows="5" placeholder="628123456789,628987654321"><?= htmlspecialchars($currentSettings['admin_whatsapp_numbers']); ?></textarea>
                    <div class="help-text">
                        • Pisahkan dengan koma (,) untuk multiple nomor<br>
                        • Format: 628xxx (tanpa + atau spasi)<br>
                        • Admin dapat generate voucher tanpa pemotongan saldo
                    </div>
                </div>

                <?php if (!empty($currentSettings['admin_whatsapp_numbers'])): ?>
                <div class="admin-numbers-list">
                    <strong style="color: #333 !important;">Nomor Terdaftar:</strong>
                    <ul>
                        <?php foreach (explode(',', $currentSettings['admin_whatsapp_numbers']) as $number): ?>
                        <li>
                            <i class="fa fa-check-circle"></i>
                            <span style="color: #333 !important;"><?= htmlspecialchars(trim($number)); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- WhatsApp API Settings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-cog"></i> WhatsApp API (Public Voucher)</h3>
                </div>
                <div class="card-body">
                <div class="form-group">
                    <label>WhatsApp API URL</label>
                    <input type="text" name="whatsapp_api_url" class="form-control" value="<?= htmlspecialchars($currentSettings['whatsapp_api_url']); ?>" placeholder="https://api.fonnte.com/send">
                    <div class="help-text">
                        • URL gateway WhatsApp untuk pengiriman voucher public<br>
                        • Contoh: https://api.fonnte.com/send, https://wablas.com/api/send-message
                    </div>
                </div>

                <div class="form-group">
                    <label>WhatsApp API Key</label>
                    <input type="text" name="whatsapp_api_key" class="form-control" value="<?= htmlspecialchars($currentSettings['whatsapp_api_key']); ?>" placeholder="Masukkan API Key">
                    <div class="help-text">
                        • API Key/Token dari provider WhatsApp gateway Anda<br>
                        • <strong style="color: #d9534f;">WAJIB DIISI</strong> agar voucher public bisa dikirim via WhatsApp
                    </div>
                </div>
                </div>
            </div>
            
            <!-- Telegram Bot Settings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fab fa-telegram"></i> Telegram Bot Settings</h3>
                </div>
                <div class="card-body">
                <div class="form-group">
                    <label>Enable Telegram Bot</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="telegram_enabled" id="telegram_enabled" value="1" <?= isset($currentSettings['telegram_enabled']) && $currentSettings['telegram_enabled'] == '1' ? 'checked' : ''; ?>>
                        <label for="telegram_enabled">Aktifkan Bot Telegram</label>
                    </div>
                    <div class="help-text">
                        • Bot Telegram untuk menerima perintah dari user<br>
                        • Gratis, tidak ada biaya per pesan
                    </div>
                </div>

                <div class="form-group">
                    <label>Telegram Bot Token</label>
                    <input type="text" name="telegram_bot_token" class="form-control" value="<?= htmlspecialchars($currentSettings['telegram_bot_token'] ?? ''); ?>" placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz" style="font-family: monospace;">
                    <div class="help-text">
                        • Dapatkan dari <strong>@BotFather</strong> di Telegram (kirim /newbot)<br>
                        • Token format: 1234567890:ABCdefGHIjklMNOpqrsTUVwxyz
                    </div>
                </div>

                <div class="form-group">
                    <label>Admin Chat IDs</label>
                    <input type="text" name="telegram_admin_chat_ids" class="form-control" value="<?= htmlspecialchars($currentSettings['telegram_admin_chat_ids'] ?? ''); ?>" placeholder="123456789, 987654321">
                    <div class="help-text">
                        • Dapatkan Chat ID dari <strong>@userinfobot</strong> di Telegram<br>
                        • Pisahkan dengan koma (,) untuk multiple admin<br>
                        • Admin dapat generate voucher tanpa pemotongan saldo
                    </div>
                </div>

                <div class="form-group">
                    <label>Webhook Mode</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="telegram_webhook_mode" id="telegram_webhook_mode" value="1" <?= isset($currentSettings['telegram_webhook_mode']) && $currentSettings['telegram_webhook_mode'] == '1' ? 'checked' : ''; ?>>
                        <label for="telegram_webhook_mode">Use Webhook (Recommended)</label>
                    </div>
                    <div class="help-text">
                        • Webhook lebih efisien (butuh HTTPS)<br>
                        • Uncheck jika server belum ada SSL certificate<br>
                        • Setup webhook di <a href="../settings/telegram_settings.php" target="_blank" style="color: #0088cc;">Telegram Settings</a>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <label>Webhook & Connection Management</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                        <button type="submit" name="test_telegram" class="btn" style="background: #0088cc; color: white; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa fa-plug"></i> Test Connection
                        </button>
                        <button type="submit" name="set_webhook" class="btn" style="background: #10b981; color: white; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa fa-link"></i> Set Webhook
                        </button>
                        <button type="submit" name="delete_webhook" class="btn" style="background: #ef4444; color: white; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa fa-unlink"></i> Delete Webhook
                        </button>
                    </div>
                    <div class="help-text" style="margin-top: 10px;">
                        • <strong>Test Connection:</strong> Cek apakah bot token valid<br>
                        • <strong>Set Webhook:</strong> Aktifkan webhook untuk menerima pesan (butuh HTTPS)<br>
                        • <strong>Delete Webhook:</strong> Hapus webhook jika ingin switch ke long polling<br>
                        • Webhook URL: <code>https://<?= $_SERVER['HTTP_HOST'] ?>/api/telegram_webhook.php</code>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <div class="settings-grid">
            <!-- Business Info -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-building"></i> Informasi Bisnis</h3>
                </div>
                <div class="card-body">
                <div class="form-group">
                    <label>Nama Bisnis</label>
                    <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($currentSettings['wa_business_name']); ?>" placeholder="WiFi Hotspot">
                </div>

                <div class="form-group">
                    <label>Nomor WhatsApp Bisnis</label>
                    <input type="text" name="business_phone" class="form-control" value="<?= htmlspecialchars($currentSettings['wa_business_phone']); ?>" placeholder="08123456789">
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <input type="text" name="business_address" class="form-control" value="<?= htmlspecialchars($currentSettings['wa_business_address']); ?>" placeholder="Jl. Contoh No. 123">
                </div>
                </div>
            </div>
        </div>

        <!-- Message Templates -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-comment"></i> Template Pesan</h3>
            </div>
            <div class="card-body">
            <div class="settings-grid">
                <div class="form-group">
                    <label>Header Pesan</label>
                    <textarea name="message_header" class="form-control" rows="6"><?= htmlspecialchars($currentSettings['wa_message_header']); ?></textarea>
                    <div class="help-text">Header yang ditampilkan di awal setiap pesan</div>
                </div>

                <div class="form-group">
                    <label>Footer Pesan</label>
                    <textarea name="message_footer" class="form-control" rows="6"><?= htmlspecialchars($currentSettings['wa_message_footer']); ?></textarea>
                    <div class="help-text">Footer yang ditampilkan di akhir setiap pesan</div>
                    
                    <div class="variable-tags">
                        <span class="variable-tag" onclick="insertVariable('message_footer', '{business_name}')" title="Klik untuk insert">{business_name}</span>
                        <span class="variable-tag" onclick="insertVariable('message_footer', '{business_phone}')" title="Klik untuk insert">{business_phone}</span>
                        <span class="variable-tag" onclick="insertVariable('message_footer', '{business_address}')" title="Klik untuk insert">{business_address}</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Opsi Pesan</label>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_emoji" id="enable_emoji" value="1" <?= $currentSettings['wa_enable_emoji'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_emoji">Gunakan Emoji</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_formatting" id="enable_formatting" value="1" <?= $currentSettings['wa_enable_formatting'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_formatting">Gunakan Format Bold/Italic</label>
                </div>
            </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="card">
            <div class="card-body">
        <div class="preview-box">
            <h4><i class="fa fa-eye"></i> Preview Pesan</h4>
            <div class="preview-content" id="messagePreview">
                <!-- Preview will be generated here -->
            </div>
        </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div style="margin-top: 30px; text-align: center;">
            <button type="submit" name="save_settings" class="btn btn-primary">
                <i class="fa fa-save"></i> Simpan Pengaturan
            </button>
            <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>
    </form>
</div>
</div>
</div>
</div>

<script>
function insertVariable(fieldName, variable) {
    const field = document.querySelector(`textarea[name="${fieldName}"]`);
    const cursorPos = field.selectionStart;
    const textBefore = field.value.substring(0, cursorPos);
    const textAfter = field.value.substring(cursorPos);
    
    field.value = textBefore + variable + textAfter;
    field.focus();
    field.selectionStart = field.selectionEnd = cursorPos + variable.length;
    
    updatePreview();
}

function updatePreview() {
    const header = document.querySelector('textarea[name="message_header"]').value;
    const footer = document.querySelector('textarea[name="message_footer"]').value;
    const businessName = document.querySelector('input[name="business_name"]').value;
    const businessPhone = document.querySelector('input[name="business_phone"]').value;
    const businessAddress = document.querySelector('input[name="business_address"]').value;
    
    // Replace variables
    let footerProcessed = footer
        .replace(/{business_name}/g, businessName)
        .replace(/{business_phone}/g, businessPhone)
        .replace(/{business_address}/g, businessAddress);
    
    // Sample voucher message
    const sampleMessage = `${header}

✅ VOUCHER BERHASIL DI-GENERATE

Profile: *3JAM*
Jumlah: 5 voucher
Total: Rp 15,000

━━━━━━━━━━━━━━━━━━━━

*Voucher #1*
Username: \`AG12AB34CD\`
Password: \`XY56ZW\`

*Voucher #2*
Username: \`AG98EF76GH\`
Password: \`MN12OP\`

...

${footerProcessed}`;
    
    document.getElementById('messagePreview').textContent = sampleMessage;
}

// Update preview on input
document.querySelectorAll('textarea, input[type="text"]').forEach(element => {
    element.addEventListener('input', updatePreview);
});

// Initial preview
document.addEventListener('DOMContentLoaded', updatePreview);
</script>
