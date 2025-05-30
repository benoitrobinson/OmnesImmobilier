<?php
session_start();

// Define development mode (you can set this to false in production)
define('DEVELOPMENT_MODE', false); // Set to false to hide database messages

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
    'favorites' => 5,
    'managed_properties' => 0,
    'scheduled_appointments' => 0
];

try {
    if (isClient()) {
        // Get client statistics
        $stats_query = "SELECT 
                       (SELECT COUNT(*) FROM appointments WHERE client_id = :user_id) as total_appointments,
                       (SELECT COUNT(*) FROM appointments WHERE client_id = :user_id AND status = 'scheduled') as confirmed_appointments,
                       (SELECT COUNT(*) FROM user_favorites WHERE user_id = :user_id) as favorites
                       ";
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stats_stmt->execute();
        $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_stats) {
            $stats = array_merge($stats, $user_stats);
        }
        $stats['properties_viewed'] = $stats['total_appointments'] * 2; // Estimation
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
        $appointment_query = "SELECT 
                                a.appointment_date,
                                p.title AS property_title,
                                p.address_line1 AS adresse,
                                CONCAT(u.first_name, ' ', u.last_name) AS agent_name,
                                a.location AS notes
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

// Get real favorite properties from user_favorites table
$favorite_properties = [];
try {
    if (isClient()) {
        $property_query = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                           uf.created_at as favorited_at
                    FROM user_favorites uf
                    JOIN properties p ON uf.property_id = p.id 
                    LEFT JOIN users u ON p.agent_id = u.id 
                    WHERE uf.user_id = :user_id
                    AND p.status = 'available'
                    ORDER BY uf.created_at DESC";
        $property_stmt = $pdo->prepare($property_query);
        $property_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $property_stmt->execute();
        $db_properties = $property_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to expected format
        foreach ($db_properties as $prop) {
            // Generate realistic property details based on type
            $nb_pieces = 0;
            $surface = 0;
            $etage = '';
            
            switch ($prop['property_type']) {
                case 'apartment':
                    $nb_pieces = rand(1, 5);
                    $surface = rand(30, 150);
                    $etage = 'Floor ' . rand(1, 10);
                    break;
                case 'house':
                    $nb_pieces = rand(3, 8);
                    $surface = rand(80, 300);
                    $etage = 'Ground floor';
                    break;
                case 'commercial':
                    $nb_pieces = 0;
                    $surface = rand(50, 500);
                    $etage = 'Ground floor';
                    break;
                default:
                    $nb_pieces = 0;
                    $surface = rand(100, 1000);
                    $etage = 'N/A';
                    break;
            }
            
            $favorite_properties[] = [
                'id' => $prop['id'],
                'titre' => $prop['title'],
                'prix' => $prop['price'],
                'nb_pieces' => $nb_pieces,
                'surface' => $surface,
                'etage' => $etage,
                'agent_name' => $prop['agent_name'] ?? 'Not assigned',
                'property_type' => $prop['property_type'],
                'city' => $prop['city'],
                'address_line1' => $prop['address_line1'],
                'description' => $prop['description'],
                'favorited_at' => $prop['favorited_at']
            ];
        }
    }
} catch (Exception $e) {
    error_log("Favorite Properties error: " . $e->getMessage());
    // Keep empty array if error occurs
}

// No mock data - only show real appointments from database
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
    
    <style>
        /* Dashboard specific styling - NO navigation bar */
        body[data-page="dashboard"] {
            padding-top: 0 !important; /* Remove top padding since no navbar */
            background: #f8f9fa;
        }
        
        /* Hide any potential navigation */
        body[data-page="dashboard"] .navbar,
        body[data-page="dashboard"] nav {
            display: none !important;
        }

        /* Enhanced header styling to match account page */
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

        .brand-logo:hover {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .user-profile {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            margin-right: 2rem; /* Move away from edge */
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-info .fw-semibold {
            font-size: 0.95rem;
            margin-bottom: 2px;
        }

        .user-info small {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        /* Enhanced dropdown in header - matching account page style */
        .luxury-header .dropdown-menu,
        .dashboard-dropdown-menu {
            background: white !important;
            backdrop-filter: blur(20px);
            border: none !important;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25) !important;
            border-radius: 16px !important;
            margin-top: 20px !important;
            z-index: 10050 !important;
            min-width: 320px;
            max-width: 380px;
            padding: 1rem 0;
            /* Better positioning to prevent cutoff */
            right: 2rem !important;
            left: auto !important;
            transform: none !important;
            border: 2px solid rgba(212, 175, 55, 0.2) !important;
            position: absolute !important;
            top: 100% !important;
        }

        /* Dashboard dropdown hover functionality */
        .account-dropdown-dashboard:hover .dashboard-dropdown-menu {
            display: block !important;
        }

        /* Dashboard dropdown animations */
        .dashboard-dropdown-menu {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            display: none;
        }

        .account-dropdown-dashboard:hover .dashboard-dropdown-menu,
        .account-dropdown-dashboard .dropdown-menu.show {
            opacity: 1 !important;
            transform: translateY(0) scale(1) !important;
            pointer-events: auto !important;
            display: block !important;
        }

        .luxury-header .dropdown-header {
            padding: 1rem 1.5rem 0.5rem;
            color: #6c757d;
            font-weight: 600;
        }

        .luxury-header .user-info-detailed {
            padding: 0.5rem;
        }

        .luxury-header .user-avatar-large {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .luxury-header .dropdown-item,
        .dashboard-dropdown-menu .dropdown-item {
            color: #333 !important;
            padding: 0.8rem 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            border-radius: 0;
            white-space: normal;
            background: transparent;
        }

        .luxury-header .dropdown-item:hover,
        .dashboard-dropdown-menu .dropdown-item:hover {
            background: #f8f9fa !important;
            color: #d4af37 !important;
            /* Remove transform to keep text in dropdown */
            transform: none !important;
            border-radius: 8px;
            margin: 0 0.5rem;
        }

        .luxury-header .dropdown-item i,
        .dashboard-dropdown-menu .dropdown-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .luxury-header .stat-number,
        .dashboard-dropdown-menu .stat-number {
            font-weight: 700;
            font-size: 1.1rem;
            color: #d4af37;
        }

        /* Breadcrumb styling to match account page */
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

        /* Page header matching account page */
        .page-header {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
            border-radius: 1rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Stats cards matching account page style */
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
            padding: 1rem;
            border-radius: 0.75rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #d4af37;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        /* Dashboard content styling */
        .dashboard-content {
            padding-top: 0;
            margin-top: 0;
        }

        /* Enhanced sidebar matching account page */
        .luxury-sidebar {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            position: sticky;
            top: 2rem;
        }

        .sidebar-header {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .sidebar-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        /* Navigation links in sidebar */
        .nav-link-luxury {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #333;
            text-decoration: none;
            border-radius: 0;
            margin-bottom: 0;
            transition: all 0.3s ease;
            background: transparent;
            border-bottom: 1px solid #f8f9fa;
        }

        .nav-link-luxury:hover {
            background: #f8f9fa;
            color: #d4af37;
            text-decoration: none;
            border-left: 4px solid #d4af37;
            padding-left: calc(1.5rem - 4px);
        }

        .nav-link-luxury.active {
            background: #f8f9fa;
            color: #d4af37;
            font-weight: 600;
            border-left: 4px solid #d4af37;
            padding-left: calc(1.5rem - 4px);
        }

        .nav-link-luxury i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
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

        .content-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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

        /* Button styling matching account page */
        .btn-luxury-primary {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
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

        .btn-luxury-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-luxury-secondary {
            background: white;
            border: 2px solid #d4af37;
            color: #d4af37;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-luxury-secondary:hover {
            background: #d4af37;
            color: white;
            text-decoration: none;
        }

        /* Property cards */
        .property-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
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
        }

        .property-price {
            color: #d4af37;
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }        .property-title {
            color: #333;
            font-weight: 700;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .property-details {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        /* make modal header title and section headings heaviest */
        .modal-title,
        .modal-body h6 {
            font-weight: 700 !important;
            color: #333 !important;
        }

        /* body paragraphs slightly lighter */
        .modal-body p {
            font-weight: 400 !important;
            color: #555 !important;
        }

        /* replace black override with gold */
        .modal-body .text-primary {
            color: #d4af37 !important;
            font-weight: 700 !important;
        }

        /* darken the close icon */
        .modal-header .btn-close {
            --bs-btn-close-color: #333;
            filter: none;
        }

        /* force close‐icon to dark */
        .modal-header .btn-close {
            color: #333 !important;
            opacity: 1 !important;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .user-profile {
                margin-right: 1rem;
                flex-direction: column;
                text-align: center;
                padding: 0.75rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .luxury-header .dropdown-menu,
            .dashboard-dropdown-menu {
                right: 1rem !important;
                max-width: 300px;
                transform: none !important;
            }
            
            /* Disable hover on mobile for dashboard dropdown */
            .account-dropdown-dashboard:hover .dashboard-dropdown-menu {
                display: none;
            }
            
            /* Enable click on mobile */
            .account-dropdown-dashboard .dropdown-menu.show {
                display: block !important;
                opacity: 1 !important;
                transform: none !important;
                pointer-events: auto !important;
            }
        }

        /* Ensure proper spacing for content */
        .container {
            padding-top: 2rem;
        }

        /* Success and error messages styling */
        .alert {
            border: none;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }

        /* Text utilities */
        .text-gold {
            color: #d4af37 !important;
        }

        .bg-grey-light {
            background-color: #f8f9fa !important;
        }

        /* darker, bolder text in upcoming appointments & favorites cards */
        .content-card-body .fw-semibold,
        .content-card-body .text-muted,
        .content-card-body small,
        .content-card-body p {
            font-weight: 600 !important;
            color: #333 !important;
        }

        /* appointment icons more prominent */
        .content-card-header i.fa-calendar-check,
        .content-card-body i.fa-calendar-alt {
            color: #333 !important;
            font-size: 2.5rem !important;
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
                    <div class="dropdown account-dropdown-dashboard">
                        <div class="user-profile d-inline-flex align-items-center dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                            <div class="user-avatar me-2">
                                <?= strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)) ?>
                            </div>
                            <div class="user-info me-2">
                                <div class="fw-semibold"><?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?></div>
                                <small><?= ucfirst($user_data['role']) ?> Member</small>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end dashboard-dropdown-menu">
                            <li class="dropdown-header">
                                <div class="user-info-detailed">
                                    <div class="user-avatar-large mx-auto mb-2">
                                        <?= strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)) ?>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-semibold"><?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?></div>
                                        <small class="text-muted"><?= ucfirst($user_data['role']) ?> Member</small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Quick Stats -->
                            <li class="px-3 py-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                                        <small class="text-muted">Appointments</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number"><?= $stats['favorites'] ?></div>
                                        <small class="text-muted">Favorites</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number"><?= $stats['properties_viewed'] ?></div>
                                        <small class="text-muted">Views</small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Navigation Links -->
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="fas fa-chart-pie me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Dashboard</div>
                                        <small class="text-muted">Overview & Statistics</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="account.php">
                                    <i class="fas fa-user-cog me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-semibold">Account Settings</div>
                                        <small class="text-muted">Profile & Preferences</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php?section=favorites">
                                    <i class="fas fa-heart me-2 text-danger"></i>
                                    <div>
                                        <div class="fw-semibold">My Favorites</div>
                                        <small class="text-muted">Saved Properties</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php?section=appointments">
                                    <i class="fas fa-calendar-alt me-2 text-success"></i>
                                    <div>
                                        <div class="fw-semibold">Appointments</div>
                                        <small class="text-muted">Schedule & History</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Logout -->
                            <li>
                                <a class="dropdown-item text-danger" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    <div>
                                        <div class="fw-semibold">Sign Out</div>
                                        <small class="text-muted">End your session</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><i class="fas fa-home me-1"></i>Home</li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Page Header -->
    <div class="container">
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="fas fa-chart-pie me-3"></i>Dashboard</h1>
                        <p class="mb-0">Welcome to your personalized dashboard, <?= htmlspecialchars($user_data['first_name']) ?>!</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="../pages/home.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container dashboard-content">
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

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= $stats['total_appointments'] ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $stats['confirmed_appointments'] ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $stats['favorites'] ?></div>
                    <div class="stat-label">Favorites</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">2025</div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="luxury-sidebar">
                    <div class="sidebar-header">
                        <div class="sidebar-avatar">
                            <?= strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)) ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) ?></h5>
                        <small class="opacity-75">Member since <?= date('Y', strtotime($user_data['created_at'] ?? '2025-01-01')) ?></small>
                    </div>

                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <a href="?section=overview" class="nav-link-luxury <?= $current_section === 'overview' ? 'active' : '' ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span>Overview</span>
                        </a>
                        <a href="?section=appointments" class="nav-link-luxury <?= $current_section === 'appointments' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Appointments</span>
                        </a>
                        <a href="?section=search" class="nav-link-luxury <?= $current_section === 'search' ? 'active' : '' ?>">
                            <i class="fas fa-search"></i>
                            <span>Search</span>
                        </a>
                        <a href="?section=favorites#favoritesSection" class="nav-link-luxury <?= $current_section === 'favorites' ? 'active' : '' ?>">
                            <i class="fas fa-heart"></i>
                            <span>Favorites</span>
                        </a>
                        <a href="account.php" class="nav-link-luxury">
                            <i class="fas fa-user-cog"></i>
                            <span>Account Settings</span>
                        </a>
                    </nav>

                    <!-- Quick Actions -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chart-pie me-1"></i>Dashboard
                            </a>
                            <a href="account.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-cog me-1"></i>Account Settings
                            </a>
                            <a href="?section=favorites#favoritesSection" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-heart me-1"></i>My Favorites
                            </a>
                            <a href="?section=appointments" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-calendar-alt me-1"></i>Appointments
                            </a>
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
                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="content-card">
                                <div class="content-card-header">
                                    <i class="fas fa-calendar-check me-2"></i>Upcoming Appointments
                                </div>
                                <div class="content-card-body">
                                    <?php if (!empty($upcoming_appointments)): ?>
                                        <?php foreach (array_slice($upcoming_appointments, 0, 2) as $appointment): ?>
                                            <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                                <div class="me-3">
                                                    <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold"><?= htmlspecialchars($appointment['property_title']) ?></div>
                                                    <small class="text-muted">with <?= htmlspecialchars($appointment['agent_name']) ?></small>
                                                    <div class="text-primary mt-1">
                                                        <small><?= date('M d, Y - H:i', strtotime($appointment['appointment_date'])) ?></small>
                                                    </div>
                                                </div>
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
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-4">
                            <div class="content-card">
                                <div class="content-card-header">
                                    <i class="fas fa-heart me-2"></i>Favorite Properties
                                </div>
                                <div class="content-card-body">
                                    <?php if (!empty($favorite_properties)): ?>
                                        <?php foreach (array_slice($favorite_properties, 0, 2) as $property): ?>                                            <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                                <div class="me-3" style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 2px solid #d4af37;">
                                                    <img src="../assets/images/property<?= $property['id'] ?>-2.jpg" 
                                                         alt="<?= htmlspecialchars($property['titre']) ?>"
                                                         style="width: 100%; height: 100%; object-fit: cover;"
                                                         onerror="this.src='../assets/images/placeholder.jpg'">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold"><?= htmlspecialchars($property['titre']) ?></div>
                                                    <small class="text-muted"><?= $property['surface'] ?>m² • <?= $property['nb_pieces'] ?> rooms</small>
                                                    <div class="property-price mt-1">€<?= number_format($property['prix'], 0, ',', ' ') ?></div>
                                                    <div class="mt-2">
                                                        <a href="../pages/explore.php?property_id=<?= $property['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i>View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>                                        <div class="text-center py-4">
                                            <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No favorite properties yet</p>
                                            <a href="../pages/explore.php" class="btn-luxury-primary">
                                                <i class="fas fa-search"></i>
                                                Browse Properties
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($current_section === 'appointments'): ?>
                    <!-- flash success from booking -->
                    <?php if (!empty($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <?= $_SESSION['success_message'] ?>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <ul class="nav nav-tabs mb-4">
                      <li class="nav-item">
                        <a class="nav-link <?= ($_GET['section'] ?? '')==='appointments' ? 'active' : '' ?>"
                           href="?section=appointments">My Appointments</a>
                      </li>
                    </ul>

                    <?php if(($_GET['section'] ?? '')==='appointments'): 
                      // fetch appointments
                      $stmt = $pdo->prepare("
                        SELECT a.appointment_date, a.status, p.title AS property,
                               CONCAT(u.first_name,' ',u.last_name) AS agent
                        FROM appointments a
                        JOIN properties p ON a.property_id=p.id
                        JOIN users u ON a.agent_id=u.id
                        WHERE a.client_id=?
                        ORDER BY a.appointment_date DESC
                      ");
                      $stmt->execute([$_SESSION['user_id']]);
                      $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                      <?php if (empty($apps)): ?>
                        <p>No appointments found.</p>
                      <?php else: ?>
                        <table class="table">
                          <thead>
                            <tr>
                              <th>Date & Time</th><th>Property</th><th>Agent</th><th>Status</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach($apps as $a): ?>
                              <tr>
                                <td><?= date('d M Y, H:i', strtotime($a['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($a['property']) ?></td>
                                <td><?= htmlspecialchars($a['agent']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($a['status'])) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($current_section === 'search'): ?>
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-search me-2"></i>Property Search
                        </div>
                        <div class="content-card-body">
                            <!-- Search Form -->
                            <form method="GET" action="" class="mb-4">
                                <input type="hidden" name="section" value="search">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Property Type</label>
                                        <select class="form-select">
                                            <option>All Types</option>
                                            <option>House</option>
                                            <option>Apartment</option>
                                            <option>Land</option>
                                            <option>Commercial</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">City</label>
                                        <input type="text" class="form-control" placeholder="City name">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Max Budget</label>
                                        <input type="text" class="form-control" placeholder="Maximum price">
                                    </div>
                                </div>
                                <button type="submit" class="btn-luxury-primary">
                                    <i class="fas fa-search"></i>
                                    Search Properties
                                </button>
                            </form>

                            <!-- Results -->
                            <div class="row">
                                <?php foreach ($favorite_properties as $property): ?>
                                    <div class="col-md-6 mb-4">
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
                        </div>
                    </div>

                <?php elseif ($current_section === 'favorites'): ?>
                    <div id="favoritesSection" class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-heart me-2"></i>My Favorite Properties
                        </div>
                        <div class="content-card-body">
                            <?php if (!empty($favorite_properties)): ?>
                                <div class="row">
                                    <?php foreach ($favorite_properties as $property): ?>                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="property-card">
                                                <div class="property-image" style="height: 200px; overflow: hidden;">
                                                    <img src="../assets/images/property<?= $property['id'] ?>-2.jpg" 
                                                         alt="<?= htmlspecialchars($property['titre']) ?>"
                                                         style="width: 100%; height: 100%; object-fit: cover;"
                                                         onerror="this.src='../assets/images/placeholder.jpg'">
                                                </div>
                                                <div class="p-3">
                                                    <div class="property-price">€<?= number_format($property['prix'], 0, ',', ' ') ?></div>
                                                    <div class="property-title"><?= htmlspecialchars($property['titre']) ?></div>
                                                    <div class="property-details">
                                                        <?= $property['surface'] ?>m² • <?= $property['nb_pieces'] ?> rooms<br>
                                                        <?= htmlspecialchars($property['etage'] ?? 'Floor not specified') ?><br>
                                                        Agent: <?= htmlspecialchars($property['agent_name'] ?? 'Not assigned') ?>
                                                    </div>                                                    <div class="d-flex gap-2">
                                                        <button type="button" 
                                                                class="btn-luxury-primary flex-grow-1"
                                                                onclick="showPropertyDetailsFromDashboard(<?= htmlspecialchars(json_encode($property)) ?>, true)">
                                                            <i class="fas fa-eye me-1"></i>View Details
                                                        </button>
                                                        <button class="btn btn-outline-danger" 
                                                                title="Remove from favorites"
                                                                onclick="removeFavorite(<?= $property['id'] ?>, this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>                                <div class="text-center py-5">
                                    <i class="fas fa-heart-broken fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-3">No Favorite Properties</h4>
                                    <p class="text-muted mb-4">Start browsing properties and save your favorites for easy access.</p>
                                    <a href="../pages/explore.php" class="btn-luxury-primary">
                                        <i class="fas fa-search"></i>
                                        Browse Properties
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>        </div>
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
                <div class="modal-footer">
                <button type="button" class="btn btn-dark fw-bold" data-bs-dismiss="modal">Close</button>
                <a href="../pages/explore.php" class="btn btn-primary" id="explorePropertiesBtn">
                        <i class="fas fa-search me-1"></i>Explore All Properties
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <!-- Dashboard Dropdown Hover Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const accountDropdown = document.querySelector('.account-dropdown-dashboard');
        const dropdownMenu = document.querySelector('.dashboard-dropdown-menu');
        
        if (accountDropdown && dropdownMenu) {
            // Show dropdown on hover
            accountDropdown.addEventListener('mouseenter', function() {
                dropdownMenu.style.display = 'block';
                setTimeout(() => {
                    dropdownMenu.style.opacity = '1';
                    dropdownMenu.style.transform = 'translateY(0) scale(1)';
                    dropdownMenu.style.pointerEvents = 'auto';
                }, 10);
            });
            
            // Hide dropdown when leaving both elements
            accountDropdown.addEventListener('mouseleave', function(e) {
                // Check if we're moving to the dropdown menu
                if (!dropdownMenu.contains(e.relatedTarget)) {
                    hideDropdown();
                }
            });
            
            dropdownMenu.addEventListener('mouseleave', function(e) {
                // Check if we're moving to the account dropdown
                if (!accountDropdown.contains(e.relatedTarget)) {
                    hideDropdown();
                }
            });
            
            function hideDropdown() {
                dropdownMenu.style.opacity = '0';
                dropdownMenu.style.transform = 'translateY(-10px) scale(0.95)';
                dropdownMenu.style.pointerEvents = 'none';
                setTimeout(() => {
                    if (dropdownMenu.style.opacity === '0') {
                        dropdownMenu.style.display = 'none';
                    }
                }, 300);
            }
        }    });
    
    // Property details modal functionality
    function showPropertyDetailsFromDashboard(property, isFavorited = false) {
        document.getElementById('propertyModalTitle').textContent = property.titre;
        
        // Get the primary image for this property
        const primaryImage = `../assets/images/property${property.id}-2.jpg`;
        
        const modalBody = document.getElementById('propertyModalBody');
        modalBody.innerHTML = `
            <div class="row">
            <div class="col-md-6">
            <div class="mb-3" style="height: 250px; border-radius: 8px; overflow: hidden; border: 2px solid #d4af37;">
            <img src="${primaryImage}" 
                 alt="${property.titre}"
                 style="width: 100%; height: 100%; object-fit: cover;"
                 onerror="this.src='../assets/images/placeholder.jpg'">
            </div>
            </div>
            <div class="col-md-6">
            <h6 class="fw-bold" style="color: #111; font-weight: 600;"><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
            <p class="fw-semibold" style="color: #111; font-weight: 600;">${property.address_line1 || 'Address not specified'}, ${property.city || 'City not specified'}</p>
            
            <h6 class="fw-bold" style="color: #111; font-weight: 600;"><i class="fas fa-info-circle me-2"></i>Property Details</h6>
            <p class="fw-semibold" style="color: #111; font-weight: 600;">
            Type: <span class="fw-bold" style="color: #111; font-weight: 600;">${property.property_type ? property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1) : 'Not specified'}</span><br>
            Surface: <span class="fw-bold" style="color: #111; font-weight: 600;">${property.surface}m²</span><br>
            Rooms: <span class="fw-bold" style="color: #111; font-weight: 600;">${property.nb_pieces}</span><br>
            Floor: <span class="fw-bold" style="color: #111; font-weight: 600;">${property.etage}</span>
            </p>
            
            <h6 class="fw-bold" style="color: #111; font-weight: 600;"><i class="fas fa-euro-sign me-2"></i>Price</h6>
            <p class="h4 text-primary fw-bold" style="color: #111; font-weight: 600;">€${new Intl.NumberFormat().format(property.prix)}</p>
            
            <h6 class="fw-bold" style="color: #111; font-weight: 600;"><i class="fas fa-user-tie me-2"></i>Agent</h6>
            <p class="fw-semibold" style="color: #111; font-weight: 600;">${property.agent_name || 'Not assigned'}</p>
            </div>
            </div>
            <hr>
            <h6 class="fw-bold" style="color: #111; font-weight: 600;"><i class="fas fa-file-alt me-2"></i>Description</h6>
            <p class="fw-semibold" style="color: #111; font-weight: 600;">${property.description || 'No description available for this property.'}</p>
            <div class="d-grid gap-2">
            <a href="../pages/explore.php?property_id=${property.id}" class="btn btn-primary fw-bold">
            <i class="fas fa-eye me-1"></i>View Full Details in Explore
            </a>
            <button type="button" 
            class="btn btn-outline-danger fw-bold"
            onclick="removeFavoriteFromModal(${property.id})">
            <i class="fas fa-heart-broken me-1"></i>Remove from Favorites
            </button>
            </div>
        `;
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
        modal.show();
    }
    
    // Remove favorite from modal
    function removeFavoriteFromModal(propertyId) {
        if (confirm('Are you sure you want to remove this property from your favorites?')) {
            removeFavorite(propertyId, null);
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('propertyModal'));
            modal.hide();
        }
    }
      // Remove favorite function
    function removeFavorite(propertyId, element) {
        fetch('../ajax/favorites.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `property_id=${propertyId}&action=remove`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showNotification('Property removed from favorites!', 'success');
                // Reload the page to update the favorites list
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification('Error removing from favorites: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error removing from favorites', 'error');
        });
    }
    
    // Show notification function
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    </script>
</body>
</html>