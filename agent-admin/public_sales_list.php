<?php
/*
 * Public Sales Transactions List
 * Admin view of all public voucher sales
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$where_clauses = [];
$params = [];

if ($status_filter != 'all') {
    $where_clauses[] = "ps.status = :status";
    $params[':status'] = $status_filter;
}

if ($date_from) {
    $where_clauses[] = "DATE(ps.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_clauses[] = "DATE(ps.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    $conn = getDBConnection();
    
    // Check if agent_id column exists in public_sales table
    $hasAgentId = false;
    try {
        $conn->query("SELECT agent_id FROM public_sales LIMIT 1");
        $hasAgentId = true;
    } catch (Exception $e) {
        // Column doesn't exist
        $hasAgentId = false;
    }
    
    // Build query based on available columns
    if ($hasAgentId) {
        $sql = "SELECT ps.*, a.agent_name, a.agent_code
                FROM public_sales ps
                LEFT JOIN agents a ON ps.agent_id = a.id
                $where_sql
                ORDER BY ps.created_at DESC
                LIMIT 100";
    } else {
        // Fallback query without agent join
        $sql = "SELECT ps.*, 'N/A' as agent_name, 'N/A' as agent_code
                FROM public_sales ps
                $where_sql
                ORDER BY ps.created_at DESC
                LIMIT 100";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_sql = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as revenue
                  FROM public_sales ps
                  $where_sql";
    
    $stmt = $conn->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $transactions = [];
    $stats = ['total' => 0, 'paid' => 0, 'pending' => 0, 'revenue' => 0];
}

$session = $_GET['session'] ?? '';
?>

<!-- Include responsive tables CSS -->
<link rel="stylesheet" href="./css/responsive-tables.css">

<div class="row">
<div class="col-12">
    
    <!-- Statistics Cards -->
    <div class="row mb-3">
        <div class="col-3 col-box-6">
            <div class="box bg-blue bmh-75">
                <h1><?= $stats['total']; ?>
                    <span style="font-size: 15px;">sales</span>
                </h1>
                <div>
                    <i class="fa fa-shopping-cart"></i> Total Transaksi
                </div>
            </div>
        </div>
        <div class="col-3 col-box-6">
            <div class="box bg-green bmh-75">
                <h1><?= $stats['paid']; ?>
                    <span style="font-size: 15px;">paid</span>
                </h1>
                <div>
                    <i class="fa fa-check-circle"></i> Berhasil
                </div>
            </div>
        </div>
        <div class="col-3 col-box-6">
            <div class="box bg-yellow bmh-75">
                <h1><?= $stats['pending']; ?>
                    <span style="font-size: 15px;">pending</span>
                </h1>
                <div>
                    <i class="fa fa-clock-o"></i> Pending
                </div>
            </div>
        </div>
        <div class="col-3 col-box-6">
            <div class="box bg-aqua bmh-75">
                <h1 style="font-size: 18px;">Rp <?= number_format($stats['revenue'], 0, ',', '.'); ?></h1>
                <div>
                    <i class="fa fa-money"></i> Revenue
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-list"></i> Public Sales Transactions</h3>
        </div>
        <div class="card-body">
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <form method="GET" action="" class="mb-3">
                <input type="hidden" name="hotspot" value="public-sales">
                <input type="hidden" name="session" value="<?= htmlspecialchars($session); ?>">
                
                <div class="row">
                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?= $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="expired" <?= $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="failed" <?= $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Mobile Scroll Hint -->
            <div class="d-block d-md-none" style="background: #fff3cd; padding: 8px 12px; margin-bottom: 10px; border-radius: 3px; font-size: 12px; color: #856404;">
                <i class="fa fa-hand-o-right"></i> Geser tabel ke kanan untuk melihat semua kolom
            </div>
            
            <!-- Transactions Table -->
            <!-- Desktop Table View -->
            <div class="desktop-only">
            <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="table table-bordered table-striped table-hover" style="min-width: 1000px;">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Agent</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Voucher</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No transactions found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($transactions as $trx): ?>
                        <tr>
                            <td><small><?= htmlspecialchars($trx['transaction_id']); ?></small></td>
                            <td><small><?= date('d/m/Y H:i', strtotime($trx['created_at'])); ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($trx['customer_name']); ?></strong><br>
                                <small><?= htmlspecialchars($trx['customer_phone']); ?></small>
                            </td>
                            <td><small><?= htmlspecialchars($trx['agent_name']); ?></small></td>
                            <td><small><?= htmlspecialchars($trx['profile_name']); ?></small></td>
                            <td><strong>Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?></strong></td>
                            <td><small><?= htmlspecialchars($trx['payment_method'] ?? '-'); ?></small></td>
                            <td>
                                <?php
                                $badge_colors = [
                                    'pending' => 'warning',
                                    'paid' => 'success',
                                    'expired' => 'secondary',
                                    'failed' => 'danger'
                                ];
                                $badge_color = $badge_colors[$trx['status']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $badge_color; ?>">
                                    <?= ucfirst($trx['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($trx['voucher_code'])): ?>
                                <small>
                                    <strong><?= htmlspecialchars($trx['voucher_code']); ?></strong><br>
                                    <?= htmlspecialchars($trx['voucher_password']); ?>
                                </small>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewDetail('<?= $trx['id']; ?>')">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <?php if ($trx['status'] == 'paid' && empty($trx['voucher_code'])): ?>
                                <button class="btn btn-warning btn-sm" onclick="generateVoucher('<?= $trx['id']; ?>')">
                                    <i class="fa fa-ticket"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
            
            <!-- Mobile Card View -->
            <div class="mobile-only">
                <?php if (empty($transactions)): ?>
                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> No transactions found</div>
                <?php else: ?>
                    <?php foreach ($transactions as $trx): ?>
                    <?php
                    $badge_colors = [
                        'pending' => 'warning',
                        'paid' => 'success',
                        'expired' => 'secondary',
                        'failed' => 'danger'
                    ];
                    $badge_color = $badge_colors[$trx['status']] ?? 'secondary';
                    ?>
                    <div class="sale-card">
                        <div class="sale-card-header">
                            <div class="sale-reference">
                                #<?= htmlspecialchars($trx['transaction_id']); ?>
                            </div>
                            <div class="sale-amount">
                                Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?>
                            </div>
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Date:</span>
                            <span class="data-value"><?= date('d/m/Y H:i', strtotime($trx['created_at'])); ?></span>
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Customer:</span>
                            <span class="data-value">
                                <strong><?= htmlspecialchars($trx['customer_name']); ?></strong><br>
                                <small><?= htmlspecialchars($trx['customer_phone']); ?></small>
                            </span>
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Agent:</span>
                            <span class="data-value"><?= htmlspecialchars($trx['agent_name']); ?></span>
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Package:</span>
                            <span class="data-value"><?= htmlspecialchars($trx['profile_name']); ?></span>
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Payment:</span>
                            <span class="data-value"><?= htmlspecialchars($trx['payment_method'] ?? '-'); ?></span>
                        </div>
                        
                        <div class="data-row">
                            <span class="data-label">Status:</span>
                            <span class="data-value">
                                <span class="badge badge-<?= $badge_color; ?>"><?= ucfirst($trx['status']); ?></span>
                            </span>
                        </div>
                        
                        <?php if (!empty($trx['voucher_code'])): ?>
                        <div class="data-row">
                            <span class="data-label">Voucher:</span>
                            <span class="data-value">
                                <strong><?= htmlspecialchars($trx['voucher_code']); ?></strong><br>
                                <small><?= htmlspecialchars($trx['voucher_password']); ?></small>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="data-actions">
                            <button class="btn btn-info btn-sm" onclick="viewDetail('<?= $trx['id']; ?>')">
                                <i class="fa fa-eye"></i> View Detail
                            </button>
                            <?php if ($trx['status'] == 'paid' && empty($trx['voucher_code'])): ?>
                            <button class="btn btn-warning btn-sm" onclick="generateVoucher('<?= $trx['id']; ?>')">
                                <i class="fa fa-ticket"></i> Generate Voucher
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    
</div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Detail</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailContent">
                <div class="text-center">
                    <i class="fa fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Mobile Table Responsive */
