<?php
/*
 * Billing Dashboard - Admin view
 */

include_once('./include/db_config.php');
require_once('./lib/Agent.class.php');
require_once('./lib/WhatsAppNotification.class.php');

$db = getDBConnection();
if (!$db) {
    echo '<div class="alert bg-danger">Gagal terhubung ke database.</div>';
    return;
}

// Summary metrics
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}

$today = date('Y-m-d');
$currentMonth = date('Y-m');

$totalInvoicesStmt = $db->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid, SUM(amount) AS total_amount, SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS paid_amount FROM billing_invoices WHERE period = :period");
$totalInvoicesStmt->execute([':period' => $currentMonth]);
$invoiceSummary = $totalInvoicesStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'paid' => 0, 'total_amount' => 0, 'paid_amount' => 0];

$customerSummaryStmt = $db->query("SELECT SUM(status='active') AS active_customers, SUM(is_isolated=1) AS isolated_customers FROM billing_customers");
$customerSummary = $customerSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['active_customers' => 0, 'isolated_customers' => 0];

$upcomingDueStmt = $db->prepare("SELECT bi.*, bc.name AS customer_name, bp.profile_name FROM billing_invoices bi INNER JOIN billing_customers bc ON bi.customer_id = bc.id LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id WHERE bi.status IN ('unpaid','overdue') AND bi.due_date BETWEEN :today AND :limit_date ORDER BY bi.due_date ASC LIMIT 10");
$upcomingDueStmt->execute([
    ':today' => $today,
    ':limit_date' => date('Y-m-d', strtotime('+7 days', strtotime($today))),
]);
$upcomingDueInvoices = $upcomingDueStmt->fetchAll(PDO::FETCH_ASSOC);

$revenueTrendStmt = $db->query("SELECT period, SUM(amount) AS total_amount, SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS paid_amount FROM billing_invoices WHERE period >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m') GROUP BY period ORDER BY period ASC");
$revenueTrend = $revenueTrendStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- Include responsive tables CSS -->
<link rel="stylesheet" href="./css/responsive-tables.css">

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

.billing-module .list-group-item {
    font-size: 14px;
    line-height: 1.55;
}

.billing-module table {
    font-size: 14px;
}

@media (min-width: 992px) {
    .billing-module .summary-row > [class*='col-'] {
        display: flex;
    }
}

