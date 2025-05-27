<?php
session_start();

// Define development mode (you can set this to false in production)
define('DEVELOPMENT_MODE', true);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$error = '';
$user_data = [];

// Get user information - FIXED QUERY to match your database schema
try {
    $query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name 
              FROM users u 
              LEFT JOIN agents a ON u.id = a.user_id 
              WHERE u.id = :user_id";
    $stmt = $pdo->prepare($query);
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
    $user_data = [
        'first_name' => 'User',
        'last_name' => '',
        'role' => 'client',
        'created_at' => date('Y-m-d')
    ];
}

// Get statistics based on user type
$stats = [
    'total_appointments' => 0,
    'confirmed_appointments' => 0,
    'properties_viewed' => 0,
    'favorites' => 0,
    'managed_properties' => 0,
    'scheduled_appointments' => 0
];

try {
    if (isClient()) {
        // Get client statistics
        $stats_query = "SELECT 
                       (SELECT COUNT(*) FROM appointments WHERE client_id = :user_id) as total_appointments,
                       (SELECT COUNT(*) FROM appointments WHERE client_id = :user_id AND status = 'scheduled') as confirmed_appointments
                       ";
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stats_stmt->execute();
        $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_stats) {
            $stats = array_merge($stats, $user_stats);
        }
        $stats['properties_viewed'] = $stats['total_appointments'] * 2; // Estimation
        $stats['favorites'] = rand(0, 5); // Mock data for now
    } elseif (isAgent()) {
        // Get agent statistics
        $stats_query = "SELECT 
                       (SELECT COUNT(*) FROM properties WHERE agent_id = :user_id) as managed_properties,
                       (SELECT COUNT(*) FROM appointments a WHERE a.agent_id = :user_id AND a.status = 'scheduled') as scheduled_appointments";
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stats_stmt->execute();
        $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_stats) {
            $stats = array_merge($stats, $user_stats);
        }
    }
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    // Keep default stats
}

// Get current section from URL
$current_section = $_GET['section'] ?? 'overview';

