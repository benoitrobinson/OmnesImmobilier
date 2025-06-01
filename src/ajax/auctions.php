<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Default response structure
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Check if the user is logged in
if (!isLoggedIn()) {
    $response['message'] = 'You must be logged in to perform this action';
    echo json_encode($response);
    exit;
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Get action from request
$action = $_POST['action'] ?? '';

// Handle different actions
switch ($action) {
    case 'get_bids':
        $auctionId = (int)$_POST['auction_id'];
        
        try {
            $stmt = $db->prepare("
                SELECT ab.*, 
                       CONCAT(u.first_name, ' ', SUBSTRING(u.last_name, 1, 1), '.') as user_name
                FROM auction_bids ab
                JOIN users u ON ab.user_id = u.id
                WHERE ab.auction_id = ?
                ORDER BY ab.bid_amount DESC, ab.bid_time DESC
                LIMIT 10
            ");
            $stmt->execute([$auctionId]);
            $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'bids' => $bids
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Error fetching bids: ' . $e->getMessage()
            ];
        }
        break;
        
    case 'place_bid':
        $auctionId = (int)$_POST['auction_id'];
        $propertyId = (int)$_POST['property_id'];
        $bidAmount = (float)$_POST['bid_amount'];
        $userId = $_SESSION['user_id'];
        
        try {
            // 1. First, check if the user has verified payment method
            $verifyStmt = $db->prepare("
                SELECT COUNT(*) as verified_count
                FROM payment_information
                WHERE user_id = ? AND is_verified = 1
            ");
            $verifyStmt->execute([$userId]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($verifyResult['verified_count'] == 0) {
                $response = [
                    'success' => false,
                    'message' => 'You must have a verified payment method to place bids'
                ];
                break;
            }
            
            // 2. Check if auction is still active
            $auctionStmt = $db->prepare("
                SELECT * FROM property_auctions 
                WHERE id = ? AND status = 'active'
            ");
            $auctionStmt->execute([$auctionId]);
            $auction = $auctionStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$auction) {
                $response = [
                    'success' => false,
                    'message' => 'This auction is no longer active'
                ];
                break;
            }
            
            // 3. Check if bid is high enough
            if ($bidAmount <= $auction['current_price']) {
                $response = [
                    'success' => false,
                    'message' => 'Your bid must be higher than the current price'
                ];
                break;
            }
            
            // 4. Add user as participant if not already
            $participantStmt = $db->prepare("
                INSERT IGNORE INTO auction_participants (auction_id, user_id)
                VALUES (?, ?)
            ");
            $participantStmt->execute([$auctionId, $userId]);
            
            // 5. Place the bid and update current price
            $db->beginTransaction();
            
            $bidStmt = $db->prepare("
                INSERT INTO auction_bids (auction_id, user_id, bid_amount)
                VALUES (?, ?, ?)
            ");
            $bidStmt->execute([$auctionId, $userId, $bidAmount]);
            
            $updateStmt = $db->prepare("
                UPDATE property_auctions
                SET current_price = ?, highest_bidder_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$bidAmount, $userId, $auctionId]);
            
            $db->commit();
            
            $response = [
                'success' => true,
                'message' => 'Your bid has been placed successfully!'
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $response = [
                'success' => false,
                'message' => 'Error placing bid: ' . $e->getMessage()
            ];
        }
        break;
        
    case 'end_auction':
        // Only admin can end auctions
        if ($_SESSION['role'] !== 'admin') {
            $response['message'] = 'Only administrators can perform this action';
            break;
        }
        
        $auctionId = (int)$_POST['auction_id'];
        
        try {
            $db->beginTransaction();
            
            // Get auction details
            $auctionStmt = $db->prepare("
                SELECT pa.*, p.title as property_title, p.id as property_id
                FROM property_auctions pa
                JOIN properties p ON pa.property_id = p.id
                WHERE pa.id = ? AND pa.status = 'active'
            ");
            $auctionStmt->execute([$auctionId]);
            $auction = $auctionStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$auction) {
                throw new Exception('Auction not found or already ended');
            }
            
            // Get winner information if there is a highest bidder
            $winnerInfo = null;
            if ($auction['highest_bidder_id']) {
                $winnerStmt = $db->prepare("
                    SELECT u.id, u.first_name, u.last_name, u.email
                    FROM users u
                    WHERE u.id = ?
                ");
                $winnerStmt->execute([$auction['highest_bidder_id']]);
                $winnerInfo = $winnerStmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Update auction status
            $updateStmt = $db->prepare("
                UPDATE property_auctions
                SET status = 'ended', end_date = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$auctionId]);
            
            // If there's a winner, create purchase record
            $purchaseId = null;
            if ($auction['highest_bidder_id']) {
                $purchaseStmt = $db->prepare("
                    INSERT INTO user_purchases 
                    (user_id, property_id, auction_id, purchase_price, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $purchaseStmt->execute([
                    $auction['highest_bidder_id'],
                    $auction['property_id'],
                    $auctionId,
                    $auction['current_price']
                ]);
                $purchaseId = $db->lastInsertId();
                
                // Update property status
                $propertyStmt = $db->prepare("
                    UPDATE properties
                    SET status = 'pending'
                    WHERE id = ?
                ");
                $propertyStmt->execute([$auction['property_id']]);
                
                $successMessage = "Auction ended successfully! Winner: " . $winnerInfo['first_name'] . " " . $winnerInfo['last_name'] . " with a winning bid of €" . number_format($auction['current_price'], 0, ',', ' ') . ".";
            } else {
                $successMessage = "Auction ended successfully. No bids were placed for this property.";
            }
            
            $db->commit();
            
            // Return user-friendly success message
            $response = [
                'success' => true,
                'message' => $successMessage,
                'auction' => [
                    'id' => $auction['id'],
                    'property_id' => $auction['property_id'],
                    'property_title' => $auction['property_title'],
                    'final_price' => $auction['current_price'],
                    'has_winner' => !empty($auction['highest_bidder_id']),
                    'purchase_id' => $purchaseId
                ],
                'winner' => $winnerInfo ? [
                    'id' => $winnerInfo['id'],
                    'name' => $winnerInfo['first_name'] . ' ' . $winnerInfo['last_name'],
                    'email' => $winnerInfo['email']
                ] : null
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $response = [
                'success' => false,
                'message' => 'Error ending auction: ' . $e->getMessage()
            ];
        }
        break;
        
    case 'extend_auction':
        // Only admin can extend auctions
        if ($_SESSION['role'] !== 'admin') {
            $response['message'] = 'Only administrators can perform this action';
            break;
        }
        
        $auctionId = (int)$_POST['auction_id'];
        
        try {
            // Extend auction by 24 hours
            $stmt = $db->prepare("
                UPDATE property_auctions
                SET end_date = DATE_ADD(COALESCE(end_date, NOW()), INTERVAL 24 HOUR), updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            $success = $stmt->execute([$auctionId]);
            
            $response = [
                'success' => $success,
                'message' => $success ? 'Auction extended by 24 hours' : 'Could not extend auction'
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Error extending auction: ' . $e->getMessage()
            ];
        }
        break;
        
    case 'cancel_auction':
        // Only admin can cancel auctions
        if ($_SESSION['role'] !== 'admin') {
            $response['message'] = 'Only administrators can perform this action';
            break;
        }
        
        $auctionId = (int)$_POST['auction_id'];
        
        try {
            $db->beginTransaction();
            
            // Update auction status
            $updateStmt = $db->prepare("
                UPDATE property_auctions
                SET status = 'cancelled', end_date = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$auctionId]);
            
            // Get property ID from auction
            $propStmt = $db->prepare("
                SELECT property_id FROM property_auctions WHERE id = ?
            ");
            $propStmt->execute([$auctionId]);
            $propertyId = $propStmt->fetchColumn();
            
            // Reset property to available
            $propertyStmt = $db->prepare("
                UPDATE properties
                SET status = 'available'
                WHERE id = ?
            ");
            $propertyStmt->execute([$propertyId]);
            
            $db->commit();
            
            $response = [
                'success' => true,
                'message' => 'Auction cancelled successfully'
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $response = [
                'success' => false,
                'message' => 'Error cancelling auction: ' . $e->getMessage()
            ];
        }
        break;
        
    default:
        $response['message'] = 'Unknown action';
}

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
