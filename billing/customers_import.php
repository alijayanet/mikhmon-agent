<?php
session_start();

if (!isset($_SESSION['mikhmon'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/../lib/BillingService.class.php';
require_once __DIR__ . '/../lib/excel/SimpleXLSX.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
    exit;
}

$service = new BillingService();
$profiles = $service->getProfiles();
$profileMap = [];
foreach ($profiles as $profile) {
    $profileMap[strtolower($profile['profile_name'])] = (int)$profile['id'];
}

$tmpFile = $_FILES['file']['tmp_name'];
$xlsx = SimpleXLSX::parse($tmpFile);
if (!$xlsx) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Gagal membaca file Excel']);
    exit;
}

$rows = $xlsx->rows();
if (count($rows) <= 1) {
    echo json_encode(['success' => false, 'message' => 'File tidak memiliki data']);
    exit;
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
        echo json_encode(['success' => false, 'message' => 'Kolom wajib tidak ditemukan: ' . $column]);
        exit;
    }
}

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
        } else {
            $service->createCustomer($payload);
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

echo json_encode($response);
exit;