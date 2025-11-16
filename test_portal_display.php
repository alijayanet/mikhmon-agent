<?php
// Test script to simulate billing portal display

session_start();
$_SESSION['mikhmon'] = true;
$_SESSION['billing_portal_customer_id'] = 1; // Use the same customer ID as in our debug

// Include required files
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';

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
    
    $deviceSnapshot = $billingService->getCustomerDeviceSnapshot((int)$customer['id']);
    
    echo "<h3>Device Snapshot:</h3>\n";
    echo "<pre>";
    print_r($deviceSnapshot);
    echo "</pre>\n";
    
    // Simulate the portal display logic
    if (!empty($deviceSnapshot)) {
        echo "<h3>Portal Display Simulation:</h3>\n";
        $snapshotStatus = $deviceSnapshot['status'] ?? null;
        $statusLabel = $snapshotStatus ? strtoupper($snapshotStatus) : 'TIDAK DIKETAHUI';
        $statusClass = $snapshotStatus === 'online' ? 'online' : ($snapshotStatus === 'offline' ? 'offline' : '');
        $connectedDevices = $deviceSnapshot['connected_devices'] ?? null;
        $connectedLabel = $connectedDevices !== null ? $connectedDevices : 'N/A';
        $rxPowerRaw = $deviceSnapshot['rx_power'] ?? null;
        $rxLabel = ($rxPowerRaw !== null && $rxPowerRaw !== 'N/A' && $rxPowerRaw !== '') ? $rxPowerRaw . ' dBm' : 'N/A';
        $temperatureRaw = $deviceSnapshot['temperature'] ?? null;
        $temperatureLabel = ($temperatureRaw !== null && $temperatureRaw !== 'N/A' && $temperatureRaw !== '') ? $temperatureRaw . '°C' : 'N/A';
        $pppoeLabel = $deviceSnapshot['pppoe_username'] ?? 'N/A';
        
        echo "<ul>\n";
        echo "<li>Status Label: " . htmlspecialchars($statusLabel) . "</li>\n";
        echo "<li>Connected Devices: " . htmlspecialchars((string)$connectedLabel) . "</li>\n";
        echo "<li>RX Power Label: " . htmlspecialchars($rxLabel) . "</li>\n";
        echo "<li>Temperature Label: " . htmlspecialchars($temperatureLabel) . "</li>\n";
        echo "<li>PPPoE Username: " . htmlspecialchars($pppoeLabel) . "</li>\n";
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>