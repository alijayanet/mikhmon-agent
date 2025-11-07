<?php
/**
 * GenieACS API Wrapper Class
 * 
 * Provides methods to interact with GenieACS TR-069 ACS server
 */

class GenieACS {
    private $apiUrl;
    private $username;
    private $password;
    private $timeout;
    private $enabled;
    
    public function __construct() {
        // Load config if exists
        $configFile = __DIR__ . '/../config.php';
        if (file_exists($configFile)) {
            require_once($configFile);
            $this->apiUrl = GENIEACS_API_URL;
            $this->username = GENIEACS_USERNAME;
            $this->password = GENIEACS_PASSWORD;
            $this->timeout = GENIEACS_TIMEOUT;
            $this->enabled = GENIEACS_ENABLED;
        } else {
            $this->enabled = false;
        }
    }
    
    /**
     * Check if GenieACS is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Make HTTP request to GenieACS API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'GenieACS is not enabled'];
        }
        
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Basic Auth if configured
        if (!empty($this->username) && !empty($this->password)) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }
        
        // Set headers
        $headers = ['Content-Type: application/json'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Add data for POST/PUT
        if ($data !== null && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error];
        }
        
        $result = json_decode($response, true);
        
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'data' => $result,
            'raw' => $response
        ];
    }
    
    /**
     * Get all devices/ONUs
     */
    public function getDevices($query = [], $projection = null) {
        $endpoint = '/devices/';
        
        if (!empty($query)) {
            $endpoint .= '?query=' . urlencode(json_encode($query));
        }
        
        if ($projection) {
            $separator = empty($query) ? '?' : '&';
            $endpoint .= $separator . 'projection=' . urlencode($projection);
        }
        
        return $this->makeRequest($endpoint);
    }
    
    /**
     * Get single device by ID
     */
    public function getDevice($deviceId) {
        $query = ['_id' => $deviceId];
        $result = $this->getDevices($query);
        
        if ($result['success'] && !empty($result['data'])) {
            $result['data'] = $result['data'][0];
        }
        
        return $result;
    }
    
    /**
     * Execute task on device
     */
    public function executeTask($deviceId, $task, $connectionRequest = true) {
        $endpoint = '/devices/' . urlencode($deviceId) . '/tasks';
        
        if ($connectionRequest) {
            $endpoint .= '?connection_request';
        }
        
        return $this->makeRequest($endpoint, 'POST', $task);
    }
    
    /**
     * Get parameter values from device
     */
    public function getParameterValues($deviceId, $parameterNames) {
        $task = [
            'name' => 'getParameterValues',
            'parameterNames' => $parameterNames
        ];
        
        return $this->executeTask($deviceId, $task);
    }
    
    /**
     * Set parameter values on device
     */
    public function setParameterValues($deviceId, $parameterValues) {
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => $parameterValues
        ];
        
