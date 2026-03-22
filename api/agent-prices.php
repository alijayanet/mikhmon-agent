<?php
/*
 * API Endpoint untuk Agent Prices - FIXED VERSION
 * Menangani AJAX requests untuk set/update harga agent
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
$includeDebug = $isLocal;

// Set error handling BEFORE any other code
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Wrap EVERYTHING in try-catch
try {
    if (!$isLocal && !isset($_SESSION['mikhmon'])) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access Denied']);
        exit;
    }

    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        http_response_code(405);
        $resp = ['success' => false, 'message' => 'Method Not Allowed'];
        if ($includeDebug) {
            $resp['debug'] = 'Not POST';
        }
        echo json_encode($resp);
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
            $resp = ['success' => false, 'message' => "File $name not found"];
            if ($includeDebug) {
                $resp['debug'] = "Path: $file";
                $resp['baseDir'] = $baseDir;
            }
            echo json_encode($resp);
            exit;
        }
        
        try {
            include_once($file);
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            $resp = ['success' => false, 'message' => "Error including $name"];
            if ($includeDebug) {
                $resp['debug'] = $e->getMessage();
            }
            echo json_encode($resp);
            exit;
        }
    }

    // Read JSON input
    $jsonInput = file_get_contents('php://input');
    
    if (empty($jsonInput)) {
        ob_clean();
        http_response_code(400);
        $resp = ['success' => false, 'message' => 'Empty request body'];
        if ($includeDebug) {
            $resp['debug'] = 'No JSON input';
        }
        echo json_encode($resp);
        exit;
    }
    
    $request = json_decode($jsonInput, true);
    
    if (!$request) {
        ob_clean();
        http_response_code(400);
        $resp = ['success' => false, 'message' => 'Invalid JSON input'];
        if ($includeDebug) {
            $resp['debug'] = 'JSON decode failed: ' . json_last_error_msg();
            $resp['raw_input'] = substr($jsonInput, 0, 100);
        }
        echo json_encode($resp);
        exit;
    }

    $action = $request['action'] ?? null;
    
    if (!$action) {
        ob_clean();
        http_response_code(400);
        $resp = ['success' => false, 'message' => 'No action specified'];
        if ($includeDebug) {
            $resp['debug'] = 'Missing action';
        }
        echo json_encode($resp);
        exit;
    }

    // Initialize Agent class
    try {
        $agent = new Agent();
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        $resp = ['success' => false, 'message' => 'Failed to initialize Agent class'];
        if ($includeDebug) {
            $resp['debug'] = $e->getMessage();
        }
        echo json_encode($resp);
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
            $resp = ['success' => false, 'message' => 'Agent dan Profile harus dipilih!'];
            if ($includeDebug) {
                $resp['debug'] = "agentId: $agentId, profileName: $profileName";
            }
            echo json_encode($resp);
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
                $resp = [
                    'success' => false,
                    'message' => $result['message'] ?? 'Gagal menyimpan harga'
                ];
                if ($includeDebug) {
                    $resp['debug'] = $result;
                }
                echo json_encode($resp);
            }
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            $resp = ['success' => false, 'message' => 'Error saat menyimpan harga'];
            if ($includeDebug) {
                $resp['debug'] = $e->getMessage();
            }
            echo json_encode($resp);
        }
        exit;
    }

    // Unknown action
    ob_clean();
    http_response_code(400);
    $resp = ['success' => false, 'message' => 'Unknown action'];
    if ($includeDebug) {
        $resp['debug'] = "action: $action";
    }
    echo json_encode($resp);
    exit;

} catch (Throwable $e) {
    // Catch ALL errors including Fatal Errors (PHP 7+)
    ob_clean();
    http_response_code(500);
    $resp = [
        'success' => false,
        'message' => 'Server Error'
    ];
    if ($includeDebug) {
        $resp['debug'] = $e->getMessage();
        $resp['file'] = $e->getFile();
        $resp['line'] = $e->getLine();
        $resp['trace'] = $e->getTraceAsString();
    }
    echo json_encode($resp);
    exit;
}
