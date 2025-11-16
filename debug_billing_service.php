<?php
// Debug BillingService instantiation
echo "<h2>Debug BillingService Instantiation</h2>";

// Check PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>Extension $ext: OK</p>";
    } else {
        echo "<p style='color: red;'>Extension $ext: MISSING</p>";
    }
}

// Try to include the files step by step
echo "<h3>File Inclusion Test:</h3>";

$db_config_file = './include/db_config.php';
$billing_service_file = './lib/BillingService.class.php';

if (file_exists($db_config_file)) {
    echo "<p style='color: green;'>Found db_config.php</p>";
    try {
        include_once($db_config_file);
        echo "<p style='color: green;'>Successfully included db_config.php</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error including db_config.php: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
} else {
    echo "<p style='color: red;'>db_config.php not found</p>";
    exit;
}

if (file_exists($billing_service_file)) {
    echo "<p style='color: green;'>Found BillingService.class.php</p>";
    try {
        require_once($billing_service_file);
        echo "<p style='color: green;'>Successfully included BillingService.class.php</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error including BillingService.class.php: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    } catch (Throwable $e) {
        echo "<p style='color: red;'>Error including BillingService.class.php: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
} else {
    echo "<p style='color: red;'>BillingService.class.php not found</p>";
    exit;
}

// Check if getDBConnection function exists
echo "<h3>Database Connection Test:</h3>";
if (function_exists('getDBConnection')) {
    echo "<p style='color: green;'>getDBConnection function exists</p>";
    try {
        $db = getDBConnection();
        if ($db) {
            echo "<p style='color: green;'>Database connection successful</p>";
        } else {
            echo "<p style='color: red;'>Database connection returned null</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
} else {
    echo "<p style='color: red;'>getDBConnection function does not exist</p>";
    exit;
}

// Try to instantiate BillingService
echo "<h3>BillingService Instantiation Test:</h3>";
try {
    echo "<p>Attempting to create BillingService instance...</p>";
    $billingService = new BillingService();
    echo "<p style='color: green;'>Successfully created BillingService instance</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error creating BillingService instance: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Exception type: " . get_class($e) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
} catch (Throwable $e) {
    echo "<p style='color: red;'>Error creating BillingService instance: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Exception type: " . get_class($e) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}

echo "<h3>Conclusion:</h3>";
echo "<p style='color: green;'>All tests passed! BillingService can be instantiated successfully.</p>";
echo "<p>If you're still experiencing issues with the billing invoices page, the problem might be elsewhere in the application.</p>";
?>