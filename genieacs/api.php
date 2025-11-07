<?php
/*
 * GenieACS API Functions
 */

// Include configuration
if (file_exists('./genieacs/config.php')) {
    include_once('./genieacs/config.php');
} elseif (file_exists('config.php')) {
    include_once('config.php');
}

// Function to make API requests to GenieACS
function genieacs_api_request($endpoint, $method = 'GET', $data = null) {
    global $genieacs_api_base, $genieacs_host, $genieacs_port, $genieacs_protocol, $genieacs_username, $genieacs_password, $genieacs_alternative_ports;
    
    // Try the default configuration first
    $urls_to_try = array();
    $urls_to_try[] = $genieacs_api_base . $endpoint;
    
    // Add alternative ports if different from default
    // IMPORTANT: Skip port 3000 (Admin UI, not API)
    $valid_api_ports = array(7557, 80, 8080); // Only valid API ports
    foreach ($genieacs_alternative_ports as $port) {
        if ($port != $genieacs_port && in_array($port, $valid_api_ports)) {
            $urls_to_try[] = $genieacs_protocol . '://' . $genieacs_host . ':' . $port . $endpoint;
        }
    }
    
    // Try each URL
    foreach ($urls_to_try as $url) {
        // Log request
        error_log("GenieACS API Request: " . $method . " " . $url);
        
        // Check if cURL is available
        if (function_exists('curl_init')) {
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Very short timeout for faster response
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Connection timeout
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            // Set headers
            $headers = array('Content-Type: application/json');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // Add authentication if required
            if (!empty($genieacs_username)) {
                curl_setopt($ch, CURLOPT_USERPWD, $genieacs_username . ':' . $genieacs_password);
                error_log("GenieACS API Authentication: Enabled for user " . $genieacs_username);
            }
            
            // Add data for POST/PUT requests
            if ($data !== null && ($method === 'POST' || $method === 'PUT')) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                error_log("GenieACS API Request Data: " . json_encode($data));
            }
            
            // Execute request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Log response
            error_log("GenieACS API Response HTTP Code: " . $http_code . " from " . $url);
            if (!empty($error)) {
                error_log("GenieACS API Response Error: " . $error . " from " . $url);
            }
            
            // If successful, return the response
            if ($http_code >= 200 && $http_code < 300 && empty($error)) {
                // Decode JSON response
                $result = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("GenieACS API JSON Decode Error: " . json_last_error_msg() . " - Response: " . $response);
                    return array('error' => 'JSON Decode Error: ' . json_last_error_msg() . ' - Response: ' . $response);
                }
                return $result;
            }
            
            // Special handling for GenieACS - it might return 404 for empty collections which is still valid
            if ($http_code == 404 && strpos($endpoint, '/devices/') !== false) {
                // Return empty array for devices endpoint with 404 (no devices found)
                return array();
            }
            
            // If this is the last URL to try, return the error
            if ($url === end($urls_to_try)) {
                if (!empty($error)) {
                    return array('error' => 'cURL Error: ' . $error . ' - URL: ' . $url);
                } else {
                    return array('error' => 'HTTP Error: ' . $http_code . ' - Response: ' . $response . ' - URL: ' . $url);
                }
            }
        } else {
            // Fallback method using file_get_contents (if cURL is not available)
            error_log("GenieACS API: cURL not available, using file_get_contents as fallback for " . $url);
            
            // Create context for HTTP request
            $context_options = array(
                'http' => array(
                    'method' => $method,
                    'timeout' => 3, // Very short timeout for faster response
                    'ignore_errors' => true,
                    'header' => "Content-Type: application/json\r\n"
                )
            );
            
            // Add authentication if required
            if (!empty($genieacs_username)) {
                $context_options['http']['header'] .= "Authorization: Basic " . base64_encode($genieacs_username . ':' . $genieacs_password) . "\r\n";
            }
            
            // Add data for POST/PUT requests
            if ($data !== null && ($method === 'POST' || $method === 'PUT')) {
                $context_options['http']['content'] = json_encode($data);
            }
            
            $context = stream_context_create($context_options);
            
            // Execute request
            $response = @file_get_contents($url, false, $context);
            
            // Get HTTP response code
            $http_code = 500;
            if (isset($http_response_header)) {
                $http_code = (int)substr($http_response_header[0], 9, 3);
            }
            
            // If successful, return the response
            if (($http_code >= 200 && $http_code < 300) && $response !== false) {
                // Decode JSON response
                $result = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("GenieACS API JSON Decode Error: " . json_last_error_msg() . " - Response: " . $response);
                    return array('error' => 'JSON Decode Error: ' . json_last_error_msg() . ' - Response: ' . $response);
                }
                return $result;
            }
            
            // Special handling for GenieACS - it might return 404 for empty collections which is still valid
            if ($http_code == 404 && strpos($endpoint, '/devices/') !== false) {
                // Return empty array for devices endpoint with 404 (no devices found)
                return array();
            }
            
            // If this is the last URL to try, return the error
            if ($url === end($urls_to_try)) {
                // Get the last error
                $error = error_get_last();
                $error_msg = isset($error['message']) ? $error['message'] : 'Unknown error';
                return array('error' => 'file_get_contents Error: Unable to connect to ' . $url . ' - ' . $error_msg);
            }
        }
    }
    
    // This should never be reached, but just in case
    return array('error' => 'Unable to connect to GenieACS server after trying all configured ports');
}

