<?php
/*
 * Admin Panel - Digiflazz Integration Settings
 * Konfigurasi koneksi server pulsa Digiflazz dan sinkronisasi produk
 */

include_once('./include/db_config.php');
require_once(__DIR__ . '/../lib/DigiflazzClient.class.php');

$session = $_GET['session'] ?? '';
$success = '';
$error = '';
$info = '';

// Helper untuk menyimpan setting
function saveSetting(PDO $db, string $key, $value, string $type = 'string', string $description = ''): void {
    $stmt = $db->prepare("
        INSERT INTO agent_settings (setting_key, setting_value, setting_type, description, updated_by)
        VALUES (:key, :value, :type, :description, 'admin')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = 'admin'
    ");
    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
        ':type' => $type,
        ':description' => $description
    ]);
}

// Muat setting saat ini
function loadDigiflazzSettings(PDO $db): array {
    $defaults = [
        'digiflazz_enabled' => '0',
        'digiflazz_username' => '',
        'digiflazz_api_key' => '',
        'digiflazz_allow_test' => '1',
        'digiflazz_default_markup_nominal' => '0',
        'digiflazz_last_sync' => null,
        'digiflazz_webhook_secret' => '',
        'digiflazz_webhook_id' => ''
    ];

    $stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'digiflazz_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaults[$row['setting_key']] = $row['setting_value'];
    }

    if (!isset($defaults['digiflazz_default_markup_nominal']) || $defaults['digiflazz_default_markup_nominal'] === '' ) {
        if (isset($defaults['digiflazz_default_markup_percent']) && $defaults['digiflazz_default_markup_percent'] !== '') {
            $defaults['digiflazz_default_markup_nominal'] = $defaults['digiflazz_default_markup_percent'];
        }
    }

    return $defaults;
}

try {
    $db = getDBConnection();
} catch (Exception $e) {
    $error = 'Tidak dapat terhubung ke database: ' . $e->getMessage();
}

$settings = isset($db) ? loadDigiflazzSettings($db) : [];

if (isset($db) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_settings'])) {
            $db->beginTransaction();

            $enable = isset($_POST['digiflazz_enabled']) ? '1' : '0';
            $username = trim($_POST['digiflazz_username'] ?? '');
            $apiKey = trim($_POST['digiflazz_api_key'] ?? '');
            $allowTest = isset($_POST['digiflazz_allow_test']) ? '1' : '0';
            $markup = max(0, (int)($_POST['digiflazz_default_markup_nominal'] ?? 0));
            $webhookSecret = trim($_POST['digiflazz_webhook_secret'] ?? '');
            $webhookId = trim($_POST['digiflazz_webhook_id'] ?? '');

            saveSetting($db, 'digiflazz_enabled', $enable, 'boolean', 'Enable Digiflazz integration');
            saveSetting($db, 'digiflazz_username', $username, 'string', 'Digiflazz buyer username');
            saveSetting($db, 'digiflazz_api_key', $apiKey, 'string', 'Digiflazz API key');
            saveSetting($db, 'digiflazz_allow_test', $allowTest, 'boolean', 'Allow Digiflazz testing mode');
            saveSetting($db, 'digiflazz_default_markup_nominal', (string)$markup, 'number', 'Default markup nominal for Digiflazz products');
            if ($webhookSecret !== '') {
                saveSetting($db, 'digiflazz_webhook_secret', $webhookSecret, 'string', 'Webhook secret token for Digiflazz callbacks');
            }
            if ($webhookId !== '') {
                saveSetting($db, 'digiflazz_webhook_id', $webhookId, 'string', 'Webhook identifier provided by Digiflazz');
            }

            $db->commit();
            $success = 'Konfigurasi Digiflazz berhasil disimpan!';
            $settings = loadDigiflazzSettings($db);
        } elseif (isset($_POST['action']) && $_POST['action'] === 'check_balance') {
            $client = new DigiflazzClient();
            $balance = $client->checkBalance();
            $info = 'Saldo Digiflazz saat ini: Rp ' . number_format($balance['balance'], 0, ',', '.');
        } elseif (isset($_POST['action']) && $_POST['action'] === 'sync_price') {
            $client = new DigiflazzClient();
            $report = $client->syncPriceList();
            $info = 'Sinkronisasi berhasil. Prepaid: ' . $report['report']['prepaid'] . ' produk, Postpaid: ' . $report['report']['postpaid'] . ' produk.';
            $settings = loadDigiflazzSettings($db);
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Statistik produk digiflazz
$productStats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];
$digiflazzTableExists = false;

