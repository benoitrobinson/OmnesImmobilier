<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$pdo = $database->getConnection();

$property_id = $_GET['id'] ?? 0;
$property = null;
$error = '';

// Get property details
try {
    $query = "
        SELECT 
            p.*,
            CONCAT(a.first_name, ' ', a.last_name) as agent_name,
            u.email as agent_email,
            u.phone as agent_phone,
            a.agency_name,
            COUNT(DISTINCT ap.id) as total_appointments,
            COUNT(DISTINCT CASE WHEN ap.status = 'scheduled' AND ap.appointment_date >= NOW() THEN ap.id END) as upcoming_appointments,
            COUNT(DISTINCT CASE WHEN ap.status = 'completed' THEN ap.id END) as completed_appointments,
            COUNT(DISTINCT f.id) as favorite_count
        FROM properties p
        INNER JOIN agents a ON p.agent_id = a.user_id
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN appointments ap ON p.id = ap.property_id
        LEFT JOIN user_favorites f ON p.id = f.property_id
        WHERE p.id = :property_id
        GROUP BY p.id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['property_id' => $property_id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$property) {
        $error = 'Property not found.';
    }
} catch (Exception $e) {
    error_log("View property error: " . $e->getMessage());
    $error = 'Error loading property details.';
}

// Get recent appointments
$recent_appointments = [];
if ($property) {
    try {
        $appt_query = "
            SELECT 
                ap.id, ap.appointment_date, ap.status, ap.location,
                CONCAT(u.first_name, ' ', u.last_name) as client_name,
                u.email as client_email
            FROM appointments ap
            LEFT JOIN users u ON ap.client_id = u.id
            WHERE ap.property_id = :property_id 
            ORDER BY ap.appointment_date DESC 
            LIMIT 10
        ";
        $appt_stmt = $pdo->prepare($appt_query);
        $appt_stmt->execute(['property_id' => $property_id]);
        $recent_appointments = $appt_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Appointments fetch error: " . $e->getMessage());
    }
}

