<?php
use ReflectionClass;

// Billing Invoices Page Test Script
echo "<h2>Billing Invoices Page Test</h2>";
echo "<p>Testing billing invoices page components...</p>";



// Start timing
$start_time = microtime(true);

// Include required files
echo "<h3>File Inclusion Test:</h3>";

$required_files = [
    './include/db_config.php',
    './lib/BillingService.class.php',
    './billing/invoices.php'
];

$all_files_found = true;
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'><strong>FOUND:</strong> $file</p>";
    } else {
        echo "<p style='color: red;'><strong>MISSING:</strong> $file</p>";
        $all_files_found = false;
    }
}

if (!$all_files_found) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Some required files are missing!</p>";
    exit;
}

// Test BillingService instantiation
echo "<h3>BillingService Test:</h3>";
try {
    require_once('./include/db_config.php');
    require_once('./lib/BillingService.class.php');
    
    $service_start = microtime(true);
    $billingService = new BillingService();
    $service_end = microtime(true);
    
    echo "<p style='color: green;'><strong>SUCCESS:</strong> BillingService instantiated in " . round(($service_end - $service_start) * 1000, 2) . " ms</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Failed to instantiate BillingService: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
} catch (Throwable $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Failed to instantiate BillingService: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test database queries
echo "<h3>Database Query Test:</h3>";
try {
    $query_start = microtime(true);
    
    // Test summary query (similar to billing_invoices.php)
    $currentPeriod = date('Y-m');
    // Since we can't access private $db property, we'll test using BillingService methods
    try {
        $summaryData = $billingService->getDashboardSummary($currentPeriod);
        $summary = [
            'total' => $summaryData['invoices']['total'],
            'paid' => $summaryData['invoices']['paid'],
            'unpaid' => $summaryData['invoices']['unpaid'],
            'total_amount' => $summaryData['amounts']['total'],
            'paid_amount' => $summaryData['amounts']['paid']
        ];
    } catch (Exception $e) {
        // Fallback to manual query if method doesn't exist
        $reflection = new ReflectionClass($billingService);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $db = $dbProperty->getValue($billingService);
        
        $summaryStmt = $db->prepare("SELECT 
            COUNT(*) AS total,
            SUM(status='paid') AS paid,
            SUM(status='unpaid') AS unpaid,
            SUM(amount) AS total_amount,
            SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS paid_amount
        FROM billing_invoices
        WHERE period = :period");
        $summaryStmt->execute([':period' => $currentPeriod]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'paid' => 0, 'unpaid' => 0, 'total_amount' => 0, 'paid_amount' => 0];
    }
    
    $query_end = microtime(true);
    
    echo "<p style='color: green;'><strong>SUCCESS:</strong> Summary query executed in " . round(($query_end - $query_start) * 1000, 2) . " ms</p>";
    echo "<p>Summary Data: " . json_encode($summary) . "</p>";
    
    // Test invoices query
    $query_start = microtime(true);
    try {
        // Try using BillingService method
        $invoices = $billingService->listInvoices([], 10);
    } catch (Exception $e) {
        // Fallback to manual query if method doesn't exist
        $reflection = new ReflectionClass($billingService);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $db = $dbProperty->getValue($billingService);
        
        $invoiceStmt = $db->prepare("SELECT bi.*, bc.name AS customer_name, bc.phone, bc.service_number, bp.profile_name
        FROM billing_invoices bi
        INNER JOIN billing_customers bc ON bi.customer_id = bc.id
        LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id
        ORDER BY bi.created_at DESC
        LIMIT 10");
        $invoiceStmt->execute();
        $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $query_end = microtime(true);
    
    echo "<p style='color: green;'><strong>SUCCESS:</strong> Invoices query executed in " . round(($query_end - $query_start) * 1000, 2) . " ms</p>";
    echo "<p>Found " . count($invoices) . " invoices</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Database query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test customer options query
echo "<h3>Customer Options Query Test:</h3>";
try {
    $query_start = microtime(true);
    try {
        // Try using BillingService method
        $allCustomers = $billingService->getCustomers(5);
        $customerOptions = array_map(function($customer) {
            return ['id' => $customer['id'], 'name' => $customer['name']];
        }, $allCustomers);
    } catch (Exception $e) {
        // Fallback to manual query
        $reflection = new ReflectionClass($billingService);
        $dbProperty = $reflection->getProperty('db');
        $dbProperty->setAccessible(true);
        $db = $dbProperty->getValue($billingService);
        
        $customerOptions = $db->query("SELECT id, name FROM billing_customers ORDER BY name ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
    $query_end = microtime(true);
    
    echo "<p style='color: green;'><strong>SUCCESS:</strong> Customer options query executed in " . round(($query_end - $query_start) * 1000, 2) . " ms</p>";
    echo "<p>Found " . count($customerOptions) . " customers</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Customer options query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test session handling
echo "<h3>Session Handling Test:</h3>";
echo "<p>Session status: " . (session_status() == PHP_SESSION_ACTIVE ? "Active" : "Not Active") . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";

// Overall timing
$end_time = microtime(true);
$total_time = round(($end_time - $start_time) * 1000, 2);

echo "<h3>Performance Summary:</h3>";
echo "<p>Total execution time: {$total_time} ms</p>";

echo "<h3>Conclusion:</h3>";
if ($total_time < 5000) {
    echo "<p style='color: green;'><strong>GOOD:</strong> All tests completed quickly. The issue might be related to:</p>";
    echo "<ul>";
    echo "<li>Server timeout settings</li>";
    echo "<li>Network issues between web server and database</li>";
    echo "<li>Large dataset causing slow rendering</li>";
    echo "<li>PHP memory usage issues with large datasets</li>";
    echo "</ul>";
} else {
    echo "<p style='color: orange;'><strong>WARNING:</strong> Tests took longer than expected ({$total_time} ms). There might be performance issues.</p>";
}

echo "<h3>Recommendations:</h3>";
echo "<ol>";
echo "<li>Check your web server error logs for any PHP errors</li>";
echo "<li>Try accessing the page with a specific invoice ID: ?hotspot=billing-invoices&id=1</li>";
echo "<li>Check if there are too many invoices causing slow loading</li>";
echo "<li>Increase PHP max_execution_time to 60 seconds if needed</li>";
echo "<li>Check server resources (CPU, memory usage)</li>";
echo "</ol>";
?>