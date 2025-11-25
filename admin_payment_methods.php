<?php
/*
 * Simple Admin - Payment Methods Management
 * Standalone admin page for payment methods
 */

session_start();

// Simple admin authentication
$admin_password = 'admin123'; // Change this password!

if (isset($_POST['admin_login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['payment_admin'] = true;
    } else {
        $login_error = 'Invalid password!';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['payment_admin']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if logged in
if (!isset($_SESSION['payment_admin'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Methods Admin</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">
        <style>
            body { background: #f4f4f4; padding: 50px 0; }
            .login-container { max-width: 400px; margin: 0 auto; }
            .card { border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .card-header { background: #3c8dbc; color: white; text-align: center; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-credit-card"></i> Payment Methods Admin
                </div>
                <div class="card-body">
                    <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger"><?= $login_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Admin Password:</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="admin_login" class="btn btn-primary btn-block">
                            <i class="fa fa-sign-in"></i> Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">Default password: admin123</small>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

include_once('include/db_config.php');

$conn = getDBConnection();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_login'])) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_method':
                $id = $_POST['id'];
                $admin_fee_value = $_POST['admin_fee_value'];
                $admin_fee_type = $_POST['admin_fee_type'];
                $min_amount = $_POST['min_amount'];
                $max_amount = $_POST['max_amount'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $stmt = $conn->prepare("UPDATE payment_methods SET 
                        admin_fee_value = ?, admin_fee_type = ?, min_amount = ?, max_amount = ?, is_active = ?
                        WHERE id = ?");
                    $stmt->execute([$admin_fee_value, $admin_fee_type, $min_amount, $max_amount, $is_active, $id]);
                    
                    $message = 'Payment method updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating payment method: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'bulk_update':
                // Quick update for common scenarios
                $updates = $_POST['updates'] ?? [];
                $success_count = 0;
                
                foreach ($updates as $id => $data) {
                    if (!empty($data['admin_fee_value'])) {
                        try {
                            $stmt = $conn->prepare("UPDATE payment_methods SET admin_fee_value = ? WHERE id = ?");
                            $stmt->execute([$data['admin_fee_value'], $id]);
                            $success_count++;
                        } catch (Exception $e) {
                            // Continue with other updates
                        }
                    }
                }
                
                $message = "Updated $success_count payment methods successfully!";
                $messageType = 'success';
                break;
        }
    }
}

// Get all payment methods
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY gateway_name, sort_order, id");
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by type for easier management
$grouped_methods = [];
foreach ($payment_methods as $method) {
    $type = $method['method_type'];
    if (!isset($grouped_methods[$type])) {
        $grouped_methods[$type] = [];
    }
    $grouped_methods[$type][] = $method;
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
<html>
<head>
    <title>Payment Methods Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background: #f4f4f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header-card {
            background: linear-gradient(135deg, #3c8dbc 0%, #2c6aa0 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header-card h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
            color: #333;
        }
        
        .method-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 15px 20px;
        }
        
        .method-item:last-child {
            border-bottom: none;
        }
        
        .method-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .method-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .method-icon {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #3c8dbc;
            color: white;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .method-name {
            font-weight: 600;
            color: #333;
        }
        
        .method-code {
            font-size: 0.85rem;
            color: #666;
        }
        
        .current-fee {
            font-weight: 600;
            color: #3c8dbc;
            font-size: 0.9rem;
        }
        
        .form-inline .form-control {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        
        .btn-update {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .btn-update:hover {
            background: #218838;
            color: white;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .quick-actions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .quick-actions h5 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .method-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .form-inline {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-inline .form-control {
                margin-right: 0;
                margin-bottom: 10px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fa fa-credit-card"></i> Payment Methods Management</h1>
                    <p class="mb-0">Manage payment fees and settings for your payment gateway</p>
                </div>
                <a href="?logout=1" class="btn btn-light">
                    <i class="fa fa-sign-out"></i> Logout
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?>">
            <i class="fa fa-<?= $messageType === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
            <?= htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="quick-actions">
            <h5><i class="fa fa-bolt"></i> Quick Actions</h5>
            <p>Common fee adjustments you mentioned:</p>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_update">
                <div class="row">
                    <?php foreach ($payment_methods as $method): ?>
                        <?php if ($method['method_code'] === 'QRIS'): ?>
                        <div class="col-md-4 mb-2">
                            <label class="small">QRIS Fee:</label>
                            <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                   class="form-control form-control-sm" placeholder="1500" value="<?= $method['admin_fee_value']; ?>">
                        </div>
                        <?php elseif ($method['method_code'] === 'BRIVA'): ?>
                        <div class="col-md-4 mb-2">
                            <label class="small">BRI VA Fee:</label>
                            <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                   class="form-control form-control-sm" placeholder="2500" value="<?= $method['admin_fee_value']; ?>">
                        </div>
                        <?php elseif ($method['method_code'] === 'OVO'): ?>
                        <div class="col-md-4 mb-2">
                            <label class="small">OVO Fee (%):</label>
                            <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                   class="form-control form-control-sm" placeholder="2.0" step="0.1" value="<?= $method['admin_fee_value']; ?>">
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-warning btn-sm">
                    <i class="fa fa-flash"></i> Quick Update
                </button>
            </form>
        </div>
        
        <?php foreach ($grouped_methods as $type => $methods): ?>
        <div class="card">
            <div class="card-header">
                <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>"></i>
                <?= $type_labels[$type] ?? ucfirst($type); ?> Methods
            </div>
            <div class="card-body p-0">
                <?php foreach ($methods as $method): ?>
                <div class="method-item">
                    <div class="method-header">
                        <div class="method-info">
                            <div class="method-icon">
                                <i class="fa <?= $method['icon'] ?? 'fa-credit-card'; ?>"></i>
                            </div>
                            <div>
                                <div class="method-name"><?= htmlspecialchars($method['method_name']); ?></div>
                                <div class="method-code"><?= htmlspecialchars($method['method_code']); ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="current-fee">
                                Current: <?= $method['admin_fee_type'] === 'percentage' ? $method['admin_fee_value'] . '%' : 'Rp ' . number_format($method['admin_fee_value']); ?>
                            </div>
                            <span class="status-badge <?= $method['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?= $method['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <form method="POST" class="form-inline">
                        <input type="hidden" name="action" value="update_method">
                        <input type="hidden" name="id" value="<?= $method['id']; ?>">
                        
                        <select name="admin_fee_type" class="form-control form-control-sm">
                            <option value="fixed" <?= $method['admin_fee_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed (Rp)</option>
                            <option value="percentage" <?= $method['admin_fee_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                        </select>
                        
                        <input type="number" name="admin_fee_value" class="form-control form-control-sm" 
                               value="<?= $method['admin_fee_value']; ?>" step="0.01" min="0" placeholder="Fee Value">
                        
                        <input type="number" name="min_amount" class="form-control form-control-sm" 
                               value="<?= $method['min_amount']; ?>" placeholder="Min Amount">
                        
                        <input type="number" name="max_amount" class="form-control form-control-sm" 
                               value="<?= $method['max_amount']; ?>" placeholder="Max Amount">
                        
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="is_active" class="form-check-input" 
                                   <?= $method['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label small">Active</label>
                        </div>
                        
                        <button type="submit" class="btn-update">
                            <i class="fa fa-save"></i> Update
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="card">
            <div class="card-body">
                <h5><i class="fa fa-info-circle text-info"></i> Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Fee Types:</h6>
                        <ul class="small">
                            <li><strong>Fixed:</strong> Fee tetap dalam Rupiah</li>
                            <li><strong>Percentage:</strong> Fee persentase dari total transaksi</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Examples:</h6>
                        <ul class="small">
                            <li>QRIS: Fixed 1500 = Fee Rp 1,500</li>
                            <li>OVO: Percentage 2.0 = Fee 2.0%</li>
                            <li>BRI VA: Fixed 2500 = Fee Rp 2,500</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
