<?php
// Include configuration
include('config.php');

echo "<h2>GenieACS Connection Test</h2>";
echo "<p>Testing connection to GenieACS server:</p>";

// Display configuration
echo "<h3>Configuration:</h3>";
echo "<ul>";
echo "<li>Host: " . htmlspecialchars($genieacs_host) . "</li>";
echo "<li>Port: " . htmlspecialchars($genieacs_port) . "</li>";
echo "<li>Protocol: " . htmlspecialchars($genieacs_protocol) . "</li>";
echo "<li>API Base: " . htmlspecialchars($genieacs_api_base) . "</li>";
echo "</ul>";

// Test connection using fsockopen
echo "<h3>Socket Connection Test:</h3>";
$connection = @fsockopen($genieacs_host, $genieacs_port, $errno, $errstr, 10);
if ($connection) {
    echo "<p style='color: green;'>✓ Socket connection successful</p>";
    fclose($connection);
} else {
    echo "<p style='color: red;'>✗ Socket connection failed: " . htmlspecialchars($errstr) . " (" . $errno . ")</p>";
}

// Test HTTP request
echo "<h3>HTTP Request Test:</h3>";
$url = $genieacs_api_base . '/devices/';
echo "<p>Testing URL: " . htmlspecialchars($url) . "</p>";

// Use cURL if available
if (function_exists('curl_init')) {
    echo "<p>cURL is available</p>";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Add authentication if required
    if (!empty($genieacs_username)) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs_username . ':' . $genieacs_password);
        echo "<p>Authentication enabled for user: " . htmlspecialchars($genieacs_username) . "</p>";
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p>HTTP Response Code: " . $http_code . "</p>";
    if (!empty($error)) {
        echo "<p style='color: red;'>cURL Error: " . htmlspecialchars($error) . "</p>";
    } else {
        echo "<p style='color: green;'>cURL Request successful</p>";
        if ($response !== false) {
            echo "<p>Response length: " . strlen($response) . " characters</p>";
            if (strlen($response) > 0) {
                echo "<p>First 500 characters of response:</p>";
                echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
                
                // Try to decode JSON
                $json = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<p>JSON decoded successfully</p>";
                    echo "<p>Number of devices: " . (is_array($json) ? count($json) : 'N/A') . "</p>";
                } else {
                    echo "<p style='color: red;'>JSON decode error: " . json_last_error_msg() . "</p>";
                }
            }
        }
    }
} else {
    echo "<p style='color: orange;'>cURL is not available, using file_get_contents</p>";
    
    // Create context for HTTP request
    $context_options = array(
        'http' => array(
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\n"
        )
    );
    
    // Add authentication if required
    if (!empty($genieacs_username)) {
        $context_options['http']['header'] .= "Authorization: Basic " . base64_encode($genieacs_username . ':' . $genieacs_password) . "\r\n";
        echo "<p>Authentication enabled for user: " . htmlspecialchars($genieacs_username) . "</p>";
    }
    
    $context = stream_context_create($context_options);
    $response = @file_get_contents($url, false, $context);
    
    // Get HTTP response code
    $http_code = 500;
    if (isset($http_response_header)) {
        $http_code = (int)substr($http_response_header[0], 9, 3);
    }
    
    echo "<p>HTTP Response Code: " . $http_code . "</p>";
    if ($response !== false) {
        echo "<p style='color: green;'>Request successful</p>";
        echo "<p>Response length: " . strlen($response) . " characters</p>";
        if (strlen($response) > 0) {
            echo "<p>First 500 characters of response:</p>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
            
            // Try to decode JSON
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "<p>JSON decoded successfully</p>";
                echo "<p>Number of devices: " . (is_array($json) ? count($json) : 'N/A') . "</p>";
            } else {
                echo "<p style='color: red;'>JSON decode error: " . json_last_error_msg() . "</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>Request failed</p>";
        $error = error_get_last();
        if ($error) {
            echo "<p>Error: " . htmlspecialchars($error['message']) . "</p>";
        }
    }
}

echo "<h3>Recommendations:</h3>";
echo "<ul>";
echo "<li>If connection fails, check if GenieACS is running on the target server</li>";
echo "<li>Verify firewall settings on both servers</li>";
echo "<li>Check if GenieACS is configured to listen on the correct interface (0.0.0.0 vs 127.0.0.1)</li>";
echo "<li>Try accessing GenieACS directly through a browser to verify it's running</li>";
echo "</ul>";
?>