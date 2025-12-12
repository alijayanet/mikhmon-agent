<?php
/*
 * Payment Methods Management
 * Manage payment methods and fees for payment gateway
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');

$conn = getDBConnection();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_method':
                $id = $_POST['id'];
                $admin_fee_value = $_POST['admin_fee_value'];
                $admin_fee_type = $_POST['admin_fee_type'];
                $min_amount = $_POST['min_amount'];
                $max_amount = $_POST['max_amount'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $stmt = $conn->prepare("UPDATE payment_methods SET 
                        admin_fee_value = ?, admin_fee_type = ?, min_amount = ?, max_amount = ?, is_active = ?
                        WHERE id = ?");
                    $stmt->execute([$admin_fee_value, $admin_fee_type, $min_amount, $max_amount, $is_active, $id]);
                    
                    $message = 'Payment method updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating payment method: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'bulk_update':
                // Quick update for common scenarios
                $updates = $_POST['updates'] ?? [];
                $success_count = 0;
                
                foreach ($updates as $id => $data) {
                    if (!empty($data['admin_fee_value'])) {
                        try {
                            $stmt = $conn->prepare("UPDATE payment_methods SET admin_fee_value = ? WHERE id = ?");
                            $stmt->execute([$data['admin_fee_value'], $id]);
                            $success_count++;
                        } catch (Exception $e) {
                            // Continue with other updates
                        }
                    }
                }
                
                $message = "Updated $success_count payment methods successfully!";
                $messageType = 'success';
                break;
        }
    }
}

// Get all payment methods
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY gateway_name, sort_order, id");
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by type for easier management
$grouped_methods = [];
foreach ($payment_methods as $method) {
    $type = $method['method_type'];
    if (!isset($grouped_methods[$type])) {
        $grouped_methods[$type] = [];
    }
    $grouped_methods[$type][] = $method;
}

$type_labels = [
    'qris' => 'QRIS',
    'va' => 'Virtual Account',
    'ewallet' => 'E-Wallet',
    'retail' => 'Retail Store'
];

$type_icons = [
    'qris' => 'fa-qrcode',
    'va' => 'fa-bank',
    'ewallet' => 'fa-mobile',
    'retail' => 'fa-shopping-cart'
];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-money"></i> Payment Methods Management</h3>
            </div>
            <div class="card-body">
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <i class="fa fa-<?= $messageType === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
                    <?= htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Summary -->
                <div class="row mb-3">
                    <div class="col-3 col-box-6">
                        <div class="box bg-blue bmh-75">
                            <h1><?= count($payment_methods); ?>
                                <span style="font-size: 15px;">methods</span>
                            </h1>
                            <div><i class="fa fa-credit-card"></i> Total Payment Methods</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-green bmh-75">
                            <h1><?= count(array_filter($payment_methods, function($m) { return $m['is_active']; })); ?>
                                <span style="font-size: 15px;">active</span>
                            </h1>
                            <div><i class="fa fa-check"></i> Active Methods</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-yellow bmh-75">
                            <h1><?= count($grouped_methods['qris'] ?? []); ?>
                                <span style="font-size: 15px;">QRIS</span>
                            </h1>
                            <div><i class="fa fa-qrcode"></i> QRIS Methods</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-red bmh-75">
                            <h1><?= count(array_filter($payment_methods, function($m) { return !$m['is_active']; })); ?>
                                <span style="font-size: 15px;">inactive</span>
                            </h1>
                            <div><i class="fa fa-times"></i> Inactive Methods</div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-3 col-box-6">
                        <div class="box bg-purple bmh-75">
                            <h1><?= count($grouped_methods['va'] ?? []); ?>
                                <span style="font-size: 15px;">VA</span>
                            </h1>
                            <div><i class="fa fa-bank"></i> Virtual Account</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-navy bmh-75">
                            <h1><?= count($grouped_methods['ewallet'] ?? []); ?>
                                <span style="font-size: 15px;">e-wallet</span>
                            </h1>
                            <div><i class="fa fa-mobile"></i> E-Wallet Methods</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-orange bmh-75">
                            <h1><?= count($grouped_methods['retail'] ?? []); ?>
                                <span style="font-size: 15px;">retail</span>
                            </h1>
                            <div><i class="fa fa-shopping-cart"></i> Retail Store</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-teal bmh-75">
                            <h1><?= count(array_filter($payment_methods, function($m) { return $m['admin_fee_type'] === 'percentage'; })); ?>
                                <span style="font-size: 15px;">percent</span>
                            </h1>
                            <div><i class="fa fa-percent"></i> Percentage Fee</div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> Information</h5>
                    <p><strong>Admin Fee</strong> adalah biaya tambahan yang dikenakan kepada customer di atas fee payment gateway.</p>
                    <ul class="mb-0">
                        <li><strong>Fixed:</strong> Fee tetap dalam Rupiah (contoh: 1500 = Rp 1,500)</li>
                        <li><strong>Percentage:</strong> Fee persentase dari total transaksi (contoh: 2.5 = 2.5%)</li>
                        <li><strong>Fee Payment Gateway</strong> sudah diatur di dashboard payment gateway (Tripay/Xendit/Midtrans)</li>
                    </ul>
                </div>
                
                <!-- Quick Actions -->
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <p>Update fee untuk metode pembayaran yang umum digunakan:</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="bulk_update">
                            <div class="row">
                                <?php foreach ($payment_methods as $method): ?>
                                    <?php if ($method['method_code'] === 'QRIS'): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">QRIS Admin Fee (Rp):</label>
                                        <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                               class="form-control" placeholder="1500" value="<?= $method['admin_fee_value']; ?>">
                                        <small class="text-muted">Current: Rp <?= number_format($method['admin_fee_value']); ?></small>
                                    </div>
                                    <?php elseif ($method['method_code'] === 'BRIVA'): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">BRI VA Admin Fee (Rp):</label>
                                        <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                               class="form-control" placeholder="2500" value="<?= $method['admin_fee_value']; ?>">
                                        <small class="text-muted">Current: Rp <?= number_format($method['admin_fee_value']); ?></small>
                                    </div>
                                    <?php elseif ($method['method_code'] === 'OVO'): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">OVO Admin Fee (%):</label>
                                        <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                               class="form-control" placeholder="2.0" step="0.1" value="<?= $method['admin_fee_value']; ?>">
                                        <small class="text-muted">Current: <?= $method['admin_fee_value']; ?>%</small>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fa fa-flash"></i> Quick Update
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Payment Methods by Category -->
                <?php foreach ($grouped_methods as $type => $methods): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>"></i>
                            <?= $type_labels[$type] ?? ucfirst($type); ?> Methods
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="25%">Method</th>
                                        <th width="12%">Current Fee</th>
                                        <th width="12%">Fee Type</th>
                                        <th width="10%">Fee Value</th>
                                        <th width="10%">Min Amount</th>
                                        <th width="10%">Max Amount</th>
                                        <th width="8%">Status</th>
                                        <th width="13%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($methods as $method): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="method-icon mr-2" style="width: 30px; height: 30px; background: #3c8dbc; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa <?= $method['icon'] ?? 'fa-credit-card'; ?>" style="font-size: 14px;"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($method['method_name']); ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($method['method_code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= $method['admin_fee_type'] === 'percentage' ? $method['admin_fee_value'] . '%' : 'Rp ' . number_format($method['admin_fee_value']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" id="form_<?= $method['id']; ?>">
                                                <input type="hidden" name="action" value="update_method">
                                                <input type="hidden" name="id" value="<?= $method['id']; ?>">
                                                
                                                <select name="admin_fee_type" class="form-control form-control-sm">
                                                    <option value="fixed" <?= $method['admin_fee_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                                    <option value="percentage" <?= $method['admin_fee_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                                </select>
                                        </td>
                                        <td>
                                                <input type="number" name="admin_fee_value" class="form-control form-control-sm" 
                                                       value="<?= $method['admin_fee_value']; ?>" step="0.01" min="0">
                                        </td>
                                        <td>
                                                <input type="number" name="min_amount" class="form-control form-control-sm" 
                                                       value="<?= $method['min_amount']; ?>" placeholder="0">
                                        </td>
                                        <td>
                                                <input type="number" name="max_amount" class="form-control form-control-sm" 
                                                       value="<?= $method['max_amount']; ?>" placeholder="0">
                                        </td>
                                        <td>
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" class="form-check-input" 
                                                           <?= $method['is_active'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">Active</label>
                                                </div>
                                        </td>
                                        <td>
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fa fa-save"></i> Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Important Notes -->
                <div class="alert alert-warning">
                    <h5><i class="fa fa-exclamation-triangle"></i> Important Notes:</h5>
                    <ul class="mb-0">
                        <li><strong>Admin Fee</strong> adalah keuntungan untuk admin/agen, bukan fee payment gateway</li>
                        <li><strong>Fee Payment Gateway</strong> (seperti fee Tripay) sudah diatur di dashboard payment gateway</li>
                        <li><strong>Total yang dibayar customer</strong> = Harga Voucher + Admin Fee + Fee Payment Gateway</li>
                        <li>Jika tidak ingin keuntungan tambahan, set Admin Fee = 0</li>
                    </ul>
                </div>
                
            </div>
        </div>
    </div>
</div>

<style>
.method-icon {
    flex-shrink: 0;
}

.table td {
    vertical-align: middle;
}

.form-control-sm {
    font-size: 0.875rem;
}

.form-check {
    margin-bottom: 0;
}

.form-check-label {
    font-size: 0.875rem;
    margin-left: 0.25rem;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .method-icon {
        width: 25px !important;
        height: 25px !important;
    }
    
    .method-icon i {
        font-size: 12px !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}

/* Mobile layout fixes - Force 2 columns */
@media (max-width: 767px) {
    .col-3.col-box-6 {
        flex: 0 0 calc(50% - 10px) !important;
        max-width: calc(50% - 10px) !important;
        width: calc(50% - 10px) !important;
        margin: 5px !important;
        float: left !important;
        display: block !important;
    }
    
    .row.mb-3 {
        display: block !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        overflow: hidden !important;
    }
    
    .row.mb-3::after {
        content: "" !important;
        display: table !important;
        clear: both !important;
    }
    
    .box {
        height: 90px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        margin-bottom: 0 !important;
        text-align: center !important;
    }
    
    .box h1 {
        font-size: 1.1rem !important;
        margin-bottom: 3px !important;
    }
    
    .box h1 span {
        font-size: 0.65rem !important;
    }
    
    .box div {
        font-size: 0.7rem !important;
        line-height: 1.1 !important;
    }
}