if (isset($db)) {
    try {
        $db->query("SELECT 1 FROM digiflazz_products LIMIT 1");
        $digiflazzTableExists = true;
        $stmt = $db->query("SELECT status, COUNT(*) AS total FROM digiflazz_products GROUP BY status");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['status'] === 'active') {
                $productStats['active'] = (int)$row['total'];
            } elseif ($row['status'] === 'inactive') {
                $productStats['inactive'] = (int)$row['total'];
            }
            $productStats['total'] += (int)$row['total'];
        }
    } catch (Exception $e) {
        $digiflazzTableExists = false;
    }
}

$enabled = $settings['digiflazz_enabled'] === '1';
$allowTest = $settings['digiflazz_allow_test'] === '1';
$lastSync = $settings['digiflazz_last_sync'] ? date('d M Y H:i', strtotime($settings['digiflazz_last_sync'])) : '- belum pernah -';
$statusBoxClass = $enabled ? 'bg-green' : 'bg-red';
$statusLabel = $enabled ? 'AKTIF' : 'NON-AKTIF';

?>



<div class="row">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3><i class="fa fa-plug"></i> Integrasi Digiflazz</h3>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($info): ?>
        <div class="alert alert-info"><i class="fa fa-info-circle"></i> <?= htmlspecialchars($info); ?></div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-3 col-box-6">
                <div class="box <?= $statusBoxClass; ?> bmh-75">
                    <h1 style="font-size:24px;"><?= $statusLabel; ?></h1>
                    <div><i class="fa fa-plug"></i> Status Integrasi</div>
                    <div style="font-size:12px;margin-top:5px;">Username: <strong><?= htmlspecialchars($settings['digiflazz_username']); ?></strong></div>
                </div>
            </div>

            <div class="col-3 col-box-6">
                <div class="box bg-blue bmh-75">
                    <h1><?= number_format($productStats['total']); ?>
                        <span style="font-size:15px;">produk</span>
                    </h1>
                    <div><i class="fa fa-list"></i> Produk Sinkron</div>
                    <div style="font-size:12px;margin-top:5px;">Aktif: <?= number_format($productStats['active']); ?> | Non-aktif: <?= number_format($productStats['inactive']); ?></div>
                </div>
            </div>

            <div class="col-3 col-box-6">
                <div class="box bg-yellow bmh-75">
                    <h1>Rp <?= number_format((int)$settings['digiflazz_default_markup_nominal'], 0, ',', '.'); ?>
                    </h1>
                    <div><i class="fa fa-tag"></i> Markup Default</div>
                </div>
            </div>

            <div class="col-3 col-box-6">
                <div class="box bg-aqua bmh-75">
                    <h1 style="font-size:18px;"><?= $lastSync; ?></h1>
                    <div><i class="fa fa-clock-o"></i> Terakhir Sinkron</div>
                    <div style="font-size:12px;margin-top:5px;">Gunakan tombol di bawah untuk update.</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-cog"></i> Konfigurasi API Digiflazz</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="digiflazz_enabled" value="1" <?= $enabled ? 'checked' : ''; ?>>
                                    <strong>Aktifkan Integrasi Digiflazz</strong>
                                </label>
                                <div class="help-text">Jika dinonaktifkan, menu produk digital tidak akan tersedia untuk agent.</div>
                            </div>
                            <div class="form-group">
                                <label>Username Digiflazz <span class="text-danger">*</span></label>
                                <input type="text" name="digiflazz_username" class="form-control" value="<?= htmlspecialchars($settings['digiflazz_username']); ?>" required>
                                <div class="help-text">Gunakan username buyer dari member panel Digiflazz.</div>
                            </div>
                            <div class="form-group">
                                <label>API Key Digiflazz <span class="text-danger">*</span></label>
                                <input type="text" name="digiflazz_api_key" class="form-control" value="<?= htmlspecialchars($settings['digiflazz_api_key']); ?>" required>
                                <div class="help-text">Salin dari menu API Connection di Digiflazz.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Markup Default (Rp)</label>
                                <input type="number" min="0" step="50" name="digiflazz_default_markup_nominal" class="form-control" value="<?= (int)$settings['digiflazz_default_markup_nominal']; ?>">
                                <div class="help-text">Nilai nominal yang ditambahkan ke harga dasar saat ditampilkan ke agent/public.</div>
                            </div>
                            
                            <?php
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $webhookUrl = $protocol . "://" . $host . "/api/digiflazz_webhook.php";
                            ?>
                            <div class="form-group">
                                <label>Webhook URL</label>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" value="<?= $webhookUrl; ?>" id="webhookUrl" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-default" onclick="copyWebhookUrl()" title="Salin URL">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="help-text">Salin URL ini dan masukkan ke pengaturan webhook di dashboard Digiflazz.</div>
                            </div>

                            <div class="form-group">
                                <label>Webhook Secret</label>
                                <input type="text" name="digiflazz_webhook_secret" class="form-control" value="<?= htmlspecialchars($settings['digiflazz_webhook_secret']); ?>" autocomplete="off">
                                <div class="help-text">Digunakan untuk memverifikasi header <code>X-Hub-Signature</code> pada webhook Digiflazz. Kosongkan untuk menggunakan metode verifikasi default.</div>
                            </div>
                            <div class="form-group">
                                <label>Webhook ID</label>
                                <input type="text" name="digiflazz_webhook_id" class="form-control" value="<?= htmlspecialchars($settings['digiflazz_webhook_id']); ?>" autocomplete="off">
                                <div class="help-text">Masukkan ID webhook dari dashboard Digiflazz (<code><?= htmlspecialchars($settings['digiflazz_webhook_id'] ?: 'contoh: DlmZMW'); ?></code>).</div>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="digiflazz_allow_test" value="1" <?= $allowTest ? 'checked' : ''; ?>>
                                    Izinkan mode testing Digiflazz
                                </label>
                                <div class="help-text">Aktifkan untuk mengirim parameter <code>testing=true</code> saat menggunakan nomor uji coba.</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Simpan Konfigurasi
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-refresh"></i> Sinkronisasi & Monitoring</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="check_balance">
                            <button type="submit" class="btn btn-info" <?= $enabled ? '' : 'disabled'; ?>>
                                <i class="fa fa-money"></i> Cek Saldo Digiflazz
                            </button>
                            <div class="help-text" style="margin-top:8px;">Menampilkan saldo deposit Digiflazz saat ini.</div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="POST" onsubmit="return confirm('Sinkronisasi akan memperbarui daftar produk dari Digiflazz. Lanjutkan?');">
                            <input type="hidden" name="action" value="sync_price">
                            <button type="submit" class="btn btn-success" <?= $enabled ? '' : 'disabled'; ?>>
                                <i class="fa fa-download"></i> Sinkronisasi Daftar Produk
                            </button>
                            <div class="help-text" style="margin-top:8px;">Mengambil daftar harga terbaru (prepaid & postpaid) dan menyimpannya ke database.</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($productStats['total'] > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-list"></i> Ringkasan Produk</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Total Produk</th>
                            <th>Produk Aktif</th>
                            <th>Produk Non-Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $summary = $db->query("SELECT type, SUM(status = 'active') AS active_count, SUM(status = 'inactive') AS inactive_count, COUNT(*) AS total FROM digiflazz_products GROUP BY type ORDER BY type");
                        while ($row = $summary->fetch(PDO::FETCH_ASSOC)) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars(strtoupper($row['type'])) . '</td>';
                            echo '<td>' . number_format($row['total']) . '</td>';
                            echo '<td>' . number_format($row['active_count']) . '</td>';
                            echo '<td>' . number_format($row['inactive_count']) . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyWebhookUrl() {
    var copyText = document.getElementById("webhookUrl");
    copyText.select();
    copyText.setSelectionRange(0, 99999); /* For mobile devices */
    document.execCommand("copy");
    alert("Webhook URL berhasil disalin: " + copyText.value);
}
</script>
</div>
