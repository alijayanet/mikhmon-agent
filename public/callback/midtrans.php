<?php
/*
 * Midtrans Payment Notification Handler
 * Handle payment notification from Midtrans
 */

// Log callback for debugging
$log_file = __DIR__ . '/../../logs/midtrans_callback_' . date('Y-m-d') . '.log';
$log_data = date('Y-m-d H:i:s') . " - Notification received\n";
$log_data .= "POST: " . json_encode($_POST) . "\n";
$log_data .= "---\n";
@file_put_contents($log_file, $log_data, FILE_APPEND);

include_once('../../include/db_config.php');
include_once('../../lib/PublicPayment.class.php');

// Get notification data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status_code' => '400', 'status_message' => 'Invalid JSON']);
    exit;
}

try {
    // Initialize payment gateway
    $payment = new PublicPayment('midtrans');
    
    // Get signature from data
    $signature = $data['signature_key'] ?? '';
    
    // Verify signature
    if (!$payment->verifyCallback($data, $signature)) {
        throw new Exception('Invalid signature');
    }
    
    // Get payment status
    $status = $payment->getPaymentStatus($data);
    
    // Get order ID (our transaction ID)
    $order_id = $data['order_id'] ?? '';
    
    if (empty($order_id)) {
        throw new Exception('Order ID not found');
    }
    
    // Get transaction by transaction_id
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM public_sales WHERE transaction_id = :trx_id");
    $stmt->execute([':trx_id' => $order_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Update transaction status
    $update_data = [
        'status' => $status,
        'callback_data' => $json,
        'payment_reference' => $data['transaction_id'] ?? $order_id
    ];
    
    if ($status == 'paid') {
        $update_data['paid_at'] = date('Y-m-d H:i:s', strtotime($data['transaction_time']));
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
    
    // Return success response (Midtrans format)
    http_response_code(200);
    echo json_encode([
        'status_code' => '200',
        'status_message' => 'Success'
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Midtrans notification error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'status_code' => '400',
        'status_message' => $e->getMessage()
    ]);
}
