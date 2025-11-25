<?php
/*
 * WhatsApp Agent Webhook Handler
 * Handle incoming WhatsApp messages for agent commands
 */

session_start();
error_reporting(0);

// Load required files
include('../include/config.php');
include('../include/whatsapp_config.php');
include('../include/db_config.php');
include('../lib/Agent.class.php');
include('../lib/VoucherGenerator.class.php');
include('../lib/routeros_api.class.php');
include('../lib/DigiflazzClient.class.php');
include('../lib/BillingService.class.php');
include('../lib/WhatsAppNotification.class.php');

// Load message settings
$messageSettings = loadMessageSettings();

// Get webhook data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log incoming webhook
logWebhook($input);

// Process webhook based on gateway
$gateway = WHATSAPP_GATEWAY ?? 'fonnte';

switch ($gateway) {
    case 'fonnte':
        $phone = $data['sender'] ?? '';
        $message = $data['message'] ?? '';
        break;
    case 'wablas':
        $phone = $data['phone'] ?? '';
        $message = $data['message'] ?? '';
        break;
    case 'woowa':
        $phone = $data['from'] ?? '';
        $message = $data['message'] ?? '';
        break;
    case 'mpwa':
        $phone = $data['sender'] ?? '';
        $message = $data['message'] ?? '';
        break;
    default:
        $phone = $data['phone'] ?? $data['sender'] ?? $data['from'] ?? '';
        $message = $data['message'] ?? '';
        break;
}

function formatRupiah($value) {
    return 'Rp ' . number_format((int)$value, 0, ',', '.');
}

if (!empty($phone) && !empty($message)) {
    // Debug log
    error_log("WhatsApp Agent Webhook - Phone: $phone, Message: $message");
    error_log("WHATSAPP_GATEWAY: " . (WHATSAPP_GATEWAY ?? 'undefined'));
    processAgentCommand($phone, trim($message));
} else {
    error_log("WhatsApp Agent Webhook - Empty phone or message. Phone: $phone, Message: $message");
}

/**
 * Process agent command
 */
function processAgentCommand($phone, $message) {
    try {
        error_log("processAgentCommand called - Phone: $phone, Message: $message");
        $messageLower = strtolower($message);
        
        // Debug log
        error_log("Processing command - Phone: $phone, Message: $message");
        
        // Check if agent exists
        $agent = new Agent();
        $agentData = $agent->getAgentByPhone($phone);
        
        // Check if admin number
        $isAdmin = isAdminNumber($phone);
        
        // Debug log
        error_log("Agent check - IsAdmin: " . ($isAdmin ? 'YES' : 'NO') . ", HasAgentData: " . ($agentData ? 'YES' : 'NO'));
    } catch (Exception $e) {
        error_log("Error in processAgentCommand: " . $e->getMessage());
        sendWhatsAppMessage($phone, "❌ Terjadi error. Silakan coba lagi.");
        return;
    }
    
    // Commands available for agents
    error_log("Checking commands - agentData: " . ($agentData ? 'YES' : 'NO') . ", isAdmin: " . ($isAdmin ? 'YES' : 'NO'));
    
    if ($agentData || $isAdmin) {
        error_log("Command access granted - checking message: $message");
        
        // Add comprehensive error handling and logging
        try {
            // GENERATE command
            if (preg_match('/^(gen|generate)\s+(\w+)(\s+(\d+))?$/i', $message, $matches)) {
                $profile = $matches[2];
                $quantity = isset($matches[4]) ? intval($matches[4]) : 1;
                
                if ($isAdmin) {
                    generateVoucherAdmin($phone, $profile, $quantity);
                } else {
                    generateVoucherAgent($phone, $agentData, $profile, $quantity);
                }
            }
            // SALDO command
            elseif (preg_match('/^(saldo|balance|cek\s*saldo)$/i', $messageLower)) {
                if ($isAdmin) {
                    sendAdminInfo($phone);
                } else {
                    checkBalance($phone, $agentData);
                }
            }
            // TRANSAKSI command
            elseif (preg_match('/^(transaksi|history|riwayat)(\s+(\d+))?$/i', $message, $matches)) {
                $limit = isset($matches[3]) ? intval($matches[3]) : 10;
                checkTransactions($phone, $agentData, $limit);
            }
            // HARGA command
            elseif (preg_match('/^(harga|price|paket)$/i', $messageLower)) {
                if ($agentData) {
                    showAgentPrices($phone, $agentData);
                } else {
                    showAllPrices($phone);
                }
            }
            // Cek Tagihan billing command
            elseif (preg_match('/^(cek\s+tagihan|tagihan)\s+(.+)$/i', $message, $matches)) {
                if ($agentData) {
                    $keyword = trim($matches[2]);
                    handleBillingCheckCommand($phone, $agentData, $keyword);
                } else {
                    sendWhatsAppMessage($phone, formatMessage("❌ Perintah ini hanya untuk agent terdaftar."));
                }
            }
            // Bayar tagihan billing command
            elseif (preg_match('/^bayar\s+(.+)$/i', $message, $matches)) {
                if ($agentData) {
                    $keyword = trim($matches[1]);
                    handleBillingPaymentCommand($phone, $agentData, $keyword);
                } else {
                    sendWhatsAppMessage($phone, formatMessage("❌ Perintah ini hanya untuk agent terdaftar."));
                }
            }
            // TOPUP REQUEST command
            elseif (preg_match('/^topup\s+(\d+)$/i', $message, $matches)) {
                if ($agentData) {
                    $amount = intval($matches[1]);
                    requestTopup($phone, $agentData, $amount);
                }
            }
            // PULSA command (Digiflazz purchase)
            elseif (preg_match('/^pulsa\s+(\S+)\s+([0-9+\-]+)(?:\s+(.+))?$/i', $message, $matches)) {
                error_log("PULSA command detected - Phone: $phone, Message: $message");
                error_log("PULSA matches: " . json_encode($matches));
                
                $sku = strtoupper(trim($matches[1]));
                $customerNumberInput = trim($matches[2]);
                $customerNameInput = isset($matches[3]) ? trim($matches[3]) : '';
                
                error_log("PULSA parsed - SKU: $sku, CustomerNo: $customerNumberInput, CustomerName: $customerNameInput");
                error_log("PULSA user status - IsAdmin: " . ($isAdmin ? 'YES' : 'NO') . ", HasAgentData: " . ($agentData ? 'YES' : 'NO'));
                
                if ($isAdmin) {
                    error_log("Processing PULSA for ADMIN - Phone: $phone");
                    // Admin can use Digiflazz without balance deduction
                    processDigiflazzPurchaseAdmin($phone, null, $sku, $customerNumberInput, $customerNameInput);
                } elseif ($agentData) {
                    error_log("Processing PULSA for AGENT - Phone: $phone");
                    // Regular agents use normal Digiflazz with balance deduction
                    processDigiflazzPurchaseCommand($phone, $agentData, $sku, $customerNumberInput, $customerNameInput);
                } else {
                    error_log("PULSA command rejected - User not authorized");
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk agent terdaftar atau admin.");
                }
            }
            // SALES REPORT command
            elseif (preg_match('/^(laporan|report|sales)(\s+(today|week|month))?$/i', $message, $matches)) {
                if ($agentData) {
                    $period = isset($matches[3]) ? strtolower($matches[3]) : 'today';
                    sendSalesReport($phone, $agentData, $period);
                }
            }
            // BROADCAST command (admin only)
            elseif (preg_match('/^broadcast\s+(.+)$/is', $message, $matches)) {
                if ($agentData) {
                    $broadcastMessage = $matches[1];
                    broadcastMessage($phone, $agentData, $broadcastMessage);
                }
            }
            // ADDSALDO command (admin only)
            elseif (preg_match('/^addsaldo\s+(.+?)\s+(\d+)$/i', $message, $matches)) {
                if ($isAdmin) {
                    $agentIdentifier = trim($matches[1]);
                    $amount = intval($matches[2]);
                    addAgentBalance($phone, $agentIdentifier, $amount);
                } else {
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk admin.");
                }
            }
            // ADDAGENT command (admin only)
            elseif (preg_match('/^(addagent|register)\s+(.+?)\s+(\d+)$/i', $message, $matches)) {
                if ($isAdmin) {
                    $agentName = trim($matches[2]);
                    $agentPhone = $matches[3];
                    registerNewAgent($phone, $agentName, $agentPhone);
                } else {
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk admin.");
                }
            }
            // DISABLE command (admin only)
            elseif (preg_match('/^disable\s+(.+)$/i', $message, $matches)) {
                if ($isAdmin) {
                    $agentIdentifier = trim($matches[1]);
                    disableAgent($phone, $agentIdentifier);
                } else {
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk admin.");
                }
            }
            // ENABLE command (admin only)
            elseif (preg_match('/^enable\s+(.+)$/i', $message, $matches)) {
                if ($isAdmin) {
                    $agentIdentifier = trim($matches[1]);
                    enableAgent($phone, $agentIdentifier);
                } else {
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk admin.");
                }
            }
            // LISTAGENT command (admin only)
            elseif (preg_match('/^(listagent|agents|daftaragent)$/i', $messageLower)) {
                if ($isAdmin) {
                    listAllAgents($phone);
                } else {
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk admin.");
                }
            }
            // INFOAGENT command (admin only)
            elseif (preg_match('/^infoagent\s+(.+)$/i', $message, $matches)) {
                if ($isAdmin) {
                    $agentIdentifier = trim($matches[1]);
                    showAgentInfo($phone, $agentIdentifier);
                } else {
                    sendWhatsAppMessage($phone, "❌ Perintah ini hanya untuk admin.");
                }
            }
            // HELP command
            elseif (preg_match('/^(help|bantuan|\?)$/i', $messageLower)) {
                error_log("HELP command detected - IsAdmin: " . ($isAdmin ? 'YES' : 'NO') . " - Phone: $phone - Message: $message");
                if ($isAdmin) {
                    error_log("Sending admin help to: $phone");
                    sendAdminHelp($phone);
                } else {
                    error_log("Sending agent help to: $phone");
                    sendAgentHelp($phone);
                }
            }
            // Unknown command
            else {
                error_log("Unknown command received - Phone: $phone, Message: $message");
                $reply = "❌ Perintah tidak dikenali.\n\n";
                $reply .= "Ketik *HELP* untuk melihat daftar perintah.";
                sendWhatsAppMessage($phone, $reply);
            }
        } catch (Exception $e) {
            error_log("Exception in command processing: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            sendWhatsAppMessage($phone, "❌ Terjadi kesalahan saat memproses perintah. Silakan coba lagi.");
        }
    }
    // Not registered
    else {
        $reply = "❌ Nomor Anda belum terdaftar sebagai agent.\n\n";
        $reply .= "Silakan hubungi admin untuk pendaftaran.";
        sendWhatsAppMessage($phone, $reply);
    }
}

/**
 * Check if admin number
 */
function isAdminNumber($phone) {
    // Get admin numbers from database or config
    $db = getDBConnection();
    $stmt = $db->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
    $result = $stmt->fetch();
    
    if ($result) {
        $adminNumbers = explode(',', $result['setting_value']);
        $adminNumbers = array_map('trim', $adminNumbers);
        return in_array($phone, $adminNumbers);
    }
    
    return false;
}

/**
 * Generate voucher for agent
 */
function generateVoucherAgent($phone, $agentData, $profileName, $quantity) {
    global $data;
    
    if ($agentData['status'] != 'active') {
        sendWhatsAppMessage($phone, "❌ Akun agent Anda tidak aktif.\nHubungi admin untuk informasi lebih lanjut.");
        return;
    }
    
    // Get agent price
    $agent = new Agent();
    $priceData = $agent->getAgentPrice($agentData['id'], $profileName);
    
    if (!$priceData) {
        sendWhatsAppMessage($phone, "❌ Harga untuk profile *$profileName* belum diset.\nHubungi admin.");
        return;
    }
    
    $buyPrice = $priceData['buy_price'];
    $totalCost = $buyPrice * $quantity;
    
    // Check balance
    if ($agentData['balance'] < $totalCost) {
        $reply = "❌ *SALDO TIDAK CUKUP*\n\n";
        $reply .= "Saldo Anda: Rp " . number_format($agentData['balance'], 0, ',', '.') . "\n";
        $reply .= "Dibutuhkan: Rp " . number_format($totalCost, 0, ',', '.') . "\n";
        $reply .= "Kurang: Rp " . number_format($totalCost - $agentData['balance'], 0, ',', '.') . "\n\n";
        $reply .= "Silakan topup saldo terlebih dahulu.";
        sendWhatsAppMessage($phone, $reply);
        return;
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
        sendWhatsAppMessage($phone, "❌ Sistem sedang maintenance. Coba lagi nanti.");
        return;
    }
    
    // Connect to MikroTik
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ Gagal terhubung ke server. Coba lagi nanti.");
        return;
    }
    
    // Generate vouchers
    $voucherGen = new VoucherGenerator();
    $generatedVouchers = [];
    $successCount = 0;
    
    for ($i = 0; $i < $quantity; $i++) {
        $voucher = $voucherGen->generateVoucher();
        $username = $voucher['username'];
        $password = $voucher['password'];
        $comment = 'vc-Agent-' . $agentData['agent_code'] . '-' . date('dmy');
        
        // Add to MikroTik
        $API->comm("/ip/hotspot/user/add", array(
            "server" => "all",
            "name" => $username,
            "password" => $password,
            "profile" => $profileName,
            "comment" => $comment,
        ));
        
        // Deduct balance
        $deductResult = $agent->deductBalance(
            $agentData['id'],
            $buyPrice,
            $profileName,
            $username,
            'Generate via WhatsApp'
        );
        
        if ($deductResult['success']) {
            // Save to agent_vouchers
            $db = getDBConnection();
            $sql = "INSERT INTO agent_vouchers (agent_id, transaction_id, username, password, profile_name, buy_price, sell_price, sent_via) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'whatsapp')";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $agentData['id'],
                $deductResult['transaction_id'],
                $username,
                $password,
                $profileName,
                $buyPrice,
                $priceData['sell_price']
            ]);
            
            $generatedVouchers[] = [
                'username' => $username,
                'password' => $password
            ];
            
            $successCount++;
        }
    }
    
    $API->disconnect();
    
    // Send vouchers
    if ($successCount > 0) {
        $agentData = $agent->getAgentById($agentData['id']); // Refresh balance
        
        $content = "✅ *VOUCHER BERHASIL DI-GENERATE*\n\n";
        $content .= "Profile: *$profileName*\n";
        $content .= "Jumlah: $successCount voucher\n";
        $content .= "Total: Rp " . number_format($totalCost, 0, ',', '.') . "\n\n";
        $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($generatedVouchers as $index => $v) {
            $content .= "*Voucher #" . ($index + 1) . "*\n";
            $content .= "Username: `" . $v['username'] . "`\n";
            $content .= "Password: `" . $v['password'] . "`\n\n";
        }
        
        $content .= "━━━━━━━━━━━━━━━━━━━━\n";
        $content .= "💰 Saldo Anda: Rp " . number_format($agentData['balance'], 0, ',', '.');
        
        $reply = formatMessage($content);
        sendWhatsAppMessage($phone, $reply);
    } else {
        sendWhatsAppMessage($phone, "❌ Gagal generate voucher. Silakan coba lagi.");
    }
}

