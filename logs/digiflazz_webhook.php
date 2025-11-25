<?php
/**
 * Digiflazz Webhook Endpoint
 * Menangani callback status transaksi dari Digiflazz.
 */

declare(strict_types=1);

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/DigiflazzClient.class.php');
require_once(__DIR__ . '/../lib/WhatsAppNotification.class.php');

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

$logFile = __DIR__ . '/../logs/digiflazz_webhook.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}

$logEntry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $rawInput ?: '');
@file_put_contents($logFile, $logEntry, FILE_APPEND);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}


$eventData = $payload['data'] ?? $payload;

$refId = $eventData['ref_id'] ?? null;
$status = strtolower($eventData['status'] ?? '');
$message = $eventData['message'] ?? '';
$serialNumber = $eventData['serial_number'] ?? ($eventData['sn'] ?? '');
$buyerSku = $eventData['buyer_sku_code'] ?? ($eventData['code'] ?? '');
$customerNo = $eventData['target'] ?? ($eventData['customer_no'] ?? '');
$buyerLastSaldo = $eventData['buyer_last_saldo'] ?? null;

$digiflazzClient = null;
$digiflazzSettings = [];

try {
    $digiflazzClient = new DigiflazzClient();
    $digiflazzSettings = $digiflazzClient->getSettings();
} catch (Exception $e) {
    $digiflazzSettings = [];
}

$webhookSecret = trim($digiflazzSettings['webhook_secret'] ?? '');
$receivedSignature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$signatureVerified = false;
$expectedSignature = null;

if (!empty($webhookSecret) && !empty($receivedSignature)) {
    $expectedSignature = 'sha1=' . hash_hmac('sha1', $rawInput, $webhookSecret);
    if (!hash_equals($expectedSignature, $receivedSignature)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid signature']);
        exit;
    }
    $signatureVerified = true;
}

if (!$refId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ref_id']);
    exit;
}

