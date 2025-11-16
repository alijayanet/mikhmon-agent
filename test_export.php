<?php
// Mulai sesi untuk menghindari error
session_start();
$_SESSION['mikhmon'] = true;

// Include file yang diperlukan
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/lib/excel/SimpleXLSXGen.php';

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
    'Tanggal Tagihan',
    'Status',
    'Catatan',
    'Isolasi'
];

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
        (int)($customer['billing_day'] ?? 1),
        $customer['status'] ?? 'inactive',
        $customer['notes'] ?? '',
        (int)($customer['is_isolated'] ?? 0)
    ];
}

$filename = 'pelanggan_billing_' . date('Ymd_His') . '.xlsx';
$xlsx = SimpleXLSXGen::fromArray($rows);

// Simpan file ke disk alih-alih mengirim ke browser
$data = $xlsx->buildXLSX();
file_put_contents($filename, $data);

echo "File ekspor berhasil dibuat: " . $filename . "\n";
echo "Jumlah baris data: " . count($rows) . "\n";