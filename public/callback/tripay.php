<?php
/*
 * Tripay Payment Callback Handler
 * Handle payment notification from Tripay
 */

// Log callback for debugging
$log_file = __DIR__ . '/../../logs/tripay_callback_' . date('Y-m-d') . '.log';
$log_data = date('Y-m-d H:i:s') . " - Callback received\n";
$log_data .= "POST: " . json_encode($_POST) . "\n";
$log_data .= "GET: " . json_encode($_GET) . "\n";
$log_data .= "Headers: " . json_encode(getallheaders()) . "\n";
$log_data .= "---\n";
@file_put_contents($log_file, $log_data, FILE_APPEND);

include_once('../../include/db_config.php');
include_once('../../lib/PublicPayment.class.php');

// Get callback data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Get signature from header
$headers = getallheaders();
$signature = $headers['X-Callback-Signature'] ?? '';

try {
    // Initialize payment gateway
    $payment = new PublicPayment('tripay');
    
    // Verify signature
    if (!$payment->verifyCallback($data, $signature)) {
        throw new Exception('Invalid signature');
    }
    
    // Get payment status
    $status = $payment->getPaymentStatus($data);
    
    // Get transaction by payment reference
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM public_sales WHERE payment_reference = :ref");
    $stmt->execute([':ref' => $data['reference']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    error_log("Tripay Callback - Processing payment: " . $data['reference']);
    error_log("Tripay Callback - Status: " . $status);
    error_log("Tripay Callback - Transaction found: " . $transaction['transaction_id']);
    
    // Update transaction status
    $update_data = [
        'status' => $status,
        'callback_data' => $json
    ];
    
    if ($status == 'paid') {
        // Handle paid_at - could be timestamp or datetime string
        if (isset($data['paid_at'])) {
            if (is_numeric($data['paid_at'])) {
                // If it's a timestamp
                $update_data['paid_at'] = date('Y-m-d H:i:s', $data['paid_at']);
            } else {
                // If it's already a datetime string
                $update_data['paid_at'] = $data['paid_at'];
            }
        } else {
            // If no paid_at provided, use current time
            $update_data['paid_at'] = date('Y-m-d H:i:s');
        }
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
        error_log("Tripay Callback - Generating voucher for transaction: " . $transaction['transaction_id']);
        
        // Include voucher generation
        include_once('../../lib/VoucherGenerator.class.php');
        
        $generator = new VoucherGenerator();
        $result = $generator->generateAndSend($transaction['id']);
        
        if ($result['success']) {
            error_log("Tripay Callback - Voucher generated successfully: " . $result['voucher_code']);
        } else {
            // Log error but don't fail callback
            error_log("Tripay Callback - Voucher generation failed: " . $result['message']);
        }
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Log error
    error_log("Tripay callback error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
