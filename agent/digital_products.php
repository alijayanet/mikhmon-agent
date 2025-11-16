<?php
session_start();
error_reporting(0);

if (!isset($_SESSION['agent_id'])) {
    header('Location: index.php');
    exit();
}

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');
require_once(__DIR__ . '/../lib/DigiflazzClient.class.php');

$agentId = $_SESSION['agent_id'];
$agent = new Agent();
$agentData = $agent->getAgentById($agentId);
if (!$agentData || $agentData['status'] !== 'active') {
    header('Location: logout.php');
    exit();
}

$balance = $agentData['balance'];
$digiflazzClient = null;
$digiflazzEnabled = false;
$digiflazzError = '';
$markupPercent = 0;
$markupNominal = 0;

try {
    $digiflazzClient = new DigiflazzClient();
    $digiflazzEnabled = $digiflazzClient->isEnabled();
    $settings = $digiflazzClient->getSettings();
    $markupNominal = isset($settings['default_markup_nominal']) ? (int)$settings['default_markup_nominal'] : 0;
} catch (Exception $e) {
    $digiflazzError = $e->getMessage();
}

$pdo = getDBConnection();
$categoryFilter = trim($_GET['category'] ?? '');
$brandFilter = trim($_GET['brand'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// Fetch filter options
$brands = $pdo->query("SELECT DISTINCT brand FROM digiflazz_products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand")
              ->fetchAll(PDO::FETCH_COLUMN);
$categories = $pdo->query("SELECT DISTINCT category FROM digiflazz_products WHERE category IS NOT NULL AND category != '' ORDER BY category")
                  ->fetchAll(PDO::FETCH_COLUMN);
$types = ['prepaid', 'postpaid'];

$filtersApplied = [];
$sql = "SELECT * FROM digiflazz_products WHERE status = 'active'";
$params = [];

if ($categoryFilter !== '') {
    $sql .= " AND category = :category";
    $params[':category'] = $categoryFilter;
    $filtersApplied['category'] = $categoryFilter;
}

if ($brandFilter !== '') {
    $sql .= " AND brand = :brand";
    $params[':brand'] = $brandFilter;
    $filtersApplied['brand'] = $brandFilter;
}

if ($typeFilter !== '' && in_array($typeFilter, $types, true)) {
    $sql .= " AND type = :type";
    $params[':type'] = $typeFilter;
    $filtersApplied['type'] = $typeFilter;
}

if ($searchQuery !== '') {
    $sql .= " AND (product_name LIKE :search OR buyer_sku_code LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
    $filtersApplied['q'] = $searchQuery;
}

$sql .= " ORDER BY brand, product_name LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($products)) {
    $markupForSort = (int)($markupNominal ?? 0);

    usort($products, function (array $a, array $b) use ($markupForSort) {
        $priceA = (int)($a['seller_price'] ?? 0);
        $priceB = (int)($b['seller_price'] ?? 0);

        if ($priceA <= 0) {
            $priceA = (int)($a['price'] ?? 0);
            if ($priceA <= 0) {
                $priceA = (int)($a['buyer_price'] ?? 0);
            }
            if ($priceA > 0 && $markupForSort > 0) {
                $priceA += $markupForSort;
            }
        }

        if ($priceB <= 0) {
            $priceB = (int)($b['price'] ?? 0);
            if ($priceB <= 0) {
                $priceB = (int)($b['buyer_price'] ?? 0);
            }
            if ($priceB > 0 && $markupForSort > 0) {
                $priceB += $markupForSort;
            }
        }

        if ($priceA === $priceB) {
            return strcmp($a['product_name'] ?? '', $b['product_name'] ?? '');
        }

        return ($priceA <=> $priceB);
    });
}
$totalProducts = count($products);

include_once('include_head.php');
include_once('include_nav.php');
?>
<style>
.digital-products-header {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: space-between;
    align-items: flex-end;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.product-card {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 16px;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: #1f2933;
    min-width: 0;
    overflow-wrap: anywhere;
}

.product-brand {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #0f172a;
    margin-bottom: 5px;
}

.product-name {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 6px;
}

.product-sku {
    font-family: monospace;
    font-size: 12px;
    color: #334155;
    margin-bottom: 10px;
}

.product-price {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.product-price span {
    font-size: 13px;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.product-price strong {
    font-size: 18px;
    color: #0d6efd;
    font-weight: 700;
}

.product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}

.product-meta span {
    background: #e2e8f0;
    color: #1e293b;
    padding: 3px 7px;
    border-radius: 12px;
    font-size: 11px;
}

.product-actions {
    margin-top: auto;
}

.btn-order {
    width: 100%;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #fff;
    border: none;
    padding: 9px 14px;
    border-radius: 8px;
    font-weight: 600;
    transition: transform 0.2s, box-shadow 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

@media (max-width: 992px) {
    .product-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }
}

@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }
    .product-card {
        padding: 14px;
    }
    .product-name {
        font-size: 15px;
    }
    .product-price strong {
        font-size: 17px;
    }
}

@media (max-width: 520px) {
    .product-grid {
        grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
        gap: 10px;
    }
    .product-card {
        padding: 12px;
    }
    .product-name {
        font-size: 14px;
    }
    .product-sku {
        font-size: 11px;
        margin-bottom: 8px;
    }
    .product-meta span {
        font-size: 10px;
        padding: 2px 6px;
    }
    .product-price strong {
        font-size: 16px;
    }
    .btn-order {
        padding: 8px 12px;
        font-size: 13px;
    }
}

.btn-order:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(59, 130, 246, 0.3);
}
.dark .btn-order {
    color: #10172a;
    font-weight: 700;
}
.dark .product-card {
    background: #1f2937;
    border: 1px solid #374151;
    color: #e2e8f0;
}
.dark .product-brand,
.dark .product-name,
.dark .product-sku {
    color: #f9fafb;
}
.dark .product-meta span {
    background: #334155;
    color: #e2e8f0;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(102,126,234,0.35);
}

