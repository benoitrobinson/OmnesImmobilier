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
            redirect('../client/dashboard.php'); // Temporary
            break;
        default:
            redirect('../auth/login.php');
    }
}

$error = '';
$agent_data = [];

// Get agent information
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

// Get agent statistics
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
    // Get property statistics
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

    // Get appointment statistics
    $appointment_query = "SELECT 
                         COUNT(*) as total_appointments,
                         SUM(CASE WHEN status = 'scheduled' AND appointment_date >= NOW() THEN 1 ELSE 0 END) as scheduled_appointments
                         FROM appointments WHERE agent_id = :agent_id";
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->bindParam(':agent_id', $_SESSION['user_id']);
    $appointment_stmt->execute();
    $appointment_stats = $appointment_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment_stats) {
        $stats = array_merge($stats, $appointment_stats);
    }

    // Get message statistics
    $message_query = "SELECT COUNT(*) as unread_messages 
                     FROM messages 
                     WHERE receiver_id = :agent_id AND is_read = 0";
    $message_stmt = $pdo->prepare($message_query);
    $message_stmt->bindParam(':agent_id', $_SESSION['user_id']);
    $message_stmt->execute();
    $message_stats = $message_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message_stats) {
        $stats = array_merge($stats, $message_stats);
    }

    // Calculate client satisfaction from agent data
    $stats['client_satisfaction'] = round($agent_data['average_rating'] * 20); // Convert 5-star to percentage

} catch (Exception $e) {
    error_log("Agent Stats error: " . $e->getMessage());
}

// Get current section from URL
$current_section = $_GET['section'] ?? 'overview';

// Get upcoming appointments
$upcoming_appointments = [];
try {
    $appointment_query = "SELECT a.*, p.title as property_title, p.address_line1 as address,
                         p.price as property_price,
                         CONCAT(u.first_name, ' ', u.last_name) as client_name,
                         u.phone as client_phone, u.email as client_email,
                         a.appointment_date as date_rdv,
                         TIME(a.appointment_date) as heure_rdv
                  FROM appointments a 
                  JOIN properties p ON a.property_id = p.id 
                  JOIN users u ON a.client_id = u.id 
                  WHERE a.agent_id = :agent_id 
                  AND a.appointment_date >= NOW() 
                  AND a.status = 'scheduled'
                  ORDER BY a.appointment_date 
                  LIMIT 10";
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->bindParam(':agent_id', $_SESSION['user_id']);
    $appointment_stmt->execute();
    $upcoming_appointments = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
}

// Get agent's properties
$agent_properties = [];
try {
    $property_query = "SELECT p.*, 
                       CASE WHEN p.property_type = 'apartment' THEN FLOOR(RAND() * 5 + 1)
                            WHEN p.property_type = 'house' THEN FLOOR(RAND() * 8 + 2)
                            ELSE 0 END as bedrooms,
                       CASE WHEN p.property_type IN ('apartment', 'house') THEN FLOOR(RAND() * 150 + 30)
                            ELSE FLOOR(RAND() * 1000 + 100) END as living_area
                FROM properties p 
                WHERE p.agent_id = :agent_id 
                ORDER BY p.created_at DESC 
                LIMIT 6";
    $property_stmt = $pdo->prepare($property_query);
    $property_stmt->bindParam(':agent_id', $_SESSION['user_id']);
    $property_stmt->execute();
    $agent_properties = $property_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Properties error: " . $e->getMessage());
}

// Get recent messages
$recent_messages = [];
try {
    $message_query = "SELECT m.*, 
                     CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                     p.title as property_title
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id
                     LEFT JOIN properties p ON m.property_id = p.id
                     WHERE m.receiver_id = :agent_id
                     ORDER BY m.sent_at DESC
                     LIMIT 5";
    $message_stmt = $pdo->prepare($message_query);
    $message_stmt->bindParam(':agent_id', $_SESSION['user_id']);
    $message_stmt->execute();
    $recent_messages = $message_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Messages error: " . $e->getMessage());
}

// Mock data fallbacks
if (empty($upcoming_appointments)) {
    $upcoming_appointments = [
        [
            'id' => 1,
            'property_title' => 'Luxury Apartment - Champs-Élysées',
            'client_name' => 'Alice Durand',
            'client_phone' => '+33 7 12 34 56 78',
            'client_email' => 'alice@omnes.fr',
            'date_rdv' => '2025-05-28',
            'heure_rdv' => '14:30:00',
            'address' => '100 Avenue des Champs-Élysées, Paris',
            'property_price' => 850000
        ]
    ];
}

