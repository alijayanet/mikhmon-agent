<?php
// Admin Digiflazz Transactions History
// No session_start() needed - already handled in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agents = $agent->getAllAgents();

$session = $_GET['session'] ?? (isset($session) ? $session : '');
$selectedAgentId = isset($_GET['agent_id']) && $_GET['agent_id'] !== '' ? (int)$_GET['agent_id'] : null;
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$limit = isset($_GET['limit']) && (int)$_GET['limit'] > 0 ? (int)$_GET['limit'] : 200;

$transactions = $agent->getDigiflazzTransactionsAdmin($selectedAgentId, $statusFilter, $limit);

$statusOptions = [
    '' => 'Semua Status',
    'success' => 'Berhasil',
    'pending' => 'Pending',
    'failed' => 'Gagal / Refund',
    'empty' => 'Tanpa Status'
];
?>

<!-- Include responsive tables CSS -->
<link rel="stylesheet" href="./css/responsive-tables.css">

<style>
.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-digiflazz {
    background: #e0f2fe;
    color: #0f172a;
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

.status-success { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-failed  { background: #fee2e2; color: #b91c1c; }

.status-note {
    margin-top: 4px;
    font-size: 11px;
    color: #475569;
}

.serial-chip {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(15,23,42,0.08);
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3><i class="fa fa-bolt"></i> Riwayat Transaksi Digiflazz</h3>
    </div>
    <div class="card-body">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-filter"></i> Filter</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-grid">
                    <input type="hidden" name="hotspot" value="digiflazz-transactions">
                    <input type="hidden" name="session" value="<?= htmlspecialchars($session); ?>">

                    <div class="form-group">
                        <label>Agent</label>
                        <select name="agent_id" class="form-control" onchange="this.form.submit()">
                            <option value="">Semua Agent</option>
                            <?php foreach ($agents as $agt): ?>
                                <option value="<?= (int)$agt['id']; ?>" <?= $selectedAgentId === (int)$agt['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value); ?>" <?= $statusFilter === $value ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jumlah Data</label>
                        <select name="limit" class="form-control" onchange="this.form.submit()">
                            <?php foreach ([50, 100, 200, 300, 500] as $opt): ?>
                                <option value="<?= $opt; ?>" <?= $limit === $opt ? 'selected' : ''; ?>><?= $opt; ?> baris</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="align-self: end;">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Terapkan</button>
                        <a href="./?hotspot=digiflazz-transactions&session=<?= htmlspecialchars($session); ?>" class="btn"><i class="fa fa-refresh"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3><i class="fa fa-list"></i> Daftar Transaksi</h3>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> Tidak ada transaksi yang cocok dengan filter.</div>
                <?php else: ?>
                    <!-- Desktop Table View -->
                    <div class="desktop-only">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Agent</th>
                                    <th>Produk</th>
                                    <th>Nomor</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>SN</th>
                                    <th>Ref ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                    <?php
                                        $statusRaw = strtolower($tx['digiflazz_status'] ?? '');
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

                                        $description = $tx['description'] ?: $tx['profile_name'];
                                        if ($description) {
                                            $description = preg_replace('/^digiflazz\s+order:\s*/i', '', $description);
                                        }
                                        if (!$description && !empty($tx['digiflazz_sku'])) {
                                            $description = $tx['digiflazz_sku'];
                                        }
                                    ?>
                                    <tr>
                                        <td><?= date('d M Y H:i', strtotime($tx['created_at'])); ?></td>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($tx['agent_name']); ?></div>
                                            <small style="color:#64748b;">Kode: <?= htmlspecialchars($tx['agent_code']); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($description); ?></td>
                                        <td><?= htmlspecialchars($tx['digiflazz_customer_no'] ?: '-'); ?></td>
                                        <td style="font-weight:bold; color:#ef4444;">-Rp <?= number_format($tx['amount'], 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                                            <?php if (!empty($tx['digiflazz_message'])): ?>
                                                <div class="status-note"><?= htmlspecialchars($tx['digiflazz_message']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($tx['digiflazz_serial'])): ?>
                                                <span class="serial-chip"><?= htmlspecialchars($tx['digiflazz_serial']); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-digiflazz"><?= htmlspecialchars($tx['voucher_username']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                    
                    <!-- Mobile Card View -->
                    <div class="mobile-only">
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                                $statusRaw = strtolower($tx['digiflazz_status'] ?? '');
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

                                $description = $tx['description'] ?: $tx['profile_name'];
                                if ($description) {
                                    $description = preg_replace('/^digiflazz\s+order:\s*/i', '', $description);
                                }
                                if (!$description && !empty($tx['digiflazz_sku'])) {
                                    $description = $tx['digiflazz_sku'];
                                }
                            ?>
                            <div class="transaction-card">
                                <div class="transaction-card-header">
                                    <div class="transaction-date">
                                        <i class="fa fa-calendar"></i> <?= date('d M Y H:i', strtotime($tx['created_at'])); ?>
                                    </div>
                                    <div class="transaction-amount amount-negative">
                                        -Rp <?= number_format($tx['amount'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                                
                                <div class="transaction-row">
                                    <span class="transaction-label">Agent:</span>
                                    <span class="transaction-value">
                                        <div style="font-weight:600;"><?= htmlspecialchars($tx['agent_name']); ?></div>
                                        <small style="color:#64748b;">Kode: <?= htmlspecialchars($tx['agent_code']); ?></small>
                                    </span>
                                </div>
                                
                                <div class="transaction-row">
                                    <span class="transaction-label">Produk:</span>
                                    <span class="transaction-value"><?= htmlspecialchars($description); ?></span>
                                </div>
                                
                                <div class="transaction-row">
                                    <span class="transaction-label">Nomor:</span>
                                    <span class="transaction-value"><?= htmlspecialchars($tx['digiflazz_customer_no'] ?: '-'); ?></span>
                                </div>
                                
                                <div class="transaction-row">
                                    <span class="transaction-label">Status:</span>
                                    <span class="transaction-value">
                                        <span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                                    </span>
                                </div>
                                
                                <?php if (!empty($tx['digiflazz_message'])): ?>
                                <div class="transaction-row">
                                    <span class="transaction-label">Pesan:</span>
                                    <span class="transaction-value" style="font-size:11px;color:#6b7280;">
                                        <?= htmlspecialchars($tx['digiflazz_message']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($tx['digiflazz_serial'])): ?>
                                <div class="transaction-row">
                                    <span class="transaction-label">Serial Number:</span>
                                    <span class="transaction-value">
                                        <span class="serial-chip"><?= htmlspecialchars($tx['digiflazz_serial']); ?></span>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="transaction-row" style="border-top:1px solid #e5e7eb;margin-top:8px;padding-top:8px;">
                                    <span class="transaction-label">Ref ID:</span>
                                    <span class="transaction-value">
                                        <span class="badge badge-digiflazz"><?= htmlspecialchars($tx['voucher_username']); ?></span>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
</div>
