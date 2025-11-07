<?php
/*
 * GenieACS Main Page
 * Integrated with MikhMon - No session_start() needed
 */

// Get session and action from URL
$session = $_GET['session'] ?? (isset($session) ? $session : '');
$action = $_GET['action'] ?? 'list';

// Route to different pages
if ($action == 'settings') {
    include('./genieacs/settings.php');
    return;
}

// Default: Show device list page
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-server"></i> GenieACS - ONU Management
        <span style="font-size: 14px">
            &nbsp; | &nbsp; <a href="./?hotspot=genieacs&action=settings&session=<?= $session; ?>" title="Settings"><i class="fa fa-cog"></i> Settings</a>
        </span>
    </h3>
</div>
<div class="card-body">
    
    <!-- Action Buttons -->
    <div class="row mb-3">
        <div class="col-12">
            <button onclick="loadDevices()" class="btn btn-primary">
                <i class="fa fa-refresh"></i> Load Devices
            </button>
            <a href="./?hotspot=genieacs&action=settings&session=<?= $session; ?>" class="btn btn-warning">
                <i class="fa fa-cog"></i> Settings
            </a>
            <a href="./?hotspot=dashboard&session=<?= $session; ?>" class="btn btn-secondary">
                <i class="fa fa-home"></i> Dashboard
            </a>
        </div>
    </div>
    
    <!-- Loading Indicator -->
    <div id="loading-indicator" style="display:none;">
        <div class="alert alert-info">
            <i class="fa fa-spinner fa-spin"></i> Loading devices from GenieACS server...
        </div>
    </div>
    
    <!-- Device Container -->
    <div id="device-container">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> Click "Load Devices" button to fetch ONU devices from GenieACS server.
        </div>
    </div>
    
</div>
</div>
</div>
</div>

<script>
function loadDevices() {
    console.log('Loading devices...');
    
    // Show loading indicator
    document.getElementById('loading-indicator').style.display = 'block';
    document.getElementById('device-container').innerHTML = '';
    
    // Fetch devices via AJAX (using FAST version)
    var url = './genieacs/api_devices_fast.php?session=<?= $session; ?>&_=' + new Date().getTime();
    console.log('Fetching from:', url);
    
    fetch(url)
        .then(function(response) {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(function(html) {
            console.log('HTML received, length:', html.length);
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('device-container').innerHTML = html;
        })
        .catch(function(error) {
            console.error('Error:', error);
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('device-container').innerHTML = 
                '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Error loading devices: ' + error + '</div>';
        });
}

function refreshDevice(deviceId) {
    if (!confirm('Refresh device data for: ' + deviceId + '?\n\nThis will fetch latest data from the device including SSID, WiFi Password, IP, etc.\n\nThis may take 5-10 seconds.')) {
        return;
    }
    
    // Show loading notification
    var notification = document.createElement('div');
    notification.id = 'refresh-notification';
    notification.style.cssText = 'position:fixed;top:20px;right:20px;background:#17a2b8;color:white;padding:15px 20px;border-radius:5px;z-index:10000;box-shadow:0 2px 10px rgba(0,0,0,0.2);';
    notification.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Refreshing device data...';
    document.body.appendChild(notification);
    
    // Prepare form data
    var formData = new FormData();
    formData.append('device_id', deviceId);
    
    // Call API
    fetch('./genieacs/refresh_device.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            notification.style.background = '#28a745';
            notification.innerHTML = '<i class="fa fa-check"></i> ' + data.message;
            
            // Wait 8 seconds then reload devices (give device time to respond)
            setTimeout(function() {
                notification.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Reloading device list...';
                loadDevices();
                setTimeout(function() {
                    notification.remove();
                }, 1000);
            }, 8000);
        } else {
            notification.style.background = '#dc3545';
            notification.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error: ' + (data.error || 'Unknown error');
            setTimeout(function() {
                notification.remove();
            }, 5000);
        }
    })
    .catch(function(error) {
        notification.style.background = '#dc3545';
        notification.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error: ' + error;
        setTimeout(function() {
            notification.remove();
        }, 5000);
    });
}

