<?php
/*
 * Billing Portal - Customer self-service page
 */

session_start();

date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');

if (!isset($_SESSION['billing_portal_customer_id']) || (int)$_SESSION['billing_portal_customer_id'] <= 0) {
    header('Location: billing_login.php');
    exit;
}

$activeCustomerId = (int)$_SESSION['billing_portal_customer_id'];

$wifiFeedback = [
    'ssid' => ['error' => null, 'success' => null],
    'password' => ['error' => null, 'success' => null],
];

try {
    $billingService = new BillingService();
    $customer = $billingService->getCustomerById($activeCustomerId);

    if (!$customer) {
        unset($_SESSION['billing_portal_customer_id']);
        $_SESSION['billing_portal_flash_error'] = 'Data pelanggan tidak ditemukan. Silakan login kembali.';
        header('Location: billing_login.php');
        exit;
    }

    $profile = $billingService->getProfileById((int)$customer['profile_id']);
    $invoices = $billingService->listInvoices(['customer_id' => (int)$customer['id']], 12);
    $outstanding = array_filter($invoices, static fn ($invoice) => in_array($invoice['status'], ['unpaid', 'overdue'], true));
    $outstandingTotal = array_reduce($outstanding, static function ($carry, $invoice) {
        $amount = is_numeric($invoice['amount'] ?? null) ? (float)$invoice['amount'] : 0.0;
        return $carry + $amount;
    }, 0.0);
    $deviceSnapshot = $billingService->getCustomerDeviceSnapshot((int)$customer['id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_ssid'])) {
            $newSsid = trim($_POST['ssid'] ?? '');

            if ($newSsid === '' || mb_strlen($newSsid) < 3) {
                $wifiFeedback['ssid']['error'] = 'SSID minimal 3 karakter.';
            } else {
                try {
                    $billingService->changeCustomerWifi((int)$customer['id'], $newSsid, null);
                    $wifiFeedback['ssid']['success'] = 'SSID baru berhasil dikirim. Hubungkan ulang perangkat setelah beberapa menit.';
                } catch (Throwable $e) {
                    $wifiFeedback['ssid']['error'] = $e->getMessage();
                }
            }
        }

        if (isset($_POST['change_password'])) {
            $newPassword = trim($_POST['password'] ?? '');

            if ($newPassword === '' || mb_strlen($newPassword) < 8) {
                $wifiFeedback['password']['error'] = 'Password minimal 8 karakter.';
            } else {
                try {
                    $billingService->changeCustomerWifi((int)$customer['id'], null, $newPassword);
                    $wifiFeedback['password']['success'] = 'Password WiFi berhasil dikirim. Gunakan password baru setelah perangkat tersinkron.';
                } catch (Throwable $e) {
                    $wifiFeedback['password']['error'] = $e->getMessage();
                }
            }
        }
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Terjadi kesalahan: ' . htmlspecialchars($e->getMessage());
    exit;
}

