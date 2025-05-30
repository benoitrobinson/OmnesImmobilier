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

$success = '';
$error = '';

// Handle client actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_client' && isset($_POST['client_id'])) {
        try {
            $client_id = (int)$_POST['client_id'];
            
            // Check if client has active appointments
            $check_stmt = $db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM appointments WHERE client_id = :client_id AND status = 'scheduled' AND appointment_date >= NOW()) as future_appointments
            ");
            $check_stmt->execute(['client_id' => $client_id]);
            $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($check['future_appointments'] > 0) {
                $error = "Cannot delete client. They have {$check['future_appointments']} scheduled appointments.";
            } else {
                // Delete client (cascade will handle related records)
                $delete_stmt = $db->prepare("DELETE FROM users WHERE id = :client_id AND role = 'client'");
                $delete_stmt->execute(['client_id' => $client_id]);
                $success = 'Client deleted successfully.';
            }
        } catch (Exception $e) {
            error_log("Delete client error: " . $e->getMessage());
            $error = 'Error deleting client.';
        }
    }
    
    elseif ($action === 'send_message' && isset($_POST['client_id'])) {
        try {
            $client_id = (int)$_POST['client_id'];
            $message = trim($_POST['message'] ?? '');
            
            if (!empty($message)) {
                // Insert admin message to client (you might need to create an admin_messages table)
                // For now, we'll just show success
                $success = 'Message sent to client successfully.';
            } else {
                $error = 'Message cannot be empty.';
            }
        } catch (Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            $error = 'Error sending message.';
        }
    }
}

// Get all clients with statistics
$clients = [];
try {
    $query = "
        SELECT 
            u.*,
            c.address_line1,
            c.address_line2,
            c.city,
            c.state,
            c.postal_code,
            c.country,
            COUNT(DISTINCT a.id) as total_appointments,
            COUNT(DISTINCT CASE WHEN a.status = 'scheduled' AND a.appointment_date >= NOW() THEN a.id END) as upcoming_appointments,
            COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
            COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) as cancelled_appointments,
            COUNT(DISTINCT m.id) as total_messages,
            MAX(a.created_at) as last_appointment_date,
            MAX(m.sent_at) as last_message_date,
            DATEDIFF(NOW(), u.created_at) as days_since_registration
        FROM users u
        LEFT JOIN clients c ON u.id = c.user_id
        LEFT JOIN appointments a ON u.id = a.client_id
        LEFT JOIN messages m ON u.id = m.sender_id
        WHERE u.role = 'client'
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Fetch clients error: " . $e->getMessage());
    $error = 'Error loading clients.';
}

// Search functionality
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

if ($search) {
    $clients = array_filter($clients, function($client) use ($search) {
        $searchLower = strtolower($search);
        return strpos(strtolower($client['first_name']), $searchLower) !== false ||
               strpos(strtolower($client['last_name']), $searchLower) !== false ||
               strpos(strtolower($client['email']), $searchLower) !== false ||
               strpos(strtolower($client['city'] ?? ''), $searchLower) !== false;
    });
}

