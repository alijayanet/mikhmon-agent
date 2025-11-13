<?php
/*
 * Billing Invoices Management - Skeleton UI
 */

include_once('./include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');

function findBillingCustomer(BillingService $service, string $type, string $value): ?array
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    switch ($type) {
        case 'pppoe_username':
            return $service->getCustomerByPppoeUsername($value);
        case 'service_number':
            return $service->getCustomerByServiceNumber($value);
        case 'name':
            foreach ($service->getCustomers(50) as $customer) {
                if (stripos($customer['name'] ?? '', $value) !== false) {
                    return $customer;
                }
            }
            return null;
        case 'phone':
        default:
            return $service->getCustomerByPhone($value);
    }
}

try {
    $db = getDBConnection();
} catch (Exception $e) {
    echo '<div class="alert bg-danger">Gagal terhubung ke database: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

try {
    $billingService = new BillingService();
} catch (Throwable $e) {
    echo '<div class="alert bg-danger">Gagal memuat layanan billing: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

$quickSearchCustomer = null;
$quickSearchInvoices = [];
$quickSearchType = 'phone';
$quickSearchValue = '';
$quickSuccess = null;
$quickError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quick_search'])) {
        $quickSearchType = $_POST['search_type'] ?? 'phone';
        $quickSearchValue = trim($_POST['search_value'] ?? '');

        if ($quickSearchValue === '') {
            $quickError = 'Masukkan nomor telepon, PPPoE username, atau service number.';
        } else {
            $customer = findBillingCustomer($billingService, $quickSearchType, $quickSearchValue);
            if ($customer) {
                $quickSearchCustomer = $customer;
                $quickSearchInvoices = $billingService->listInvoices([
                    'customer_id' => $customer['id'],
                    'statuses' => ['unpaid', 'overdue'],
                ], 20);

                if (empty($quickSearchInvoices)) {
                    $quickSuccess = 'Pelanggan ditemukan tetapi tidak ada tagihan yang masih menunggu pembayaran.';
                }
            } else {
                $quickError = 'Pelanggan tidak ditemukan.';
            }
        }
    } elseif (isset($_POST['quick_mark_paid'])) {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $quickSearchType = $_POST['context_search_type'] ?? 'phone';
        $quickSearchValue = trim($_POST['context_search_value'] ?? '');
        $channel = trim($_POST['payment_channel'] ?? 'manual_admin');
        $reference = trim($_POST['reference_number'] ?? '');

        if ($invoiceId <= 0) {
            $quickError = 'ID invoice tidak valid.';
        } else {
            $invoice = $billingService->getInvoiceById($invoiceId);
            if (!$invoice) {
                $quickError = 'Invoice tidak ditemukan.';
            } elseif ($invoice['status'] === 'paid') {
                $quickSuccess = 'Invoice tersebut sudah berstatus lunas sebelumnya.';
            } else {
                $customerId = (int)($invoice['customer_id'] ?? 0);
                $billingService->markInvoicePaid($invoiceId, [
                    'payment_channel' => $channel !== '' ? $channel : 'manual_admin',
                    'reference_number' => $reference !== '' ? $reference : null,
                    'paid_via' => 'admin_manual',
                    'paid_via_agent_id' => null,
                ]);

                $billingService->recordPayment($invoiceId, (float)$invoice['amount'], [
                    'method' => $channel !== '' ? $channel : 'manual_admin',
                    'notes' => 'Ditandai lunas melalui panel admin',
                    'created_by' => null,
                ]);

                if ($customerId > 0) {
                    $billingService->restoreCustomerProfile($customerId);
                }

                $quickSuccess = 'Invoice #' . $invoiceId . ' berhasil ditandai lunas.';
            }

            if ($quickSearchValue !== '') {
                $customer = findBillingCustomer($billingService, $quickSearchType, $quickSearchValue);
                if ($customer) {
                    $quickSearchCustomer = $customer;
                    $quickSearchInvoices = $billingService->listInvoices([
                        'customer_id' => $customer['id'],
                        'statuses' => ['unpaid', 'overdue'],
                    ], 20);
                }
            }
        }
    }
}

$currentPeriod = date('Y-m');

