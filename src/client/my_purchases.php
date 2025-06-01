<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get database connection
$database = Database::getInstance();
$db = $database->getConnection();

// Only allow clients to access this page
if ($_SESSION['role'] !== 'client') {
    $_SESSION['error_message'] = "Access denied. This page is for clients only.";
    redirect('../pages/home.php');
}

// Get user information
try {
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        // User not found, redirect to login
        session_destroy();
        redirect('../auth/login.php');
    }

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "Error loading user data.";
}

// Get user's purchases (auction wins)
$purchasesQuery = $db->prepare("
    SELECT up.*, p.title, p.images, p.address_line1, p.city, pa.starting_price, pa.status as auction_status
    FROM user_purchases up
    JOIN properties p ON up.property_id = p.id
    LEFT JOIN property_auctions pa ON up.auction_id = pa.id
    WHERE up.user_id = ?
    ORDER BY up.purchase_date DESC
");
$purchasesQuery->execute([$_SESSION['user_id']]);
$purchases = $purchasesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get active auction participations
$participationsQuery = $db->prepare("
    SELECT pa.*, p.title, p.images, p.address_line1, p.city, p.property_type,
           (SELECT MAX(bid_amount) FROM auction_bids WHERE auction_id = pa.id AND user_id = ?) as your_bid,
           pa.highest_bidder_id = ? as is_highest_bidder
    FROM property_auctions pa
    JOIN auction_participants ap ON pa.id = ap.auction_id
    JOIN properties p ON pa.property_id = p.id
    WHERE ap.user_id = ? AND pa.status = 'active'
    ORDER BY pa.updated_at DESC
");
$participationsQuery->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$participations = $participationsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get user's payment information
$paymentQuery = $db->prepare("
    SELECT * FROM payment_information WHERE user_id = ? AND is_verified = 1 LIMIT 1
");
$paymentQuery->execute([$_SESSION['user_id']]);
$paymentMethod = $paymentQuery->fetch(PDO::FETCH_ASSOC);

// Debug: Let's also check if there's any payment info at all
$debugPaymentQuery = $db->prepare("
    SELECT *, is_verified FROM payment_information WHERE user_id = ?
");
$debugPaymentQuery->execute([$_SESSION['user_id']]);
$allPaymentMethods = $debugPaymentQuery->fetchAll(PDO::FETCH_ASSOC);

// Debug info
error_log("Payment methods for user " . $_SESSION['user_id'] . ": " . json_encode($allPaymentMethods));
error_log("Selected verified payment method: " . json_encode($paymentMethod));
if ($paymentMethod) {
    error_log("Available payment method fields: " . implode(', ', array_keys($paymentMethod)));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Purchases - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Main Site CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Dashboard Specific CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <style>
        /* Dashboard specific styling - NO navigation bar */
        body[data-page="dashboard"] {
            padding-top: 0 !important;
            background: #f8f9fa;
        }
        
        /* Enhanced header styling */
        .luxury-header {
            background: #000 !important;
            padding: 1rem 0;
            color: white;
            margin-bottom: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none !important;
            color: white !important;
        }

        .brand-logo img {
            height: 80px !important;
        }
        
        /* Page header */
        .page-header {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
            border-radius: 1rem;
            position: relative;
            overflow: hidden;
        }

        /* Content cards */
        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: 1px solid #e9ecef;
            color: #333;
        }
        .content-card-body {
            padding: 2rem;
        }

        /* Style adjustments for the table */
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .badge-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700 !important;
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        .bg-gradient-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }
        /* Breadcrumb styling */
        .breadcrumb-container {
            background: white;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 0;
        }
        .breadcrumb {
            background: none;
            margin-bottom: 0;
            padding: 0;
        }
        .breadcrumb-item {
            color: #6c757d;
        }
        .breadcrumb-item.active {
            color: #333;
            font-weight: 600;
        }
        .breadcrumb-item + .breadcrumb-item::before {
            content: "/";
            color: #dee2e6;
        }
        .container {
            padding-top: 2rem;
        }
        .table th {
            color: #000 !important;
            font-weight: 700 !important;
            font-size: 0.8rem !important;
            text-transform: uppercase;
            opacity: 1 !important;
        }
        .table td {
            color: #000 !important;
            font-size: 0.85rem !important;
        }
        .table .text-secondary {
            color: #333 !important;
        }
        .table .text-xs {
            font-size: 0.85rem !important;
        }
        .badge-sm.bg-gradient-warning {
            background: #ffc107 !important; /* Solid color instead of gradient */
            color: #000 !important;
            font-weight: 700 !important;
            padding: 5px 10px !important;
            font-size: 0.8rem !important;
        }
        .badge-sm.bg-gradient-success {
            font-weight: 700 !important;
            padding: 5px 10px !important;
            font-size: 0.8rem !important;
        }
        .badge-sm.bg-gradient-secondary {
            font-weight: 700 !important;
            padding: 5px 10px !important;
            font-size: 0.8rem !important;
        }
        .text-sm {
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            color: #000 !important;
        }
        /* Status explanation styling */
        .status-explanation {
            background-color: #f8f9fa !important;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin-top: 1rem !important;
            margin-bottom: 1rem !important;
            border-radius: 0.25rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease !important;
        }
        /* Make payment button more visible */
        #paymentForm .btn-primary {
            background-color: #d4af37 !important;
            border-color: #d4af37 !important;
            color: #000 !important;
            font-weight: 700 !important;
            font-size: 1.1rem !important;
            padding: 12px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
            transition: all 0.3s ease !important;
        }
        #paymentForm .btn-primary:hover {
            background-color: #c4a030 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15) !important;
        }
        /* Make the "Complete Payment" button in the table more visible */
        .btn-link.text-primary {
            color: #d4af37 !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            background-color: #fff8e1 !important;
            padding: 8px 16px !important;
            border-radius: 20px !important;
            border: 1px solid #d4af37 !important;
            display: inline-flex !important;
            align-items: center !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08) !important;
            margin-right: 5px !important;
        }
        .btn-link.text-primary:hover {
            background-color: #d4af37 !important;
            color: #000 !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
            transform: translateY(-2px) !important;
        }
        .btn-link.text-primary .text-xs {
            font-size: 1rem !important;
            margin-right: 5px !important;
        }
        /* Make payment success modal more visible */
        #paymentSuccessModal .modal-content {
            background-color: white !important;
            border: 3px solid #28a745 !important;
        }
        #paymentSuccessModal .text-success {
            color: #28a745 !important;
        }
        #paymentSuccessModal h3 {
            color: #000 !important;
            font-weight: 700 !important;
        }
        #paymentSuccessModal p {
            color: #333 !important;
            font-size: 1.1rem !important;
        }
        #paymentSuccessModal .btn-success {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
            color: white !important;
            font-weight: 600 !important;
        }
    </style>
