<?php
/*
 * View PHP Error Log
 */

echo "<h2>📝 PHP Error Log</h2>";
echo "<hr>";

// Possible error log locations
$logPaths = [
    'C:\xampp3\apache\logs\error.log',
    'C:\xampp\apache\logs\error.log',
    'C:\xampp3\php\logs\php_error_log.txt',
    './logs/error.log',
    ini_get('error_log')
];

$foundLog = false;

foreach ($logPaths as $logPath) {
    if (file_exists($logPath)) {
        $foundLog = true;
        echo "<h3>✅ Log File: <code>$logPath</code></h3>";
        
        $lines = file($logPath);
        $lines = array_reverse($lines);
        $lines = array_slice($lines, 0, 100); // Last 100 lines
        
        // Filter for WhatsApp related logs
        $whatsappLines = array_filter($lines, function($line) {
            return stripos($line, 'whatsapp') !== false || 
                   stripos($line, 'webhook') !== false ||
                   stripos($line, 'mpwa') !== false ||
                   stripos($line, 'agent') !== false;
        });
        
        if (!empty($whatsappLines)) {
            echo "<h4>🔍 WhatsApp/Webhook Related Logs (Last 100 lines):</h4>";
            echo "<pre style='background:#f5f5f5;padding:15px;border-radius:4px;max-height:400px;overflow-y:auto;font-size:12px;'>";
            foreach ($whatsappLines as $line) {
                // Highlight important keywords
                $line = str_replace('WhatsApp', '<strong style="color:#007bff;">WhatsApp</strong>', $line);
                $line = str_replace('Webhook', '<strong style="color:#28a745;">Webhook</strong>', $line);
                $line = str_replace('HELP', '<strong style="color:#dc3545;">HELP</strong>', $line);
                $line = str_replace('IsAdmin', '<strong style="color:#ffc107;">IsAdmin</strong>', $line);
                echo $line;
            }
            echo "</pre>";
        }
        
        echo "<hr>";
        echo "<h4>📄 All Recent Logs (Last 100 lines):</h4>";
        echo "<pre style='background:#f5f5f5;padding:15px;border-radius:4px;max-height:400px;overflow-y:auto;font-size:11px;'>";
        foreach ($lines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>";
        
        echo "<hr>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='clear_log' value='$logPath' style='padding:10px 20px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;'>Clear This Log</button>";
        echo "</form>";
        
        if (isset($_POST['clear_log']) && $_POST['clear_log'] == $logPath) {
            file_put_contents($logPath, '');
            echo "<p style='color:green;'>✅ Log cleared!</p>";
            echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
        }
        
        break; // Show only first found log
    }
}

if (!$foundLog) {
    echo "<p style='color:orange;'>⚠️ No error log file found in common locations.</p>";
    echo "<p>Checked paths:</p>";
    echo "<ul>";
    foreach ($logPaths as $path) {
        echo "<li><code>" . htmlspecialchars($path) . "</code></li>";
    }
    echo "</ul>";
    echo "<p>Current error_log setting: <code>" . ini_get('error_log') . "</code></p>";
}

echo "<hr>";
echo "<h3>🧪 Test Webhook Now:</h3>";
echo "<form method='POST'>";
echo "<button type='submit' name='test_webhook' style='padding:10px 20px;background:#007bff;color:white;border:none;border-radius:4px;cursor:pointer;'>Send Test HELP Command</button>";
echo "</form>";

if (isset($_POST['test_webhook'])) {
    $webhookData = [
        'sender' => '6281947215703',
        'message' => 'HELP',
        'device' => '6287820851413'
    ];
    
    $url = 'http://localhost/mikhmon-agent/api/whatsapp_agent_webhook.php';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<div style='background:#d4edda;padding:15px;border-radius:4px;margin-top:10px;'>";
    echo "<strong>✅ Test webhook sent!</strong><br>";
    echo "HTTP Code: $httpCode<br>";
    echo "Response: " . ($response ? htmlspecialchars($response) : '(empty)');
    echo "</div>";
    echo "<p><strong>Refresh halaman ini untuk melihat log terbaru!</strong></p>";
}
?>
