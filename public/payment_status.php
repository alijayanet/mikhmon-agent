<?php
/*
 * Payment Status Page
 * Show payment status and voucher (if paid)
 */

$transaction_id = $_GET['trx'] ?? '';

if (empty($transaction_id)) {
    header('Location: index.php');
    exit;
}

include_once('../include/db_config.php');

// Get theme from MikhMon config
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT ps.*, a.agent_name, a.agent_code, a.phone as agent_phone
                           FROM public_sales ps
                           JOIN agents a ON ps.agent_id = a.id
                           WHERE ps.transaction_id = :trx_id");
    $stmt->execute([':trx_id' => $transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

$status_labels = [
    'pending' => ['label' => 'Menunggu Pembayaran', 'color' => 'warning', 'icon' => 'fa-clock-o'],
    'paid' => ['label' => 'Pembayaran Berhasil', 'color' => 'success', 'icon' => 'fa-check-circle'],
    'expired' => ['label' => 'Kadaluarsa', 'color' => 'danger', 'icon' => 'fa-times-circle'],
    'failed' => ['label' => 'Pembayaran Gagal', 'color' => 'danger', 'icon' => 'fa-exclamation-triangle']
];

$status_info = $status_labels[$transaction['status']] ?? ['label' => 'Unknown', 'color' => 'secondary', 'icon' => 'fa-question'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran</title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    
    <style>
        :root {
            --primary-color: <?= $themecolor; ?>;
            --primary-dark: <?= $themecolor; ?>dd;
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --text-muted: #7f8c8d;
            --border-color: #e1e8ed;
            --border-light: #f1f3f4;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --voucher-bg: #f8f9fa;
            --success-bg: #d5f4e6;
            --success-text: #0d7377;
            --info-bg: #e3f2fd;
            --info-text: #1565c0;
        }

        body {
            background: var(--bg-color);
            min-height: 100vh;
            padding: 20px 10px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .status-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .status-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
        }
        
        .status-card h2 {
            color: var(--text-color);
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .status-card h4, .status-card h5 {
            color: var(--text-color);
            font-weight: 600;
        }
        
        .status-icon {
            font-size: 4.5rem;
            margin-bottom: 25px;
        }
        
        .status-icon.success { color: var(--success-color); }
        .status-icon.warning { color: var(--warning-color); }
        .status-icon.danger { color: var(--danger-color); }
        
        .voucher-box {
            background: var(--voucher-bg, #f8f9fa);
            border: 2px dashed var(--primary-color, #3c8dbc);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .voucher-code {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            font-family: 'Courier New', 'Monaco', monospace;
            letter-spacing: 3px;
            margin: 15px 0;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.8);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .voucher-password {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-color);
            font-family: 'Courier New', 'Monaco', monospace;
            letter-spacing: 2px;
            margin: 15px 0;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.8);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .copy-btn {
            background: var(--primary-color, #3c8dbc);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background: var(--primary-dark, #2c6aa0);
            transform: translateY(-1px);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-color);
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid var(--border-color);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-color);
            font-size: 1rem;
        }
        
        .info-value {
            font-weight: 600;
            text-align: right;
            color: var(--text-color);
            font-size: 1rem;
        }
        
        .btn-action {
            background: var(--primary-color, #3c8dbc);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            background: var(--primary-dark, #2c6aa0);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 141, 188, 0.3);
        }
        
        .btn-secondary {
            background: var(--secondary-color, #6c757d);
        }
        
        .btn-secondary:hover {
            background: var(--secondary-dark, #545b62);
        }
        
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        
        .qr-code img {
            max-width: 200px;
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px 5px;
            }
            
            .status-card {
                padding: 20px 15px;
                margin-bottom: 15px;
            }
            
            .voucher-code {
                font-size: 1.4rem;
                letter-spacing: 1px;
            }
            
            .voucher-password {
                font-size: 1.2rem;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .info-value {
                text-align: left;
            }
            
            .btn-action {
                width: 100%;
                margin: 5px 0;
                text-align: center;
            }
            
            .copy-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
        
        .text-muted {
            color: var(--text-muted) !important;
            font-weight: 500;
        }
        
        p.text-muted {
            color: var(--text-muted) !important;
            font-size: 1rem;
        }
        
        small {
            color: var(--text-color) !important;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Voucher labels */
        .voucher-box small {
            color: var(--text-color) !important;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Usage instructions */
        .voucher-box ol {
            color: var(--info-text) !important;
            font-weight: 500;
        }
        
        .voucher-box ol li {
            margin-bottom: 5px;
        }
        
        /* Dark theme support */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #1a1a1a;
                --card-bg: #2d2d2d;
                --text-color: #ffffff;
                --text-muted: #cccccc;
                --border-color: #404040;
                --border-light: #353535;
                --voucher-bg: #404040;
            }
        }
    </style>
</head>
<body>
    
    <div class="status-container">
        
        <div class="status-card text-center">
            <div class="status-icon <?= $status_info['color']; ?>">
                <i class="fa <?= $status_info['icon']; ?>"></i>
            </div>
            
            <h2 style="color: var(--text-color); font-weight: 700;"><?= $status_info['label']; ?></h2>
            
            <p style="color: var(--text-muted); font-size: 1.1rem; font-weight: 500;">
                Transaksi ID: <strong style="color: var(--text-color);"><?= htmlspecialchars($transaction['transaction_id']); ?></strong>
            </p>
        </div>
        
        <?php if ($transaction['status'] == 'paid' && !empty($transaction['voucher_code'])): ?>
        <!-- Voucher Info -->
        <div class="status-card">
            <h4 class="text-center mb-4" style="color: var(--primary-color, #3c8dbc);">
                <i class="fa fa-ticket"></i> Voucher WiFi Anda
            </h4>
            
            <div class="voucher-box">
                <!-- QR Code -->
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($transaction['voucher_code'] . '|' . $transaction['voucher_password']); ?>" 
                         alt="QR Code Voucher">
                    <p style="font-size: 1rem; color: var(--text-color); margin-top: 10px; font-weight: 600;">
                        <i class="fa fa-qrcode"></i> Scan untuk auto-login
                    </p>
                </div>
                
                <hr style="border-color: var(--border-color, #e0e0e0); margin: 20px 0;">
                
                <div class="mb-3">
                    <div style="font-size: 1rem; color: var(--text-color); margin-bottom: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                        <i class="fa fa-user"></i> Username
                    </div>
                    <div class="voucher-code">
                        <?= htmlspecialchars($transaction['voucher_code']); ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($transaction['voucher_code']); ?>', 'Username')" title="Copy Username">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div style="font-size: 1rem; color: var(--text-color); margin-bottom: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                        <i class="fa fa-lock"></i> Password
                    </div>
                    <div class="voucher-password">
                        <?= htmlspecialchars($transaction['voucher_password']); ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($transaction['voucher_password']); ?>', 'Password')" title="Copy Password">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div style="background: var(--info-bg, #e3f2fd); padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <div style="font-size: 0.9rem; color: var(--info-text, #0277bd);">
                        <i class="fa fa-info-circle"></i> <strong>Cara Menggunakan:</strong>
                    </div>
                    <ol style="font-size: 0.85rem; color: var(--info-text, #0277bd); margin: 10px 0 0 20px; padding: 0;">
                        <li>Hubungkan ke WiFi <strong><?= htmlspecialchars($transaction['agent_name']); ?></strong></li>
                        <li>Buka browser, masukkan username & password di atas</li>
                        <li>Atau scan QR Code untuk auto-login</li>
                    </ol>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="text-center" style="margin-top: 25px;">
                <button class="btn-action" onclick="printVoucher()">
                    <i class="fa fa-print"></i> Print A4
                </button>
                <button class="btn-action btn-secondary" onclick="printThermal()">
                    <i class="fa fa-print"></i> Print Thermal
                </button>
            </div>
            
            <?php if (!empty($transaction['customer_phone'])): ?>
            <div style="background: var(--success-bg, #d4edda); color: var(--success-text, #155724); padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center;">
                <i class="fa fa-whatsapp" style="color: #25d366; font-size: 1.2rem;"></i>
                <strong>Voucher sudah dikirim ke WhatsApp Anda</strong>
                <br>
                <small><?= htmlspecialchars($transaction['customer_phone']); ?></small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Transaction Details -->
        <div class="status-card">
            <h5 style="color: var(--primary-color, #3c8dbc); margin-bottom: 20px;">
                <i class="fa fa-receipt"></i> Detail Transaksi
            </h5>
            
            <div class="info-row">
                <span class="info-label">Agent</span>
                <span class="info-value"><?= htmlspecialchars($transaction['agent_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Paket</span>
                <span class="info-value"><strong><?= htmlspecialchars($transaction['profile_name']); ?></strong></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Customer</span>
                <span class="info-value"><?= htmlspecialchars($transaction['customer_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">WhatsApp</span>
                <span class="info-value"><?= htmlspecialchars($transaction['customer_phone']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Metode Bayar</span>
                <span class="info-value"><?= htmlspecialchars($transaction['payment_method'] ?? 'N/A'); ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Harga Paket</span>
                <span class="info-value">Rp <?= number_format($transaction['price'], 0, ',', '.'); ?></span>
            </div>
            
            <?php if ($transaction['admin_fee'] > 0): ?>
            <div class="info-row">
                <span class="info-label">Biaya Admin</span>
                <span class="info-value">Rp <?= number_format($transaction['admin_fee'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span>Total</span>
                <strong>Rp <?= number_format($transaction['total_amount'], 0, ',', '.'); ?></strong>
            </div>
            
            <div class="info-row">
                <span>Metode Pembayaran</span>
                <span><?= htmlspecialchars($transaction['payment_method'] ?? '-'); ?></span>
            </div>
            
            <div class="info-row">
                <span>Tanggal</span>
                <span><?= date('d M Y H:i', strtotime($transaction['created_at'])); ?></span>
            </div>
            
            <?php if ($transaction['status'] == 'paid' && $transaction['paid_at']): ?>
            <div class="info-row">
                <span>Dibayar</span>
                <span><?= date('d M Y H:i', strtotime($transaction['paid_at'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($transaction['status'] == 'pending'): ?>
        <!-- Pending Payment Info -->
        <div class="status-card">
            <h5 class="mb-3">Menunggu Pembayaran</h5>
            
            <?php if ($transaction['expired_at']): ?>
            <p>Silakan selesaikan pembayaran sebelum:</p>
            <p class="text-center">
                <strong><?= date('d M Y H:i', strtotime($transaction['expired_at'])); ?></strong>
            </p>
            <?php endif; ?>
            
            <?php if ($transaction['payment_url']): ?>
            <a href="<?= htmlspecialchars($transaction['payment_url']); ?>" class="btn btn-primary btn-block">
                <i class="fa fa-credit-card"></i> Bayar Sekarang
            </a>
            <?php endif; ?>
            
            <button class="btn btn-secondary btn-block mt-2" onclick="location.reload()">
                <i class="fa fa-refresh"></i> Refresh Status
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Support -->
        <div class="text-center mt-3">
            <?php if (!empty($transaction['agent_phone'])): ?>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $transaction['agent_phone']); ?>" 
               class="btn btn-success" target="_blank">
                <i class="fa fa-whatsapp"></i> Hubungi Kami
            </a>
            <?php endif; ?>
            
            <a href="index.php?agent=<?= urlencode($transaction['agent_code']); ?>" class="btn btn-outline-light">
                <i class="fa fa-home"></i> Kembali ke Beranda
            </a>
        </div>
        
    </div>
    
    <script>
    function copyToClipboard(text, label) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                alert(label + ' berhasil disalin!');
            });
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert(label + ' berhasil disalin!');
        }
    }
    
    // Print Voucher A4
    function printVoucher() {
        const username = '<?= htmlspecialchars($transaction['voucher_code']); ?>';
        const password = '<?= htmlspecialchars($transaction['voucher_password']); ?>';
        const profile = '<?= htmlspecialchars($transaction['profile_name']); ?>';
        const qrData = username + '|' + password;
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qrData);
        
        const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Voucher - ${username}</title>
            <style>
                @page { size: A4; margin: 20mm; }
                body { font-family: Arial, sans-serif; text-align: center; }
                .voucher { border: 2px solid #333; padding: 30px; margin: 20px auto; max-width: 400px; }
                .title { font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                .qr { margin: 20px 0; }
                .info { margin: 15px 0; font-size: 18px; }
                .label { color: #666; font-size: 14px; }
                .value { font-weight: bold; font-size: 20px; font-family: monospace; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="voucher">
                <div class="title">VOUCHER WiFi</div>
                <div class="info">
                    <div class="label">Paket</div>
                    <div class="value">${profile}</div>
                </div>
                <div class="qr">
                    <img src="${qrUrl}" alt="QR Code">
                </div>
                <div class="info">
                    <div class="label">Username</div>
                    <div class="value">${username}</div>
                </div>
                <div class="info">
                    <div class="label">Password</div>
                    <div class="value">${password}</div>
                </div>
                <div class="footer">
                    Scan QR Code atau masukkan username & password untuk login
                </div>
            </div>
        </body>
        </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.print();
        };
    }
    
    // Print Voucher Thermal (58mm)
    function printThermal() {
        const username = '<?= htmlspecialchars($transaction['voucher_code']); ?>';
        const password = '<?= htmlspecialchars($transaction['voucher_password']); ?>';
        const profile = '<?= htmlspecialchars($transaction['profile_name']); ?>';
        const qrData = username + '|' + password;
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(qrData);
        
        const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Voucher Thermal - ${username}</title>
            <style>
                @page { size: 58mm auto; margin: 2mm; }
                body { font-family: Arial, sans-serif; text-align: center; width: 58mm; margin: 0; padding: 5mm; }
                .title { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
                .qr { margin: 10px 0; }
                .qr img { width: 40mm; height: 40mm; }
                .info { margin: 8px 0; font-size: 12px; }
                .label { color: #666; font-size: 10px; }
                .value { font-weight: bold; font-size: 14px; font-family: monospace; word-wrap: break-word; }
                .footer { margin-top: 10px; font-size: 9px; color: #666; }
                .divider { border-top: 1px dashed #333; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="title">VOUCHER WiFi</div>
            <div class="info">
                <div class="label">Paket</div>
                <div class="value">${profile}</div>
            </div>
            <div class="divider"></div>
            <div class="qr">
                <img src="${qrUrl}" alt="QR Code">
            </div>
            <div class="divider"></div>
            <div class="info">
                <div class="label">Username</div>
                <div class="value">${username}</div>
            </div>
            <div class="info">
                <div class="label">Password</div>
                <div class="value">${password}</div>
            </div>
            <div class="divider"></div>
            <div class="footer">
                Terima kasih
            </div>
        </body>
        </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.onload = function() {
            printWindow.print();
        };
    }
    
    // Auto refresh for pending status
    <?php if ($transaction['status'] == 'pending'): ?>
    setTimeout(function() {
        location.reload();
    }, 30000); // Refresh every 30 seconds
    <?php endif; ?>
    </script>
    
</body>
</html>
