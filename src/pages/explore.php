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

// Add this function before the existing appointment booking logic
function getAvailableTimeSlots($pdo, $agentId, $date, $duration = 30) {
    $dayOfWeek = date('l', strtotime($date));
    
    // Get general availability for the day
    $availStmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM agent_availability 
        WHERE agent_id = ? AND day_of_week = ? AND specific_date IS NULL AND is_available = 1
    ");
    $availStmt->execute([$agentId, $dayOfWeek]);
    $availability = $availStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$availability) return [];
    
    // Get blocked time slots for this specific date (is_available = 0)
    $blockedStmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM agent_availability 
        WHERE agent_id = ? AND specific_date = ? AND is_available = 0
        ORDER BY start_time
    ");
    $blockedStmt->execute([$agentId, $date]);
    $blockedSlots = $blockedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate time slots
    $slots = [];
    $current = new DateTime($availability['start_time']);
    $end = new DateTime($availability['end_time']);
    
    while ($current < $end) {
        $slotEnd = clone $current;
        $slotEnd->add(new DateInterval('PT' . $duration . 'M'));
        
        if ($slotEnd <= $end) {
            $isAvailable = true;
            $currentTime = $current->format('H:i:s');
            $slotEndTime = $slotEnd->format('H:i:s');
            
            // Check if this slot conflicts with any blocked slots
            foreach ($blockedSlots as $blocked) {
                $blockedStart = new DateTime($blocked['start_time']);
                $blockedEnd = new DateTime($blocked['end_time']);
                
                // Check for overlap
                if (($current < $blockedEnd) && ($slotEnd > $blockedStart)) {
                    $isAvailable = false;
                    break;
                }
            }
            
            if ($isAvailable) {
                $slots[] = $current->format('H:i');
            }
        }
        
        $current->add(new DateInterval('PT30M')); // 30-minute intervals
    }
    
    return $slots;
}

