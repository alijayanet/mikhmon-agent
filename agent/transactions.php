<?php
session_start();
error_reporting(0);

// Check if logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');

$agent = new Agent();
$agentId = $_SESSION['agent_id'];
$agentData = $agent->getAgentById($agentId);

// Get all transactions
$transactions = $agent->getTransactions($agentId, 100);

include_once('include_head.php');
include_once('include_nav.php');
?>

<style>
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

.serial-chip {
    display: inline-block;
    margin-top: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(15,23,42,0.08);
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.4px;
}

.dark .serial-chip {
    background: rgba(255,255,255,0.08);
    color: #f9fafb;
}

.desktop-only {
    display: block;
}

.mobile-only {
    display: none;
}

.transaction-card {
    background: #fff;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(15,23,42,0.08);
    border: 1px solid rgba(15,23,42,0.06);
}

.transaction-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.transaction-card .card-header .date {
    font-weight: 700;
    font-size: 13px;
    color: #0f172a;
}

.transaction-card .card-header .type-badge {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #fff;
    background: #475569;
}

.transaction-card .badge-topup {
    background: #10b981;
}

.transaction-card .badge-generate,
.transaction-card .badge-digiflazz {
    background: #2563eb;
}

.transaction-card .card-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
}

.transaction-card .label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #475569;
}

.transaction-card .value {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
}

.transaction-card .value-muted {
    color: #94a3b8;
    font-weight: 500;
}

.transaction-card .value-amount {
    font-size: 16px;
    font-weight: 700;
}

.transaction-card .value-amount.positive {
    color: #10b981;
}

.transaction-card .value-amount.negative {
    color: #ef4444;
}

.transaction-card .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.transaction-card .status-note {
    font-size: 12px;
    color: #475569;
    margin-top: 6px;
}

.transaction-card .print-btn {
    margin-top: 16px;
    width: 100%;
    justify-content: center;
    font-weight: 700;
    border-radius: 10px;
    padding: 10px 16px;
}

.dark .transaction-card {
    background: #111827;
    border-color: #1f2937;
    box-shadow: 0 12px 28px rgba(15,23,42,0.4);
}

.dark .transaction-card .label {
    color: #cbd5f5;
}

.dark .transaction-card .value {
    color: #f1f5f9;
}

.dark .transaction-card .value-muted {
    color: #94a3b8;
}

.payment-receipt-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 4000;
}

.payment-receipt-modal {
    background: #ffffff;
    border-radius: 18px;
    padding: 26px;
    max-width: 440px;
    width: 100%;
    position: relative;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.25);
    color: #0f172a;
    font-size: 15px;
    line-height: 1.6;
}

.payment-receipt-modal h4 {
    font-weight: 700;
    margin-bottom: 8px;
    font-size: 22px;
    color: #0b1f33;
}

.payment-receipt-modal p.subtitle {
    margin: 0 0 18px;
    color: #1e293b;
    font-size: 15px;
    font-weight: 500;
}

.receipt-close {
    position: absolute;
    top: 14px;
    right: 16px;
    border: none;
    background: none;
    font-size: 22px;
    color: #64748b;
    cursor: pointer;
}

.payment-receipt-summary dl {
    display: grid;
    grid-template-columns: 140px 1fr;
    row-gap: 8px;
    column-gap: 12px;
    margin: 0;
}

.payment-receipt-summary dt {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #1e293b;
    font-weight: 700;
}

.payment-receipt-summary dd {
    margin: 0;
    font-weight: 700;
    font-size: 17px;
    color: #0b1f33;
    letter-spacing: 0.2px;
}

.payment-receipt-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 22px;
}

.payment-receipt-actions button {
    flex: 1 1 calc(50% - 10px);
    border: none;
    border-radius: 10px;
    padding: 10px 14px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.payment-receipt-actions button i {
    margin-right: 6px;
    font-size: 15px;
}

#printNormalBtn {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff;
}

#printThermalBtn {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff;
}

#receiptDoneBtn {
    background: #f1f5f9;
    color: #1e293b;
    border: 1px solid #cbd5f5;
}

