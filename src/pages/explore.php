<?php
session_start();
require '../config/database.php';
require_once '../includes/functions.php';

// ✅ CORRECT way to get database connection
$database = Database::getInstance();
$pdo = $database->getConnection();

// Get user role for conditional functionality
$user_role = $_SESSION['role'] ?? null;
$is_client = ($user_role === 'client');
$is_agent = ($user_role === 'agent');
$is_admin = ($user_role === 'admin');

// fetch filters
$lt = $_GET['listingType'] ?? '';
$location = $_GET['location'] ?? '';
$budget = $_GET['budget'] ?? '';

// build query
$sql = "SELECT * FROM properties WHERE status='available'";
$params = [];

if($lt){
  $sql .= " AND property_type = ?";
  $params[] = $lt;
}
if($location){
  $sql .= " AND city LIKE ?";
  $params[] = "%{$location}%";
}
if($budget){
  $sql .= " AND price <= ?";
  $params[] = $budget;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's favorites if logged in and is a client
$userFavorites = [];
if (isset($_SESSION['user_id']) && $is_client) {
    $favoritesSql = "SELECT property_id FROM user_favorites WHERE user_id = ?";
    $favoritesStmt = $pdo->prepare($favoritesSql);
    $favoritesStmt->execute([$_SESSION['user_id']]);
    $userFavorites = $favoritesStmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Explore Properties - Omnes Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/explore.css?v=1.1">
    <style>
        /* transparent, flat navbar */
        .navbar {
          position: absolute !important;
          top: 0; left: 0; right: 0;
          background-color: transparent !important;
          box-shadow: none !important;
          backdrop-filter: none !important;
          z-index: 1000;
        }
        
        /* Make navbar text white on transparent background */
        .navbar .nav-link,
        .navbar .navbar-brand {
          color: white !important;
        }
        
        .navbar .nav-link:hover {
          color: #d4af37 !important;
        }
        
        .navbar .nav-link.active {
          color: #d4af37 !important;
        }
        
        /* User avatar on transparent navbar */
        .navbar .user-avatar-small {
          border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Dropdown arrow */
        .navbar .fas.fa-chevron-down {
          color: white !important;
        }
        
        /* remove border‐radius on hero image */
        .explore-hero-bg {
          border-radius: 0 !important;
          overflow: hidden;
        }

        .property-image-container {
            height: 200px;
            position: relative;
            overflow: hidden;
            border-radius: 8px 8px 0 0;
        }
        
        .property-main-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .property-card:hover .property-main-img {
            transform: scale(1.05);
        }
        
        .property-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.6);
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            color: white;
            font-size: 14px;
            cursor: pointer;
            z-index: 2;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .property-image-container:hover .property-arrow {
            opacity: 1;
        }
        
        .left-arrow {
            left: 10px;
        }
        
        .right-arrow {
            right: 10px;
        }
          .property-arrow:hover {
            background: rgba(0,0,0,0.8);
        }
        
        .favorite-btn {
            transition: all 0.3s ease;
        }
        
        .favorite-btn.favorited {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        .favorite-btn:not(.favorited):hover {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        /* bold, gold prices in property cards */
.property-card .text-primary {
    color: #d4af37 !important;
    font-weight: 700 !important;
}

/* black Book Appointment button in property cards */
.property-card .btn-primary {
    background-color: #000 !important;
    border-color: #000 !important;
    color: #fff !important;
}
.property-card .btn-primary:hover {
    background-color: #222 !important;
    border-color: #222 !important;
}
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

<!-- Hero Section -->
<div class="explore-hero-bg">
    <div class="explore-hero-overlay"></div>
    <div class="explore-hero-content">
        <h1>Welcome to our Explore Section</h1>
        <p>Browse all the properties you are looking for and discover your next dream home or investment.</p>
    </div>
</div>

<div class="container mt-5 pt-5">

    <!-- Search Menu -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <form id="exploreForm" method="GET" class="d-flex justify-content-center align-items-center gap-3 bg-light p-4 rounded shadow-sm" style="flex-wrap: wrap;">
                <!-- Location Dropdown -->
                <div>
                    <label for="location" class="form-label mb-1">Location</label>
                    <select class="form-select" id="location" name="location">
                        <option value="" <?php if($location==='') echo 'selected'; ?>>All Locations</option>
                        <option value="Paris" <?php if($location==='Paris') echo 'selected'; ?>>Paris</option>
                        <option value="Saint-Tropez" <?php if($location==='Saint-Tropez') echo 'selected'; ?>>Saint-Tropez</option>
                        <option value="Nice" <?php if($location==='Nice') echo 'selected'; ?>>Nice</option>
                        <option value="Lyon" <?php if($location==='Lyon') echo 'selected'; ?>>Lyon</option>
                        <option value="Bordeaux" <?php if($location==='Bordeaux') echo 'selected'; ?>>Bordeaux</option>
                        <option value="Cannes" <?php if($location==='Cannes') echo 'selected'; ?>>Cannes</option>
                        <option value="Marseille" <?php if($location==='Marseille') echo 'selected'; ?>>Marseille</option>
                        <option value="Bayeux" <?php if($location==='Bayeux') echo 'selected'; ?>>Bayeux</option>
                    </select>
                </div>
                <!-- Listing Type Dropdown -->
                <div>
                    <label for="listingType" class="form-label mb-1">Listing Type</label>
                    <select class="form-select" id="listingType" name="listingType">
                        <option value="" <?php if($lt==='') echo 'selected'; ?>>All Types</option>
                        <option value="house" <?php if($lt==='house') echo 'selected'; ?>>Houses</option>
                        <option value="apartment" <?php if($lt==='apartment') echo 'selected'; ?>>Apartments</option>
                        <option value="commercial" <?php if($lt==='commercial') echo 'selected'; ?>>Commercial</option>
                        <option value="land" <?php if($lt==='land') echo 'selected'; ?>>Land</option>
                        <option value="rental" <?php if($lt==='rental') echo 'selected'; ?>>Rentals</option>
                        <option value="auction" <?php if($lt==='auction') echo 'selected'; ?>>Auction Properties</option>
                    </select>
                </div>
                <!-- Budget Input -->
                <div>
                    <label for="budget" class="form-label mb-1">Max Budget (€)</label>
                    <input type="number" class="form-control" id="budget" name="budget" min="0" 
                           placeholder="Enter budget" value="<?= htmlspecialchars($budget) ?>">
                </div>
                <!-- Search Button -->
                <div class="align-self-end">
                    <button type="submit" id="searchBtn" class="btn btn-primary mt-2">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Properties Results -->
    <div class="mb-5 featured-properties">
        <h3>
            <?php if ($location || $lt || $budget): ?>
                Search Results (<?= count($properties) ?> properties found)
            <?php else: ?>
                Featured Properties (<?= count($properties) ?> properties)
            <?php endif; ?>
        </h3>
        
        <?php if (empty($properties)): ?>
            <div class="text-center py-5">
                <i class="fas fa-home fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No properties found</h4>
                <p class="text-muted">Try adjusting your search criteria or browse all properties.</p>
                <a href="explore.php" class="btn btn-primary">View All Properties</a>
            </div>
        <?php else: ?>
            <div class="row">                <?php foreach($properties as $p): 
                    // Get images from database or use fallback
                    $images = [];
                    if (!empty($p['images'])) {
                        $images = json_decode($p['images'], true);
                    }
                    
                    // Fallback to default images if no images in database
                    if (empty($images) || !is_array($images)) {
                        $images = [
                            "../assets/images/property{$p['id']}-1.jpg",
                            "../assets/images/property{$p['id']}-2.jpg",
                            "../assets/images/property{$p['id']}-3.jpg",
                            "../assets/images/property{$p['id']}-4.jpg",
                            "../assets/images/property{$p['id']}-5.jpg"
                        ];
                    }
                    
                    // Check if this property is favorited by the current user
                    $isFavorited = in_array($p['id'], $userFavorites);
                ?>
                    <div class="col-md-4 mb-4">
                        <div class="card property-card h-100">
                            <div class="property-image-container" 
                                 data-images='<?= json_encode($images) ?>' 
                                 data-current="0">
                                <img src="<?= htmlspecialchars($images[0]) ?>" 
                                     class="property-main-img" 
                                     alt="<?= htmlspecialchars($p['title']) ?>"
                                     onerror="this.src='../assets/images/placeholder.jpg'">
                                
                                <?php if (count($images) > 1): ?>
                                    <button class="property-arrow left-arrow" onclick="changeImage(this, -1)">
                                        &#8592;
                                    </button>
                                    <button class="property-arrow right-arrow" onclick="changeImage(this, 1)">
                                        &#8594;
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($p['title']) ?></h5>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars($p['city']) ?>
                                </p>
                                <p class="card-text">
                                    <?= htmlspecialchars(substr($p['description'], 0, 100)) ?>...
                                </p>
                                <p class="text-muted small">
                                    <i class="fas fa-home me-1"></i>
                                    <?= htmlspecialchars($p['address_line1']) ?>
                                </p>
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="h5 text-primary mb-0">€<?= number_format($p['price'], 0, ',', ' ') ?></span>
                                        <span class="badge bg-secondary"><?= ucfirst($p['property_type']) ?></span>
                                    </div>                                    <div class="d-grid gap-2">
                                        <button type="button" 
                                                class="btn btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#propertyModal"
                                                onclick="showPropertyDetails(<?= htmlspecialchars(json_encode($p)) ?>, <?= $isFavorited ? 'true' : 'false' ?>)">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </button>
                                        
                                        <?php if ($is_client && isset($_SESSION['user_id'])): ?>
                                            <a href="book_appointment.php?property_id=<?= $p['id'] ?>&agent_id=<?= $p['agent_id'] ?>"
                                               class="btn btn-primary">
                                                <i class="fas fa-calendar-alt me-1"></i>Book Appointment
                                            </a>
                                        <?php elseif (!isset($_SESSION['user_id'])): ?>
                                            <a href="../auth/login.php" class="btn btn-primary">
                                                <i class="fas fa-sign-in-alt me-1"></i>Login to Book
                                            </a>
                                        <?php elseif ($is_agent): ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class="fas fa-user-tie me-1"></i>Agent View
                                            </button>
                                        <?php elseif ($is_admin): ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class="fas fa-user-shield me-1"></i>Admin View
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Property Details Modal -->
<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="propertyModalTitle">Property Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="propertyModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>



<script>
// Pass PHP data to JavaScript with explicit boolean values
const userLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
const userRole = <?= json_encode($user_role) ?>;
const isAgent = <?= $is_agent ? 'true' : 'false' ?>;
const isClient = <?= $is_client ? 'true' : 'false' ?>;
const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
const userFavorites = <?= json_encode($userFavorites) ?>;

// Output debug info to console
console.log("User session data:", {
    loggedIn: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>,
    userId: <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>,
    role: <?= json_encode($user_role) ?>,
    isAgent: <?= $is_agent ? 'true' : 'false' ?>,
    isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
    isClient: <?= $is_client ? 'true' : 'false' ?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/explore.js"></script>
</body>
</html>