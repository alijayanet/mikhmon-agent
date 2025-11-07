<?php
/*
 * Direct Order Page - Order specific profile directly
 * URL: /public/order.php?agent=AG001&profile=PROFILE_ID
 */

// Get parameters
$agent_code = $_GET['agent'] ?? '';
$profile_id = $_GET['profile'] ?? '';

if (empty($agent_code)) {
    die('Invalid agent code');
}

if (empty($profile_id)) {
    die('Invalid profile ID');
}

include_once('../include/db_config.php');

// Get theme from MikhMon config
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

try {
    $conn = getDBConnection();
    
    // Get agent data
    $stmt = $conn->prepare("SELECT * FROM agents WHERE agent_code = :code AND status = 'active'");
    $stmt->execute([':code' => $agent_code]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        die('Agent not found or inactive');
    }
    
    // Get specific profile pricing
    $stmt = $conn->prepare("SELECT * FROM agent_profile_pricing 
                           WHERE agent_id = :agent_id AND id = :profile_id AND is_active = 1");
    $stmt->execute([':agent_id' => $agent['id'], ':profile_id' => $profile_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        die('Profile not found or inactive');
    }
    
    // Get active payment gateways
    $stmt = $conn->query("SELECT * FROM payment_gateway_config WHERE is_active = 1");
    $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($gateways)) {
        die('Payment gateway not configured');
    }
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

// Color mapping for profiles
$colors = ['bg-blue', 'bg-green', 'bg-red', 'bg-yellow', 'bg-aqua', 'bg-orange'];
$profile_color = $colors[$profile['id'] % count($colors)];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= htmlspecialchars($profile['profile_name']); ?> - <?= htmlspecialchars($agent['agent_name']); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    
    <style>
        :root {
            --primary-color: <?= $themecolor; ?>;
            --primary-dark: <?= $themecolor; ?>dd;
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --text-muted: #7f8c8d;
            --border-color: #e1e8ed;
        }
        
        body {
            background: var(--bg-color);
            min-height: 100vh;
            padding: 20px 10px 40px 10px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
            margin: 0;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            width: 100%;
        }
        
        .header {
            text-align: center;
            background: var(--primary-color);
            color: white;
            margin: -20px -10px 30px -10px;
            padding: 30px 20px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }
        
        /* Profile Card - Highlighted */
        .profile-highlight {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            border-top: 4px solid;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .profile-highlight.bg-blue { border-top-color: #3c8dbc; }
        .profile-highlight.bg-green { border-top-color: #00a65a; }
        .profile-highlight.bg-red { border-top-color: #dd4b39; }
        .profile-highlight.bg-yellow { border-top-color: #f39c12; }
        .profile-highlight.bg-aqua { border-top-color: #00c0ef; }
        .profile-highlight.bg-orange { border-top-color: #ff851b; }
        
        .profile-highlight .icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .profile-highlight.bg-blue .icon { color: #3c8dbc; }
        .profile-highlight.bg-green .icon { color: #00a65a; }
        .profile-highlight.bg-red .icon { color: #dd4b39; }
        .profile-highlight.bg-yellow .icon { color: #f39c12; }
        .profile-highlight.bg-aqua .icon { color: #00c0ef; }
        .profile-highlight.bg-orange .icon { color: #ff851b; }
        
        .profile-highlight h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .profile-highlight .description {
            color: #7f8c8d;
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        .profile-highlight .price {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .profile-highlight.bg-blue .price { color: #3c8dbc; }
        .profile-highlight.bg-green .price { color: #00a65a; }
        .profile-highlight.bg-red .price { color: #dd4b39; }
        .profile-highlight.bg-yellow .price { color: #f39c12; }
        .profile-highlight.bg-aqua .price { color: #00c0ef; }
        .profile-highlight.bg-orange .price { color: #ff851b; }
        
        /* Order Form */
        .order-form {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 2;
            margin-bottom: 0;
        }
        
        .form-group label {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 6px;
            border: 2px solid #e1e8ed;
            color: #2c3e50 !important;
            background-color: #ffffff !important;
            font-size: 14px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(60, 141, 188, 0.25);
            color: #2c3e50 !important;
            background-color: #ffffff !important;
        }
        
        .form-control::placeholder {
            color: #95a5a6 !important;
            opacity: 1;
        }
        
        .text-muted {
            color: #7f8c8d !important;
            font-size: 12px;
            font-weight: 500;
        }
        
        .text-danger {
            color: #e74c3c !important;
            font-weight: 600;
        }
        
        .btn-order {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            margin-bottom: 0;
            display: block;
        }
        
        .btn-order:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .order-form {
            margin-bottom: 0;
        }
        
        .alert {
            border-radius: 6px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .footer {
            text-align: center;
            color: var(--text-muted);
            margin-top: 60px !important;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
            clear: both;
            width: 100%;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
            display: block;
        }
        
        .footer i {
            color: var(--primary-color);
            margin-right: 5px;
        }
        
        .footer p {
            margin: 0;
            line-height: 1.5;
        }
        
        .footer small {
            display: block;
            margin-top: 5px;
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px 5px 30px 5px;
            }
            
            .container {
                padding: 0 5px;
            }
            
            .header {
                margin: -10px -5px 20px -5px;
                padding: 25px 15px;
            }
            
            .header h1 {
                font-size: 1.6rem;
            }
            
            .profile-highlight {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .profile-highlight .price {
                font-size: 1.8rem;
            }
            
            .order-form {
                padding: 20px;
            }
            
            .footer {
                margin-top: 50px !important;
                padding: 15px;
                font-size: 0.85rem;
                position: relative;
                display: block;
            }
        }
    </style>
</head>
<body>
    
    <div class="container">
        
        <!-- Header -->
        <div class="header">
            <h1><i class="fa fa-wifi"></i> Order Voucher WiFi</h1>
            <p><?= htmlspecialchars($agent['agent_name']); ?></p>
        </div>
        
        <!-- Profile Highlight -->
        <div class="profile-highlight <?= $profile_color; ?>">
            <div class="icon">
                <i class="fa fa-ticket"></i>
            </div>
            <h2><?= htmlspecialchars($profile['profile_name']); ?></h2>
            <div class="description">
                <?= htmlspecialchars($profile['description'] ?: 'Paket voucher WiFi berkualitas tinggi'); ?>
            </div>
            <div class="price">
                Rp <?= number_format($profile['price'], 0, ',', '.'); ?>
            </div>
            <small class="text-muted">Harga sudah termasuk pajak</small>
        </div>
        
        <!-- Order Form -->
        <div class="order-form">
            <h4 class="mb-4" style="color: #2c3e50; font-weight: 700;">
                <i class="fa fa-user"></i> Data Pembeli
            </h4>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i> <?= $_SESSION['error']; ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form method="POST" action="process_order.php">
                <input type="hidden" name="agent_code" value="<?= htmlspecialchars($agent_code); ?>">
                <input type="hidden" name="profile_id" value="<?= htmlspecialchars($profile_id); ?>">
                
                <div class="form-group">
                    <label>Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" class="form-control" 
                           placeholder="Masukkan nama lengkap" required>
                </div>
                
                <div class="form-group">
                    <label>Nomor WhatsApp <span class="text-danger">*</span></label>
                    <input type="tel" name="customer_phone" class="form-control" 
                           placeholder="08xxxxxxxxxx" required>
                    <small class="text-muted">Voucher akan dikirim ke nomor ini</small>
                </div>
                
                <div class="form-group">
                    <label>Email (Optional)</label>
                    <input type="email" name="customer_email" class="form-control" 
                           placeholder="email@example.com">
                </div>
                
                <div class="form-group">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid var(--primary-color);">
                        <h6 style="color: #2c3e50; margin-bottom: 10px;">
                            <i class="fa fa-info-circle"></i> Ringkasan Pesanan
                        </h6>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Paket:</span>
                            <strong><?= htmlspecialchars($profile['profile_name']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Harga:</span>
                            <strong>Rp <?= number_format($profile['price'], 0, ',', '.'); ?></strong>
                        </div>
                        <hr style="margin: 10px 0;">
                        <div style="display: flex; justify-content: space-between; font-size: 1.1rem;">
                            <span><strong>Total:</strong></span>
                            <strong style="color: var(--primary-color);">Rp <?= number_format($profile['price'], 0, ',', '.'); ?></strong>
                        </div>
                        <small class="text-muted">*Biaya admin akan ditambahkan sesuai metode pembayaran</small>
                    </div>
                </div>
                
                <button type="submit" class="btn-order">
                    <i class="fa fa-shopping-cart"></i> Lanjut ke Pembayaran
                </button>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <i class="fa fa-shield"></i> Transaksi aman dan terpercaya<br>
                <small>Powered by MikhMon Agent System</small>
            </p>
        </div>
        
    </div>
    
</body>
</html>