/**
 * Generate voucher for admin (no balance check)
 */
function generateVoucherAdmin($phone, $profileName, $quantity) {
    global $data;
    
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
        sendWhatsAppMessage($phone, "❌ Sistem sedang maintenance.");
        return;
    }
    
    // Connect to MikroTik
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ Gagal terhubung ke server.");
        return;
    }
    
    // Generate vouchers
    $voucherGen = new VoucherGenerator();
    $generatedVouchers = [];
    
    for ($i = 0; $i < $quantity; $i++) {
        $voucher = $voucherGen->generateVoucher();
        $username = $voucher['username'];
        $password = $voucher['password'];
        $comment = 'vc-Admin-WA-' . date('dmy');
        
        // Add to MikroTik
        $API->comm("/ip/hotspot/user/add", array(
            "server" => "all",
            "name" => $username,
            "password" => $password,
            "profile" => $profileName,
            "comment" => $comment,
        ));
        
        $generatedVouchers[] = [
            'username' => $username,
            'password' => $password
        ];
    }
    
    $API->disconnect();
    
    // Send vouchers
    $content = "✅ *VOUCHER ADMIN*\n\n";
    $content .= "Profile: *$profileName*\n";
    $content .= "Jumlah: $quantity voucher\n\n";
    $content .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($generatedVouchers as $index => $v) {
        $content .= "*Voucher #" . ($index + 1) . "*\n";
        $content .= "Username: `" . $v['username'] . "`\n";
        $content .= "Password: `" . $v['password'] . "`\n\n";
    }
    
    $content .= "━━━━━━━━━━━━━━━━━━━━\n";
    $content .= "🔑 *ADMIN ACCESS* - No balance deduction";
    
    $reply = formatMessage($content);
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Handle billing check command
 */
