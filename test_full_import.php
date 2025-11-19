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

// Simulasikan proses impor seperti di customers_import.php
$service = new BillingService();
$profiles = $service->getProfiles();
$profileMap = [];
foreach ($profiles as $profile) {
    $profileMap[strtolower($profile['profile_name'])] = (int)$profile['id'];
}

// Simulasikan $_FILES
$_FILES['file'] = [
    'tmp_name' => $newestFile,
    'name' => $newestFile,
    'size' => filesize($newestFile),
    'error' => 0
];

$tmpFile = $_FILES['file']['tmp_name'];
$xlsx = SimpleXLSX::parse($tmpFile);
if (!$xlsx) {
    echo "Gagal membaca file Excel\n";
    exit(1);
}

echo "File Excel berhasil dibaca.\n";

$rows = $xlsx->rows();
if (count($rows) <= 1) {
    echo "File tidak memiliki data\n";
    exit(1);
}

$header = array_map('strtolower', $rows[0]);
$colIndex = [
    'name' => array_search('nama', $header),
    'phone' => array_search('no. whatsapp', $header),
    'email' => array_search('email', $header),
    'address' => array_search('alamat', $header),
    'pppoe' => array_search('pppoe username', $header),
    'service_number' => array_search('nomor layanan', $header),
    'profile' => array_search('paket', $header),
    'billing_day' => array_search('tanggal isolasi', $header),
    'auto_isolation' => array_search('isolasi otomatis', $header),
    'status' => array_search('status', $header),
    'notes' => array_search('catatan', $header),
    'isolated' => array_search('isolasi', $header),
];

$requiredColumns = ['name', 'phone', 'pppoe', 'profile'];
foreach ($requiredColumns as $column) {
    if ($colIndex[$column] === false || $colIndex[$column] === null) {
        echo "Kolom wajib tidak ditemukan: " . $column . "\n";
        exit(1);
    }
}

echo "Semua kolom wajib ditemukan.\n";

$success = 0;
$failed = 0;
$errors = [];
$lineNumber = 1;

foreach (array_slice($rows, 1) as $row) {
    $lineNumber++;
    $name = trim((string)($row[$colIndex['name']] ?? ''));
    $phone = trim((string)($row[$colIndex['phone']] ?? ''));
    $pppoe = trim((string)($row[$colIndex['pppoe']] ?? ''));
    $profileName = strtolower(trim((string)($row[$colIndex['profile']] ?? '')));

    if ($name === '' && $phone === '' && $pppoe === '') {
        continue;
    }

    if ($name === '' || $phone === '' || $pppoe === '' || $profileName === '') {
        $failed++;
        $errors[] = "Baris {$lineNumber}: kolom wajib kosong";
        continue;
    }

    $profileId = $profileMap[$profileName] ?? null;
    if (!$profileId) {
        $failed++;
        $errors[] = "Baris {$lineNumber}: paket '{$row[$colIndex['profile']]}' tidak ditemukan";
        continue;
    }

    $billingDay = (int)($row[$colIndex['billing_day']] ?? 20);
    $billingDay = max(1, min(28, $billingDay));

    $status = strtolower(trim((string)($row[$colIndex['status']] ?? 'active')));
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    $notes = trim((string)($row[$colIndex['notes']] ?? ''));
    $address = trim((string)($row[$colIndex['address']] ?? ''));
    $serviceNumber = trim((string)($row[$colIndex['service_number']] ?? ''));
    $email = trim((string)($row[$colIndex['email']] ?? ''));
    $isolated = (int)($row[$colIndex['isolated']] ?? 0) === 1 ? 1 : 0;

    $existing = $service->getCustomerByPppoeUsername($pppoe);

    $payload = [
        'profile_id' => $profileId,
        'name' => $name,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'address' => $address !== '' ? $address : null,
        'service_number' => $serviceNumber !== '' ? $serviceNumber : null,
        'genieacs_pppoe_username' => $pppoe,
        'billing_day' => $billingDay,
        'auto_isolation' => (int)($row[$colIndex['auto_isolation']] ?? 1),
        'status' => $status,
        'is_isolated' => $isolated,
        'notes' => $notes !== '' ? $notes : null,
    ];

    try {
        if ($existing) {
            $service->updateCustomer((int)$existing['id'], $payload);
            echo "Baris {$lineNumber}: Data pelanggan diperbarui - {$name} ({$pppoe})\n";
        } else {
            $service->createCustomer($payload);
            echo "Baris {$lineNumber}: Data pelanggan dibuat - {$name} ({$pppoe})\n";
        }
        $success++;
    } catch (Throwable $e) {
        $failed++;
        $errors[] = "Baris {$lineNumber}: " . $e->getMessage();
    }
}

$response = [
    'success' => true,
    'imported' => $success,
    'failed' => $failed,
    'errors' => $errors,
];

echo "\n=== Hasil Impor ===\n";
echo "Berhasil: " . $success . "\n";
echo "Gagal: " . $failed . "\n";

if (!empty($errors)) {
    echo "Error:\n";
    foreach ($errors as $error) {
        echo "  - " . $error . "\n";
    }
}

echo "Proses impor selesai.\n";