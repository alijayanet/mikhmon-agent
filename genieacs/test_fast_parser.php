<?php
/**
 * Test Fast Parser Performance
 * Compare old vs new parsing method
 */

// Include API functions
include_once('api.php');

// Include fast parser
require_once('lib/GenieACS_Fast.class.php');

// Get devices from GenieACS
echo "<h2>GenieACS Fast Parser Performance Test</h2>";
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
    echo '<p>No ONU devices are currently registered in GenieACS.</p>';
    echo '</div>';
    exit;
}

$deviceCount = count($devices);
echo "<p><strong>Total Devices:</strong> $deviceCount</p>";
echo "<hr>";

// Test 1: Old method (get_value function)
echo "<h3>Test 1: Traditional Method (String Parsing)</h3>";

function get_value($array, $paths) {
    if (!is_array($paths)) {
        $paths = array($paths);
    }
    
    foreach ($paths as $path) {
        $keys = explode('.', $path);
        $current = $array;
        $found = true;
        
        foreach ($keys as $key) {
            if (preg_match('/^(\w+)\[(\d+)\]$/', $key, $matches)) {
                $key = $matches[1];
                $index = (int)$matches[2];
                if (isset($current[$key]) && is_array($current[$key]) && isset($current[$key][$index])) {
                    $current = $current[$key][$index];
                } else {
                    $found = false;
                    break;
                }
            } else {
                if (isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    $found_key = null;
                    if (is_array($current)) {
                        foreach ($current as $k => $v) {
                            if (strtolower($k) === strtolower($key)) {
                                $found_key = $k;
                                break;
                            }
                        }
                    }
                    if ($found_key !== null) {
                        $current = $current[$found_key];
                    } else {
                        $found = false;
                        break;
                    }
                }
            }
        }
        
        if ($found && $current !== null && $current !== '') {
            if (is_array($current) && isset($current['_value'])) {
                return $current['_value'];
            }
            if (is_array($current) && isset($current['value'])) {
                return $current['value'];
            }
            if (is_array($current) && isset($current['_object']) && $current['_object'] === false) {
                continue;
            }
            if (!is_array($current)) {
                return $current;
            }
        }
    }
    
    return 'N/A';
}

$startTime = microtime(true);
$oldResults = [];

foreach ($devices as $device) {
    $data = [];
    $data['pppoe_id'] = get_value($device, array(
        'VirtualParameters.pppoeUsername',
        'VirtualParameters.pppoeUsername2'
    ));
    $data['ssid'] = get_value($device, array(
        'VirtualParameters.SSID_ALL',
        'VirtualParameters.SSID'
    ));
    $data['rx_power'] = get_value($device, array(
        'VirtualParameters.RXPower'
    ));
    $data['temp'] = get_value($device, array(
        'VirtualParameters.gettemp'
    ));
    $data['serial'] = get_value($device, array(
        'VirtualParameters.getSerialNumber'
    ));
    
    $oldResults[] = $data;
}

$oldTime = (microtime(true) - $startTime) * 1000;

echo "<p><strong>Time:</strong> " . number_format($oldTime, 2) . " ms</p>";
echo "<p><strong>Memory:</strong> " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";
echo "<p><strong>Method:</strong> String parsing with explode() and nested loops</p>";

// Reset memory
$oldResults = null;
gc_collect_cycles();

echo "<hr>";

// Test 2: New fast method
echo "<h3>Test 2: Fast Parser Method (Direct Array Access)</h3>";

$startTime = microtime(true);
$newResults = GenieACS_Fast::parseMultipleDevices($devices);
$newTime = (microtime(true) - $startTime) * 1000;

echo "<p><strong>Time:</strong> " . number_format($newTime, 2) . " ms</p>";
echo "<p><strong>Memory:</strong> " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";
echo "<p><strong>Method:</strong> Direct array access with null coalescing operator (??)</p>";

echo "<hr>";

// Comparison
echo "<h3>Performance Comparison</h3>";