// Update the appointment booking AJAX handler section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_appointment') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Please log in to book an appointment']);
        exit;
    }
    
    $agentId = (int)$_POST['agent_id'];
    $propertyId = (int)$_POST['property_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    // Validate inputs
    if (!$agentId || !$propertyId || !$date || !$time) {
        echo json_encode(['success' => false, 'message' => 'Missing required information']);
        exit;
    }
    
    // Check if the time slot is still available
    $availableSlots = getAvailableTimeSlots($pdo, $agentId, $date);
    if (!in_array($time, $availableSlots)) {
        echo json_encode(['success' => false, 'message' => 'This time slot is no longer available']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create appointment in appointments table
        $appointmentDate = $date . ' ' . $time . ':00';
        $stmt = $pdo->prepare("
            INSERT INTO appointments (client_id, agent_id, property_id, appointment_date, status, location, created_at) 
            VALUES (?, ?, ?, ?, 'scheduled', 
                    (SELECT CONCAT(address_line1, ', ', city) FROM properties WHERE id = ?), 
                    NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $agentId, $propertyId, $appointmentDate, $propertyId]);
        $appointmentId = $pdo->lastInsertId();
        
        // Create corresponding unavailable slot in agent_availability
        $appointmentDateTime = new DateTime($appointmentDate);
        $dayOfWeek = $appointmentDateTime->format('l');
        $specificDate = $appointmentDateTime->format('Y-m-d');
        $startTime = $appointmentDateTime->format('H:i:s');
        
        // Calculate end time (30 minutes later)
        $endDateTime = clone $appointmentDateTime;
        $endDateTime->add(new DateInterval('PT30M'));
        $endTime = $endDateTime->format('H:i:s');
        
        // Insert unavailable slot
        $availStmt = $pdo->prepare("
            INSERT INTO agent_availability 
            (agent_id, day_of_week, specific_date, start_time, end_time, user_id, is_available, availability_type, notes)
            VALUES (?, ?, ?, ?, ?, ?, 0, 'exception', ?)
        ");
        $availStmt->execute([
            $agentId,
            $dayOfWeek,
            $specificDate,
            $startTime,
            $endTime,
            $_SESSION['user_id'],
            'Appointment #' . $appointmentId
        ]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Appointment booked successfully!']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $e->getMessage()]);
    }
    exit;
}

// Update the JavaScript section for fetching available times
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
            <div class="row">
                <?php foreach($properties as $p): 
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
                    
                    // Define $isAuction for all user types, not just clients
                    $isAuction = $p['property_type'] === 'auction';
                    $auctionDetails = null;
                    
                    // Add debugging output
                    echo "<!-- DEBUG: Property #{$p['id']} Type: {$p['property_type']} IsAuction: " . ($isAuction ? 'true' : 'false') . " -->";
                    
                    if ($isAuction) {
                        try {
                            $auctionQuery = $pdo->prepare("
                                SELECT pa.*, COUNT(ap.id) as participant_count, COUNT(DISTINCT ab.user_id) as bidder_count
                                FROM property_auctions pa
                                LEFT JOIN auction_participants ap ON pa.id = ap.auction_id
                                LEFT JOIN auction_bids ab ON pa.id = ab.auction_id
                                WHERE pa.property_id = ?
                                GROUP BY pa.id
                            ");
                            $auctionQuery->execute([$p['id']]);
                            $auctionDetails = $auctionQuery->fetch(PDO::FETCH_ASSOC);
                            
                            // Add debugging for auction details
                            echo "<!-- DEBUG: Auction details for #{$p['id']}: " . ($auctionDetails ? 'Found' : 'Not found') . " -->";
                            if ($auctionDetails) {
                                echo "<!-- Status: {$auctionDetails['status']} -->";
                            }
                        } catch (Exception $e) {
                            echo "<!-- ERROR: {$e->getMessage()} -->";
                        }
                    }
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
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="button" 
                                                class="btn btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#propertyModal"
                                                onclick="showPropertyDetails(<?= htmlspecialchars(json_encode($p)) ?>, <?= $isFavorited ? 'true' : 'false' ?>)">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </button>
                                        
                                        <?php if ($is_client && isset($_SESSION['user_id'])): ?>
                                            <!-- Remove the duplicate auction check since we already did it above -->
                                            <?php if ($isAuction && $auctionDetails && $auctionDetails['status'] === 'active'): ?>
                                                <button type="button" 
                                                       class="btn btn-warning"
                                                       data-bs-toggle="modal"
                                                       data-bs-target="#auctionModal"
                                                       onclick="showAuctionDetails(<?= htmlspecialchars(json_encode($p)) ?>, <?= htmlspecialchars(json_encode($auctionDetails)) ?>)">
                                                    <i class="fas fa-gavel me-1"></i>Bid Now - €<?= number_format($auctionDetails['current_price'], 0, ',', ' ') ?>
                                                </button>
                                            <?php else: ?>
                                                <a href="book_appointment.php?property_id=<?= $p['id'] ?>&agent_id=<?= $p['agent_id'] ?>"
                                                   class="btn btn-primary">
                                                    <i class="fas fa-calendar-alt me-1"></i>Book Appointment
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif (!isset($_SESSION['user_id'])): ?>
                                            <a href="../auth/login.php" class="btn btn-primary">
                                                <i class="fas fa-sign-in-alt me-1"></i>Login to
                                                <?php if ($isAuction): ?> Bid<?php else: ?> Book<?php endif; ?>
                                            </a>
                                        <?php elseif ($is_agent): ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class="fas fa-user-tie me-1"></i>Agent View
                                            </button>
                                        <?php elseif ($is_admin): ?>
                                            <?php if ($isAuction && $auctionDetails && $auctionDetails['status'] === 'active'): ?>
                                                <button type="button" 
                                                       class="btn btn-danger"
                                                       data-bs-toggle="modal"
                                                       data-bs-target="#auctionModal"
                                                       onclick="showAuctionDetailsAdmin(<?= htmlspecialchars(json_encode($p)) ?>, <?= htmlspecialchars(json_encode($auctionDetails)) ?>)">
                                                    <i class="fas fa-gavel me-1"></i>Manage Auction
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary" disabled>
                                                    <i class="fas fa-user-shield me-1"></i>Admin View
                                                </button>
                                            <?php endif; ?>
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
                <div>
                    <div class="text-muted small mb-1">Property ID: <span id="propertyModalId"></span></div>
                    <h5 class="modal-title" id="propertyModalTitle">Property Details</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="propertyModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Auction Modal -->
<div class="modal fade" id="auctionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="text-warning small mb-1">Auction Property ID: <span id="auctionPropertyId" style="font-weight: 700; color: #000;"></span></div>
                    <h5 class="modal-title" id="auctionModalTitle">Property Auction</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="auctionModalBody">
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

// Replace the showPropertyDetails function completely
window.showPropertyDetails = function(property, isFavorited) {
    console.log('Property data received:', property); // Debug
    
    // Set property ID directly - moved to top of function
    const propertyIdElement = document.getElementById('propertyModalId');
    if (propertyIdElement) {
        propertyIdElement.textContent = property.id;
        propertyIdElement.style.fontWeight = "700"; // Make more bold (was 600)
        propertyIdElement.style.color = "#000"; 
    }
    
    // Build the modal content
    const modalBody = document.getElementById('propertyModalBody');
    
    // Set the title after ID is set
    document.getElementById('propertyModalTitle').textContent = property.title;
    
    // Generate carousel HTML
    let images = [];
    if (property.images && typeof property.images === 'string') {
        try {
            images = JSON.parse(property.images);
        } catch (e) {
            console.error('Failed to parse images JSON:', e);
        }
    } else if (Array.isArray(property.images)) {
        images = property.images;
    }
    
    if (!images.length) {
        // Fallback to default images if no images in database
        images = [
            `../assets/images/property${property.id}-1.jpg`,
            `../assets/images/property${property.id}-2.jpg`,
            `../assets/images/property${property.id}-3.jpg`,
        ];
    }
    
    // Now construct the modal content
    const imageCarousel = `
        <div id="propertyImagesCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                ${images.map((img, index) => `
                    <div class="carousel-item ${index === 0 ? 'active' : ''}">
                        <img src="${img}" class="d-block w-100" alt="Property Image ${index + 1}">
                    </div>
                `).join('')}
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#propertyImagesCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#propertyImagesCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    `;
    
    // Add the image carousel to the modal body
    modalBody.innerHTML = imageCarousel;
    
    // Add favorite button if user is logged in and is a client
    if (userLoggedIn && isClient) {
        const favoriteButton = document.createElement('button');
        favoriteButton.className = `btn btn-sm ${isFavorited ? 'btn-danger' : 'btn-outline-danger'} mb-3`;
        favoriteButton.innerHTML = `
            <i class="fas fa-heart${isFavorited ? '' : '-o'}"></i> 
            ${isFavorited ? 'Remove from Favorites' : 'Add to Favorites'}
        `;
        favoriteButton.onclick = function() {
            const action = isFavorited ? 'remove_favorite' : 'add_favorite';
            fetch('../ajax/favorites.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=${action}&user_id=${<?= $_SESSION['user_id'] ?>}&property_id=${property.id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update favorite button state
                    isFavorited = !isFavorited;
                    favoriteButton.classList.toggle('btn-danger');
                    favoriteButton.classList.toggle('btn-outline-danger');
                    favoriteButton.innerHTML = `
                        <i class="fas fa-heart${isFavorited ? '' : '-o'}"></i> 
                        ${isFavorited ? 'Remove from Favorites' : 'Add to Favorites'}
                    `;
                    // Optionally, update global userFavorites array
                    if (isFavorited) {
                        userFavorites.push(property.id);
                    } else {
                        const index = userFavorites.indexOf(property.id);
                        if (index > -1) {
                            userFavorites.splice(index, 1);
                        }
                    }
                } else {
                    alert('Failed to update favorite status. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error updating favorite status:', error);
                alert('An error occurred while updating favorites. Please try again.');
            });
        };
        
        // Prepend the favorite button to the modal body
        modalBody.insertBefore(favoriteButton, modalBody.firstChild);
    }
    
    // Add appointment booking form if applicable
    if (userLoggedIn && isClient) {
        const appointmentForm = `
            <div class="mt-4">
                <h6>Book an Appointment</h6>
                <div class="mb-3">
                    <label for="appointment_date" class="form-label">Select Date</label>
                    <input type="date" class="form-control" id="appointment_date" required>
                </div>
                <div class="mb-3">
                    <label for="appointment_time" class="form-label">Select Time</label>
                    <select class="form-select" id="appointment_time" required>
                        <option value="">Select a time</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="bookAppointment(${property.id}, ${property.agent_id})">
                    <i class="fas fa-calendar-alt"></i> Book Appointment
                </button>
            </div>
        `;
        
        // Append the appointment form to the modal body
        modalBody.insertAdjacentHTML('beforeend', appointmentForm);
        
        // Fetch available times for the selected date
        const dateInput = document.getElementById('appointment_date');
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (selectedDate) {
                fetchAvailableTimes(property.agent_id, selectedDate);
            } else {
                // Clear time select if no date is selected
                const timeSelect = document.getElementById('appointment_time');
                timeSelect.innerHTML = '<option value="">Select a time</option>';
                timeSelect.disabled = true;
            }
        });
    }
};

// Update the fetchAvailableTimes function to use appointments.php
function fetchAvailableTimes(agentId, date) {
    fetch('../ajax/appointments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_available_times&agent_id=${agentId}&date=${date}`
    })
    .then(response => response.json())
    .then(data => {
        const timeSelect = document.getElementById('appointment_time');
        timeSelect.innerHTML = '<option value="">Select a time</option>';
        
        if (data.success && data.times.length > 0) {
            data.times.forEach(time => {
                const option = document.createElement('option');
                option.value = time;
                option.textContent = formatTime(time);
                timeSelect.appendChild(option);
            });
            timeSelect.disabled = false;
        } else {
            timeSelect.innerHTML = '<option value="">No available times</option>';
            timeSelect.disabled = true;
        }
    })
    .catch(error => {
        console.error('Error fetching times:', error);
        const timeSelect = document.getElementById('appointment_time');
        timeSelect.innerHTML = '<option value="">Error loading times</option>';
        timeSelect.disabled = true;
    });
}

