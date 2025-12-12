<?php
/*
 * Admin Panel - Edit Agent
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agentId = $_GET['agent_id'] ?? 0;
$agentData = $agent->getAgentById($agentId);

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');

if (!$agentData) {
    echo "<script>alert('Agent tidak ditemukan'); window.location='./?hotspot=agent-list&session=$session';</script>";
    exit;
}

$error = '';
$success = '';

if (isset($_POST['update_agent'])) {
    $data = [
        'agent_name' => trim($_POST['agent_name']),
        'phone' => trim($_POST['phone']),
        'email' => trim($_POST['email']),
        'level' => $_POST['level'],
        'commission_amount' => floatval($_POST['commission_amount']),
        'status' => $_POST['status'],
        'notes' => trim($_POST['notes']),
        'telegram_chat_id' => trim($_POST['telegram_chat_id']),
        'telegram_username' => trim($_POST['telegram_username'])
    ];
    
    if (!empty($_POST['password'])) {
        $data['password'] = $_POST['password'];
    }
    
    // Check if telegram_chat_id already exists for another agent (if provided)
    if (!empty($data['telegram_chat_id']) && $data['telegram_chat_id'] != $agentData['telegram_chat_id']) {
        $existingTelegram = $agent->getAgentByTelegramChatId($data['telegram_chat_id']);
        if ($existingTelegram && $existingTelegram['id'] != $agentId) {
            $error = 'User ID Telegram sudah terdaftar untuk agent lain!';
        }
    }
    
    if (empty($error)) {
        $result = $agent->updateAgent($agentId, $data);
        
        if ($result['success']) {
            $success = 'Data agent berhasil diupdate!';
            $agentData = $agent->getAgentById($agentId);
        } else {
            $error = $result['message'];
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
    <h3><i class="fa fa-edit"></i> Edit Agent: <?= htmlspecialchars($agentData['agent_name']); ?></h3>
</div>
<div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-user"></i> Data Agent</h3>
            </div>
            <div class="card-body">

            <div class="form-group">
                <label>Kode Agent</label>
                <input type="text" class="form-control" value="<?= $agentData['agent_code']; ?>" disabled>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="agent_name" class="form-control" value="<?= $agentData['agent_name']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Nomor WhatsApp</label>
                    <input type="tel" name="phone" class="form-control" value="<?= $agentData['phone']; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= $agentData['email']; ?>">
                </div>
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>User ID Telegram</label>
                    <input type="text" name="telegram_chat_id" class="form-control" 
                           value="<?= htmlspecialchars($agentData['telegram_chat_id'] ?? ''); ?>" 
                           placeholder="123456789">
                    <small style="color: #666;">Chat ID Telegram agent (opsional)</small>
                </div>
                <div class="form-group">
                    <label>Username Telegram</label>
                    <input type="text" name="telegram_username" class="form-control" 
                           value="<?= htmlspecialchars($agentData['telegram_username'] ?? ''); ?>" 
                           placeholder="@username">
                    <small style="color: #666;">Username Telegram agent (opsional)</small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-cog"></i> Pengaturan</h3>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Level Agent</label>
                    <select name="level" class="form-control">
                        <option value="bronze" <?= $agentData['level'] == 'bronze' ? 'selected' : ''; ?>>Bronze</option>
                        <option value="silver" <?= $agentData['level'] == 'silver' ? 'selected' : ''; ?>>Silver</option>
                        <option value="gold" <?= $agentData['level'] == 'gold' ? 'selected' : ''; ?>>Gold</option>
                        <option value="platinum" <?= $agentData['level'] == 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Komisi (Rp)</label>
                    <input type="number" name="commission_amount" class="form-control" value="<?= $agentData['commission_amount']; ?>" min="0" step="1">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $agentData['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= $agentData['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?= $agentData['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Saldo Saat Ini</label>
                    <input type="text" class="form-control" value="Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?>" disabled>
                    <small style="color: #666;">Gunakan menu Topup untuk mengubah saldo</small>
                </div>
            </div>

            <div class="form-group">
                <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($agentData['notes']); ?></textarea>
            </div>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" name="update_agent" class="btn btn-primary btn-block">
                <i class="fa fa-save"></i> Update Data Agent
            </button>
            <br>
            <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn btn-block">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>
    </form>
</div>
</div>
</div>
</div>
