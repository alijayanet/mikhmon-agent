<?php
/*
 * Xendit Payment Webhook Handler
 * Handle payment notification from Xendit
 */

// Log callback for debugging
$log_file = __DIR__ . '/../../logs/xendit_callback_' . date('Y-m-d') . '.log';
$log_data = date('Y-m-d H:i:s') . " - Webhook received\n";
$log_data .= "POST: " . json_encode($_POST) . "\n";
$log_data .= "Headers: " . json_encode(getallheaders()) . "\n";
$log_data .= "---\n";
@file_put_contents($log_file, $log_data, FILE_APPEND);

include_once('../../include/db_config.php');
include_once('../../lib/PublicPayment.class.php');

// Get webhook data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Get callback token from header
$headers = getallheaders();
$callbackToken = $headers['X-Callback-Token'] ?? '';

try {
    // Initialize payment gateway
    $payment = new PublicPayment('xendit');
    
    // Verify callback token
    if (!$payment->verifyCallback($data, $callbackToken)) {
        throw new Exception('Invalid callback token');
    }
    
    // Get payment status
    $status = $payment->getPaymentStatus($data);
    
    // Get external ID (our transaction ID)
    $external_id = $data['external_id'] ?? '';
    
    if (empty($external_id)) {
        throw new Exception('External ID not found');
    }
    
    // Get transaction by transaction_id or payment_reference
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM public_sales 
                           WHERE transaction_id = :trx_id OR payment_reference = :ref");
    $stmt->execute([
        ':trx_id' => $external_id,
        ':ref' => $data['id'] ?? ''
    ]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Update transaction status
    $update_data = [
        'status' => $status,
        'callback_data' => $json
    ];
    
    if ($status == 'paid') {
        $paid_at = $data['paid_at'] ?? $data['updated'] ?? date('Y-m-d H:i:s');
        $update_data['paid_at'] = date('Y-m-d H:i:s', strtotime($paid_at));
    }
    
    $set_clause = [];
    foreach ($update_data as $key => $value) {
        $set_clause[] = "$key = :$key";
    }
    
    $sql = "UPDATE public_sales SET " . implode(', ', $set_clause) . " WHERE id = :id";
    $stmt = $conn->prepare($sql);
    
    $update_data['id'] = $transaction['id'];
    $stmt->execute($update_data);
    
    // If payment is successful, generate voucher
    if ($status == 'paid' && empty($transaction['voucher_code'])) {
        // Include voucher generation
        include_once('../../lib/VoucherGenerator.class.php');
        
        $generator = new VoucherGenerator();
        $result = $generator->generateAndSend($transaction['id']);
        
        if (!$result['success']) {
            // Log error but don't fail callback
            error_log("Voucher generation failed for transaction {$transaction['transaction_id']}: " . $result['message']);
        }
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Log error
    error_log("Xendit webhook error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