try {
    if (!$signatureVerified) {
        if (!$digiflazzClient) {
            $digiflazzClient = new DigiflazzClient();
            $digiflazzSettings = $digiflazzClient->getSettings();
        }

        if (empty($digiflazzSettings['username']) || empty($digiflazzSettings['api_key'])) {
            throw new Exception('Digiflazz credentials not configured');
        }

        $fallbackSign = $payload['sign'] ?? '';
        $expectedSign = md5($digiflazzSettings['username'] . $digiflazzSettings['api_key'] . $refId);
        if (!hash_equals($expectedSign, $fallbackSign)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid signature']);
            exit;
        }
    }

    $db = getDBConnection();
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT * FROM digiflazz_transactions WHERE ref_id = :ref LIMIT 1 FOR UPDATE');
    $stmt->execute([':ref' => $refId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        // Tidak ada transaksi yang cocok, tetap balas sukses agar Digiflazz tidak retry terus
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Ref not found, ignored']);
        exit;
    }

    $update = $db->prepare('UPDATE digiflazz_transactions SET status = :status, message = :message, serial_number = :serial, response = :response, updated_at = NOW() WHERE id = :id');

    $existingResponse = json_decode($transaction['response'] ?? '[]', true);
    if (!is_array($existingResponse)) {
        $existingResponse = [];
    }
    $existingResponse['webhook'] = $payload;

    $finalStatus = $status ?: ($transaction['status'] ?? 'pending');

    $update->execute([
        ':status' => $finalStatus,
        ':message' => $message,
        ':serial' => $serialNumber ?: ($transaction['serial_number'] ?? ''),
        ':response' => json_encode($existingResponse),
        ':id' => $transaction['id']
    ]);

    $normalizedStatus = strtolower($finalStatus);
    $wasNotified = (int)($transaction['whatsapp_notified'] ?? 0);
    $successStatuses = ['success', 'sukses', 'berhasil', 'done', 'ok', 'selesai'];

    // Check if we should send notification (only if not already notified and status is success)
    if ($transaction['agent_id'] && $wasNotified === 0 && in_array($normalizedStatus, $successStatuses, true)) {
        $productName = $transaction['buyer_sku_code'] ?? '-';
        
        try {
            $productStmt = $db->prepare('SELECT product_name FROM digiflazz_products WHERE buyer_sku_code = :sku LIMIT 1');
            $productStmt->execute([':sku' => $transaction['buyer_sku_code']]);
            $productRow = $productStmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($productRow['product_name'])) {
                $productName = $productRow['product_name'];
            }
        } catch (Throwable $ignored) {
            // Ignore lookup failures, fallback to SKU
        }
        
        $notificationPayload = [
            'product_name' => $productName,
            'customer_no' => $customerNo ?: ($transaction['customer_no'] ?? '-'),
            'customer_name' => $transaction['customer_name'] ?? '',
            'status' => $finalStatus,
            'message' => $message ?: ($transaction['message'] ?? ''),
            'ref_id' => $refId,
            'serial_number' => $serialNumber ?: ($transaction['serial_number'] ?? ''),
            'price' => (int)($transaction['sell_price'] ?? $transaction['price'] ?? 0)
        ];
        
        // Send notification to agent
        $notifier = new WhatsAppNotification();
        $agentNotified = $notifier->notifyDigiflazzSuccess((int)$transaction['agent_id'], $notificationPayload, null, $buyerLastSaldo);
        
        // Send notification to customer if customer phone is available
        $customerPhone = $transaction['customer_no'] ?? '';
        if (!empty($customerPhone)) {
            $notifier->notifyCustomerDigiflazzSuccess($customerPhone, $notificationPayload);
        }
        
        if ($agentNotified) {
            $flagStmt = $db->prepare('UPDATE digiflazz_transactions SET whatsapp_notified = 1 WHERE id = :id');
            $flagStmt->execute([':id' => $transaction['id']]);
        }
    }

    $agentId = (int)$transaction['agent_id'];
    $amount = (int)$transaction['price'];
    $shouldRefund = in_array($status, ['gagal', 'failed', 'cancelled', 'refunded'], true);
    $refunded = false;

    if ($shouldRefund && $amount > 0) {
        $refundCheck = $db->prepare("SELECT id FROM agent_transactions WHERE agent_id = :agent AND transaction_type = 'topup' AND description LIKE :desc LIMIT 1");
        $refundCheck->execute([
            ':agent' => $agentId,
            ':desc' => '%' . $refId . '%'
        ]);

        if (!$refundCheck->fetch()) {
            // Update balance langsung menggunakan koneksi yang sama
            $balanceStmt = $db->prepare('SELECT balance FROM agents WHERE id = :id FOR UPDATE');
            $balanceStmt->execute([':id' => $agentId]);
            $agentRow = $balanceStmt->fetch(PDO::FETCH_ASSOC);

            if ($agentRow) {
                $balanceBefore = (int)$agentRow['balance'];
                $balanceAfter = $balanceBefore + $amount;

                $updateBalance = $db->prepare('UPDATE agents SET balance = :balance WHERE id = :id');
                $updateBalance->execute([
                    ':balance' => $balanceAfter,
                    ':id' => $agentId
                ]);

                $insertTxn = $db->prepare('INSERT INTO agent_transactions (agent_id, transaction_type, amount, balance_before, balance_after, description, created_by) VALUES (:agent_id, :type, :amount, :before, :after, :description, :created_by)');
                $insertTxn->execute([
                    ':agent_id' => $agentId,
                    ':type' => 'topup',
                    ':amount' => $amount,
                    ':before' => $balanceBefore,
                    ':after' => $balanceAfter,
                    ':description' => 'Refund Digiflazz ' . $refId,
                    ':created_by' => 'system'
                ]);

                $refunded = true;
            }
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Webhook processed',
        'status' => $status,
        'refunded' => $refunded
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
