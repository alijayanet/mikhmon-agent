<?php
require_once 'include/db_config.php';
require_once 'lib/excel/SimpleXLSX.php';

$xlsx = SimpleXLSX::parse('template_pelanggan_billing.xlsx');
if ($xlsx) {
    echo "Template file berhasil dibaca.\n";
    $rows = $xlsx->rows();
    echo "Jumlah baris: " . count($rows) . "\n";
    
    foreach ($rows as $index => $row) {
        echo "Baris $index: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "Gagal membaca template file.\n";
}