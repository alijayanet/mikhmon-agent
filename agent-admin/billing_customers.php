<?php
/*
 * Billing Customers Management - Skeleton UI
 */

include_once('./include/db_config.php');

try {
    $db = getDBConnection();
} catch (Exception $e) {
    echo '<div class="alert bg-danger">Gagal terhubung ke database: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

$session = $_GET['session'] ?? ($_SESSION['session'] ?? '');

// Handle search parameters
$searchType = $_GET['search_type'] ?? '';
$searchValue = trim($_GET['search_value'] ?? '');

// Build query based on search parameters
if (!empty($searchValue) && in_array($searchType, ['name', 'phone', 'service_number', 'pppoe'])) {
    $sql = "SELECT bc.*, bp.profile_name FROM billing_customers bc LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id WHERE ";
    
    switch ($searchType) {
        case 'name':
            $sql .= "bc.name LIKE :searchValue";
            break;
        case 'phone':
            $sql .= "bc.phone LIKE :searchValue";
            break;
        case 'service_number':
            $sql .= "bc.service_number LIKE :searchValue";
            break;
        case 'pppoe':
            $sql .= "bc.genieacs_pppoe_username LIKE :searchValue";
            break;
    }
    
    $sql .= " ORDER BY bc.created_at DESC";
    $customersStmt = $db->prepare($sql);
    $customersStmt->execute([':searchValue' => "%{$searchValue}%"]);
} else {
    $customersStmt = $db->query("SELECT bc.*, bp.profile_name FROM billing_customers bc LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id ORDER BY bc.created_at DESC");
}
$customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

$profilesStmt = $db->query("SELECT id, profile_name FROM billing_profiles ORDER BY profile_name ASC");
$profileOptions = $profilesStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCustomers = count($customers);
$activeCustomers = count(array_filter($customers, static fn ($row) => ($row['status'] ?? '') === 'active'));
$isolatedCustomers = count(array_filter($customers, static fn ($row) => (int)($row['is_isolated'] ?? 0) === 1));
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


.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.customer-table .action-cell {
    min-width: 110px;
}

.customer-table .btn-group {
    display: inline-flex;
    gap: 4px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .customer-table th,
    .customer-table td {
        font-size: 13px;
        white-space: nowrap;
    }

    .customer-table .btn-group {
        justify-content: flex-start;
    }
}

.customer-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.customer-table th,
.customer-table td {
    padding: 10px;
    border: 1px solid #e5e7eb;
    text-align: left;
}

.customer-table th {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.2px;
}

.customer-table td {
    color: #0b1f4b;
    font-weight: 500;
}

.customer-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.customer-table td strong {
    color: #0b1f4b;
}

.customer-table td small {
    color: #1f2937;
    font-weight: 400;
}

.tag-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    background: #e5e7eb;
    color: #374151;
}

.tag-pill.active { background: #d1fae5; color: #166534; }
.tag-pill.inactive { background: #fee2e2; color: #991b1b; }
.tag-pill.isolated { background: #fde68a; color: #92400e; }

.placeholder-form {
    background: #f9fafb;
    border: 1px dashed #d1d5db;
    padding: 16px;
    border-radius: 4px;
    margin-top: 20px;
}

/* Tambahkan ini */
#customerEditModal { display: none; }
#customerDeleteModal { display: none; }
#markPaidModal { display: none; }
#customerImportModal { display: none; }

/* Modal Styling */
#customerEditModal,
#markPaidModal,
#customerImportModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: none;
    transition: opacity 0.3s ease;
}

#customerEditModal > div,
#markPaidModal > div,
#customerImportModal > div {
    background: white;
    width: 80%;
    max-width: 600px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transition: transform 0.3s ease;
}

#customerEditModal:not([style*="display: none"]),
#markPaidModal:not([style*="display: none"]),
#customerImportModal:not([style*="display: none"]) {
    opacity: 1;
}

#customerEditModal:not([style*="display: none"]) > div,
#markPaidModal:not([style*="display: none"]) > div,
#customerImportModal:not([style*="display: none"]) > div {
    transform: translateY(0);
}

#customerEditModal[style*="display: none"],
#markPaidModal[style*="display: none"],
#customerImportModal[style*="display: none"] {
    opacity: 0;
}