@media (max-width: 768px) {
    .desktop-only {
        display: none !important;
    }

    .mobile-only {
        display: block;
    }

    .transaction-card {
        display: block;
        width: 100%;
    }

    .table-responsive {
        overflow-x: hidden !important;
        width: 100% !important;
    }

    .transactions-table {
        display: none !important;
    }

    .transactions-table thead,
    .transactions-table tbody,
    .transactions-table tr,
    .transactions-table td {
        display: none !important;
    }
}

/* Mobile responsive table */
@media (max-width: 768px) {
    .content-wrapper {
        padding-left: 10px !important;
        padding-right: 10px !important;
        overflow-x: visible !important;
    }
    
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .col-12 {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    .card {
        margin-bottom: 10px !important;
        border-radius: 4px !important;
    }
    
    .table-responsive {
        overflow-x: auto !important;
        overflow-y: visible !important;
        -webkit-overflow-scrolling: touch !important;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        margin: 0 !important;
        -ms-overflow-style: -ms-autohiding-scrollbar !important;
        position: relative !important;
    }
    
    .table-responsive::-webkit-scrollbar {
        height: 8px !important;
        -webkit-appearance: none !important;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1 !important;
        border-radius: 4px !important;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #888 !important;
        border-radius: 4px !important;
    }
    
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555 !important;
    }
    
    .table-responsive table {
        width: 100% !important;
        min-width: 600px !important;
        font-size: 12px !important;
        margin-bottom: 0 !important;
        display: table !important;
        table-layout: auto !important;
    }
    
    .table-responsive th,
    .table-responsive td {
        padding: 8px 6px !important;
        white-space: nowrap !important;
        font-size: 11px !important;
    }
    
    .card-body {
        padding: 10px !important;
        overflow-x: visible !important;
        overflow-y: visible !important;
        max-width: 100% !important;
    }
    
    .card {
        overflow: visible !important;
        max-width: 100% !important;
    }
    
    .card-header {
        padding: 10px !important;
        font-size: 14px !important;
    }
    
    .card-header h3 {
        font-size: 16px !important;
        margin-bottom: 5px !important;
    }
    
    /* Make table more compact on mobile */
    .table-bordered {
        border-collapse: collapse !important;
    }
    
    .badge {
        padding: 3px 8px !important;
        font-size: 10px !important;
    }
}

</style>

