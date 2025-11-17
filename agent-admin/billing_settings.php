<?php
/*
 * Billing Settings - Skeleton UI
 */

include_once('./include/db_config.php');

try {
    $db = getDBConnection();
} catch (Exception $e) {
    echo '<div class="alert bg-danger">Gagal terhubung ke database: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

function loadBillingSettings(PDO $db): array {
    $stmt = $db->query("SELECT setting_key, setting_value FROM billing_settings");
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[$row['setting_key']] = $row['setting_value'];
    }
    return $result;
}

function saveBillingSetting(PDO $db, string $key, $value): void {
    $stmt = $db->prepare(
        "INSERT INTO billing_settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

$error = '';
$success = '';

// Tampilkan pesan sukses/error jika ada
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Pengaturan billing berhasil disimpan!';
} elseif (isset($_GET['error']) && !empty($_GET['error'])) {
    $error = 'Gagal menyimpan pengaturan: ' . htmlspecialchars($_GET['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        if (isset($_POST['save_portal_settings'])) {
            $contactHeading = trim($_POST['portal_contact_heading'] ?? '');
            $contactWhatsapp = trim($_POST['portal_contact_whatsapp'] ?? '');
            $contactEmail = trim($_POST['portal_contact_email'] ?? '');
            $contactBody = trim($_POST['portal_contact_body'] ?? '');
            $portalUrl = trim($_POST['portal_base_url'] ?? '');

            saveBillingSetting($db, 'billing_portal_contact_heading', $contactHeading);
            saveBillingSetting($db, 'billing_portal_contact_whatsapp', $contactWhatsapp);
            saveBillingSetting($db, 'billing_portal_contact_email', $contactEmail);
            saveBillingSetting($db, 'billing_portal_contact_body', $contactBody);
            saveBillingSetting($db, 'billing_portal_base_url', $portalUrl);
        }

        if (isset($_POST['save_otp_settings'])) {
            $otpEnabled = isset($_POST['portal_otp_enabled']) ? '1' : '0';
            $otpDigits = (int)($_POST['portal_otp_digits'] ?? 6);
            $otpExpiry = max(1, (int)($_POST['portal_otp_expiry'] ?? 5));
            $otpAttempts = max(1, (int)($_POST['portal_otp_max_attempts'] ?? 5));

            $otpDigits = min(8, max(4, $otpDigits));
            $otpExpiry = min(30, $otpExpiry);
            $otpAttempts = min(10, $otpAttempts);

            saveBillingSetting($db, 'billing_portal_otp_enabled', $otpEnabled);
            saveBillingSetting($db, 'billing_portal_otp_digits', (string)$otpDigits);
            saveBillingSetting($db, 'billing_portal_otp_expiry_minutes', (string)$otpExpiry);
            saveBillingSetting($db, 'billing_portal_otp_max_attempts', (string)$otpAttempts);
        }

        if (isset($_POST['save_billing_reminder_settings'])) {
            $reminderTemplate = trim($_POST['billing_reminder_template'] ?? '');
            $reminderDays = trim($_POST['billing_reminder_days_before'] ?? '');
            $isolationDelay = (int)($_POST['billing_isolation_delay'] ?? 1);

            // Validasi format hari reminder
            if (!empty($reminderDays)) {
                $daysArray = array_map('trim', explode(',', $reminderDays));
                $validDays = [];
                foreach ($daysArray as $day) {
                    if (is_numeric($day) && $day > 0) {
                        $validDays[] = (int)$day;
                    }
                }
                $reminderDays = implode(',', $validDays);
            }

            $isolationDelay = max(0, min(30, $isolationDelay));

            saveBillingSetting($db, 'billing_reminder_template', $reminderTemplate);
            saveBillingSetting($db, 'billing_reminder_days_before', $reminderDays);
            saveBillingSetting($db, 'billing_isolation_delay', (string)$isolationDelay);
        }

        $db->commit();
        // Redirect dengan pesan sukses
        header('Location: ?hotspot=billing-settings&session=' . urlencode($_GET['session'] ?? '') . '&success=1');
        exit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // Redirect dengan pesan error
        $errorMessage = urlencode($e->getMessage());
        header('Location: ?hotspot=billing-settings&session=' . urlencode($_GET['session'] ?? '') . '&error=' . $errorMessage);
        exit();
    }
}

$billingSettings = loadBillingSettings($db);

$paymentGateways = $db->query(
    "SELECT gateway_name AS name, merchant_code AS provider, is_active, callback_token AS callback_url
     FROM payment_gateway_config
     ORDER BY gateway_name"
)->fetchAll(PDO::FETCH_ASSOC);
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

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.settings-card {
    background: #f9fafb;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    padding: 20px;
}

.settings-card h4 {
    margin-top: 0;
    font-weight: 600;
    color: #0f172a;
}

.settings-card small { color: #6b7280; }

.placeholder-form {
    border: 1px dashed #d1d5db;
    padding: 20px;
    border-radius: 8px;
    background: #fff;
}
</style>

<div class="billing-module">
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-cog"></i> Pengaturan Billing</h3>
            </div>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin: 20px;">
                    <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin: 20px;">
                    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
</div>
            <div class="card-body">
                <div class="settings-grid">
                    <div class="settings-card">
                        <h4><i class="fa fa-whatsapp"></i> Pengingat WhatsApp</h4>
                        <p>Gunakan konfigurasi WhatsApp yang sudah aktif di sistem. Template pesan disimpan di tabel <code>billing_settings</code>.</p>
                        <ul style="padding-left: 18px; margin-bottom:0;">
                            <li>Reminder H-3 dan H+1 otomatis.</li>
                            <li>Menggunakan nomor & token dari menu WhatsApp Settings.</li>
                        </ul>
                    </div>
                    <div class="settings-card">
                        <h4><i class="fa fa-credit-card"></i> Payment Gateway</h4>
                        <?php if (empty($paymentGateways)): ?>
                            <p class="text-muted">Belum ada gateway pembayaran aktif. Atur melalui menu <strong>Payment Gateway</strong>.</p>
                        <?php else: ?>
                            <ul style="padding-left: 18px;">
                                <?php foreach ($paymentGateways as $gateway): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($gateway['name']); ?></strong>
                                        (<?= htmlspecialchars($gateway['provider']); ?>)
                                        <?= (int)$gateway['is_active'] === 1 ? '<span class="label label-success">aktif</span>' : '<span class="label label-default">nonaktif</span>'; ?>
                                        <br>
                                        <small>Callback: <?= htmlspecialchars($gateway['callback_url']); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="settings-card">
                        <h4><i class="fa fa-plug"></i> Integrasi GenieACS</h4>
                        <p>Perubahan SSID/Password pelanggan memanfaatkan API GenieACS yang sudah dikonfigurasi. Pastikan file <code>genieacs/config.php</code> terisi benar.</p>
                        <p style="margin-bottom:0;">
                            Endpoint yang akan dipanggil: <code>GenieACS::changeWiFi()</code> dengan parameter SSID & password baru.
                        </p>
                    </div>
                    <div class="settings-card">
                        <h4><i class="fa fa-user-circle"></i> Portal Pelanggan</h4>
                        <p>Atur teks kontak dan URL portal pelanggan. Informasi ini tampil di halaman login pelanggan.</p>
                        <form method="post" style="margin-top:10px;">
                            <input type="hidden" name="save_portal_settings" value="1">
                            <div class="form-group">
                                <label>Judul Kontak</label>
                                <input type="text" name="portal_contact_heading" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_contact_heading'] ?? 'Butuh bantuan? Hubungi Admin ISP'); ?>" placeholder="Butuh bantuan?">
                            </div>
                            <div class="form-group">
                                <label>Nomor WhatsApp</label>
                                <input type="text" name="portal_contact_whatsapp" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_contact_whatsapp'] ?? '08123456789'); ?>" placeholder="08123456789">
                                <small>Nomor ini akan dikirimkan bersama OTP jika OTP WhatsApp diaktifkan.</small>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="portal_contact_email" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_contact_email'] ?? 'support@ispanda.com'); ?>" placeholder="support@ispanda.com">
                            </div>
                            <div class="form-group">
                                <label>Keterangan Tambahan</label>
                                <textarea name="portal_contact_body" class="form-control" rows="3" placeholder="Jam operasional, alamat kantor, dsb."><?= htmlspecialchars($billingSettings['billing_portal_contact_body'] ?? 'Jam operasional: 08.00 - 22.00'); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>URL Portal Dasar</label>
                                <input type="text" name="portal_base_url" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_base_url'] ?? ''); ?>" placeholder="https://ispanda.com/public/billing_login.php">
                                <small>Digunakan di link pengingat WhatsApp. Kosongkan jika tidak ingin disertakan.</small>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Portal</button>
                        </form>
                    </div>
                </div>

                <div class="settings-grid">
                    <div class="settings-card" style="grid-column: span 2;">
                        <h4><i class="fa fa-key"></i> OTP Portal Pelanggan</h4>
                        <p>OTP dikirim via WhatsApp gateway bawaan. Pastikan WhatsApp gateway aktif dan nomor pelanggan valid.</p>
                        <form method="post" style="margin-top:10px;">
                            <input type="hidden" name="save_otp_settings" value="1">
                            <div class="checkbox-group" style="margin-bottom:15px;">
                                <input type="checkbox" name="portal_otp_enabled" id="portal_otp_enabled" value="1" <?= ($billingSettings['billing_portal_otp_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <label for="portal_otp_enabled" style="margin-bottom:0;">Aktifkan OTP saat login portal pelanggan</label>
                            </div>
                            <div class="settings-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr));">
                                <div class="form-group">
                                    <label>Jumlah Digit OTP</label>
                                    <input type="number" min="4" max="8" name="portal_otp_digits" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_otp_digits'] ?? '6'); ?>">
                                    <small>4-8 digit.</small>
                                </div>
                                <div class="form-group">
                                    <label>Masa Berlaku (menit)</label>
                                    <input type="number" min="1" max="30" name="portal_otp_expiry" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_otp_expiry_minutes'] ?? '5'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Batas Percobaan</label>
                                    <input type="number" min="1" max="10" name="portal_otp_max_attempts" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_portal_otp_max_attempts'] ?? '5'); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan OTP</button>
                        </form>
                    </div>
                </div>

                <div class="placeholder-form">
                    <h4 style="margin-top:0;"><i class="fa fa-edit"></i> Pengaturan Reminder Billing</h4>
                    <p>API penyimpanan pengaturan billing akan menulis ke tabel <code>billing_settings</code> tanpa menduplikasi konfigurasi gateway.</p>
                    <form method="post">
                        <input type="hidden" name="save_billing_reminder_settings" value="1">
                        <div class="form-group">
                            <label>Template Pesan WhatsApp</label>
                            <textarea name="billing_reminder_template" class="form-control" rows="4"><?= htmlspecialchars($billingSettings['billing_reminder_template'] ?? "Halo {{nama}}, tagihan Wifi {{periode}} sebesar Rp {{total}}. Mohon bayar sebelum {{jatuh_tempo}}."); ?></textarea>
                            <small>Placeholder: {{nama}}, {{periode}}, {{total}}, {{jatuh_tempo}}</small>
                        </div>
                        <div class="form-group">
                            <label>Hari Reminder</label>
                            <input type="text" name="billing_reminder_days_before" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_reminder_days_before'] ?? '3,1'); ?>">
                            <small>Format: angka dipisah koma, contoh 3,1 untuk H-3 dan H-1.</small>
                        </div>
                        <div class="form-group">
                            <label>Grace Period Isolasi (hari)</label>
                            <input type="number" name="billing_isolation_delay" class="form-control" value="<?= htmlspecialchars($billingSettings['billing_isolation_delay'] ?? '1'); ?>" min="0" max="30">
                            <small>Jumlah hari setelah jatuh tempo sebelum pelanggan diisolasi.</small>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Simpan Pengaturan</button>
                    </form>
                </div>

                <div class="alert bg-warning" style="margin-top:20px;">
                    <strong>Next:</strong> API backend akan membaca pengaturan ini untuk dijalankan oleh cron (generate invoice, reminder WhatsApp, isolasi MikroTik).
                </div>
            </div>
        </div>
    </div>
</div>