// Function to format time (add this if it doesn't exist in explore.js)
function formatTime(time) {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// Update booking function to use appointments.php
function bookAppointment(propertyId, agentId) {
    const date = document.getElementById('appointment_date').value;
    const time = document.getElementById('appointment_time').value;
    
    if (!date || !time) {
        alert('Please select both date and time');
        return;
    }
    
    fetch('../ajax/appointments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=book_appointment&agent_id=${agentId}&property_id=${propertyId}&date=${date}&time=${time}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment booked successfully!');
            // Close modal and refresh available times
            const modal = bootstrap.Modal.getInstance(document.getElementById('propertyModal'));
            modal.hide();
            // Optionally refresh the times to show the slot is no longer available
            fetchAvailableTimes(agentId, date);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error booking appointment:', error);
        alert('Failed to book appointment. Please try again.');
    });
}

// Force the property ID to show by checking when modal is opened
document.addEventListener('DOMContentLoaded', function() {
    const propertyModal = document.getElementById('propertyModal');
    
    if (propertyModal) {
        propertyModal.addEventListener('show.bs.modal', function(event) {
            // Get button that triggered the modal
            const button = event.relatedTarget;
            
            // Check if modal is fully shown
            setTimeout(function() {
                // Make sure property ID is displayed by getting it from the data attribute
                const idSpan = document.getElementById('propertyModalId');
                if (idSpan && (!idSpan.textContent || idSpan.textContent === '')) {
                    // Try to get ID from modal title or other source
                    const clickedProperty = button ? button.closest('.property-card') : null;
                    if (clickedProperty) {
                        const propertyId = clickedProperty.getAttribute('data-property-id');
                        if (propertyId) {
                            idSpan.textContent = propertyId;
                        }
                    }
                }
            }, 100);
        });
    }
});

