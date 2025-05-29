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

$error = '';
$success = '';

// Get all agents for assignment
$agents = [];
try {
    $agents_query = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name 
                     FROM users u 
                     WHERE u.role = 'agent' 
                     ORDER BY u.first_name, u.last_name";
    $agents_stmt = $db->query($agents_query);
    $agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Agents fetch error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Property data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $property_type = $_POST['property_type'] ?? '';
    $listing_type = $_POST['listing_type'] ?? 'sale';
    $agent_id = (int)($_POST['agent_id'] ?? 0);
    
    // Address
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'France');
    
    // Property details
    $bedrooms = (int)($_POST['bedrooms'] ?? 0);
    $bathrooms = (int)($_POST['bathrooms'] ?? 0);
    $total_rooms = (int)($_POST['total_rooms'] ?? 0);
    $living_area = (float)($_POST['living_area'] ?? 0);
    $lot_size = (float)($_POST['lot_size'] ?? 0);
    $year_built = (int)($_POST['year_built'] ?? 0);
    
    // Features
    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $parking_spaces = (int)($_POST['parking_spaces'] ?? 0);
    $has_balcony = isset($_POST['has_balcony']) ? 1 : 0;
    $has_terrace = isset($_POST['has_terrace']) ? 1 : 0;
    $has_garden = isset($_POST['has_garden']) ? 1 : 0;
    $heating_type = $_POST['heating_type'] ?? '';
    $energy_rating = $_POST['energy_rating'] ?? '';
    
    // Validation
    if (empty($title) || empty($description) || $price <= 0 || empty($property_type) || empty($address_line1) || empty($city) || $agent_id <= 0) {
        $error = 'Please fill in all required fields and select an agent.';
    } else {
        try {
            $query = "INSERT INTO properties (
                        agent_id, title, description, price, property_type, listing_type,
                        address_line1, address_line2, city, state, postal_code, country,
                        bedrooms, bathrooms, total_rooms, living_area, lot_size, year_built,
                        has_parking, parking_spaces, has_balcony, has_terrace, has_garden,
                        heating_type, energy_rating, status, created_at
                      ) VALUES (
                        :agent_id, :title, :description, :price, :property_type, :listing_type,
                        :address_line1, :address_line2, :city, :state, :postal_code, :country,
                        :bedrooms, :bathrooms, :total_rooms, :living_area, :lot_size, :year_built,
                        :has_parking, :parking_spaces, :has_balcony, :has_terrace, :has_garden,
                        :heating_type, :energy_rating, 'available', NOW()
                      )";
            
            $stmt = $db->prepare($query);
            $params = [
                'agent_id' => $agent_id,
                'title' => $title,
                'description' => $description,
                'price' => $price,
                'property_type' => $property_type,
                'listing_type' => $listing_type,
                'address_line1' => $address_line1,
                'address_line2' => $address_line2,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'country' => $country,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'total_rooms' => $total_rooms,
                'living_area' => $living_area,
                'lot_size' => $lot_size,
                'year_built' => $year_built,
                'has_parking' => $has_parking,
                'parking_spaces' => $parking_spaces,
                'has_balcony' => $has_balcony,
                'has_terrace' => $has_terrace,
                'has_garden' => $has_garden,
                'heating_type' => $heating_type,
                'energy_rating' => $energy_rating
            ];
            
            $stmt->execute($params);
            $success = 'Property added successfully!';
            
        } catch (PDOException $e) {
            error_log("Property save error: " . $e->getMessage());
            $error = 'Database error occurred.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: #dc3545;
            --admin-secondary: #c82333;
            --admin-bg: #f8f9fa;
        }

        body {
            background: var(--admin-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none !important;
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

        .form-control-custom {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
            background: white;
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

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            border-color: var(--admin-primary);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../pages/home.php" class="brand-logo">
                        <i class="fas fa-building me-2"></i>Omnes Real Estate - Admin
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <a href="dashboard.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-1"></i>Dashboard
                    </a>
                    <a href="manage_properties.php" class="btn btn-light">
                        <i class="fas fa-list me-1"></i>All Properties
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <h1 class="h4 mb-0">
                    <i class="fas fa-plus-circle me-2"></i>Add New Property
                </h1>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">Add a new property to the system and assign it to an agent.</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="content-card">
                <div class="card-body p-4">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Property Title *</label>
                            <input type="text" class="form-control form-control-custom" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description *</label>
                            <textarea class="form-control form-control-custom" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Price (€) *</label>
                                    <input type="number" class="form-control form-control-custom" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Property Type *</label>
                                    <select class="form-control form-control-custom" name="property_type" required>
                                        <option value="">Select Type</option>
                                        <option value="house">House</option>
                                        <option value="apartment">Apartment</option>
                                        <option value="land">Land</option>
                                        <option value="commercial">Commercial</option>
                                        <option value="rental">Rental</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Listing Type</label>
                                    <select class="form-control form-control-custom" name="listing_type">
                                        <option value="sale">For Sale</option>
                                        <option value="rent">For Rent</option>
                                        <option value="auction">Auction</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assign to Agent *</label>
                            <select class="form-control form-control-custom" name="agent_id" required>
                                <option value="">Select Agent</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-map-marker-alt me-2"></i>Address
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Address Line 1 *</label>
                            <input type="text" class="form-control form-control-custom" name="address_line1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Address Line 2</label>
                            <input type="text" class="form-control form-control-custom" name="address_line2">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">City *</label>
                                    <input type="text" class="form-control form-control-custom" name="city" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">State/Region</label>
                                    <input type="text" class="form-control form-control-custom" name="state">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Postal Code</label>
                                    <input type="text" class="form-control form-control-custom" name="postal_code">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Country</label>
                            <input type="text" class="form-control form-control-custom" name="country" value="France">
                        </div>
                    </div>

                    <!-- Property Details -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-home me-2"></i>Property Details
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Bedrooms</label>
                                    <input type="number" class="form-control form-control-custom" name="bedrooms" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Bathrooms</label>
                                    <input type="number" class="form-control form-control-custom" name="bathrooms" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Total Rooms</label>
                                    <input type="number" class="form-control form-control-custom" name="total_rooms" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Year Built</label>
                                    <input type="number" class="form-control form-control-custom" name="year_built" min="1800" max="<?= date('Y') + 5 ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Living Area (m²)</label>
                                    <input type="number" step="0.01" class="form-control form-control-custom" name="living_area" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Lot Size (m²)</label>
                                    <input type="number" step="0.01" class="form-control form-control-custom" name="lot_size" min="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-star me-2"></i>Features
                        </h5>
                        
                        <div class="checkbox-grid">
                            <div class="checkbox-item">
                                <input type="checkbox" class="form-check-input me-2" name="has_parking" id="has_parking">
                                <label class="form-check-label" for="has_parking">Has Parking</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" class="form-check-input me-2" name="has_balcony" id="has_balcony">
                                <label class="form-check-label" for="has_balcony">Has Balcony</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" class="form-check-input me-2" name="has_terrace" id="has_terrace">
                                <label class="form-check-label" for="has_terrace">Has Terrace</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" class="form-check-input me-2" name="has_garden" id="has_garden">
                                <label class="form-check-label" for="has_garden">Has Garden</label>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Parking Spaces</label>
                                    <input type="number" class="form-control form-control-custom" name="parking_spaces" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Heating Type</label>
                                    <select class="form-control form-control-custom" name="heating_type">
                                        <option value="">Select Heating</option>
                                        <option value="gas">Gas</option>
                                        <option value="electric">Electric</option>
                                        <option value="oil">Oil</option>
                                        <option value="solar">Solar</option>
                                        <option value="geothermal">Geothermal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Energy Rating</label>
                            <select class="form-control form-control-custom" name="energy_rating">
                                <option value="">Select Rating</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                                <option value="F">F</option>
                                <option value="G">G</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="manage_properties.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn-admin-primary">
                            <i class="fas fa-save me-2"></i>Add Property
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>