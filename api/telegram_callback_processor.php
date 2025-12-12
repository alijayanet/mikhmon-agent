<?php
/*
 * SOLUTION 3: Background Callback Processor
 * Process callback queries in background for instant response
 */

// Load required files
require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../include/telegram_config.php');

/**
 * Process callback queue in background
 */
function processCallbackQueue() {
    $queueFile = __DIR__ . '/../cache/telegram_callback_queue.json';
    
    if (!file_exists($queueFile)) {
        return;
    }
    
    $queue = json_decode(file_get_contents($queueFile), true) ?: [];
    
    foreach ($queue as $index => $item) {
        try {
            $chatId = $item['chat_id'];
            $data = $item['data'];
            $timestamp = $item['timestamp'];
            
            // Skip old items (older than 30 seconds)
            if ((time() - $timestamp) > 30) {
                unset($queue[$index]);
                continue;
            }
            
            // Process the callback
            processCallbackData($chatId, $data);
            
            // Remove from queue
            unset($queue[$index]);
            
        } catch (Exception $e) {
            error_log("Error processing callback queue: " . $e->getMessage());
            unset($queue[$index]);
        }
    }
    
    // Save updated queue
    file_put_contents($queueFile, json_encode(array_values($queue)), LOCK_EX);
}

/**
 * Add callback to queue for background processing
 */
function addCallbackToQueue($chatId, $data) {
    $queueFile = __DIR__ . '/../cache/telegram_callback_queue.json';
    $cacheDir = dirname($queueFile);
    
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $queue = [];
    if (file_exists($queueFile)) {
        $queue = json_decode(file_get_contents($queueFile), true) ?: [];
    }
    
    $queue[] = [
        'chat_id' => $chatId,
        'data' => $data,
        'timestamp' => time()
    ];
    
    file_put_contents($queueFile, json_encode($queue), LOCK_EX);
}

/**
 * Process individual callback data
 */
function processCallbackData($chatId, $data) {
    $parts = explode(':', $data);
    $action = $parts[0];
    
    switch ($action) {
        case 'agent_buy':
            if (isset($parts[1])) {
                $profileName = $parts[1];
                // Load the main webhook functions
                require_once(__DIR__ . '/telegram_webhook.php');
                purchaseTelegramVoucher($chatId, $profileName, false);
            }
            break;
            
        case 'admin_buy':
            if (isset($parts[1])) {
                $profileName = $parts[1];
                require_once(__DIR__ . '/telegram_webhook.php');
                purchaseTelegramVoucher($chatId, $profileName, true);
            }
            break;
            
        case 'agent_quick':
            require_once(__DIR__ . '/telegram_webhook.php');
            showTelegramAgentQuickMenu($chatId);
            break;
            
        case 'agent_packages':
            require_once(__DIR__ . '/telegram_webhook.php');
            showTelegramAgentPackagesMenu($chatId);
            break;
    }
}

// If called directly, process the queue
if (php_sapi_name() === 'cli') {
    processCallbackQueue();
}
?>
