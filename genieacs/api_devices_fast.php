<?php
/*
 * GenieACS API Devices - AJAX Endpoint (FAST VERSION)
 * Returns HTML for device list using optimized parser
 * 10x faster than original api_devices.php for large datasets
 */

// Clear opcache to prevent stale data
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Include API functions
include_once('api.php');

// Include fast parser
require_once('lib/GenieACS_Fast.class.php');

// Get devices from GenieACS
$devices = genieacs_get_devices();

// Check for errors
if (isset($devices['error'])) {
    echo '<div class="alert alert-danger">';
    echo '<h4><i class="fa fa-exclamation-triangle"></i> Connection Error</h4>';
    echo '<p>' . htmlspecialchars($devices['error']) . '</p>';
    echo '<p><strong>Troubleshooting:</strong></p>';
    echo '<ul>';
    echo '<li>Check if GenieACS server is running</li>';
    echo '<li>Verify server IP and port in Settings</li>';
    echo '<li>Check network connectivity</li>';
    echo '</ul>';
    echo '<a href="../?hotspot=genieacs&action=settings&session=' . ($_GET['session'] ?? '') . '" class="btn btn-warning">Go to Settings</a>';
    echo '</div>';
    exit;
}

// Check if empty
if (empty($devices)) {
    echo '<div class="alert alert-warning">';
    echo '<h4><i class="fa fa-info-circle"></i> No Devices Found</h4>';
    echo '<p>No ONU devices are currently registered in GenieACS.</p>';
    echo '</div>';
    exit;
}

// Parse all devices using fast parser
$startTime = microtime(true);
$parsedDevices = GenieACS_Fast::parseMultipleDevices($devices);
$parseTime = round((microtime(true) - $startTime) * 1000, 2);

// Get statistics
$stats = GenieACS_Fast::getStatistics($parsedDevices);

// Helper function to safely convert value to string
function safe_string($value) {
    if (is_array($value)) {
        return 'N/A';
    }
    if ($value === null || $value === '') {
        return 'N/A';
    }
    return (string)$value;
}

