<?php
require_once 'include/db_config.php';

try {
    $pdo = getDBConnection();
    
    // Periksa jumlah data dalam tabel billing_customers
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM billing_customers');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Jumlah data dalam tabel billing_customers: " . $result['count'] . "\n";
    
    // Periksa beberapa data sample
    if ($result['count'] > 0) {
        $stmt = $pdo->query('SELECT id, name, genieacs_pppoe_username FROM billing_customers LIMIT 5');
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nSample data:\n";
        foreach ($customers as $customer) {
            echo "ID: " . $customer['id'] . ", Nama: " . $customer['name'] . ", PPPoE: " . ($customer['genieacs_pppoe_username'] ?: 'NULL') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}