<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Debugging
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Session: ";
    print_r($_SESSION);
    echo "\nGET: ";
    print_r($_GET);
    echo "</pre>";
}

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$db = $database->getConnection();

$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Check if property exists
try {
    $check_stmt = $db->prepare("SELECT * FROM properties WHERE id = :id");
    $check_stmt->execute(['id' => $property_id]);
    $property = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$property) {
        // Don't redirect immediately, show an error message instead
        $error = "Property not found with ID: $property_id";
    } else {
        // Initialize missing fields with default values based on actual database schema
        $property['state'] = $property['state'] ?? null;
        $property['postal_code'] = $property['postal_code'] ?? '';
        $property['country'] = $property['country'] ?? 'France';
        $property['bedrooms'] = $property['bedrooms'] ?? 0;
        $property['bathrooms'] = $property['bathrooms'] ?? 0;
        $property['year_built'] = $property['year_built'] ?? 0;
        $property['living_area'] = $property['living_area'] ?? 0;
        $property['has_parking'] = $property['has_parking'] ?? 0;
        $property['has_garden'] = $property['has_garden'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log("Property fetch error: " . $e->getMessage());
    $error = "Error loading property: " . $e->getMessage();
}

// Get all agents for the dropdown - only if we have a valid property
if ($property) {
    try {
        // First try to get from agents table with names
        $agents_stmt = $db->query("
            SELECT a.user_id as id, CONCAT(a.first_name, ' ', a.last_name) as name 
            FROM agents a 
            INNER JOIN users u ON a.user_id = u.id
            WHERE u.role = 'agent'
            ORDER BY a.first_name, a.last_name
        ");
        $agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If that fails, try the users table
        if (empty($agents)) {
            $agents_stmt = $db->query("
                SELECT id, CONCAT(first_name, ' ', last_name) as name 
                FROM users 
                WHERE role = 'agent'
                ORDER BY first_name, last_name
            ");
            $agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Agents fetch error: " . $e->getMessage());
        $agents = [];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $property_type = $_POST['property_type'] ?? '';
    $status = $_POST['status'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $agent_id = (int)($_POST['agent_id'] ?? 0);
    $bedrooms = (int)($_POST['bedrooms'] ?? 0);
    $bathrooms = (int)($_POST['bathrooms'] ?? 0);
    $living_area = (int)($_POST['living_area'] ?? 0);
    $year_built = (int)($_POST['year_built'] ?? 0);
    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $has_garden = isset($_POST['has_garden']) ? 1 : 0;
    
    // Address fields
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'France');
    
    // Basic validation
    if (empty($title)) {
        $error = 'Property title is required.';
    } elseif (empty($property_type)) {
        $error = 'Property type is required.';
    } elseif (empty($status)) {
        $error = 'Property status is required.';
    } elseif ($price <= 0) {
        $error = 'Property price must be greater than zero.';
    } elseif ($agent_id <= 0) {
        $error = 'Please select an agent.';
    } elseif (empty($address_line1) || empty($city)) {
        $error = 'Address and city are required.';
    } else {
        try {
            $db->beginTransaction();
            
            // Update property data
            $update_stmt = $db->prepare("
                UPDATE properties SET
                    title = :title,
                    description = :description,
                    property_type = :property_type,
                    status = :status,
                    price = :price,
                    agent_id = :agent_id,
                    bedrooms = :bedrooms,
                    bathrooms = :bathrooms,
                    living_area = :living_area,
                    year_built = :year_built,
                    has_parking = :has_parking,
                    has_garden = :has_garden,
                    address_line1 = :address_line1,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    country = :country,
                    updated_at = NOW()
                WHERE id = :property_id
            ");
            
            $update_stmt->execute([
                'title' => $title,
                'description' => $description,
                'property_type' => $property_type,
                'status' => $status,
                'price' => $price,
                'agent_id' => $agent_id,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'living_area' => $living_area,
                'year_built' => $year_built,
                'has_parking' => $has_parking,
                'has_garden' => $has_garden,
                'address_line1' => $address_line1,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'country' => $country,
                'property_id' => $property_id
            ]);
            
            $db->commit();
            
            $success = 'Property has been updated successfully.';
            
            // Reload property data
            $check_stmt->execute(['id' => $property_id]);
            $property = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Property update error: " . $e->getMessage());
            $error = 'Error updating property. Please try again.';
        }
    }
}

// After retrieving property details, add this check for auction property type
$isAuctionProperty = ($property['property_type'] === 'auction');

// Check if this auction property has an auction set up
$auctionExists = false;
$auctionStatus = null;
if ($isAuctionProperty) {
    $auctionQuery = $db->prepare("
        SELECT id, status 
        FROM property_auctions 
        WHERE property_id = ?
    ");
    $auctionQuery->execute([$property['id']]);
    $auctionData = $auctionQuery->fetch(PDO::FETCH_ASSOC);
    $auctionExists = ($auctionData !== false);
    $auctionStatus = $auctionData['status'] ?? null;
}

// Set the current page for navigation highlighting
$current_page = 'properties';

// Include the admin header file
include '../includes/admin_header.php';

// Add this near the beginning of the file, where other messages are handled
$auctionSetupSuccess = isset($_GET['success']) && $_GET['success'] === 'auction_setup';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
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

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--admin-primary);
            margin-bottom: 1rem;
        }

        .btn-admin-primary {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-admin-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        
        .property-images-display {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-lg-12">
                <!-- Page Header -->
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="h4 mb-0">
                                <i class="fas fa-edit me-2"></i>Edit Property
                            </h1>
                            <a href="manage_properties.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Properties
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error && !$property): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        <div class="mt-3">
                            <a href="manage_properties.php" class="btn btn-dark">
                                <i class="fas fa-arrow-left me-2"></i>Return to Properties
                            </a>
                        </div>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($auctionSetupSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Auction was set up successfully for this property.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($property): ?>
                    <!-- Edit Property Form -->
                    <form method="POST" action="">
                        <div class="content-card">
                            <div class="card-body p-4">
                                <!-- Basic Information -->
                                <div class="form-section">
                                    <div class="form-section-title">Basic Information</div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label class="form-label">Property Title *</label>
                                                <input type="text" class="form-control" name="title" 
                                                       value="<?= htmlspecialchars($property['title']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Assigned Agent *</label>
                                                <select class="form-select" name="agent_id" required>
                                                    <option value="">Select Agent</option>
                                                    <?php foreach ($agents as $agent): ?>
                                                        <option value="<?= $agent['id'] ?>" <?= $property['agent_id'] == $agent['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($agent['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Property Type *</label>
                                                <select class="form-select" name="property_type" required>
                                                    <option value="">Select Type</option>
                                                    <option value="house" <?= $property['property_type'] == 'house' ? 'selected' : '' ?>>House</option>
                                                    <option value="apartment" <?= $property['property_type'] == 'apartment' ? 'selected' : '' ?>>Apartment</option>
                                                    <option value="commercial" <?= $property['property_type'] == 'commercial' ? 'selected' : '' ?>>Commercial</option>
                                                    <option value="land" <?= $property['property_type'] == 'land' ? 'selected' : '' ?>>Land</option>
                                                    <option value="rental" <?= $property['property_type'] == 'rental' ? 'selected' : '' ?>>Rental</option>
                                                    <option value="auction" <?= $property['property_type'] == 'auction' ? 'selected' : '' ?> style="<?= $property['property_type'] == 'auction' ? 'background-color: #fff3cd; font-weight: bold;' : '' ?>">
                                                        Auction
                                                    </option>
                                                </select>
                                                <?php if ($property['property_type'] === 'auction'): ?>
                                                <small class="form-text text-warning">
                                                    <i class="fas fa-info-circle"></i> Changing property type from auction will remove it from active auctions.
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Status *</label>
                                                <select class="form-select" name="status" required>
                                                    <option value="available" <?= $property['status'] == 'available' ? 'selected' : '' ?>>Available</option>
                                                    <option value="sold" <?= $property['status'] == 'sold' ? 'selected' : '' ?>>Sold</option>
                                                    <option value="rented" <?= $property['status'] == 'rented' ? 'selected' : '' ?>>Rented</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Price (€) *</label>
                                                <input type="number" class="form-control" name="price" step="1000" min="0"
                                                       value="<?= $property['price'] ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="5"><?= htmlspecialchars($property['description']) ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Features -->
                                <div class="form-section">
                                    <div class="form-section-title">Features & Amenities</div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Bedrooms</label>
                                                <input type="number" class="form-control" name="bedrooms" min="0"
                                                       value="<?= $property['bedrooms'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Bathrooms</label>
                                                <input type="number" class="form-control" name="bathrooms" min="0" step="0.5"
                                                       value="<?= $property['bathrooms'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Living Area (m²)</label>
                                                <input type="number" class="form-control" name="living_area" min="0"
                                                       value="<?= $property['living_area'] ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Year Built</label>
                                                <input type="number" class="form-control" name="year_built" min="1800" max="<?= date('Y') ?>"
                                                       value="<?= $property['year_built'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="has_parking" id="has_parking"
                                                      <?= $property['has_parking'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="has_parking">Has Parking</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="has_garden" id="has_garden"
                                                      <?= $property['has_garden'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="has_garden">Has Garden</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Location -->
                                <div class="form-section">
                                    <div class="form-section-title">Location</div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Address Line 1 *</label>
                                                <input type="text" class="form-control" name="address_line1" required
                                                       value="<?= htmlspecialchars($property['address_line1']) ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">City *</label>
                                                <input type="text" class="form-control" name="city" required
                                                       value="<?= htmlspecialchars($property['city']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">State/Province</label>
                                                <input type="text" class="form-control" name="state"
                                                       value="<?= htmlspecialchars($property['state'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Postal Code</label>
                                                <input type="text" class="form-control" name="postal_code"
                                                       value="<?= htmlspecialchars($property['postal_code'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Country</label>
                                                <input type="text" class="form-control" name="country"
                                                       value="<?= htmlspecialchars($property['country'] ?? 'France') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Current Images - Simplified view -->
                                <div class="form-section">
                                    <div class="form-section-title">Property Image</div>
                                    
                                    <div class="text-center mb-3">
                                        <?php 
                                        $image_path = "../assets/images/property{$property_id}-2.jpg";
                                        $image_exists = file_exists($image_path);
                                        ?>
                                        
                                        <?php if ($image_exists): ?>
                                            <img src="<?= $image_path ?>" 
                                                 alt="Property <?= $property_id ?>" 
                                                 class="img-fluid rounded" 
                                                 style="max-height: 300px;">
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No image available for this property.</p>
                                                <p class="text-muted small">
                                                    Expected image path: <code><?= $image_path ?></code>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Auction Property Details - New Section -->
                                <?php if ($isAuctionProperty): ?>
                                <div class="card mb-4">
                                    <div class="card-header pb-0">
                                        <h6>Property Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Existing title field -->
                                        <div class="form-group">
                                            <label for="title">Property Title</label>
                                            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($property['title']) ?>" required>
                                        </div>
                                        
                                        <!-- Auction badge and link -->
                                        <div class="alert alert-warning mt-3 d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-gavel me-2"></i>
                                                <strong>Auction Property</strong>
                                                <?php if ($auctionExists): ?>
                                                    <span class="badge <?= $auctionStatus === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= ucfirst($auctionStatus) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">No Auction Set Up</span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="auction_setup.php?property_id=<?= $property['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-hammer me-1"></i> <?= $auctionExists ? 'Manage Auction' : 'Set Up Auction' ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Submit Button -->
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="manage_properties.php" class="btn btn-outline-secondary me-md-2">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn-admin-primary">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>No property ID specified or property not found.
                        <div class="mt-3">
                            <a href="manage_properties.php" class="btn btn-dark">
                                <i class="fas fa-list me-2"></i>View All Properties
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (Bundle) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const propertyTypeSelect = document.getElementById('property_type');
        const originalType = propertyTypeSelect.value;
        
        propertyTypeSelect.addEventListener('change', function() {
            const newType = this.value;
            
            // If changing from auction to something else
            if (originalType === 'auction' && newType !== 'auction') {
                if (!confirm('Changing from auction property type will remove this property from any active auctions. Are you sure?')) {
                    this.value = 'auction'; // Revert change if not confirmed
                }
            }
            
            // If changing to auction
            if (newType === 'auction') {
                // Add auction styling
                this.options[this.selectedIndex].style.backgroundColor = '#fff3cd';
                this.options[this.selectedIndex].style.fontWeight = 'bold';
            }
        });
    });
    </script>
</body>
</html>