<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn() || !isAgent()) {
    redirect('../auth/login.php');
}

// Use the global $pdo connection (not Database::getInstance())
$db = $pdo;

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$property_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'add_property' || $form_action === 'edit_property') {
        // Property data
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $property_type = $_POST['property_type'] ?? '';
        $listing_type = $_POST['listing_type'] ?? 'sale';
        
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
        if (empty($title)) {
            $error = 'Property title is required.';
        } elseif (empty($description)) {
            $error = 'Property description is required.';
        } elseif ($price <= 0) {
            $error = 'Please enter a valid price.';
        } elseif (empty($property_type)) {
            $error = 'Please select a property type.';
        } elseif (empty($address_line1)) {
            $error = 'Address is required.';
        } elseif (empty($city)) {
            $error = 'City is required.';
        } else {
            try {
                if ($form_action === 'add_property') {
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
                    $message = 'Property added successfully!';
                } else {
                    $query = "UPDATE properties SET
                                title = :title, description = :description, price = :price, 
                                property_type = :property_type, listing_type = :listing_type,
                                address_line1 = :address_line1, address_line2 = :address_line2, 
                                city = :city, state = :state, postal_code = :postal_code, country = :country,
                                bedrooms = :bedrooms, bathrooms = :bathrooms, total_rooms = :total_rooms,
                                living_area = :living_area, lot_size = :lot_size, year_built = :year_built,
                                has_parking = :has_parking, parking_spaces = :parking_spaces,
                                has_balcony = :has_balcony, has_terrace = :has_terrace, has_garden = :has_garden,
                                heating_type = :heating_type, energy_rating = :energy_rating,
                                updated_at = NOW()
                              WHERE id = :property_id AND agent_id = :agent_id";
                    $message = 'Property updated successfully!';
                }
                
                $stmt = $db->prepare($query);
                $params = [
                    'agent_id' => $_SESSION['user_id'],
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
                
                if ($form_action === 'edit_property') {
                    $params['property_id'] = $property_id;
                }
                
                if ($stmt->execute($params)) {
                    $success = $message;
                    
                    if ($form_action === 'add_property') {
                        $action = 'list'; // Reset to list view after adding
                    }
                } else {
                    $error = 'Database error occurred while saving the property.';
                }
            } catch (PDOException $e) {
                error_log("Property save error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        }
    }
    
    elseif ($form_action === 'change_status') {
        $status = $_POST['status'] ?? '';
        $property_id = $_POST['property_id'] ?? '';
        
        if (in_array($status, ['available', 'pending', 'sold', 'rented', 'withdrawn'])) {
            try {
                $query = "UPDATE properties SET status = :status, updated_at = NOW() 
                         WHERE id = :property_id AND agent_id = :agent_id";
                $stmt = $db->prepare($query);
                if ($stmt->execute([
                    'status' => $status,
                    'property_id' => $property_id,
                    'agent_id' => $_SESSION['user_id']
                ])) {
                    $success = 'Property status updated successfully!';
                } else {
                    $error = 'Error updating property status.';
                }
            } catch (PDOException $e) {
                error_log("Status update error: " . $e->getMessage());
                $error = 'Error updating property status.';
            }
        }
    }
    
    elseif ($form_action === 'delete_property') {
        $property_id = $_POST['property_id'] ?? '';
        
        try {
            // Check if property has any appointments
            $check_query = "SELECT COUNT(*) as count FROM appointments WHERE property_id = ? AND status = 'scheduled'";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$property_id]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $error = 'Cannot delete property with scheduled appointments.';
            } else {
                $delete_query = "DELETE FROM properties WHERE id = ? AND agent_id = ?";
                $delete_stmt = $db->prepare($delete_query);
                if ($delete_stmt->execute([$property_id, $_SESSION['user_id']])) {
                    $success = 'Property deleted successfully!';
                } else {
                    $error = 'Error deleting property.';
                }
            }
        } catch (PDOException $e) {
            error_log("Property delete error: " . $e->getMessage());
            $error = 'Error deleting property.';
        }
    }
}

// Get agent's properties - FIXED QUERY
$properties = [];
try {
    // Simple query without property_views table (since it doesn't exist)
    $query = "SELECT p.*, 
                     (SELECT COUNT(*) FROM appointments WHERE property_id = p.id) as appointment_count
              FROM properties p 
              WHERE p.agent_id = :agent_id 
              ORDER BY p.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the number of properties found
    error_log("Agent ID: " . $_SESSION['user_id'] . " - Properties found: " . count($properties));
    
} catch (Exception $e) {
    error_log("Properties fetch error: " . $e->getMessage());
    $properties = []; // Ensure it's an array
}

// Get specific property for editing
$property = null;
if ($action === 'edit' && $property_id) {
    try {
        $query = "SELECT * FROM properties WHERE id = :property_id AND agent_id = :agent_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['property_id' => $property_id, 'agent_id' => $_SESSION['user_id']]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$property) {
            $error = 'Property not found.';
            $action = 'list';
        }
    } catch (Exception $e) {
        error_log("Property fetch error: " . $e->getMessage());
        $error = 'Error loading property.';
        $action = 'list';
    }
}

