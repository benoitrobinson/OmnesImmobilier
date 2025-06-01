<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$db = $database->getConnection();

// Handle auction actions
$successMessage = '';
$errorMessage = '';
$auctionEndedData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['end_auction'])) {
        $auctionId = (int)$_POST['auction_id'];
        
        try {
            $db->beginTransaction();
            
            // Get auction details
            $auctionStmt = $db->prepare("
                SELECT pa.*, p.title as property_title, p.id as property_id, p.images
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
                    SELECT u.id, u.first_name, u.last_name, u.email, u.phone
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
            }
            
            $db->commit();
            
            $successMessage = "Auction #{$auctionId} ended successfully.";
            
            // Store auction ended data to display in the nice confirmation box
            $auctionEndedData = [
                'auction' => $auction,
                'winner' => $winnerInfo,
                'purchase_id' => $purchaseId
            ];
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errorMessage = "Error ending auction: " . $e->getMessage();
        }
    } elseif (isset($_POST['extend_auction'])) {
        // Handle extend auction action
        $auctionId = (int)$_POST['auction_id'];
        
        try {
            // Extend auction by 24 hours
            $stmt = $db->prepare("
                UPDATE property_auctions
                SET end_date = DATE_ADD(COALESCE(end_date, NOW()), INTERVAL 24 HOUR), updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            $success = $stmt->execute([$auctionId]);
            
            if ($success) {
                $successMessage = "Auction #{$auctionId} extended by 24 hours.";
            } else {
                $errorMessage = "Could not extend auction.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error extending auction: " . $e->getMessage();
        }
    } elseif (isset($_POST['cancel_auction'])) {
        // Handle cancel auction action
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
            
            $successMessage = "Auction #{$auctionId} cancelled successfully.";
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errorMessage = "Error cancelling auction: " . $e->getMessage();
        }
    }
}