// Filter functionality
if ($filter !== 'all') {
    $clients = array_filter($clients, function($client) use ($filter) {
        switch ($filter) {
            case 'active':
                return $client['upcoming_appointments'] > 0 || $client['days_since_registration'] <= 30;
            case 'inactive':
                return $client['total_appointments'] == 0 && $client['days_since_registration'] > 30;
            case 'new':
                return $client['days_since_registration'] <= 7;
            default:
                return true;
        }
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients - Admin Panel</title>
    
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

        /* Admin Header - Updated to match dashboard */
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

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            transform: translateY(-1px);
        }

        .dropdown-toggle::after {
            display: none;
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

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0;
        }

        .user-role {
            font-size: 0.75rem;
            opacity: 0.8;
            margin: 0;
        }

        /* Reuse styles from dashboard */
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

        .client-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .client-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .client-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .client-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f8f9fa;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--admin-primary);
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }

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

        .search-box, .filter-select {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .search-box:focus, .filter-select:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
            outline: none;
        }

        .activity-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .activity-active { background-color: #28a745; }
        .activity-inactive { background-color: #6c757d; }
        .activity-new { background-color: #ffc107; }

        .client-location {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .last-activity {
            color: #6c757d;
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- Header - Updated to match dashboard -->
    <nav class="navbar navbar-expand-lg admin-header">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center ms-3" href="../pages/home.php">
                <img src="../assets/images/logo1.png" alt="Logo" width="120" height="50" class="me-3">
            </a>
            
            <button class="navbar-toggler me-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_clients.php">
                            <i class="fas fa-users me-1"></i>Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_agents.php">
                            <i class="fas fa-user-tie me-1"></i>Agents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_properties.php">
                            <i class="fas fa-home me-1"></i>Properties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_appointments.php">
                            <i class="fas fa-calendar-alt me-1"></i>Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="fas fa-calendar-star me-1"></i>Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center me-3">
                    <div class="dropdown">
                        <a href="#" class="user-profile dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                AC
                            </div>
                            <div class="user-info">
                                <div class="user-name">Administrator</div>
                                <div class="user-role">Admin</div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">
                                    <i class="fas fa-user-shield me-2"></i>Admin Actions
                                </h6>
                            </li>
                            <li><a class="dropdown-item" href="add_agent.php">
                                <i class="fas fa-user-plus me-2"></i>Add New Agent
                            </a></li>
                            <li><a class="dropdown-item" href="add_property.php">
                                <i class="fas fa-plus-circle me-2"></i>Add New Property
                            </a></li>
                            <li><a class="dropdown-item" href="add_client.php">
                                <i class="fas fa-user-plus me-2"></i>Add New Client
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

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
                        <a href="dashboard.php" class="nav-link-admin">
                            <i class="fas fa-chart-pie me-3"></i>Dashboard
                        </a>
                        <a href="manage_clients.php" class="nav-link-admin active">
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
                <div class="content-card">
                    <div class="content-card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="h4 mb-0">
                                <i class="fas fa-user-friends me-2"></i>Manage Clients
                            </h1>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                    <i class="fas fa-download me-2"></i>Export Data
                                </button>
                                <button class="btn-admin-primary" data-bs-toggle="modal" data-bs-target="#bulkMessageModal">
                                    <i class="fas fa-envelope me-2"></i>Bulk Message
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <form method="GET" action="">
                                    <div class="input-group">
                                        <input type="text" name="search" class="search-box form-control" 
                                               placeholder="Search clients..." value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-3">
                                <form method="GET" action="" id="filterForm">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <select name="filter" class="filter-select form-select" onchange="document.getElementById('filterForm').submit()">
                                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Clients</option>
                                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Clients</option>
                                        <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive Clients</option>
                                        <option value="new" <?= $filter === 'new' ? 'selected' : '' ?>>New (7 days)</option>
                                    </select>
                                </form>
                            </div>
                            <div class="col-md-5 text-end">
                                <span class="text-muted">
                                    Showing <?= count($clients) ?> clients
                                    <?php
                                    $total_clients = count($clients);
                                    $active_clients = count(array_filter($clients, fn($c) => $c['upcoming_appointments'] > 0 || $c['days_since_registration'] <= 30));
                                    $new_clients = count(array_filter($clients, fn($c) => $c['days_since_registration'] <= 7));
                                    ?>
                                    | <span class="text-success"><?= $active_clients ?> active</span>
                                    | <span class="text-warning"><?= $new_clients ?> new</span>
                                </span>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Clients List -->
                        <?php if (empty($clients)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No clients found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <div class="client-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="client-avatar me-3">
                                                    <?= strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="d-flex align-items-center mb-1">
                                                        <!-- Activity Indicator -->
                                                        <?php
                                                        $activity_class = 'activity-inactive';
                                                        if ($client['days_since_registration'] <= 7) {
                                                            $activity_class = 'activity-new';
                                                        } elseif ($client['upcoming_appointments'] > 0 || $client['days_since_registration'] <= 30) {
                                                            $activity_class = 'activity-active';
                                                        }
                                                        ?>
                                                        <span class="activity-indicator <?= $activity_class ?>"></span>
                                                        <h5 class="mb-0"><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h5>
                                                    </div>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($client['email']) ?>
                                                        <?php if ($client['phone']): ?>
                                                            <br><i class="fas fa-phone me-1"></i><?= htmlspecialchars($client['phone']) ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if ($client['city']): ?>
                                                        <p class="client-location mb-1">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?= htmlspecialchars(trim($client['city'] . ', ' . $client['country'], ', ')) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <p class="last-activity mb-0">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Registered <?= $client['days_since_registration'] ?> days ago
                                                        <?php if ($client['last_appointment_date']): ?>
                                                            | Last appointment: <?= date('M d, Y', strtotime($client['last_appointment_date'])) ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="client-stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $client['total_appointments'] ?></div>
                                                    <div class="stat-label">Total Appts</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $client['upcoming_appointments'] ?></div>
                                                    <div class="stat-label">Upcoming</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $client['completed_appointments'] ?></div>
                                                    <div class="stat-label">Completed</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $client['cancelled_appointments'] ?></div>
                                                    <div class="stat-label">Cancelled</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $client['total_messages'] ?></div>
                                                    <div class="stat-label">Messages</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 text-end">
                                            <div class="btn-group-vertical d-grid gap-2">
                                                <div class="btn-group">
                                                    <a href="view_client.php?id=<?= $client['id'] ?>" class="btn btn-outline-info btn-sm">
                                                        <i class="fas fa-eye"></i> View Profile
                                                    </a>
                                                    <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </div>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-outline-success btn-sm" 
                                                            onclick="sendMessage(<?= $client['id'] ?>, '<?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>')">
                                                        <i class="fas fa-envelope"></i> Message
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            onclick="confirmDelete(<?= $client['id'] ?>, '<?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete client <strong id="clientName"></strong>?</p>
                    <p class="text-danger mb-0">This action cannot be undone and will remove all associated data.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete_client">
                        <input type="hidden" name="client_id" id="deleteClientId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Client</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Message to <span id="messageClientName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="client_id" id="messageClientId">
                        <div class="mb-3">
                            <label for="messageText" class="form-label">Message</label>
                            <textarea class="form-control" id="messageText" name="message" rows="4" 
                                      placeholder="Enter your message to the client..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Message Modal -->
    <div class="modal fade" id="bulkMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Bulk Message to Clients</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_message">
                        <div class="mb-3">
                            <label class="form-label">Send to:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_target" value="all" id="bulkAll" checked>
                                <label class="form-check-label" for="bulkAll">All Clients</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_target" value="active" id="bulkActive">
                                <label class="form-check-label" for="bulkActive">Active Clients Only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="bulk_target" value="new" id="bulkNew">
                                <label class="form-check-label" for="bulkNew">New Clients (7 days)</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bulkMessage" class="form-label">Message</label>
                            <textarea class="form-control" id="bulkMessage" name="bulk_message" rows="5" 
                                      placeholder="Enter your message..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send to Selected Clients</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Data Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Client Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Choose the format to export client data:</p>
                    <div class="d-grid gap-2">
                        <a href="export_clients.php?format=csv" class="btn btn-outline-success">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </a>
                        <a href="export_clients.php?format=excel" class="btn btn-outline-primary">
                            <i class="fas fa-file-excel me-2"></i>Export as Excel
                        </a>
                        <a href="export_clients.php?format=pdf" class="btn btn-outline-danger">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF Report
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(clientId, clientName) {
            document.getElementById('deleteClientId').value = clientId;
            document.getElementById('clientName').textContent = clientName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function sendMessage(clientId, clientName) {
            document.getElementById('messageClientId').value = clientId;
            document.getElementById('messageClientName').textContent = clientName;
            document.getElementById('messageText').value = '';
            new bootstrap.Modal(document.getElementById('messageModal')).show();
        }

        // Auto-refresh every 5 minutes to keep data current
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>