function showDeviceDetail(deviceId) {
    // Show loading with better styling
    var modalHTML = '<div id="detailModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;">';
    modalHTML += '<div style="background:#ffffff;padding:0;border-radius:8px;max-width:900px;width:90%;max-height:85vh;overflow:hidden;color:#000;box-shadow:0 10px 40px rgba(0,0,0,0.4);display:flex;flex-direction:column;">';
    
    // Header
    modalHTML += '<div style="background:#000;color:#fff;padding:20px;border-radius:8px 8px 0 0;flex-shrink:0;">';
    modalHTML += '<h4 style="margin:0;color:#fff;font-weight:bold;font-size:18px;"><i class="fa fa-server"></i> Device Details</h4>';
    modalHTML += '<p style="margin:5px 0 0 0;color:#ccc;font-size:13px;">Device ID: ' + deviceId + '</p>';
    modalHTML += '</div>';
    
    // Body (scrollable)
    modalHTML += '<div style="padding:25px;overflow-y:auto;flex-grow:1;">';
    modalHTML += '<div id="detailContent" style="color:#000;"><div style="text-align:center;padding:40px;"><i class="fa fa-spinner fa-spin" style="font-size:32px;color:#666;"></i><p style="margin-top:15px;color:#666;">Loading device details...</p></div></div>';
    modalHTML += '</div>';
    
    // Footer
    modalHTML += '<div style="background:#f5f5f5;padding:15px 25px;border-radius:0 0 8px 8px;flex-shrink:0;">';
    modalHTML += '<button onclick="closeDetailModal()" class="btn" style="width:100%;font-weight:bold;color:#666;background:#fff;border:2px solid #ddd;padding:10px;border-radius:6px;cursor:pointer;"><i class="fa fa-times"></i> Close</button>';
    modalHTML += '</div>';
    
    modalHTML += '</div>';
    modalHTML += '</div>';
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Fetch device details
    fetch('./genieacs/device_detail.php?device_id=' + encodeURIComponent(deviceId))
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            document.getElementById('detailContent').innerHTML = html;
        })
        .catch(function(error) {
            document.getElementById('detailContent').innerHTML = '<div class="alert alert-danger">Error loading details: ' + error + '</div>';
        });
}

function closeDetailModal() {
    var modal = document.getElementById('detailModal');
    if (modal) {
        modal.remove();
    }
}

