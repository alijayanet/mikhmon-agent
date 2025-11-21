<?php
/*
 * Get User Details for WhatsApp
 */

session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$session = $_GET['session'];
if (empty($session)) {
    $session = $_SESSION['session'];
}

include('../include/config.php');
include('../include/readcfg.php');
include('../lib/routeros_api.class.php');
include('../lib/formatbytesbites.php');

$username = $_GET['username'];

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username required']);
    exit;
}

// Connect to MikroTik
$API = new RouterosAPI();
$API->debug = false;

if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
    echo json_encode(['success' => false, 'message' => 'Failed to connect to MikroTik']);
    exit;
}

// Get user details
$getuser = $API->comm("/ip/hotspot/user/print", array("?name" => $username));

if (empty($getuser)) {
    $API->disconnect();
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user = $getuser[0];
$profileName = $user['profile'];

// Get profile details
$getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => $profileName));
$profile = $getprofile[0];

$API->disconnect();

// Parse profile data
$ponlogin = $profile['on-login'];
$validity = explode(",", $ponlogin)[3];
$price = explode(",", $ponlogin)[2];
$sprice = explode(",", $ponlogin)[4];

// Format price
if ($currency == in_array($currency, $cekindo['indo'])) {
    $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
} else {
    $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
}

// Format data limit
$datalimitFormatted = '';
if (!empty($user['limit-bytes-total']) && $user['limit-bytes-total'] != '0') {
    $datalimitFormatted = formatBytes($user['limit-bytes-total'], 2);
}

// Prepare response
$response = [
    'success' => true,
    'username' => $user['name'],
    'password' => $user['password'],
    'profile' => $profileName,
    'timelimit' => isset($user['limit-uptime']) ? $user['limit-uptime'] : '',
    'datalimit' => $datalimitFormatted,
    'validity' => $validity,
    'price' => $priceFormatted,
    'comment' => isset($user['comment']) ? $user['comment'] : ''
];

echo json_encode($response);
