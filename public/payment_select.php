<?php
/*
 * Payment Method Selection
 * Display available payment methods and create payment
 */

session_start();

// Debug session
error_log("Payment Select - Session data: " . json_encode($_SESSION));

// Check if transaction exists in session
if (!isset($_SESSION['pending_transaction'])) {
    error_log("Payment Select - No pending transaction in session");
    $_SESSION['error'] = 'No pending transaction found. Please try again.';
    header('Location: index.php');
    exit;
}

$transaction = $_SESSION['pending_transaction'];
error_log("Payment Select - Transaction data: " . json_encode($transaction));

include_once('../include/db_config.php');
include_once('../lib/PublicPayment.class.php');

// Get theme from MikhMon config
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

try {
    // Clear any previous error
    unset($_SESSION['error']);
    
    $payment = new PublicPayment($transaction['gateway']);
    $methods = $payment->getPaymentMethods($transaction['price']);
    
    if (empty($methods)) {
        throw new Exception('No payment methods available for amount Rp ' . number_format($transaction['price'], 0, ',', '.'));
    }
    
    // Group by type
    $grouped_methods = [];
    foreach ($methods as $method) {
        $type = $method['method_type'];
        if (!isset($grouped_methods[$type])) {
            $grouped_methods[$type] = [];
        }
        $grouped_methods[$type][] = $method;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php?agent=' . urlencode($transaction['agent_code']));
    exit;
}

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    
    error_log("Payment Select - Processing payment method: " . $payment_method);
    error_log("Payment Select - Transaction: " . json_encode($transaction));
    
    try {
        $conn = getDBConnection();
        
        // Get method details for fee calculation
        $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE method_code = :code");
        $stmt->execute([':code' => $payment_method]);
        $method_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate admin fee - ensure integer for Tripay
        $admin_fee = 0;
        if ($method_data) {
            if ($method_data['admin_fee_type'] == 'percentage' || $method_data['admin_fee_type'] == 'percent') {
                $admin_fee = ceil(($transaction['price'] * $method_data['admin_fee_value']) / 100);
            } else {
                $admin_fee = (int)$method_data['admin_fee_value'];
            }
        }
        
        $total_amount = (int)$transaction['price'] + $admin_fee;
        
        error_log("Payment Select - Admin fee calculation: price=" . $transaction['price'] . ", fee=" . $admin_fee . ", total=" . $total_amount);
        
        // Update transaction with admin fee
        $stmt = $conn->prepare("UPDATE public_sales SET 
                               admin_fee = :admin_fee, 
                               total_amount = :total_amount,
                               payment_method = :payment_method
                               WHERE id = :id");
        $stmt->execute([
            ':admin_fee' => $admin_fee,
            ':total_amount' => $total_amount,
            ':payment_method' => $payment_method,
            ':id' => $transaction['id']
        ]);
        
        // Create payment with gateway
        $payment_data = [
            'payment_method' => $payment_method,
            'amount' => $total_amount,
            'customer_name' => $transaction['customer_name'],
            'customer_phone' => $transaction['customer_phone'],
            'customer_email' => $transaction['customer_email'],
            'product_name' => $transaction['package_name'],
            'callback_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/public/callback/' . $transaction['gateway'] . '.php',
            'return_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/public/payment_status.php?trx=' . $transaction['transaction_id']
        ];
        
        error_log("Payment Select - Creating payment with data: " . json_encode($payment_data));
        
        $result = $payment->createPayment($payment_data);
        
        error_log("Payment Select - Payment result: " . json_encode($result));
        
        if ($result['success']) {
            // Update transaction with payment details
            $stmt = $conn->prepare("UPDATE public_sales SET 
                                   payment_reference = :ref,
                                   payment_url = :url,
                                   qr_url = :qr,
                                   virtual_account = :va,
                                   expired_at = :expired,
                                   payment_channel = :channel
                                   WHERE id = :id");
            $stmt->execute([
                ':ref' => $result['payment_reference'],
                ':url' => $result['payment_url'] ?? null,
                ':qr' => $result['qr_url'] ?? null,
                ':va' => $result['virtual_account'] ?? null,
                ':expired' => $result['expired_at'],
                ':channel' => $payment_method,
                ':id' => $transaction['id']
            ]);
            
            // Store payment info in session
            $_SESSION['payment_info'] = $result;
            
            // Redirect to payment page or external URL
            if (!empty($result['payment_url'])) {
                header('Location: ' . $result['payment_url']);
            } else {
                header('Location: payment_instructions.php');
            }
            exit;
        } else {
            throw new Exception($result['message'] ?? 'Failed to create payment');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$type_labels = [
    'qris' => 'QRIS',
    'va' => 'Virtual Account',
    'ewallet' => 'E-Wallet',
    'retail' => 'Retail Store'
];

$type_icons = [
    'qris' => 'fa-qrcode',
    'va' => 'fa-bank',
    'ewallet' => 'fa-mobile',
    'retail' => 'fa-shopping-cart'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Metode Pembayaran - <?= htmlspecialchars($transaction['agent_name']); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    
    <style>
        body {
            background: var(--bg-color, #f4f4f4);
            min-height: 100vh;
            padding: 20px 10px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .payment-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .order-summary {
            background: var(--card-bg, white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .order-summary h3 {
            color: var(--primary-color, #3c8dbc);
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-light, #f0f0f0);
            color: var(--text-color, #333);
        }
        
        .order-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--success-color, #28a745);
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--border-light, #f0f0f0);
        }
        
        .payment-section {
            background: var(--card-bg, white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .payment-section h4 {
            color: var(--primary-color, #3c8dbc);
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .payment-section h4 i {
            margin-right: 8px;
            width: 20px;
        }
        
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .payment-methods-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .payment-container {
                padding: 0 10px;
            }
            
            .order-summary, .payment-section {
                padding: 15px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .payment-methods-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .payment-method {
            border: 2px solid var(--border-color, #ddd);
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--card-bg, white);
            color: var(--text-color, #333);
            min-height: 60px;
        }
        
        .payment-method:hover {
            border-color: var(--primary-color, #3c8dbc);
            background: var(--hover-bg, #f8f9fa);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .payment-method.selected {
            border-color: var(--primary-color, #3c8dbc);
            background: var(--selected-bg, #e3f2fd);
            box-shadow: 0 4px 12px rgba(60, 141, 188, 0.2);
        }
        
        .payment-method input[type="radio"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .payment-method .method-info {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 12px;
        }
        
        .payment-method .method-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--icon-bg, #f8f9fa);
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .payment-method .method-details {
            flex: 1;
        }
        
        .payment-method input[type="radio"] {
            margin: 0;
            transform: scale(1.3);
            accent-color: var(--primary-color, #3c8dbc);
        }
        
        .btn-pay {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            background: var(--primary-color, #3c8dbc);
            color: white;
            border: none;
            border-radius: 10px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-pay:hover:not(:disabled) {
            background: var(--primary-dark, #2c6aa0);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 141, 188, 0.3);
        }
        
        .btn-pay:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--disabled-color, #ccc);
        }
        
        /* Dark theme support */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #1a1a1a;
                --card-bg: #2d2d2d;
                --text-color: #ffffff;
                --text-muted: #cccccc;
                --border-color: #404040;
                --border-light: #353535;
                --hover-bg: #3a3a3a;
                --selected-bg: #1e3a5f;
                --icon-bg: #404040;
            }
        }
        
        @media (max-width: 768px) {
            .payment-method {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .payment-method .method-fee {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    
    <div class="payment-container">
        
        <!-- Order Summary -->
        <div class="order-summary">
            <h3><i class="fa fa-shopping-cart"></i> Ringkasan Pesanan</h3>
            
            <div class="order-item">
                <span>Paket</span>
                <strong><?= htmlspecialchars($transaction['package_name']); ?></strong>
            </div>
            
            <div class="order-item">
                <span>Harga</span>
                <span>Rp <?= number_format($transaction['price'], 0, ',', '.'); ?></span>
            </div>
            
            <div class="order-item" id="admin-fee-row" style="display: none;">
                <span>Biaya Admin</span>
                <span id="admin-fee-amount">Rp 0</span>
            </div>
            
            <div class="order-item">
                <span>Total Bayar</span>
                <span id="total-amount">Rp <?= number_format($transaction['price'], 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Payment Methods -->
        <form method="POST" action="" id="paymentForm">
            
            <?php foreach ($grouped_methods as $type => $type_methods): ?>
            <div class="payment-section">
                <h4>
                    <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>"></i>
                    <?= $type_labels[$type] ?? ucfirst($type); ?>
                </h4>
                
                <div class="payment-methods-grid">
                    <?php foreach ($type_methods as $method): ?>
                    <label class="payment-method" data-fee-type="<?= $method['admin_fee_type']; ?>" data-fee-value="<?= $method['admin_fee_value']; ?>">
                        <div class="method-info">
                            <input type="radio" name="payment_method" value="<?= htmlspecialchars($method['method_code']); ?>" required>
                            <div class="method-icon">
                                <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>" style="font-size: 24px; color: var(--primary-color, #3c8dbc);"></i>
                            </div>
                            <div class="method-details">
                                <div class="method-name" style="font-weight: 600; color: var(--text-color, #333); margin-bottom: 4px;">
                                    <?= htmlspecialchars($method['method_name']); ?>
                                </div>
                                <div class="method-fee" style="font-size: 12px; color: var(--text-muted, #666);">
                                    <?php if ($method['admin_fee_value'] > 0): ?>
                                        <?php if ($method['admin_fee_type'] == 'percentage'): ?>
                                            +<?= $method['admin_fee_value']; ?>%
                                        <?php else: ?>
                                            +Rp <?= number_format($method['admin_fee_value'], 0, ',', '.'); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--success-color, #28a745);">Gratis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-pay" id="btnPay" disabled>
                <i class="fa fa-lock"></i> Bayar Sekarang
            </button>
            
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php?agent=<?= urlencode($transaction['agent_code']); ?>" style="color: white;">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>
        
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    
    <script>
    const basePrice = <?= $transaction['price']; ?>;
    
    // Handle payment method selection
    $('.payment-method').click(function() {
        $('.payment-method').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
        
        // Calculate admin fee
        const feeType = $(this).data('fee-type');
        const feeValue = parseFloat($(this).data('fee-value'));
        
        let adminFee = 0;
        if (feeType === 'percent') {
            adminFee = (basePrice * feeValue) / 100;
        } else {
            adminFee = feeValue;
        }
        
        const totalAmount = basePrice + adminFee;
        
        // Update display
        if (adminFee > 0) {
            $('#admin-fee-row').show();
            $('#admin-fee-amount').text('Rp ' + adminFee.toLocaleString('id-ID'));
        } else {
            $('#admin-fee-row').hide();
        }
        
        $('#total-amount').text('Rp ' + totalAmount.toLocaleString('id-ID'));
        
        // Enable pay button
        $('#btnPay').prop('disabled', false);
    });
    
    // Form submission
    $('#paymentForm').submit(function() {
        $('#btnPay').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
    });
    </script>
    
</body>
</html>