@media (max-width: 768px) {
    .table-responsive {
        border: 1px solid #ddd;
        border-radius: 3px;
        box-shadow: inset 0 0 5px rgba(0,0,0,0.05);
    }
    
    .table-responsive table {
        margin-bottom: 0;
    }
    
    /* Sticky first column */
    .table-responsive th:first-child,
    .table-responsive td:first-child {
        position: sticky;
        left: 0;
        background: white;
        z-index: 1;
        box-shadow: 2px 0 3px rgba(0,0,0,0.1);
    }
    
    .table-responsive thead th:first-child {
        z-index: 2;
        background: #343a40;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    console.log('Public Sales List - jQuery loaded');
    console.log('Bootstrap version:', typeof $.fn.modal !== 'undefined' ? 'loaded' : 'not loaded');
});

function viewDetail(id) {
    console.log('viewDetail called with id:', id);
    
    // Show modal
    $('#detailModal').modal('show');
    
    // Set loading
    $('#detailContent').html('<div class="text-center" style="padding: 40px;"><i class="fa fa-spinner fa-spin fa-3x"></i><p style="margin-top: 20px;">Loading...</p></div>');
    
    // Load detail via AJAX
    $.ajax({
        url: './agent-admin/public_sales_detail.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            console.log('Detail loaded successfully');
            $('#detailContent').html(response);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            $('#detailContent').html('<div class="alert alert-danger" style="margin: 20px;">Failed to load details.<br><strong>Status:</strong> ' + status + '<br><strong>Error:</strong> ' + error + '</div>');
        }
    });
}

function generateVoucher(id) {
    if (!confirm('Generate voucher for this transaction?')) return;
    
    $.ajax({
        url: './agent-admin/generate_voucher.php',
        method: 'POST',
        data: { sale_id: id },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    alert('Voucher generated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch(e) {
                alert('Error parsing response: ' + e.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Request failed: ' + error);
        }
    });
}
</script>
