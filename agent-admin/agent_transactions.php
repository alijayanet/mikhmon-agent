<?php
// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agentId = $_GET['agent_id'] ?? 0;
$agentData = $agentId ? $agent->getAgentById($agentId) : null;
$transactions = $agentId ? $agent->getTransactions($agentId, 100) : [];
$agents = $agent->getAllAgents();

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-topup {
    background: #d1fae5;
    color: #065f46;
}

.badge-generate {
    background: #fee2e2;
    color: #991b1b;
}

.badge-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #b91c1c;
}

.status-note {
    margin-top: 4px;
    font-size: 11px;
    color: #475569;
}

/* Mobile-specific styles */
.mobile-only {
    display: none;
}

.desktop-only {
    display: block;
}

/* Transaction card for mobile */
.transaction-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 12px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.transaction-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.transaction-date {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
}

.transaction-amount {
    font-size: 16px;
    font-weight: bold;
}

.amount-positive {
    color: #10b981;
}

.amount-negative {
    color: #ef4444;
}

.transaction-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
}

.transaction-label {
    color: #6b7280;
    font-weight: 500;
}

.transaction-value {
    text-align: right;
    font-weight: 600;
    color: #1f2937;
}

/* Responsive breakpoints */
@media (max-width: 768px) {
    .mobile-only {
        display: block;
    }
    
    .desktop-only {
        display: none;
    }
    
    /* Make table scrollable on small screens if needed */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Reduce padding on mobile */
    .card-body {
        padding: 10px;
    }
    
    .transaction-card {
        padding: 12px;
    }
}

@media (max-width: 480px) {
    .transaction-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .transaction-amount {
        font-size: 18px;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-history"></i> Transaksi Agent</h3>
</div>
<div class="card-body">
    <div class="card">
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="hotspot" value="agent-transactions">
                <input type="hidden" name="session" value="<?= $session; ?>">
                <div class="form-group">
                    <label>Pilih Agent</label>
                    <select name="agent_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Agent --</option>
                        <?php foreach ($agents as $agt): ?>
                        <option value="<?= $agt['id']; ?>" <?= $agentId == $agt['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($agentData && !empty($transactions)): ?>
    <div class="card">
        <div class="card-header">
            <h3><?= htmlspecialchars($agentData['agent_name']); ?> - Saldo: Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?></h3>
        </div>
        <div class="card-body">
        <!-- Desktop Table View -->
        <div class="desktop-only">
        <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>SN</th>
                    <th>Saldo Sebelum</th>
                    <th>Saldo Sesudah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trx): ?>
                <tr>
                    <td style="white-space: nowrap;"><?= date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                    <td><span class="badge badge-<?= $trx['transaction_type']; ?>"><?= ucfirst($trx['transaction_type']); ?></span></td>
                    <td style="font-weight: bold; color: <?= $trx['transaction_type'] == 'topup' ? '#10b981' : '#ef4444'; ?>; white-space: nowrap;">
                        <?= $trx['transaction_type'] == 'topup' ? '+' : '-'; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                    </td>
                    <td>
                        <?php if ($trx['transaction_type'] === 'digiflazz'): ?>
                            <?php
                                $statusRaw = strtolower($trx['digiflazz_status'] ?? '');
                                $statusClass = 'status-pending';
                                $statusLabel = 'PENDING';

                                if (!$statusRaw || in_array($statusRaw, ['success', 'sukses', 'berhasil', 'ok'])) {
                                    $statusClass = 'status-success';
                                    $statusLabel = 'BERHASIL';
                                } elseif (in_array($statusRaw, ['pending', 'process', 'processing', 'menunggu'])) {
                                    $statusClass = 'status-pending';
                                    $statusLabel = 'PENDING';
                                } else {
                                    $statusClass = 'status-failed';
                                    $statusLabel = strtoupper($statusRaw);
                                }
                            ?>
                            <span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                            <?php if (!empty($trx['digiflazz_message'])): ?>
                                <div class="status-note"><?= htmlspecialchars($trx['digiflazz_message']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($trx['transaction_type'] === 'digiflazz' && !empty($trx['digiflazz_serial'])): ?>
                            <span class="badge-status" style="background:#e0f2fe;color:#0f172a;font-family:'Courier New',monospace;letter-spacing:0.4px;"><?= htmlspecialchars($trx['digiflazz_serial']); ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;">Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></td>
                    <td style="white-space: nowrap;">Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></td>
                    <td><?= htmlspecialchars($trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </div>
        
        <!-- Mobile Card View -->
        <div class="mobile-only">
            <?php foreach ($transactions as $trx): ?>
            <div class="transaction-card">
                <div class="transaction-card-header">
                    <div class="transaction-date">
                        <i class="fa fa-calendar"></i> <?= date('d M Y H:i', strtotime($trx['created_at'])); ?>
                    </div>
                    <div class="transaction-amount <?= $trx['transaction_type'] == 'topup' ? 'amount-positive' : 'amount-negative'; ?>">
                        <?= $trx['transaction_type'] == 'topup' ? '+' : '-'; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                    </div>
                </div>
                
                <div class="transaction-row">
                    <span class="transaction-label">Tipe:</span>
                    <span class="transaction-value">
                        <span class="badge badge-<?= $trx['transaction_type']; ?>"><?= ucfirst($trx['transaction_type']); ?></span>
                    </span>
                </div>
                
                <?php if ($trx['transaction_type'] === 'digiflazz'): ?>
                <div class="transaction-row">
                    <span class="transaction-label">Status:</span>
                    <span class="transaction-value">
                        <?php
                            $statusRaw = strtolower($trx['digiflazz_status'] ?? '');
                            $statusClass = 'status-pending';
                            $statusLabel = 'PENDING';

                            if (!$statusRaw || in_array($statusRaw, ['success', 'sukses', 'berhasil', 'ok'])) {
                                $statusClass = 'status-success';
                                $statusLabel = 'BERHASIL';
                            } elseif (in_array($statusRaw, ['pending', 'process', 'processing', 'menunggu'])) {
                                $statusClass = 'status-pending';
                                $statusLabel = 'PENDING';
                            } else {
                                $statusClass = 'status-failed';
                                $statusLabel = strtoupper($statusRaw);
                            }
                        ?>
                        <span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                    </span>
                </div>
                
                <?php if (!empty($trx['digiflazz_serial'])): ?>
                <div class="transaction-row">
                    <span class="transaction-label">Serial Number:</span>
                    <span class="transaction-value">
                        <span class="badge-status" style="background:#e0f2fe;color:#0f172a;font-family:'Courier New',monospace;font-size:10px;"><?= htmlspecialchars($trx['digiflazz_serial']); ?></span>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($trx['digiflazz_message'])): ?>
                <div class="transaction-row">
                    <span class="transaction-label">Pesan:</span>
                    <span class="transaction-value" style="font-size:11px;color:#6b7280;">
                        <?= htmlspecialchars($trx['digiflazz_message']); ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="transaction-row">
                    <span class="transaction-label">Saldo Sebelum:</span>
                    <span class="transaction-value">Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></span>
                </div>
                
                <div class="transaction-row">
                    <span class="transaction-label">Saldo Sesudah:</span>
                    <span class="transaction-value">Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></span>
                </div>
                
                <?php if (!empty($trx['description']) || !empty($trx['profile_name'])): ?>
                <div class="transaction-row" style="border-top:1px solid #e5e7eb;margin-top:8px;padding-top:8px;">
                    <span class="transaction-label">Keterangan:</span>
                    <span class="transaction-value" style="font-size:12px;text-align:right;max-width:60%;">
                        <?= htmlspecialchars($trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>
