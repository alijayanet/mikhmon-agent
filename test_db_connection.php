<?php
// Database Connection Test Script
echo "<h2>Database Connection Test</h2>";
echo "<p>Testing database connection for MikhMon Billing System...</p>";

// Include database configuration
@include_once('./include/db_config.php');

if (!function_exists('getDBConnection')) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Database configuration file not found or getDBConnection function not available.</p>";
    echo "<p>Please check if 'include/db_config.php' exists and is accessible.</p>";
    exit;
}

try {
    // Try to establish database connection
    $db = getDBConnection();
    
    if ($db) {
        echo "<p style='color: green;'><strong>SUCCESS:</strong> Database connection established!</p>";
        
        // Test a simple query
        try {
            $stmt = $db->query("SELECT 1 as test");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo "<p style='color: green;'><strong>SUCCESS:</strong> Database query executed successfully!</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: orange;'><strong>WARNING:</strong> Database connection OK but query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        // Check if required tables exist
        $requiredTables = ['billing_invoices', 'billing_customers', 'billing_profiles'];
        $existingTables = [];
        
        try {
            $stmt = $db->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $existingTables[] = $row[0];
            }
            
            echo "<h3>Database Tables Check:</h3>";
            foreach ($requiredTables as $table) {
                if (in_array($table, $existingTables)) {
                    echo "<p style='color: green;'><strong>FOUND:</strong> $table</p>";
                } else {
                    echo "<p style='color: red;'><strong>MISSING:</strong> $table</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: orange;'><strong>WARNING:</strong> Could not check tables: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Possible causes:</strong></p>";
    echo "<ul>";
    echo "<li>Incorrect database credentials in 'include/db_config.php'</li>";
    echo "<li>Database server is not accessible</li>";
    echo "<li>Required PHP extensions (PDO, MySQL) are not installed</li>";
    echo "<li>Database server is down or overloaded</li>";
    echo "</ul>";
}

// Check PHP configuration
echo "<h3>PHP Configuration Check:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PDO Available: " . (extension_loaded('pdo') ? "<span style='color: green;'>YES</span>" : "<span style='color: red;'>NO</span>") . "</p>";
echo "<p>PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? "<span style='color: green;'>YES</span>" : "<span style='color: red;'>NO</span>") . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . " seconds</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If database connection fails, check your 'include/db_config.php' file</li>";
echo "<li>If PDO extensions are missing, contact your hosting provider</li>";
echo "<li>If queries fail, check database permissions</li>";
echo "<li>If timeout occurs, increase max_execution_time in php.ini</li>";
echo "</ol>";
?>