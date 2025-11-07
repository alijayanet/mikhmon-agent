<?php
/*
 * Process Order - Create Transaction
 * Step 1: Validate data and create transaction record
 * Step 2: Redirect to payment selection
 */

session_start();

include_once('../include/db_config.php');
include_once('../lib/PublicPayment.class.php');

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

$agent_code = $_POST['agent_code'] ?? '';
$profile_id = $_POST['profile_id'] ?? 0;
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$customer_email = trim($_POST['customer_email'] ?? '');

// Validation
$errors = [];

if (empty($agent_code)) {
    $errors[] = 'Agent code required';
}

if (empty($profile_id)) {
    $errors[] = 'Package not selected';
}

if (empty($customer_name)) {
    $errors[] = 'Name is required';
}

if (empty($customer_phone)) {
    $errors[] = 'WhatsApp number is required';
}

// Validate phone format
if (!empty($customer_phone)) {
    $customer_phone = preg_replace('/[^0-9]/', '', $customer_phone);
    
    if (strlen($customer_phone) < 10) {
        $errors[] = 'Invalid phone number';
    }
    
    // Convert to 62xxx format
    if (substr($customer_phone, 0, 1) == '0') {
        $customer_phone = '62' . substr($customer_phone, 1);
    } elseif (substr($customer_phone, 0, 2) != '62') {
        $customer_phone = '62' . $customer_phone;
    }
}

if (!empty($errors)) {
    error_log("Process Order - Validation errors: " . implode(', ', $errors));
    $_SESSION['error'] = implode(', ', $errors);
    header('Location: index.php?agent=' . urlencode($agent_code));
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get agent data
    $stmt = $conn->prepare("SELECT * FROM agents WHERE agent_code = :code AND status = 'active'");
    $stmt->execute([':code' => $agent_code]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception('Agent not found or inactive');
    }
    
    // Get pricing data
    $stmt = $conn->prepare("SELECT * FROM agent_profile_pricing WHERE id = :id AND agent_id = :agent_id AND is_active = 1");
    $stmt->execute([':id' => $profile_id, ':agent_id' => $agent['id']]);
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pricing) {
        throw new Exception('Package not found or inactive');
    }
    
    // Check if payment gateway is configured
    $payment = new PublicPayment();
    $gateway = $payment->getActiveGateway();
    
    // Debug log
    error_log("Process Order - Gateway check: " . json_encode($gateway));
    
    if (!$gateway) {
        error_log("Process Order - No active gateway found");
        throw new Exception('Payment gateway not configured. Please contact administrator.');
    }
    
    // Generate transaction ID
    $transaction_id = 'TRX-' . time() . '-' . rand(10000, 99999);
    
    // Calculate total amount (price + admin fee if any)
    $price = $pricing['price'];
    $admin_fee = 0; // Will be calculated based on payment method
    $total_amount = $price + $admin_fee;
    
    // Create transaction record
    $stmt = $conn->prepare("INSERT INTO public_sales 
                           (transaction_id, agent_id, profile_id, customer_name, customer_phone, customer_email,
                            profile_name, price, admin_fee, total_amount, gateway_name, status, ip_address, user_agent)
                           VALUES 
                           (:transaction_id, :agent_id, :profile_id, :customer_name, :customer_phone, :customer_email,
                            :profile_name, :price, :admin_fee, :total_amount, :gateway_name, 'pending', :ip, :ua)");
    
    $stmt->execute([
        ':transaction_id' => $transaction_id,
        ':agent_id' => $agent['id'],
        ':profile_id' => $pricing['id'],
        ':customer_name' => $customer_name,
        ':customer_phone' => $customer_phone,
        ':customer_email' => $customer_email,
        ':profile_name' => $pricing['profile_name'],
        ':price' => $price,
        ':admin_fee' => $admin_fee,
        ':total_amount' => $total_amount,
        ':gateway_name' => $gateway['gateway_name'],
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    $sale_id = $conn->lastInsertId();
    
    // Store in session for payment page
    $_SESSION['pending_transaction'] = [
        'id' => $sale_id,
        'transaction_id' => $transaction_id,
        'agent_code' => $agent_code,
        'agent_name' => $agent['agent_name'],
        'package_name' => $pricing['display_name'],
        'price' => $price,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'customer_email' => $customer_email,
        'gateway' => $gateway['gateway_name']
    ];
    
    error_log("Process Order - Session stored: " . json_encode($_SESSION['pending_transaction']));
    error_log("Process Order - Redirecting to payment_select.php");
    
    // Redirect to payment selection
    header('Location: payment_select.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php?agent=' . urlencode($agent_code));
    exit;
}