$summaryStmt = $db->prepare("SELECT 
        COUNT(*) AS total,
        SUM(status='paid') AS paid,
        SUM(status='unpaid') AS unpaid,
        SUM(amount) AS total_amount,
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS paid_amount
    FROM billing_invoices
    WHERE period = :period");
$summaryStmt->execute([':period' => $currentPeriod]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'paid' => 0, 'unpaid' => 0, 'total_amount' => 0, 'paid_amount' => 0];

$invoiceStmt = $db->prepare("SELECT bi.*, bc.name AS customer_name, bc.phone, bc.service_number, bp.profile_name
    FROM billing_invoices bi
    INNER JOIN billing_customers bc ON bi.customer_id = bc.id
    LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id
    ORDER BY bi.created_at DESC
    LIMIT 100");
$invoiceStmt->execute();
$invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

$customerOptions = $db->query("SELECT id, name FROM billing_customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$session = $_GET['session'] ?? ($_SESSION['session'] ?? '');
?>

<style>
.billing-module {
    font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
    font-size: 15px;
    line-height: 1.6;
    color: #1f2937;
}

.billing-module .card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.billing-module .card-header h3 {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .invoice-table th,
    .invoice-table td {
        font-size: 13px;
        white-space: nowrap;
    }

    .invoice-actions,
    .invoice-table .btn-group {
        justify-content: flex-start;
    }
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.invoice-table th, .invoice-table td {
    border: 1px solid #e5e7eb;
    padding: 10px;
}

.invoice-table th {
    background: #f3f4f6;
    font-weight: 600;
}

.invoice-table .action-cell {
    min-width: 110px;
}

.invoice-table .btn-group,
.invoice-actions {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
}

.badge-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.badge-status.paid { background: #d1fae5; color: #065f46; }
.badge-status.unpaid { background: #fee2e2; color: #991b1b; }
.badge-status.overdue { background: #fde68a; color: #92400e; }

.placeholder-actions {
    background: #f9fafb;
    border: 1px dashed #d1d5db;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.invoice-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1050;
    display: none;
    align-items: center;
    justify-content: center;
    transition: opacity 0.3s ease;
}

.modal-overlay.show {
    display: flex;
    opacity: 1;
}

.modal-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.25);
    width: 96%;
    max-width: 520px;
    padding: 24px;
    animation: slideDown 0.3s ease;
}

.modal-card h4 {
    margin-top: 0;
    margin-bottom: 18px;
    font-weight: 600;
    color: #112240;
}

.modal-card .form-group label {
    font-weight: 600;
    color: #1f2937;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.modal-meta {
    background: #f8fafc;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #0f172a;
}

@keyframes slideDown {
    from { transform: translateY(-12px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 768px) {
    .invoice-table th,
    .invoice-table td {
        font-size: 13px;
        white-space: nowrap;
    }

    .invoice-actions,
    .invoice-table .btn-group {
        justify-content: flex-start;
    }
}
</style>

<div class="billing-module">
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-file-text-o"></i> Tagihan & Pembayaran</h3>
            </div>
            <div class="card-body">
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h3><i class="fa fa-search"></i> Cari Tagihan Pelanggan</h3>
                        <small>Gunakan nomor telepon, PPPoE username, atau service number untuk menemukan invoice dan menandai pelunasan.</small>
                    </div>
                    <div class="card-body">
                        <?php if ($quickError): ?>
                            <div class="alert alert-danger"><strong>Gagal:</strong> <?= htmlspecialchars($quickError); ?></div>
                        <?php endif; ?>
                        <?php if ($quickSuccess): ?>
                            <div class="alert alert-success"><strong>Informasi:</strong> <?= htmlspecialchars($quickSuccess); ?></div>
                        <?php endif; ?>

                        <form method="post" class="mb-4">
                            <div class="row">
                                <div class="col-lg-3 col-12">
                                    <div class="form-group">
                                        <label for="quick_search_type">Cari Berdasarkan</label>
                                        <select name="search_type" id="quick_search_type" class="form-control">
                                            <option value="phone" <?= $quickSearchType === 'phone' ? 'selected' : ''; ?>>Nomor Telepon</option>
                                            <option value="pppoe_username" <?= $quickSearchType === 'pppoe_username' ? 'selected' : ''; ?>>PPPoE Username</option>
                                            <option value="service_number" <?= $quickSearchType === 'service_number' ? 'selected' : ''; ?>>Service Number</option>
                                            <option value="name" <?= $quickSearchType === 'name' ? 'selected' : ''; ?>>Nama Pelanggan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-5 col-12">
                                    <div class="form-group">
                                        <label for="quick_search_value">Nilai Pencarian</label>
                                        <input type="text" id="quick_search_value" name="search_value" class="form-control" placeholder="Contoh: 0812xxxx / user_pppoe" value="<?= htmlspecialchars($quickSearchValue); ?>" required>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-12 align-self-end">
                                    <button type="submit" name="quick_search" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Cari</button>
                                </div>
                            </div>
                        </form>

                        <?php if ($quickSearchCustomer): ?>
                            <div class="card" style="margin-bottom: 20px;">
                                <div class="card-header"><strong>Informasi Pelanggan</strong></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Nama:</strong> <?= htmlspecialchars($quickSearchCustomer['name']); ?></p>
                                            <p><strong>Telepon:</strong> <?= htmlspecialchars($quickSearchCustomer['phone'] ?? '-'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Service Number:</strong> <?= htmlspecialchars($quickSearchCustomer['service_number'] ?? '-'); ?></p>
                                            <p><strong>PPPoE Username:</strong> <?= htmlspecialchars($quickSearchCustomer['genieacs_pppoe_username'] ?? '-'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="invoice-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 110px;">Aksi</th>
                                            <th>ID</th>
                                            <th>Periode</th>
                                            <th>Nominal</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($quickSearchInvoices)): ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center; padding: 25px;">Tidak ada invoice yang perlu ditagih.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($quickSearchInvoices as $invoice): ?>
                                                <tr>
                                                    <td class="action-cell">
                                                        <div class="invoice-actions">
                                                            <button type="button" class="btn btn-sm btn-success" onclick="openQuickMarkPaidModal(<?= (int)$invoice['id']; ?>)">
                                                                <i class="fa fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-primary" onclick="openEditInvoiceModal(<?= (int)$invoice['id']; ?>)">
                                                                <i class="fa fa-pencil"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteInvoiceModal(<?= (int)$invoice['id']; ?>)">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td>#<?= (int)$invoice['id']; ?></td>
                                                    <td><?= htmlspecialchars($invoice['period']); ?></td>
                                                    <td>Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></td>
                                                    <td><?= date('d M Y', strtotime($invoice['due_date'])); ?></td>
                                                    <td><span class="badge-status <?= htmlspecialchars(strtolower($invoice['status'] ?? 'unpaid')); ?>"><?= htmlspecialchars(ucfirst($invoice['status'] ?? 'unpaid')); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-3 col-box-6">
                        <div class="box bg-blue bmh-75">
                            <h1><?= number_format($summary['total'], 0); ?>
                                <span style="font-size: 15px;">total</span>
                            </h1>
                            <div><i class="fa fa-list"></i> Periode <?= htmlspecialchars($currentPeriod); ?></div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-green bmh-75">
                            <h1><?= number_format($summary['paid'], 0); ?>
                                <span style="font-size: 15px;">paid</span>
                            </h1>
                            <div><i class="fa fa-check"></i> Sudah Dibayar</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-red bmh-75">
                            <h1><?= number_format($summary['unpaid'], 0); ?>
                                <span style="font-size: 15px;">unpaid</span>
                            </h1>
                            <div><i class="fa fa-times"></i> Belum Bayar</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-yellow bmh-75">
                            <h1><?= number_format($summary['paid_amount'], 0, ',', '.'); ?></h1>
                            <div><i class="fa fa-money"></i> Pendapatan</div>
                        </div>
                    </div>
                </div>

                <div class="alert bg-light" style="margin-bottom: 15px;">
                    <strong>Workflow:</strong> Invoice otomatis dibuat setiap tanggal 1, reminder dikirim H-3 & H+1, isolasi dijalankan bila status tetap <em>unpaid</em> setelah jatuh tempo.
                </div>

                <div class="table-responsive">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Periode</th>
                                <th>Pelanggan</th>
                                <th>Paket</th>
                                <th>Nominal</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Dibayar</th>
                                <th>Channel</th>
                                <th>Diupdate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding: 30px; color:#6b7280;">
                                        <i class="fa fa-info-circle"></i> Belum ada data invoice.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <?php $status = strtolower($invoice['status'] ?? 'unpaid'); ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($invoice['period']); ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($invoice['customer_name']); ?><br>
                                            <small><?= htmlspecialchars($invoice['phone'] ?? ''); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($invoice['profile_name'] ?? '-'); ?></td>
                                        <td>Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></td>
                                        <td><?= date('d M Y', strtotime($invoice['due_date'])); ?></td>
                                        <td>
                                            <span class="badge-status <?= $status; ?>">
                                                <?= ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $invoice['paid_at'] ? date('d M Y H:i', strtotime($invoice['paid_at'])) : '-'; ?>
                                        </td>
                                        <td><?= htmlspecialchars($invoice['payment_channel'] ?? '-'); ?></td>
                                        <td><?= date('d M Y H:i', strtotime($invoice['updated_at'] ?? $invoice['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="placeholder-actions">
                    <h4 style="margin-top:0;"><i class="fa fa-cogs"></i> Kelola Invoice</h4>
                    <div class="invoice-actions">
                        <button class="btn btn-primary" onclick="openCreateInvoiceModal()"><i class="fa fa-plus"></i> Buat Invoice</button>
                        <button class="btn btn-success" onclick="openManualMarkPaidModal()"><i class="fa fa-check"></i> Tandai Lunas</button>
                        <button class="btn btn-danger" onclick="openDeleteInvoiceModal()"><i class="fa fa-trash"></i> Hapus Invoice</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<div id="invoiceCreateModal" class="modal-overlay">
    <div class="modal-card">
        <h4><i class="fa fa-plus"></i> Buat Invoice Manual</h4>
        <form id="createInvoiceForm">
            <div class="form-group">
                <label for="create_customer_id">Pelanggan</label>
                <select id="create_customer_id" name="customer_id" class="form-control" required>
                    <option value="">-- pilih pelanggan --</option>
<?php foreach ($customerOptions as $customer): ?>
                    <option value="<?= (int)$customer['id']; ?>"><?= htmlspecialchars($customer['name']); ?></option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="create_period">Periode (YYYY-MM)</label>
                <input type="text" id="create_period" name="period" class="form-control" value="<?= htmlspecialchars($currentPeriod); ?>" required>
            </div>
            <div class="form-group">
                <label for="create_due_date">Jatuh Tempo</label>
                <input type="date" id="create_due_date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')); ?>" required>
            </div>
            <div class="form-group">
                <label for="create_amount">Nominal (Rp)</label>
                <input type="number" id="create_amount" name="amount" class="form-control" min="0" step="1000" required>
            </div>
            <div class="form-group">
                <label for="create_snapshot">Snapshot Profil (JSON opsional)</label>
                <textarea id="create_snapshot" name="profile_snapshot" rows="2" class="form-control" placeholder='{"price":150000,"speed":"30 Mbps"}'></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceCreateModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="invoiceEditModal" class="modal-overlay">
    <div class="modal-card">
        <h4><i class="fa fa-pencil"></i> Edit Invoice</h4>
        <div class="modal-meta" id="editInvoiceMeta"></div>
        <form id="editInvoiceForm">
            <input type="hidden" name="id" id="edit_invoice_id">
            <div class="form-group">
                <label for="edit_period">Periode</label>
                <input type="text" id="edit_period" name="period" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="edit_due_date">Jatuh Tempo</label>
                <input type="date" id="edit_due_date" name="due_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="edit_amount">Nominal (Rp)</label>
                <input type="number" id="edit_amount" name="amount" class="form-control" min="0" step="1000" required>
            </div>
            <div class="form-group">
                <label for="edit_status">Status</label>
                <select id="edit_status" name="status" class="form-control" required>
                    <option value="unpaid">Unpaid</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_payment_channel">Channel Pembayaran</label>
                <input type="text" id="edit_payment_channel" name="payment_channel" class="form-control" placeholder="Opsional">
            </div>
            <div class="form-group">
                <label for="edit_reference_number">Nomor Referensi</label>
                <input type="text" id="edit_reference_number" name="reference_number" class="form-control" placeholder="Opsional">
            </div>
            <div class="form-group">
                <label for="edit_paid_at">Tanggal Dibayar</label>
                <input type="datetime-local" id="edit_paid_at" name="paid_at" class="form-control">
            </div>
            <div class="form-group">
                <label for="edit_snapshot">Snapshot Profil (JSON)</label>
                <textarea id="edit_snapshot" name="profile_snapshot" rows="2" class="form-control" placeholder='{"price":150000}'></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceEditModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="invoiceDeleteModal" class="modal-overlay">
    <div class="modal-card">
        <h4><i class="fa fa-trash"></i> Hapus Invoice</h4>
        <div class="modal-meta" id="deleteInvoiceMeta">
            Pilih invoice yang ingin dihapus kemudian konfirmasi.
        </div>
        <form id="deleteInvoiceForm">
            <div class="form-group">
                <label for="delete_invoice_id">ID Invoice</label>
                <input type="number" id="delete_invoice_id" name="id" class="form-control" min="1" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceDeleteModal')">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Hapus</button>
            </div>
        </form>
    </div>
</div>

<div id="invoiceMarkPaidModal" class="modal-overlay">
    <div class="modal-card">
        <h4><i class="fa fa-check-circle"></i> Tandai Invoice Lunas</h4>
        <div class="modal-meta" id="markPaidInvoiceMeta"></div>
        <form id="markPaidInvoiceForm">
            <input type="hidden" id="mark_paid_invoice_id" name="id">
            <div class="form-group">
                <label for="mark_paid_amount_modal">Nominal Dibayar (Rp)</label>
                <input type="number" id="mark_paid_amount_modal" name="amount" class="form-control" min="0" step="1000">
            </div>
            <div class="form-group">
                <label for="mark_paid_channel_modal">Channel Pembayaran</label>
                <input type="text" id="mark_paid_channel_modal" name="payment_channel" class="form-control" placeholder="Transfer/Tripay">
            </div>
            <div class="form-group">
                <label for="mark_paid_reference_modal">Nomor Referensi</label>
                <input type="text" id="mark_paid_reference_modal" name="reference_number" class="form-control" placeholder="Opsional">
            </div>
            <div class="form-group">
                <label for="mark_paid_at_modal">Tanggal Dibayar</label>
                <input type="datetime-local" id="mark_paid_at_modal" name="paid_at" class="form-control" value="<?= date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="form-group">
                <label for="mark_paid_notes_modal">Catatan</label>
                <textarea id="mark_paid_notes_modal" name="notes" rows="2" class="form-control" placeholder="Opsional"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceMarkPaidModal')">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Tandai Lunas</button>
            </div>
        </form>
    </div>
</div>

<script src="./js/billing_forms.js"></script>
<script>
const sessionParam = '<?= urlencode($session); ?>';

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('show');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('show');
}

document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });
});

function openCreateInvoiceModal() {
    const form = document.getElementById('createInvoiceForm');
    form.reset();
    document.getElementById('create_period').value = '<?= htmlspecialchars($currentPeriod); ?>';
    document.getElementById('create_due_date').value = '<?= date('Y-m-d', strtotime('+7 days')); ?>';
    openModal('invoiceCreateModal');
}

document.getElementById('createInvoiceForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const form = event.target;
    const payload = {
        action: 'create',
        customer_id: form.customer_id.value,
        period: form.period.value,
        due_date: form.due_date.value,
        amount: form.amount.value,
        profile_snapshot: form.profile_snapshot.value
    };

    fetch('./billing/invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Invoice berhasil dibuat.');
                window.location.reload();
            } else {
                alert(data.message || 'Gagal membuat invoice');
            }
        });
});

function openEditInvoiceModal(invoiceId) {
    fetch(`./billing/invoices.php?id=${invoiceId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.data) {
                alert('Invoice tidak ditemukan');
                return;
            }

            const invoice = data.data;
            document.getElementById('edit_invoice_id').value = invoice.id;
            document.getElementById('edit_period').value = invoice.period;
            document.getElementById('edit_due_date').value = invoice.due_date;
            document.getElementById('edit_amount').value = invoice.amount;
            document.getElementById('edit_status').value = invoice.status;
            document.getElementById('edit_payment_channel').value = invoice.payment_channel || '';
            document.getElementById('edit_reference_number').value = invoice.reference_number || '';
            document.getElementById('edit_paid_at').value = invoice.paid_at ? invoice.paid_at.replace(' ', 'T') : '';
            document.getElementById('edit_snapshot').value = invoice.profile_snapshot ? JSON.stringify(JSON.parse(invoice.profile_snapshot), null, 2) : '';

            document.getElementById('editInvoiceMeta').innerHTML =
                `<strong>Invoice #${invoice.id}</strong><br>Pelanggan: ${invoice.customer_name || '-'}<br>Channel: ${invoice.payment_channel || '-'}<br>Dibuat: ${invoice.created_at}`;

            openModal('invoiceEditModal');
        });
}

document.getElementById('editInvoiceForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const form = event.target;

    let snapshotValue = form.profile_snapshot.value;
    if (snapshotValue) {
        try {
            JSON.parse(snapshotValue);
        } catch (e) {
            alert('Snapshot harus berupa JSON valid');
            return;
        }
    }

    const payload = {
        action: 'update',
        id: form.id.value,
        period: form.period.value,
        due_date: form.due_date.value,
        amount: form.amount.value,
        status: form.status.value,
        payment_channel: form.payment_channel.value,
        reference_number: form.reference_number.value,
        paid_at: form.paid_at.value,
        profile_snapshot: snapshotValue
    };

    fetch('./billing/invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Invoice berhasil diupdate.');
                window.location.reload();
            } else {
                alert(data.message || 'Gagal memperbarui invoice');
            }
        });
});

