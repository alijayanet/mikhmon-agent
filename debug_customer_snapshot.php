<?php
// Debug script for customer device snapshot

session_start();
$_SESSION['mikhmon'] = true;

// Include required files
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/genieacs/lib/GenieACS.class.php';
require_once __DIR__ . '/genieacs/lib/GenieACS_Fast.class.php';

echo "<h2>Debug Customer Device Snapshot</h2>\n";

try {
    $billingService = new BillingService();
    
    // Get a specific customer by ID (replace with an actual customer ID from your database)
    // For now, let's get the first customer with a PPPoE username
    $stmt = $billingService->getConnection()->query(
        "SELECT * FROM billing_customers WHERE genieacs_pppoe_username IS NOT NULL AND genieacs_pppoe_username != '' LIMIT 1"
    );
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo "<p>No customer with PPPoE username found in database.</p>\n";
        exit;
    }
    
    echo "<h3>Customer Info:</h3>\n";
    echo "<pre>";
    print_r([
        'id' => $customer['id'],
        'name' => $customer['name'],
        'service_number' => $customer['service_number'],
        'genieacs_pppoe_username' => $customer['genieacs_pppoe_username'],
    ]);
    echo "</pre>\n";
    
    // Test the getCustomerDeviceSnapshot method directly
    echo "<h3>Customer Device Snapshot:</h3>\n";
    $deviceSnapshot = $billingService->getCustomerDeviceSnapshot((int)$customer['id']);
    
    echo "<pre>";
    print_r($deviceSnapshot);
    echo "</pre>\n";
    
    // Check if any values are null or N/A
    if ($deviceSnapshot) {
        echo "<h3>Snapshot Analysis:</h3>\n";
        echo "<ul>\n";
        echo "<li>Status: " . htmlspecialchars(var_export($deviceSnapshot['status'], true)) . "</li>\n";
        echo "<li>RX Power: " . htmlspecialchars(var_export($deviceSnapshot['rx_power'], true)) . "</li>\n";
        echo "<li>Temperature: " . htmlspecialchars(var_export($deviceSnapshot['temperature'], true)) . "</li>\n";
        echo "<li>Connected Devices: " . htmlspecialchars(var_export($deviceSnapshot['connected_devices'], true)) . "</li>\n";
        echo "<li>PPPoE Username: " . htmlspecialchars(var_export($deviceSnapshot['pppoe_username'], true)) . "</li>\n";
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>