</head>
<body data-page="dashboard">
    <!-- Header -->
    <header class="luxury-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../pages/home.php" class="brand-logo text-decoration-none">
                        <img src="../assets/images/logo1.png" alt="Omnes Real Estate" height="40">
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                    <a href="../auth/logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../pages/home.php"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Purchases</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Page Header -->
    <div class="container">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-gavel me-3"></i>My Purchases</h1>
                    <p class="mb-0">View your property purchases and auction participations</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </div>
    
    <!-- Status Explanation Section -->
    <div class="container mb-4">
        <div class="status-explanation">
            <h6><i class="fas fa-info-circle me-2"></i>Understanding Your Purchase Status</h6>
            <p>When you win an auction, your purchase goes through several stages:</p>
            
            <div class="status-flow">
                <div class="status-step">
                    <div class="status-icon pending-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="status-content">
                        <strong>Pending</strong>
                        <p class="small">You've won the auction but payment needs to be completed</p>
                    </div>
                </div>
                <div class="status-step">
                    <div class="status-icon completed-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="status-content">
                        <strong>Completed</strong>
                        <p class="small">Payment processed successfully and you now own the property</p>
                    </div>
                </div>
            </div>
            <p class="mt-2 mb-0"><strong>Note:</strong> After winning an auction, click the "Complete Payment" button to finalize your purchase and take ownership of the property.</p>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6>My Purchases</h6>
                        <p class="text-sm mb-0">Properties you've won through auctions</p>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <?php if (empty($purchases)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-secondary opacity-5 mb-3"></i>
                                <p class="text-secondary">You haven't won any auctions yet.</p>
                                <a href="../pages/explore.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-search me-1"></i>Explore Properties
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Property</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Price</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Purchase Date</th>
                                            <th class="text-secondary opacity-7"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchases as $purchase): 
                                            $images = [];
                                            if (!empty($purchase['images'])) {
                                                $images = json_decode($purchase['images'], true);
                                            }
                                            // Fallback image
                                            $image = !empty($images) && is_array($images) ? $images[0] : "../assets/images/placeholder.jpg";
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div>
                                                        <img src="<?= htmlspecialchars($image) ?>" class="avatar avatar-sm me-3" alt="<?= htmlspecialchars($purchase['title']) ?>">
                                                    </div>
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?= htmlspecialchars($purchase['title']) ?></h6>
                                                        <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($purchase['address_line1']) ?>, <?= htmlspecialchars($purchase['city']) ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <p class="text-xs font-weight-bold mb-0" style="color: #000 !important; font-weight: 700 !important; font-size: 0.9rem !important;">€<?= number_format($purchase['purchase_price'], 0, ',', ' ') ?></p>
                                                <?php if ($purchase['starting_price']): ?>
                                                    <p class="text-xs mb-0" style="color: #333 !important;">Start: €<?= number_format($purchase['starting_price'], 0, ',', ' ') ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle text-center">
                                                <?php if ($purchase['status'] === 'pending'): ?>
                                                    <span class="badge badge-sm bg-gradient-warning" style="background: #ffc107 !important; color: #000 !important; font-weight: 700;">PENDING</span>
                                                <?php elseif ($purchase['status'] === 'completed'): ?>
                                                    <span class="badge badge-sm bg-gradient-success" style="font-weight: 700;">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-sm bg-gradient-secondary" style="font-weight: 700;">Cancelled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-xs font-weight-bold" style="color: #000 !important;"><?= date('M d, Y', strtotime($purchase['purchase_date'])) ?></span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="../pages/explore.php?property_id=<?= $purchase['property_id'] ?>" class="btn btn-link text-secondary mb-0">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </a>
                                                <?php if ($purchase['status'] === 'pending'): ?>
                                                    <button class="btn btn-link text-primary mb-0" onclick='openPaymentModal(<?= htmlspecialchars(json_encode([
                                                        'id' => $purchase['id'],
                                                        'property_id' => $purchase['property_id'],
                                                        'title' => $purchase['title'],
                                                        'price' => $purchase['purchase_price'],
                                                        'image' => $image
                                                    ])) ?>)'>
                                                        <i class="fas fa-credit-card text-xs"></i> Complete Payment
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="row mt-4">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <h6>Active Auction Participations</h6>
                        <p class="text-sm mb-0">Auctions you are currently participating in</p>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <?php if (empty($participations)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-gavel fa-3x text-secondary opacity-5 mb-3"></i>
                                <p class="text-secondary">You're not participating in any active auctions.</p>
                                <a href="../pages/explore.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-search me-1"></i>Explore Auctions
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Property</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Current Price</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Your Bid</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-secondary opacity-7"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participations as $auction): 
                                            $images = [];
                                            if (!empty($auction['images'])) {
                                                $images = json_decode($auction['images'], true);
                                            }
                                            // Fallback image
                                            $image = !empty($images) && is_array($images) ? $images[0] : "../assets/images/placeholder.jpg";
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div>
                                                        <img src="<?= htmlspecialchars($image) ?>" class="avatar avatar-sm me-3" alt="<?= htmlspecialchars($auction['title']) ?>">
                                                    </div>
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?= htmlspecialchars($auction['title']) ?></h6>
                                                        <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($auction['address_line1']) ?>, <?= htmlspecialchars($auction['city']) ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <p class="text-xs font-weight-bold mb-0">€<?= number_format($auction['current_price'], 0, ',', ' ') ?></p>
                                                <p class="text-xs text-secondary mb-0">Start: €<?= number_format($auction['starting_price'], 0, ',', ' ') ?></p>
                                            </td>
                                            <td>
                                                <?php if ($auction['your_bid']): ?>
                                                    <p class="text-xs font-weight-bold mb-0">€<?= number_format($auction['your_bid'], 0, ',', ' ') ?></p>
                                                    <?php if ($auction['is_highest_bidder']): ?>
                                                        <span class="badge badge-sm bg-gradient-success">Highest</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-sm bg-gradient-warning">Outbid</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p class="text-xs text-secondary mb-0">No bids yet</p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="badge badge-sm bg-gradient-info">Active</span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="../pages/explore.php?auction=<?= $auction['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-gavel me-1"></i>Bid Again
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">  
                    <h5 class="modal-title">Complete Your Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Property Information -->
                        <div class="col-md-5">
                            <div class="mb-4">
                                <img id="propertyImage" src="" alt="Property" class="img-fluid rounded">
                            </div>
                            <h6 id="propertyTitle" class="fw-bold"></h6>
                            <p class="text-muted" id="propertyId"></p>
                            <div class="payment-summary mt-4">
                                <h6 class="fw-bold">Payment Summary</h6>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Property Price:</span>
                                    <span id="propertyPrice" class="fw-bold"></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Service Fee (2%):</span>
                                    <span id="serviceFee" class="fw-bold"></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between payment-total">
                                    <span>Total:</span>
                                    <span id="totalAmount"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Form -->
                        <div class="col-md-7">
                            <h6 class="fw-bold mb-3" style="color: #000 !important;">Payment Method</h6>
                            <?php if ($paymentMethod): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Verified Payment Method Found</strong> - You can proceed with payment.
                                </div>
                                
                                <div class="payment-card mb-4" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; padding: 20px;">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-credit-card card-icon" style="font-size: 24px; margin-right: 10px;"></i>
                                        <span class="card-number" style="font-size: 18px; letter-spacing: 2px; color: #000; font-weight: 600;">
                                            •••• •••• •••• ****
                                        </span>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block" style="color: #666 !important; font-weight: 600;">Card Holder</small>
                                            <span style="color: #000 !important; font-weight: 600;">
                                                <?= htmlspecialchars(isset($paymentMethod['cardholder_name']) && $paymentMethod['cardholder_name'] ? $paymentMethod['cardholder_name'] : 'Verified User') ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block" style="color: #666 !important; font-weight: 600;">Status</small>
                                            <span style="color: #28a745 !important; font-weight: 600;">
                                                <i class="fas fa-check-circle me-1"></i>Verified
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <form id="paymentForm">
                                    <input type="hidden" id="purchaseId" name="purchase_id">
                                    
                                    <div class="mb-4">
                                        <label for="lastFourDigits" class="form-label text-center d-block">Last 4 Digits of Your Card</label>
                                        <input type="password" class="form-control cvv-input" id="lastFourDigits" placeholder="****" maxlength="4" required>
                                        <div class="form-text text-center">Enter the last 4 digits of your card number for verification</div>
                                    </div>
                                    
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="termsCheck" required>
                                        <label class="form-check-label" for="termsCheck">
                                            I agree to the <a href="#">terms and conditions</a> for this purchase
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-lock me-2"></i>Complete Payment
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Your payment information is secure. We'll process your payment once you confirm.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger mb-4">
                                    <p>You need a verified payment method to complete this purchase.</p>
                                    <a href="account.php?section=payment" class="btn btn-danger">
                                        <i class="fas fa-credit-card me-2"></i>Add Payment Method
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Success Modal -->
    <div class="modal fade" id="paymentSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="mb-3">Payment Successful!</h3>
                    <p class="mb-4">Congratulations! You now own the property.</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" onclick="window.location.reload()">
                            <i class="fas fa-redo me-2"></i>Refresh Page
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPaymentModal(purchaseData) {
            console.log('Opening payment modal for purchase:', purchaseData);
            
            if (document.getElementById('purchaseId')) {
                document.getElementById('purchaseId').value = purchaseData.id;
            }
            
            if (document.getElementById('propertyImage')) {
                document.getElementById('propertyImage').src = purchaseData.image;
            }
            
            if (document.getElementById('propertyTitle')) {
                document.getElementById('propertyTitle').textContent = purchaseData.title;
            }
            
            if (document.getElementById('propertyId')) {
                document.getElementById('propertyId').textContent = 'Property ID: ' + purchaseData.property_id;
            }
            
            const price = parseFloat(purchaseData.price);
            const serviceFee = price * 0.02;
            const total = price + serviceFee;
            
            if (document.getElementById('propertyPrice')) {
                document.getElementById('propertyPrice').textContent = '€' + price.toLocaleString('fr-FR');
            }
            if (document.getElementById('serviceFee')) {
                document.getElementById('serviceFee').textContent = '€' + serviceFee.toLocaleString('fr-FR');
            }
            if (document.getElementById('totalAmount')) {
                document.getElementById('totalAmount').textContent = '€' + total.toLocaleString('fr-FR');
            }
            
            const lastFourDigitsInput = document.getElementById('lastFourDigits');
            const termsCheck = document.getElementById('termsCheck');
            if (lastFourDigitsInput) lastFourDigitsInput.value = '';
            if (termsCheck) termsCheck.checked = false;
            
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const paymentForm = document.getElementById('paymentForm');
            
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const lastFourDigits = document.getElementById('lastFourDigits').value;
                    const purchaseId = document.getElementById('purchaseId').value;
                    const submitButton = this.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;
                    
                    if (!lastFourDigits) {
                        alert('Please enter the last 4 digits of your card');
                        return;
                    }
                    
                    if (lastFourDigits.length !== 4 || !/^\d+$/.test(lastFourDigits)) {
                        alert('Please enter exactly 4 digits');
                        return;
                    }
                    
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Payment...';
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '../ajax/purchases.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalButtonText;
                            
                            if (xhr.status === 200) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                                        paymentModal.hide();
                                        
                                        setTimeout(() => {
                                            const successModal = new bootstrap.Modal(document.getElementById('paymentSuccessModal'));
                                            successModal.show();
                                        }, 500);
                                    } else {
                                        alert('Payment failed: ' + (response.message || 'Unknown error'));
                                    }
                                } catch (e) {
                                    console.error('Error parsing response:', e);
                                    alert('An error occurred while processing your payment. Please try again.');
                                }
                            } else {
                                alert('Server error (HTTP ' + xhr.status + '). Please try again later.');
                            }
                        }
                    };
                    xhr.send(`action=complete_payment&purchase_id=${purchaseId}&last_four_digits=${lastFourDigits}`);
                });
            }
        });
    </script>
</body>
</html>
