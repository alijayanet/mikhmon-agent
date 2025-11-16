<?php
require_once 'include/db_config.php';

try {
    $pdo = getDBConnection();
    
    // Periksa kolom dalam tabel billing_customers
    $stmt = $pdo->query('DESCRIBE billing_customers');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Struktur tabel billing_customers:\n";
    foreach ($columns as $column) {
        echo $column['Field'] . ' - ' . $column['Type'] . "\n";
    }
    
    // Periksa apakah kolom genieacs_pppoe_username ada
    $hasPppoeColumn = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'genieacs_pppoe_username') {
            $hasPppoeColumn = true;
            break;
        }
    }
    
    echo "\n";
    if ($hasPppoeColumn) {
        echo "Kolom genieacs_pppoe_username sudah ada.\n";
    } else {
        echo "Kolom genieacs_pppoe_username tidak ditemukan.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}