#customerEditModal[style*="display: none"] > div,
#markPaidModal[style*="display: none"] > div,
#customerImportModal[style*="display: none"] > div {
    transform: translateY(-20px);
}

#markPaidModal .modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

#markPaidModal .invoice-meta {
    background: #f1f5f9;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #0f172a;
}

#markPaidModal label {
    font-weight: 600;
    color: #1e293b;
}

.loading-indicator {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.7);
    z-index: 1001;
    display: none;
    justify-content: center;
    align-items: center;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="billing-module">
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="fa fa-users"></i> Pelanggan Billing</h3>
                <div class="btn-group">
                    <a href="./billing/customers_export.php" class="btn btn-sm btn-outline-success" title="Export pelanggan ke Excel">
                        <i class="fa fa-download"></i> Export
                    </a>
                    <a href="./billing/customers_export.php?template=1" class="btn btn-sm btn-outline-secondary" title="Unduh template Excel">
                        <i class="fa fa-file-excel-o"></i> Template
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" onclick="showImportModal()" title="Import pelanggan dari Excel">
                        <i class="fa fa-upload"></i> Import
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-3 col-box-6">
                        <div class="box bg-blue bmh-75">
                            <h1><?= number_format($totalCustomers, 0); ?>
                                <span style="font-size: 15px;">customers</span>
                            </h1>
                            <div><i class="fa fa-users"></i> Total Pelanggan</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-green bmh-75">
                            <h1><?= number_format($activeCustomers, 0); ?>
                                <span style="font-size: 15px;">active</span>
                            </h1>
                            <div><i class="fa fa-check"></i> Aktif</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-yellow bmh-75">
                            <h1><?= number_format($isolatedCustomers, 0); ?>
                                <span style="font-size: 15px;">isolated</span>
                            </h1>
                            <div><i class="fa fa-exclamation-triangle"></i> Terisolasi</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-red bmh-75">
                            <h1>H+3
                                <span style="font-size: 15px;">reminder</span>
                            </h1>
                            <div><i class="fa fa-clock-o"></i> Reminder Default</div>
                        </div>
                    </div>
                </div>

                <div class="alert bg-light" style="margin-bottom: 20px;">
                    <strong>Catatan:</strong> Pengaturan tanggal penagihan menentukan kapan invoice otomatis dibuat dan kapan profil akan diubah ke <strong>ISOLIR</strong> jika belum bayar.
                </div>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fa fa-search"></i> Cari Pelanggan</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($searchValue)): ?>
                            <div class="alert alert-info">
                                <strong>Hasil Pencarian:</strong> Menampilkan pelanggan dengan <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $searchType))); ?> mengandung "<?= htmlspecialchars($searchValue); ?>" 
                                <a href="./?hotspot=billing-customers&session=<?= urlencode($session); ?>" class="float-right">Tampilkan Semua</a>
                            </div>
                        <?php endif; ?>
                        <form method="GET" action="" class="row">
                            <input type="hidden" name="hotspot" value="billing-customers">
                            <input type="hidden" name="session" value="<?= htmlspecialchars($session); ?>">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search_type">Cari Berdasarkan</label>
                                    <select name="search_type" id="search_type" class="form-control">
                                        <option value="name" <?= ($_GET['search_type'] ?? '') === 'name' ? 'selected' : ''; ?>>Nama Pelanggan</option>
                                        <option value="phone" <?= ($_GET['search_type'] ?? '') === 'phone' ? 'selected' : ''; ?>>Nomor Telepon</option>
                                        <option value="service_number" <?= ($_GET['search_type'] ?? '') === 'service_number' ? 'selected' : ''; ?>>Nomor Layanan</option>
                                        <option value="pppoe" <?= ($_GET['search_type'] ?? '') === 'pppoe' ? 'selected' : ''; ?>>PPPoE Username</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="search_value">Kata Kunci</label>
                                    <input type="text" id="search_value" name="search_value" class="form-control" placeholder="Masukkan kata kunci pencarian" value="<?= htmlspecialchars($_GET['search_value'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3 align-self-end">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-search"></i> Cari
                                    </button>
                                    <?php if (!empty($_GET['search_value'])): ?>
                                        <a href="./?hotspot=billing-customers&session=<?= urlencode($session); ?>" class="btn btn-secondary btn-block mt-2">
                                            <i class="fa fa-times"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="customer-table">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Aksi</th>
                                <th>Nama</th>
                                <th>No. Layanan / Telepon</th>
                                <th>PPPoE Username</th>
                                <th>Paket</th>
                                <th>Tgl Tagihan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 30px; color:#6b7280;">
                                        <i class="fa fa-info-circle"></i> Belum ada data pelanggan billing.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td class="action-cell">
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-success" onclick="markLatestInvoicePaid(<?= $customer['id']; ?>)" title="Tandai invoice terbaru lunas">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                                <a class="btn btn-sm btn-outline-info" href="./?hotspot=billing-invoices&session=<?= urlencode($session); ?>&customer=<?= $customer['id']; ?>" title="Lihat detail tagihan">
                                                    <i class="fa fa-file-text-o"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-primary" onclick="showEditModal(<?= $customer['id']; ?>)" title="Edit pelanggan">
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $customer['id']; ?>)" title="Hapus pelanggan">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($customer['name']); ?></strong><br>
                                            <small><?= htmlspecialchars($customer['email'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($customer['service_number'] ?? '-'); ?><br>
                                            <small><?= htmlspecialchars($customer['phone'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <span class="tag-pill" style="background:#e0e7ff;color:#312e81;">
                                                <i class="fa fa-random"></i> PPPoE Username
                                            </span>
                                            <div style="font-size:12px;color:#0f172a; font-weight:600;">
                                                <?= htmlspecialchars($customer['genieacs_pppoe_username'] ?? '-'); ?>
                                            </div>
                                            <div style="font-size:11px;color:#1e293b; margin-top:4px;">
                                                <strong>Fallback:</strong>
                                                <span style="display:block;">Tag Telepon: <?= htmlspecialchars($customer['phone'] ?? '-'); ?></span>
                                                <span style="display:block;">Device ID: <?= htmlspecialchars($customer['service_number'] ?? '-'); ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($customer['profile_name'] ?? '-'); ?></td>
                                        <td><?= str_pad((int)$customer['billing_day'], 2, '0', STR_PAD_LEFT); ?> setiap bulan</td>
                                        <td>
                                            <?php $status = $customer['status'] ?? 'inactive'; ?>
                                            <span class="tag-pill <?= $status === 'active' ? 'active' : 'inactive'; ?>">
                                                <?= ucfirst($status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="placeholder-form">
                    <h4 style="margin-top:0;"><i class="fa fa-plus"></i> Tambah Pelanggan</h4>
                    <p style="margin-bottom: 15px;">Gunakan formulir ini untuk menambahkan pelanggan baru. Data akan langsung tersimpan melalui API billing.</p>
                    <form data-api-form data-api-endpoint="./billing/customers.php" data-success-reload="true">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="customer_name">Nama Pelanggan</label>
                                    <input type="text" id="customer_name" name="name" class="form-control" placeholder="Nama lengkap" required>
                                </div>
                            </div>
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="customer_phone">No. WhatsApp</label>
                                    <input type="text" id="customer_phone" name="phone" class="form-control" placeholder="08xxx" required>
                                    <small class="form-text text-muted">Nomor WhatsApp pelanggan (dipakai untuk invoice & fallback GenieACS).</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="customer_email">Email</label>
                                    <input type="email" id="customer_email" name="email" class="form-control" placeholder="opsional">
                                </div>
                            </div>
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="service_number">Nomor Layanan / CPE ID</label>
                                    <input type="text" id="service_number" name="service_number" class="form-control" placeholder="Digunakan untuk mapping ke GenieACS">
                                    <small class="form-text text-muted">Opsional, dipakai sebagai fallback (Device ID) jika PPPoE & tag tidak ditemukan.</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="genieacs_pppoe_username">PPPoE Username (GenieACS)</label>
                                    <input type="text" id="genieacs_pppoe_username" name="genieacs_pppoe_username" class="form-control" placeholder="user123@isp" required>
                                    <small class="form-text text-muted">Harus sama dengan PPPoE username di GenieACS.</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="profile_id">Paket Billing</label>
                                    <select id="profile_id" name="profile_id" class="form-control" required>
                                        <option value="">-- pilih paket --</option>
<?php foreach ($profileOptions as $profile): ?>
                                        <option value="<?= (int)$profile['id']; ?>"><?= htmlspecialchars($profile['profile_name']); ?></option>
<?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-3 col-box-12">
                                <div class="form-group">
                                    <label for="billing_day">Tanggal Tagihan</label>
                                    <input type="number" id="billing_day" name="billing_day" class="form-control" min="1" max="28" value="1" required>
                                </div>
                            </div>
                            <div class="col-3 col-box-12">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Tidak aktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="address">Alamat</label>
                            <textarea id="address" name="address" rows="2" class="form-control" placeholder="Opsional"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="notes">Catatan</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control" placeholder="Opsional"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Simpan Pelanggan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal Edit Pelanggan -->
<div id="customerEditModal">
    <div style="background:white;width:80%;max-width:600px;margin:50px auto;padding:20px;border-radius:10px;">
        <h3><i class="fa fa-pencil"></i> Edit Pelanggan</h3>
        <form id="customerEditForm" data-api-form data-api-endpoint="./billing/customers.php" data-success-reload="true">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_customer_id">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nama Pelanggan</label>
                        <input type="text" name="name" id="edit_customer_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>No. WhatsApp</label>
                        <input type="text" name="phone" id="edit_customer_phone" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_customer_email" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" id="edit_customer_address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>PPPoE Username</label>
                        <input type="text" name="genieacs_pppoe_username" id="edit_pppoe_username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Layanan</label>
                        <input type="text" name="service_number" id="edit_service_number" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Paket</label>
                        <select name="profile_id" id="edit_profile_id" class="form-control" required>
                            <?php foreach ($profileOptions as $profile): ?>
                                <option value="<?= $profile['id'] ?>"><?= htmlspecialchars($profile['profile_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Tagihan</label>
                        <input type="number" name="billing_day" id="edit_billing_day" class="form-control" min="1" max="28" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="active">Aktif</option>
                    <option value="inactive">Non-Aktif</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Batal</button>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Pelunasan -->
<div id="markPaidModal">
    <div>
        <h3><i class="fa fa-check-circle"></i> Tandai Invoice Lunas</h3>
        <div class="invoice-meta">
            <div><strong>ID Invoice:</strong> <span id="markPaidInvoiceId">-</span></div>
            <div><strong>Periode:</strong> <span id="markPaidInvoicePeriod">-</span></div>
            <div><strong>Jatuh Tempo:</strong> <span id="markPaidInvoiceDue">-</span></div>
            <div><strong>Nominal:</strong> <span id="markPaidInvoiceAmount">-</span></div>
            <div><strong>Status saat ini:</strong> <span id="markPaidInvoiceStatus">-</span></div>
        </div>

        <div class="form-group">
            <label for="markPaidChannel">Channel Pembayaran</label>
            <select id="markPaidChannel" class="form-control">
                <option value="admin_manual">Manual Admin</option>
                <option value="agent_balance">Saldo Agen</option>
                <option value="transfer_bank">Transfer Bank</option>
                <option value="cash">Tunai</option>
            </select>
        </div>

        <div class="form-group">
            <label for="markPaidReference">Nomor Referensi</label>
            <input type="text" id="markPaidReference" class="form-control" placeholder="Opsional">
        </div>

        <div class="form-group">
            <label for="markPaidNotes">Catatan</label>
            <textarea id="markPaidNotes" class="form-control" rows="2" placeholder="Opsional"></textarea>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeMarkPaidModal()">Batal</button>
            <button type="button" class="btn btn-success" onclick="confirmMarkPaid()">
                <i class="fa fa-check"></i> Tandai Lunas
            </button>
        </div>
    </div>
</div>

<!-- Modal Import Pelanggan -->
<div id="customerImportModal">
    <div>
        <h3><i class="fa fa-upload"></i> Import Pelanggan Billing</h3>
        <p>Unggah file Excel (.xlsx) dengan format sesuai template. Kolom wajib: Nama, No. WhatsApp, PPPoE Username, Paket.</p>
        <form id="customerImportForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="import_file">File Excel (.xlsx)</label>
                <input type="file" id="import_file" name="file" class="form-control" accept=".xlsx" required>
            </div>
            <div class="form-group">
                <small>Butuh template? <a href="./billing/customers_export.php?template=1" target="_blank">Download di sini</a>.</small>
            </div>
            <div id="importResult" class="alert" style="display:none;"></div>
            <div class="d-flex justify-content-end" style="gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Proses Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Pelanggan -->
<div class="modal fade" id="customerDeleteModal" tabindex="-1" role="dialog" aria-labelledby="customerDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerDeleteModalLabel"><i class="fa fa-trash"></i> Hapus Pelanggan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus pelanggan ini? Tindakan ini tidak dapat dibatalkan.</p>
                <form id="customerDeleteForm" data-api-form data-api-endpoint="./billing/customers.php" data-success-reload="true">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="">
                    <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Hapus</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="loading-indicator" class="loading-indicator">
    <div class="loading-spinner"></div>
</div>

<script src="./js/billing_forms.js"></script>
<script>
let markPaidContext = null;
let importInProgress = false;

function markLatestInvoicePaid(customerId) {
    showLoading();

    fetch(`./billing/invoices.php?customer_id=${customerId}&limit=1`, {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();

            if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
                alert('Invoice belum ditemukan untuk pelanggan ini.');
                return;
            }

            const invoice = data.data[0];
            if (invoice.status === 'paid') {
                alert('Invoice terbaru sudah berstatus lunas.');
                return;
            }

            showMarkPaidModal(customerId, invoice);
        })
        .catch(() => {
            hideLoading();
            alert('Terjadi kesalahan saat memuat invoice pelanggan.');
        });
}

function showMarkPaidModal(customerId, invoice) {
    markPaidContext = {
        customerId,
        invoiceId: invoice.id,
        amount: invoice.amount
    };

    document.getElementById('markPaidInvoiceId').textContent = invoice.id;
    document.getElementById('markPaidInvoicePeriod').textContent = invoice.period || '-';
    document.getElementById('markPaidInvoiceDue').textContent = invoice.due_date || '-';
    document.getElementById('markPaidInvoiceAmount').textContent = typeof invoice.amount !== 'undefined'
        ? 'Rp ' + Number(invoice.amount).toLocaleString('id-ID')
        : '-';
    document.getElementById('markPaidInvoiceStatus').textContent = invoice.status || '-';

    const defaultReference = `ADMIN-${Date.now()}`;
    const referenceInput = document.getElementById('markPaidReference');
    referenceInput.value = defaultReference;

    document.getElementById('markPaidChannel').value = 'admin_manual';
    document.getElementById('markPaidNotes').value = '';

    const modal = document.getElementById('markPaidModal');
    modal.style.display = 'block';
    modal.style.opacity = '1';
    modal.querySelector('div').style.transform = 'translateY(0)';
}

function closeMarkPaidModal() {
    const modal = document.getElementById('markPaidModal');
    modal.style.opacity = '0';
    modal.querySelector('div').style.transform = 'translateY(-20px)';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    markPaidContext = null;
}

function confirmMarkPaid() {
    if (!markPaidContext) {
        return;
    }

    const channel = document.getElementById('markPaidChannel').value;
    const reference = document.getElementById('markPaidReference').value.trim() || null;
    const notes = document.getElementById('markPaidNotes').value.trim() || null;

    const payload = {
        action: 'mark_paid',
        id: markPaidContext.invoiceId,
        amount: markPaidContext.amount,
        payment_channel: channel,
        reference_number: reference,
        notes: notes,
        paid_via: 'admin_quick',
        paid_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    };

    showLoading();

    fetch('./billing/invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(result => {
            hideLoading();
            closeMarkPaidModal();

            if (result.success) {
                alert('Invoice berhasil ditandai lunas.');
                window.location.href = `./?hotspot=billing-invoices&session=<?= urlencode($session); ?>&customer=${markPaidContext.customerId}`;
            } else {
                alert(result.message || 'Gagal menandai invoice sebagai lunas.');
            }
        })
        .catch(() => {
            hideLoading();
            alert('Terjadi kesalahan saat menandai invoice sebagai lunas.');
        });
}

document.getElementById('customerEditForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const phone = document.getElementById('edit_customer_phone').value.trim();
    const pppoe = document.getElementById('edit_pppoe_username').value.trim();
    
    if (!phone) {
        alert('Nomor telepon wajib diisi');
        return;
    }
    
    if (!pppoe) {
        alert('PPPoE username wajib diisi');
        return;
    }
    
    // Lanjutkan dengan AJAX submit
    // ...
});

function showLoading() {
    let loader = document.getElementById('loading-indicator');
    loader.style.display = 'flex';
}

function hideLoading() {
    const loader = document.getElementById('loading-indicator');
    loader.style.display = 'none';
}

function showImportModal() {
    const modal = document.getElementById('customerImportModal');
    document.getElementById('importResult').style.display = 'none';
    document.getElementById('importResult').className = 'alert';
    document.getElementById('importResult').textContent = '';
    if (!importInProgress) {
        document.getElementById('customerImportForm').reset();
    }
    modal.style.display = 'block';
    modal.style.opacity = '1';
    modal.querySelector('div').style.transform = 'translateY(0)';
}

function closeImportModal() {
    const modal = document.getElementById('customerImportModal');
    modal.style.opacity = '0';
    modal.querySelector('div').style.transform = 'translateY(-20px)';
    setTimeout(() => {
        modal.style.display = 'none';
        importInProgress = false;
    }, 300);
}

function showEditModal(customerId) {
    showLoading();
    
    fetch(`./billing/customers.php?id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const customer = data.data;
                
                // Isi semua field form
                document.getElementById('edit_customer_id').value = customer.id;
                document.getElementById('edit_customer_name').value = customer.name;
                document.getElementById('edit_customer_phone').value = customer.phone;
                document.getElementById('edit_customer_email').value = customer.email || '';
                document.getElementById('edit_customer_address').value = customer.address || '';
                document.getElementById('edit_pppoe_username').value = customer.genieacs_pppoe_username;
                document.getElementById('edit_service_number').value = customer.service_number || '';
                document.getElementById('edit_profile_id').value = customer.profile_id;
                document.getElementById('edit_billing_day').value = customer.billing_day;
                document.getElementById('edit_status').value = customer.status;
                document.getElementById('edit_notes').value = customer.notes || '';
                
                hideLoading();
                document.getElementById('customerEditModal').style.display = 'block';
            }
        })
        .catch(error => {
            hideLoading();
            alert('Gagal memuat data pelanggan');
        });
}

function hideEditModal() {
    const modal = document.getElementById('customerEditModal');
    modal.style.opacity = '0';
    modal.querySelector('div').style.transform = 'translateY(-20px)';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function confirmDelete(customerId) {
    if (confirm('Apakah Anda yakin ingin menghapus pelanggan ini?')) {
        showLoading();
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', customerId);
        
        fetch('./billing/customers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
            hideLoading();
        });
    }
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        hideEditModal();
        closeImportModal();
    }
});

document.getElementById('customerImportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('import_file');
    const resultBox = document.getElementById('importResult');

    if (!fileInput.files.length) {
        resultBox.style.display = 'block';
        resultBox.className = 'alert alert-danger';
        resultBox.textContent = 'Silakan pilih file Excel terlebih dahulu.';
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    importInProgress = true;
    showLoading();

    fetch('./billing/customers_import.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            resultBox.style.display = 'block';

            if (data.success) {
                resultBox.className = 'alert alert-success';
                resultBox.textContent = `Import selesai. Berhasil: ${data.imported}, Gagal: ${data.failed}`;

                if (Array.isArray(data.errors) && data.errors.length > 0) {
                    const list = document.createElement('ul');
                    data.errors.forEach(err => {
                        const li = document.createElement('li');
                        li.textContent = err;
                        list.appendChild(li);
                    });
                    resultBox.appendChild(list);
                }

                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                resultBox.className = 'alert alert-danger';
                resultBox.textContent = data.message || 'Import gagal dijalankan.';
            }
        })
        .catch(() => {
            hideLoading();
            resultBox.style.display = 'block';
            resultBox.className = 'alert alert-danger';
            resultBox.textContent = 'Terjadi kesalahan saat memproses import.';
        });
});
</script>
</div>
