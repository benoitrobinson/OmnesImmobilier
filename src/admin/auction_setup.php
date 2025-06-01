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

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new auction
    if (isset($_POST['setup_auction'])) {
        $propertyId = (int)$_POST['property_id'];
        $startingPrice = (float)$_POST['starting_price'];

        try {
            // Check if property exists and is of type auction
            $checkProperty = $db->prepare("
                SELECT id, property_type 
                FROM properties 
                WHERE id = ? AND property_type = 'auction' AND status = 'available'
            ");
            $checkProperty->execute([$propertyId]);
            $property = $checkProperty->fetch(PDO::FETCH_ASSOC);
            
            if (!$property) {
                throw new Exception("Property not found or not an auction type property");
            }
            
            // Check if auction already exists for this property
            $checkExisting = $db->prepare("
                SELECT id FROM property_auctions WHERE property_id = ?
            ");
            $checkExisting->execute([$propertyId]);
            if ($checkExisting->rowCount() > 0) {
                throw new Exception("An auction already exists for this property");
            }
            
            // Create the auction
            $insertAuction = $db->prepare("
                INSERT INTO property_auctions 
                (property_id, starting_price, current_price, status, start_date) 
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $insertAuction->execute([$propertyId, $startingPrice, $startingPrice]);
            
            $success = "Auction has been set up successfully!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Other form actions can be handled here
}

// Get auction properties that don't have auctions yet
$auctionPropertiesQuery = $db->prepare("
    SELECT p.* 
    FROM properties p
    LEFT JOIN property_auctions pa ON p.id = pa.property_id
    WHERE p.property_type = 'auction' AND p.status = 'available' AND pa.id IS NULL
    ORDER BY p.created_at DESC
");
$auctionPropertiesQuery->execute();
$auctionProperties = $auctionPropertiesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get active auctions
$activeAuctionsQuery = $db->prepare("
    SELECT pa.*, p.title, p.price as original_price, 
           p.city, p.address_line1, p.images,
           COUNT(DISTINCT ab.user_id) as bidder_count,
           COUNT(ab.id) as bid_count,
           MAX(ab.bid_amount) as highest_bid
    FROM property_auctions pa
    JOIN properties p ON pa.property_id = p.id
    LEFT JOIN auction_bids ab ON pa.id = ab.auction_id
    WHERE pa.status = 'active'
    GROUP BY pa.id
    ORDER BY pa.start_date DESC
");
$activeAuctionsQuery->execute();
$activeAuctions = $activeAuctionsQuery->fetchAll(PDO::FETCH_ASSOC);

$admin_data = getCurrentUser();

// Near the beginning of the file, add this code to handle direct property linking
$directPropertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$directProperty = null;

if ($directPropertyId > 0) {
    // Get the specific property details
    $propertyQuery = $db->prepare("
        SELECT p.*, 
               (SELECT id FROM property_auctions WHERE property_id = p.id) as auction_id
        FROM properties p
        WHERE p.id = ? AND p.property_type = 'auction'
    ");
    $propertyQuery->execute([$directPropertyId]);
    $directProperty = $propertyQuery->fetch(PDO::FETCH_ASSOC);
    
    // If property found and has no auction yet, pre-populate the auction setup form
    if ($directProperty && empty($directProperty['auction_id'])) {
        $preSelectedPropertyId = $directProperty['id'];
        $suggestedStartingPrice = round($directProperty['price'] * 0.8 / 1000) * 1000; // 80% of price, rounded to nearest thousand
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Setup - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Use same styles as other admin pages */
        :root {
            --admin-primary: #dc3545;
            --admin-secondary: #c82333;
            --admin-dark: #721c24;
            --admin-light: #f8d7da;
            --admin-bg: #f8f9fa;
        }

        body {
            background: var(--admin-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-header {
            background: #000;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .btn-admin-primary {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-admin-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .auction-card {
            transition: all 0.3s ease;
        }
        
        .auction-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .auction-img {
            height: 150px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar - reuse from other admin pages -->
            <div class="col-lg-3 mb-4">
                <div class="admin-sidebar">
                    <div class="sidebar-header">
                        <div class="admin-avatar mx-auto mb-3" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5 class="mb-1">Admin Panel</h5>
                        <small>System Management</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a href="dashboard.php" class="nav-link-admin">
                            <i class="fas fa-chart-pie me-3"></i>Dashboard
                        </a>
                        <a href="manage_clients.php" class="nav-link-admin">
                            <i class="fas fa-users me-3"></i>Manage Clients
                        </a>
                        <a href="manage_agents.php" class="nav-link-admin">
                            <i class="fas fa-user-tie me-3"></i>Manage Agents
                        </a>
                        <a href="manage_properties.php" class="nav-link-admin">
                            <i class="fas fa-home me-3"></i>Manage Properties
                        </a>
                        <a href="auction_setup.php" class="nav-link-admin active">
                            <i class="fas fa-gavel me-3"></i>Auction Setup
                        </a>
                        <a href="manage_appointments.php" class="nav-link-admin">
                            <i class="fas fa-calendar-alt me-3"></i>Appointments
                        </a>
                        <a href="analytics.php" class="nav-link-admin">
                            <i class="fas fa-chart-bar me-3"></i>Analytics & Reports
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main content -->
            <div class="col-lg-9">
                <!-- Page Header -->
                <div class="content-card">
                    <div class="content-card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="h4 mb-0">
                                <i class="fas fa-gavel me-2"></i>Auction Management
                            </h1>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Messages -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Setup New Auction -->
                        <div class="mb-5">
                            <h5 class="mb-3">Setup New Auction</h5>
                            
                            <?php if ($directProperty && !empty($directProperty['auction_id'])): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                This property already has an auction set up. You can manage it in the Active Auctions section below.
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label for="property_id" class="form-label">Select Auction Property</label>
                                        <select class="form-select" id="property_id" name="property_id" required <?= $directProperty ? 'disabled' : '' ?>>
                                            <?php if (empty($auctionProperties) && !$directProperty): ?>
                                                <option value="">No available auction properties</option>
                                            <?php else: ?>
                                                <option value="">Select a property</option>
                                                <?php foreach ($auctionProperties as $property): 
                                                    $selected = ($directProperty && $property['id'] == $directProperty['id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $property['id'] ?>" <?= $selected ?>>
                                                        <?= htmlspecialchars($property['title']) ?> 
                                                        - €<?= number_format($property['price'], 0, ',', ' ') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                
                                                <?php if ($directProperty && !in_array($directProperty['id'], array_column($auctionProperties, 'id'))): ?>
                                                    <option value="<?= $directProperty['id'] ?>" selected>
                                                        <?= htmlspecialchars($directProperty['title']) ?> 
                                                        - €<?= number_format($directProperty['price'], 0, ',', ' ') ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php if ($directProperty): ?>
                                            <input type="hidden" name="property_id" value="<?= $directProperty['id'] ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="starting_price" class="form-label">Starting Price (€)</label>
                                        <input type="number" class="form-control" id="starting_price" name="starting_price" 
                                               value="<?= $suggestedStartingPrice ?? '' ?>"
                                               min="0" step="1000" required>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" name="setup_auction" class="btn btn-admin-primary w-100" 
                                                <?= (empty($auctionProperties) && !$directProperty) || ($directProperty && !empty($directProperty['auction_id'])) ? 'disabled' : '' ?>>
                                            <i class="fas fa-plus-circle me-2"></i>Setup Auction
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Active Auctions -->
                        <div>
                            <h5 class="mb-3">Active Auctions</h5>
                            <?php if (empty($activeAuctions)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No active auctions.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($activeAuctions as $auction): 
                                        // Get first image
                                        $images = [];
                                        if (!empty($auction['images'])) {
                                            try {
                                                $images = json_decode($auction['images'], true);
                                            } catch (Exception $e) {
                                                // Do nothing, use fallback
                                            }
                                        }
                                        
                                        // Fallback image
                                        $image = !empty($images) && is_array($images) ? $images[0] : "../assets/images/placeholder.jpg";
                                    ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card auction-card h-100">
                                            <img src="<?= htmlspecialchars($image) ?>" class="card-img-top auction-img" alt="Property Image">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($auction['title']) ?></h5>
                                                <p class="text-muted small">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?= htmlspecialchars($auction['address_line1']) ?>, <?= htmlspecialchars($auction['city']) ?>
                                                </p>
                                                <div class="row mb-2">
                                                    <div class="col-6">
                                                        <p class="mb-0"><strong>ID:</strong> <?= $auction['id'] ?></p>
                                                        <p class="mb-0"><strong>Starting:</strong> €<?= number_format($auction['starting_price'], 0, ',', ' ') ?></p>
                                                    </div>
                                                    <div class="col-6">
                                                        <p class="mb-0"><strong>Current:</strong> €<?= number_format($auction['current_price'], 0, ',', ' ') ?></p>
                                                        <p class="mb-0"><strong>Original:</strong> €<?= number_format($auction['original_price'], 0, ',', ' ') ?></p>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge bg-primary"><?= $auction['bidder_count'] ?> Bidders</span>
                                                        <span class="badge bg-info"><?= $auction['bid_count'] ?> Bids</span>
                                                    </div>
                                                    <div>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="endAuction(<?= $auction['id'] ?>)">
                                                            <i class="fas fa-stop-circle"></i> End
                                                        </button>
                                                        <a href="auction_details.php?id=<?= $auction['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-footer text-muted small">
                                                Started: <?= date('M d, Y', strtotime($auction['start_date'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- End Auction Confirmation Modal -->
    <div class="modal fade" id="endAuctionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">End Auction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to end this auction? The highest bidder will win.</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="../ajax/auctions.php">
                        <input type="hidden" name="action" value="end_auction">
                        <input type="hidden" name="auction_id" id="endAuctionId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">End Auction</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate starting price from selected property's original price
        document.addEventListener('DOMContentLoaded', function() {
            const propertySelect = document.getElementById('property_id');
            const startingPriceInput = document.getElementById('starting_price');
            
            if (propertySelect && startingPriceInput) {
                propertySelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        // Extract price from the option text (format: "Property Title - €100,000")
                        const priceText = selectedOption.textContent.split('€')[1];
                        if (priceText) {
                            // Convert to number (remove spaces and commas)
                            const price = parseInt(priceText.replace(/[\s,]/g, ''), 10);
                            // Set 80% of original price as starting price
                            startingPriceInput.value = Math.round(price * 0.8 / 1000) * 1000;
                        }
                    } else {
                        startingPriceInput.value = '';
                    }
                });
            }
        });
        
        function endAuction(auctionId) {
            document.getElementById('endAuctionId').value = auctionId;
            const modal = new bootstrap.Modal(document.getElementById('endAuctionModal'));
            modal.show();
        }
    </script>
</body>
</html>

<?php
if ($success && $directPropertyId) {
    // If this was a direct property setup, redirect back to edit property
    echo "<script>
        setTimeout(function() {
            window.location.href = 'edit_property.php?id=" . $directPropertyId . "&success=auction_setup';
        }, 2000);
    </script>";
}
?>