// Get active auctions
$activeAuctionsQuery = $db->prepare("
    SELECT pa.*, p.title as property_title, p.price as original_price, 
           p.city, p.address_line1, p.images, p.property_type,
           COUNT(DISTINCT ab.user_id) as bidder_count,
           COUNT(ab.id) as bid_count,
           MAX(ab.bid_amount) as highest_bid,
           u.first_name, u.last_name
    FROM property_auctions pa
    JOIN properties p ON pa.property_id = p.id
    LEFT JOIN auction_bids ab ON pa.id = ab.auction_id
    LEFT JOIN users u ON pa.highest_bidder_id = u.id
    WHERE pa.status = 'active'
    GROUP BY pa.id
    ORDER BY pa.start_date DESC
");
$activeAuctionsQuery->execute();
$activeAuctions = $activeAuctionsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get ended auctions
$endedAuctionsQuery = $db->prepare("
    SELECT pa.*, p.title as property_title, p.price as original_price, 
           p.city, p.address_line1, p.status as property_status,
           COUNT(DISTINCT ab.user_id) as bidder_count,
           COUNT(ab.id) as bid_count,
           MAX(ab.bid_amount) as highest_bid,
           u.first_name, u.last_name, u.id as winner_id
    FROM property_auctions pa
    JOIN properties p ON pa.property_id = p.id
    LEFT JOIN auction_bids ab ON pa.id = ab.auction_id
    LEFT JOIN users u ON pa.highest_bidder_id = u.id
    WHERE pa.status IN ('ended', 'cancelled')
    GROUP BY pa.id
    ORDER BY pa.end_date DESC
    LIMIT 10
");
$endedAuctionsQuery->execute();
$endedAuctions = $endedAuctionsQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Management - Omnes Immobilier Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">Auction Management</h1>
                
                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($auctionEndedData): 
                    $auction = $auctionEndedData['auction'];
                    $winner = $auctionEndedData['winner'];
                    $hasWinner = !empty($winner);
                    
                    // Parse image for the property
                    $propertyImage = '../assets/images/placeholder.jpg';
                    if (!empty($auction['images'])) {
                        try {
                            $images = json_decode($auction['images'], true);
                            if (is_array($images) && !empty($images[0])) {
                                $propertyImage = $images[0];
                            }
                        } catch (Exception $e) {
                            // Use default
                        }
                    }
                ?>
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Auction Ended Successfully</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <img src="<?= htmlspecialchars($propertyImage) ?>" class="img-fluid rounded mb-3" alt="Property Image">
                                <h5><?= htmlspecialchars($auction['property_title']) ?></h5>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars($auction['address_line1']) ?>, <?= htmlspecialchars($auction['city']) ?>
                                </p>
                                <p>
                                    <span class="badge bg-dark">Original Price: €<?= number_format($auction['original_price'], 0, ',', ' ') ?></span>
                                    <span class="badge bg-success">Final Price: €<?= number_format($auction['current_price'], 0, ',', ' ') ?></span>
                                </p>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="card <?= $hasWinner ? 'border-success' : 'border-warning' ?> h-100">
                                    <div class="card-header <?= $hasWinner ? 'bg-success text-white' : 'bg-warning' ?>">
                                        <h5 class="mb-0">
                                            <?php if ($hasWinner): ?>
                                                <i class="fas fa-trophy me-2"></i>Auction Winner
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle me-2"></i>No Winner (No Bidders)
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($hasWinner): ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="avatar-lg me-3 bg-success-subtle text-center rounded-circle">
                                                    <i class="fas fa-user-check text-success" style="font-size: 2rem; line-height: 4rem;"></i>
                                                </div>
                                                <div>
                                                    <h4><?= htmlspecialchars($winner['first_name'] . ' ' . $winner['last_name']) ?></h4>
                                                    <p class="text-muted mb-0">
                                                        <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($winner['email']) ?>
                                                        <br>
                                                        <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($winner['phone']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-success bg-success-subtle">
                                                <p class="mb-0">This property has been marked as <strong>pending</strong> and the winner has been notified.</p>
                                            </div>
                                            
                                            <div class="d-grid gap-2 d-md-flex mt-3">
                                                <a href="view_client.php?id=<?= $winner['id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-user me-1"></i>View Winner Profile
                                                </a>
                                                <a href="edit_property.php?id=<?= $auction['property_id'] ?>" class="btn btn-success">
                                                    <i class="fas fa-home me-1"></i>Edit Property Status
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <div class="mb-3" style="font-size: 3rem; color: #ffc107;">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                </div>
                                                <h5>No bids were placed on this property during the auction.</h5>
                                                <p class="text-muted">The property remains available for sale.</p>
                                            </div>
                                            <div class="d-grid gap-2 mt-3">
                                                <a href="edit_property.php?id=<?= $auction['property_id'] ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit me-1"></i>Edit Property Details
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-th-large me-1"></i>Go to Dashboard
                        </a>
                        <a href="auction_management.php" class="btn btn-outline-primary">
                            <i class="fas fa-gavel me-1"></i>Manage Other Auctions
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Active Auctions -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>Active Auctions</h5>
                        <a href="auction_setup.php" class="btn btn-sm btn-light">
                            <i class="fas fa-plus me-1"></i>Setup New Auction
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeAuctions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No active auctions at the moment.</p>
                                <a href="auction_setup.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Set Up New Auction
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Current Price</th>
                                            <th>Original Price</th>
                                            <th>Bids</th>
                                            <th>Start Date</th>
                                            <th>Current Winner</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeAuctions as $auction): 
                                            // Get property image
                                            $propertyImage = '../assets/images/placeholder.jpg';
                                            if (!empty($auction['images'])) {
                                                try {
                                                    $images = json_decode($auction['images'], true);
                                                    if (is_array($images) && !empty($images[0])) {
                                                        $propertyImage = $images[0];
                                                    }
                                                } catch (Exception $e) {
                                                    // Use default
                                                }
                                            }
                                            
                                            // Format current winner name
                                            $currentWinner = 'No bids yet';
                                            if (!empty($auction['first_name'])) {
                                                $currentWinner = $auction['first_name'] . ' ' . substr($auction['last_name'], 0, 1) . '.';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= htmlspecialchars($propertyImage) ?>" alt="Property" class="rounded me-2" width="60" height="60" style="object-fit: cover;">
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($auction['property_title']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($auction['city']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary">€<?= number_format($auction['current_price'], 0, ',', ' ') ?></span>
                                            </td>
                                            <td>€<?= number_format($auction['original_price'], 0, ',', ' ') ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= $auction['bid_count'] ?> bids</span>
                                                <span class="badge bg-secondary"><?= $auction['bidder_count'] ?> bidders</span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($auction['start_date'])) ?></td>
                                            <td><?= htmlspecialchars($currentWinner) ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $auction['id'] ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                        <button type="submit" name="end_auction" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to end this auction? The highest bidder will win.')">
                                                            <i class="fas fa-stop-circle"></i>
                                                        </button>
                                                    </form>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                        <button type="submit" name="extend_auction" class="btn btn-sm btn-outline-warning">
                                                            <i class="fas fa-clock"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                
                                                <!-- Details Modal -->
                                                <div class="modal fade" id="detailsModal<?= $auction['id'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Auction Details - #<?= $auction['id'] ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <!-- Auction details content -->
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <img src="<?= htmlspecialchars($propertyImage) ?>" class="img-fluid rounded mb-3" alt="Property Image">
                                                                        <h5><?= htmlspecialchars($auction['property_title']) ?></h5>
                                                                        <p class="text-muted"><?= htmlspecialchars($auction['address_line1']) ?>, <?= htmlspecialchars($auction['city']) ?></p>
                                                                        <p class="mb-2">
                                                                            <span class="badge bg-secondary"><?= ucfirst($auction['property_type']) ?></span>
                                                                        </p>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="card bg-light mb-3">
                                                                            <div class="card-body">
                                                                                <h5 class="card-title">Auction Statistics</h5>
                                                                                <p class="mb-1"><strong>Starting Price:</strong> €<?= number_format($auction['starting_price'], 0, ',', ' ') ?></p>
                                                                                <p class="mb-1"><strong>Current Price:</strong> €<?= number_format($auction['current_price'], 0, ',', ' ') ?></p>
                                                                                <p class="mb-1"><strong>Original Price:</strong> €<?= number_format($auction['original_price'], 0, ',', ' ') ?></p>
                                                                                <p class="mb-1"><strong>Bids:</strong> <?= $auction['bid_count'] ?></p>
                                                                                <p class="mb-1"><strong>Unique Bidders:</strong> <?= $auction['bidder_count'] ?></p>
                                                                                <p class="mb-1"><strong>Started:</strong> <?= date('M d, Y H:i', strtotime($auction['start_date'])) ?></p>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <div class="d-grid gap-2">
                                                                            <a href="edit_property.php?id=<?= $auction['property_id'] ?>" class="btn btn-outline-primary">
                                                                                <i class="fas fa-edit me-1"></i>Edit Property
                                                                            </a>
                                                                            <a href="auction_bids.php?auction_id=<?= $auction['id'] ?>" class="btn btn-outline-info">
                                                                                <i class="fas fa-list-ul me-1"></i>View All Bids
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <form action="" method="POST" class="d-inline">
                                                                    <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                                                    <button type="submit" name="end_auction" class="btn btn-danger">
                                                                        <i class="fas fa-stop-circle me-1"></i>End Auction
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Ended Auctions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recently Ended Auctions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($endedAuctions)): ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No ended auctions yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Final Price</th>
                                            <th>Status</th>
                                            <th>End Date</th>
                                            <th>Winner</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($endedAuctions as $auction): 
                                            $statusClasses = [
                                                'ended' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $statusClass = $statusClasses[$auction['status']] ?? 'secondary';
                                            
                                            // Format winner name
                                            $winnerName = 'No winner';
                                            if (!empty($auction['first_name'])) {
                                                $winnerName = $auction['first_name'] . ' ' . substr($auction['last_name'], 0, 1) . '.';
                                            }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($auction['property_title']) ?></td>
                                            <td>€<?= number_format($auction['current_price'], 0, ',', ' ') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= ucfirst($auction['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($auction['end_date'] ?? $auction['updated_at'])) ?></td>
                                            <td><?= htmlspecialchars($winnerName) ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if (!empty($auction['winner_id'])): ?>
                                                    <a href="view_client.php?id=<?= $auction['winner_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-user"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="edit_property.php?id=<?= $auction['property_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-home"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