function handleBillingCheckCommand($phone, $agentData, $keyword) {
    $customers = findBillingCustomers($keyword);

    if (empty($customers)) {
        sendWhatsAppMessage($phone, formatMessage("❌ Pelanggan dengan data *{$keyword}* tidak ditemukan."));
        return;
    }

    if (count($customers) > 1) {
        $reply = "📋 *Ditemukan lebih dari satu pelanggan*\n\n";
        foreach ($customers as $index => $customer) {
            $reply .= ($index + 1) . ". " . ($customer['name'] ?? '-') . "\n";
            if (!empty($customer['service_number'])) {
                $reply .= "   • Service: `" . $customer['service_number'] . "`\n";
            }
            if (!empty($customer['phone'])) {
                $reply .= "   • Telepon: " . $customer['phone'] . "\n";
            }
            if (!empty($customer['genieacs_pppoe_username'])) {
                $reply .= "   • PPPoE: `" . $customer['genieacs_pppoe_username'] . "`\n";
            }
            $reply .= "\n";
        }
        $reply .= "Gunakan service number/PPPoE agar lebih spesifik.\n";
        sendWhatsAppMessage($phone, formatMessage($reply));
        return;
    }

    $customer = $customers[0];
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT id, period, due_date, amount, status FROM billing_invoices WHERE customer_id = :cid AND status IN ('unpaid','overdue') ORDER BY due_date ASC, id ASC");
    $stmt->execute([':cid' => $customer['id']]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reply  = "📄 *TAGIHAN BILLING*\n\n";
    $reply .= "Nama     : *" . ($customer['name'] ?? '-') . "*\n";
    $reply .= "Paket    : " . ($customer['profile_name'] ?? '-') . "\n";
    if (!empty($customer['service_number'])) {
        $reply .= "Service  : `" . $customer['service_number'] . "`\n";
    }
    if (!empty($customer['genieacs_pppoe_username'])) {
        $reply .= "PPPoE    : `" . $customer['genieacs_pppoe_username'] . "`\n";
    }
    if (!empty($customer['phone'])) {
        $reply .= "Telepon  : " . $customer['phone'] . "\n";
    }
    $reply .= "Status   : " . strtoupper($customer['status'] ?? 'unknown') . "\n\n";

    if (empty($invoices)) {
        $reply .= "✅ Tidak ada tagihan yang belum dibayar.\n";
        $reply .= "\nKetik *BAYAR {keyword}* untuk memproses pembayaran jika ada tagihan baru.";
        sendWhatsAppMessage($phone, formatMessage($reply));
        return;
    }

    $totalDue = 0;
    foreach ($invoices as $invoice) {
        $totalDue += (float)$invoice['amount'];
        $dueDate = !empty($invoice['due_date']) ? date('d M Y', strtotime($invoice['due_date'])) : '-';
        $reply .= "• Periode  : " . ($invoice['period'] ?? '-') . "\n";
        $reply .= "  Jatuh Tempo : {$dueDate}\n";
        $reply .= "  Status      : " . strtoupper($invoice['status']) . "\n";
        $reply .= "  Tagihan     : " . formatRupiah($invoice['amount']) . "\n\n";
    }

    $reply .= "Total Tagihan: *" . formatRupiah($totalDue) . "*\n";
    $reply .= "\nKetik *BAYAR {$keyword}* untuk memproses pembayaran tagihan pertama.";

    sendWhatsAppMessage($phone, formatMessage($reply));
}

/**
 * Handle billing payment command
 */
function handleBillingPaymentCommand($phone, $agentData, $keyword) {
    if ($agentData['status'] !== 'active') {
        sendWhatsAppMessage($phone, formatMessage("❌ Akun agent Anda tidak aktif. Hubungi admin."));
        return;
    }

    $customers = findBillingCustomers($keyword);

    if (empty($customers)) {
        sendWhatsAppMessage($phone, formatMessage("❌ Pelanggan dengan data *{$keyword}* tidak ditemukan."));
        return;
    }

    if (count($customers) > 1) {
        $reply = "📋 *Ditemukan lebih dari satu pelanggan*\n\n";
        foreach ($customers as $index => $customer) {
            $reply .= ($index + 1) . ". " . ($customer['name'] ?? '-') . "\n";
            if (!empty($customer['service_number'])) {
                $reply .= "   • Service: `" . $customer['service_number'] . "`\n";
            }
            if (!empty($customer['phone'])) {
                $reply .= "   • Telepon: " . $customer['phone'] . "\n";
            }
            if (!empty($customer['genieacs_pppoe_username'])) {
                $reply .= "   • PPPoE: `" . $customer['genieacs_pppoe_username'] . "`\n";
            }
            $reply .= "\n";
        }
        $reply .= "Gunakan service number/PPPoE agar lebih spesifik sebelum bayar.";
        sendWhatsAppMessage($phone, formatMessage($reply));
        return;
    }

    $customer = $customers[0];
    $pdo = getDBConnection();
    $invoiceStmt = $pdo->prepare("SELECT * FROM billing_invoices WHERE customer_id = :cid AND status IN ('unpaid','overdue') ORDER BY due_date ASC, id ASC LIMIT 1");
    $invoiceStmt->execute([':cid' => $customer['id']]);
    $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        sendWhatsAppMessage($phone, formatMessage("✅ Tidak ada tagihan yang perlu dibayar untuk pelanggan ini."));
        return;
    }

    $billingService = new BillingService();
    $agent = new Agent();
    $agentLatest = $agent->getAgentById($agentData['id']);
    $agentSettings = $agent->getAgentSettings($agentData['id']);

    $invoiceAmount = (float)$invoice['amount'];
    $commissionAmount = isset($agentLatest['commission_amount']) ? max(0.0, (float)$agentLatest['commission_amount']) : 0.0;
    $discountAmount = min($commissionAmount, $invoiceAmount);
    $feeEnabled = $agentSettings['billing_payment_fee_enabled'] ?? '0';
    $feePercent = (float)($agentSettings['billing_payment_fee_percent'] ?? 0);

    $fee = 0.0;
    if ($discountAmount > 0) {
        $fee = -$discountAmount;
    } elseif ($feeEnabled === '1' && $feePercent > 0) {
        $fee = ($invoiceAmount * $feePercent / 100);
    }

    try {
        $paymentResult = $billingService->payInvoiceWithAgentBalance($agentLatest['id'], (int)$invoice['id'], $fee);
    } catch (Exception $e) {
        sendWhatsAppMessage($phone, formatMessage("❌ Pembayaran gagal: " . $e->getMessage()));
        return;
    }

    if (!$paymentResult['success']) {
        $errorMessage = $paymentResult['message'] ?? 'Pembayaran gagal diproses.';
        sendWhatsAppMessage($phone, formatMessage("❌ Pembayaran gagal: {$errorMessage}"));
        return;
    }

    $paidInvoice = $billingService->getInvoiceById((int)$invoice['id']);
    $dueDateFormatted = !empty($paidInvoice['due_date']) ? date('d M Y', strtotime($paidInvoice['due_date'])) : '-';
    $paidAt = !empty($paidInvoice['paid_at']) ? date('d M Y H:i', strtotime($paidInvoice['paid_at'])) : date('d M Y H:i');
    $profileName = '-';
    if (!empty($paidInvoice['profile_snapshot'])) {
        $snapshot = json_decode($paidInvoice['profile_snapshot'], true);
        if (is_array($snapshot) && !empty($snapshot['profile_name'])) {
            $profileName = $snapshot['profile_name'];
        }
    }
    if ($profileName === '-' && isset($customer['profile_name']) && $customer['profile_name']) {
        $profileName = $customer['profile_name'];
    }

    $amountFormatted = number_format($invoiceAmount, 0, ',', '.');
    $deductedTotal = $invoiceAmount + $fee;
    $totalFormatted = number_format($deductedTotal, 0, ',', '.');
    $discountFormatted = $discountAmount > 0 ? number_format($discountAmount, 0, ',', '.') : null;
    $balanceAfterFormatted = number_format($paymentResult['balance_after'], 0, ',', '.');

    $reply  = "✅ *PEMBAYARAN BERHASIL*\n\n";
    $reply .= "Pelanggan : *" . ($customer['name'] ?? '-') . "*\n";
    $reply .= "Paket     : {$profileName}\n";
    $reply .= "Periode   : " . ($paidInvoice['period'] ?? '-') . "\n";
    $reply .= "Tagihan   : Rp {$amountFormatted}\n";
    if ($discountFormatted) {
        $reply .= "Komisi    : Rp {$discountFormatted}\n";
    }
    if ($fee > 0) {
        $reply .= "Biaya     : Rp " . number_format($fee, 0, ',', '.') . "\n";
    }
    $reply .= "Saldo Dipotong : *Rp {$totalFormatted}*\n";
    $reply .= "Saldo Sisa     : Rp {$balanceAfterFormatted}\n";
    $reply .= "Jatuh Tempo    : {$dueDateFormatted}\n";
    $reply .= "Tanggal Bayar  : {$paidAt}\n";
    $reply .= "\nStruk telah dikirim ke pelanggan.";

    sendWhatsAppMessage($phone, formatMessage($reply));

    if (!empty($customer['phone'])) {
        $notification = new WhatsAppNotification();
        $payload = [
            'invoice_id' => (int)$paidInvoice['id'],
            'customer_name' => $customer['name'] ?? '-',
            'package_name' => $profileName,
            'period' => $paidInvoice['period'] ?? '-',
            'due_date' => $dueDateFormatted,
            'status' => $paidInvoice['status'] ?? 'paid',
            'amount' => $invoiceAmount,
            'amount_formatted' => $amountFormatted,
            'total_paid_formatted' => $totalFormatted,
            'balance_after_formatted' => $balanceAfterFormatted,
            'agent_name' => $agentLatest['agent_name'] ?? '-',
            'service_number' => $customer['service_number'] ?? '-',
            'pppoe_username' => $customer['genieacs_pppoe_username'] ?? '-',
            'paid_at' => $paidAt,
        ];
        $notification->notifyInvoicePaidByAgent($customer['phone'], $payload);
    }
}

/**
 * Find billing customers by keyword
 */