/* Extra small mobile */
@media (max-width: 480px) {
    .col-3.col-box-6 {
        flex: 0 0 calc(50% - 6px) !important;
        max-width: calc(50% - 6px) !important;
        width: calc(50% - 6px) !important;
        margin: 3px !important;
    }
    
    .box {
        height: 80px !important;
    }
    
    .box h1 {
        font-size: 1rem !important;
    }
    
    .box h1 span {
        font-size: 0.6rem !important;
    }
    
    .box div {
        font-size: 0.65rem !important;
    }
}

/* Table responsive for mobile */
@media (max-width: 767px) {
    .table-responsive {
        border: none !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    
    .table {
        font-size: 0.75rem !important;
        margin-bottom: 0 !important;
    }
    
    .table th,
    .table td {
        padding: 8px 4px !important;
        vertical-align: middle !important;
        border: 1px solid #dee2e6 !important;
    }
    
    .table th {
        background-color: #f8f9fa !important;
        font-weight: 600 !important;
        font-size: 0.7rem !important;
        white-space: nowrap !important;
    }
    
    .method-icon {
        width: 20px !important;
        height: 20px !important;
        margin-right: 5px !important;
    }
    
    .method-icon i {
        font-size: 10px !important;
    }
    
    .form-control-sm {
        font-size: 0.7rem !important;
        padding: 4px 6px !important;
        height: auto !important;
    }
    
    .btn-sm {
        padding: 4px 8px !important;
        font-size: 0.65rem !important;
    }
    
    .form-check {
        margin-bottom: 0 !important;
    }
    
    .form-check-input {
        margin-top: 2px !important;
    }
    
    .form-check-label {
        font-size: 0.65rem !important;
        margin-left: 3px !important;
    }
    
    .badge {
        font-size: 0.6rem !important;
        padding: 2px 6px !important;
    }
}

/* Extra small mobile table */
@media (max-width: 480px) {
    .table {
        font-size: 0.7rem !important;
    }
    
    .table th,
    .table td {
        padding: 6px 3px !important;
    }
    
    .table th {
        font-size: 0.65rem !important;
    }
    
    .method-icon {
        width: 18px !important;
        height: 18px !important;
    }
    
    .method-icon i {
        font-size: 9px !important;
    }
    
    .form-control-sm {
        font-size: 0.65rem !important;
        padding: 3px 5px !important;
    }
    
    .btn-sm {
        padding: 3px 6px !important;
        font-size: 0.6rem !important;
    }
    
    .form-check-label {
        font-size: 0.6rem !important;
    }
    
    .badge {
        font-size: 0.55rem !important;
        padding: 1px 4px !important;
    }
}

/* Horizontal scroll indicator for mobile */
@media (max-width: 767px) {
    .table-responsive::before {
        content: "← Geser untuk melihat semua kolom →" !important;
        display: block !important;
        text-align: center !important;
        background: #fff3cd !important;
        color: #856404 !important;
        padding: 8px !important;
        font-size: 0.7rem !important;
        border: 1px solid #ffeaa7 !important;
        border-radius: 4px !important;
        margin-bottom: 10px !important;
    }
}
</style>