.summary-card h4 {
    margin: 0 0 10px 0;
    font-weight: 600;
}

.summary-card .value {
    font-size: 26px;
    font-weight: 700;
}

.alert-note {
    border-left: 4px solid #0dcaf0;
    background: #e7f9fd;
    padding: 15px;
    border-radius: 8px;
    color: #0b7285;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .summary-card .value {
        font-size: 22px;
    }
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}

.modal-content {
    background: #fff;
    border-radius: 12px;
    max-width: 480px;
    width: 100%;
    padding: 25px;
    position: relative;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

.modal-content h4 {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
}

.modal-close {
    position: absolute;
    top: 12px;
    right: 15px;
    border: none;
    background: none;
    font-size: 22px;
    color: #666;
    cursor: pointer;
}

.order-summary {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    color: #1f2933;
}

.order-summary h5 {
    margin: 0 0 8px 0;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.serial-highlight {
    display: inline-block;
    margin-top: 8px;
    padding: 6px 10px;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.08);
    font-family: 'Courier New', monospace;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.dark .serial-highlight {
    background: rgba(255,255,255,0.08);
    color: #f9fafb;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #b91c1c;
}

.dark .status-badge {
    color: #e2e8f0;
}

.dark .status-success {
    background: rgba(34, 197, 94, 0.25);
    color: #bbf7d0;
}

.dark .status-pending {
    background: rgba(251, 191, 36, 0.25);
    color: #fcd34d;
}

.dark .status-failed {
    background: rgba(248, 113, 113, 0.25);
    color: #fca5a5;
}

.modal-content label,
.modal-content input,
.modal-content button {
    color: #0f172a;
}

.modal-content input {
    background: #fff;
}

.modal-content .form-control {
    color: #0f172a !important;
    background: #fff !important;
    border-color: #cbd5f5 !important;
}

.modal-content .form-control::placeholder {
    color: #64748b;
    opacity: 1;
}

.dark .modal-content {
    background: #111827;
    color: #e2e8f0;
}

.dark .modal-content label,
.dark .modal-content input,
.dark .modal-content button {
    color: #e2e8f0;
}

.dark .modal-content input {
    background: #1f2937;
    border-color: #374151;
    color: #f9fafb;
}

.dark .modal-content .form-control {
    background: #1f2937 !important;
    border-color: #475569 !important;
    color: #f9fafb !important;
}

.dark .modal-content .form-control::placeholder {
    color: #94a3b8;
    opacity: 1;
}

.dark .order-summary {
    background: #1f2937;
    color: #e2e8f0;
}

.dark .order-summary h5 {
    color: #f9fafb;
}

.dark .order-summary p {
    color: #cbd5f5;
}

.badge-type {
    background: #fef3c7;
    color: #92400e;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    text-transform: uppercase;
}

.badge-brand {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header digital-products-header">
                <div>
                    <h3><i class="fa fa-plug"></i> Digital Products (Digiflazz)</h3>
                    <div>Saldo Anda: <strong id="agentBalance">Rp <?= number_format($balance, 0, ',', '.'); ?></strong></div>
                </div>
                <form class="d-flex" method="get" style="gap:10px;">
                    <input type="text" name="q" class="form-control" placeholder="Cari produk atau SKU" value="<?= htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Cari</button>
                </form>
            </div>
            <div class="card-body">
                <?php if ($digiflazzError): ?>
                    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($digiflazzError); ?></div>
                <?php elseif (!$digiflazzEnabled): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i> Integrasi Digiflazz belum dikonfigurasi. Hubungi administrator untuk mengaktifkan fitur ini.
                    </div>
                <?php else: ?>
                    <div class="summary-cards">
                        <div class="summary-card">
                            <h4>Produk Tersedia</h4>
                            <div class="value"><?= number_format($totalProducts); ?></div>
                            <small>Filter otomatis menampilkan maksimal 200 produk.</small>
                        </div>
                    </div>

                    <form method="get" class="filter-grid">
                        <div>
                            <label>Brand</label>
                            <select name="brand" class="form-control">
                                <option value="">Semua Brand</option>
                                <?php foreach ($brands as $brand): ?>
                                <option value="<?= htmlspecialchars($brand); ?>" <?= $brand === $brandFilter ? 'selected' : ''; ?>><?= htmlspecialchars($brand); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Kategori</label>
                            <select name="category" class="form-control">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category); ?>" <?= $category === $categoryFilter ? 'selected' : ''; ?>><?= htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Tipe</label>
                            <select name="type" class="form-control">
                                <option value="">Semua Tipe</option>
                                <?php foreach ($types as $type): ?>
                                <option value="<?= $type; ?>" <?= $type === $typeFilter ? 'selected' : ''; ?>><?= ucfirst($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>&nbsp;</label>
                            <div class="d-flex" style="gap:8px;">
                                <button type="submit" class="btn btn-secondary"><i class="fa fa-filter"></i> Terapkan</button>
                                <a href="digital_products.php" class="btn btn-light">Reset</a>
                            </div>
                        </div>
                    </form>

                    <?php if ($totalProducts === 0): ?>
                        <div class="alert-note">
                            Tidak ada produk yang cocok dengan filter Anda. Coba ubah filter atau lakukan sinkronisasi ulang daftar produk melalui panel admin.
                        </div>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php foreach ($products as $product): 
                                $costPrice = (int)$product['price'];
                                if ($costPrice <= 0 && isset($product['buyer_price'])) {
                                    $costPrice = (int)$product['buyer_price'];
                                }
                                $displayPrice = $costPrice;
                                if (!empty($product['seller_price']) && (int)$product['seller_price'] > 0) {
                                    $displayPrice = (int)$product['seller_price'];
                                } elseif ($markupNominal > 0) {
                                    $displayPrice = $costPrice + $markupNominal;
                                }
                                if ($displayPrice < $costPrice) {
                                    $displayPrice = $costPrice;
                                }
                                $brand = $product['brand'] ?: 'Unknown';
                                $category = $product['category'] ?: 'General';
                                $type = $product['type'] ?: 'prepaid';
                            ?>
                            <div class="product-card">
                                <div>
                                    <div class="product-brand"><?= htmlspecialchars($brand); ?></div>
                                    <div class="product-name"><?= htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-sku">SKU: <?= htmlspecialchars($product['buyer_sku_code']); ?></div>
                                </div>
                                <div class="product-meta">
                                    <span class="badge-brand">Brand: <?= htmlspecialchars($brand); ?></span>
                                    <span class="badge-type"><?= strtoupper($type); ?></span>
                                    <span>Kategori: <?= htmlspecialchars($category); ?></span>
                                </div>
                                <div class="product-price">
                                    <span>Harga Dasar</span>
                                    <strong>Rp <?= number_format($displayPrice, 0, ',', '.'); ?></strong>
                                </div>
                                <div class="product-actions">
                                    <button
                                        class="btn-order"
                                        data-product-id="<?= (int)$product['id']; ?>"
                                        data-product-name="<?= htmlspecialchars($product['product_name']); ?>"
                                        data-product-sku="<?= htmlspecialchars($product['buyer_sku_code']); ?>"
                                        data-cost-price="<?= $costPrice; ?>"
                                        data-display-price="<?= $displayPrice; ?>"
                                        data-product-brand="<?= htmlspecialchars($brand); ?>"
                                        data-product-type="<?= htmlspecialchars($type); ?>"
                                        >
                                        <i class="fa fa-shopping-cart"></i> Order
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="orderModal">
    <div class="modal-content">
        <button class="modal-close" id="orderModalClose" aria-label="Tutup">&times;</button>
        <h4>Order Produk Digital</h4>
        <div class="order-summary" id="orderSummary">
            <h5 id="orderProductName">-</h5>
            <p class="mb-1">SKU: <span id="orderProductSku">-</span></p>
            <p>Harga: <strong id="orderProductPrice">Rp 0</strong></p>
        </div>
        <form id="orderForm">
            <input type="hidden" name="product_id" id="orderProductId">
            <input type="hidden" name="agent_token" value="<?= htmlspecialchars($_SESSION['agent_token'] ?? ''); ?>">
            <div class="form-group">
                <label>Nomor Tujuan (MSISDN)</label>
                <input type="text" name="customer_no" id="orderCustomerNo" class="form-control" maxlength="20" required placeholder="Contoh: 081234567890">
            </div>
            <div class="alert alert-info" id="orderInfo" style="display:none;"></div>
            <div class="alert alert-danger" id="orderError" style="display:none;"></div>
            <button type="submit" class="btn btn-primary" id="orderSubmitBtn">
                <i class="fa fa-paper-plane"></i> Kirim Pesanan
            </button>
        </form>
    </div>
</div>

<script>
(function($){
    const modal = $('#orderModal');
    const orderForm = $('#orderForm');
    const orderInfo = $('#orderInfo');
    const orderError = $('#orderError');
    const submitBtn = $('#orderSubmitBtn');

    function formatCurrency(num){
        return new Intl.NumberFormat('id-ID').format(num);
    }

    $('.btn-order').on('click', function(){
        const btn = $(this);
        const productName = btn.data('product-name');
        const productSku = btn.data('product-sku');
        const productId = btn.data('product-id');
        const displayPrice = btn.data('display-price');

        $('#orderProductName').text(productName);
        $('#orderProductSku').text(productSku);
        $('#orderProductPrice').text('Rp ' + formatCurrency(displayPrice));
        $('#orderProductId').val(productId);
        $('#orderCustomerNo').val('');
        orderInfo.hide();
        orderError.hide();
        submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Kirim Pesanan');
        modal.css('display', 'flex');
    });

    $('#orderModalClose').on('click', function(){
        modal.hide();
    });

    modal.on('click', function(e){
        if ($(e.target).is('#orderModal')) {
            modal.hide();
        }
    });

    orderForm.on('submit', function(e){
        e.preventDefault();
        orderInfo.hide();
        orderError.hide();

        const formData = orderForm.serialize();
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');

        $.ajax({
            url: '../api/agent_digiflazz_order.php',
            method: 'POST',
            data: formData,
            dataType: 'json'
        }).done(function(res){
            if (res.success) {
                const statusValue = (res.status || '').toString().toLowerCase();
                let badgeClass = 'status-pending';
                let badgeLabel = 'PENDING';

                if (!statusValue || ['success', 'sukses', 'berhasil', 'ok'].includes(statusValue)) {
                    badgeClass = 'status-success';
                    badgeLabel = 'BERHASIL';
                } else if (['pending', 'process', 'processing', 'menunggu'].includes(statusValue)) {
                    badgeClass = 'status-pending';
                    badgeLabel = 'PENDING';
                } else {
                    badgeClass = 'status-failed';
                    badgeLabel = statusValue.toUpperCase();
                }

                let statusBadge = `<span class="status-badge ${badgeClass}">${badgeLabel}</span><br>`;
                let successMsg = res.message || 'Transaksi berhasil diproses.';

                if (res.bill_details) {
                    const bill = res.bill_details;
                    successMsg += '\n\nDetail Tagihan:\n' +
                        (bill.customer_name ? '- Nama: ' + bill.customer_name + '\n' : '') +
                        (bill.period ? '- Periode: ' + bill.period + '\n' : '') +
                        (bill.amount ? '- Tagihan: Rp ' + formatCurrency(bill.amount) + '\n' : '') +
                        (bill.admin ? '- Admin: Rp ' + formatCurrency(bill.admin) + '\n' : '') +
                        (bill.total ? '- Total: Rp ' + formatCurrency(bill.total) : '');
                }

                if (res.serial_number) {
                    successMsg += '\n\nSN: ' + res.serial_number;
                }

                let formattedMessage = statusBadge + successMsg.replace(/\n/g, '<br>');
                if (res.serial_number) {
                    formattedMessage += `<div class="serial-highlight">${res.serial_number}</div>`;
                }

                orderInfo.html(formattedMessage).show();
                if (res.balance !== undefined) {
                    $('#agentBalance').text('Rp ' + formatCurrency(res.balance));
                }
                setTimeout(function(){
                    modal.hide();
                }, 1500);
            } else {
                const statusValue = (res.status || '').toString().toLowerCase();
                let badgeClass = 'status-failed';
                let badgeLabel = statusValue ? statusValue.toUpperCase() : 'GAGAL';

                if (['pending', 'process', 'processing', 'menunggu'].includes(statusValue)) {
                    badgeClass = 'status-pending';
                    badgeLabel = 'PENDING';
                }

                let errorContent = `<span class="status-badge ${badgeClass}">${badgeLabel}</span><br><strong>` + (res.message || 'Transaksi gagal diproses.') + '</strong>';
                let retryPayload = null;

                if (res.transaction_data) {
                    retryPayload = res.transaction_data;
                    errorContent += '<div class="mt-2">' +
                        '<button type="button" class="btn btn-warning btn-sm" id="retryOrderBtn">' +
                        '<i class="fa fa-refresh"></i> Coba Ulang</button></div>';
                }

                orderError.html(errorContent).show();
                if (retryPayload) {
                    orderError.find('#retryOrderBtn').data('retry-payload', retryPayload);
                }
            }
        }).fail(function(){
            orderError.text('Terjadi kesalahan koneksi. Silakan coba lagi.').show();
        }).always(function(){
            submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Kirim Pesanan');
        });
    });

    orderForm.on('click', '#retryOrderBtn', function(){
        const payload = $(this).data('retry-payload');
        if (!payload) {
            return;
        }

        orderError.hide();
        orderInfo.hide();
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Mengulang...');

        $.ajax({
            url: '../api/agent_digiflazz_order.php',
            method: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function(res){
            if (res.success) {
                const statusValue = (res.status || '').toString().toLowerCase();
                let badgeClass = 'status-pending';
                let badgeLabel = 'PENDING';

                if (!statusValue || ['success', 'sukses', 'berhasil', 'ok'].includes(statusValue)) {
                    badgeClass = 'status-success';
                    badgeLabel = 'BERHASIL';
                } else if (['pending', 'process', 'processing', 'menunggu'].includes(statusValue)) {
                    badgeClass = 'status-pending';
                    badgeLabel = 'PENDING';
                } else {
                    badgeClass = 'status-failed';
                    badgeLabel = statusValue.toUpperCase();
                }

                let statusBadge = `<span class="status-badge ${badgeClass}">${badgeLabel}</span><br>`;
                let successMsg = res.message || 'Transaksi berhasil diproses.';
                if (res.bill_details) {
                    const bill = res.bill_details;
                    successMsg += '\n\nDetail Tagihan:\n' +
                        (bill.customer_name ? '- Nama: ' + bill.customer_name + '\n' : '') +
                        (bill.period ? '- Periode: ' + bill.period + '\n' : '') +
                        (bill.amount ? '- Tagihan: Rp ' + formatCurrency(bill.amount) + '\n' : '') +
                        (bill.admin ? '- Admin: Rp ' + formatCurrency(bill.admin) + '\n' : '') +
                        (bill.total ? '- Total: Rp ' + formatCurrency(bill.total) : '');
                }
                orderInfo.html(statusBadge + successMsg.replace(/\n/g, '<br>')).show();
                orderError.hide();
                if (res.balance !== undefined) {
                    $('#agentBalance').text('Rp ' + formatCurrency(res.balance));
                }
                setTimeout(function(){ modal.hide(); }, 1500);
            } else {
                const statusValue = (res.status || '').toString().toLowerCase();
                let badgeClass = 'status-failed';
                let badgeLabel = statusValue ? statusValue.toUpperCase() : 'GAGAL';

                if (['pending', 'process', 'processing', 'menunggu'].includes(statusValue)) {
                    badgeClass = 'status-pending';
                    badgeLabel = 'PENDING';
                }

                orderError.html(`<span class="status-badge ${badgeClass}">${badgeLabel}</span><br>` + (res.message || 'Transaksi gagal diproses.')).show();
            }
        }).fail(function(){
            orderError.text('Terjadi kesalahan koneksi. Silakan coba lagi.').show();
        }).always(function(){
            submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Kirim Pesanan');
        });
    });
})(jQuery);
</script>

<?php include_once('include_foot.php'); ?>
