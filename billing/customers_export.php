<?php
session_start();

if (!isset($_SESSION['mikhmon'])) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/../lib/BillingService.class.php';
require_once __DIR__ . '/../lib/excel/SimpleXLSXGen.php';

date_default_timezone_set('Asia/Jakarta');

$service = new BillingService();

$headers = [
    'Nama',
    'No. WhatsApp',
    'Email',
    'Alamat',
    'PPPoE Username',
    'Nomor Layanan',
    'Paket',
    'Tanggal Isolasi',
    'Isolasi Otomatis',
    'Status',
    'Catatan',
    'Isolasi'
];

if (isset($_GET['template'])) {
    $rows = [$headers];
    $rows[] = ['Contoh Pelanggan', '081234567890', 'email@contoh.com', 'Alamat pelanggan', 'user123@isp', 'CPE-001', 'BRONZE', 20, 'Ya', 'active', 'Catatan opsional', 0];

    $xlsx = SimpleXLSXGen::fromArray($rows);
    $xlsx->downloadAs('template_pelanggan_billing.xlsx');
    exit;
}

$customers = $service->getAllCustomersWithProfile();

$rows = [$headers];
foreach ($customers as $customer) {
    $rows[] = [
        $customer['name'] ?? '',
        $customer['phone'] ?? '',
        $customer['email'] ?? '',
        $customer['address'] ?? '',
        $customer['genieacs_pppoe_username'] ?? '',
        $customer['service_number'] ?? '',
        $customer['profile_name'] ?? '',
        (int)($customer['billing_day'] ?? 20),
        ((int)($customer['auto_isolation'] ?? 1) === 1) ? 'Ya' : 'Tidak',
        $customer['status'] ?? 'inactive',
        $customer['notes'] ?? '',
        (int)($customer['is_isolated'] ?? 0)
    ];
}

$filename = 'pelanggan_billing_' . date('Ymd_His') . '.xlsx';
$xlsx = SimpleXLSXGen::fromArray($rows);
$xlsx->downloadAs($filename);
exit;