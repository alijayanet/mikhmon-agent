<?php
/**
 * Cron Path Checker
 * 
 * File ini digunakan untuk mengetahui path absolut yang benar
 * untuk setup cronjob billing automation.
 * 
 * Cara pakai:
 * 1. Upload file ini ke root folder aplikasi
 * 2. Akses via browser: https://billing.alijaya.net/check_cron_path.php
 * 3. Copy command cronjob yang ditampilkan
 * 4. Hapus file ini setelah selesai (untuk keamanan)
 */

// Styling
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Path Checker - MikhMon Billing</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .info-value {
            background: white;
            padding: 12px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #333;
            border: 1px solid #ddd;
            word-break: break-all;
        }
        
        .command-box {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .command-box h3 {
            color: #4ec9b0;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .command {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #ce9178;
            margin-bottom: 15px;
            padding: 10px;
            background: #252526;
            border-radius: 5px;
            position: relative;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 5px;
            transition: background 0.3s;
        }
        
        .copy-btn:hover {
            background: #5568d3;
        }
        
        .copy-btn:active {
            background: #4451b8;
        }
        
        .success {
            color: #4caf50;
            font-weight: 600;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: #856404;
        }
        
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: #721c24;
        }
        
        .check-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .check-item.ok {
            background: #d4edda;
            color: #155724;
        }
        
        .check-item.fail {
            background: #f8d7da;
            color: #721c24;
        }
        
        .icon {
            font-size: 18px;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .delete-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .delete-warning strong {
            color: #856404;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🕐 Cron Path Checker</h1>
        <p class="subtitle">MikhMon Billing Automation - Path Detection Tool</p>
        
        <?php
        // Deteksi path
        $currentDir = __DIR__;
        $billingCronPath = $currentDir . '/process/billing_cron.php';
        $billingCronExists = file_exists($billingCronPath);
        
        // Deteksi PHP binary
        $phpBinary = PHP_BINARY;
        if (empty($phpBinary) || !file_exists($phpBinary)) {
            $phpBinary = '/usr/bin/php'; // Default
        }
        
        // Deteksi server info
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown';
        $phpVersion = phpversion();
        $osInfo = php_uname();
        
        // Deteksi user
        $currentUser = get_current_user();
        $processUser = posix_getpwuid(posix_geteuid())['name'] ?? 'Unknown';
        
        // Cek writable
        $logDir = $currentDir . '/logs';
        $logDirExists = is_dir($logDir);
        $logDirWritable = $logDirExists && is_writable($logDir);
        
        // Alternatif log path
        $homeDir = getenv('HOME') ?: '/home/' . $currentUser;
        $altLogPath = $homeDir . '/logs/billing_cron.log';
        ?>
        
        <!-- System Information -->
        <div class="info-box">
            <h3>📊 Informasi Sistem</h3>
            <div class="info-item">
                <span class="info-label">Server Software:</span>
                <div class="info-value"><?php echo htmlspecialchars($serverSoftware); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">PHP Version:</span>
                <div class="info-value"><?php echo htmlspecialchars($phpVersion); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Operating System:</span>
                <div class="info-value"><?php echo htmlspecialchars($osInfo); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Current User:</span>
                <div class="info-value"><?php echo htmlspecialchars($currentUser); ?> (Process: <?php echo htmlspecialchars($processUser); ?>)</div>
            </div>
        </div>
        
        <!-- Path Information -->
        <div class="info-box">
            <h3>📁 Path Information</h3>
            <div class="info-item">
                <span class="info-label">Application Root Directory:</span>
                <div class="info-value"><?php echo htmlspecialchars($currentDir); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Document Root:</span>
                <div class="info-value"><?php echo htmlspecialchars($documentRoot); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">Billing Cron Script Path:</span>
                <div class="info-value"><?php echo htmlspecialchars($billingCronPath); ?></div>
            </div>
            <div class="info-item">
                <span class="info-label">PHP Binary Path:</span>
                <div class="info-value"><?php echo htmlspecialchars($phpBinary); ?></div>
            </div>
        </div>
        
        <!-- File Checks -->
        <div class="info-box">
            <h3>✅ File Checks</h3>
            <div class="check-item <?php echo $billingCronExists ? 'ok' : 'fail'; ?>">
                <span class="icon"><?php echo $billingCronExists ? '✓' : '✗'; ?></span>
                <span>billing_cron.php <?php echo $billingCronExists ? 'FOUND' : 'NOT FOUND'; ?></span>
            </div>
            <div class="check-item <?php echo $logDirExists ? 'ok' : 'fail'; ?>">
                <span class="icon"><?php echo $logDirExists ? '✓' : '✗'; ?></span>
                <span>logs/ directory <?php echo $logDirExists ? 'EXISTS' : 'NOT FOUND'; ?></span>
            </div>
            <div class="check-item <?php echo $logDirWritable ? 'ok' : 'fail'; ?>">
                <span class="icon"><?php echo $logDirWritable ? '✓' : '✗'; ?></span>
                <span>logs/ directory <?php echo $logDirWritable ? 'WRITABLE' : 'NOT WRITABLE'; ?></span>
            </div>
        </div>
        
        <?php if (!$billingCronExists): ?>
        <div class="error">
            <strong>⚠️ Error:</strong> File billing_cron.php tidak ditemukan di path: <code><?php echo htmlspecialchars($billingCronPath); ?></code>
            <br><br>
            Pastikan file tersebut ada sebelum setup cronjob.
        </div>
        <?php endif; ?>
        
        <?php if (!$logDirWritable && $logDirExists): ?>
        <div class="warning">
            <strong>⚠️ Warning:</strong> Folder logs/ tidak writable. Cronjob mungkin tidak bisa menulis log.
            <br><br>
            Jalankan command: <code>chmod 755 <?php echo htmlspecialchars($logDir); ?></code>
        </div>
        <?php endif; ?>
        
        <!-- Cronjob Commands -->
        <div class="command-box">
            <h3>🚀 Recommended Cronjob Commands</h3>
            
            <p style="color: #9cdcfe; margin-bottom: 15px;">1. Daily at 00:30 (Recommended for Production)</p>
            <div class="command" id="cmd1">30 0 * * * <?php echo htmlspecialchars($phpBinary); ?> <?php echo htmlspecialchars($billingCronPath); ?> >> <?php echo htmlspecialchars($logDirExists ? $logDir . '/billing_cron.log' : $altLogPath); ?> 2>&1</div>
            <button class="copy-btn" onclick="copyCommand('cmd1')">📋 Copy Command</button>
            
            <p style="color: #9cdcfe; margin-bottom: 15px; margin-top: 20px;">2. Every 6 Hours (For Testing)</p>
            <div class="command" id="cmd2">0 */6 * * * <?php echo htmlspecialchars($phpBinary); ?> <?php echo htmlspecialchars($billingCronPath); ?> >> <?php echo htmlspecialchars($logDirExists ? $logDir . '/billing_cron.log' : $altLogPath); ?> 2>&1</div>
            <button class="copy-btn" onclick="copyCommand('cmd2')">📋 Copy Command</button>
            
            <p style="color: #9cdcfe; margin-bottom: 15px; margin-top: 20px;">3. Every Hour (For Development/Testing)</p>
            <div class="command" id="cmd3">0 * * * * <?php echo htmlspecialchars($phpBinary); ?> <?php echo htmlspecialchars($billingCronPath); ?> >> <?php echo htmlspecialchars($logDirExists ? $logDir . '/billing_cron.log' : $altLogPath); ?> 2>&1</div>
            <button class="copy-btn" onclick="copyCommand('cmd3')">📋 Copy Command</button>
            
            <p style="color: #9cdcfe; margin-bottom: 15px; margin-top: 20px;">4. Using wget (If PHP CLI not available)</p>
            <div class="command" id="cmd4">30 0 * * * wget -q -O /dev/null https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/process/billing_cron.php</div>
            <button class="copy-btn" onclick="copyCommand('cmd4')">📋 Copy Command</button>
        </div>
        
        <!-- Manual Test Command -->
        <div class="command-box">
            <h3>🧪 Manual Test Command (via SSH)</h3>
            <p style="color: #9cdcfe; margin-bottom: 15px;">Run this command to test the cron script manually:</p>
            <div class="command" id="cmd5"><?php echo htmlspecialchars($phpBinary); ?> <?php echo htmlspecialchars($billingCronPath); ?></div>
            <button class="copy-btn" onclick="copyCommand('cmd5')">📋 Copy Command</button>
        </div>
        
        <!-- Setup Instructions -->
        <div class="info-box">
            <h3>📝 Setup Instructions</h3>
            <ol style="margin-left: 20px; line-height: 1.8;">
                <li>Login ke <strong>cPanel</strong> hosting Anda</li>
                <li>Cari menu <strong>Cron Jobs</strong></li>
                <li>Klik <strong>Add New Cron Job</strong></li>
                <li>Copy salah satu command di atas (yang recommended: #1)</li>
                <li>Paste ke field <strong>Command</strong></li>
                <li>Klik <strong>Add New Cron Job</strong></li>
                <li>Tunggu cronjob berjalan sesuai jadwal</li>
                <li>Monitor log di: <code><?php echo htmlspecialchars($logDirExists ? $logDir . '/billing_cron.log' : $altLogPath); ?></code></li>
            </ol>
        </div>
        
        <div class="delete-warning">
            <strong>⚠️ PENTING: Hapus file ini setelah selesai!</strong><br>
            File ini mengandung informasi sensitif tentang server Anda.<br>
            Hapus <code>check_cron_path.php</code> setelah Anda copy command cronjob.
        </div>
        
        <div class="footer">
            <p>MikhMon Billing Automation System</p>
            <p>Generated at: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <script>
        function copyCommand(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            // Copy to clipboard
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                const btn = element.nextElementSibling;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#4caf50';
                
                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.style.background = '#667eea';
                }, 2000);
            }).catch(function(err) {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>