if (empty($agent_properties)) {
    $agent_properties = [
        [
            'id' => 1,
            'title' => 'Luxury Apartment - Champs-Élysées',
            'price' => 850000,
            'bedrooms' => 3,
            'living_area' => 120,
            'property_type' => 'apartment',
            'status' => 'available',
            'address_line1' => '100 Avenue des Champs-Élysées',
            'city' => 'Paris'
        ],
        [
            'id' => 2,
            'title' => 'Modern Office Space',
            'price' => 1200000,
            'bedrooms' => 0,
            'living_area' => 300,
            'property_type' => 'commercial',
            'status' => 'available',
            'address_line1' => '50 Avenue de la Grande Armée',
            'city' => 'Paris'
        ]
    ];
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
    <!-- Agent Dashboard CSS -->
    <link href="../assets/css/agent-dashboard.css" rel="stylesheet">
    
    <style>
        /* Agent-specific theme colors */
        :root {
            --agent-primary: #2c5aa0;
            --agent-secondary: #4a90e2;
            --agent-accent: #1e3d72;
            --agent-success: #28a745;
            --agent-warning: #ffc107;
            --agent-danger: #dc3545;
            --agent-light: #f8f9fa;
            --agent-dark: #343a40;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Header */
        .agent-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            padding: 1rem 0;
            color: white;
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none !important;
        }

        .agent-profile {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            margin-right: 2rem;
        }

        .agent-profile:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .agent-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        /* Dropdown */
        .agent-dropdown-menu {
            background: white !important;
            border: none !important;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25) !important;
            border-radius: 16px !important;
            margin-top: 20px !important;
            min-width: 320px;
            padding: 1rem 0;
            right: 2rem !important;
            border: 2px solid rgba(44, 90, 160, 0.2) !important;
        }

        .agent-dropdown-menu .dropdown-item {
            color: #333 !important;
            padding: 0.8rem 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .agent-dropdown-menu .dropdown-item:hover {
            background: #f8f9fa !important;
            color: var(--agent-primary) !important;
            border-radius: 8px;
            margin: 0 0.5rem;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
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
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
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

        /* Sidebar */
        .agent-sidebar {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            position: sticky;
            top: 2rem;
        }

        .sidebar-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
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

        .nav-link-agent {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f8f9fa;
        }

        .nav-link-agent:hover {
            background: #f8f9fa;
            color: var(--agent-primary);
            border-left: 4px solid var(--agent-primary);
            padding-left: calc(1.5rem - 4px);
        }

        .nav-link-agent.active {
            background: #f8f9fa;
            color: var(--agent-primary);
            font-weight: 600;
            border-left: 4px solid var(--agent-primary);
            padding-left: calc(1.5rem - 4px);
        }

        .nav-link-agent i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        /* Buttons */
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

        .btn-agent-secondary {
            background: white;
            border: 2px solid var(--agent-primary);
            color: var(--agent-primary);
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
            background: var(--agent-primary);
            color: white;
        }

        /* Property Cards */
        .property-card-agent {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
        }

        .property-card-agent:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .property-image-agent {
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

        /* Appointment Cards */
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

        /* Mobile responsive */
        @media (max-width: 768px) {
            .agent-profile {
                margin-right: 1rem;
                padding: 0.75rem;
            }
            
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
<body data-page="agent-dashboard">
    <!-- Header -->
    <header class="agent-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../pages/home.php" class="brand-logo text-decoration-none">
                        <i class="fas fa-building me-2"></i>Omnes Real Estate
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <div class="dropdown">
                        <div class="agent-profile d-inline-flex align-items-center dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                            <div class="agent-avatar me-2">
                                <?= strtoupper(substr($agent_data['first_name'], 0, 1) . substr($agent_data['last_name'], 0, 1)) ?>
                            </div>
                            <div class="user-info me-2">
                                <div class="fw-semibold"><?= htmlspecialchars($agent_data['first_name'] . ' ' . $agent_data['last_name']) ?></div>
                                <small>Licensed Agent</small>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end agent-dropdown-menu">
                            <li class="dropdown-header">
                                <div class="text-center">
                                    <div class="agent-avatar mx-auto mb-2" style="width: 50px; height: 50px;">
                                        <?= strtoupper(substr($agent_data['first_name'], 0, 1) . substr($agent_data['last_name'], 0, 1)) ?>
                                    </div>
                                    <div class="fw-semibold"><?= htmlspecialchars($agent_data['first_name'] . ' ' . $agent_data['last_name']) ?></div>
                                    <small class="text-muted">Licensed Real Estate Agent</small>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Quick Stats -->
                            <li class="px-3 py-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div style="font-weight: 700; color: var(--agent-primary);"><?= $stats['managed_properties'] ?></div>
                                        <small class="text-muted">Properties</small>
                                    </div>
                                    <div class="col-4">
                                        <div style="font-weight: 700; color: var(--agent-primary);"><?= $stats['scheduled_appointments'] ?></div>
                                        <small class="text-muted">Appointments</small>
                                    </div>
                                    <div class="col-4">
                                        <div style="font-weight: 700; color: var(--agent-primary);"><?= $stats['unread_messages'] ?></div>
                                        <small class="text-muted">Messages</small>
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
                                        <small class="text-muted">Overview & Analytics</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="properties.php">
                                    <i class="fas fa-home me-2 text-success"></i>
                                    <div>
                                        <div class="fw-semibold">Properties</div>
                                        <small class="text-muted">Manage Listings</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="appointments.php">
                                    <i class="fas fa-calendar-alt me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-semibold">Appointments</div>
                                        <small class="text-muted">Schedule & Meetings</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="messages.php">
                                    <i class="fas fa-envelope me-2 text-info"></i>
                                    <div>
                                        <div class="fw-semibold">Messages</div>
                                        <small class="text-muted">Client Communication</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="account.php">
                                    <i class="fas fa-user-cog me-2 text-secondary"></i>
                                    <div>
                                        <div class="fw-semibold">Account Settings</div>
                                        <small class="text-muted">Profile & Preferences</small>
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
    <div class="breadcrumb-container" style="background: white; padding: 1rem 0; border-bottom: 1px solid #e9ecef;">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><i class="fas fa-home me-1"></i>Agent Portal</li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Page Header -->
    <div class="container mt-4">
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="fas fa-chart-line me-3"></i>Agent Dashboard</h1>
                        <p class="mb-0">Welcome back, <?= htmlspecialchars($agent_data['first_name']) ?>! Manage your properties and grow your business.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="properties.php?action=add" class="btn btn-light">
                            <i class="fas fa-plus me-2"></i>Add Property
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
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
                    <div class="stat-value"><?= $stats['client_satisfaction'] ?>%</div>
                    <div class="stat-label">Client Satisfaction</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="agent-sidebar">
                    <div class="sidebar-header">
                        <div class="sidebar-avatar">
                            <?= strtoupper(substr($agent_data['first_name'], 0, 1) . substr($agent_data['last_name'], 0, 1)) ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($agent_data['first_name'] . ' ' . $agent_data['last_name']) ?></h5>
                        <small class="opacity-75"><?= htmlspecialchars($agent_data['agency_name']) ?></small>
                        <?php if ($agent_data['average_rating'] > 0): ?>
                        <div class="mt-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $agent_data['average_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                            <small class="ms-1">(<?= number_format($agent_data['average_rating'], 1) ?>)</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Navigation -->
                    <nav class="nav flex-column">
                        <a href="?section=overview" class="nav-link-agent <?= $current_section === 'overview' ? 'active' : '' ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span>Overview</span>
                        </a>
                        <a href="properties.php" class="nav-link-agent">
                            <i class="fas fa-home"></i>
                            <span>Properties</span>
                        </a>
                        <a href="appointments.php" class="nav-link-agent">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Appointments</span>
                        </a>
                        <a href="availability.php" class="nav-link-agent">
                            <i class="fas fa-clock"></i>
                            <span>Availability</span>
                        </a>
                        <a href="messages.php" class="nav-link-agent">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if ($stats['unread_messages'] > 0): ?>
                                <span class="badge bg-danger ms-auto"><?= $stats['unread_messages'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="account.php" class="nav-link-agent">
                            <i class="fas fa-user-cog"></i>
                            <span>Account Settings</span>
                        </a>
                    </nav>

                    <!-- Quick Actions -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem;">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="properties.php?action=add" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Add Property
                            </a>
                            <a href="appointments.php?action=schedule" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-calendar-plus me-1"></i>Schedule Meeting
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
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="content-card">
                            <div class="content-card-header">
                                <i class="fas fa-calendar-check me-2"></i>Upcoming Appointments
                            </div>
                            <div class="content-card-body">
                                <?php if (!empty($upcoming_appointments)): ?>
                                    <?php foreach (array_slice($upcoming_appointments, 0, 3) as $appointment): ?>
                                        <div class="appointment-card-agent">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="fw-semibold"><?= htmlspecialchars($appointment['property_title']) ?></div>
                                                <small class="text-muted"><?= date('M d, H:i', strtotime($appointment['date_rdv'] . ' ' . $appointment['heure_rdv'])) ?></small>
                                            </div>
                                            <div class="text-muted mb-2">
                                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($appointment['client_name']) ?>
                                                <br>
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($appointment['client_phone']) ?>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="appointments.php" class="btn-agent-secondary">
                                            <i class="fas fa-calendar-alt"></i>
                                            View All Appointments
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No upcoming appointments</p>
                                        <a href="appointments.php" class="btn-agent-primary">
                                            <i class="fas fa-calendar-plus"></i>
                                            Schedule Meeting
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
                                                <div style="color: var(--agent-primary); font-weight: 600;">
                                                    €<?= number_format($property['price'], 0, ',', ' ') ?>
                                                </div>
                                            </div>
                                            <div class="property-status status-<?= $property['status'] ?>">
                                                <?= ucfirst($property['status']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="properties.php" class="btn-agent-secondary">
                                            <i class="fas fa-home"></i>
                                            Manage All Properties
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-home fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No properties listed yet</p>
                                        <a href="properties.php?action=add" class="btn-agent-primary">
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
                                <div class="h3" style="color: var(--agent-primary);"><?= $stats['total_appointments'] ?></div>
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
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/agent-dashboard.js"></script>
</body>
</html>