function findBillingCustomers($keyword, $limit = 5) {
    $keyword = trim($keyword);
    if ($keyword === '') {
        return [];
    }

    $pdo = getDBConnection();
    $results = [];
    $added = [];

    // Exact match by service number, PPPoE username, or phone
    $stmt = $pdo->prepare("SELECT bc.*, bp.profile_name FROM billing_customers bc LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id WHERE bc.service_number = :kw OR bc.genieacs_pppoe_username = :kw OR bc.phone = :kw LIMIT :limit");
    $stmt->bindValue(':kw', $keyword);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isset($added[$row['id']])) {
            $results[] = $row;
            $added[$row['id']] = true;
        }
    }

    // Match numeric phone stripped
    $digits = preg_replace('/\D+/', '', $keyword);
    if ($digits !== '' && count($results) < $limit) {
        $stmt = $pdo->prepare("SELECT bc.*, bp.profile_name FROM billing_customers bc LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id WHERE REPLACE(REPLACE(REPLACE(REPLACE(bc.phone, ' ', ''), '-', ''), '+', ''), '.', '') = :digits LIMIT :limit");
        $stmt->bindValue(':digits', $digits);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($added[$row['id']])) {
                $results[] = $row;
                $added[$row['id']] = true;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
    }

    if (count($results) < $limit) {
        $stmt = $pdo->prepare("SELECT bc.*, bp.profile_name FROM billing_customers bc LEFT JOIN billing_profiles bp ON bc.profile_id = bp.id WHERE bc.name LIKE :name ORDER BY bc.name ASC LIMIT :limit");
        $stmt->bindValue(':name', '%' . $keyword . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($added[$row['id']])) {
                $results[] = $row;
                $added[$row['id']] = true;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
    }

    return array_slice($results, 0, $limit);
}

/**
 * Check agent balance
 */
function checkBalance($phone, $agentData) {
    $agent = new Agent();
    $summary = $agent->getAgentSummary($agentData['id']);
    
    $reply = "💰 *INFORMASI SALDO*\n\n";
    $reply .= "Agent: *" . $agentData['agent_name'] . "*\n";
    $reply .= "Kode: " . $agentData['agent_code'] . "\n";
    $reply .= "Level: " . ucfirst($agentData['level']) . "\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $reply .= "💵 Saldo: *Rp " . number_format($agentData['balance'], 0, ',', '.') . "*\n\n";
    
    if ($summary) {
        $reply .= "📊 *Statistik:*\n";
        $reply .= "• Total Voucher: " . $summary['total_vouchers'] . "\n";
        $reply .= "• Voucher Terpakai: " . $summary['used_vouchers'] . "\n";
        $reply .= "• Total Topup: Rp " . number_format($summary['total_topup'], 0, ',', '.') . "\n";
        $reply .= "• Total Pengeluaran: Rp " . number_format($summary['total_spent'], 0, ',', '.') . "\n";
    }
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Check transactions
 */
function checkTransactions($phone, $agentData, $limit) {
    $agent = new Agent();
    $transactions = $agent->getTransactions($agentData['id'], $limit);
    
    if (empty($transactions)) {
        sendWhatsAppMessage($phone, "📋 Belum ada transaksi.");
        return;
    }
    
    $reply = "📋 *RIWAYAT TRANSAKSI*\n";
    $reply .= "(" . count($transactions) . " transaksi terakhir)\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($transactions as $trx) {
        $date = date('d/m H:i', strtotime($trx['created_at']));
        $type = ucfirst($trx['transaction_type']);
        $amount = number_format($trx['amount'], 0, ',', '.');
        $sign = $trx['transaction_type'] == 'topup' ? '+' : '-';
        
        $reply .= "*$date* | $type\n";
        $reply .= "$sign Rp $amount\n";
        
        if ($trx['profile_name']) {
            $reply .= "Profile: " . $trx['profile_name'] . "\n";
        }
        if ($trx['voucher_username']) {
            $reply .= "User: " . $trx['voucher_username'] . "\n";
        }
        
        $reply .= "\n";
    }
    
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
    $reply .= "💰 Saldo: Rp " . number_format($agentData['balance'], 0, ',', '.');
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Show agent prices
 */
function showAgentPrices($phone, $agentData) {
    $agent = new Agent();
    $prices = $agent->getAllAgentPrices($agentData['id']);
    
    if (empty($prices)) {
        sendWhatsAppMessage($phone, "❌ Harga belum diset. Hubungi admin.");
        return;
    }
    
    $reply = "💵 *DAFTAR HARGA AGENT*\n\n";
    $reply .= "Agent: " . $agentData['agent_name'] . "\n";
    $reply .= "Kode: " . $agentData['agent_code'] . "\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($prices as $price) {
        $profit = $price['sell_price'] - $price['buy_price'];
        
        $reply .= "*" . $price['profile_name'] . "*\n";
        $reply .= "Harga Beli: Rp " . number_format($price['buy_price'], 0, ',', '.') . "\n";
        $reply .= "Harga Jual: Rp " . number_format($price['sell_price'], 0, ',', '.') . "\n";
        $reply .= "Profit: Rp " . number_format($profit, 0, ',', '.') . "\n\n";
    }
    
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
    $reply .= "Cara generate:\n";
    $reply .= "*GEN <PROFILE> <QTY>*\n";
    $reply .= "Contoh: GEN 3JAM 5";
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Show all prices (for admin)
 */
function showAllPrices($phone) {
    global $data;
    
    $sessions = array_keys($data);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendWhatsAppMessage($phone, "❌ Sistem sedang maintenance.");
        return;
    }
    
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendWhatsAppMessage($phone, "❌ Gagal terhubung ke server.");
        return;
    }
    
    $profiles = $API->comm("/ip/hotspot/user/profile/print");
    $API->disconnect();
    
    $reply = "📋 *DAFTAR PROFILE*\n\n";
    
    foreach ($profiles as $profile) {
        if ($profile['name'] == 'default' || $profile['name'] == 'default-encryption') continue;
        $reply .= "• " . $profile['name'] . "\n";
    }
    
    $reply .= "\n━━━━━━━━━━━━━━━━━━━━\n";
    $reply .= "Cara generate:\n";
    $reply .= "*GEN <PROFILE> <QTY>*";
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Send agent help
 */