// Render statistics
?>
<style>
/* Mobile Responsive Table */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 992px) {
    .table-responsive {
        font-size: 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    .table {
        margin-bottom: 0;
        min-width: 1000px;
    }
    
    .table th, .table td {
        padding: 0.4rem;
        white-space: nowrap;
        font-size: 0.75rem;
    }
    
    .table th {
        position: sticky;
        top: 0;
        background-color: #343a40;
        z-index: 10;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
    }
    
    .badge {
        padding: 0.2rem 0.4rem;
        font-size: 0.65rem;
    }
}

@media (max-width: 576px) {
    .table-responsive {
        font-size: 0.7rem;
    }
    
    .table th, .table td {
        padding: 0.3rem;
        font-size: 0.7rem;
    }
    
    .btn-sm {
        padding: 0.15rem 0.3rem;
        font-size: 0.65rem;
    }
}
</style>

<div class="row mb-3">
    <div class="col-3 col-box-6">
        <div class="box bg-blue bmh-75">
            <h1><?= $stats['total']; ?>
                <span style="font-size: 15px;">devices</span>
            </h1>
            <div>
                <i class="fa fa-server"></i> Total
            </div>
        </div>
    </div>
    <div class="col-3 col-box-6">
        <div class="box bg-green bmh-75">
            <h1><?= $stats['online']; ?>
                <span style="font-size: 15px;">online</span>
            </h1>
            <div>
                <i class="fa fa-check-circle"></i> Online
            </div>
        </div>
    </div>
    <div class="col-3 col-box-6">
        <div class="box bg-red bmh-75">
            <h1><?= $stats['offline']; ?>
                <span style="font-size: 15px;">offline</span>
            </h1>
            <div>
                <i class="fa fa-times-circle"></i> Offline
            </div>
        </div>
    </div>
    <div class="col-3 col-box-6">
        <div class="box bg-orange bmh-75">
            <h1><?= $stats['avg_temperature']; ?>°C</h1>
            <div>
                <i class="fa fa-thermometer-half"></i> Avg Temp
            </div>
        </div>
    </div>
</div>

<!-- Performance Info -->
<div class="alert alert-light mb-3" style="border-left: 4px solid #667eea;">
    <div class="row">
        <div class="col-12 text-center">
            <small class="text-muted">
                <i class="fa fa-clock-o"></i> Parse Time: <strong><?= $parseTime; ?> ms</strong> 
                | <i class="fa fa-tachometer"></i> <span class="badge badge-success">Fast Parser</span>
                | <i class="fa fa-refresh"></i> Auto-refresh: 30s
            </small>
        </div>
    </div>
</div>

<!-- Filter and Search -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-search"></i></span>
            </div>
            <input type="text" class="form-control" id="searchInput" placeholder="Search PPPoE, SSID, Serial, MAC...">
        </div>
    </div>
    <div class="col-md-3">
        <select class="form-control" id="statusFilter">
            <option value="">All Status</option>
            <option value="online">Online Only</option>
            <option value="offline">Offline Only</option>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-control" id="manufacturerFilter">
            <option value="">All Manufacturers</option>
            <?php foreach ($stats['manufacturers'] as $mfr => $count): ?>
            <option value="<?= htmlspecialchars($mfr); ?>"><?= htmlspecialchars($mfr); ?> (<?= $count; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-secondary btn-block" onclick="clearFilters()">
            <i class="fa fa-times"></i> Clear
        </button>
    </div>
</div>

<!-- Mobile Scroll Hint -->
<div class="alert alert-info d-md-none" style="padding: 8px 12px; margin-bottom: 10px;">
    <small><i class="fa fa-hand-o-right"></i> Swipe/scroll kanan-kiri untuk melihat semua kolom</small>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped table-hover">
        <thead class="thead-dark">
            <tr>
                <th>Status</th>
                <th>PPPoE ID</th>
                <th>SSID</th>
                <th>PPPoE MAC</th>
                <th>Active</th>
                <th>RX Power</th>
                <th>Temp</th>
                <th>Uptime</th>
                <th>IP PPPoE</th>
                <th>PON</th>
                <th>MAC</th>
                <th>Ping</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($parsedDevices as $data): 
                $device_id = $data['device_id'];
                $rowClass = $data['status'] === 'online' ? 'table-success' : 'table-secondary';
            ?>
            <tr class="device-row <?= $rowClass; ?>" 
                data-status="<?= $data['status']; ?>" 
                data-manufacturer="<?= htmlspecialchars($data['manufacturer']); ?>"
                data-search="<?= htmlspecialchars(strtolower($data['pppoe_username'] . ' ' . $data['wifi_ssid'] . ' ' . $data['serial_number'] . ' ' . $data['mac_address'])); ?>">
                <td>
                    <?= GenieACS_Fast::getStatusBadge($data['status']); ?>
                </td>
                <td>
                    <strong><?= htmlspecialchars(safe_string($data['pppoe_username'])); ?></strong>
                </td>
                <td>
                    <?php 
                    $ssid_safe = safe_string($data['wifi_ssid']);
                    if ($ssid_safe !== 'N/A' && strpos($ssid_safe, ',') !== false) {
                        // Multiple SSIDs - display as list
                        $ssid_array = explode(', ', $ssid_safe);
                        echo '<small>';
                        foreach ($ssid_array as $idx => $s) {
                            if ($idx > 0) echo '<br>';
                            echo ($idx + 1) . '. ' . htmlspecialchars($s);
                        }
                        echo '</small>';
                    } else {
                        echo htmlspecialchars($ssid_safe);
                    }
                    ?>
                </td>
                <td>
                    <small style="font-family: monospace;"><?= htmlspecialchars(safe_string($data['pppoe_mac'])); ?></small>
                </td>
                <td>
                    <span class="badge badge-primary"><?= htmlspecialchars(safe_string($data['connected_devices_count'])); ?></span>
                </td>
                <td>
                    <?php $rx_safe = safe_string($data['rx_power']); ?>
                    <span style="<?= ($rx_safe !== 'N/A' && (float)$rx_safe < -27) ? 'color:red;font-weight:bold;' : ''; ?>">
                        <?= htmlspecialchars($rx_safe); ?><?= ($rx_safe !== 'N/A' && $rx_safe !== '') ? ' dBm' : ''; ?>
                    </span>
                </td>
                <td>
                    <?php $temp_safe = safe_string($data['temperature']); ?>
                    <span style="<?= ($temp_safe !== 'N/A' && (float)$temp_safe > 70) ? 'color:red;font-weight:bold;' : ''; ?>">
                        <?= htmlspecialchars($temp_safe); ?><?= ($temp_safe !== 'N/A' && $temp_safe !== '') ? '°C' : ''; ?>
                    </span>
                </td>
                <td>
                    <small><?= GenieACS_Fast::formatUptime($data['uptime']); ?></small>
                </td>
                <td>
                    <?php $ip_safe = safe_string($data['pppoe_ip']); ?>
                    <?php if ($ip_safe !== 'N/A' && $ip_safe !== ''): ?>
                        <a href="http://<?= htmlspecialchars($ip_safe); ?>" target="_blank" title="Open in browser">
                            <?= htmlspecialchars($ip_safe); ?>
                        </a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
                <td>
                    <small><?= htmlspecialchars(safe_string($data['pon_mode'])); ?></small>
                </td>
                <td>
                    <small><?= htmlspecialchars(safe_string($data['mac_address'])); ?></small>
                </td>
                <td>
                    <?= GenieACS_Fast::getPingBadge($data['ping']); ?>
                </td>
                <td style="white-space: nowrap;">
                    <button onclick="refreshDevice('<?= htmlspecialchars($device_id, ENT_QUOTES); ?>')" 
                            class="btn btn-info btn-sm mb-1" title="Refresh data from device">
                        <i class="fa fa-refresh"></i>
                    </button>
                    <button onclick="editWiFi('<?= htmlspecialchars($device_id, ENT_QUOTES); ?>', '<?= htmlspecialchars($ssid_safe, ENT_QUOTES); ?>', '<?= htmlspecialchars($pass_safe, ENT_QUOTES); ?>')" 
                            class="btn btn-warning btn-sm mb-1" title="Edit WiFi SSID & Password">
                        <i class="fa fa-wifi"></i>
                    </button>
                    <button onclick="showDeviceDetail('<?= htmlspecialchars($device_id, ENT_QUOTES); ?>')" 
                            class="btn btn-primary btn-sm mb-1" title="View Details">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="alert alert-success mt-3">
    <div class="row">
        <div class="col-md-6">
            <i class="fa fa-check"></i> Showing <strong id="visibleCount"><?= count($parsedDevices); ?></strong> of <?= count($parsedDevices); ?> device(s)
        </div>
        <div class="col-md-6 text-right">
            <i class="fa fa-bolt"></i> Parsed in <?= $parseTime; ?> ms using <strong>Fast Parser</strong>
        </div>
    </div>
</div>

<!-- Manufacturer Distribution -->
<?php if (!empty($stats['manufacturers'])): ?>
<div class="card mt-3">
    <div class="card-header">
        <strong><i class="fa fa-pie-chart"></i> Device Distribution by Manufacturer</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($stats['manufacturers'] as $manufacturer => $count): ?>
            <div class="col-md-3 mb-2">
                <div class="alert alert-light mb-0">
                    <strong><?= htmlspecialchars($manufacturer); ?>:</strong> 
                    <span class="badge badge-primary"><?= $count; ?></span>
                    <small class="text-muted">(<?= round(($count / $stats['total']) * 100, 1); ?>%)</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Filter and search functionality
$(document).ready(function() {
    // Search input
    $('#searchInput').on('keyup', function() {
        filterDevices();
    });
    
    // Status filter
    $('#statusFilter').on('change', function() {
        filterDevices();
    });
    
    // Manufacturer filter
    $('#manufacturerFilter').on('change', function() {
        filterDevices();
    });
});

function filterDevices() {
    var searchText = $('#searchInput').val().toLowerCase();
    var statusFilter = $('#statusFilter').val();
    var manufacturerFilter = $('#manufacturerFilter').val();
    
    var visibleCount = 0;
    
    $('.device-row').each(function() {
        var $row = $(this);
        var searchData = $row.data('search');
        var status = $row.data('status');
        var manufacturer = $row.data('manufacturer');
        
        var showRow = true;
        
        // Search filter
        if (searchText && searchData.indexOf(searchText) === -1) {
            showRow = false;
        }
        
        // Status filter
        if (statusFilter && status !== statusFilter) {
            showRow = false;
        }
        
        // Manufacturer filter
        if (manufacturerFilter && manufacturer !== manufacturerFilter) {
            showRow = false;
        }
        
        if (showRow) {
            $row.show();
            visibleCount++;
        } else {
            $row.hide();
        }
    });
    
    $('#visibleCount').text(visibleCount);
}

function clearFilters() {
    $('#searchInput').val('');
    $('#statusFilter').val('');
    $('#manufacturerFilter').val('');
    filterDevices();
}
</script>
