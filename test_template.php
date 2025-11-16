<?php
// Mulai sesi untuk menghindari error
session_start();
$_SESSION['mikhmon'] = true;

// Include file yang diperlukan
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/lib/excel/SimpleXLSX.php';
require_once __DIR__ . '/lib/excel/SimpleXLSXGen.php';

// Buat template file
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

$rows = [$headers];
$rows[] = ['Contoh Pelanggan', '081234567890', 'email@contoh.com', 'Alamat pelanggan', 'user123@isp', 'CPE-001', 'BRONZE', 1, 'active', 'Catatan opsional', 0];

$filename = 'template_pelanggan_billing.xlsx';
$xlsx = SimpleXLSXGen::fromArray($rows);
$data = $xlsx->buildXLSX();
file_put_contents($filename, $data);

echo "Template file berhasil dibuat: " . $filename . "\n";

// Coba baca file yang dibuat
$xlsxRead = SimpleXLSX::parse($filename);
if (!$xlsxRead) {
    echo "Gagal membaca file template yang dibuat.\n";
    exit(1);
}

echo "File template berhasil dibaca.\n";
$rowsRead = $xlsxRead->rows();
echo "Jumlah baris: " . count($rowsRead) . "\n";

foreach ($rowsRead as $index => $row) {
    echo "Baris $index: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "Template berhasil diuji.\n";