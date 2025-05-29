<?php
session_start();
require '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle both JSON and form data
$input = null;
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
    $property_id = $input['property_id'] ?? null;
    $action = $input['action'] ?? null;
} else {
    $property_id = $_POST['property_id'] ?? null;
    $action = $_POST['action'] ?? null;
}

if (!$property_id || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    if ($action === 'add') {
        // Check if already favorited
        $checkSql = "SELECT id FROM user_favorites WHERE user_id = ? AND property_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$user_id, $property_id]);
        
        if ($checkStmt->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Property already in favorites']);
            exit;
        }
        
        // Add to favorites
        $insertSql = "INSERT INTO user_favorites (user_id, property_id) VALUES (?, ?)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([$user_id, $property_id]);
        
        echo json_encode(['success' => true, 'message' => 'Property added to favorites', 'action' => 'added']);
        
    } elseif ($action === 'remove') {
        // Remove from favorites
        $deleteSql = "DELETE FROM user_favorites WHERE user_id = ? AND property_id = ?";
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute([$user_id, $property_id]);
        
        echo json_encode(['success' => true, 'message' => 'Property removed from favorites', 'action' => 'removed']);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