// Add auction functionality
function showAuctionDetails(property, auctionDetails) {
    console.log('Auction data received:', auctionDetails);
    
    // Set property ID
    const propertyIdElement = document.getElementById('auctionPropertyId');
    if (propertyIdElement) {
        propertyIdElement.textContent = property.id;
    }
    
    // Set the title
    document.getElementById('auctionModalTitle').textContent = property.title + ' - Auction';
    
    // Build the modal content
    const modalBody = document.getElementById('auctionModalBody');
    
    // Parse images
    let images = [];
    if (property.images && typeof property.images === 'string') {
        try {
            images = JSON.parse(property.images);
        } catch (e) {
            console.error('Failed to parse images JSON:', e);
        }
    } else if (Array.isArray(property.images)) {
        images = property.images;
    }
    
    if (!images.length) {
        images = [
            `../assets/images/property${property.id}-1.jpg`,
            `../assets/images/property${property.id}-2.jpg`,
            `../assets/images/property${property.id}-3.jpg`,
        ];
    }
    
    // Check if user has verified payment methods
    fetch('../ajax/user_verification.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=check_verification'
    })
    .then(response => response.json())
    .then(data => {
        // First, add carousel
        const imageCarousel = `
            <div id="auctionImagesCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
                <div class="carousel-inner">
                    ${images.map((img, index) => `
                        <div class="carousel-item ${index === 0 ? 'active' : ''}">
                            <img src="${img}" class="d-block w-100" alt="Property Image ${index + 1}" 
                                 style="max-height: 300px; object-fit: cover;">
                        </div>
                    `).join('')}
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#auctionImagesCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#auctionImagesCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-gavel me-2"></i> This property is available through auction.
            </div>
            
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Auction Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Starting Price:</strong> €${parseInt(auctionDetails.starting_price).toLocaleString()}</p>
                            <p><strong>Current Price:</strong> <span class="text-primary fw-bold">€${parseInt(auctionDetails.current_price).toLocaleString()}</span></p>
                            <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Participants:</strong> ${auctionDetails.participant_count || 0}</p>
                            <p><strong>Bidders:</strong> ${auctionDetails.bidder_count || 0}</p>
                            <p><strong>Started:</strong> ${new Date(auctionDetails.start_date).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Property Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Type:</strong> ${property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1)}</p>
                            <p><strong>Location:</strong> ${property.city}</p>
                            <p><strong>Address:</strong> ${property.address_line1}</p>
                        </div>
                        <div class="col-md-6">
                            ${property.bedrooms ? `<p><strong>Bedrooms:</strong> ${property.bedrooms}</p>` : ''}
                            ${property.bathrooms ? `<p><strong>Bathrooms:</strong> ${property.bathrooms}</p>` : ''}
                            ${property.living_area ? `<p><strong>Living Area:</strong> ${property.living_area} m²</p>` : ''}
                        </div>
                    </div>
                    <p><strong>Description:</strong> ${property.description}</p>
                </div>
            </div>
        `;
        
        // Set the HTML content
        modalBody.innerHTML = imageCarousel;
        
        // Now add the bidding form, if user is verified
        if (data.verified) {
            const bidForm = document.createElement('div');
            bidForm.className = 'card';
            bidForm.innerHTML = `
                <div class="card-header bg-warning">
                    <h6 class="mb-0">Place Your Bid</h6>
                </div>
                <div class="card-body">
                    <form id="bidForm" class="row g-3 align-items-center">
                        <input type="hidden" name="auction_id" value="${auctionDetails.id}">
                        <input type="hidden" name="property_id" value="${property.id}">
                        <div class="col-md-7">
                            <label for="bidAmount" class="form-label">Your Bid (€)</label>
                            <input type="number" class="form-control" id="bidAmount" name="bid_amount" 
                                   min="${parseInt(auctionDetails.current_price) + 1000}" 
                                   value="${parseInt(auctionDetails.current_price) + 5000}"
                                   step="1000" required>
                            <div class="form-text">Minimum bid: €${parseInt(auctionDetails.current_price) + 1000}</div>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <button type="button" class="btn btn-warning w-100" onclick="placeBid()">
                                <i class="fas fa-gavel me-2"></i>Place Bid
                            </button>
                        </div>
                    </form>
                </div>
            `;
            modalBody.appendChild(bidForm);
        } else {
            // Show verification warning
            const verificationWarning = document.createElement('div');
            verificationWarning.className = 'alert alert-danger';
            verificationWarning.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Verification Required!</strong> You need to verify your payment method before placing bids.
                <hr>
                <a href="../client/dashboard.php?section=payment" class="btn btn-sm btn-danger">
                    <i class="fas fa-credit-card me-1"></i>Verify Payment Method
                </a>
            `;
            modalBody.appendChild(verificationWarning);
        }
        
        // Add bidding history
        fetch('../ajax/auctions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_bids&auction_id=${auctionDetails.id}`
        })
        .then(response => response.json())
        .then(bidData => {
            const bidsSection = document.createElement('div');
            bidsSection.className = 'mt-4';
            bidsSection.innerHTML = `
                <h6 class="mb-3">Bidding History</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Bidder</th>
                                <th>Amount</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody id="bidHistoryBody">
                            ${bidData.bids && bidData.bids.length > 0 ? 
                                bidData.bids.map(bid => `
                                    <tr>
                                        <td>${bid.user_name}</td>
                                        <td>€${parseInt(bid.bid_amount).toLocaleString()}</td>
                                        <td>${new Date(bid.bid_time).toLocaleString()}</td>
                                    </tr>
                                `).join('') :
                                '<tr><td colspan="3" class="text-center">No bids yet. Be the first to bid!</td></tr>'
                            }
                        </tbody>
                    </table>
                </div>
            `;
            modalBody.appendChild(bidsSection);
        });
    })
    .catch(error => {
        console.error('Error checking verification:', error);
        modalBody.innerHTML += `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error loading auction details. Please try again later.
            </div>
        `;
    });
}

