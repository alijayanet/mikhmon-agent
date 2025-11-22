<?php
/*
 * API Endpoint untuk Agent Prices - FIXED VERSION
 * Menangani AJAX requests untuk set/update harga agent
 */

// Start output buffering to catch any unwanted output
ob_start();

// Set error handling BEFORE any other code
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Wrap EVERYTHING in try-catch
try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed', 'debug' => 'Not POST']);
        exit;
    }

    // Get base directory
    $baseDir = dirname(__DIR__);
    
    // Check and include files one by one with error checking
    $requiredFiles = [
        'db_config' => $baseDir . '/include/db_config.php',
        'config' => $baseDir . '/include/config.php',
        'Agent' => $baseDir . '/lib/Agent.class.php'
    ];
    
    foreach ($requiredFiles as $name => $file) {
        if (!file_exists($file)) {
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => "File $name not found",
                'debug' => "Path: $file",
                'baseDir' => $baseDir
            ]);
            exit;
        }
        
        try {
            include_once($file);
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => "Error including $name",
                'debug' => $e->getMessage()
            ]);
            exit;
        }
    }

    // Read JSON input
    $jsonInput = file_get_contents('php://input');
    
    if (empty($jsonInput)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Empty request body', 'debug' => 'No JSON input']);
        exit;
    }
    
    $request = json_decode($jsonInput, true);
    
    if (!$request) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid JSON input',
            'debug' => 'JSON decode failed: ' . json_last_error_msg(),
            'raw_input' => substr($jsonInput, 0, 100)
        ]);
        exit;
    }

    $action = $request['action'] ?? null;
    
    if (!$action) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No action specified', 'debug' => 'Missing action']);
        exit;
    }

    // Initialize Agent class
    try {
        $agent = new Agent();
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to initialize Agent class',
            'debug' => $e->getMessage()
        ]);
        exit;
    }

    // Handle create/update price
    if ($action === 'create') {
        $agentId = $request['agent_id'] ?? null;
        $profileName = $request['profile_name'] ?? null;
        $buyPrice = isset($request['buy_price']) ? floatval($request['buy_price']) : 0;
        $sellPrice = isset($request['sell_price']) ? floatval($request['sell_price']) : 0;

        if (!$agentId || !$profileName) {
            ob_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Agent dan Profile harus dipilih!',
                'debug' => "agentId: $agentId, profileName: $profileName"
            ]);
            exit;
        }

        try {
            $result = $agent->setAgentPrice($agentId, $profileName, $buyPrice, $sellPrice);
            
            if ($result['success']) {
                ob_clean();
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Harga berhasil diset!']);
            } else {
                ob_clean();
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => $result['message'] ?? 'Gagal menyimpan harga',
                    'debug' => $result
                ]);
            }
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error saat menyimpan harga',
                'debug' => $e->getMessage()
            ]);
        }
        exit;
    }

    // Unknown action
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action', 'debug' => "action: $action"]);
    exit;

} catch (Throwable $e) {
    // Catch ALL errors including Fatal Errors (PHP 7+)
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server Error',
        'debug' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
