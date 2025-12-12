<?php
/*
 * Admin Panel - Tambah Agent
 * Integrated with MikhMon
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();

$error = '';
$success = '';

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');

// Handle form submission
if (isset($_POST['add_agent'])) {
    $agentCode = $agent->generateAgentCode();
    $agentName = trim($_POST['agent_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $balance = floatval($_POST['balance']);
    $level = $_POST['level'];
    $commissionAmount = floatval($_POST['commission_amount']);
    $notes = trim($_POST['notes']);
    $telegramChatId = trim($_POST['telegram_chat_id']);
    $telegramUsername = trim($_POST['telegram_username']);
    
    // Validation
    if (empty($agentName) || empty($phone) || empty($password)) {
        $error = 'Nama, nomor WhatsApp, dan password wajib diisi!';
    } else {
        // Check if phone already exists
        $existingAgent = $agent->getAgentByPhone($phone);
        if ($existingAgent) {
            $error = 'Nomor WhatsApp sudah terdaftar!';
        } else {
            // Check if telegram_chat_id already exists (if provided)
            if (!empty($telegramChatId)) {
                $existingTelegram = $agent->getAgentByTelegramChatId($telegramChatId);
                if ($existingTelegram) {
                    $error = 'User ID Telegram sudah terdaftar untuk agent lain!';
                }
            }
            
            if (empty($error)) {
                $data = [
                    'agent_code' => $agentCode,
                    'agent_name' => $agentName,
                    'phone' => $phone,
                    'email' => $email,
                    'password' => $password,
                    'balance' => $balance,
                    'status' => 'active',
                    'level' => $level,
                    'commission_amount' => $commissionAmount,
                    'created_by' => $_SESSION['mikhmon'],
                    'notes' => $notes,
                    'telegram_chat_id' => $telegramChatId,
                    'telegram_username' => $telegramUsername
                ];
            
                $result = $agent->createAgent($data);
                
                if ($result['success']) {
                    $success = 'Agent berhasil ditambahkan! Kode Agent: ' . $agentCode;
                    // Clear form
                    $_POST = array();
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group label .required {
    color: #ef4444;
}

.info-box {
    background: #f0f9ff;
    border-left: 4px solid #3b82f6;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.info-box h4 {
    margin: 0 0 10px 0;
    color: #1e40af;
}

.info-box ul {
    margin: 10px 0 0 20px;
    color: #1e40af;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-user-plus"></i> Tambah Agent Baru</h3>
</div>
<div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fa fa-exclamation-circle"></i> <?= $error; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fa fa-check-circle"></i> <?= $success; ?>
        <br><br>
        <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn btn-primary">
            <i class="fa fa-list"></i> Lihat Daftar Agent
        </a>
        <a href="./?hotspot=agent-add&session=<?= $session; ?>" class="btn">
            <i class="fa fa-plus"></i> Tambah Agent Lagi
        </a>
    </div>
    <?php endif; ?>

    <div class="info-box">
        <h4><i class="fa fa-info-circle"></i> Informasi</h4>
        <ul>
            <li>Kode agent akan di-generate otomatis</li>
            <li>Nomor WhatsApp akan digunakan untuk login</li>
            <li>Password minimal 6 karakter</li>
            <li>Saldo awal bisa diisi 0 dan ditopup kemudian</li>
            <li><strong>User ID Telegram:</strong> Minta agent kirim pesan ke bot Telegram, lalu ambil Chat ID dari log webhook</li>
            <li><strong>Username Telegram:</strong> Username Telegram agent (opsional, untuk identifikasi)</li>
        </ul>
    </div>

    <form method="POST">
        <!-- Data Agent -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-user"></i> Data Agent</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="agent_name" class="form-control" 
                               value="<?= htmlspecialchars($_POST['agent_name'] ?? ''); ?>" 
                               placeholder="Nama lengkap agent" required>
                    </div>

                    <div class="form-group">
                        <label>Nomor WhatsApp <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="08123456789" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="email@example.com">
                    </div>

                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Minimal 6 karakter" minlength="6" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>User ID Telegram</label>
                        <input type="text" name="telegram_chat_id" class="form-control" 
                               value="<?= htmlspecialchars($_POST['telegram_chat_id'] ?? ''); ?>" 
                               placeholder="123456789">
                        <small style="color: #666;">Chat ID Telegram agent (opsional)</small>
                    </div>

                    <div class="form-group">
                        <label>Username Telegram</label>
                        <input type="text" name="telegram_username" class="form-control" 
                               value="<?= htmlspecialchars($_POST['telegram_username'] ?? ''); ?>" 
                               placeholder="@username">
                        <small style="color: #666;">Username Telegram agent (opsional)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pengaturan -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-cog"></i> Pengaturan Agent</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Level Agent</label>
                        <select name="level" class="form-control">
                            <option value="bronze">Bronze</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Komisi</label>
                        <input type="number" name="commission_amount" class="form-control" 
                               value="<?= htmlspecialchars($_POST['commission_amount'] ?? '0'); ?>" 
                               min="0" step="1000">
                    </div>
                </div>

                <div class="form-group">
                    <label>Saldo Awal</label>
                    <input type="number" name="balance" class="form-control" 
                           value="<?= htmlspecialchars($_POST['balance'] ?? '0'); ?>" 
                           min="0" step="1000" placeholder="0">
                    <small style="color: #666;">Biarkan 0 jika tidak ingin memberikan saldo awal</small>
                </div>

                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Catatan tambahan tentang agent ini"><?= htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div style="margin-top: 20px;">
            <button type="submit" name="add_agent" class="btn btn-primary btn-block">
                <i class="fa fa-save"></i> Simpan Agent
            </button>
            <br>
            <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn btn-block">
                <i class="fa fa-arrow-left"></i> Kembali ke Daftar Agent
            </a>
        </div>
    </form>
</div>
</div>
</div>
</div>

<script>
// Format phone number
document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.startsWith('62')) {
        value = '0' + value.substring(2);
    }
    e.target.value = value;
});

// Format Telegram Chat ID (only numbers)
document.querySelector('input[name="telegram_chat_id"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    e.target.value = value;
});

// Format Telegram Username (add @ if not present)
document.querySelector('input[name="telegram_username"]').addEventListener('input', function(e) {
    let value = e.target.value.trim();
    if (value && !value.startsWith('@')) {
        value = '@' + value;
    }
    e.target.value = value;
});

// Generate strong password
function generatePassword() {
    const length = 8;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let password = "";
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    document.querySelector('input[name="password"]').value = password;
}

// Show info about getting Telegram Chat ID
function showTelegramInfo() {
    alert('Cara mendapatkan User ID Telegram:\n\n1. Minta agent kirim pesan "/start" ke bot Telegram\n2. Cek log webhook atau database telegram_webhook_log\n3. Ambil chat_id dari log tersebut\n4. Masukkan chat_id ke field User ID Telegram');
}

// Add info button next to Telegram Chat ID field
document.addEventListener('DOMContentLoaded', function() {
    const telegramField = document.querySelector('input[name="telegram_chat_id"]');
    if (telegramField) {
        const infoBtn = document.createElement('button');
        infoBtn.type = 'button';
        infoBtn.className = 'btn btn-sm';
        infoBtn.innerHTML = '<i class="fa fa-info-circle"></i> Info';
        infoBtn.onclick = showTelegramInfo;
        infoBtn.style.marginLeft = '10px';
        telegramField.parentNode.appendChild(infoBtn);
    }
});
</script>