        return $this->executeTask($deviceId, $task);
    }
    
    /**
     * Change WiFi SSID and Password
     */
    public function changeWiFi($deviceId, $ssid, $password = null, $band = '2.4') {
        $parameterValues = [];
        
        // Determine which band (2.4GHz or 5GHz)
        $ssidPath = ($band == '5') ? GENIEACS_WIFI_SSID_5G_PATH : GENIEACS_WIFI_SSID_PATH;
        $passwordPath = ($band == '5') ? GENIEACS_WIFI_PASSWORD_5G_PATH : GENIEACS_WIFI_PASSWORD_PATH;
        
        // Add SSID
        if (!empty($ssid)) {
            $parameterValues[] = [$ssidPath, $ssid, 'xsd:string'];
        }
        
        // Add Password
        if (!empty($password)) {
            $parameterValues[] = [$passwordPath, $password, 'xsd:string'];
        }
        
        if (empty($parameterValues)) {
            return ['success' => false, 'message' => 'No parameters to change'];
        }
        
        return $this->setParameterValues($deviceId, $parameterValues);
    }
    
    /**
     * Refresh device object (get latest data)
     */
    public function refreshObject($deviceId, $objectName = '') {
        $task = [
            'name' => 'refreshObject',
            'objectName' => $objectName
        ];
        
        return $this->executeTask($deviceId, $task);
    }
    
    /**
     * Reboot device
     */
    public function reboot($deviceId) {
        $task = ['name' => 'reboot'];
        return $this->executeTask($deviceId, $task);
    }
    
    /**
     * Factory reset device
     */
    public function factoryReset($deviceId) {
        $task = ['name' => 'factoryReset'];
        return $this->executeTask($deviceId, $task);
    }
    
    /**
     * Get device info (Model, Manufacturer, Serial, MAC, etc)
     */
    public function getDeviceInfo($device) {
        $info = [
            'id' => $device['_id'] ?? 'Unknown',
            'model' => 'Unknown',
            'manufacturer' => 'Unknown',
            'serial' => 'Unknown',
            'mac' => 'Unknown',
            'firmware' => 'Unknown',
            'hardware' => 'Unknown',
            'last_inform' => $device['_lastInform'] ?? null,
            'online' => false
        ];
        
        // Check if online
        if (isset($device['_lastInform'])) {
            $lastInform = strtotime($device['_lastInform']);
            $now = time();
            $info['online'] = ($now - $lastInform) < GENIEACS_ONLINE_THRESHOLD;
        }
        
        // Extract device info from parameters
        if (isset($device[GENIEACS_DEVICE_MODEL_PATH]['_value'])) {
            $info['model'] = $device[GENIEACS_DEVICE_MODEL_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_DEVICE_MANUFACTURER_PATH]['_value'])) {
            $info['manufacturer'] = $device[GENIEACS_DEVICE_MANUFACTURER_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_DEVICE_SERIAL_PATH]['_value'])) {
            $info['serial'] = $device[GENIEACS_DEVICE_SERIAL_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_DEVICE_MAC_PATH]['_value'])) {
            $info['mac'] = $device[GENIEACS_DEVICE_MAC_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_DEVICE_FIRMWARE_PATH]['_value'])) {
            $info['firmware'] = $device[GENIEACS_DEVICE_FIRMWARE_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_DEVICE_HARDWARE_PATH]['_value'])) {
            $info['hardware'] = $device[GENIEACS_DEVICE_HARDWARE_PATH]['_value'];
        }
        
        return $info;
    }
    
    /**
     * Get WiFi info from device
     */
    public function getWiFiInfo($device) {
        $wifi = [
            '2.4ghz' => [
                'ssid' => 'Unknown',
                'password' => '********',
                'enabled' => false
            ],
            '5ghz' => [
                'ssid' => 'Unknown',
                'password' => '********',
                'enabled' => false
            ]
        ];
        
        // 2.4GHz WiFi
        if (isset($device[GENIEACS_WIFI_SSID_PATH]['_value'])) {
            $wifi['2.4ghz']['ssid'] = $device[GENIEACS_WIFI_SSID_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_WIFI_PASSWORD_PATH]['_value'])) {
            $wifi['2.4ghz']['password'] = $device[GENIEACS_WIFI_PASSWORD_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_WIFI_ENABLE_PATH]['_value'])) {
            $wifi['2.4ghz']['enabled'] = (bool)$device[GENIEACS_WIFI_ENABLE_PATH]['_value'];
        }
        
        // 5GHz WiFi (if available)
        if (isset($device[GENIEACS_WIFI_SSID_5G_PATH]['_value'])) {
            $wifi['5ghz']['ssid'] = $device[GENIEACS_WIFI_SSID_5G_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_WIFI_PASSWORD_5G_PATH]['_value'])) {
            $wifi['5ghz']['password'] = $device[GENIEACS_WIFI_PASSWORD_5G_PATH]['_value'];
        }
        
        return $wifi;
    }
    
    /**
     * Get WAN info from device
     */
    public function getWANInfo($device) {
        $wan = [
            'ip' => 'Unknown',
            'status' => 'Unknown'
        ];
        
        if (isset($device[GENIEACS_WAN_IP_PATH]['_value'])) {
            $wan['ip'] = $device[GENIEACS_WAN_IP_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_WAN_STATUS_PATH]['_value'])) {
            $wan['status'] = $device[GENIEACS_WAN_STATUS_PATH]['_value'];
        }
        
        return $wan;
    }
    
    /**
     * Get LAN info from device
     */
    public function getLANInfo($device) {
        $lan = [
            'ip' => 'Unknown',
            'subnet' => 'Unknown'
        ];
        
        if (isset($device[GENIEACS_LAN_IP_PATH]['_value'])) {
            $lan['ip'] = $device[GENIEACS_LAN_IP_PATH]['_value'];
        }
        
        if (isset($device[GENIEACS_LAN_SUBNET_PATH]['_value'])) {
            $lan['subnet'] = $device[GENIEACS_LAN_SUBNET_PATH]['_value'];
        }
        
        return $lan;
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        $result = $this->getDevices();
        
        if (!$result['success']) {
            return [
                'total' => 0,
                'online' => 0,
                'offline' => 0
            ];
        }
        
        $devices = $result['data'] ?? [];
        $total = count($devices);
        $online = 0;
        
        foreach ($devices as $device) {
            $info = $this->getDeviceInfo($device);
            if ($info['online']) {
                $online++;
            }
        }
        
        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online
        ];
    }
}
?>
