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

$filename = 'debug_template.xlsx';
$xlsx = SimpleXLSXGen::fromArray($rows);
$data = $xlsx->buildXLSX();
file_put_contents($filename, $data);

echo "Template file berhasil dibuat: " . $filename . "\n";

// Debug file ZIP
$zip = new ZipArchive();
if ($zip->open($filename) === true) {
    echo "File ZIP berhasil dibuka.\n";
    echo "Jumlah entri: " . $zip->numFiles . "\n";
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        echo "  Entri $i: " . $stat['name'] . " (" . $stat['size'] . " bytes)\n";
    }
    
    // Periksa file penting
    $filesToCheck = [
        'xl/workbook.xml',
        'xl/worksheets/sheet1.xml',
        'xl/sharedStrings.xml',
        'xl/_rels/workbook.xml.rels'
    ];
    
    foreach ($filesToCheck as $file) {
        $content = $zip->getFromName($file);
        if ($content !== false) {
            echo "\n--- Konten $file ---\n";
            echo substr($content, 0, 500) . (strlen($content) > 500 ? '...' : '') . "\n";
        } else {
            echo "\n--- File $file tidak ditemukan ---\n";
        }
    }
    
    $zip->close();
} else {
    echo "Gagal membuka file ZIP.\n";
}

// Coba baca file dengan SimpleXLSX
echo "\n=== Menguji SimpleXLSX ===\n";
$xlsxRead = SimpleXLSX::parse($filename);
if (!$xlsxRead) {
    echo "Gagal membaca file template yang dibuat.\n";
    
    // Coba debug lebih lanjut
    $zip = new ZipArchive();
    if ($zip->open($filename) === true) {
        echo "File ZIP bisa dibuka, memeriksa struktur...\n";
        
        // Periksa workbook.xml
        $workbookContent = $zip->getFromName('xl/workbook.xml');
        if ($workbookContent === false) {
            echo "File xl/workbook.xml tidak ditemukan\n";
        } else {
            echo "File xl/workbook.xml ditemukan\n";
            $workbookXml = simplexml_load_string($workbookContent);
            if ($workbookXml === false) {
                echo "File xl/workbook.xml tidak valid XML\n";
            } else {
                echo "File xl/workbook.xml valid XML\n";
            }
        }
        
        // Periksa worksheet
        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetContent === false) {
            echo "File xl/worksheets/sheet1.xml tidak ditemukan\n";
        } else {
            echo "File xl/worksheets/sheet1.xml ditemukan\n";
            $sheetXml = simplexml_load_string($sheetContent);
            if ($sheetXml === false) {
                echo "File xl/worksheets/sheet1.xml tidak valid XML\n";
            } else {
                echo "File xl/worksheets/sheet1.xml valid XML\n";
            }
        }
        
        $zip->close();
    }
} else {
    echo "File template berhasil dibaca.\n";
    $rowsRead = $xlsxRead->rows();
    echo "Jumlah baris: " . count($rowsRead) . "\n";
}