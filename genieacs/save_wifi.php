<?php
/*
 * GenieACS Save WiFi Settings
 * API endpoint to change WiFi SSID and Password
 */

header('Content-Type: application/json');

// Include API functions
include_once('api.php');

// Get POST data
$device_id = $_POST['device_id'] ?? '';
$ssid = $_POST['ssid'] ?? '';
$password = $_POST['password'] ?? '';

// Validation
if (empty($device_id)) {
    echo json_encode(['success' => false, 'error' => 'Device ID is required']);
    exit;
}

if (empty($ssid)) {
    echo json_encode(['success' => false, 'error' => 'SSID is required']);
    exit;
}

if (!empty($password) && strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}

// Prepare parameters to set using DIRECT parameter paths
// Format: [path, value, type] - as per GenieACS API documentation
$params_to_set = [];

if (!empty($ssid)) {
    // Direct path to SSID (works for most manufacturers)
    $params_to_set[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $ssid, 'xsd:string'];
}

if (!empty($password)) {
    // Try multiple password paths for different manufacturers
    // Huawei, ZTE, FiberHome use PreSharedKey.1.KeyPassphrase
    $params_to_set[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $password, 'xsd:string'];
    // Some manufacturers use direct KeyPassphrase
    $params_to_set[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase', $password, 'xsd:string'];
}

// Create task object
$task = [
    "name" => "setParameterValues",
    "parameterValues" => $params_to_set
];

// Send task to GenieACS
$result = genieacs_create_task($device_id, $task, true); // true = connection_request

if (isset($result['error'])) {
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'WiFi settings updated successfully',
        'task_id' => $result['_id'] ?? null
    ]);
}
?>