function editWiFi(deviceId, currentSSID, currentPassword) {
    // Create modal HTML with better styling
    var modalHTML = '<div id="wifiModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;">';
    modalHTML += '<div style="background:#ffffff;padding:0;border-radius:8px;max-width:550px;width:90%;color:#000;box-shadow:0 10px 40px rgba(0,0,0,0.4);">';
    
    // Header
    modalHTML += '<div style="background:#000;color:#fff;padding:20px;border-radius:8px 8px 0 0;">';
    modalHTML += '<h4 style="margin:0;color:#fff;font-weight:bold;font-size:18px;"><i class="fa fa-wifi"></i> Edit WiFi Configuration</h4>';
    modalHTML += '<p style="margin:5px 0 0 0;color:#ccc;font-size:13px;">Device: ' + deviceId + '</p>';
    modalHTML += '</div>';
    
    // Body
    modalHTML += '<div style="padding:25px;">';
    
    // Info alert
    modalHTML += '<div style="background:#e7f3ff;border-left:4px solid #2196F3;padding:12px;margin-bottom:20px;border-radius:4px;">';
    modalHTML += '<p style="margin:0;color:#000;font-size:13px;"><i class="fa fa-info-circle" style="color:#2196F3;"></i> <strong>Info:</strong> Perubahan akan dikirim ke ONU melalui GenieACS. Device mungkin perlu reboot untuk menerapkan konfigurasi baru.</p>';
    modalHTML += '</div>';
    
    // SSID Section
    modalHTML += '<div style="margin-bottom:20px;">';
    modalHTML += '<label style="display:block;margin-bottom:8px;color:#000;font-weight:bold;font-size:14px;"><i class="fa fa-wifi"></i> WiFi SSID</label>';
    modalHTML += '<input type="text" id="newSSID" value="' + (currentSSID !== 'N/A' ? currentSSID : '') + '" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;margin-bottom:8px;font-size:14px;color:#000;background:#fff;box-sizing:border-box;" placeholder="Enter WiFi SSID (1-32 characters)">';
    modalHTML += '<small style="color:#666;display:block;margin-bottom:10px;font-size:12px;">SSID harus antara 1-32 karakter</small>';
    modalHTML += '<button onclick="saveSSID(\'' + deviceId + '\')" class="btn" style="width:100%;font-weight:bold;color:#fff;background:#000;border:none;padding:12px;border-radius:6px;cursor:pointer;font-size:14px;"><i class="fa fa-save"></i> Save SSID Only</button>';
    modalHTML += '</div>';
    
    // Password Section
    modalHTML += '<div style="margin-bottom:20px;">';
    modalHTML += '<label style="display:block;margin-bottom:8px;color:#000;font-weight:bold;font-size:14px;"><i class="fa fa-key"></i> WiFi Password</label>';
    modalHTML += '<input type="text" id="newPassword" value="' + (currentPassword !== 'N/A' ? currentPassword : '') + '" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;margin-bottom:8px;font-size:14px;color:#000;background:#fff;box-sizing:border-box;" placeholder="Enter WiFi Password (8-63 characters)">';
    modalHTML += '<small style="color:#666;display:block;margin-bottom:10px;font-size:12px;">Password harus antara 8-63 karakter untuk WPA/WPA2</small>';
    modalHTML += '<button onclick="savePassword(\'' + deviceId + '\')" class="btn" style="width:100%;font-weight:bold;color:#fff;background:#ffc107;border:none;padding:12px;border-radius:6px;cursor:pointer;font-size:14px;"><i class="fa fa-save"></i> Save Password Only</button>';
    modalHTML += '</div>';
    
    modalHTML += '</div>';
    
    // Footer
    modalHTML += '<div style="background:#f5f5f5;padding:15px 25px;border-radius:0 0 8px 8px;">';
    modalHTML += '<button onclick="closeWiFiModal()" class="btn" style="width:100%;font-weight:bold;color:#666;background:#fff;border:2px solid #ddd;padding:12px;border-radius:6px;cursor:pointer;font-size:14px;"><i class="fa fa-times"></i> Close</button>';
    modalHTML += '</div>';
    
    modalHTML += '</div>';
    modalHTML += '</div>';
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeWiFiModal() {
    var modal = document.getElementById('wifiModal');
    if (modal) {
        modal.remove();
    }
}

function saveSSID(deviceId) {
    var newSSID = document.getElementById('newSSID').value.trim();
    
    // Validation
    if (!newSSID) {
        alert('SSID cannot be empty!');
        return;
    }
    
    if (!confirm('Save SSID for device: ' + deviceId + '?\n\nNew SSID: ' + newSSID)) {
        return;
    }
    
    // Show loading
    var saveBtn = event.target;
    var originalHTML = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
    
    // Prepare form data
    var formData = new FormData();
    formData.append('device_id', deviceId);
    formData.append('ssid', newSSID);
    
    // Call API
    fetch('./genieacs/save_wifi.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            alert('SSID saved successfully!\n\nThe device will apply the new SSID shortly.');
            closeWiFiModal();
            loadDevices();
        } else {
            alert('Error: ' + (data.error || 'Unknown error occurred'));
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHTML;
        }
    })
    .catch(function(error) {
        alert('Error saving SSID: ' + error);
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHTML;
    });
}

function savePassword(deviceId) {
    var newPassword = document.getElementById('newPassword').value.trim();
    
    // Validation
    if (!newPassword) {
        alert('Password cannot be empty!');
        return;
    }
    
    if (newPassword.length < 8) {
        alert('Password must be at least 8 characters!');
        return;
    }
    
    if (!confirm('Save Password for device: ' + deviceId + '?\n\nNew Password: ********')) {
        return;
    }
    
    // Show loading
    var saveBtn = event.target;
    var originalHTML = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
    
    // Prepare form data - get current SSID to send along
    var currentSSID = document.getElementById('newSSID').value.trim();
    var formData = new FormData();
    formData.append('device_id', deviceId);
    formData.append('ssid', currentSSID); // Send current SSID
    formData.append('password', newPassword);
    
    // Call API
    fetch('./genieacs/save_wifi.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            alert('Password saved successfully!\n\nThe device will apply the new password shortly.');
            closeWiFiModal();
            loadDevices();
        } else {
            alert('Error: ' + (data.error || 'Unknown error occurred'));
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHTML;
        }
    })
    .catch(function(error) {
        alert('Error saving password: ' + error);
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHTML;
    });
}
</script>