// Admin auction management
function showAuctionDetailsAdmin(property, auctionDetails) {
    // Similar to showAuctionDetails but with admin controls
    showAuctionDetails(property, auctionDetails);
    
    // Add admin controls
    setTimeout(() => {
        const modalBody = document.getElementById('auctionModalBody');
        const adminControls = document.createElement('div');
        adminControls.className = 'card mt-4 border-danger';
        adminControls.innerHTML = `
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">Admin Controls</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-danger" onclick="endAuction(${auctionDetails.id})">
                        <i class="fas fa-stop-circle me-2"></i>End Auction Now
                    </button>
                    <button class="btn btn-outline-warning" onclick="extendAuction(${auctionDetails.id})">
                        <i class="fas fa-clock me-2"></i>Extend Auction 24h
                    </button>
                    <button class="btn btn-outline-secondary" onclick="cancelAuction(${auctionDetails.id})">
                        <i class="fas fa-ban me-2"></i>Cancel Auction
                    </button>
                </div>
            </div>
        `;
        modalBody.appendChild(adminControls);
    }, 500);
}

// Function to place a bid
function placeBid() {
    const bidAmount = document.getElementById('bidAmount').value;
    const form = document.getElementById('bidForm');
    const auctionId = form.elements['auction_id'].value;
    const propertyId = form.elements['property_id'].value;
    
    if (!bidAmount) {
        alert('Please enter a bid amount');
        return;
    }
    
    fetch('../ajax/auctions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=place_bid&auction_id=${auctionId}&property_id=${propertyId}&bid_amount=${bidAmount}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Your bid was placed successfully!');
            // Refresh the auction modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('auctionModal'));
            modal.hide();
            
            // Update the property price display in the card
            location.reload(); // Simple solution - reload the page
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error placing bid:', error);
        alert('An error occurred while placing your bid. Please try again.');
    });
}

// Admin functions
function endAuction(auctionId) {
    if (confirm('Are you sure you want to end this auction? The highest bidder will win.')) {
        fetch('../ajax/auctions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=end_auction&auction_id=${auctionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Auction ended successfully. The winner has been notified.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error ending auction:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

function extendAuction(auctionId) {
    if (confirm('Are you sure you want to extend this auction by 24 hours?')) {
        fetch('../ajax/auctions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=extend_auction&auction_id=${auctionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Auction extended by 24 hours.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error extending auction:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

function cancelAuction(auctionId) {
    if (confirm('Are you sure you want to cancel this auction? All bids will be cancelled.')) {
        fetch('../ajax/auctions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=cancel_auction&auction_id=${auctionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Auction cancelled successfully.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error cancelling auction:', error);
            alert('An error occurred. Please try again.');
        });
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/explore.js"></script>
</body>
</html>