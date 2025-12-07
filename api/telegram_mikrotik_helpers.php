<?php
/**
 * Telegram MikroTik Helper Functions
 * Adapted from WhatsApp webhook for Telegram bot
 */

/**
 * Check MikroTik Status
 */
function checkTelegramMikroTikStatus($chatId) {
    // Load MikroTik session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get identity
    $identity = $API->comm("/system/identity/print");
    $identityName = $identity[0]['name'] ?? 'Unknown';
    
    // Get uptime
    $resource = $API->comm("/system/resource/print");
    $uptime = $resource[0]['uptime'] ?? '0s';
    
    // Get version
    $version = $resource[0]['version'] ?? 'Unknown';
    
    $API->disconnect();
    
    $message = "📊 *STATUS MIKROTIK*\n\n";
    $message .= "Identity: *$identityName*\n";
    $message .= "IP: *$iphost*\n";
    $message .= "Version: *$version*\n";
    $message .= "Uptime: *$uptime*";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Check MikroTik Resource
 */
function checkTelegramMikroTikResource($chatId) {
    // Load MikroTik session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get resource
    $resource = $API->comm("/system/resource/print");
    $API->disconnect();
    
    $res = $resource[0];
    
    $cpu = $res['cpu-load'] ?? '0%';
    $cpuCount = $res['cpu-count'] ?? '1';
    $ramTotal = $res['total-memory'] ?? '0';
    $ramUsed = $res['free-memory'] ?? '0';
    $ramFree = $res['free-memory'] ?? '0';
    $ramPercent = $ramTotal > 0 ? round(($ramUsed / $ramTotal) * 100, 1) : 0;
    
    $diskTotal = $res['total-hdd-space'] ?? '0';
    $diskFree = $res['free-hdd-space'] ?? '0';
    $diskUsed = $diskTotal - $diskFree;
    $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;
    
    // Format bytes
    require_once(__DIR__ . '/../lib/formatbytesbites.php');
    $ramTotalFormatted = formatBytes($ramTotal);
    $ramUsedFormatted = formatBytes($ramUsed);
    $ramFreeFormatted = formatBytes($ramFree);
    $diskTotalFormatted = formatBytes($diskTotal);
    $diskUsedFormatted = formatBytes($diskUsed);
    $diskFreeFormatted = formatBytes($diskFree);
    
    $message = "💻 *RESOURCE MIKROTIK*\n\n";
    $message .= "⚙️ *CPU*\n";
    $message .= "Load: *$cpu*\n";
    $message .= "Cores: *$cpuCount*\n\n";
    $message .= "💾 *RAM*\n";
    $message .= "Used: *$ramUsedFormatted* ($ramPercent%)\n";
    $message .= "Free: *$ramFreeFormatted*\n";
    $message .= "Total: *$ramTotalFormatted*\n\n";
    $message .= "💿 *DISK*\n";
    $message .= "Used: *$diskUsedFormatted* ($diskPercent%)\n";
    $message .= "Free: *$diskFreeFormatted*\n";
    $message .= "Total: *$diskTotalFormatted*";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Check PPPoE Active
 */
function checkTelegramPPPoEActive($chatId) {
    // Load MikroTik session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get active PPPoE connections
    $active = $API->comm("/ppp/active/print");
    $API->disconnect();
    
    $message = "📡 *PPPoE AKTIF*\n\n";
    $message .= "Total: *" . count($active) . " koneksi*\n\n";
    
    if (empty($active)) {
        $message .= "Tidak ada koneksi aktif.";
    } else {
        $count = 0;
        foreach ($active as $conn) {
            $count++;
            if ($count > 10) {
                $message .= "\n... dan " . (count($active) - 10) . " koneksi lainnya";
                break;
            }
            $name = $conn['name'] ?? 'Unknown';
            $address = $conn['address'] ?? 'N/A';
            $uptime = $conn['uptime'] ?? 'N/A';
            $message .= "$count. *$name*\n";
            $message .= "   IP: $address\n";
            $message .= "   Uptime: $uptime\n\n";
        }
    }
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Check MikroTik Ping
 */
function checkTelegramMikroTikPing($chatId) {
    // Load MikroTik session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    $startTime = microtime(true);
    $connected = $API->connect($iphost, $userhost, decrypt($passwdhost));
    $endTime = microtime(true);
    
    if (!$connected) {
        sendTelegramMessage($chatId, "❌ *PING GAGAL*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $latency = round(($endTime - $startTime) * 1000, 2);
    
    $API->disconnect();
    
    $message = "🔌 *PING MIKROTIK*\n\n";
    $message .= "✅ *Koneksi Berhasil*\n";
    $message .= "IP: *$iphost*\n";
    $message .= "Latency: *{$latency}ms*";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Check PPPoE Offline
 */
function checkTelegramPPPoEOffline($chatId) {
    // Load MikroTik session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    // Get all PPPoE secrets
    $allSecrets = $API->comm("/ppp/secret/print");
    
    // Get active PPPoE connections
    $active = $API->comm("/ppp/active/print");
    $activeNames = array();
    foreach ($active as $conn) {
        $name = trim($conn['name'] ?? '');
        if (!empty($name)) {
            $activeNames[] = strtolower($name);
        }
    }
    
    // Filter PPPoE secrets and find offline users
    $offlineUsers = array();
    foreach ($allSecrets as $secret) {
        $name = trim($secret['name'] ?? '');
        $service = isset($secret['service']) ? strtolower($secret['service']) : '';
        $disabled = isset($secret['disabled']) && $secret['disabled'] == 'true';
        
        if (empty($name)) continue;
        if ($service != 'pppoe') continue;
        if ($disabled) continue;
        
        $nameLower = strtolower($name);
        if (!in_array($nameLower, $activeNames)) {
            $profile = $secret['profile'] ?? 'N/A';
            $offlineUsers[] = [
                'name' => $name,
                'profile' => $profile
            ];
        }
    }
    
    $API->disconnect();
    
    $message = "📴 *PPPoE OFFLINE*\n\n";
    $message .= "Total: *" . count($offlineUsers) . " user offline*\n\n";
    
    if (empty($offlineUsers)) {
        $message .= "✅ Semua user PPPoE sedang online.";
    } else {
        $count = 0;
        foreach ($offlineUsers as $user) {
            $count++;
            if ($count > 20) {
                $message .= "\n... dan " . (count($offlineUsers) - 20) . " user lainnya";
                break;
            }
            $message .= "$count. *{$user['name']}*\n";
            $message .= "   Profile: {$user['profile']}\n\n";
        }
    }
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Add PPPoE Secret
 */
function addTelegramPPPoESecret($chatId, $username, $password, $profile) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $checkUser = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (!empty($checkUser)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME SUDAH ADA*\n\nUsername *$username* sudah terdaftar.");
        return;
    }
    
    $checkProfile = $API->comm("/ppp/profile/print", array("?name" => $profile));
    if (empty($checkProfile)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *PROFILE TIDAK DITEMUKAN*\n\nProfile *$profile* tidak ada di MikroTik.");
        return;
    }
    
    $API->comm("/ppp/secret/add", array(
        "name" => $username,
        "password" => $password,
        "service" => "pppoe",
        "profile" => $profile
    ));
    
    $API->disconnect();
    
    sendTelegramMessage($chatId, "✅ *PPPoE SECRET BERHASIL DITAMBAH*\n\nUsername: *$username*\nProfile: *$profile*");
}

/**
 * Edit PPPoE Secret Profile
 */
function editTelegramPPPoESecret($chatId, $username, $newProfile) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    
    $checkProfile = $API->comm("/ppp/profile/print", array("?name" => $newProfile));
    if (empty($checkProfile)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *PROFILE TIDAK DITEMUKAN*\n\nProfile *$newProfile* tidak ada di MikroTik.");
        return;
    }
    
    $API->comm("/ppp/secret/set", array(
        ".id" => $userId,
        "profile" => $newProfile
    ));
    
    $activeSessions = $API->comm("/ppp/active/print", array("?name" => $username));
    $activeSessionCount = 0;
    if (!empty($activeSessions)) {
        foreach ($activeSessions as $activeSession) {
            $API->comm("/ppp/active/remove", array(
                ".id" => $activeSession['.id']
            ));
        }
        $activeSessionCount = count($activeSessions);
    }
    
    $API->disconnect();
    
    $message = "✅ *PROFILE BERHASIL DIUPDATE*\n\nUsername: *$username*\nProfile Baru: *$newProfile*";
    if ($activeSessionCount > 0) {
        $message .= "\n\n✔️ Session aktif ($activeSessionCount) sudah dihapus.\nClient akan reconnect otomatis dengan profile baru.";
    }
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Delete PPPoE Secret
 */
function deleteTelegramPPPoESecret($chatId, $username) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    
    $API->comm("/ppp/secret/remove", array(".id" => $userId));
    
    $API->disconnect();
    
    sendTelegramMessage($chatId, "✅ *PPPoE SECRET BERHASIL DIHAPUS*\n\nUsername: *$username*");
}

/**
 * Enable PPPoE Secret
 */
function enableTelegramPPPoESecret($chatId, $username) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if (!$isDisabled) {
        $API->disconnect();
        sendTelegramMessage($chatId, "ℹ️ *SUDAH ENABLE*\n\nUsername *$username* sudah dalam keadaan enable.");
        return;
    }
    
    $API->comm("/ppp/secret/set", array(
        ".id" => $userId,
        "disabled" => "no"
    ));
    
    $API->disconnect();
    
    sendTelegramMessage($chatId, "✅ *PPPoE SECRET BERHASIL ENABLE*\n\nUsername: *$username*");
}

/**
 * Disable PPPoE Secret
 */
function disableTelegramPPPoESecret($chatId, $username) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $users = $API->comm("/ppp/secret/print", array("?name" => $username));
    if (empty($users)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if ($isDisabled) {
        $API->disconnect();
        sendTelegramMessage($chatId, "ℹ️ *SUDAH DISABLE*\n\nUsername *$username* sudah dalam keadaan disable.");
        return;
    }
    
    $API->comm("/ppp/secret/set", array(
        ".id" => $userId,
        "disabled" => "yes"
    ));
    
    $API->disconnect();
    
    sendTelegramMessage($chatId, "✅ *PPPoE SECRET BERHASIL DISABLE*\n\nUsername: *$username*");
}

/**
 * Get Voucher Settings from Database
 */
function getVoucherSettings() {
    $defaults = [
        'voucher_username_password_same' => '0',
        'voucher_username_type' => 'alphanumeric',
        'voucher_username_length' => '8',
        'voucher_password_type' => 'alphanumeric',
        'voucher_password_length' => '6',
        'voucher_prefix_enabled' => '1',
        'voucher_prefix' => 'AG',
        'voucher_uppercase' => '1'
    ];
    
    try {
        $db = getDBConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'voucher_%'");
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Merge with defaults
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error getting voucher settings: " . $e->getMessage());
        return $defaults;
    }
}

/**
 * Generate Random String
 */
function generateRandomString($type, $length) {
    $chars = '';
    
    switch($type) {
        case 'numeric':
            $chars = '0123456789';
            break;
        case 'alpha':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'alphanumeric':
            $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        default:
            $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $result;
}


/**
 * Enable Hotspot User
 */
function enableTelegramHotspotUser($chatId, $username) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $users = $API->comm("/ip/hotspot/user/print", array("?name" => $username));
    
    if (empty($users)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if (!$isDisabled) {
        $API->disconnect();
        sendTelegramMessage($chatId, "ℹ️ *SUDAH ENABLE*\n\nUsername *$username* sudah dalam keadaan enable.");
        return;
    }
    
    $API->comm("/ip/hotspot/user/set", array(
        ".id" => $userId,
        "disabled" => "no"
    ));
    
    $API->disconnect();
    
    sendTelegramMessage($chatId, "✅ *HOTSPOT USER BERHASIL ENABLE*\n\nUsername: *$username*");
}

/**
 * Disable Hotspot User
 */
function disableTelegramHotspotUser($chatId, $username) {
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nKonfigurasi session tidak ter-load.");
        return;
    }
    
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session) {
        sendTelegramMessage($chatId, "❌ *SISTEM ERROR*\n\nSession MikroTik tidak ditemukan.");
        return;
    }
    
    $sessionData = $sessionConfig[$session];
    $iphost = explode('!', $sessionData[1])[1] ?? '';
    $userhost = explode('@|@', $sessionData[2])[1] ?? '';
    $passwdhost = explode('#|#', $sessionData[3])[1] ?? '';
    
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ *GAGAL TERHUBUNG*\n\nTidak dapat terhubung ke MikroTik.");
        return;
    }
    
    $users = $API->comm("/ip/hotspot/user/print", array("?name" => $username));
    
    if (empty($users)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ *USERNAME TIDAK DITEMUKAN*\n\nUsername *$username* tidak ada di MikroTik.");
        return;
    }
    
    $userId = $users[0]['.id'];
    $isDisabled = isset($users[0]['disabled']) && $users[0]['disabled'] == 'true';
    
    if ($isDisabled) {
        $API->disconnect();
        sendTelegramMessage($chatId, "ℹ️ *SUDAH DISABLE*\n\nUsername *$username* sudah dalam keadaan disable.");
        return;
    }
    
    $API->comm("/ip/hotspot/user/set", array(
        ".id" => $userId,
        "disabled" => "yes"
    ));
    
    $API->disconnect();
    
    sendTelegramMessage($chatId, "✅ *HOTSPOT USER BERHASIL DISABLE*\n\nUsername: *$username*");
}
?>
