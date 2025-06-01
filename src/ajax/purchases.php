<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$database = Database::getInstance();
$db = $database->getConnection();

if (isset($_POST['action']) && $_POST['action'] === 'complete_payment') {
    try {
        $purchaseId = (int)$_POST['purchase_id'];
        $lastFourDigits = $_POST['last_four_digits'];
        $userId = $_SESSION['user_id'];
        
        // Validate input
        if (!$purchaseId || !$lastFourDigits) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        if (strlen($lastFourDigits) !== 4 || !ctype_digit($lastFourDigits)) {
            echo json_encode(['success' => false, 'message' => 'Invalid card digits format']);
            exit;
        }
        
        // Verify the purchase belongs to the user and is pending
        $purchaseQuery = $db->prepare("SELECT * FROM user_purchases WHERE id = ? AND user_id = ? AND status = 'pending'");
        $purchaseQuery->execute([$purchaseId, $userId]);
        $purchase = $purchaseQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$purchase) {
            echo json_encode(['success' => false, 'message' => 'Purchase not found or already completed']);
            exit;
        }
        
        // Get the user's verified payment method and check last 4 digits
        $paymentQuery = $db->prepare("SELECT card_last_four FROM payment_information WHERE user_id = ? AND is_verified = 1 LIMIT 1");
        $paymentQuery->execute([$userId]);
        $paymentMethod = $paymentQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$paymentMethod) {
            echo json_encode(['success' => false, 'message' => 'No verified payment method found']);
            exit;
        }
        
        // Compare the provided last 4 digits with the stored ones
        if ($paymentMethod['card_last_four'] !== $lastFourDigits) {
            echo json_encode(['success' => false, 'message' => 'Card verification failed. The last 4 digits do not match your registered card.']);
            exit;
        }
        
        // Update purchase status to completed
        $updateQuery = $db->prepare("UPDATE user_purchases SET status = 'completed' WHERE id = ?");
        $updateResult = $updateQuery->execute([$purchaseId]);
        
        if ($updateResult) {
            echo json_encode([
                'success' => true, 
                'message' => 'Payment completed successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update purchase status']);
        }
        
    } catch (Exception $e) {
        error_log("Payment processing error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
