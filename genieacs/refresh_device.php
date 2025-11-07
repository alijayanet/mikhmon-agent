<?php
/*
 * GenieACS Refresh Device
 * Summon Virtual Parameters and refresh device data
 */

header('Content-Type: application/json');

// Include API functions
include_once('api.php');

// Get POST data
$device_id = $_POST['device_id'] ?? '';

// Validation
if (empty($device_id)) {
    echo json_encode(['success' => false, 'error' => 'Device ID is required']);
    exit;
}

// Step 1: Refresh WLAN Configuration object to fetch all WLAN data
$refresh_wlan_task = [
    "name" => "refreshObject",
    "objectName" => "InternetGatewayDevice.LANDevice.1.WLANConfiguration."
];

// Send first task with connection request
$result = genieacs_create_task($device_id, $refresh_wlan_task, true);

if (isset($result['error'])) {
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
    exit;
}

// Step 2: Get specific WLAN parameters
$wlan_params = [
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.3.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.4.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BSSID'
];

$get_wlan_task = [
    "name" => "getParameterValues",
    "parameterNames" => $wlan_params
];

genieacs_create_task($device_id, $get_wlan_task, false);

// Step 3: Also refresh Virtual Parameters
$refresh_vp_task = [
    "name" => "refreshObject",
    "objectName" => "VirtualParameters."
];

genieacs_create_task($device_id, $refresh_vp_task, false);

echo json_encode([
    'success' => true,
    'message' => 'Device refresh initiated. WLAN data will be fetched in a few seconds.',
    'task_id' => $result['_id'] ?? null
]);
?>
