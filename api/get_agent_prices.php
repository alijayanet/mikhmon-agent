<?php
/*
 * API to get all agent prices from database
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once(__DIR__ . '/../include/db_config.php');
    
    $db = getDBConnection();
    
    $stmt = $db->query("
        SELECT ap.*, a.agent_name, a.agent_code 
        FROM agent_prices ap 
        LEFT JOIN agents a ON a.id = ap.agent_id 
        ORDER BY ap.updated_at DESC
    ");
    
    $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'prices' => $prices,
        'total' => count($prices)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
