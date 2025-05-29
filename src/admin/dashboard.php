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

// Get admin information
$admin_data = getCurrentUser();

// Get statistics
$stats = [
    'total_users' => 0,
    'total_agents' => 0,
    'total_clients' => 0,
    'total_properties' => 0,
    'available_properties' => 0,
    'sold_properties' => 0,
    'total_appointments' => 0,
    'scheduled_appointments' => 0,
    'revenue_this_month' => 0,
    'new_users_this_month' => 0
];

try {
    // Get user statistics
    $user_stats = $db->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'agent' THEN 1 ELSE 0 END) as total_agents,
            SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as total_clients,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as new_users_this_month
        FROM users
    ")->fetch(PDO::FETCH_ASSOC);
    
    $stats = array_merge($stats, $user_stats);
    
    // Get property statistics
    $property_stats = $db->query("
        SELECT 
            COUNT(*) as total_properties,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_properties,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_properties,
            SUM(CASE WHEN status = 'sold' AND MONTH(updated_at) = MONTH(CURRENT_DATE()) THEN price ELSE 0 END) as revenue_this_month
        FROM properties
    ")->fetch(PDO::FETCH_ASSOC);
    
    $stats = array_merge($stats, $property_stats);
    
    // Get appointment statistics
    $appointment_stats = $db->query("
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'scheduled' AND appointment_date >= NOW() THEN 1 ELSE 0 END) as scheduled_appointments
        FROM appointments
    ")->fetch(PDO::FETCH_ASSOC);
    
    $stats = array_merge($stats, $appointment_stats);
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
}

// Get recent activities
$recent_activities = [];
try {
    // Recent users
    $recent_users = $db->query("
        SELECT u.*, 'new_user' as activity_type, u.created_at as activity_date
        FROM users u
        ORDER BY u.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent properties
    $recent_properties = $db->query("
        SELECT p.*, u.first_name, u.last_name, 'new_property' as activity_type, p.created_at as activity_date
        FROM properties p
        JOIN users u ON p.agent_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent appointments
    $recent_appointments = $db->query("
        SELECT a.*, p.title as property_title, 
               CONCAT(agent.first_name, ' ', agent.last_name) as agent_name,
               CONCAT(client.first_name, ' ', client.last_name) as client_name,
               'new_appointment' as activity_type, a.created_at as activity_date
        FROM appointments a
        JOIN properties p ON a.property_id = p.id
        JOIN users agent ON a.agent_id = agent.id
        JOIN users client ON a.client_id = client.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort activities
    $recent_activities = array_merge($recent_users, $recent_properties, $recent_appointments);
    usort($recent_activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    $recent_activities = array_slice($recent_activities, 0, 10);
    
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Omnes Real Estate</title>
    
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

        /* Admin Header */
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

        .admin-profile {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Sidebar */
        .admin-sidebar {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: sticky;
            top: 2rem;
        }

        .sidebar-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .nav-link-admin {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f8f9fa;
        }

        .nav-link-admin:hover {
            background: var(--admin-light);
            color: var(--admin-primary);
            transform: translateX(5px);
        }

        .nav-link-admin.active {
            background: var(--admin-light);
            color: var(--admin-primary);
            font-weight: 600;
            border-left: 4px solid var(--admin-primary);
            padding-left: calc(1.5rem - 4px);
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* Content Cards */
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

        /* Activity Feed */
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #f8f9fa;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        /* Buttons */
        .btn-admin-primary {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-admin-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-value {
                font-size: 2rem;
            }
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
                    <div class="admin-profile d-inline-flex align-items-center">
                        <div class="me-3">
                            <div class="fw-semibold"><?= htmlspecialchars($admin_data['first_name'] . ' ' . $admin_data['last_name']) ?></div>
                            <small>Administrator</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="admin-sidebar">
                    <div class="sidebar-header">
                        <div class="admin-avatar mx-auto mb-3" style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5 class="mb-1">Admin Panel</h5>
                        <small>System Management</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a href="dashboard.php" class="nav-link-admin active">
                            <i class="fas fa-chart-pie me-3"></i>Dashboard
                        </a>
                        <a href="manage_clients.php" class="nav-link-admin">
                            <i class="fas fa-users me-3"></i>Manage Clients
                        </a>
                        <a href="manage_agents.php" class="nav-link-admin">
                            <i class="fas fa-user-tie me-3"></i>Manage Agents
                        </a>
                        <a href="manage_properties.php" class="nav-link-admin">
                            <i class="fas fa-home me-3"></i>Manage Properties
                        </a>
                        <a href="manage_appointments.php" class="nav-link-admin">
                            <i class="fas fa-calendar-alt me-3"></i>Appointments
                        </a>
                        <a href="events.php" class="nav-link-admin">
                            <i class="fas fa-calendar-star me-3"></i>Events
                        </a>
                        <a href="reports.php" class="nav-link-admin">
                            <i class="fas fa-chart-bar me-3"></i>Reports
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Page Header -->
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h1 class="h4 mb-0">
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                        </h1>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">Welcome back! Here's an overview of your system.</p>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-value text-primary"><?= $stats['total_users'] ?></div>
                                    <div class="stats-label">Total Users</div>
                                </div>
                                <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-value text-success"><?= $stats['total_properties'] ?></div>
                                    <div class="stats-label">Properties</div>
                                </div>
                                <div class="stats-icon bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-home"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-value text-warning"><?= $stats['scheduled_appointments'] ?></div>
                                    <div class="stats-label">Scheduled</div>
                                </div>
                                <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-value text-info">€<?= number_format($stats['revenue_this_month'], 0, ',', ' ') ?></div>
                                    <div class="stats-label">Monthly Revenue</div>
                                </div>
                                <div class="stats-icon bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-euro-sign"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <a href="add_agent.php" class="btn btn-admin-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add Agent
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="add_property.php" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-plus-circle me-2"></i>Add Property
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="manage_appointments.php" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-calendar-alt me-2"></i>View Appointments
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="reports.php" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-chart-bar me-2"></i>Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="content-card">
                    <div class="content-card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="activity_log.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item d-flex align-items-center">
                                <?php if ($activity['activity_type'] === 'new_user'): ?>
                                    <div class="activity-icon bg-primary bg-opacity-10 text-primary me-3">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">New <?= ucfirst($activity['role']) ?> Registered</div>
                                        <small class="text-muted"><?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?> - <?= $activity['email'] ?></small>
                                    </div>
                                <?php elseif ($activity['activity_type'] === 'new_property'): ?>
                                    <div class="activity-icon bg-success bg-opacity-10 text-success me-3">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">New Property Listed</div>
                                        <small class="text-muted"><?= htmlspecialchars($activity['title']) ?> by <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?></small>
                                    </div>
                                <?php elseif ($activity['activity_type'] === 'new_appointment'): ?>
                                    <div class="activity-icon bg-warning bg-opacity-10 text-warning me-3">
                                        <i class="fas fa-calendar-plus"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">New Appointment Scheduled</div>
                                        <small class="text-muted"><?= htmlspecialchars($activity['client_name']) ?> with <?= htmlspecialchars($activity['agent_name']) ?></small>
                                    </div>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <?= date('M d, H:i', strtotime($activity['activity_date'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>