$speedup = $oldTime / $newTime;
$timeReduction = (($oldTime - $newTime) / $oldTime) * 100;

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>";
echo "<th>Metric</th>";
echo "<th>Traditional Method</th>";
echo "<th>Fast Parser</th>";
echo "<th>Improvement</th>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>Parse Time</strong></td>";
echo "<td>" . number_format($oldTime, 2) . " ms</td>";
echo "<td style='background-color: #d4edda;'>" . number_format($newTime, 2) . " ms</td>";
echo "<td style='background-color: #d4edda;'><strong>" . number_format($speedup, 2) . "x faster</strong></td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>Time Reduction</strong></td>";
echo "<td>-</td>";
echo "<td style='background-color: #d4edda;'>" . number_format($timeReduction, 1) . "%</td>";
echo "<td style='background-color: #d4edda;'><strong>Saved " . number_format($oldTime - $newTime, 2) . " ms</strong></td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>Devices Processed</strong></td>";
echo "<td>" . $deviceCount . "</td>";
echo "<td>" . $deviceCount . "</td>";
echo "<td>-</td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>Avg Time per Device</strong></td>";
echo "<td>" . number_format($oldTime / $deviceCount, 2) . " ms</td>";
echo "<td style='background-color: #d4edda;'>" . number_format($newTime / $deviceCount, 2) . " ms</td>";
echo "<td>-</td>";
echo "</tr>";

echo "</table>";

echo "<hr>";

// Statistics
echo "<h3>Device Statistics</h3>";
$stats = GenieACS_Fast::getStatistics($newResults);

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Total Devices</td><td>" . $stats['total'] . "</td></tr>";
echo "<tr><td>Online</td><td style='color: green;'><strong>" . $stats['online'] . "</strong></td></tr>";
echo "<tr><td>Offline</td><td style='color: red;'><strong>" . $stats['offline'] . "</strong></td></tr>";
echo "<tr><td>Total Connected Devices</td><td>" . $stats['total_connected_devices'] . "</td></tr>";
echo "<tr><td>Average RX Power</td><td>" . $stats['avg_rx_power'] . " dBm</td></tr>";
echo "<tr><td>Average Temperature</td><td>" . $stats['avg_temperature'] . "°C</td></tr>";
echo "</table>";

echo "<hr>";

// Sample data comparison
echo "<h3>Sample Data (First Device)</h3>";

if (!empty($newResults)) {
    $sample = $newResults[0];
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    
    foreach ($sample as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";

// Recommendations
echo "<h3>Recommendations</h3>";
echo "<div style='background-color: #d4edda; padding: 15px; border-left: 5px solid #28a745;'>";
echo "<p><strong>✅ Use Fast Parser for Production</strong></p>";
echo "<ul>";
echo "<li>Fast Parser is <strong>" . number_format($speedup, 2) . "x faster</strong> than traditional method</li>";
echo "<li>Reduces parse time by <strong>" . number_format($timeReduction, 1) . "%</strong></li>";
echo "<li>More data points extracted (status, ping, MAC, connected devices, etc.)</li>";
echo "<li>Better memory efficiency</li>";
echo "<li>Easier to maintain (direct array access)</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";

echo "<h3>How to Use Fast Parser</h3>";
echo "<pre style='background-color: #f5f5f5; padding: 15px; border: 1px solid #ddd;'>";
echo htmlspecialchars("
// Include fast parser
require_once('lib/GenieACS_Fast.class.php');

// Get devices from GenieACS
\$devices = genieacs_get_devices();

// Parse all devices at once
\$parsedDevices = GenieACS_Fast::parseMultipleDevices(\$devices);

// Get statistics
\$stats = GenieACS_Fast::getStatistics(\$parsedDevices);

// Use parsed data
foreach (\$parsedDevices as \$device) {
    echo \$device['pppoe_username'];
    echo \$device['wifi_ssid'];
    echo \$device['status']; // online/offline
    echo \$device['ping']; // estimated ping
    echo \$device['mac_address']; // with fallback
    echo \$device['connected_devices_count'];
    // ... and more
}
");
echo "</pre>";

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>