// Parse images JSON
$property_images = [];
if ($property && !empty($property['images'])) {
    $property_images = json_decode($property['images'], true) ?: [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Property - <?= htmlspecialchars($property['title'] ?? 'Property') ?></title>
    
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

        .admin-header {
            background: #000;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            text-decoration: none !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            transform: translateY(-1px);
        }

        .user-profile {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            transition: all 0.3s ease;
            text-decoration: none !important;
            color: white !important;
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

        .property-hero {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 2rem;
            position: relative;
        }

        .property-image-gallery {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            grid-template-rows: 200px 200px;
            gap: 0.5rem;
            border-radius: 0.75rem;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .property-image {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6c757d;
            position: relative;
            overflow: hidden;
        }

        .property-image:first-child {
            grid-row: span 2;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-image.placeholder {
            background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%), 
                        linear-gradient(-45deg, #f8f9fa 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #f8f9fa 75%), 
                        linear-gradient(-45deg, transparent 75%, #f8f9fa 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-primary);
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--admin-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .appointment-item {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .appointment-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-available { background: #d4edda; color: #155724; }
        .status-sold { background: #f8d7da; color: #721c24; }
        .status-rented { background: #cce5ff; color: #004085; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-scheduled { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .feature-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }

        .feature-icon {
            font-size: 2rem;
            color: var(--admin-primary);
            margin-bottom: 0.5rem;
        }

        .description-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #495057;
            padding: 1rem 0;
        }

        .description-content p {
            margin-bottom: 1.5rem;
        }

        .description-highlight {
            background: linear-gradient(120deg, transparent 0%, rgba(220, 53, 69, 0.1) 50%, transparent 100%);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid var(--admin-primary);
            margin: 1rem 0;
        }

        .description-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .description-feature {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .description-feature:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .description-feature i {
            color: var(--admin-primary);
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg admin-header">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center ms-3" href="../pages/home.php">
                <img src="../assets/images/logo1.png" alt="Logo" width="120" height="50" class="me-3">
            </a>
            
            <div class="d-flex align-items-center me-3">
                <a href="manage_properties.php" class="btn btn-light me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Properties
                </a>
                <?php if ($property): ?>
                    <a href="edit_property.php?id=<?= $property['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>Edit Property
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($property): ?>
            <!-- Property Hero -->
            <div class="content-card">
                <div class="property-hero">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-2"><?= htmlspecialchars($property['title']) ?></h1>
                            <p class="h4 mb-3">€<?= number_format($property['price'], 0, ',', ' ') ?></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($property['address_line1']) ?></p>
                                    <p class="mb-1"><i class="fas fa-city me-2"></i><?= htmlspecialchars($property['city']) ?>, <?= htmlspecialchars($property['postal_code']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-home me-2"></i><?= ucfirst($property['property_type']) ?></p>
                                    <p class="mb-1">
                                        <span class="status-badge status-<?= $property['status'] ?>">
                                            <?= ucfirst($property['status']) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex align-items-center justify-content-end">
                                <i class="fas fa-heart me-2"></i>
                                <span><?= $property['favorite_count'] ?> favorites</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image Gallery -->
            <div class="content-card">
                <div class="content-card-header">
                    <h5 class="mb-0"><i class="fas fa-images me-2"></i>Property Images</h5>
                </div>
                <div class="card-body">
                    <div class="property-image-gallery">
                        <?php if (!empty($property_images)): ?>
                            <?php foreach (array_slice($property_images, 0, 5) as $index => $image): ?>
                                <div class="property-image">
                                    <img src="<?= htmlspecialchars($image) ?>" alt="Property Image <?= $index + 1 ?>" loading="lazy">
                                </div>
                            <?php endforeach; ?>
                            <?php for ($i = count($property_images); $i < 5; $i++): ?>
                                <div class="property-image placeholder">
                                    <i class="fas fa-camera"></i>
                                </div>
                            <?php endfor; ?>
                        <?php else: ?>
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <div class="property-image placeholder">
                                    <i class="fas fa-camera"></i>
                                </div>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $property['total_appointments'] ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $property['upcoming_appointments'] ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $property['completed_appointments'] ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $property['living_area'] ?>m²</div>
                        <div class="stat-label">Living Area</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $property['bedrooms'] ?></div>
                        <div class="stat-label">Bedrooms</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $property['bathrooms'] ?></div>
                        <div class="stat-label">Bathrooms</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Property Details -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Property Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-calendar"></i></div>
                                <div>
                                    <strong>Listed Date</strong><br>
                                    <?= date('F d, Y', strtotime($property['created_at'])) ?>
                                </div>
                            </div>

                            <?php if ($property['year_built'] > 0): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-hammer"></i></div>
                                    <div>
                                        <strong>Year Built</strong><br>
                                        <?= $property['year_built'] ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($property['has_parking']): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-car"></i></div>
                                    <div>
                                        <strong>Parking</strong><br>
                                        <?= $property['parking_spaces'] ?> space(s)
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($property['has_garden']): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-seedling"></i></div>
                                    <div>
                                        <strong>Garden</strong><br>
                                        Available
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-tag"></i></div>
                                <div>
                                    <strong>Property ID</strong><br>
                                    #<?= str_pad($property['id'], 6, '0', STR_PAD_LEFT) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agent Information -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Agent Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <strong><?= htmlspecialchars($property['agent_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($property['agency_name']) ?></small>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <strong>Email</strong><br>
                                    <a href="mailto:<?= htmlspecialchars($property['agent_email']) ?>">
                                        <?= htmlspecialchars($property['agent_email']) ?>
                                    </a>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-phone"></i></div>
                                <div>
                                    <strong>Phone</strong><br>
                                    <a href="tel:<?= htmlspecialchars($property['agent_phone']) ?>">
                                        <?= htmlspecialchars($property['agent_phone']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recent Appointments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_appointments)): ?>
                                <p class="text-muted text-center py-3">No appointments found</p>
                            <?php else: ?>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($appointment['client_name'] ?? 'N/A') ?></h6>
                                                <p class="mb-1 text-muted small">
                                                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($appointment['client_email'] ?? 'N/A') ?>
                                                </p>
                                                <p class="mb-0 small">
                                                    <i class="fas fa-clock me-1"></i><?= date('M j, Y H:i', strtotime($appointment['appointment_date'])) ?>
                                                </p>
                                            </div>
                                            <span class="status-badge status-<?= $appointment['status'] ?>"><?= ucfirst($appointment['status']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="content-card">
                <div class="content-card-header">
                    <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>Property Description</h5>
                </div>
                <div class="card-body">
                    <div class="description-content">
                        <?php 
                        $description = $property['description'];
                        // Split description into paragraphs for better formatting
                        $paragraphs = explode("\n", $description);
                        foreach ($paragraphs as $paragraph) {
                            if (trim($paragraph)) {
                                echo '<p>' . htmlspecialchars(trim($paragraph)) . '</p>';
                            }
                        }
                        ?>
                    </div>
                    
                    <!-- Property Highlights -->
                    <div class="description-highlight">
                        <h6 class="mb-3"><i class="fas fa-star me-2"></i>Property Highlights</h6>
                        <div class="description-features">
                            <div class="description-feature">
                                <i class="fas fa-expand-arrows-alt"></i>
                                <span><?= $property['living_area'] ?>m² living space</span>
                            </div>
                            
                            <?php if ($property['bedrooms'] > 0): ?>
                                <div class="description-feature">
                                    <i class="fas fa-bed"></i>
                                    <span><?= $property['bedrooms'] ?> bedroom<?= $property['bedrooms'] > 1 ? 's' : '' ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($property['bathrooms'] > 0): ?>
                                <div class="description-feature">
                                    <i class="fas fa-bath"></i>
                                    <span><?= $property['bathrooms'] ?> bathroom<?= $property['bathrooms'] > 1 ? 's' : '' ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($property['has_parking']): ?>
                                <div class="description-feature">
                                    <i class="fas fa-car"></i>
                                    <span><?= $property['parking_spaces'] ?> parking space<?= $property['parking_spaces'] > 1 ? 's' : '' ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($property['has_garden']): ?>
                                <div class="description-feature">
                                    <i class="fas fa-seedling"></i>
                                    <span>Private garden available</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($property['year_built'] > 0): ?>
                                <div class="description-feature">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Built in <?= $property['year_built'] ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-home fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">Property Not Found</h3>
                <p class="text-muted">The requested property could not be found.</p>
                <a href="manage_properties.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Properties
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
