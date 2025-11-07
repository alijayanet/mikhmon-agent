<?php
/**
 * GenieACS Configuration
 * 
 * Copy this file to config.php and configure your GenieACS server settings
 */

// GenieACS Server Configuration
define('GENIEACS_HOST', 'localhost');
define('GENIEACS_PORT', '7557');
define('GENIEACS_PROTOCOL', 'http');
define('GENIEACS_USERNAME', ''); // Optional: Basic Auth username
define('GENIEACS_PASSWORD', ''); // Optional: Basic Auth password

// GenieACS API Base URL
define('GENIEACS_API_URL', GENIEACS_PROTOCOL . '://' . GENIEACS_HOST . ':' . GENIEACS_PORT);

// GenieACS Feature Settings
define('GENIEACS_ENABLED', true); // Set to true to enable GenieACS integration
define('GENIEACS_TIMEOUT', 30); // API timeout in seconds

// TR-069 Parameter Paths (adjust based on your ONU model)
// Common paths for different ONU vendors:

// For Huawei/ZTE/Fiberhome ONUs (InternetGatewayDevice)
define('GENIEACS_WIFI_SSID_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
define('GENIEACS_WIFI_PASSWORD_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey');
define('GENIEACS_WIFI_ENABLE_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable');

// For 5GHz WiFi (if available)
define('GENIEACS_WIFI_SSID_5G_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.SSID');
define('GENIEACS_WIFI_PASSWORD_5G_PATH', 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.PreSharedKey.1.PreSharedKey');

// Device Info Paths
define('GENIEACS_DEVICE_MODEL_PATH', 'InternetGatewayDevice.DeviceInfo.ModelName');
define('GENIEACS_DEVICE_MANUFACTURER_PATH', 'InternetGatewayDevice.DeviceInfo.Manufacturer');
define('GENIEACS_DEVICE_SERIAL_PATH', 'InternetGatewayDevice.DeviceInfo.SerialNumber');
define('GENIEACS_DEVICE_MAC_PATH', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress');
define('GENIEACS_DEVICE_FIRMWARE_PATH', 'InternetGatewayDevice.DeviceInfo.SoftwareVersion');
define('GENIEACS_DEVICE_HARDWARE_PATH', 'InternetGatewayDevice.DeviceInfo.HardwareVersion');

// WAN Status Paths
define('GENIEACS_WAN_IP_PATH', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress');
define('GENIEACS_WAN_STATUS_PATH', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionStatus');

// LAN Info
define('GENIEACS_LAN_IP_PATH', 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress');
define('GENIEACS_LAN_SUBNET_PATH', 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceSubnetMask');

// Auto-refresh interval (seconds) - how often to check device status
define('GENIEACS_AUTO_REFRESH_INTERVAL', 300); // 5 minutes

// Online threshold (seconds) - device considered offline if no inform within this time
define('GENIEACS_ONLINE_THRESHOLD', 300); // 5 minutes

?>
