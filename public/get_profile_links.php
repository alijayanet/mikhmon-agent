<?php
/*
 * Get Profile Direct Links for Admin
 * Called via AJAX from pricing page
 */

header('Content-Type: application/json');

include_once('../include/db_config.php');

try {
    $conn = getDBConnection();
    
    // Get agent from session or parameter
    $agent_session = $_GET['session'] ?? '';
    
    if (empty($agent_session)) {
        throw new Exception('Session required');
    }
    
    // Get agent by session name (assuming session = agent name)
    $stmt = $conn->prepare("SELECT * FROM agents WHERE agent_name = :name OR agent_code = :code");
    $stmt->execute([':name' => $agent_session, ':code' => $agent_session]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception('Agent not found');
    }
    
    // Get all active profiles for this agent
    $stmt = $conn->prepare("SELECT * FROM agent_profile_pricing 
                           WHERE agent_id = :agent_id AND is_active = 1 
                           ORDER BY sort_order, id");
    $stmt->execute([':agent_id' => $agent['id']]);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get base URL (try to detect from request)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $protocol . '://' . $host;
    
    // Remove /mikhmon-agent from path if exists
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '/mikhmon-agent/') !== false) {
        $base_url .= '/mikhmon-agent';
    }
    
    $links = [];
    foreach ($profiles as $profile) {
        $direct_link = $base_url . '/public/order.php?agent=' . urlencode($agent['agent_code']) . '&profile=' . $profile['id'];
        $short_link = '/public/order.php?agent=' . urlencode($agent['agent_code']) . '&profile=' . $profile['id'];
        
        $links[] = [
            'profile_id' => $profile['id'],
            'profile_name' => $profile['profile_name'],
            'price' => $profile['price'],
            'direct_link' => $direct_link,
            'short_link' => $short_link,
            'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($direct_link)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'agent' => $agent,
        'base_url' => $base_url,
        'links' => $links
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
