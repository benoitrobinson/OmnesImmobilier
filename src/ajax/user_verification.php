<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Default response structure
$response = [
    'success' => false,
    'message' => 'Invalid request',
    'verified' => false
];

// Check if the user is logged in
if (!isLoggedIn()) {
    $response['message'] = 'You must be logged in to perform this action';
    echo json_encode($response);
    exit;
}

// Get action from request
$action = $_POST['action'] ?? '';

if ($action === 'check_verification') {
    $userId = $_SESSION['user_id'];
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if user has any verified payment methods
        $stmt = $db->prepare("
            SELECT COUNT(*) as verified_count
            FROM payment_information
            WHERE user_id = ? AND is_verified = 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $isVerified = $result['verified_count'] > 0;
        
        $response = [
            'success' => true,
            'verified' => $isVerified,
            'message' => $isVerified ? 'User has verified payment methods' : 'User has no verified payment methods'
        ];
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'verified' => false,
            'message' => 'Error checking verification: ' . $e->getMessage()
        ];
    }
}

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