<div class="row">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3><i class="fa fa-history"></i> Transaction History</h3>
        <div style="font-size: 14px;">Current Balance: <strong>Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?></strong></div>
    </div>
    <div class="card-body" style="padding: 15px;">
        <?php if (!empty($transactions)): ?>
        <div class="desktop-only">
        <div class="table-responsive" style="overflow-x: visible !important; -webkit-overflow-scrolling: touch !important; width: 100% !important; display: block !important; -ms-overflow-style: -ms-autohiding-scrollbar !important;">
        <table class="table table-bordered table-hover transactions-table" style="width: 100% !important; margin-bottom: 0 !important; display: table !important;">
            <thead>
                <tr>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Date & Time</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Type</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Amount</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Status</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">SN</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Balance Before</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Balance After</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Description</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Print</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trx): ?>
                <?php
                    $statusRaw = strtolower($trx['digiflazz_status'] ?? '');
                    $statusClass = 'status-success';
                    $statusLabel = 'SELESAI';
                    $statusNote = $trx['digiflazz_message'] ?? '';

                    if ($trx['transaction_type'] === 'digiflazz') {
                        $statusClass = 'status-pending';
                        $statusLabel = 'PENDING';

                        if (!$statusRaw || in_array($statusRaw, ['success', 'sukses', 'berhasil', 'ok'])) {
                            $statusClass = 'status-success';
                            $statusLabel = 'BERHASIL';
                        } elseif (in_array($statusRaw, ['pending', 'process', 'processing', 'menunggu'])) {
                            $statusClass = 'status-pending';
                            $statusLabel = 'PENDING';
                        } elseif ($statusRaw) {
                            $statusClass = 'status-failed';
                            $statusLabel = strtoupper($statusRaw);
                        }
                    }

                    $isDebit = ($trx['transaction_type'] !== 'topup');
                    $amountPrefix = $isDebit ? '-' : '+';
                    $amountColor = $isDebit ? '#ef4444' : '#10b981';
                    $sellPrice = isset($trx['digiflazz_sell_price']) && $trx['digiflazz_sell_price'] !== null ? (int)$trx['digiflazz_sell_price'] : abs((int)$trx['amount']);
                    $basePrice = isset($trx['digiflazz_base_price']) && $trx['digiflazz_base_price'] !== null ? (int)$trx['digiflazz_base_price'] : $sellPrice;
                    $descriptionValue = $trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username']);
                    $billingCustomerName = $trx['billing_customer_name'] ?? '';
                    $billingProfileName = $trx['billing_profile_name'] ?? '';
                    $billingInvoiceAmount = isset($trx['billing_invoice_amount']) ? (float)$trx['billing_invoice_amount'] : null;
                    $billingInvoiceStatus = $trx['billing_invoice_status'] ?? '';
                    $billingInvoicePeriod = $trx['billing_invoice_period'] ?? '';
                    $billingInvoiceAmountAttr = $billingInvoiceAmount !== null ? (string)$billingInvoiceAmount : '';

                ?>
                <tr>
                    <td data-label="Date" style="padding: 8px 6px; font-size: 11px; white-space: nowrap;"><?= date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                    <td data-label="Type" style="padding: 8px 6px; font-size: 11px;">
                        <span class="badge badge-<?= $trx['transaction_type']; ?>">
                            <?= ucfirst($trx['transaction_type']); ?>
                        </span>
                    </td>
                    <td data-label="Amount" style="padding: 8px 6px; font-size: 11px; font-weight: bold; color: <?= $amountColor ?>; white-space: nowrap;">
                        <?= $amountPrefix; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                    </td>
                    <td data-label="Status" style="padding: 8px 6px; font-size: 11px; white-space: nowrap;">
                        <?php if ($trx['transaction_type'] === 'digiflazz'): ?>
                            <span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                            <?php if (!empty($statusNote)): ?>
                                <div class="status-note"><?= htmlspecialchars($statusNote); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-status status-success">SUKSES</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="SN" style="padding: 8px 6px; font-size: 11px; white-space: nowrap;">
                        <?php if ($trx['transaction_type'] === 'digiflazz' && !empty($trx['digiflazz_serial'])): ?>
                            <span class="serial-chip"><?= htmlspecialchars($trx['digiflazz_serial']); ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td data-label="Saldo Sebelum" style="padding: 8px 6px; font-size: 11px; white-space: nowrap;">Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></td>
                    <td data-label="Saldo Sesudah" style="padding: 8px 6px; font-size: 11px; white-space: nowrap;">Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></td>
                    <td data-label="Keterangan" style="padding: 8px 6px; font-size: 11px; white-space: normal;">&nbsp;<?= htmlspecialchars($descriptionValue); ?></td>
                    <td data-label="Print" style="padding: 8px 6px; font-size: 11px; white-space: nowrap; text-align: center;">
                        <button
                            class="btn btn-primary btn-sm print-btn"
                            data-transaction-print
                            data-id="<?= htmlspecialchars((string)($trx['id'] ?? '')); ?>"
                            data-type="<?= htmlspecialchars($trx['transaction_type']); ?>"
                            data-type-label="<?= htmlspecialchars(ucfirst($trx['transaction_type'])); ?>"
                            data-created-at="<?= htmlspecialchars($trx['created_at']); ?>"
                            data-amount="<?= htmlspecialchars((string)(float)$trx['amount']); ?>"
                            data-direction="<?= $isDebit ? 'debit' : 'credit'; ?>"
                            data-balance-before="<?= htmlspecialchars((string)(float)$trx['balance_before']); ?>"
                            data-balance-after="<?= htmlspecialchars((string)(float)$trx['balance_after']); ?>"
                            data-description="<?= htmlspecialchars($descriptionValue); ?>"
                            data-status-label="<?= htmlspecialchars($statusLabel); ?>"
                            data-status-note="<?= htmlspecialchars($statusNote); ?>"
                            data-customer-no="<?= htmlspecialchars($trx['digiflazz_customer_no'] ?? ''); ?>"
                            data-customer-name="<?= htmlspecialchars($trx['digiflazz_customer_name'] ?? ''); ?>"
                            data-serial="<?= htmlspecialchars($trx['digiflazz_serial'] ?? ''); ?>"
                            data-sell-price="<?= htmlspecialchars((string)$sellPrice); ?>"
                            data-base-price="<?= htmlspecialchars((string)$basePrice); ?>"
                            data-voucher="<?= htmlspecialchars($trx['voucher_username'] ?? ''); ?>"
                            data-billing-customer="<?= htmlspecialchars($billingCustomerName); ?>"
                            data-billing-profile="<?= htmlspecialchars($billingProfileName); ?>"
                            data-billing-amount="<?= htmlspecialchars($billingInvoiceAmountAttr); ?>"
                            data-billing-status="<?= htmlspecialchars($billingInvoiceStatus); ?>"
                            data-billing-period="<?= htmlspecialchars($billingInvoicePeriod); ?>"
                        >
                            <i class="fa fa-print"></i> Cetak
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </div>

        <div class="mobile-only">
            <?php foreach ($transactions as $trx): ?>
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
                    } elseif ($statusRaw) {
                        $statusClass = 'status-failed';
                        $statusLabel = strtoupper($statusRaw);
                    }

                    $isDebit = ($trx['transaction_type'] !== 'topup');
                    $amountPrefix = $isDebit ? '-' : '+';
                    $amountColorClass = $isDebit ? 'negative' : 'positive';
                    $statusNote = $trx['digiflazz_message'] ?? '';
                    $sellPrice = isset($trx['digiflazz_sell_price']) && $trx['digiflazz_sell_price'] !== null ? (int)$trx['digiflazz_sell_price'] : abs((int)$trx['amount']);
                    $basePrice = isset($trx['digiflazz_base_price']) && $trx['digiflazz_base_price'] !== null ? (int)$trx['digiflazz_base_price'] : $sellPrice;
                    $descriptionValue = $trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username']);
                    $billingCustomerName = $trx['billing_customer_name'] ?? '';
                    $billingProfileName = $trx['billing_profile_name'] ?? '';
                    $billingInvoiceAmount = isset($trx['billing_invoice_amount']) ? (float)$trx['billing_invoice_amount'] : null;
                    $billingInvoiceStatus = $trx['billing_invoice_status'] ?? '';
                    $billingInvoicePeriod = $trx['billing_invoice_period'] ?? '';
                    $billingInvoiceAmountAttr = $billingInvoiceAmount !== null ? (string)$billingInvoiceAmount : '';
                ?>
                <div class="transaction-card">
                    <div class="card-header">
                        <div class="date"><?= date('d M Y H:i', strtotime($trx['created_at'])); ?></div>
                        <div class="type-badge badge-<?= htmlspecialchars($trx['transaction_type']); ?>">
                            <?= ucfirst($trx['transaction_type']); ?>
                        </div>
                    </div>

                    <div class="card-row">
                        <div class="label">Amount</div>
                        <div class="value value-amount <?= $amountColorClass; ?>"><?= $amountPrefix; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?></div>
                    </div>

                    <?php if ($trx['transaction_type'] === 'digiflazz'): ?>
                        <div class="card-row">
                            <div class="label">Status</div>
                            <div>
                                <span class="status-badge <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                                <?php if (!empty($statusNote)): ?>
                                    <div class="status-note"><?= htmlspecialchars($statusNote); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($trx['digiflazz_customer_no'])): ?>
                            <div class="card-row">
                                <div class="label">Nomor Tujuan</div>
                                <div class="value"><?= htmlspecialchars($trx['digiflazz_customer_no']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($trx['digiflazz_serial'])): ?>
                            <div class="card-row">
                                <div class="label">Serial Number</div>
                                <div class="value serial-chip"><?= htmlspecialchars($trx['digiflazz_serial']); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card-row">
                        <div class="label">Saldo Sebelum</div>
                        <div class="value value-muted">Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="card-row">
                        <div class="label">Saldo Sesudah</div>
                        <div class="value value-muted">Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="card-row">
                        <div class="label">Keterangan</div>
                        <div class="value"><?= htmlspecialchars($descriptionValue); ?></div>
                    </div>

                    <?php if ($trx['transaction_type'] === 'digiflazz'): ?>
                        <div class="card-row">
                            <div class="label">Ref ID</div>
                            <div class="value"><?= htmlspecialchars($trx['voucher_username']); ?></div>
                        </div>
                        <div class="card-row">
                            <div class="label">Modal</div>
                            <div class="value value-muted">Rp <?= number_format($basePrice, 0, ',', '.'); ?></div>
                        </div>
                        <div class="card-row">
                            <div class="label">Harga Jual</div>
                            <div class="value value-muted">Rp <?= number_format($sellPrice, 0, ',', '.'); ?></div>
                        </div>
                    <?php endif; ?>

                    <button
                        class="print-btn"
                        data-transaction-print
                        data-id="<?= htmlspecialchars((string)($trx['id'] ?? '')); ?>"
                        data-type="<?= htmlspecialchars($trx['transaction_type']); ?>"
                        data-type-label="<?= htmlspecialchars(ucfirst($trx['transaction_type'])); ?>"
                        data-created-at="<?= htmlspecialchars($trx['created_at']); ?>"
                        data-amount="<?= htmlspecialchars((string)(float)$trx['amount']); ?>"
                        data-direction="<?= $isDebit ? 'debit' : 'credit'; ?>"
                        data-balance-before="<?= htmlspecialchars((string)(float)$trx['balance_before']); ?>"
                        data-balance-after="<?= htmlspecialchars((string)(float)$trx['balance_after']); ?>"
                        data-description="<?= htmlspecialchars($descriptionValue); ?>"
                        data-status-label="<?= htmlspecialchars($statusLabel); ?>"
                        data-status-note="<?= htmlspecialchars($statusNote); ?>"
                        data-customer-no="<?= htmlspecialchars($trx['digiflazz_customer_no'] ?? ''); ?>"
                        data-customer-name="<?= htmlspecialchars($trx['digiflazz_customer_name'] ?? ''); ?>"
                        data-serial="<?= htmlspecialchars($trx['digiflazz_serial'] ?? ''); ?>"
                        data-sell-price="<?= htmlspecialchars((string)$sellPrice); ?>"
                        data-base-price="<?= htmlspecialchars((string)$basePrice); ?>"
                        data-voucher="<?= htmlspecialchars($trx['voucher_username'] ?? ''); ?>"
                        data-billing-customer="<?= htmlspecialchars($billingCustomerName); ?>"
                        data-billing-profile="<?= htmlspecialchars($billingProfileName); ?>"
                        data-billing-amount="<?= htmlspecialchars($billingInvoiceAmountAttr); ?>"
                        data-billing-status="<?= htmlspecialchars($billingInvoiceStatus); ?>"
                        data-billing-period="<?= htmlspecialchars($billingInvoicePeriod); ?>"
                    >
                        <i class="fa fa-print"></i> Cetak
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No transactions found.
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<div class="payment-receipt-overlay" id="transactionReceiptOverlay">
    <div class="payment-receipt-modal" id="transactionReceiptModal">
        <button type="button" class="receipt-close" id="transactionReceiptClose" aria-label="Tutup">&times;</button>
        <h4>Detail Transaksi</h4>
        <p class="subtitle">Cetak struk transaksi untuk pelanggan.</p>
        <div class="payment-receipt-summary" id="transactionReceiptContent"></div>
        <div class="payment-receipt-actions">
            <button type="button" id="printNormalBtn"><i class="fa fa-print"></i> Print Normal</button>
            <button type="button" id="printThermalBtn"><i class="fa fa-print"></i> Thermal 58mm</button>
            <button type="button" id="receiptDoneBtn">Selesai</button>
        </div>
    </div>
