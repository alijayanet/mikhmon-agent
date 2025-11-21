<?php
/*
 * Send Voucher via WhatsApp
 * Called after user generation
 */

session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include('../include/config.php');
include('../include/readcfg.php');
include('../include/whatsapp_config.php');
include('../lib/formatbytesbites.php');

// Get POST data
$phone = $_POST['phone'];
$username = $_POST['username'];
$password = $_POST['password'];
$profile = $_POST['profile'];
$timelimit = $_POST['timelimit'];
$datalimit = $_POST['datalimit'];
$validity = $_POST['validity'];
$price = $_POST['price'];
$comment = $_POST['comment'];

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp tidak boleh kosong']);
    exit;
}

// Format data limit
$datalimitFormatted = '';
if (!empty($datalimit) && $datalimit != '0') {
    $datalimitFormatted = formatBytes($datalimit, 2);
}

// Prepare voucher data
$voucherData = [
    'hotspot_name' => $hotspotname,
    'profile' => $profile,
    'username' => $username,
    'password' => $password,
    'timelimit' => $timelimit,
    'datalimit' => $datalimitFormatted,
    'validity' => $validity,
    'price' => $price,
    'login_url' => "http://$dnsname/login?username=$username&password=$password",
    'comment' => $comment
];

// Send voucher
$result = sendVoucherNotification($phone, $voucherData);

// Log transaction
logWhatsAppTransaction($phone, $username, $result['success'] ? 'SUCCESS' : 'FAILED', json_encode($result));

// Return result
echo json_encode($result);
