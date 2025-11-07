<?php
/**
 * Debug Device Data - Lihat struktur data mentah dari GenieACS
 * Gunakan file ini untuk melihat bagaimana data Virtual Parameters disimpan
 */

// Include API functions
include_once('api.php');

// Get devices from GenieACS
echo "<h2>Debug: GenieACS Device Data Structure</h2>";
echo "<hr>";

$devices = genieacs_get_devices();

// Check for errors
if (isset($devices['error'])) {
    echo '<div style="color: red;">';
    echo '<h3>Connection Error</h3>';
    echo '<p>' . htmlspecialchars($devices['error']) . '</p>';
    echo '</div>';
    exit;
}

// Check if empty
if (empty($devices)) {
    echo '<div style="color: orange;">';
    echo '<h3>No Devices Found</h3>';
    echo '</div>';
    exit;
}

$deviceCount = count($devices);
echo "<p><strong>Total Devices:</strong> $deviceCount</p>";
echo "<hr>";

// Show first device structure
$firstDevice = $devices[0];
$deviceId = $firstDevice['_id'] ?? 'Unknown';

echo "<h3>First Device: $deviceId</h3>";
echo "<hr>";

// Check if VirtualParameters exists
echo "<h4>1. VirtualParameters Check</h4>";
if (isset($firstDevice['VirtualParameters'])) {
    echo "<p style='color: green;'><strong>✅ VirtualParameters EXISTS!</strong></p>";
    echo "<p>Available Virtual Parameters:</p>";
    echo "<ul>";
    foreach ($firstDevice['VirtualParameters'] as $key => $value) {
        $displayValue = is_array($value) ? json_encode($value) : $value;
        echo "<li><strong>$key:</strong> " . htmlspecialchars($displayValue) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'><strong>❌ VirtualParameters NOT FOUND!</strong></p>";
    echo "<p>Ini berarti Virtual Parameters belum di-setup atau device belum inform.</p>";
}

echo "<hr>";

// Check specific Virtual Parameters
echo "<h4>2. Specific Virtual Parameters Values</h4>";
$vpToCheck = [
    'pppoeUsername',
    'pppoeUsername2',
    'SSID',
    'SSID_ALL',
    'WlanPassword',
    'RXPower',
    'gettemp',
    'pppoeIP',
    'getponmode',
    'getSerialNumber',
    'getdeviceuptime',
    'activedevices'
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Parameter</th><th>Value</th><th>Status</th></tr>";

foreach ($vpToCheck as $param) {
    $value = $firstDevice['VirtualParameters'][$param]['_value'] ?? null;
    
    if ($value !== null && $value !== '') {
        $status = "<span style='color: green;'>✅ OK</span>";
        $displayValue = htmlspecialchars($value);
    } else {
        $status = "<span style='color: red;'>❌ N/A</span>";
        $displayValue = "N/A";
    }
    
    echo "<tr>";
    echo "<td><strong>$param</strong></td>";
    echo "<td>$displayValue</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// Check _deviceId
echo "<h4>3. _deviceId Check</h4>";
if (isset($firstDevice['_deviceId'])) {
    echo "<p style='color: green;'><strong>✅ _deviceId EXISTS!</strong></p>";
    echo "<ul>";
    echo "<li><strong>_SerialNumber:</strong> " . htmlspecialchars($firstDevice['_deviceId']['_SerialNumber'] ?? 'N/A') . "</li>";
    echo "<li><strong>_Manufacturer:</strong> " . htmlspecialchars($firstDevice['_deviceId']['_Manufacturer'] ?? 'N/A') . "</li>";
    echo "<li><strong>_OUI:</strong> " . htmlspecialchars($firstDevice['_deviceId']['_OUI'] ?? 'N/A') . "</li>";
    echo "<li><strong>_ProductClass:</strong> " . htmlspecialchars($firstDevice['_deviceId']['_ProductClass'] ?? 'N/A') . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'><strong>❌ _deviceId NOT FOUND!</strong></p>";
}

echo "<hr>";

// Check InternetGatewayDevice parameters
echo "<h4>4. InternetGatewayDevice Parameters Check</h4>";
$igdParams = [
    'InternetGatewayDevice.DeviceInfo.SerialNumber',
    'InternetGatewayDevice.DeviceInfo.HardwareVersion',
    'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase'
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Parameter Path</th><th>Value</th><th>Status</th></tr>";

foreach ($igdParams as $path) {
    $keys = explode('.', $path);
    $value = $firstDevice;
    $found = true;
    
    foreach ($keys as $key) {
        if (isset($value[$key])) {
            $value = $value[$key];
        } else {
            $found = false;
            break;
        }
    }
    
    if ($found && is_array($value) && isset($value['_value'])) {
        $displayValue = htmlspecialchars($value['_value']);
        $status = "<span style='color: green;'>✅ OK</span>";
    } else {
        $displayValue = "N/A";
        $status = "<span style='color: red;'>❌ N/A</span>";
    }
    
    echo "<tr>";
    echo "<td><strong>$path</strong></td>";
    echo "<td>$displayValue</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// Show raw JSON structure (limited)
echo "<h4>5. Raw Device Data (First 50 keys)</h4>";
echo "<details>";
echo "<summary>Click to expand</summary>";
echo "<pre style='background: #f5f5f5; padding: 15px; overflow: auto; max-height: 500px;'>";

$keys = array_keys($firstDevice);
$limitedKeys = array_slice($keys, 0, 50);

foreach ($limitedKeys as $key) {
    $value = $firstDevice[$key];
    if (is_array($value)) {
        echo "$key: [Array with " . count($value) . " items]\n";
    } else {
        echo "$key: " . htmlspecialchars($value) . "\n";
    }
}

if (count($keys) > 50) {
    echo "\n... and " . (count($keys) - 50) . " more keys\n";
}

echo "</pre>";
echo "</details>";

echo "<hr>";

// Recommendations
echo "<h3>Recommendations</h3>";
echo "<div style='background-color: #e7f3ff; padding: 15px; border-left: 5px solid #2196F3;'>";

if (!isset($firstDevice['VirtualParameters'])) {
    echo "<p><strong>❌ Virtual Parameters NOT FOUND</strong></p>";
    echo "<p>Solusi:</p>";
    echo "<ol>";
    echo "<li>Setup Virtual Parameters di GenieACS Admin UI</li>";
    echo "<li>Tunggu device inform ulang (5-30 menit)</li>";
    echo "<li>Atau klik tombol Refresh di Mikhmon</li>";
    echo "<li>Reload page ini untuk cek lagi</li>";
    echo "</ol>";
} else {
    $missingParams = [];
    foreach ($vpToCheck as $param) {
        $value = $firstDevice['VirtualParameters'][$param]['_value'] ?? null;
        if ($value === null || $value === '') {
            $missingParams[] = $param;
        }
    }
    
    if (empty($missingParams)) {
        echo "<p><strong>✅ ALL VIRTUAL PARAMETERS OK!</strong></p>";
        echo "<p>Semua Virtual Parameters sudah ada dan terisi. Data seharusnya tampil di Mikhmon.</p>";
        echo "<p>Jika masih muncul N/A di Mikhmon, coba:</p>";
        echo "<ol>";
        echo "<li>Clear browser cache</li>";
        echo "<li>Reload page Mikhmon</li>";
        echo "<li>Check console browser untuk error JavaScript</li>";
        echo "</ol>";
    } else {
        echo "<p><strong>⚠️ SOME VIRTUAL PARAMETERS MISSING</strong></p>";
        echo "<p>Parameter yang belum terisi:</p>";
        echo "<ul>";
        foreach ($missingParams as $param) {
            echo "<li>$param</li>";
        }
        echo "</ul>";
        echo "<p>Solusi:</p>";
        echo "<ol>";
        echo "<li>Check Virtual Parameter script di GenieACS</li>";
        echo "<li>Test Virtual Parameter dengan device ini</li>";
        echo "<li>Klik tombol Refresh di Mikhmon untuk device ini</li>";
        echo "</ol>";
    }
}

echo "</div>";

echo "<hr>";
echo "<p><em>Debug completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>