function openDeleteInvoiceModal(invoiceId = '') {
    document.getElementById('deleteInvoiceMeta').innerHTML = invoiceId
        ? `Anda akan menghapus invoice <strong>#${invoiceId}</strong>.` : 'Masukkan ID invoice yang ingin dihapus.';
    document.getElementById('delete_invoice_id').value = invoiceId;
    openModal('invoiceDeleteModal');
}

document.getElementById('deleteInvoiceForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const id = event.target.id.value;
    if (!id) {
        alert('ID invoice wajib diisi');
        return;
    }

    if (!confirm(`Yakin menghapus invoice #${id}?`)) {
        return;
    }

    fetch('./billing/invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Invoice berhasil dihapus.');
                window.location.reload();
            } else {
                alert(data.message || 'Gagal menghapus invoice');
            }
        });
});

function openQuickMarkPaidModal(invoiceId) {
    if (invoiceId) {
        fetch(`./billing/invoices.php?id=${invoiceId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.data) {
                    alert('Invoice tidak ditemukan');
                    return;
                }

                const invoice = data.data;
                document.getElementById('mark_paid_invoice_id').value = invoice.id;
                document.getElementById('mark_paid_amount_modal').value = invoice.amount;
                document.getElementById('mark_paid_channel_modal').value = invoice.payment_channel || '';
                document.getElementById('mark_paid_reference_modal').value = invoice.reference_number || '';
                document.getElementById('mark_paid_at_modal').value = new Date().toISOString().slice(0, 16);
                document.getElementById('mark_paid_notes_modal').value = '';

                document.getElementById('markPaidInvoiceMeta').innerHTML =
                    `<strong>Invoice #${invoice.id}</strong> (${invoice.period})<br>Pelanggan: ${invoice.customer_name || '-'}<br>Jatuh tempo: ${invoice.due_date}`;

                openModal('invoiceMarkPaidModal');
            });
    } else {
        document.getElementById('markPaidInvoiceMeta').innerHTML = 'Masukkan ID invoice yang ingin ditandai lunas.';
        document.getElementById('mark_paid_invoice_id').value = '';
        openModal('invoiceMarkPaidModal');
    }
}

function openManualMarkPaidModal() {
    openQuickMarkPaidModal('');
}

document.getElementById('markPaidInvoiceForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const form = event.target;
    if (!form.id.value) {
        alert('ID invoice wajib diisi');
        return;
    }

    const payload = {
        action: 'mark_paid',
        id: form.id.value,
        amount: form.amount.value,
        payment_channel: form.payment_channel.value,
        reference_number: form.reference_number.value,
        paid_at: form.paid_at.value,
        notes: form.notes.value
    };

    fetch('./billing/invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Invoice ditandai lunas.');
                window.location.reload();
            } else {
                alert(data.message || 'Gagal menandai invoice');
            }
        });
});

function openQuickMarkPaidModalFromList(invoiceId) {
    openQuickMarkPaidModal(invoiceId);
}
</script>
</div>
