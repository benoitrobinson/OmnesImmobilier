<?php
session_start();

define('DEVELOPMENT_MODE', false);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

if (!isAgent()) {
    // Redirect non-agents to appropriate dashboard
    switch ($_SESSION['role']) {
        case 'client':
            redirect('../client/dashboard.php');
            break;
        case 'admin':
            redirect('../admin/dashboard.php'); // Temporary
            break;
        default:
            redirect('../auth/login.php');
    }
}

$error = '';
$agent_data = [];

// Get agent information (your existing code)
try {
    $query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name, a.agency_address, 
                     a.agency_phone, a.agency_email, a.license_number, a.specializations, 
                     a.years_experience, a.average_rating, a.total_sales, a.total_transactions
              FROM users u 
              INNER JOIN agents a ON u.id = a.user_id 
              WHERE u.id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $agent_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent_data) {
        session_destroy();
        redirect('../auth/login.php');
    }

} catch (Exception $e) {
    error_log("Agent Dashboard error: " . $e->getMessage());
    $error = "Error loading agent data.";
    $agent_data = [
        'first_name' => 'Agent',
        'last_name' => '',
        'role' => 'agent',
        'created_at' => date('Y-m-d'),
        'agency_name' => 'Omnes Immobilier',
        'average_rating' => 0,
        'total_sales' => 0,
        'total_transactions' => 0
    ];
}

// Get agent statistics (your existing code)
$stats = [
    'managed_properties' => 0,
    'available_properties' => 0,
    'scheduled_appointments' => 0,
    'total_appointments' => 0,
    'unread_messages' => 0,
    'monthly_revenue' => 0,
    'client_satisfaction' => 0,
    'properties_sold' => 0
];

try {
    // Your existing stats queries...
    $property_query = "SELECT 
                       COUNT(*) as managed_properties,
                       SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_properties,
                       SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as properties_sold
                       FROM properties WHERE agent_id = :agent_id";
    $property_stmt = $pdo->prepare($property_query);
    $property_stmt->bindParam(':agent_id', $_SESSION['user_id']);
    $property_stmt->execute();
    $property_stats = $property_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($property_stats) {
        $stats = array_merge($stats, $property_stats);
    }

    // More of your existing stats code...

} catch (Exception $e) {
    error_log("Agent Stats error: " . $e->getMessage());
}

// Set navigation stats for the top bar
$_SESSION['agent_properties_count'] = $stats['managed_properties'];
$_SESSION['agent_appointments_count'] = $stats['scheduled_appointments'];
$_SESSION['agent_unread_messages'] = $stats['unread_messages'];

// Configure breadcrumb (optional)
$show_breadcrumb = true;
$breadcrumb_items = [
    ['title' => 'Dashboard', 'url' => 'dashboard.php']
];

// Your existing data fetching code for appointments, properties, etc.
$upcoming_appointments = [];
$agent_properties = [];

// Get real upcoming appointments for agent
try {
    $appointment_query = "SELECT 
                            a.appointment_date,
                            p.title AS property_title,
                            p.address_line1,
                            p.city,
                            CONCAT(u.first_name, ' ', u.last_name) AS client_name,
                            a.location,
                            a.status
                            FROM appointments a 
                            JOIN properties p ON a.property_id = p.id 
                            JOIN users u ON a.client_id = u.id 
                            WHERE a.agent_id = :agent_id 
                              AND a.appointment_date >= NOW() 
                              AND a.status = 'scheduled'
                            ORDER BY a.appointment_date ASC
                            LIMIT 3";
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $upcoming_appointments = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Agent upcoming appointments error: " . $e->getMessage());
    $upcoming_appointments = [];
}

