<?php
require_once 'include/db_config.php';

try {
    $pdo = getDBConnection();
    
    // Check if billing tables exist
    $tables = [
        'billing_profiles',
        'billing_customers', 
        'billing_invoices',
        'billing_payments',
        'billing_settings',
        'billing_logs'
    ];
    
    echo "<h2>Billing Module Tables Status</h2>\n";
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                echo "<p style='color: green;'>✓ Table `$table` exists</p>\n";
                
                // Show table structure
                $stmt = $pdo->prepare("DESCRIBE `$table`");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<ul>\n";
                foreach ($columns as $column) {
                    echo "<li>{$column['Field']} ({$column['Type']})";
                    if ($column['Key'] == 'PRI') echo " [PRIMARY KEY]";
                    if ($column['Key'] == 'MUL') echo " [INDEX]";
                    if ($column['Null'] == 'NO' && $column['Default'] === null && $column['Extra'] != 'auto_increment') echo " [NOT NULL]";
                    echo "</li>\n";
                }
                echo "</ul>\n";
            } else {
                echo "<p style='color: red;'>✗ Table `$table` does not exist</p>\n";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error checking table `$table`: " . $e->getMessage() . "</p>\n";
        }
    }
    
    // Check foreign key constraints
    echo "<h3>Foreign Key Constraints</h3>\n";
    try {
        $stmt = $pdo->query("SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME LIKE 'billing_%'
        ORDER BY TABLE_NAME");
        
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($fks)) {
            echo "<p>No foreign key constraints found</p>\n";
        } else {
            foreach ($fks as $fk) {
                echo "<p>{$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} ({$fk['CONSTRAINT_NAME']})</p>\n";
            }
        }
    } catch (Exception $e) {
        echo "<p>Error checking foreign keys: " . $e->getMessage() . "</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection error: " . $e->getMessage() . "</p>\n";
}
?>