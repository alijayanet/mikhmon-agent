<?php
/*
 * Public Voucher Sales Landing Page
 * Accessible without login
 */

// Get agent code from URL
$agent_code = $_GET['agent'] ?? $_GET['a'] ?? '';

if (empty($agent_code)) {
    die('Invalid agent code');
}

include_once('../include/db_config.php');

// Get theme from MikhMon config
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

// Get agent data
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM agents WHERE agent_code = :code AND status = 'active'");
    $stmt->execute([':code' => $agent_code]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        die('Agent not found or inactive');
    }
    
    // Get active pricing
    $stmt = $conn->prepare("SELECT * FROM agent_profile_pricing 
                           WHERE agent_id = :agent_id AND is_active = 1 
                           ORDER BY is_featured DESC, sort_order, id");
    $stmt->execute([':agent_id' => $agent['id']]);
    $pricings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pricings)) {
        die('No active packages available');
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

$site_name = $agent['agent_name'] ?? 'WiFi Voucher';
$site_phone = $agent['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beli Voucher WiFi - <?= htmlspecialchars($site_name); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <!-- Bootstrap CSS (for modal) -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Mikhmon UI (Dynamic Theme) -->
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <!-- favicon -->
    <link rel="icon" href="../img/favicon.png" />
    
    <style>
        body {
            background-color: #ecf0f5;
            font-family: 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        
        .wrapper {
            min-height: 100vh;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(0,0,0,.1);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #3a4149;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 1rem;
            color: #666;
            margin: 0;
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .pricing-grid {
                display: flex !important;
                flex-direction: column !important;
                margin-bottom: 20px;
            }
            
            .pricing-card {
                margin-bottom: 15px;
                width: 100%;
            }
        }
        
        /* Pricing Cards - MikhMon Box Style */
        .pricing-card {
            background: white;
            border-radius: 3px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.1);
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            border-top: 3px solid;
        }
        
        .pricing-card:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,.15);
            transform: translateY(-2px);
        }
        
        .pricing-card.featured {
            box-shadow: 0 2px 8px rgba(255,193,7,.3);
        }
        
        .pricing-card.featured::before {
            content: "TERPOPULER";
            position: absolute;
            top: 10px;
            right: -30px;
            background: #ffc107;
            color: #000;
            padding: 3px 35px;
            transform: rotate(45deg);
            font-size: 11px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,.2);
        }
        
        .pricing-card.bg-blue { border-top-color: #3c8dbc; }
        .pricing-card.bg-green { border-top-color: #00a65a; }
        .pricing-card.bg-red { border-top-color: #dd4b39; }
        .pricing-card.bg-yellow { border-top-color: #f39c12; }
        .pricing-card.bg-aqua { border-top-color: #00c0ef; }
        .pricing-card.bg-orange { border-top-color: #ff851b; }
        
        .pricing-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .pricing-card.bg-blue .icon { color: #3c8dbc; }
        .pricing-card.bg-green .icon { color: #00a65a; }
        .pricing-card.bg-red .icon { color: #dd4b39; }
        .pricing-card.bg-yellow .icon { color: #f39c12; }
        .pricing-card.bg-aqua .icon { color: #00c0ef; }
        .pricing-card.bg-orange .icon { color: #ff851b; }
        
        .pricing-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #3a4149;
        }
        
        .pricing-card .description {
            color: #666;
            margin-bottom: 15px;
            min-height: 60px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .pricing-card .price-wrapper {
            margin: 15px 0;
        }
        
        .pricing-card .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9rem;
        }
        
        .pricing-card .price {
            font-size: 1.8rem;
            font-weight: 700;
            color: #00a65a;
        }
        
        .pricing-card .btn {
            width: 100%;
            padding: 10px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 3px;
        }
        
        /* Modal */
        .modal {
            z-index: 9999 !important;
        }
        
        .modal-backdrop {
            z-index: 9998 !important;
        }
        
        .modal-content {
            border-radius: 3px;
        }
        
        .modal-header {
            background: #3c8dbc;
            color: white;
            border-radius: 3px 3px 0 0;
        }
        
        .modal-header .close {
            color: white;
            opacity: 0.8;
        }
        
        .modal-header .close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            border-top: 1px solid #e5e5e5;
            padding: 15px;
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
            border-radius: 3px;
            border: 1px solid #d2d6de;
            color: #3a4149 !important;
            background-color: #ffffff !important;
            font-size: 14px;
            padding: 8px 12px;
        }
        
        .form-control:focus {
            border-color: #3c8dbc;
            box-shadow: 0 0 0 0.2rem rgba(60, 141, 188, 0.25);
            color: #3a4149 !important;
            background-color: #ffffff !important;
        }
        
        .form-control::placeholder {
            color: #999 !important;
            opacity: 1;
        }
        
        .form-control::-webkit-input-placeholder {
            color: #999 !important;
        }
        
        .form-control::-moz-placeholder {
            color: #999 !important;
            opacity: 1;
        }
        
        .form-control:-ms-input-placeholder {
            color: #999 !important;
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
        
        .alert {
            border-radius: 3px;
        }
        
        /* Footer - Force outside grid */
        .footer {
            text-align: center;
            color: #666;
            margin: 30px 20px 20px 20px;
            padding: 20px;
            background: white;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(0,0,0,.1);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            display: block !important;
            position: static !important;
            float: none !important;
            grid-column: initial !important;
            grid-row: initial !important;
        }
        
        .footer p {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .footer a {
            color: #3c8dbc;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            padding: 5px 10px;
        }
        
        .footer a:hover {
            text-decoration: underline;
            color: #2c6d9c;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .wrapper {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header p {
                font-size: 0.9rem;
            }
            
            .pricing-card {
                padding: 15px;
            }
            
            .pricing-card .icon {
                font-size: 2rem;
            }
            
            .pricing-card h3 {
                font-size: 1.1rem;
            }
            
            .pricing-card .price {
                font-size: 1.4rem;
            }
            
            .footer {
                margin-top: 20px;
                padding: 12px 15px !important;
            }
            
            .footer div {
                font-size: 12px;
            }
            
            .footer a {
                display: inline-block;
                margin: 3px 5px;
                padding: 5px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    
    <div class="wrapper">
        <div style="max-width: 1200px; margin: 0 auto;">
        
        <!-- Header -->
        <div class="header">
            <h1><i class="fa fa-wifi"></i> <?= htmlspecialchars($site_name); ?></h1>
            <p>Pilih paket voucher WiFi sesuai kebutuhan Anda</p>
        </div>
        
        <!-- Pricing Cards -->
        <div class="pricing-grid">
            <?php foreach ($pricings as $pricing): ?>
                <div class="pricing-card bg-<?= $pricing['color']; ?> <?= $pricing['is_featured'] ? 'featured' : ''; ?>" 
                     onclick="selectPackage(<?= htmlspecialchars(json_encode($pricing)); ?>)">
                    
                    <div class="icon">
                        <i class="fa <?= $pricing['icon']; ?>"></i>
                    </div>
                    
                    <h3><?= htmlspecialchars($pricing['display_name']); ?></h3>
                    
                    <div class="description">
                        <?= nl2br(htmlspecialchars($pricing['description'])); ?>
                    </div>
                    
                    <div class="price-wrapper">
                        <?php if ($pricing['original_price']): ?>
                        <div class="original-price">
                            Rp <?= number_format($pricing['original_price'], 0, ',', '.'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="price">
                            Rp <?= number_format($pricing['price'], 0, ',', '.'); ?>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary">
                        <i class="fa fa-shopping-cart"></i> Beli Sekarang
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- End Pricing Grid -->
        
        </div>
        <!-- End Max-Width Container -->
        
        <!-- Clearfix -->
        <div style="clear: both; height: 0; overflow: hidden;"></div>
        
        <!-- Footer (Outside Container) -->
        <div class="footer" style="display: block !important; clear: both !important; width: calc(100% - 40px) !important; max-width: 1200px !important; margin: 30px 20px 20px 20px !important; padding: 15px !important;">
            <div style="margin-bottom: 8px; font-size: 14px; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 5px;">
                <?php if ($site_phone): ?>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $site_phone); ?>" target="_blank" style="color: #25D366; text-decoration: none;">
                    <i class="fa fa-whatsapp"></i> WA
                </a>
                <span style="color: #ddd;">•</span>
                <?php endif; ?>
                <a href="tos.php"><i class="fa fa-file-text-o"></i> TOS</a>
                <span style="color: #ddd;">•</span>
                <a href="privacy.php"><i class="fa fa-shield"></i> Privacy</a>
                <span style="color: #ddd;">•</span>
                <a href="faq.php"><i class="fa fa-question-circle"></i> FAQ</a>
            </div>
            <div style="font-size: 11px; color: #999;">
                &copy; <?= date('Y'); ?> <?= htmlspecialchars($site_name); ?>
            </div>
        </div>
        
    </div>
    <!-- End Wrapper -->
    
    <!-- Order Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Beli Voucher
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="orderForm" method="POST" action="process_order.php">
                    <input type="hidden" name="agent_code" value="<?= htmlspecialchars($agent_code); ?>">
                    <input type="hidden" name="profile_id" id="profile_id">
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong id="selected_package"></strong><br>
                            <span id="selected_price"></span>
                        </div>
                        
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
                            <label>
                                <input type="checkbox" name="agree_tos" required>
                                Saya setuju dengan <a href="tos.php" target="_blank">Syarat & Ketentuan</a>
                            </label>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-arrow-right"></i> Lanjut ke Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS (with Popper for modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function selectPackage(pricing) {
        $('#profile_id').val(pricing.id);
        $('#selected_package').text(pricing.display_name);
        
        let priceHtml = 'Rp ' + new Intl.NumberFormat('id-ID').format(pricing.price);
        if (pricing.original_price) {
            priceHtml = '<del>Rp ' + new Intl.NumberFormat('id-ID').format(pricing.original_price) + '</del> ' + priceHtml;
        }
        $('#selected_price').html(priceHtml);
        
        $('#orderModal').modal('show');
    }
    
    // Format phone number
    $('input[name="customer_phone"]').on('input', function() {
        let val = $(this).val().replace(/[^0-9]/g, '');
        if (val.startsWith('0')) {
            val = '62' + val.substring(1);
        }
        $(this).val(val);
    });
    </script>
    
</body>
</html>
