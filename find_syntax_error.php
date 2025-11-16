<?php
// Syntax Error Detection Script
echo "<h2>Syntax Error Detection</h2>";
echo "<p>Finding the source of syntax error: 'unexpected token \";\", expecting \"]\"'</p>";

// Files to check
$files_to_check = [
    './include/db_config.php',
    './lib/BillingService.class.php',
    './billing/invoices.php',
    './agent-admin/billing_invoices.php',
    './include/config.php',
    './lib/Agent.class.php'
];

echo "<h3>File Syntax Check:</h3>";

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p>Checking: <strong>$file</strong> ... ";
        
        // Use PHP's built-in syntax check
        $output = [];
        $return_code = 0;
        exec("php -l " . escapeshellarg($file), $output, $return_code);
        
        if ($return_code === 0) {
            echo "<span style='color: green;'>OK</span></p>";
        } else {
            echo "<span style='color: red;'>ERROR</span></p>";
            echo "<pre style='background: #f8f8f8; padding: 10px; border: 1px solid #ddd;'>";
            foreach ($output as $line) {
                echo htmlspecialchars($line) . "\n";
            }
            echo "</pre>";
        }
    } else {
        echo "<p>File not found: <strong>$file</strong></p>";
    }
}

// Manual syntax check for common issues
echo "<h3>Manual Syntax Check:</h3>";

function checkSyntaxIssues($file) {
    if (!file_exists($file)) {
        return "File not found";
    }
    
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    
    $issues = [];
    
    // Check for common syntax issues
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $line_num = $i + 1;
        
        // Look for array syntax issues
        if (preg_match('/\[\s*[^]]*\s*;\s*\]/', $line)) {
            $issues[] = "Line $line_num: Possible malformed array syntax";
        }
        
        // Look for missing commas in arrays
        if (preg_match('/\[[^\]]*"[^"]*"[^\]]*"[^"]*"[^\]]*\]/', $line) && 
            !preg_match('/"[^"]*"\s*,\s*"[^"]*"/', $line)) {
            $issues[] = "Line $line_num: Possible missing comma in array";
        }
        
        // Look for unmatched brackets
        $open_brackets = substr_count($line, '[');
        $close_brackets = substr_count($line, ']');
        if ($open_brackets != $close_brackets) {
            $issues[] = "Line $line_num: Unmatched brackets []";
        }
    }
    
    return $issues;
}

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $issues = checkSyntaxIssues($file);
        if (!empty($issues)) {
            echo "<p><strong>$file</strong>:</p>";
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li style='color: orange;'>$issue</li>";
            }
            echo "</ul>";
        }
    }
}

echo "<h3>Recommendations:</h3>";
echo "<ol>";
echo "<li>Check the file(s) reported with syntax errors above</li>";
echo "<li>Look for missing commas in arrays, especially around line(s) with the error</li>";
echo "<li>Check for unmatched brackets [ ]</li>";
echo "<li>Look for semicolons inside array definitions</li>";
echo "<li>If the command-line check didn't work, manually review the files for syntax issues</li>";
echo "</ol>";

echo "<h3>Common Causes of This Error:</h3>";
echo "<ul>";
echo "<li>Missing comma between array elements: ['item1' 'item2'] instead of ['item1', 'item2']</li>";
echo "<li>Semicolon inside array: ['item1'; 'item2'] instead of ['item1', 'item2']</li>";
echo "<li>Unmatched brackets in complex array structures</li>";
echo "<li>String concatenation issues with quotes</li>";
echo "</ul>";
?>