// Property statistics
$stats = [
    'total_properties' => count($properties),
    'available_properties' => count(array_filter($properties, fn($p) => $p['status'] === 'available')),
    'sold_properties' => count(array_filter($properties, fn($p) => $p['status'] === 'sold')),
    'pending_properties' => count(array_filter($properties, fn($p) => $p['status'] === 'pending')),
    'total_value' => array_sum(array_column($properties, 'price')),
    'avg_price' => count($properties) > 0 ? array_sum(array_column($properties, 'price')) / count($properties) : 0
];

// Debug: Show some info
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Agent ID: " . $_SESSION['user_id'] . "\n";
    echo "Total Properties: " . count($properties) . "\n";
    echo "Stats: " . print_r($stats, true);
    echo "</pre>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties Management - Agent Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Navigation CSS -->
    <link href="../assets/css/agent_navigation.css" rel="stylesheet">
    
    <style>
        :root {
            --agent-primary: #2c5aa0;
            --agent-secondary: #4a90e2;
            --agent-success: #28a745;
            --agent-warning: #ffc107;
            --agent-danger: #dc3545;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-top: 80px;
        }

        .agent-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            padding: 1rem 0;
            color: white;
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1020;
        }

        .page-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stats-value.primary { color: var(--agent-primary); }
        .stats-value.success { color: var(--agent-success); }
        .stats-value.warning { color: var(--agent-warning); }
        .stats-value.danger { color: var(--agent-danger); }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .content-card-body {
            padding: 2rem;
        }

        .property-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .property-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .property-image {
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6c757d;
            position: relative;
        }

        .property-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-available { background: var(--agent-success); color: white; }
        .status-pending { background: var(--agent-warning); color: white; }
        .status-sold { background: var(--agent-danger); color: white; }
        .status-rented { background: var(--agent-primary); color: white; }
        .status-withdrawn { background: #6c757d; color: white; }

        .property-price {
            color: var(--agent-primary);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .btn-agent-primary {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-agent-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
            color: white;
        }

        .form-control-custom {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--agent-primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
            background: white;
        }

        .property-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .property-stat {
            text-align: center;
        }

        .property-stat-value {
            font-weight: 700;
            color: var(--agent-primary);
        }

        .property-stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .breadcrumb-custom {
            background: white;
            padding: 1rem 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .breadcrumb-custom .breadcrumb {
            margin-bottom: 0;
            background: none;
        }
    </style>
</head>
<body data-user-role="agent">
    <!-- Include Navigation -->
    <?php include '../includes/agent_navigation.php'; ?>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-home me-3"></i>
                        <?php if ($action === 'add'): ?>
                            Add New Property
                        <?php elseif ($action === 'edit'): ?>
                            Edit Property
                        <?php else: ?>
                            Properties Management
                        <?php endif; ?>
                    </h1>
                    <p class="mb-0 opacity-90">
                        <?php if ($action === 'list'): ?>
                            Manage your property listings and track performance
                        <?php else: ?>
                            Complete the form below to <?= $action === 'add' ? 'add a new' : 'update this' ?> property
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="btn btn-warning">
                            <i class="fas fa-plus me-1"></i>Add Property
                        </a>
                    <?php else: ?>
                        <a href="manage_properties.php" class="btn btn-light">
                            <i class="fas fa-list me-2"></i>Back to List
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
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

        <?php if ($action === 'list'): ?>
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-value primary"><?= $stats['total_properties'] ?></div>
                        <div class="stats-label">Total Properties</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-value success"><?= $stats['available_properties'] ?></div>
                        <div class="stats-label">Available</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-value danger"><?= $stats['sold_properties'] ?></div>
                        <div class="stats-label">Sold</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-value warning"><?= $stats['pending_properties'] ?></div>
                        <div class="stats-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-value primary">€<?= number_format($stats['total_value'], 0, ',', ' ') ?></div>
                        <div class="stats-label">Total Value</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-value primary">€<?= number_format($stats['avg_price'], 0, ',', ' ') ?></div>
                        <div class="stats-label">Avg. Price</div>
                    </div>
                </div>
            </div>

            <!-- Properties List -->
            <?php if (!empty($properties)): ?>
                <div class="row">
                    <?php foreach ($properties as $prop): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="property-card">
                                <div class="property-image">
                                    <?= $prop['property_type'] === 'apartment' ? '🏠' : ($prop['property_type'] === 'commercial' ? '🏢' : '🏘️') ?>
                                    <div class="property-status status-<?= $prop['status'] ?>">
                                        <?= ucfirst($prop['status']) ?>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <div class="property-price mb-2">€<?= number_format($prop['price'], 0, ',', ' ') ?></div>
                                    <h5 class="mb-2"><?= htmlspecialchars($prop['title']) ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?= htmlspecialchars($prop['address_line1'] . ', ' . $prop['city']) ?>
                                    </p>
                                    <p class="text-muted small mb-3">
                                        <?php if ($prop['living_area']): ?>
                                            <?= $prop['living_area'] ?>m²
                                        <?php endif; ?>
                                        <?php if ($prop['bedrooms']): ?>
                                            • <?= $prop['bedrooms'] ?> bedrooms
                                        <?php endif; ?>
                                        <?php if ($prop['bathrooms']): ?>
                                            • <?= $prop['bathrooms'] ?> bathrooms
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="property-stats">
                                        <div class="property-stat">
                                            <div class="property-stat-value">0</div>
                                            <div class="property-stat-label">Views</div>
                                        </div>
                                        <div class="property-stat">
                                            <div class="property-stat-value"><?= $prop['appointment_count'] ?? 0 ?></div>
                                            <div class="property-stat-label">Appointments</div>
                                        </div>
                                        <div class="property-stat">
                                            <div class="property-stat-value"><?= date('M d', strtotime($prop['created_at'])) ?></div>
                                            <div class="property-stat-label">Listed</div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="?action=edit&id=<?= $prop['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php foreach (['available', 'pending', 'sold', 'rented', 'withdrawn'] as $status): ?>
                                                    <?php if ($status !== $prop['status']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="change_status">
                                                                <input type="hidden" name="property_id" value="<?= $prop['id'] ?>">
                                                                <input type="hidden" name="status" value="<?= $status ?>">
                                                                <button type="submit" class="dropdown-item">
                                                                    Mark as <?= ucfirst($status) ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this property?')">
                                                        <input type="hidden" name="action" value="delete_property">
                                                        <input type="hidden" name="property_id" value="<?= $prop['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash me-2"></i>Delete Property
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="content-card">
                    <div class="content-card-body text-center py-5">
                        <i class="fas fa-home fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Properties Listed</h4>
                        <p class="text-muted mb-4">Start building your portfolio by adding your first property listing.</p>
                        <a href="?action=add" class="btn-agent-primary">
                            <i class="fas fa-plus"></i>Add Your First Property
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Add/Edit Property Form -->
            <div class="content-card">
                <div class="content-card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="<?= $action === 'add' ? 'add_property' : 'edit_property' ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3">Basic Information</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Property Title *</label>
                                    <input type="text" class="form-control form-control-custom" name="title" 
                                           value="<?= htmlspecialchars($property['title'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description *</label>
                                    <textarea class="form-control form-control-custom" name="description" rows="4" required><?= htmlspecialchars($property['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Price (€) *</label>
                                            <input type="number" class="form-control form-control-custom" name="price" 
                                                   value="<?= $property['price'] ?? '' ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Property Type *</label>
                                            <select class="form-control form-control-custom" name="property_type" required>
                                                <option value="">Select Type</option>
                                                <?php 
                                                $types = ['house' => 'House', 'apartment' => 'Apartment', 'land' => 'Land', 'commercial' => 'Commercial', 'rental' => 'Rental'];
                                                foreach ($types as $value => $label): 
                                                ?>
                                                    <option value="<?= $value ?>" <?= ($property['property_type'] ?? '') === $value ? 'selected' : '' ?>>
                                                        <?= $label ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Listing Type</label>
                                            <select class="form-control form-control-custom" name="listing_type">
                                                <option value="sale" <?= ($property['listing_type'] ?? 'sale') === 'sale' ? 'selected' : '' ?>>For Sale</option>
                                                <option value="rent" <?= ($property['listing_type'] ?? '') === 'rent' ? 'selected' : '' ?>>For Rent</option>
                                                <option value="auction" <?= ($property['listing_type'] ?? '') === 'auction' ? 'selected' : '' ?>>Auction</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mb-3 mt-4">Address</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Address Line 1 *</label>
                                    <input type="text" class="form-control form-control-custom" name="address_line1" 
                                           value="<?= htmlspecialchars($property['address_line1'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Address Line 2</label>
                                    <input type="text" class="form-control form-control-custom" name="address_line2" 
                                           value="<?= htmlspecialchars($property['address_line2'] ?? '') ?>">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">City *</label>
                                            <input type="text" class="form-control form-control-custom" name="city" 
                                                   value="<?= htmlspecialchars($property['city'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">State/Region</label>
                                            <input type="text" class="form-control form-control-custom" name="state" 
                                                   value="<?= htmlspecialchars($property['state'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Postal Code</label>
                                            <input type="text" class="form-control form-control-custom" name="postal_code" 
                                                   value="<?= htmlspecialchars($property['postal_code'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mb-3 mt-4">Property Details</h5>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Bedrooms</label>
                                            <input type="number" class="form-control form-control-custom" name="bedrooms" 
                                                   value="<?= $property['bedrooms'] ?? '' ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Bathrooms</label>
                                            <input type="number" class="form-control form-control-custom" name="bathrooms" 
                                                   value="<?= $property['bathrooms'] ?? '' ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Total Rooms</label>
                                            <input type="number" class="form-control form-control-custom" name="total_rooms" 
                                                   value="<?= $property['total_rooms'] ?? '' ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Year Built</label>
                                            <input type="number" class="form-control form-control-custom" name="year_built" 
                                                   value="<?= $property['year_built'] ?? '' ?>" min="1800" max="<?= date('Y') + 5 ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Living Area (m²)</label>
                                            <input type="number" step="0.01" class="form-control form-control-custom" name="living_area" 
                                                   value="<?= $property['living_area'] ?? '' ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Lot Size (m²)</label>
                                            <input type="number" step="0.01" class="form-control form-control-custom" name="lot_size" 
                                                   value="<?= $property['lot_size'] ?? '' ?>" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h5 class="mb-3">Features</h5>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_parking" 
                                               <?= !empty($property['has_parking']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Has Parking</label>
                                    </div>
                                    <input type="number" class="form-control form-control-custom mt-2" name="parking_spaces" 
                                           placeholder="Number of parking spaces" value="<?= $property['parking_spaces'] ?? '' ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_balcony" 
                                               <?= !empty($property['has_balcony']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Has Balcony</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_terrace" 
                                               <?= !empty($property['has_terrace']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Has Terrace</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_garden" 
                                               <?= !empty($property['has_garden']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Has Garden</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Heating Type</label>
                                    <select class="form-control form-control-custom" name="heating_type">
                                        <option value="">Select Heating</option>
                                        <?php 
                                        $heating_types = ['gas' => 'Gas', 'electric' => 'Electric', 'oil' => 'Oil', 'solar' => 'Solar', 'geothermal' => 'Geothermal'];
                                        foreach ($heating_types as $value => $label): 
                                        ?>
                                            <option value="<?= $value ?>" <?= ($property['heating_type'] ?? '') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Energy Rating</label>
                                    <select class="form-control form-control-custom" name="energy_rating">
                                        <option value="">Select Rating</option>
                                        <?php foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $rating): ?>
                                            <option value="<?= $rating ?>" <?= ($property['energy_rating'] ?? '') === $rating ? 'selected' : '' ?>>
                                                <?= $rating ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn-agent-primary">
                                <i class="fas fa-save me-2"></i>
                                <?= $action === 'add' ? 'Add Property' : 'Update Property' ?>
                            </button>
                            <a href="manage_properties.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/agent_navigation.js"></script>
</body>
</html>