$theme = 'default';
$themecolor = '#3a4149';
if (file_exists(__DIR__ . '/../include/theme.php')) {
    include(__DIR__ . '/../include/theme.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Pelanggan - <?= htmlspecialchars($customer['name']); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    <style>
        body { background-color: #ecf0f5; font-family: 'Source Sans Pro', Arial, sans-serif; }
        .wrapper { max-width: 940px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.1); margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
        .card-header .header-info { flex:1 1 auto; }
        .logout-link { background:#f3f4f6; border-radius:6px; padding:8px 14px; font-size:13px; color:#1f2937; text-decoration:none; border:1px solid #cbd5f5; transition: all 0.2s; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .logout-link:hover { background:#e5e7eb; color:#0f172a; }
        .card-body { padding: 20px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .box { border-radius: 4px; padding: 18px; color: #fff; min-height: 110px; }
        .box h2 { margin: 0 0 6px; font-size: 26px; }
        .badge-status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
        .badge-status.paid { background: #d1fae5; color: #065f46; }
        .badge-status.unpaid { background: #fee2e2; color: #991b1b; }
        .badge-status.overdue { background: #fde68a; color: #92400e; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { border: 1px solid #e5e7eb; padding: 10px; font-size: 13px; }
        .invoice-table th { background: #f3f4f6; font-weight: 600; }
        .form-control[disabled] { background: #f3f4f6; }
        .btn { cursor: pointer; }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: #ffffff;
            line-height: 1.55;
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            max-width: min(100%, 560px);
            box-sizing: border-box;
            flex-wrap: wrap;
        }
        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 10px 0 0 10px;
        }
        .alert-info {
            color: #075985;
        }
        .alert-info::before {
            background: #0ea5e9;
        }
        .alert-warning {
            color: #92400e;
        }
        .alert-warning::before {
            background: #d97706;
        }
        .alert-success {
            color: #166534;
        }
        .alert-success::before {
            background: #22c55e;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
        .wifi-card-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-top: 10px; }
        .wifi-action { background: #f9fafb; border-radius: 12px; padding: 18px; border: 1px solid #e5e7eb; box-shadow: inset 0 0 0 1px rgba(15,23,42,0.03); }
        .wifi-action h4 { margin-top: 0; font-weight: 600; color: #0f172a; }
        .wifi-action .btn { margin-top: 12px; }
        .wifi-action small.note { display: block; margin-top: 8px; color: #6b7280; }
        .wifi-feedback { margin-bottom: 12px; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
        .wifi-feedback.success { background: #dcfce7; color: #166534; }
        .wifi-feedback.error { background: #fee2e2; color: #991b1b; }
        .device-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .device-stat { background: #f9fafb; border-radius: 10px; padding: 16px; border: 1px solid #e5e7eb; box-shadow: inset 0 0 0 1px rgba(15,23,42,0.03); }
        .device-stat .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
        .device-stat .value { margin-top: 6px; font-size: 20px; font-weight: 600; color: #0f172a; }
        .device-stat .value.small { font-size: 16px; }
        .device-status.offline { color: #991b1b; }
        .device-status.online { color: #047857; }
        @media (max-width: 520px) {
            .device-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .wrapper { padding: 10px; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="card-header">
            <div class="header-info">
                <h2><i class="fa fa-user-circle"></i> Selamat datang, <?= htmlspecialchars($customer['name']); ?></h2>
                <p style="margin:0; color:#6b7280;">Nomor layanan: <?= htmlspecialchars($customer['service_number']); ?></p>
                <p style="margin:0; color:#9ca3af; font-size:13px;">Telepon terdaftar: <?= htmlspecialchars($customer['phone'] ?: '-'); ?></p>
            </div>
            <a href="billing_logout.php" class="logout-link"><i class="fa fa-sign-out"></i> Keluar</a>
        </div>
        <div class="card-body">
            <div class="summary-grid">
                <div class="box" style="background:#3c8dbc;">
                    <h2><?= htmlspecialchars($profile['profile_name'] ?? 'Tidak diketahui'); ?></h2>
                    <p style="margin:0;">Harga: Rp <?= number_format($profile['price_monthly'] ?? 0, 0, ',', '.'); ?>/bulan</p>
                    <p style="margin:0;">Billing setiap tanggal <?= str_pad((int)$customer['billing_day'], 2, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="box" style="background:#00a65a;">
                    <h2><?= count($outstanding); ?></h2>
                    <p style="margin:0;">Tagihan belum dibayar</p>
                </div>
                <div class="box" style="background:#f39c12;">
                    <h2><?= htmlspecialchars(strtoupper($customer['status'])); ?></h2>
                    <p style="margin:0;">Status akun</p>
                    <p style="margin:0;">Kondisi: <?= (int)$customer['is_isolated'] === 1 ? 'TERISOLASI' : 'NORMAL'; ?></p>
                </div>
                <div class="box" style="background:#dd4b39;">
                    <h2>Rp <?= number_format($outstandingTotal, 0, ',', '.'); ?></h2>
                    <p style="margin:0;">Total saldo tagihan</p>
                    <p style="margin:0;">Gabungan invoice belum dibayar</p>
                </div>
            </div>

            <?php if (!empty($outstanding)): ?>
                <div class="alert alert-warning">
                    <strong>Perhatian:</strong> Ada tagihan yang belum dibayar. Silakan lakukan pembayaran agar layanan kembali normal.
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <strong>Terima kasih!</strong> Tidak ada tagihan tertunda saat ini.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($deviceSnapshot)): ?>
        <?php
        $snapshotStatus = $deviceSnapshot['status'] ?? null;
        $statusLabel = $snapshotStatus ? strtoupper($snapshotStatus) : 'TIDAK DIKETAHUI';
        $statusClass = $snapshotStatus === 'online' ? 'online' : ($snapshotStatus === 'offline' ? 'offline' : '');
        $connectedDevices = $deviceSnapshot['connected_devices'] ?? null;
        $connectedLabel = $connectedDevices !== null ? $connectedDevices : 'N/A';
        $rxPowerRaw = $deviceSnapshot['rx_power'] ?? null;
        $rxLabel = ($rxPowerRaw !== null && $rxPowerRaw !== 'N/A' && $rxPowerRaw !== '') ? $rxPowerRaw . ' dBm' : 'N/A';
        $temperatureRaw = $deviceSnapshot['temperature'] ?? null;
        $temperatureLabel = ($temperatureRaw !== null && $temperatureRaw !== 'N/A' && $temperatureRaw !== '') ? $temperatureRaw . '°C' : 'N/A';
        $pppoeLabel = $deviceSnapshot['pppoe_username'] ?? 'N/A';
        ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-signal"></i> Informasi ONU</h3>
                <p style="margin:0; color:#6b7280;">Data ditarik langsung dari GenieACS untuk memantau perangkat Anda.</p>
            </div>
            <div class="card-body">
                <div class="device-stats-grid">
                    <div class="device-stat">
                        <div class="label">Status ONU</div>
                        <div class="value device-status <?= htmlspecialchars($statusClass); ?>"><?= htmlspecialchars($statusLabel); ?></div>
                        <small style="color:#6b7280;">Terhubung ke ACS sebagai <?= htmlspecialchars($deviceSnapshot['device_id'] ?? 'N/A'); ?></small>
                    </div>
                    <div class="device-stat">
                        <div class="label">PPPoE Username</div>
                        <div class="value small"><?= htmlspecialchars($pppoeLabel ?: 'N/A'); ?></div>
                    </div>
                    <div class="device-stat">
                        <div class="label">Perangkat Terkoneksi</div>
                        <div class="value"><?= htmlspecialchars((string)$connectedLabel); ?></div>
                        <small style="color:#6b7280;">Termasuk perangkat WiFi aktif</small>
                    </div>
                    <div class="device-stat">
                        <div class="label">RX Power</div>
                        <div class="value"><?= htmlspecialchars($rxLabel); ?></div>
                        <small style="color:#6b7280;">Semakin dekat ke 0 dBm semakin baik</small>
                    </div>
                    <?php if ($temperatureLabel !== 'N/A'): ?>
                    <div class="device-stat">
                        <div class="label">Suhu Perangkat</div>
                        <div class="value"><?= htmlspecialchars($temperatureLabel); ?></div>
                        <small style="color:#6b7280;">Suhu perangkat saat ini</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-file-text-o"></i> Riwayat Tagihan</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Nominal</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                            <th>Dibayar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 20px; color:#6b7280;">
                                    Belum ada invoice yang tercatat.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <?php $status = strtolower($invoice['status'] ?? 'unpaid'); ?>
                                <tr>
                                    <td><?= htmlspecialchars($invoice['period']); ?></td>
                                    <td>Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></td>
                                    <td><?= date('d M Y', strtotime($invoice['due_date'])); ?></td>
                                    <td><span class="badge-status <?= $status; ?>"><?= ucfirst($status); ?></span></td>
                                    <td><?= $invoice['paid_at'] ? date('d M Y H:i', strtotime($invoice['paid_at'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-wifi"></i> Kelola WiFi</h3>
            <p style="margin:0; color:#6b7280;">Perubahan SSID dan password dikirim lewat GenieACS. Gunakan formulir terpisah di bawah ini.</p>
        </div>
        <div class="card-body">
            <?php if ((int)$customer['is_isolated'] === 1): ?>
                <div class="alert alert-info">
                    Akun Anda sedang dalam kondisi isolasi. Pastikan tagihan sudah dibayar agar profil normal dikembalikan.
                </div>
            <?php endif; ?>

            <div class="wifi-card-split">
                <div class="wifi-action">
                    <h4><i class="fa fa-wifi"></i> Ubah SSID</h4>
                    <?php if ($wifiFeedback['ssid']['error']): ?>
                        <div class="wifi-feedback error"><strong>Gagal:</strong> <?= htmlspecialchars($wifiFeedback['ssid']['error']); ?></div>
                    <?php elseif ($wifiFeedback['ssid']['success']): ?>
                        <div class="wifi-feedback success"><strong>Sukses:</strong> <?= htmlspecialchars($wifiFeedback['ssid']['success']); ?></div>
                    <?php endif; ?>
                    <form method="post" class="wifi-form">
                        <input type="hidden" name="change_ssid" value="1">
                        <div class="form-group">
                            <label>SSID Baru</label>
                            <input type="text" name="ssid" class="form-control" placeholder="Nama WiFi" minlength="3" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-paper-plane"></i> Kirim SSID Baru
                        </button>
                        <small class="note">SSID minimal 3 karakter. Router mungkin butuh beberapa menit untuk menerapkan perubahan.</small>
                    </form>
                </div>
                <div class="wifi-action">
                    <h4><i class="fa fa-lock"></i> Ubah Password</h4>
                    <?php if ($wifiFeedback['password']['error']): ?>
                        <div class="wifi-feedback error"><strong>Gagal:</strong> <?= htmlspecialchars($wifiFeedback['password']['error']); ?></div>
                    <?php elseif ($wifiFeedback['password']['success']): ?>
                        <div class="wifi-feedback success"><strong>Sukses:</strong> <?= htmlspecialchars($wifiFeedback['password']['success']); ?></div>
                    <?php endif; ?>
                    <form method="post" class="wifi-form">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fa fa-shield"></i> Kirim Password Baru
                        </button>
                        <small class="note">Setidaknya 8 karakter kombinasi huruf & angka untuk keamanan terbaik.</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>