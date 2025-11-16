<?php
// Debug script for GenieACS integration

session_start();
$_SESSION['mikhmon'] = true;

// Include required files
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/genieacs/lib/GenieACS.class.php';
require_once __DIR__ . '/genieacs/lib/GenieACS_Fast.class.php';

echo "<h2>Debug GenieACS Integration</h2>\n";

try {
    $billingService = new BillingService();
    
    // Get a customer to test with (using the first active customer)
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
    
    // Test device snapshot
    echo "<h3>Device Snapshot:</h3>\n";
    $deviceSnapshot = $billingService->getCustomerDeviceSnapshot((int)$customer['id']);
    
    echo "<pre>";
    print_r($deviceSnapshot);
    echo "</pre>\n";
    
    // Test GenieACS client directly
    echo "<h3>GenieACS Client Test:</h3>\n";
    try {
        // We can't directly call private methods, but we can test the GenieACS class directly
        $genie = new GenieACS();
        echo "<p>GenieACS enabled: " . ($genie->isEnabled() ? 'Yes' : 'No') . "</p>\n";
        
        if ($genie->isEnabled()) {
            // Try to get device data using the device ID from snapshot
            $deviceId = $deviceSnapshot['device_id'] ?? null;
            if ($deviceId && $deviceId !== 'N/A') {
                echo "<h4>Getting Device Data for ID: " . htmlspecialchars($deviceId) . "</h4>\n";
                $deviceData = $genie->getDevice($deviceId);
                echo "<pre>";
                print_r($deviceData);
                echo "</pre>\n";
                
                // If we have device data, test parsing
                if (!empty($deviceData['data'])) {
                    echo "<h4>Parsed Device Data (Fast Parser):</h4>\n";
                    $parsedData = GenieACS_Fast::parseDeviceDataFast($deviceData['data']);
                    echo "<pre>";
                    print_r($parsedData);
                    echo "</pre>\n";
                }
            } else {
                echo "<p>Could not determine device ID from snapshot.</p>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "<p>Error with GenieACS client: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>