// Function to get list of devices with comprehensive data
function genieacs_get_devices($query = null) {
    // IMPORTANT: Fetch ALL data without projection to get VirtualParameters
    // Projection sometimes doesn't include VirtualParameters properly
    
    if ($query) {
        $query_param = $query;
    } else {
        $query_param = array();
    }
    
    // Fetch without projection to get ALL data including VirtualParameters
    if (!empty($query_param)) {
        $endpoint = '/devices/?query=' . urlencode(json_encode($query_param));
    } else {
        $endpoint = '/devices/';
    }
    
    $result = genieacs_api_request($endpoint, 'GET');
    
    // Handle the response structure
    if (is_array($result) && !isset($result['error'])) {
        // If it's already an array of devices, return it
        if (isset($result[0]) || count($result) == 0) {
            return $result;
        }
        // If it's a single device object, wrap it in an array
        if (is_array($result) && !empty($result)) {
            // Check if it's a device object by looking for common device fields
            if (isset($result['_id']) || isset($result['DeviceID']) || isset($result['InternetGatewayDevice'])) {
                return array($result);
            }
        }
        // Return as is if we can't determine the structure
        return $result;
    }
    
    // If there's an error, return it
    return $result;
}

// Function to get device parameters
function genieacs_get_device_parameters($device_id, $parameters = null) {
    $query = array('_id' => $device_id);
    
    if ($parameters) {
        // Create projection string
        $projection = implode(',', $parameters);
        $endpoint = '/devices/?query=' . urlencode(json_encode($query)) . '&projection=' . urlencode($projection);
    } else {
        // Get full device data without projection
        $endpoint = '/devices/?query=' . urlencode(json_encode($query));
    }
    
    $result = genieacs_api_request($endpoint, 'GET');
    
    // Handle the response structure
    if (is_array($result) && !isset($result['error'])) {
        // If it's a direct array of devices, return it
        if (isset($result[0]) || count($result) == 0) {
            return $result;
        }
        // If it's a single device object, wrap it in an array
        return array($result);
    }
    
    return $result;
}

// Function to set device parameters
function genieacs_set_device_parameters($device_id, $parameter_values, $connection_request = true) {
    $endpoint = '/devices/' . urlencode($device_id) . '/tasks';
    if ($connection_request) {
        $endpoint .= '?connection_request';
    }
    
    $data = array(
        'name' => 'setParameterValues',
        'parameterValues' => $parameter_values
    );
    
    return genieacs_api_request($endpoint, 'POST', $data);
}

// Function to refresh device parameters
function genieacs_refresh_device($device_id, $connection_request = true) {
    $endpoint = '/devices/' . urlencode($device_id) . '/tasks';
    if ($connection_request) {
        $endpoint .= '?connection_request';
    }
    
    $data = array(
        'name' => 'refreshObject',
        'objectName' => ''
    );
    
    return genieacs_api_request($endpoint, 'POST', $data);
}

// Function to get virtual parameters from GenieACS
function genieacs_get_virtual_parameters() {
    $endpoint = '/virtual_parameters/';
    return genieacs_api_request($endpoint, 'GET');
}

// Function to get device info with virtual parameters
function genieacs_get_device_info($device_id) {
    // First, get basic device info
    $basic_info = genieacs_get_device_parameters($device_id, array(
        'InternetGatewayDevice.DeviceInfo.ModelName',
        'InternetGatewayDevice.DeviceInfo.Manufacturer',
        'VirtualParameters.getSerialNumber',
        'VirtualParameters.getdeviceuptime'
    ));
    
    if (isset($basic_info['error'])) {
        return $basic_info;
    }
    
    // Then, get virtual parameters if available
    $virtual_params = genieacs_get_virtual_parameters();
    
    // Combine results
    $result = array(
        'basic_info' => $basic_info,
        'virtual_parameters' => $virtual_params
    );
    
    return $result;
}

// Function to get comprehensive device data including virtual parameters
function genieacs_get_comprehensive_device_data($device_id) {
    // Get all relevant parameters including virtual ones
    $parameters = array(
        // Basic device info
        'InternetGatewayDevice.DeviceInfo.ModelName',
        'InternetGatewayDevice.DeviceInfo.Manufacturer',
        'InternetGatewayDevice.DeviceInfo.SerialNumber',
        'InternetGatewayDevice.DeviceInfo.UpTime',
        'DeviceID.ProductClass',
        'Events.Registered',
        'Events.Inform',
        
        // WiFi parameters
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
        
        // Virtual parameters - using the keys from config
        'VirtualParameters.pppoeUsername',
        'VirtualParameters.pppoeUsername2',
        'VirtualParameters.pppoeIP',
        'VirtualParameters.pppoeMac',
        'VirtualParameters.getSerialNumber',
        'VirtualParameters.getdeviceuptime',
        'VirtualParameters.getponmode',
        'VirtualParameters.gettemp',
        'VirtualParameters.RXPower',
        'VirtualParameters.IPTR069',
        'VirtualParameters.PonMac',
        'VirtualParameters.hotspot'
    );
    
    return genieacs_get_device_parameters($device_id, $parameters);
}

// Function to create a task for a device
function genieacs_create_task($device_id, $task, $connection_request = false) {
    $endpoint = '/devices/' . urlencode($device_id) . '/tasks';
    
    // Add connection_request parameter if requested
    if ($connection_request) {
        $endpoint .= '?connection_request';
    }
    
    // Send POST request with task data
    $result = genieacs_api_request($endpoint, 'POST', $task);
    
    return $result;
}

?>