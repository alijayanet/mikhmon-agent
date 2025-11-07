<?php
/*
 * GenieACS Device Detail
 * Display comprehensive device information
 */

// Include API functions
include_once('api.php');

// Get device ID
$device_id = $_GET['device_id'] ?? '';

if (empty($device_id)) {
    echo '<div class="alert alert-danger">Device ID is required</div>';
    exit;
}

// Get device data
$query = array('_id' => $device_id);
$result = genieacs_get_devices($query);

if (isset($result['error'])) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($result['error']) . '</div>';
    exit;
}

if (empty($result)) {
    echo '<div class="alert alert-warning">Device not found</div>';
    exit;
}

$device = $result[0];

// Helper function
function get_val($device, $path) {
    $keys = explode('.', $path);
    $current = $device;
    
    foreach ($keys as $key) {
        if (isset($current[$key])) {
            $current = $current[$key];
        } else {
            return 'N/A';
        }
    }
    
    if (is_array($current) && isset($current['_value'])) {
        return $current['_value'];
    }
    
    return is_scalar($current) ? $current : 'N/A';
}

?>
<style>
    /* Force all text to be black and readable */
    #detailContent * {
        color: #000 !important;
    }
    #detailContent h5 {
        color: #000 !important;
        font-weight: bold !important;
        font-size: 16px !important;
    }
    #detailContent table {
        color: #000 !important;
        background: #fff !important;
    }
    #detailContent td, #detailContent th {
        color: #000 !important;
        font-size: 13px !important;
    }
    #detailContent strong {
        font-weight: bold !important;
    }
</style>
<div class="row">
    <div class="col-md-6">
        <h5 style="color:#000;font-weight:bold;"><i class="fa fa-info-circle"></i> Device Information</h5>
        <table class="table table-sm table-bordered" style="color:#000;background:#fff;">
            <tr>
                <td width="40%"><strong>Device ID:</strong></td>
                <td><?= htmlspecialchars($device_id); ?></td>
            </tr>
            <tr>
                <td><strong>PPPoE Username:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.pppoeUsername')); ?></td>
            </tr>
            <tr>
                <td><strong>Serial Number:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.getSerialNumber')); ?></td>
            </tr>
            <tr>
                <td><strong>PON Mode:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.getponmode')); ?></td>
            </tr>
            <tr>
                <td><strong>Product Class:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'DeviceID.ProductClass')); ?></td>
            </tr>
            <tr>
                <td><strong>Last Inform:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'Events.Inform')); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h5 style="color:#000;font-weight:bold;"><i class="fa fa-wifi"></i> WiFi Information</h5>
        <table class="table table-sm table-bordered" style="color:#000;background:#fff;">
            <tr>
                <td width="40%"><strong>SSID:</strong></td>
                <td><?php 
                    $ssid = get_val($device, 'VirtualParameters.SSID');
                    if ($ssid === 'N/A') {
                        $ssid = get_val($device, 'VirtualParameters.SSID_ALL');
                    }
                    echo htmlspecialchars($ssid);
                ?></td>
            </tr>
            <tr>
                <td><strong>Active Clients:</strong></td>
                <td><?php 
                    $active = get_val($device, 'VirtualParameters.activedevices');
                    if ($active === 'N/A') {
                        $active = get_val($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations');
                    }
                    echo htmlspecialchars($active);
                ?></td>
            </tr>
        </table>
        
        <h5 style="color:#000;font-weight:bold;"><i class="fa fa-signal"></i> Optical Information</h5>
        <table class="table table-sm table-bordered" style="color:#000;background:#fff;">
            <tr>
                <td width="40%"><strong>RX Power:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.RXPower')); ?> dBm</td>
            </tr>
            <tr>
                <td><strong>Temperature:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.gettemp')); ?>°C</td>
            </tr>
        </table>
        
        <h5 style="color:#000;font-weight:bold;"><i class="fa fa-globe"></i> Network Information</h5>
        <table class="table table-sm table-bordered" style="color:#000;background:#fff;">
            <tr>
                <td width="40%"><strong>IP PPPoE:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.pppoeIP')); ?></td>
            </tr>
            <tr>
                <td><strong>IP TR069:</strong></td>
                <td><?= htmlspecialchars(get_val($device, 'VirtualParameters.IPTR069')); ?></td>
            </tr>
        </table>
    </div>
</div>