.billing-module .chart-card,
.billing-module .due-card {
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}
</style>
<div class="billing-module">
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-credit-card"></i> Billing Dashboard</h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-3 col-box-6">
                        <div class="box bg-green bmh-75">
                            <h1><?= number_format($invoiceSummary['paid'], 0); ?>
                                <span style="font-size: 15px;">paid</span>
                            </h1>
                            <div><i class="fa fa-check"></i> Invoice Lunas Bulan Ini</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-red bmh-75">
                            <h1><?= number_format($invoiceSummary['total'] - $invoiceSummary['paid'], 0); ?>
                                <span style="font-size: 15px;">unpaid</span>
                            </h1>
                            <div><i class="fa fa-exclamation-triangle"></i> Invoice Belum Lunas</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-blue bmh-75">
                            <h1 style="font-size: 18px;">Rp <?= number_format($invoiceSummary['paid_amount'], 0, ',', '.'); ?></h1>
                            <div><i class="fa fa-money"></i> Pendapatan Bulan Ini</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-yellow bmh-75">
                            <h1><?= number_format($customerSummary['active_customers'], 0); ?>
                                <span style="font-size: 15px;">active</span>
                            </h1>
                            <div><i class="fa fa-users"></i> Pelanggan Aktif</div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 20px;">
                    <div class="col-lg-7 col-12">
                        <div class="card chart-card">
                            <div class="card-header">
                                <h3><i class="fa fa-line-chart"></i> Tren Pendapatan 6 Bulan</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="billingRevenueChart" height="180"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 col-12">
                        <div class="card due-card">
                            <div class="card-header">
                                <h3><i class="fa fa-calendar"></i> Tagihan Jatuh Tempo (7 Hari)</h3>
                            </div>
                            <div class="card-body table-responsive">
                                <?php if (empty($upcomingDueInvoices)): ?>
                                    <div class="alert bg-light">Tidak ada tagihan jatuh tempo dalam 7 hari.</div>
                                <?php else: ?>
                                    <!-- Desktop Table View -->
                                    <div class="desktop-only">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Pelanggan</th>
                                                <th>Tanggal Jatuh Tempo</th>
                                                <th>Paket</th>
                                                <th>Nominal</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingDueInvoices as $invoice): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($invoice['customer_name']); ?></td>
                                                    <td><?= date('d M', strtotime($invoice['due_date'])); ?></td>
                                                    <td><?= htmlspecialchars($invoice['profile_name'] ?? '-'); ?></td>
                                                    <td>Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></td>
                                                    <td><span class="badge badge-<?= $invoice['status']; ?>"><?= ucfirst($invoice['status']); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                    
                                    <!-- Mobile Card View -->
                                    <div class="mobile-only">
                                        <?php foreach ($upcomingDueInvoices as $invoice): ?>
                                        <div class="data-card" style="border-left: 4px solid #f0ad4e;">
                                            <div class="data-card-header">
                                                <div class="data-card-title">
                                                    <i class="fa fa-user"></i> <?= htmlspecialchars($invoice['customer_name']); ?>
                                                </div>
                                                <div class="data-card-subtitle">
                                                    <?= date('d M', strtotime($invoice['due_date'])); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="data-row">
                                                <span class="data-label">Paket:</span>
                                                <span class="data-value"><?= htmlspecialchars($invoice['profile_name'] ?? '-'); ?></span>
                                            </div>
                                            
                                            <div class="data-row">
                                                <span class="data-label">Nominal:</span>
                                                <span class="data-value" style="font-weight: bold; color: #f0ad4e;">Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></span>
                                            </div>
                                            
                                            <div class="data-row">
                                                <span class="data-label">Status:</span>
                                                <span class="data-value">
                                                    <span class="badge badge-<?= $invoice['status']; ?>"><?= ucfirst($invoice['status']); ?></span>
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

                <div class="row" style="margin-top: 20px;">
                    <div class="col-12">
                        <a href="./?hotspot=billing-profiles&session=<?= $session; ?>" class="btn btn-primary">
                            <i class="fa fa-sliders"></i> Kelola Profil Paket
                        </a>
                        <a href="./?hotspot=billing-customers&session=<?= $session; ?>" class="btn btn-success">
                            <i class="fa fa-users"></i> Kelola Pelanggan
                        </a>
                        <a href="./?hotspot=billing-invoices&session=<?= $session; ?>" class="btn btn-warning">
                            <i class="fa fa-file-text-o"></i> Kelola Tagihan
                        </a>
                        <a href="./?hotspot=billing-settings&session=<?= $session; ?>" class="btn btn-info">
                            <i class="fa fa-cog"></i> Pengaturan Billing
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const revenueCtx = document.getElementById('billingRevenueChart');
const revenueData = <?= json_encode($revenueTrend); ?>;
const labels = revenueData.map(item => item.period);
const totalAmounts = revenueData.map(item => parseFloat(item.total_amount || 0));
const paidAmounts = revenueData.map(item => parseFloat(item.paid_amount || 0));

new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Total Tagihan',
                data: totalAmounts,
                borderColor: '#f0ad4e',
                backgroundColor: 'rgba(240, 173, 78, 0.2)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Sudah Dibayar',
                data: paidAmounts,
                borderColor: '#5cb85c',
                backgroundColor: 'rgba(92, 184, 92, 0.2)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const value = context.parsed.y;
                        return label + ': Rp ' + new Intl.NumberFormat('id-ID').format(value);
                    }
                }
            }
        }
    }
});
</script>
