<?php
header('Content-Type: application/json');

if (!isset($_GET['agent_id']) || empty($_GET['agent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Agent ID required']);
    exit;
}

require_once '../config/database.php';

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    $agent_id = (int)$_GET['agent_id'];
    
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ? AND role = 'agent'");
    $stmt->execute([$agent_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($agent) {
        echo json_encode([
            'success' => true,
            'agent' => $agent
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Agent not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get agent error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>