function sendAgentHelp($phone) {
    $reply = "🤖 *BANTUAN AGENT BOT*\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $reply .= "*Perintah yang tersedia:*\n\n";
    
    $reply .= "🎫 *GEN <PROFILE> <QTY>*\n";
    $reply .= "Generate voucher\n";
    $reply .= "Contoh: GEN 3JAM 5\n\n";
    
    $reply .= "💰 *SALDO*\n";
    $reply .= "Cek saldo dan statistik\n\n";
    
    $reply .= "📋 *TRANSAKSI <JUMLAH>*\n";
    $reply .= "Lihat riwayat transaksi\n";
    $reply .= "Contoh: TRANSAKSI 20\n\n";
    
    $reply .= "💵 *HARGA*\n";
    $reply .= "Lihat daftar harga\n\n";
    
    $reply .= "💳 *TOPUP <JUMLAH>*\n";
    $reply .= "Request topup saldo\n";
    $reply .= "Contoh: TOPUP 100000\n\n";
    
    $reply .= "📄 *CEK TAGIHAN <NAMA/NOMOR>*\n";
    $reply .= "Lihat tagihan billing pelanggan\n";
    $reply .= "Contoh: CEK TAGIHAN 08123xxxx\n\n";
    
    $reply .= "✅ *BAYAR <NAMA/NOMOR>*\n";
    $reply .= "Bayar tagihan billing pelanggan\n";
    $reply .= "Contoh: BAYAR BUDI\n\n";
    
    $reply .= "📊 *LAPORAN <PERIOD>*\n";
    $reply .= "Lihat laporan penjualan\n";
    $reply .= "Period: TODAY, WEEK, MONTH\n";
    $reply .= "Contoh: LAPORAN WEEK\n\n";
    
    $reply .= "📢 *BROADCAST <PESAN>*\n";
    $reply .= "Kirim pesan ke semua customer\n";
    $reply .= "Contoh: BROADCAST Promo hari ini!\n\n";
    
    $reply .= "📱 *PULSA <SKU> <NOMOR>*\n";
    $reply .= "Beli pulsa/data via Digiflazz\n";
    $reply .= "Contoh: PULSA TELKOM_50K 08123456789\n\n";
    
    $reply .= "❓ *HELP*\n";
    $reply .= "Tampilkan bantuan ini\n\n";
    
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
    $reply .= "_Hubungi admin jika ada kendala_";
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Send admin help
 */
function sendAdminHelp($phone) {
    error_log("sendAdminHelp called for phone: $phone");
    
    $reply = "👑 *BANTUAN ADMIN BOT*\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $reply .= "*Perintah Voucher:*\n\n";
    
    $reply .= "🎫 *GEN <PROFILE> <QTY>*\n";
    $reply .= "Generate voucher (tanpa potong saldo)\n";
    $reply .= "Contoh: GEN 3JAM 10\n\n";
    
    $reply .= "💵 *HARGA*\n";
    $reply .= "Lihat semua profile\n\n";
    
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $reply .= "*Perintah Manage Agent:*\n\n";
    
    $reply .= "👥 *LISTAGENT*\n";
    $reply .= "Lihat daftar semua agent\n\n";
    
    $reply .= "👤 *INFOAGENT <NAMA/NOMOR>*\n";
    $reply .= "Info detail agent\n";
    $reply .= "Contoh: INFOAGENT Budi\n\n";
    
    $reply .= "➕ *ADDAGENT <NAMA> <NOMOR>*\n";
    $reply .= "Daftarkan agent baru\n";
    $reply .= "Contoh: ADDAGENT Budi Santoso 628123456789\n\n";
    
    $reply .= "💰 *ADDSALDO <NAMA/NOMOR> <JUMLAH>*\n";
    $reply .= "Tambah saldo agent\n";
    $reply .= "Contoh: ADDSALDO Budi 100000\n\n";
    
    $reply .= "❌ *DISABLE <NAMA/NOMOR>*\n";
    $reply .= "Nonaktifkan agent\n";
    $reply .= "Contoh: DISABLE Budi\n\n";
    
    $reply .= "✅ *ENABLE <NAMA/NOMOR>*\n";
    $reply .= "Aktifkan kembali agent\n";
    $reply .= "Contoh: ENABLE Budi\n\n";
    
    $reply .= "📱 *PULSA <SKU> <NOMOR>*\n";
    $reply .= "Beli pulsa/data via Digiflazz (tanpa saldo)\n";
    $reply .= "Contoh: PULSA TELKOM_50K 08123456789\n\n";
    
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
    $reply .= "🔑 *ADMIN ACCESS ACTIVE*";
    
    error_log("Sending admin help message to: $phone");
    error_log("Message content: " . $reply);
    $result = sendWhatsAppMessage($phone, $reply);
    error_log("Send result: " . json_encode($result));
    
    return $result;
}

/**
 * Send admin info
 */
function sendAdminInfo($phone) {
    $reply = "👑 *ADMIN ACCESS*\n\n";
    $reply .= "Status: ✅ Active\n";
    $reply .= "Privilege: Unlimited\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $reply .= "Anda dapat generate voucher tanpa batas.\n";
    $reply .= "Tidak ada pemotongan saldo.\n";
    $reply .= "Beli pulsa/data via Digiflazz tanpa saldo.\n\n";
    $reply .= "Ketik *HELP* untuk perintah.";
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Request topup
 */
function requestTopup($phone, $agentData, $amount) {
    if ($amount < 10000) {
        sendWhatsAppMessage($phone, "❌ Minimal topup Rp 10,000");
        return;
    }
    
    // Save topup request
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO agent_topup_requests (agent_id, amount, payment_method, status, agent_notes) 
        VALUES (?, ?, 'transfer', 'pending', 'Request via WhatsApp')
    ");
    $stmt->execute([$agentData['id'], $amount]);
    
    // Notify agent
    $reply = "✅ *REQUEST TOPUP DIKIRIM*\n\n";
    $reply .= "Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n\n";
    $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $reply .= "Silakan transfer ke:\n";
    $reply .= "BCA: 1234567890\n";
    $reply .= "a.n. Nama Pemilik\n\n";
    $reply .= "Setelah transfer, kirim bukti ke admin.\n";
    $reply .= "Request Anda akan diproses segera.";
    
    sendWhatsAppMessage($phone, $reply);
    
    // Notify admin
    include_once('../lib/WhatsAppNotification.class.php');
    $notification = new WhatsAppNotification();
    $notification->notifyTopupRequest($agentData['id'], $amount);
}

/**
 * Process Digiflazz purchase command
 */
function processDigiflazzPurchaseCommand($phone, $agentData, $sku, $customerNumberInput, $customerNameInput = '') {
    try {
        error_log("processDigiflazzPurchaseCommand called - Phone: $phone, SKU: $sku");
        
        $agent = new Agent();

        if ($agentData['status'] !== 'active') {
            error_log("processDigiflazzPurchaseCommand - Agent not active");
            sendWhatsAppMessage($phone, "❌ Akun agent Anda tidak aktif. Hubungi admin untuk mengaktifkan kembali.");
            return;
        }

        $customerNo = preg_replace('/\D+/', '', $customerNumberInput);
        if (strlen($customerNo) < 5) {
            error_log("processDigiflazzPurchaseCommand - Invalid customer number: $customerNumberInput");
            sendWhatsAppMessage($phone, "❌ Nomor tujuan tidak valid. Pastikan hanya berisi angka dan minimal 5 digit.");
            return;
        }

        try {
            $digiflazzClient = new DigiflazzClient();
            if (!$digiflazzClient->isEnabled()) {
                error_log("processDigiflazzPurchaseCommand - Digiflazz not enabled");
                sendWhatsAppMessage($phone, "❌ Integrasi Digiflazz belum dikonfigurasi. Hubungi administrator.");
                return;
            }
            $digiflazzSettings = $digiflazzClient->getSettings();
            $defaultMarkupNominal = isset($digiflazzSettings['default_markup_nominal']) ? (int)$digiflazzSettings['default_markup_nominal'] : 0;
        } catch (Exception $e) {
            error_log("processDigiflazzPurchaseCommand - Exception: " . $e->getMessage());
            sendWhatsAppMessage($phone, "❌ Gagal menginisialisasi Digiflazz: " . $e->getMessage());
            return;
        }

        $pdo = getDBConnection();
        // Cari produk dengan buyer_sku_code (case-insensitive)
        $stmt = $pdo->prepare('SELECT * FROM digiflazz_products WHERE UPPER(buyer_sku_code) = UPPER(:sku) AND status = "active" LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            // Jika tidak ditemukan berdasarkan SKU, cari berdasarkan nama produk
            $stmt = $pdo->prepare('SELECT * FROM digiflazz_products WHERE UPPER(product_name) = UPPER(:name) AND status = "active" LIMIT 1');
            $stmt->execute([':name' => $sku]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$product) {
            error_log("processDigiflazzPurchaseAdmin - Product not found: $sku");
            $reply = "❌ Produk dengan SKU *{$sku}* tidak ditemukan atau tidak aktif.\n";
            $reply .= "Gunakan perintah: *PULSA <SKU> <NO_HP> [Nama]*\n";
            $reply .= "Cek katalog di menu Digital Products untuk melihat SKU yang tersedia.";
            sendWhatsAppMessage($phone, $reply);
            return;
        }
        
        error_log("processDigiflazzPurchaseAdmin - Product found: " . $product['product_name']);

        $costPrice = (int)$product['price'];
        if ($costPrice <= 0 && isset($product['buyer_price'])) {
            $costPrice = (int)$product['buyer_price'];
        }

        if ($costPrice <= 0) {
            error_log("processDigiflazzPurchaseCommand - Invalid product price: $sku");
            sendWhatsAppMessage($phone, "❌ Harga produk tidak valid untuk SKU {$sku}. Coba produk lain atau hubungi admin.");
            return;
        }

        $sellPrice = (int)($product['seller_price'] ?? 0);
        if ($sellPrice <= 0) {
            $sellPrice = $costPrice;
            if (!empty($defaultMarkupNominal) && $defaultMarkupNominal > 0) {
                $sellPrice += $defaultMarkupNominal;
            }
        }

        if ($sellPrice < $costPrice) {
            $sellPrice = $costPrice;
        }

        $agentLatest = $agent->getAgentById($agentData['id']);
        if (!$agentLatest || $agentLatest['status'] !== 'active') {
            error_log("processDigiflazzPurchaseCommand - Agent data not found or not active");
            sendWhatsAppMessage($phone, "❌ Akun agent tidak ditemukan atau tidak aktif.");
            return;
        }

        if ($agentLatest['balance'] < $sellPrice) {
            error_log("processDigiflazzPurchaseCommand - Insufficient balance. Balance: " . $agentLatest['balance'] . ", Required: $sellPrice");
            $reply = "❌ *SALDO TIDAK CUKUP*\n\n";
            $reply .= "Saldo Anda: " . formatRupiah($agentLatest['balance']) . "\n";
            $reply .= "Harga Produk: " . formatRupiah($sellPrice) . "\n";
            $reply .= "Kurang: " . formatRupiah($sellPrice - $agentLatest['balance']) . "\n\n";
            $reply .= "Silakan topup saldo terlebih dahulu.";
            sendWhatsAppMessage($phone, $reply);
            return;
        }

        $refId = $digiflazzClient->generateRefId('WA' . $agentLatest['agent_code']);

        $orderPayload = [
            'buyer_sku_code' => $product['buyer_sku_code'],
            'customer_no' => $customerNo,
            'ref_id' => $refId,
        ];

        $customerName = trim($customerNameInput);
        if ($customerName !== '') {
            $orderPayload['customer_name'] = $customerName;
        }

        try {
            error_log("processDigiflazzPurchaseCommand - Creating transaction with payload: " . json_encode($orderPayload));
            $digiflazzResponse = $digiflazzClient->createTransaction($orderPayload);
            error_log("processDigiflazzPurchaseCommand - Transaction response: " . json_encode($digiflazzResponse));
        } catch (Exception $e) {
            error_log("processDigiflazzPurchaseCommand - Transaction failed: " . $e->getMessage());
            sendWhatsAppMessage($phone, "❌ Transaksi Digiflazz gagal: " . $e->getMessage());
            return;
        }

        $deductResult = $agent->deductBalance(
            $agentLatest['id'],
            $sellPrice,
            $product['product_name'],
            $digiflazzResponse['ref_id'] ?? $refId,
            'Digiflazz order via WhatsApp',
            'digiflazz'
        );

        if (!$deductResult['success']) {
            error_log("processDigiflazzPurchaseCommand - Balance deduction failed: " . $deductResult['message']);
            sendWhatsAppMessage($phone, "❌ Gagal memotong saldo: " . $deductResult['message']);
            return;
        }

        try {
            $transactionStmt = $pdo->prepare('INSERT INTO digiflazz_transactions (
                agent_id, ref_id, buyer_sku_code, customer_no, customer_name, status, message, price, sell_price, serial_number, response
            ) VALUES (
                :agent_id, :ref_id, :sku, :customer_no, :customer_name, :status, :message, :price, :sell_price, :serial, :response
            )');

            $transactionStmt->execute([
                ':agent_id' => $agentLatest['id'],
                ':ref_id' => $digiflazzResponse['ref_id'] ?? $refId,
                ':sku' => $product['buyer_sku_code'],
                ':customer_no' => $customerNo,
                ':customer_name' => $customerName,
                ':status' => strtolower($digiflazzResponse['status'] ?? 'pending'),
                ':message' => $digiflazzResponse['message'] ?? '',
                ':price' => $costPrice,
                ':sell_price' => $sellPrice,
                ':serial' => $digiflazzResponse['sn'] ?? '',
                ':response' => json_encode($digiflazzResponse)
            ]);

            if (!empty($digiflazzResponse['sn'])) {
                $voucherStmt = $pdo->prepare('INSERT INTO agent_vouchers (
                    agent_id, transaction_id, username, password, profile_name, buy_price, sell_price, status, customer_phone, customer_name, sent_via
                ) VALUES (
                    :agent_id, :transaction_id, :username, :password, :profile_name, :buy_price, :sell_price, :status, :customer_phone, :customer_name, :sent_via
                )');
                $voucherStmt->execute([
                    ':agent_id' => $agentLatest['id'],
                    ':transaction_id' => $deductResult['transaction_id'],
                    ':username' => $digiflazzResponse['sn'],
                    ':password' => $digiflazzResponse['sn'],
                    ':profile_name' => $product['product_name'],
                    ':buy_price' => $costPrice,
                    ':sell_price' => $sellPrice,
                    ':status' => 'active',
                    ':customer_phone' => $customerNo,
                    ':customer_name' => $customerName,
                    ':sent_via' => 'whatsapp'
                ]);
            }
        } catch (Exception $dbException) {
            error_log("processDigiflazzPurchaseCommand - Database error: " . $dbException->getMessage());
            $agent->topupBalance(
                $agentLatest['id'],
                $costPrice,
                'Refund Digiflazz order failure (WhatsApp): ' . $product['product_name'],
                'system'
            );
            sendWhatsAppMessage($phone, "❌ Gagal menyimpan data transaksi. " . $dbException->getMessage());
            return;
        }

        $updatedAgent = $agent->getAgentById($agentLatest['id']);

        $notification = new WhatsAppNotification();
        $notified = $notification->notifyDigiflazzSuccess($agentLatest['id'], [
            'product_name' => $product['product_name'],
            'customer_no' => $customerNo,
            'customer_name' => $customerName,
            'status' => $digiflazzResponse['status'] ?? 'pending',
            'message' => $digiflazzResponse['message'] ?? '',
            'ref_id' => $digiflazzResponse['ref_id'] ?? $refId,
            'serial_number' => $digiflazzResponse['sn'] ?? '',
            'price' => $sellPrice
        ], $updatedAgent['balance']);

        if (!$notified) {
            $content  = "⚡ *TRANSAKSI DIGIFLAZZ*\n\n";
            $content .= "Produk : *{$product['product_name']}*\n";
            $content .= "Nomor  : `{$customerNo}`\n";
            if (!empty($customerName)) {
                $content .= "Nama   : {$customerName}\n";
            }
            $content .= "Status : *" . strtoupper($digiflazzResponse['status'] ?? 'PENDING') . "*\n";
            $content .= "Ref ID : " . ($digiflazzResponse['ref_id'] ?? $refId) . "\n";
            $content .= "Biaya  : " . formatRupiah($sellPrice) . "\n";
            if (!empty($digiflazzResponse['sn'])) {
                $content .= "SN     : `{$digiflazzResponse['sn']}`\n";
            }
            $content .= "\nSaldo tersisa: " . formatRupiah($updatedAgent['balance']);
            error_log("processDigiflazzPurchaseCommand - Sending success message");
            sendWhatsAppMessage($phone, formatMessage($content));
        }
    } catch (Exception $e) {
        error_log("processDigiflazzPurchaseCommand - Unexpected error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        sendWhatsAppMessage($phone, "❌ Terjadi kesalahan tidak terduga. Silakan coba lagi atau hubungi administrator.");
    }
}

/**
 * Process Digiflazz purchase command for admin (no balance deduction)
 */
function processDigiflazzPurchaseAdmin($phone, $adminData, $sku, $customerNumberInput, $customerNameInput = '') {
    try {
        error_log("processDigiflazzPurchaseAdmin called - Phone: $phone, SKU: $sku");
        
        // For admin, we don't check balance but still validate other requirements
        $customerNo = preg_replace('/\D+/', '', $customerNumberInput);
        if (strlen($customerNo) < 5) {
            error_log("processDigiflazzPurchaseAdmin - Invalid customer number: $customerNumberInput");
            sendWhatsAppMessage($phone, "❌ Nomor tujuan tidak valid. Pastikan hanya berisi angka dan minimal 5 digit.");
            return;
        }

        try {
            $digiflazzClient = new DigiflazzClient();
            if (!$digiflazzClient->isEnabled()) {
                error_log("processDigiflazzPurchaseAdmin - Digiflazz not enabled");
                sendWhatsAppMessage($phone, "❌ Integrasi Digiflazz belum dikonfigurasi. Hubungi administrator.");
                return;
            }
            $digiflazzSettings = $digiflazzClient->getSettings();
            $defaultMarkupNominal = isset($digiflazzSettings['default_markup_nominal']) ? (int)$digiflazzSettings['default_markup_nominal'] : 0;
        } catch (Exception $e) {
            error_log("processDigiflazzPurchaseAdmin - Exception: " . $e->getMessage());
            sendWhatsAppMessage($phone, "❌ Gagal menginisialisasi Digiflazz: " . $e->getMessage());
            return;
        }

        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT * FROM digiflazz_products WHERE buyer_sku_code = :sku AND status = "active" LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $stmt = $pdo->prepare('SELECT * FROM digiflazz_products WHERE UPPER(product_name) = :name AND status = "active" LIMIT 1');
            $stmt->execute([':name' => strtoupper($sku)]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$product) {
            error_log("processDigiflazzPurchaseAdmin - Product not found: $sku");
            $reply = "❌ Produk dengan SKU *{$sku}* tidak ditemukan atau tidak aktif.\n";
            $reply .= "Gunakan perintah: *PULSA <SKU> <NO_HP> [Nama]*\n";
            $reply .= "Cek katalog di menu Digital Products untuk melihat SKU yang tersedia.";
            sendWhatsAppMessage($phone, $reply);
            return;
        }
        
        error_log("processDigiflazzPurchaseAdmin - Product found: " . $product['product_name']);

        $costPrice = (int)$product['price'];
        if ($costPrice <= 0 && isset($product['buyer_price'])) {
            $costPrice = (int)$product['buyer_price'];
        }

        if ($costPrice <= 0) {
            error_log("processDigiflazzPurchaseAdmin - Invalid product price: $sku");
            sendWhatsAppMessage($phone, "❌ Harga produk tidak valid untuk SKU {$sku}. Coba produk lain atau hubungi admin.");
            return;
        }

        $sellPrice = (int)($product['seller_price'] ?? 0);
        if ($sellPrice <= 0) {
            $sellPrice = $costPrice;
            if (!empty($defaultMarkupNominal) && $defaultMarkupNominal > 0) {
                $sellPrice += $defaultMarkupNominal;
            }
        }

        if ($sellPrice < $costPrice) {
            $sellPrice = $costPrice;
        }

        // For admin, we don't check balance but still process the transaction
        $refId = $digiflazzClient->generateRefId('ADMIN-WA');

        $orderPayload = [
            'buyer_sku_code' => $product['buyer_sku_code'],
            'customer_no' => $customerNo,
            'ref_id' => $refId,
        ];

        $customerName = trim($customerNameInput);
        if ($customerName !== '') {
            $orderPayload['customer_name'] = $customerName;
        }

        try {
            error_log("processDigiflazzPurchaseAdmin - Creating transaction with payload: " . json_encode($orderPayload));
            $digiflazzResponse = $digiflazzClient->createTransaction($orderPayload);
            error_log("processDigiflazzPurchaseAdmin - Transaction response: " . json_encode($digiflazzResponse));
        } catch (Exception $e) {
            error_log("processDigiflazzPurchaseAdmin - Transaction failed: " . $e->getMessage());
            sendWhatsAppMessage($phone, "❌ Transaksi Digiflazz gagal: " . $e->getMessage());
            return;
        }

        // For admin, we don't deduct balance but still log the transaction
        try {
            $transactionStmt = $pdo->prepare('INSERT INTO digiflazz_transactions (
                agent_id, ref_id, buyer_sku_code, customer_no, customer_name, status, message, price, sell_price, serial_number, response
            ) VALUES (
                :agent_id, :ref_id, :sku, :customer_no, :customer_name, :status, :message, :price, :sell_price, :serial, :response
            )');

            $transactionStmt->execute([
                ':agent_id' => null, // No agent ID for admin transactions
                ':ref_id' => $digiflazzResponse['ref_id'] ?? $refId,
                ':sku' => $product['buyer_sku_code'],
                ':customer_no' => $customerNo,
                ':customer_name' => $customerName,
                ':status' => strtolower($digiflazzResponse['status'] ?? 'pending'),
                ':message' => $digiflazzResponse['message'] ?? '',
                ':price' => $costPrice,
                ':sell_price' => $sellPrice,
                ':serial' => $digiflazzResponse['sn'] ?? '',
                ':response' => json_encode($digiflazzResponse)
            ]);

            if (!empty($digiflazzResponse['sn'])) {
                // Store as admin voucher
                $voucherStmt = $pdo->prepare('INSERT INTO agent_vouchers (
                    agent_id, transaction_id, username, password, profile_name, buy_price, sell_price, status, customer_phone, customer_name, sent_via
                ) VALUES (
                    :agent_id, :transaction_id, :username, :password, :profile_name, :buy_price, :sell_price, :status, :customer_phone, :customer_name, :sent_via
                )');
                $voucherStmt->execute([
                    ':agent_id' => null, // No agent ID for admin transactions
                    ':transaction_id' => 0, // No transaction ID for admin
                    ':username' => $digiflazzResponse['sn'],
                    ':password' => $digiflazzResponse['sn'],
                    ':profile_name' => $product['product_name'],
                    ':buy_price' => $costPrice,
                    ':sell_price' => $sellPrice,
                    ':status' => 'active',
                    ':customer_phone' => $customerNo,
                    ':customer_name' => $customerName,
                    ':sent_via' => 'whatsapp_admin'
                ]);
            }
        } catch (Exception $dbException) {
            error_log("processDigiflazzPurchaseAdmin - Database error: " . $dbException->getMessage());
            sendWhatsAppMessage($phone, "❌ Gagal menyimpan data transaksi. " . $dbException->getMessage());
            return;
        }

        // Send success notification to admin
        $content  = "⚡ *TRANSAKSI DIGIFLAZZ (ADMIN)*\n\n";
        $content .= "Produk : *{$product['product_name']}*\n";
        $content .= "Nomor  : `{$customerNo}`\n";
        if (!empty($customerName)) {
            $content .= "Nama   : {$customerName}\n";
        }
        $content .= "Status : *" . strtoupper($digiflazzResponse['status'] ?? 'PENDING') . "*\n";
        $content .= "Ref ID : " . ($digiflazzResponse['ref_id'] ?? $refId) . "\n";
        if (!empty($digiflazzResponse['sn'])) {
            $content .= "SN     : `{$digiflazzResponse['sn']}`\n";
        }
        $content .= "\n✅ Transaksi berhasil (tanpa pemotongan saldo)";
        
        error_log("processDigiflazzPurchaseAdmin - Sending success message");
        sendWhatsAppMessage($phone, formatMessage($content));
    } catch (Exception $e) {
        error_log("processDigiflazzPurchaseAdmin - Unexpected error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        sendWhatsAppMessage($phone, "❌ Terjadi kesalahan tidak terduga. Silakan coba lagi atau hubungi administrator.");
    }
}

/**
 * Send sales report
 */
function sendSalesReport($phone, $agentData, $period) {
    include_once('../lib/WhatsAppNotification.class.php');
    $notification = new WhatsAppNotification();
    $notification->sendSalesReport($agentData['id'], $period);
}

/**
 * Broadcast message
 */
function broadcastMessage($phone, $agentData, $messageContent) {
    include_once('../lib/WhatsAppNotification.class.php');
    $notification = new WhatsAppNotification();
    $result = $notification->broadcastToCustomers($agentData['id'], $messageContent);
    
    if ($result['success']) {
        $reply = "✅ *BROADCAST TERKIRIM*\n\n";
        $reply .= "Total Customer: {$result['total']}\n";
        $reply .= "Terkirim: {$result['sent']}\n";
        $reply .= "Gagal: " . ($result['total'] - $result['sent']);
    } else {
        $reply = "❌ " . $result['message'];
    }
    
    sendWhatsAppMessage($phone, $reply);
}

/**
 * Load message settings from database
 */
function loadMessageSettings() {
    $db = getDBConnection();
    $stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'wa_%'");
    
    $settings = [
        'header' => '',
        'footer' => '',
        'business_name' => 'WiFi Hotspot',
        'business_phone' => '08123456789',
        'business_address' => 'Jl. Contoh No. 123',
        'enable_emoji' => true,
        'enable_formatting' => true
    ];
    
    while ($row = $stmt->fetch()) {
        switch ($row['setting_key']) {
            case 'wa_message_header':
                $settings['header'] = $row['setting_value'];
                break;
            case 'wa_message_footer':
                $settings['footer'] = $row['setting_value'];
                break;
            case 'wa_business_name':
                $settings['business_name'] = $row['setting_value'];
                break;
            case 'wa_business_phone':
                $settings['business_phone'] = $row['setting_value'];
                break;
            case 'wa_business_address':
                $settings['business_address'] = $row['setting_value'];
                break;
            case 'wa_enable_emoji':
                $settings['enable_emoji'] = $row['setting_value'] == '1';
                break;
            case 'wa_enable_formatting':
                $settings['enable_formatting'] = $row['setting_value'] == '1';
                break;
        }
    }
    
    return $settings;
}

/**
 * Format message with header and footer
 */
function formatMessage($content) {
    global $messageSettings;
    
    $header = $messageSettings['header'];
    $footer = $messageSettings['footer'];
    
    // Replace variables in footer
    $footer = str_replace('{business_name}', $messageSettings['business_name'], $footer);
    $footer = str_replace('{business_phone}', $messageSettings['business_phone'], $footer);
    $footer = str_replace('{business_address}', $messageSettings['business_address'], $footer);
    
    // Build message
    $message = '';
    if (!empty($header)) {
        $message .= $header . "\n\n";
    }
    $message .= $content;
    if (!empty($footer)) {
        $message .= "\n" . $footer;
    }
    
    return $message;
}

/**
 * Find agent by name, phone, or code
 */
function findAgent($identifier) {
    global $pdo;
    
    // Try to find by agent code first
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE agent_code = ?");
    $stmt->execute([strtoupper($identifier)]);
    $agent = $stmt->fetch();
    if ($agent) return $agent;
    
    // Try to find by phone
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE phone = ? OR phone = ?");
    $stmt->execute([$identifier, '62' . ltrim($identifier, '0')]);
    $agent = $stmt->fetch();
    if ($agent) return $agent;
    
    // Try to find by name (case insensitive, partial match)
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE LOWER(name) LIKE LOWER(?)");
    $stmt->execute(['%' . $identifier . '%']);
    $agent = $stmt->fetch();
    if ($agent) return $agent;
    
    return null;
}

/**
 * Add balance to agent (Admin only)
 */
function addAgentBalance($adminPhone, $agentIdentifier, $amount) {
    global $pdo;
    
    try {
        // Find agent by name, phone, or code
        $agent = findAgent($agentIdentifier);
        
        if (!$agent) {
            $reply = "❌ *AGENT TIDAK DITEMUKAN*\n\n";
            $reply .= "Agent dengan nama/nomor *{$agentIdentifier}* tidak ditemukan.\n\n";
            $reply .= "Ketik *LISTAGENT* untuk melihat daftar agent.";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        if ($agent['status'] != 'active') {
            $reply = "❌ *AGENT TIDAK AKTIF*\n\n";
            $reply .= "Agent *{$agent['name']}* sedang nonaktif.\n";
            $reply .= "Aktifkan dulu dengan: *ENABLE {$agent['name']}*";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        // Add balance
        $newBalance = $agent['balance'] + $amount;
        $stmt = $pdo->prepare("UPDATE agents SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $agent['id']]);
        
        // Log transaction
        $stmt = $pdo->prepare("INSERT INTO agent_transactions (agent_id, type, amount, description, created_at) VALUES (?, 'topup', ?, ?, NOW())");
        $stmt->execute([$agent['id'], $amount, "Topup by admin via WhatsApp"]);
        
        // Send success to admin
        $reply = "✅ *SALDO BERHASIL DITAMBAHKAN*\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "*Agent:* {$agent['name']}\n";
        $reply .= "*Kode:* {$agent['agent_code']}\n";
        $reply .= "*Jumlah Topup:* Rp " . number_format($amount, 0, ',', '.') . "\n";
        $reply .= "*Saldo Sebelum:* Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
        $reply .= "*Saldo Sekarang:* Rp " . number_format($newBalance, 0, ',', '.') . "\n";
        $reply .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $reply .= "✅ Topup berhasil diproses";
        sendWhatsAppMessage($adminPhone, $reply);
        
        // Notify agent
        if (!empty($agent['phone'])) {
            $agentReply = "✅ *SALDO ANDA TELAH DITAMBAHKAN*\n\n";
            $agentReply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $agentReply .= "*Jumlah:* Rp " . number_format($amount, 0, ',', '.') . "\n";
            $agentReply .= "*Saldo Baru:* Rp " . number_format($newBalance, 0, ',', '.') . "\n";
            $agentReply .= "\n━━━━━━━━━━━━━━━━━━━━\n";
            $agentReply .= "Terima kasih! 🙏";
            sendWhatsAppMessage($agent['phone'], $agentReply);
        }
        
    } catch (Exception $e) {
        $reply = "❌ Error: " . $e->getMessage();
        sendWhatsAppMessage($adminPhone, $reply);
    }
}

/**
 * Register new agent (Admin only)
 */
function registerNewAgent($adminPhone, $agentName, $agentPhone) {
    global $pdo;
    
    try {
        // Check if phone already registered
        $stmt = $pdo->prepare("SELECT * FROM agents WHERE phone = ?");
        $stmt->execute([$agentPhone]);
        if ($stmt->fetch()) {
            $reply = "❌ *NOMOR SUDAH TERDAFTAR*\n\n";
            $reply .= "Nomor *{$agentPhone}* sudah terdaftar sebagai agent.";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        // Generate agent code
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(agent_code, 3) AS UNSIGNED)) as max_code FROM agents WHERE agent_code LIKE 'AG%'");
        $row = $stmt->fetch();
        $nextNumber = ($row['max_code'] ?? 0) + 1;
        $agentCode = 'AG' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        // Insert new agent
        $stmt = $pdo->prepare("INSERT INTO agents (agent_code, name, phone, balance, status, created_at) VALUES (?, ?, ?, 0, 'active', NOW())");
        $stmt->execute([$agentCode, $agentName, $agentPhone]);
        
        // Send success to admin
        $reply = "✅ *AGENT BERHASIL DIDAFTARKAN*\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "*Nama:* {$agentName}\n";
        $reply .= "*Kode:* {$agentCode}\n";
        $reply .= "*Nomor WA:* {$agentPhone}\n";
        $reply .= "*Saldo Awal:* Rp 0\n";
        $reply .= "*Status:* Aktif ✅\n";
        $reply .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $reply .= "Agent dapat langsung menggunakan bot WhatsApp.";
        sendWhatsAppMessage($adminPhone, $reply);
        
        // Send welcome to new agent
        $welcomeMsg = "🎉 *SELAMAT DATANG!*\n\n";
        $welcomeMsg .= "Anda telah terdaftar sebagai agent.\n\n";
        $welcomeMsg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $welcomeMsg .= "*Nama:* {$agentName}\n";
        $welcomeMsg .= "*Kode Agent:* {$agentCode}\n";
        $welcomeMsg .= "*Saldo:* Rp 0\n";
        $welcomeMsg .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";
        $welcomeMsg .= "Ketik *HELP* untuk melihat perintah yang tersedia.\n";
        $welcomeMsg .= "Hubungi admin untuk topup saldo pertama Anda.";
        sendWhatsAppMessage($agentPhone, $welcomeMsg);
        
    } catch (Exception $e) {
        $reply = "❌ Error: " . $e->getMessage();
        sendWhatsAppMessage($adminPhone, $reply);
    }
}

/**
 * Disable agent (Admin only)
 */
function disableAgent($adminPhone, $agentIdentifier) {
    global $pdo;
    
    try {
        $agent = findAgent($agentIdentifier);
        
        if (!$agent) {
            $reply = "❌ Agent *{$agentIdentifier}* tidak ditemukan.\n\n";
            $reply .= "Ketik *LISTAGENT* untuk melihat daftar agent.";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE agents SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$agent['id']]);
        
        $reply = "✅ *AGENT DINONAKTIFKAN*\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "*Nama:* {$agent['name']}\n";
        $reply .= "*Kode:* {$agent['agent_code']}\n";
        $reply .= "*Status:* Nonaktif ❌\n";
        $reply .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $reply .= "Agent tidak dapat lagi generate voucher.";
        sendWhatsAppMessage($adminPhone, $reply);
        
        // Notify agent
        if (!empty($agent['phone'])) {
            $agentMsg = "⚠️ *AKUN ANDA DINONAKTIFKAN*\n\n";
            $agentMsg .= "Akun agent Anda telah dinonaktifkan oleh admin.\n";
            $agentMsg .= "Hubungi admin untuk informasi lebih lanjut.";
            sendWhatsAppMessage($agent['phone'], $agentMsg);
        }
        
    } catch (Exception $e) {
        $reply = "❌ Error: " . $e->getMessage();
        sendWhatsAppMessage($adminPhone, $reply);
    }
}

/**
 * Enable agent (Admin only)
 */
function enableAgent($adminPhone, $agentIdentifier) {
    global $pdo;
    
    try {
        $agent = findAgent($agentIdentifier);
        
        if (!$agent) {
            $reply = "❌ Agent *{$agentIdentifier}* tidak ditemukan.\n\n";
            $reply .= "Ketik *LISTAGENT* untuk melihat daftar agent.";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE agents SET status = 'active' WHERE id = ?");
        $stmt->execute([$agent['id']]);
        
        $reply = "✅ *AGENT DIAKTIFKAN*\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "*Nama:* {$agent['name']}\n";
        $reply .= "*Kode:* {$agent['agent_code']}\n";
        $reply .= "*Status:* Aktif ✅\n";
        $reply .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $reply .= "Agent dapat kembali generate voucher.";
        sendWhatsAppMessage($adminPhone, $reply);
        
        // Notify agent
        if (!empty($agent['phone'])) {
            $agentMsg = "✅ *AKUN ANDA DIAKTIFKAN KEMBALI*\n\n";
            $agentMsg .= "Akun agent Anda telah diaktifkan kembali.\n";
            $agentMsg .= "Anda dapat kembali menggunakan bot WhatsApp.";
            sendWhatsAppMessage($agent['phone'], $agentMsg);
        }
        
    } catch (Exception $e) {
        $reply = "❌ Error: " . $e->getMessage();
        sendWhatsAppMessage($adminPhone, $reply);
    }
}

/**
 * List all agents (Admin only)
 */
function listAllAgents($adminPhone) {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM agents ORDER BY created_at DESC");
        $agents = $stmt->fetchAll();
        
        if (empty($agents)) {
            $reply = "📋 Belum ada agent terdaftar.";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        $reply = "📋 *DAFTAR AGENT*\n\n";
        $reply .= "Total: " . count($agents) . " agent\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $activeCount = 0;
        $inactiveCount = 0;
        
        foreach ($agents as $agent) {
            $statusIcon = $agent['status'] == 'active' ? '✅' : '❌';
            $reply .= "*{$agent['agent_code']}* - {$agent['name']} {$statusIcon}\n";
            $reply .= "Saldo: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
            $reply .= "WA: {$agent['phone']}\n";
            $reply .= "\n";
            
            if ($agent['status'] == 'active') {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
        }
        
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
        $reply .= "Aktif: {$activeCount} | Nonaktif: {$inactiveCount}\n\n";
        $reply .= "Ketik *INFOAGENT <KODE>* untuk detail.";
        
        sendWhatsAppMessage($adminPhone, $reply);
        
    } catch (Exception $e) {
        $reply = "❌ Error: " . $e->getMessage();
        sendWhatsAppMessage($adminPhone, $reply);
    }
}

/**
 * Show agent info (Admin only)
 */
function showAgentInfo($adminPhone, $agentIdentifier) {
    global $pdo;
    
    try {
        $agent = findAgent($agentIdentifier);
        
        if (!$agent) {
            $reply = "❌ Agent *{$agentIdentifier}* tidak ditemukan.\n\n";
            $reply .= "Ketik *LISTAGENT* untuk melihat daftar agent.";
            sendWhatsAppMessage($adminPhone, $reply);
            return;
        }
        
        // Get statistics
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_vouchers FROM agent_vouchers WHERE agent_id = ?");
        $stmt->execute([$agent['id']]);
        $voucherStats = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT SUM(amount) as total_topup FROM agent_transactions WHERE agent_id = ? AND type = 'topup'");
        $stmt->execute([$agent['id']]);
        $topupStats = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT SUM(amount) as total_spent FROM agent_transactions WHERE agent_id = ? AND type = 'generate'");
        $stmt->execute([$agent['id']]);
        $spentStats = $stmt->fetch();
        
        $statusIcon = $agent['status'] == 'active' ? '✅ Aktif' : '❌ Nonaktif';
        
        $reply = "👤 *INFO AGENT*\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "*Nama:* {$agent['name']}\n";
        $reply .= "*Kode:* {$agent['agent_code']}\n";
        $reply .= "*Nomor WA:* {$agent['phone']}\n";
        $reply .= "*Status:* {$statusIcon}\n";
        $reply .= "*Terdaftar:* " . date('d/m/Y', strtotime($agent['created_at'])) . "\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "💰 *SALDO*\n";
        $reply .= "Rp " . number_format($agent['balance'], 0, ',', '.') . "\n\n";
        $reply .= "📊 *STATISTIK*\n";
        $reply .= "• Total Voucher: " . ($voucherStats['total_vouchers'] ?? 0) . "\n";
        $reply .= "• Total Topup: Rp " . number_format($topupStats['total_topup'] ?? 0, 0, ',', '.') . "\n";
        $reply .= "• Total Pengeluaran: Rp " . number_format($spentStats['total_spent'] ?? 0, 0, ',', '.') . "\n\n";
        $reply .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $reply .= "*Perintah Admin:*\n";
        $reply .= "• ADDSALDO {$agent['agent_code']} <JUMLAH>\n";
        $reply .= "• DISABLE {$agent['agent_code']}\n";
        $reply .= "• ENABLE {$agent['agent_code']}";
        
        sendWhatsAppMessage($adminPhone, $reply);
        
    } catch (Exception $e) {
        $reply = "❌ Error: " . $e->getMessage();
        sendWhatsAppMessage($adminPhone, $reply);
    }
}

/**
 * Log webhook
 */
function logWebhook($data) {
    $logFile = '../logs/agent_webhook_log.txt';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | " . $data . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Return success response
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Agent webhook processed']);
