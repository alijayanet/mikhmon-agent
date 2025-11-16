<?php
// Mulai sesi untuk menghindari error
session_start();
$_SESSION['mikhmon'] = true;

// Include file yang diperlukan
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/lib/BillingService.class.php';
require_once __DIR__ . '/lib/excel/SimpleXLSX.php';

// Cari file ekspor terbaru
$files = glob("pelanggan_billing_*.xlsx");
if (empty($files)) {
    echo "Tidak ditemukan file ekspor untuk diuji.\n";
    exit(1);
}

$newestFile = $files[0];
foreach ($files as $file) {
    if (filemtime($file) > filemtime($newestFile)) {
        $newestFile = $file;
    }
}

echo "Menguji impor file: " . $newestFile . "\n";

// Simulasikan proses impor
$xlsx = SimpleXLSX::parse($newestFile);
if (!$xlsx) {
    echo "Gagal membaca file Excel: " . $newestFile . "\n";
    exit(1);
}

echo "File Excel berhasil dibaca.\n";

$rows = $xlsx->rows();
echo "Jumlah baris data: " . count($rows) . "\n";

if (count($rows) <= 1) {
    echo "File tidak memiliki data.\n";
    exit(1);
}

$header = array_map('strtolower', $rows[0]);
echo "Header kolom:\n";
foreach ($header as $index => $colName) {
    echo "  Kolom $index: $colName\n";
}

$colIndex = [
    'name' => array_search('nama', $header),
    'phone' => array_search('no. whatsapp', $header),
    'email' => array_search('email', $header),
    'address' => array_search('alamat', $header),
    'pppoe' => array_search('pppoe username', $header),
    'service_number' => array_search('nomor layanan', $header),
    'profile' => array_search('paket', $header),
    'billing_day' => array_search('tanggal tagihan', $header),
    'status' => array_search('status', $header),
    'notes' => array_search('catatan', $header),
    'isolated' => array_search('isolasi', $header),
];

$requiredColumns = ['name', 'phone', 'pppoe', 'profile'];
$missingColumns = [];
foreach ($requiredColumns as $column) {
    if ($colIndex[$column] === false || $colIndex[$column] === null) {
        $missingColumns[] = $column;
    }
}

if (!empty($missingColumns)) {
    echo "Kolom wajib yang tidak ditemukan: " . implode(', ', $missingColumns) . "\n";
    exit(1);
}

echo "Semua kolom wajib ditemukan.\n";

// Tampilkan data sample
echo "\nData sample (5 baris pertama):\n";
for ($i = 0; $i < min(5, count($rows)); $i++) {
    echo "Baris $i: " . json_encode($rows[$i], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\nProses impor berhasil diuji.\n";