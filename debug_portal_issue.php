<?php
// Debug script to identify portal issue

session_start();
$_SESSION['mikhmon'] = true;
$_SESSION['billing_portal_customer_id'] = 1; // Use the same customer ID as in our debug

// Include required files
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/genieacs/lib/GenieACS.class.php';
require_once __DIR__ . '/genieacs/lib/GenieACS_Fast.class.php';

$activeCustomerId = (int)$_SESSION['billing_portal_customer_id'];

try {
    $billingService = new BillingService();
    $customer = $billingService->getCustomerById($activeCustomerId);
    
    if (!$customer) {
        echo "<p>Customer not found</p>\n";
        exit;
    }
    
    echo "<h3>Customer Data:</h3>\n";
    echo "<pre>";
    print_r($customer);
    echo "</pre>\n";
    
    // Test GenieACS connectivity directly
    echo "<h3>GenieACS Connectivity Test:</h3>\n";
    $genie = new GenieACS();
    echo "<p>GenieACS enabled: " . ($genie->isEnabled() ? 'Yes' : 'No') . "</p>\n";
    
    if ($genie->isEnabled()) {
        // Try to get device data directly using the PPPoE username
        $pppoeUsername = $customer['genieacs_pppoe_username'];
        echo "<p>PPPoE Username: " . htmlspecialchars($pppoeUsername) . "</p>\n";
        
        // Try to find device by PPPoE username
        $devices = $genie->getDevices(['VirtualParameters.pppoeUsername' => $pppoeUsername]);
        echo "<p>Device search result:</p>\n";
        echo "<pre>";
        print_r($devices);
        echo "</pre>\n";
        
        if ($devices['success'] && !empty($devices['data'])) {
            $device = $devices['data'][0];
            $deviceId = $device['_id'];
            echo "<p>Found device ID: " . htmlspecialchars($deviceId) . "</p>\n";
            
            // Get full device data
            $deviceData = $genie->getDevice($deviceId);
            echo "<p>Full device data:</p>\n";
            echo "<pre>";
            print_r($deviceData);
            echo "</pre>\n";
            
            if ($deviceData['success'] && !empty($deviceData['data'])) {
                // Parse with fast parser
                $parsedData = GenieACS_Fast::parseDeviceDataFast($deviceData['data']);
                echo "<p>Parsed data:</p>\n";
                echo "<pre>";
                print_r($parsedData);
                echo "</pre>\n";
            }
        }
    }
    
    // Now test the BillingService method
    echo "<h3>BillingService getCustomerDeviceSnapshot:</h3>\n";
    $deviceSnapshot = $billingService->getCustomerDeviceSnapshot((int)$customer['id']);
    
    echo "<pre>";
    print_r($deviceSnapshot);
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>