</div>

<script>
(function() {
    const overlay = document.getElementById('transactionReceiptOverlay');
    const modal = document.getElementById('transactionReceiptModal');
    const contentBox = document.getElementById('transactionReceiptContent');
    const closeBtn = document.getElementById('transactionReceiptClose');
    const doneBtn = document.getElementById('receiptDoneBtn');
    const printNormalBtn = document.getElementById('printNormalBtn');
    const printThermalBtn = document.getElementById('printThermalBtn');
    let currentPayload = null;

    if (!overlay || !contentBox) {
        return;
    }

    function formatCurrency(number) {
        const value = Number(number || 0);
        return new Intl.NumberFormat('id-ID').format(value);
    }

    function formatDateTime(isoString) {
        try {
            const date = new Date(isoString);
            if (Number.isNaN(date.getTime())) {
                return isoString;
            }
            return date.toLocaleString('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (err) {
            return isoString;
        }
    }

    function safeValue(value, fallback = '-') {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }
        return value;
    }

    function buildReceiptHtml(data) {
        const isDebit = data.direction === 'debit';
        const amountDisplay = `${isDebit ? '-' : '+'}Rp ${formatCurrency(data.amount)}`;
        const statusNoteRow = data.status_note ? `<dt>Catatan</dt><dd>${safeValue(data.status_note)}</dd>` : '';
        const serialRow = data.serial ? `<dt>Serial</dt><dd>${safeValue(data.serial)}</dd>` : '';
        const refRow = data.voucher_username ? `<dt>Ref ID</dt><dd>${safeValue(data.voucher_username)}</dd>` : '';
        const customerRow = data.customer_no ? `<dt>Nomor Tujuan</dt><dd>${safeValue(data.customer_no)}</dd>` : '';
        const customerNameRow = data.customer_name ? `<dt>Nama Pelanggan</dt><dd>${safeValue(data.customer_name)}</dd>` : '';
        const billingCustomerRow = data.billing_customer_name ? `<dt>Nama Pelanggan</dt><dd>${safeValue(data.billing_customer_name)}</dd>` : '';
        const billingProfileRow = data.billing_profile_name ? `<dt>Paket Pelanggan</dt><dd>${safeValue(data.billing_profile_name)}</dd>` : '';
        const billingAmountRow = data.billing_invoice_amount ? `<dt>Total Tagihan</dt><dd>Rp ${formatCurrency(data.billing_invoice_amount)}</dd>` : '';
        const billingStatusRow = data.billing_invoice_status ? `<dt>Status Tagihan</dt><dd>${safeValue(data.billing_invoice_status)}</dd>` : '';
        const billingPeriodRow = data.billing_invoice_period ? `<dt>Periode</dt><dd>${safeValue(data.billing_invoice_period)}</dd>` : '';
        const showVoucherPricing = data.type === 'digiflazz' || data.type === 'voucher';
        const sellPriceRow = showVoucherPricing && data.sell_price ? `<dt>Harga Jual</dt><dd>Rp ${formatCurrency(data.sell_price)}</dd>` : '';

        return `
            <dl>
                <dt>Jenis Transaksi</dt><dd>${safeValue(data.type_label)}</dd>
                <dt>Tanggal</dt><dd>${formatDateTime(data.created_at)}</dd>
                <dt>Jumlah</dt><dd><strong>${amountDisplay}</strong></dd>
                <dt>Status</dt><dd>${safeValue(data.status_label)}</dd>
                ${statusNoteRow}
                <dt>Keterangan</dt><dd>${safeValue(data.description)}</dd>
                ${billingCustomerRow}
                ${billingProfileRow}
                ${billingPeriodRow}
                ${billingAmountRow}
                ${billingStatusRow}
                ${customerRow}
                ${customerNameRow}
                ${serialRow}
                ${refRow}
                ${sellPriceRow}
            </dl>
        `;
    }

    function openReceipt(payload) {
        currentPayload = payload;
        contentBox.innerHTML = buildReceiptHtml(payload);
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeReceipt() {
        overlay.style.display = 'none';
        document.body.style.removeProperty('overflow');
        currentPayload = null;
    }

    function buildPrintDocument(payload, options = {}) {
        const thermal = Boolean(options.thermal);
        const isDebit = payload.direction === 'debit';
        const signedAmount = `${isDebit ? '-' : '+'}Rp ${formatCurrency(payload.amount)}`;
        const base = formatCurrency(payload.base_price || 0);
        const sell = formatCurrency(payload.sell_price || 0);

        const detailRows = [
            `<div class="row"><span class="label">Tanggal</span><span class="value">${formatDateTime(payload.created_at)}</span></div>`,
            `<div class="row"><span class="label">Jenis</span><span class="value">${safeValue(payload.type_label)}</span></div>`,
            `<div class="row"><span class="label">Jumlah</span><span class="value">${signedAmount}</span></div>`
        ];

        if (payload.status_label) {
            detailRows.push(`<div class="row"><span class="label">Status</span><span class="value">${safeValue(payload.status_label)}</span></div>`);
        }
        if (payload.status_note) {
            detailRows.push(`<div class="row"><span class="label">Catatan</span><span class="value">${safeValue(payload.status_note)}</span></div>`);
        }
        if (payload.description) {
            detailRows.push(`<div class="row"><span class="label">Keterangan</span><span class="value">${safeValue(payload.description)}</span></div>`);
        }
        if (payload.customer_no) {
            detailRows.push(`<div class="row"><span class="label">Nomor Tujuan</span><span class="value">${safeValue(payload.customer_no)}</span></div>`);
        }
        if ((payload.type === 'digiflazz' || payload.type === 'voucher') && payload.sell_price) {
            detailRows.push(`<div class="row"><span class="label">Harga Jual</span><span class="value">Rp ${sell}</span></div>`);
        }
        if (payload.billing_customer_name) {
            detailRows.push(`<div class="row"><span class="label">Nama Pelanggan</span><span class="value">${safeValue(payload.billing_customer_name)}</span></div>`);
        }
        if (payload.billing_profile_name) {
            detailRows.push(`<div class="row"><span class="label">Paket</span><span class="value">${safeValue(payload.billing_profile_name)}</span></div>`);
        }
        if (payload.billing_invoice_period) {
            detailRows.push(`<div class="row"><span class="label">Periode</span><span class="value">${safeValue(payload.billing_invoice_period)}</span></div>`);
        }
        if (payload.billing_invoice_amount) {
            detailRows.push(`<div class="row"><span class="label">Total Tagihan</span><span class="value">Rp ${formatCurrency(payload.billing_invoice_amount)}</span></div>`);
        }
        if (payload.billing_invoice_status) {
            detailRows.push(`<div class="row"><span class="label">Status Tagihan</span><span class="value">${safeValue(payload.billing_invoice_status)}</span></div>`);
        }

        if (payload.status_label) {
            detailRows.push(`<div class="row"><span class="label">Status</span><span class="value">${safeValue(payload.status_label)}</span></div>`);
        }
        if ((payload.type === 'digiflazz' || payload.type === 'voucher') && payload.sell_price) {
            detailRows.push(`<div class="row"><span class="label">Harga Jual</span><span class="value">Rp ${sell}</span></div>`);
        }

        if (thermal) {
            return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Struk Transaksi</title>
    <style>
        @page { size: 58mm auto; margin: 3mm; }
        body { font-family: 'Courier New', monospace; width: 58mm; margin: 0; padding: 0; font-size: 12px; }
        .wrapper { padding: 6px; text-align: center; }
        .title { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
        .separator { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; margin-bottom: 4px; text-align: left; }
        .label { font-weight: bold; text-align: left; }
        .value { text-align: right; word-break: break-word; max-width: 32mm; }
        .footer { margin-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="title">STRUK TRANSAKSI</div>
        <div class="separator"></div>
        ${detailRows.join('')}
        <div class="separator"></div>
        <div class="footer">Dicetak dari sistem MikhMon Agent.</div>
    </div>
</body>
</html>`;
        }

        return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Struk Transaksi</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 28px; color: #0f172a; }
        h1 { margin: 0 0 16px; font-size: 24px; }
        .info { margin-bottom: 20px; }
        .info div { margin-bottom: 6px; font-size: 14px; }
        .info .highlight { font-weight: 700; }
        .detail { border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .row .label { font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; }
        .separator { border-top: 1px solid #e2e8f0; margin: 16px 0; }
        .footer { margin-top: 20px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Struk Transaksi</h1>
    <div class="info">
        <div><span class="highlight">Jenis:</span> ${safeValue(payload.type_label)}</div>
        <div><span class="highlight">Tanggal:</span> ${formatDateTime(payload.created_at)}</div>
        <div><span class="highlight">Jumlah:</span> ${signedAmount}</div>
        <div><span class="highlight">Saldo:</span> ${formatCurrency(payload.balance_before)} ➜ ${formatCurrency(payload.balance_after)}</div>
    </div>
    <div class="detail">
        ${detailRows.join('')}
    </div>
    <div class="footer">Struk otomatis - MikhMon Agent.</div>
</body>
</html>`;
    }

    function handlePrint(options) {
        if (!currentPayload) {
            return;
        }
        const html = buildPrintDocument(currentPayload, options);
        const popup = window.open('', '_blank');
        if (!popup) {
            alert('Popup diblokir oleh browser. Mohon izinkan popup untuk mencetak.');
            return;
        }
        popup.document.write(html);
        popup.document.close();
        popup.focus();
        setTimeout(() => {
            popup.print();
        }, 250);
    }

    function attachHandlers() {
        document.addEventListener('click', function(event) {
            const button = event.target.closest('[data-transaction-print]');
            if (!button) {
                return;
            }

            const payload = {
                id: button.getAttribute('data-id') || null,
                type: button.getAttribute('data-type') || '',
                type_label: button.getAttribute('data-type-label') || '',
                created_at: button.getAttribute('data-created-at') || '',
                amount: Number(button.getAttribute('data-amount') || 0),
                direction: button.getAttribute('data-direction') || 'debit',
                balance_before: Number(button.getAttribute('data-balance-before') || 0),
                balance_after: Number(button.getAttribute('data-balance-after') || 0),
                description: button.getAttribute('data-description') || '',
                status_label: button.getAttribute('data-status-label') || '',
                status_note: button.getAttribute('data-status-note') || '',
                customer_no: button.getAttribute('data-customer-no') || '',
                customer_name: button.getAttribute('data-customer-name') || '',
                serial: button.getAttribute('data-serial') || '',
                sell_price: Number(button.getAttribute('data-sell-price') || 0),
                base_price: Number(button.getAttribute('data-base-price') || 0),
                voucher_username: button.getAttribute('data-voucher') || '',
                billing_customer_name: button.getAttribute('data-billing-customer') || '',
                billing_profile_name: button.getAttribute('data-billing-profile') || '',
                billing_invoice_amount: Number(button.getAttribute('data-billing-amount') || 0),
                billing_invoice_status: button.getAttribute('data-billing-status') || '',
                billing_invoice_period: button.getAttribute('data-billing-period') || ''
            };

            openReceipt(payload);
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeReceipt);
        }
        if (doneBtn) {
            doneBtn.addEventListener('click', closeReceipt);
        }
        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) {
                closeReceipt();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && overlay.style.display === 'flex') {
                closeReceipt();
            }
        });

        if (printNormalBtn) {
            printNormalBtn.addEventListener('click', function() {
                handlePrint({ thermal: false });
            });
        }

        if (printThermalBtn) {
            printThermalBtn.addEventListener('click', function() {
                handlePrint({ thermal: true });
            });
        }
    }

    attachHandlers();
})();
</script>

<?php include_once('include_foot.php'); ?>
