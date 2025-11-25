<?php
/*
 * Check Admin WhatsApp Number Configuration
 */

// Load database config
require_once('./include/db_config.php');

echo "<h2>🔍 Check Admin WhatsApp Configuration</h2>";
echo "<hr>";

// Get DB connection
if (function_exists('getDBConnection')) {
    $pdo = getDBConnection();
} else {
    // Fallback: create connection manually
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
        echo "<p>Check your database configuration in <code>include/db_config.php</code></p>";
        echo "<p>Make sure these constants are defined: DB_HOST, DB_NAME, DB_USER, DB_PASS</p>";
        exit;
    }
}

try {
    
    echo "<h3>📋 Current Admin Numbers:</h3>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'agent_settings'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color:red;'>❌ Table 'agent_settings' tidak ditemukan!</p>";
        echo "<p>Tabel perlu dibuat terlebih dahulu.</p>";
        
        echo "<h3>🔧 Create Table:</h3>";
        echo "<pre>";
        echo "CREATE TABLE IF NOT EXISTS agent_settings (\n";
        echo "  id INT AUTO_INCREMENT PRIMARY KEY,\n";
        echo "  setting_key VARCHAR(100) UNIQUE,\n";
        echo "  setting_value TEXT,\n";
        echo "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n";
        echo ");\n";
        echo "</pre>";
        
        echo "<form method='POST'>";
        echo "<button type='submit' name='create_table' style='padding:10px 20px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;'>Create Table</button>";
        echo "</form>";
        
        if (isset($_POST['create_table'])) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS agent_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p style='color:green;'>✅ Table created successfully!</p>";
            echo "<script>location.reload();</script>";
        }
    } else {
        // Get admin numbers
        $stmt = $pdo->query("SELECT * FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "<pre>";
            echo "Setting Key: " . $result['setting_key'] . "\n";
            echo "Setting Value: " . $result['setting_value'] . "\n";
            echo "Created At: " . $result['created_at'] . "\n";
            echo "</pre>";
            
            $adminNumbers = explode(',', $result['setting_value']);
            $adminNumbers = array_map('trim', $adminNumbers);
            
            echo "<h4>Admin Numbers List:</h4>";
            echo "<ul>";
            foreach ($adminNumbers as $num) {
                echo "<li><strong>" . htmlspecialchars($num) . "</strong></li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:orange;'>⚠️ Admin numbers belum dikonfigurasi!</p>";
        }
        
        echo "<hr>";
        echo "<h3>➕ Add/Update Admin Number:</h3>";
        echo "<form method='POST'>";
        echo "<label>Admin WhatsApp Numbers (pisahkan dengan koma):</label><br>";
        echo "<input type='text' name='admin_numbers' value='" . ($result ? htmlspecialchars($result['setting_value']) : '') . "' style='width:500px;padding:8px;' placeholder='6281947215703, 628123456789'><br><br>";
        echo "<button type='submit' name='save_admin' style='padding:10px 20px;background:#007bff;color:white;border:none;border-radius:4px;cursor:pointer;'>Save Admin Numbers</button>";
        echo "</form>";
        
        if (isset($_POST['save_admin'])) {
            $adminNumbers = $_POST['admin_numbers'];
            
            if ($result) {
                // Update
                $stmt = $pdo->prepare("UPDATE agent_settings SET setting_value = ? WHERE setting_key = 'admin_whatsapp_numbers'");
                $stmt->execute([$adminNumbers]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO agent_settings (agent_id, setting_key, setting_value) VALUES (1, 'admin_whatsapp_numbers', ?)");
                $stmt->execute([$adminNumbers]);
            }
            
            echo "<p style='color:green;'>✅ Admin numbers saved successfully!</p>";
            echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
        }
        
        echo "<hr>";
        echo "<h3>🧪 Test Admin Check:</h3>";
        echo "<form method='POST'>";
        echo "<label>Test Number:</label><br>";
        echo "<input type='text' name='test_number' value='6281947215703' style='padding:8px;'><br><br>";
        echo "<button type='submit' name='test_admin' style='padding:10px 20px;background:#17a2b8;color:white;border:none;border-radius:4px;cursor:pointer;'>Test</button>";
        echo "</form>";
        
        if (isset($_POST['test_admin'])) {
            $testNumber = $_POST['test_number'];
            
            $stmt = $pdo->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $adminNumbers = explode(',', $result['setting_value']);
                $adminNumbers = array_map('trim', $adminNumbers);
                $isAdmin = in_array($testNumber, $adminNumbers);
                
                echo "<pre>";
                echo "Test Number: " . $testNumber . "\n";
                echo "Is Admin: " . ($isAdmin ? "YES ✅" : "NO ❌") . "\n";
                echo "Admin List: " . implode(', ', $adminNumbers) . "\n";
                echo "</pre>";
            } else {
                echo "<p style='color:red;'>❌ No admin numbers configured!</p>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database Error: " . $e->getMessage() . "</p>";
    echo "<p>Pastikan database sudah dikonfigurasi dengan benar di <code>include/db_config.php</code></p>";
}
?>