// Get real agent properties
try {
    $property_query = "SELECT 
                         id,
                         title,
                         price,
                         bedrooms,
                         living_area,
                         property_type,
                         status,
                         address_line1,
                         city,
                         created_at
                       FROM properties 
                       WHERE agent_id = :agent_id 
                       ORDER BY created_at DESC
                       LIMIT 5";
    $property_stmt = $pdo->prepare($property_query);
    $property_stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $agent_properties = $property_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Agent properties error: " . $e->getMessage());
    $agent_properties = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link href="../assets/css/agent_navigation.css" rel="stylesheet">
    
    <style>
        /* Your existing styles, but remove the old header styles */
        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Stats Cards */
        .stats-overview {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-item.primary {
            background: linear-gradient(135deg, #2c5aa0 0%, #4a90e2 100%);
            color: white;
        }

        .stat-item.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .stat-item.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .stat-item.info {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
            color: white;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
            opacity: 0.9;
        }

        /* Content Cards */
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

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #2c5aa0 0%, #4a90e2 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-section .container {
            position: relative;
            z-index: 1;
        }

        /* Property and Appointment Cards */
        .appointment-card-agent {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .appointment-card-agent:hover {
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .property-status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-available {
            background: #28a745;
            color: white;
        }

        .status-sold {
            background: #dc3545;
            color: white;
        }

        .status-pending {
            background: #ffc107;
            color: #212529;
        }

        .btn-agent-primary {
            background: linear-gradient(135deg, #2c5aa0 0%, #4a90e2 100%);
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

        .btn-agent-secondary {
            background: white;
            border: 2px solid #2c5aa0;
            color: #2c5aa0;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-agent-secondary:hover {
            background: #2c5aa0;
            color: white;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-value {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body class="has-agent-nav" data-user-role="agent" data-current-page="dashboard">
    <!-- Include New Agent Navigation -->
    <?php include '../includes/agent_navigation.php'; ?>

    <div class="container mt-4">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">
                            <i class="fas fa-chart-line me-3"></i>Welcome back, <?= htmlspecialchars($agent_data['first_name']) ?>!
                        </h1>
                        <p class="mb-0 opacity-90">Manage your properties and grow your business with Omnes Real Estate.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="manage_properties.php?action=add" class="btn btn-light">
                            <i class="fas fa-plus me-2"></i>Add Property
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stats-grid">
                <div class="stat-item primary">
                    <div class="stat-value"><?= $stats['managed_properties'] ?></div>
                    <div class="stat-label">Managed Properties</div>
                </div>
                <div class="stat-item success">
                    <div class="stat-value"><?= $stats['scheduled_appointments'] ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-item warning">
                    <div class="stat-value"><?= $stats['unread_messages'] ?></div>
                    <div class="stat-label">Unread Messages</div>
                </div>
                <div class="stat-item info">
                    <div class="stat-value"><?= round($agent_data['average_rating'] * 20) ?>%</div>
                    <div class="stat-label">Client Satisfaction</div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <div class="content-card-header">
                        <i class="fas fa-calendar-check me-2"></i>Upcoming Appointments
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($upcoming_appointments)): ?>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                    <div class="me-3">
                                        <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= htmlspecialchars($appointment['property_title']) ?></div>
                                        <small class="text-muted">with <?= htmlspecialchars($appointment['client_name']) ?></small>
                                        <div class="text-primary mt-1">
                                            <small><?= date('M d, Y - H:i', strtotime($appointment['appointment_date'])) ?></small>
                                        </div>
                                        <?php if (!empty($appointment['location'])): ?>
                                            <div class="text-muted mt-1">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($appointment['location']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="manage_appointments.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-alt me-1"></i>View All Appointments
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No upcoming appointments</p>
                                <a href="manage_properties.php" class="btn-luxury-primary">
                                    <i class="fas fa-plus"></i>
                                    Add Properties
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <div class="content-card-header">
                        <i class="fas fa-home me-2"></i>My Properties
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($agent_properties)): ?>
                            <?php foreach (array_slice($agent_properties, 0, 3) as $property): ?>
                                <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                    <div class="me-3 fs-1">
                                        <?= $property['property_type'] === 'apartment' ? '🏠' : ($property['property_type'] === 'commercial' ? '🏢' : '🏘️') ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= htmlspecialchars($property['title']) ?></div>
                                        <small class="text-muted">
                                            <?php if ($property['living_area']): ?>
                                                <?= $property['living_area'] ?>m²
                                            <?php endif; ?>
                                            <?php if ($property['bedrooms']): ?>
                                                • <?= $property['bedrooms'] ?> rooms
                                            <?php endif; ?>
                                        </small>
                                        <div style="color: #2c5aa0; font-weight: 600;">
                                            €<?= number_format($property['price'], 0, ',', ' ') ?>
                                        </div>
                                    </div>
                                    <div class="property-status status-<?= $property['status'] ?>">
                                        <?= ucfirst($property['status']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="manage_properties.php" class="btn-agent-secondary">
                                    <i class="fas fa-home"></i>
                                    Manage All Properties
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-home fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No properties listed yet</p>
                                <a href="manage_properties.php?action=add" class="btn-agent-primary">
                                    <i class="fas fa-plus"></i>
                                    Add First Property
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Overview -->
        <div class="content-card">
            <div class="content-card-header">
                <i class="fas fa-chart-bar me-2"></i>Performance Overview
            </div>
            <div class="content-card-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <div class="h3 text-success"><?= $stats['properties_sold'] ?></div>
                        <small class="text-muted">Properties Sold</small>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="h3" style="color: #2c5aa0;"><?= $stats['total_appointments'] ?></div>
                        <small class="text-muted">Total Appointments</small>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="h3 text-warning"><?= number_format($agent_data['average_rating'], 1) ?>/5</div>
                        <small class="text-muted">Average Rating</small>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="h3 text-info"><?= $agent_data['years_experience'] ?? 0 ?>Y</div>
                        <small class="text-muted">Experience</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/agent_navigation.js"></script>
</body>
</html>