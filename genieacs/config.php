<?php
/*
 * GenieACS Configuration
 */

// GenieACS API Configuration
$genieacs_host = '192.168.8.89';  // GenieACS server IP/hostname
$genieacs_port = 7557;         // Using port 7557 as confirmed by actual server configuration
$genieacs_protocol = 'http';   // http or https
$genieacs_username = 'alijaya';       // If authentication is required
$genieacs_password = 'password_sebenarnya';       // If authentication is required

// Alternative ports to try if default fails
$genieacs_alternative_ports = array(80, 7557, 8080, 3000);

// API Endpoints
$genieacs_api_base = $genieacs_protocol . '://' . $genieacs_host . ':' . $genieacs_port;

// Common TR-069 Parameters
$genieacs_parameters = array(
    // WiFi Parameters
    'wifi_ssid' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
    'wifi_password' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey',
    
    // PPPoE Parameters
    'pppoe_username' => 'VirtualParameters.pppoeUsername',
    'pppoe_username2' => 'VirtualParameters.pppoeUsername2',
    'pppoe_password' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password',
    'pppoe_ip' => 'VirtualParameters.pppoeIP',
    'pppoe_mac' => 'VirtualParameters.pppoeMac',
    
    // Device Info Parameters
    'device_model' => 'InternetGatewayDevice.DeviceInfo.ModelName',
    'device_manufacturer' => 'InternetGatewayDevice.DeviceInfo.Manufacturer',
    'device_serial' => 'VirtualParameters.getSerialNumber',
    'device_uptime' => 'VirtualParameters.getdeviceuptime',
    
    // Optical Parameters
    'optical_rx_power' => 'VirtualParameters.RXPower',
    'optical_pon_mode' => 'VirtualParameters.getponmode',
    'optical_temp' => 'VirtualParameters.gettemp',
    'optical_mac' => 'VirtualParameters.PonMac',
    
    // Network Parameters
    'ip_tr069' => 'VirtualParameters.IPTR069',
    'hotspot' => 'VirtualParameters.hotspot',
    
    // Connection Info
    'total_associations' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
    'product_class' => 'DeviceID.ProductClass',
    'registered_time' => 'Events.Registered',
    'last_inform' => 'Events.Inform'
);

// Virtual Parameters (to be populated from GenieACS server)
$genieacs_virtual_parameters = array();

?>