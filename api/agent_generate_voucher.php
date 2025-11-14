<?php
/*
 * API Bridge - Agent Generate Voucher
 * Agent panel call this API to generate voucher
 */

session_start();
header('Content-Type: application/json');

// Check if request from agent
if (!isset($_POST['agent_id']) || !isset($_POST['agent_token'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include('../include/config.php');
include('../include/db_config.php');
include('../include/whatsapp_config.php');
include('../lib/Agent.class.php');
include('../lib/routeros_api.class.php');
include('../lib/VoucherGenerator.class.php');

$agent = new Agent();

// Verify agent
$agentId = intval($_POST['agent_id']);
$agentToken = $_POST['agent_token']; // Simple token: md5(agent_id . phone)
$agentData = $agent->getAgentById($agentId);

if (!$agentData || md5($agentId . $agentData['phone']) !== $agentToken) {
    echo json_encode(['success' => false, 'message' => 'Invalid agent']);
    exit;
}

if ($agentData['status'] != 'active') {
    echo json_encode(['success' => false, 'message' => 'Agent tidak aktif']);
    exit;
}

// Get parameters
$profileName = $_POST['profile'] ?? '';
$quantity = intval($_POST['quantity'] ?? 1);
$customerPhone = $_POST['customer_phone'] ?? '';
$customerName = $_POST['customer_name'] ?? '';

// Validate
if (empty($profileName) || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Get agent price
$priceData = $agent->getAgentPrice($agentId, $profileName);
if (!$priceData) {
    echo json_encode(['success' => false, 'message' => 'Harga untuk profile ini belum diset']);
    exit;
}

$buyPrice = $priceData['buy_price'];
$totalCost = $buyPrice * $quantity;

// Check balance
if ($agentData['balance'] < $totalCost) {
    echo json_encode([
        'success' => false, 
        'message' => 'Saldo tidak mencukupi',
        'balance' => $agentData['balance'],
        'required' => $totalCost
    ]);
    exit;
}

// Get MikroTik session
$sessions = array_keys($data);
$session = null;
foreach ($sessions as $s) {
    if ($s != 'mikhmon') {
        $session = $s;
        break;
    }
}

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Session MikroTik tidak ditemukan']);
    exit;
}

// Connect to MikroTik
try {
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        echo json_encode(['success' => false, 'message' => 'Gagal terhubung ke MikroTik']);
        exit;
    }
    
    $generatedVouchers = [];
    $successCount = 0;
    
    // Initialize voucher generator
    $voucherGen = new VoucherGenerator();
    
    for ($i = 0; $i < $quantity; $i++) {
        // Generate username & password using settings
        $voucher = $voucherGen->generateVoucher();
        $username = $voucher['username'];
        $password = $voucher['password'];
        $comment = 'vc-agent-' . $agentData['agent_code'] . '-' . date('dmy');
        
        // Add user to MikroTik
        $API->comm("/ip/hotspot/user/add", array(
            "server" => "all",
            "name" => $username,
            "password" => $password,
            "profile" => $profileName,
            "comment" => $comment,
        ));
        
        // Deduct balance
        $deductResult = $agent->deductBalance(
            $agentId,
            $buyPrice,
            $profileName,
            $username,
            'Generate voucher: ' . $username
        );
        
        if ($deductResult['success']) {
            // Save to agent_vouchers
            $db = getDBConnection();
            $sql = "INSERT INTO agent_vouchers (agent_id, transaction_id, username, password, profile_name, buy_price, sell_price, customer_phone, customer_name, sent_via) 
                    VALUES (:agent_id, :transaction_id, :username, :password, :profile_name, :buy_price, :sell_price, :customer_phone, :customer_name, :sent_via)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':agent_id' => $agentId,
                ':transaction_id' => $deductResult['transaction_id'],
                ':username' => $username,
                ':password' => $password,
                ':profile_name' => $profileName,
                ':buy_price' => $buyPrice,
                ':sell_price' => $priceData['sell_price'],
                ':customer_phone' => $customerPhone,
                ':customer_name' => $customerName,
                ':sent_via' => 'web'
            ]);
            
            $generatedVouchers[] = [
                'username' => $username,
                'password' => $password,
                'profile' => $profileName
            ];
            
            $successCount++;
        }
    }
    
    $API->disconnect();
    
    // Send WhatsApp notification if customer phone is provided
    $whatsappSent = false;
    $whatsappError = null;
    
    if (!empty($customerPhone) && !empty($generatedVouchers) && WHATSAPP_ENABLED) {
        try {
            // Load payment settings for hotspot info
            $db = getDBConnection();
            $stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'payment_%'");
            $paymentSettings = [];
            while ($row = $stmt->fetch()) {
                $paymentSettings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Get hotspot name from session name or use default
            $hotspotName = $session ?? 'Hotspot WiFi';
            
            // Get DNS name from config or use IP
            $dnsName = '';
            if (isset($data[$session]) && isset($data[$session][4])) {
                $dnsName = explode('@|@', $data[$session][4])[1] ?? '';
            }
            
            $loginUrl = !empty($dnsName) ? "http://$dnsName" : "http://$iphost";
            
            // Format message for all vouchers
            $message = "*🎫 VOUCHER WIFI HOTSPOT*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "*Hotspot:* " . $hotspotName . "\n";
            $message .= "*Profile:* " . $profileName . "\n\n";
            
            if (count($generatedVouchers) == 1) {
                // Single voucher
                $v = $generatedVouchers[0];
                $message .= "*Username:* `" . $v['username'] . "`\n";
                $message .= "*Password:* `" . $v['password'] . "`\n";
            } else {
                // Multiple vouchers
                $message .= "*Jumlah Voucher:* " . count($generatedVouchers) . "\n\n";
                foreach ($generatedVouchers as $index => $v) {
                    $message .= "*Voucher #" . ($index + 1) . "*\n";
                    $message .= "Username: `" . $v['username'] . "`\n";
                    $message .= "Password: `" . $v['password'] . "`\n\n";
                }
            }
            
            $message .= "\n*Login URL:*\n";
            $message .= $loginUrl . "\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "_Terima kasih telah menggunakan layanan kami_\n";
            $message .= "_Voucher ini berlaku sesuai ketentuan yang tertera_";
            
            // Send WhatsApp
            $result = sendWhatsAppMessage($customerPhone, $message);
            $whatsappSent = $result['success'];
            
            if (!$whatsappSent) {
                $whatsappError = $result['message'] ?? 'Gagal mengirim WhatsApp';
            }
            
            // Update sent_via in database
            if ($whatsappSent) {
                $db = getDBConnection();
                foreach ($generatedVouchers as $v) {
                    $stmt = $db->prepare("UPDATE agent_vouchers SET sent_via = 'whatsapp' WHERE agent_id = :agent_id AND username = :username ORDER BY id DESC LIMIT 1");
                    $stmt->execute([
                        ':agent_id' => $agentId,
                        ':username' => $v['username']
                    ]);
                }
            }
        } catch (Exception $e) {
            $whatsappError = 'Error: ' . $e->getMessage();
            error_log("WhatsApp send error: " . $whatsappError);
        }
    }
    
    // Get updated balance
    $agentData = $agent->getAgentById($agentId);
    
    $response = [
        'success' => true,
        'message' => "Berhasil generate $successCount voucher!",
        'vouchers' => $generatedVouchers,
        'balance' => $agentData['balance'],
        'total_cost' => $totalCost
    ];
    
    // Add WhatsApp status
    if (!empty($customerPhone)) {
        $response['whatsapp_sent'] = $whatsappSent;
        if ($whatsappError) {
            $response['whatsapp_error'] = $whatsappError;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
