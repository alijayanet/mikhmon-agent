<?php
/*
 * Setup Admin WhatsApp Numbers
 * Add admin numbers that can generate vouchers without balance
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Admin WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .btn { padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #5568d3; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .help-text { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>";

echo "<h1>🔐 Setup Admin WhatsApp Numbers</h1>";

include_once('./include/db_config.php');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminNumbers = $_POST['admin_numbers'] ?? '';
    
    if (!empty($adminNumbers)) {
        try {
            $db = getDBConnection();
            
            // Clean and validate numbers
            $numbers = explode(',', $adminNumbers);
            $numbers = array_map('trim', $numbers);
            $numbers = array_filter($numbers);
            
            // Validate format (should be 628xxx)
            $validNumbers = [];
            foreach ($numbers as $number) {
                if (preg_match('/^62\d{9,13}$/', $number)) {
                    $validNumbers[] = $number;
                } else {
                    $error .= "Format nomor salah: $number (harus 628xxx)<br>";
                }
            }
            
            if (!empty($validNumbers)) {
                $numbersString = implode(',', $validNumbers);
                
                // Insert or update
                $stmt = $db->prepare("
                    INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) 
                    VALUES (1, 'admin_whatsapp_numbers', ?, 'string', 'Admin WhatsApp numbers for unlimited voucher generation', 'admin')
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
                ");
                $stmt->execute([$numbersString, $numbersString]);
                
                $success = "✅ Berhasil menyimpan " . count($validNumbers) . " nomor admin!";
            }
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Nomor WhatsApp tidak boleh kosong!";
    }
}

// Get current admin numbers
try {
    $db = getDBConnection();
    $stmt = $db->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
    $result = $stmt->fetch();
    $currentNumbers = $result ? $result['setting_value'] : '';
} catch (Exception $e) {
    $currentNumbers = '';
}

if ($error) {
    echo "<div class='error'>$error</div>";
}

if ($success) {
    echo "<div class='success'>$success</div>";
}

echo "<div class='info'>";
echo "<h3>ℹ️ Informasi</h3>";
echo "<p>Nomor admin dapat generate voucher via WhatsApp tanpa pemotongan saldo.</p>";
echo "<p>Fitur ini berguna untuk admin yang perlu generate voucher darurat atau testing.</p>";
echo "</div>";

echo "<form method='POST'>";
echo "<div class='form-group'>";
echo "<label>Nomor WhatsApp Admin</label>";
echo "<textarea name='admin_numbers' class='form-control' rows='5' placeholder='628123456789,628987654321'>" . htmlspecialchars($currentNumbers) . "</textarea>";
echo "<div class='help-text'>";
echo "• Pisahkan dengan koma (,) untuk multiple nomor<br>";
echo "• Format: 628xxx (tanpa + atau spasi)<br>";
echo "• Contoh: 628123456789,628987654321<br>";
echo "• Nomor harus terdaftar di WhatsApp Gateway";
echo "</div>";
echo "</div>";

echo "<button type='submit' class='btn'>💾 Simpan Nomor Admin</button>";
echo "</form>";

if (!empty($currentNumbers)) {
    echo "<div style='margin-top: 30px;'>";
    echo "<h3>📋 Nomor Admin Terdaftar</h3>";
    $numbers = explode(',', $currentNumbers);
    echo "<ul>";
    foreach ($numbers as $number) {
        echo "<li><code>$number</code></li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<div style='margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px;'>";
echo "<h3>📱 Cara Menggunakan</h3>";
echo "<ol>";
echo "<li>Tambahkan nomor admin di form di atas</li>";
echo "<li>Klik Simpan</li>";
echo "<li>Kirim pesan WhatsApp ke bot dengan format:</li>";
echo "</ol>";

echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<strong>Perintah Admin:</strong><br><br>";
echo "<code>GEN 3JAM 10</code> - Generate 10 voucher profile 3JAM<br>";
echo "<code>SALDO</code> - Cek status admin<br>";
echo "<code>HARGA</code> - Lihat semua profile<br>";
echo "<code>HELP</code> - Bantuan<br>";
echo "</div>";

echo "<div class='info'>";
echo "<strong>⚠️ Penting:</strong><br>";
echo "• Admin tidak perlu terdaftar sebagai agent<br>";
echo "• Generate voucher tidak memotong saldo<br>";
echo "• Voucher langsung dikirim via WhatsApp<br>";
echo "• Pastikan nomor sudah terdaftar di WhatsApp Gateway";
echo "</div>";
echo "</div>";

echo "<div style='margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px;'>";
echo "<h3>🔗 Webhook URL</h3>";
echo "<p>Gunakan URL ini sebagai webhook di WhatsApp Gateway:</p>";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$webhookUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/whatsapp_agent_webhook.php';
echo "<p style='background: white; padding: 10px; border-radius: 5px; font-family: monospace; word-break: break-all;'>";
echo $webhookUrl;
echo "</p>";
echo "</div>";

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='./admin.php' style='display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>← Kembali ke Admin</a>";
echo "</div>";

echo "</body></html>";
?>