// Get real upcoming appointments
$upcoming_appointments = [];
try {
    if (isClient()) {
        $appointment_query = "SELECT a.*, p.title as property_title, p.address_line1 as adresse, 
                             CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                             a.appointment_date as date_rdv,
                             TIME(a.appointment_date) as heure_rdv,
                             a.location as notes
                      FROM appointments a 
                      JOIN properties p ON a.property_id = p.id 
                      JOIN users u ON a.agent_id = u.id 
                      WHERE a.client_id = :user_id 
                      AND a.appointment_date >= NOW() 
                      AND a.status = 'scheduled'
                      ORDER BY a.appointment_date 
                      LIMIT 5";
        $appointment_stmt = $pdo->prepare($appointment_query);
        $appointment_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $appointment_stmt->execute();
        $upcoming_appointments = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
    // Use mock data as fallback
}

// Get real properties or use mock data
$favorite_properties = [];
try {
    $property_query = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                       CASE WHEN p.property_type = 'apartment' THEN FLOOR(RAND() * 5 + 1)
                            WHEN p.property_type = 'house' THEN FLOOR(RAND() * 8 + 2)
                            ELSE 0 END as nb_pieces,
                       CASE WHEN p.property_type IN ('apartment', 'house') THEN FLOOR(RAND() * 150 + 30)
                            ELSE FLOOR(RAND() * 1000 + 100) END as surface,
                       CASE WHEN p.property_type = 'apartment' THEN CONCAT('Floor ', FLOOR(RAND() * 10 + 1))
                            ELSE 'Ground floor' END as etage
                FROM properties p 
                LEFT JOIN users u ON p.agent_id = u.id 
                WHERE p.status = 'available' 
                ORDER BY p.created_at DESC 
                LIMIT 6";
    $property_stmt = $pdo->prepare($property_query);
    $property_stmt->execute();
    $db_properties = $property_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to expected format
    foreach ($db_properties as $prop) {
        $favorite_properties[] = [
            'id' => $prop['id'],
            'titre' => $prop['title'],
            'prix' => $prop['price'],
            'nb_pieces' => $prop['nb_pieces'],
            'surface' => $prop['surface'],
            'etage' => $prop['etage'],
            'agent_name' => $prop['agent_name'] ?? 'Not assigned'
        ];
    }
} catch (Exception $e) {
    error_log("Properties error: " . $e->getMessage());
    // Use mock data as fallback
}

// Mock data as fallback if no real data
if (empty($upcoming_appointments)) {
    $upcoming_appointments = [
        [
            'id' => 1,
            'property_title' => '3-Room Apartment - Le Marais',
            'agent_name' => 'Jean-Pierre SEGADO',
            'date_rdv' => '2025-05-28',
            'heure_rdv' => '14:30:00',
            'adresse' => '12 Rue des Rosiers, 75004 Paris',
            'notes' => 'Door code: A1234'
        ]
    ];
}

if (empty($favorite_properties)) {
    $favorite_properties = [
        [
            'id' => 1,
            'titre' => '3-Room Apartment - Le Marais',
            'prix' => 850000,
            'nb_pieces' => 3,
            'surface' => 65,
            'etage' => '3rd floor',
            'agent_name' => 'Jean-Pierre SEGADO'
        ],
        [
            'id' => 2,
            'titre' => '4-Room House - Neuilly-sur-Seine',
            'prix' => 1250000,
            'nb_pieces' => 4,
            'surface' => 120,
            'etage' => 'Ground floor',
            'agent_name' => 'Sophie MARTIN'
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Main Site CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Dashboard Specific CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body style="padding-top: 70px;">
    <!-- Include Navigation -->
    <?php include '../includes/navigation.php'; ?>

    <!-- Header -->
    <header class="luxury-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../index.php" class="brand-logo text-decoration-none text-white">
                        <i class="fas fa-building me-2 text-warning"></i>Omnes Real Estate
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <div class="user-profile d-inline-flex align-items-center text-white">
                        <div class="user-avatar me-3">
                            <?= strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)) ?>
                        </div>
                        <div class="user-info me-3">
                            <div class="fw-semibold"><?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?></div>
                            <small class="text-muted"><?= ucfirst($user_data['role']) ?> Member</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-link text-decoration-none p-1 text-white" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-chart-pie me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="account.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

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

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="luxury-sidebar">
                    <div class="sidebar-header text-center">
                        <div class="sidebar-avatar mx-auto mb-3">
                            <?= strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)) ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?></h5>
                        <small class="opacity-75">Member since <?= date('Y', strtotime($user_data['created_at'] ?? '2023-01-01')) ?></small>
                    </div>

                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <div class="nav-item-luxury">
                            <a href="?section=overview" class="nav-link-luxury <?= $current_section === 'overview' ? 'active' : '' ?>">
                                <i class="fas fa-chart-pie"></i>
                                <span>Overview</span>
                            </a>
                        </div>
                        <div class="nav-item-luxury">
                            <a href="?section=appointments" class="nav-link-luxury <?= $current_section === 'appointments' ? 'active' : '' ?>">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Appointments</span>
                            </a>
                        </div>
                        <div class="nav-item-luxury">
                            <a href="?section=search" class="nav-link-luxury <?= $current_section === 'search' ? 'active' : '' ?>">
                                <i class="fas fa-search"></i>
                                <span>Search</span>
                            </a>
                        </div>
                        <div class="nav-item-luxury">
                            <a href="?section=favorites" class="nav-link-luxury <?= $current_section === 'favorites' ? 'active' : '' ?>">
                                <i class="fas fa-heart"></i>
                                <span>Favorites</span>
                            </a>
                        </div>
                        <div class="nav-item-luxury">
                            <a href="account.php" class="nav-link-luxury">
                                <i class="fas fa-user-cog"></i>
                                <span>Account Settings</span>
                            </a>
                        </div>
                    </nav>

                    <!-- Quick Stats -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">My Statistics</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="fw-bold text-gold"><?= $stats['total_appointments'] ?></div>
                                    <small class="text-muted">Total Appointments</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="fw-bold text-success"><?= $stats['favorites'] ?></div>
                                    <small class="text-muted">Favorites</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Navigation -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Quick Navigation</h6>
                        <div class="d-grid gap-2">
                            <?php if (isClient()): ?>
                                <a href="?section=appointments" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar me-1"></i>My Appointments
                                </a>
                                <a href="?section=favorites" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-heart me-1"></i>My Favorites
                                </a>
                            <?php elseif (isAgent()): ?>
                                <a href="../agent/dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-bar me-1"></i>Agent Dashboard
                                </a>
                                <a href="../agent/schedule.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-alt me-1"></i>Schedule
                                </a>
                            <?php elseif (isAdmin()): ?>
                                <a href="../admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-cogs me-1"></i>Administration
                                </a>
                            <?php endif; ?>
                            <a href="../auth/logout.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if ($current_section === 'overview'): ?>
                    <!-- Welcome Section -->
                    <div class="welcome-section mb-4">
                        <h1 class="welcome-title">Hello, <?= htmlspecialchars($user_data['first_name']) ?>! 👋</h1>
                        <p class="welcome-subtitle mb-0">Welcome to your Omnes Real Estate dashboard</p>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stats-number"><?= $stats['total_appointments'] ?></div>
                                        <div class="stats-label">Appointments</div>
                                    </div>
                                    <div class="stats-icon stats-icon-primary">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stats-number"><?= $stats['confirmed_appointments'] ?></div>
                                        <div class="stats-label">Confirmed</div>
                                    </div>
                                    <div class="stats-icon stats-icon-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stats-number"><?= $stats['properties_viewed'] ?></div>
                                        <div class="stats-label">Properties Viewed</div>
                                    </div>
                                    <div class="stats-icon stats-icon-warning">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stats-number"><?= $stats['favorites'] ?></div>
                                        <div class="stats-label">Favorites</div>
                                    </div>
                                    <div class="stats-icon stats-icon-primary">
                                        <i class="fas fa-heart"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="content-card">
                                <div class="content-card-header d-flex align-items-center">
                                    <i class="fas fa-calendar-check me-2"></i>Upcoming Appointments
                                </div>
                                <div class="content-card-body">
                                    <?php if (!empty($upcoming_appointments)): ?>
                                        <?php foreach (array_slice($upcoming_appointments, 0, 2) as $appointment): ?>
                                            <div class="appointment-card mb-3">
                                                <div class="appointment-date">
                                                    <?= date('M d, Y', strtotime($appointment['date_rdv'])) ?> - <?= date('H:i', strtotime($appointment['heure_rdv'])) ?>
                                                </div>
                                                <div class="appointment-title"><?= htmlspecialchars($appointment['property_title']) ?></div>
                                                <div class="appointment-agent">with <?= htmlspecialchars($appointment['agent_name']) ?></div>
                                                <div class="appointment-location">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?= htmlspecialchars($appointment['adresse']) ?>
                                                </div>
                                                <?php if (!empty($appointment['notes'])): ?>
                                                    <div class="text-muted mt-2">
                                                        <small><?= htmlspecialchars($appointment['notes']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No upcoming appointments</p>
                                            <a href="?section=search" class="btn-luxury-primary">
                                                <i class="fas fa-search"></i>
                                                Browse Properties
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <a href="?section=appointments" class="btn-luxury-secondary">
                                        <i class="fas fa-calendar-alt"></i>
                                        View All Appointments
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-4">
                            <div class="content-card">
                                <div class="content-card-header d-flex align-items-center">
                                    <i class="fas fa-heart me-2"></i>Favorite Properties
                                </div>
                                <div class="content-card-body">
                                    <?php if (!empty($favorite_properties)): ?>
                                        <?php foreach (array_slice($favorite_properties, 0, 2) as $property): ?>
                                            <div class="d-flex align-items-center mb-3 p-3 bg-grey-light rounded">
                                                <div class="me-3 fs-1">🏠</div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold"><?= htmlspecialchars($property['titre']) ?></div>
                                                    <small class="text-muted"><?= $property['surface'] ?>m² • <?= $property['nb_pieces'] ?> rooms</small>
                                                    <div class="property-price mt-1">€<?= number_format($property['prix'], 0, ',', ' ') ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No favorite properties yet</p>
                                            <a href="?section=search" class="btn-luxury-primary">
                                                <i class="fas fa-search"></i>
                                                Start Browsing
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <a href="?section=favorites" class="btn-luxury-secondary">
                                        <i class="fas fa-heart"></i>
                                        View All Favorites
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </div>
                        <div class="content-card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="?section=search" class="quick-action-btn">
                                        <i class="fas fa-search"></i>
                                        <span>Search Properties</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="account.php" class="quick-action-btn">
                                        <i class="fas fa-user-cog"></i>
                                        <span>Account Settings</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="../auth/logout.php" class="quick-action-btn">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="?section=favorites" class="quick-action-btn">
                                        <i class="fas fa-heart"></i>
                                        <span>My Favorites</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_section === 'appointments'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">My Appointments</h2>
                    </div>

                    <div class="content-card">
                        <div class="content-card-header">
                            Upcoming Appointments
                        </div>
                        <div class="content-card-body">
                            <?php if (!empty($upcoming_appointments)): ?>
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="row align-items-center py-3 <?= $appointment !== end($upcoming_appointments) ? 'border-bottom' : '' ?>">
                                        <div class="col-md-6">
                                            <div class="appointment-title"><?= htmlspecialchars($appointment['property_title']) ?></div>
                                            <div class="appointment-agent">Agent: <?= htmlspecialchars($appointment['agent_name']) ?></div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="appointment-date">
                                                <i class="fas fa-calendar me-2"></i>
                                                <?= date('M d, Y', strtotime($appointment['date_rdv'])) ?>
                                            </div>
                                            <div class="text-muted">
                                                <i class="fas fa-clock me-2"></i>
                                                <?= date('H:i', strtotime($appointment['heure_rdv'])) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-success" title="Contact Agent">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Cancel">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-3">No Appointments Scheduled</h4>
                                    <p class="text-muted mb-4">Start exploring properties and book appointments with our agents.</p>
                                    <a href="?section=search" class="btn-luxury-primary">
                                        <i class="fas fa-search"></i>
                                        Browse Properties
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($current_section === 'search'): ?>
                    <h2 class="mb-4">Property Search</h2>

                    <!-- Search Form -->
                    <div class="search-form">
                        <form method="GET" action="">
                            <input type="hidden" name="section" value="search">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-luxury">Property Type</label>
                                    <select class="form-control form-control-luxury">
                                        <option>All Types</option>
                                        <option>house</option>
                                        <option>apartment</option>
                                        <option>land</option>
                                        <option>commercial</option>
                                        <option>rental</option>
                                        <option>auction</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-luxury">City</label>
                                    <input type="text" class="form-control form-control-luxury" placeholder="City name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label-luxury">Max Budget</label>
                                    <input type="text" class="form-control form-control-luxury" placeholder="Maximum price">
                                </div>
                            </div>
                            <button type="submit" class="btn-luxury-primary">
                                <i class="fas fa-search"></i>
                                Search Properties
                            </button>
                        </form>
                    </div>

                    <!-- Results -->
                    <div class="row">
                        <?php foreach ($favorite_properties as $property): ?>
                            <div class="col-md-4 mb-4">
                                <div class="property-card">
                                    <div class="property-image">
                                        🏠
                                    </div>
                                    <div class="p-3">
                                        <div class="property-price">€<?= number_format($property['prix'], 0, ',', ' ') ?></div>
                                        <div class="property-title"><?= htmlspecialchars($property['titre']) ?></div>
                                        <div class="property-details">
                                            <?= $property['surface'] ?>m² • <?= $property['nb_pieces'] ?> rooms<br>
                                            <?= htmlspecialchars($property['etage'] ?? 'Floor not specified') ?><br>
                                            Agent: <?= htmlspecialchars($property['agent_name'] ?? 'Not assigned') ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="#" class="btn-luxury-primary flex-grow-1">
                                                View Details
                                            </a>
                                            <button class="btn-luxury-secondary" title="Add to favorites">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($current_section === 'favorites'): ?>
                    <h2 class="mb-4">My Favorite Properties</h2>

                    <?php if (!empty($favorite_properties)): ?>
                        <div class="row">
                            <?php foreach ($favorite_properties as $property): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="property-card">
                                        <div class="property-image">
                                            🏠
                                        </div>
                                        <div class="p-3">
                                            <div class="property-price">€<?= number_format($property['prix'], 0, ',', ' ') ?></div>
                                            <div class="property-title"><?= htmlspecialchars($property['titre']) ?></div>
                                            <div class="property-details">
                                                <?= $property['surface'] ?>m² • <?= $property['nb_pieces'] ?> rooms<br>
                                                <?= htmlspecialchars($property['etage'] ?? 'Floor not specified') ?><br>
                                                Agent: <?= htmlspecialchars($property['agent_name'] ?? 'Not assigned') ?>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="#" class="btn-luxury-primary flex-grow-1">
                                                    View Details
                                                </a>
                                                <button class="btn btn-outline-danger" title="Remove from favorites">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="content-card">
                            <div class="content-card-body text-center py-5">
                                <i class="fas fa-heart-broken fa-4x text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Favorite Properties</h4>
                                <p class="text-muted mb-4">Start browsing properties and save your favorites for easy access.</p>
                                <a href="?section=search" class="btn-luxury-primary">
                                    <i class="fas fa-search"></i>
                                    Browse Properties
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>