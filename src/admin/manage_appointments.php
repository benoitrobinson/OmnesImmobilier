<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

// Get admin information
$admin_data = getCurrentUser();

$database = Database::getInstance();
$pdo = $database->getConnection();

$success = '';
$error = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel_appointment' && isset($_POST['appointment_id'])) {
        try {
            $appointment_id = (int)$_POST['appointment_id'];
            
            $update_stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = :appointment_id
            ");
            $update_stmt->execute(['appointment_id' => $appointment_id]);
            $success = 'Appointment cancelled successfully.';
        } catch (Exception $e) {
            error_log("Cancel appointment error: " . $e->getMessage());
            $error = 'Error cancelling appointment.';
        }
    }
    
    elseif ($action === 'update_status' && isset($_POST['appointment_id'])) {
        try {
            $appointment_id = (int)$_POST['appointment_id'];
            $new_status = $_POST['new_status'] ?? 'scheduled';
            
            if (in_array($new_status, ['scheduled', 'completed', 'cancelled'])) {
                $update_stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = :status, updated_at = NOW() 
                    WHERE id = :appointment_id
                ");
                $update_stmt->execute([
                    'status' => $new_status,
                    'appointment_id' => $appointment_id
                ]);
                $success = 'Appointment status updated successfully.';
            }
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            $error = 'Error updating appointment status.';
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_agent = $_GET['agent'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query with filters
$query = "
    SELECT 
        a.*,
        p.title as property_title,
        p.address_line1,
        p.city,
        p.price as property_price,
        CONCAT(agent.first_name, ' ', agent.last_name) as agent_name,
        agent_details.agency_name,
        agent_user.email as agent_email,
        agent_user.phone as agent_phone,
        CONCAT(client.first_name, ' ', client.last_name) as client_name,
        client.email as client_email,
        client.phone as client_phone
    FROM appointments a
    INNER JOIN properties p ON a.property_id = p.id
    INNER JOIN agents agent_details ON a.agent_id = agent_details.user_id
    INNER JOIN users agent_user ON agent_details.user_id = agent_user.id
    INNER JOIN users agent ON a.agent_id = agent.id
    INNER JOIN users client ON a.client_id = client.id
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter_status) {
    $query .= " AND a.status = :status";
    $params['status'] = $filter_status;
}

if ($filter_agent) {
    $query .= " AND a.agent_id = :agent_id";
    $params['agent_id'] = $filter_agent;
}

if ($filter_date) {
    $query .= " AND DATE(a.appointment_date) = :filter_date";
    $params['filter_date'] = $filter_date;
}

if ($search) {
    $query .= " AND (p.title LIKE :search OR CONCAT(client.first_name, ' ', client.last_name) LIKE :search2 OR CONCAT(agent.first_name, ' ', agent.last_name) LIKE :search3)";
    $params['search'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

// Apply sorting
switch ($sort) {
    case 'date-asc':
        $query .= " ORDER BY a.appointment_date ASC";
        break;
    case 'date-desc':
        $query .= " ORDER BY a.appointment_date DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY a.created_at ASC";
        break;
    default:
        $query .= " ORDER BY a.created_at DESC";
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get agents for filter dropdown
    $agents_stmt = $pdo->query("
        SELECT a.user_id as id, CONCAT(a.first_name, ' ', a.last_name) as name 
        FROM agents a 
        INNER JOIN users u ON a.user_id = u.id
        WHERE u.role = 'agent'
        ORDER BY a.first_name, a.last_name
    ");
    $agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'scheduled' AND appointment_date >= NOW() THEN 1 ELSE 0 END) as upcoming
        FROM appointments
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Appointments fetch error: " . $e->getMessage());
    $error = 'Error loading appointments.';
    $appointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Omnes Real Estate</title>
    
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

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
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
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-mini-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-mini-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat-mini-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--admin-primary);
        }

        .stat-mini-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .appointment-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .appointment-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-scheduled { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .filter-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <!-- Include shared admin header -->
    <?php include '../includes/admin_header.php'; ?>
    
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
                        <a href="manage_clients.php" class="nav-link-admin">
                            <i class="fas fa-users me-3"></i>Manage Clients
                        </a>
                        <a href="manage_agents.php" class="nav-link-admin">
                            <i class="fas fa-user-tie me-3"></i>Manage Agents
                        </a>
                        <a href="manage_properties.php" class="nav-link-admin">
                            <i class="fas fa-home me-3"></i>Manage Properties
                        </a>
                        <a href="manage_appointments.php" class="nav-link-admin active">
                            <i class="fas fa-calendar-alt me-3"></i>Appointments
                        </a>
                        <a href="analytics.php" class="nav-link-admin">
                            <i class="fas fa-chart-bar me-3"></i>Analytics & Reports
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main content -->
            <div class="col-lg-9">
                <!-- Page Header -->
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h1 class="h4 mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Manage Appointments
                        </h1>
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

                <!-- Statistics -->
                <div class="stats-mini">
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['total'] ?? 0 ?></div>
                        <div class="stat-mini-label">Total Appointments</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['scheduled'] ?? 0 ?></div>
                        <div class="stat-mini-label">Scheduled</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['completed'] ?? 0 ?></div>
                        <div class="stat-mini-label">Completed</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['cancelled'] ?? 0 ?></div>
                        <div class="stat-mini-label">Cancelled</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['upcoming'] ?? 0 ?></div>
                        <div class="stat-mini-label">Upcoming</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search appointments..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?= $filter_status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="agent" class="form-select">
                                    <option value="">All Agents</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['id'] ?>" <?= $filter_agent == $agent['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($agent['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="sort" class="form-select">
                                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                    <option value="date-asc" <?= $sort === 'date-asc' ? 'selected' : '' ?>>Date (Earliest)</option>
                                    <option value="date-desc" <?= $sort === 'date-desc' ? 'selected' : '' ?>>Date (Latest)</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Appointments List -->
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No appointments found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($appointment['property_title']) ?></h5>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?= htmlspecialchars($appointment['address_line1'] . ', ' . $appointment['city']) ?>
                                            </p>
                                        </div>
                                        <span class="status-badge status-<?= $appointment['status'] ?>">
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-user me-2"></i>Client</h6>
                                            <p class="mb-1"><?= htmlspecialchars($appointment['client_name']) ?></p>
                                            <p class="mb-1 small text-muted">
                                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($appointment['client_email']) ?>
                                            </p>
                                            <?php if (!empty($appointment['client_phone'])): ?>
                                                <p class="mb-0 small text-muted">
                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($appointment['client_phone']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="fas fa-user-tie me-2"></i>Agent</h6>
                                            <p class="mb-1"><?= htmlspecialchars($appointment['agent_name']) ?></p>
                                            <p class="mb-1 small text-muted">
                                                <i class="fas fa-building me-1"></i><?= htmlspecialchars($appointment['agency_name']) ?>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($appointment['agent_email']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="text-end mb-3">
                                        <h6><i class="fas fa-clock me-2"></i>Appointment Details</h6>
                                        <p class="mb-1">
                                            <strong><?= date('F d, Y', strtotime($appointment['appointment_date'])) ?></strong>
                                        </p>
                                        <p class="mb-1"><?= date('H:i', strtotime($appointment['appointment_date'])) ?></p>
                                        <p class="mb-3 small text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($appointment['location']) ?>
                                        </p>
                                    </div>
                                    
                                    <div class="action-buttons justify-content-end">
                                        <!-- Status Change Dropdown -->
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-exchange-alt"></i> Status
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php foreach (['scheduled', 'completed', 'cancelled'] as $status): ?>
                                                    <?php if ($status !== $appointment['status']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                                <input type="hidden" name="new_status" value="<?= $status ?>">
                                                                <button type="submit" class="dropdown-item">
                                                                    Mark as <?= ucfirst($status) ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        
                                        <?php if ($appointment['status'] === 'scheduled'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmCancel(<?= $appointment['id'] ?>, '<?= htmlspecialchars(addslashes($appointment['property_title'])) ?>')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel the appointment for <strong id="appointmentProperty"></strong>?</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="cancel_appointment">
                        <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Cancel Appointment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmCancel(appointmentId, propertyTitle) {
            document.getElementById('cancelAppointmentId').value = appointmentId;
            document.getElementById('appointmentProperty').textContent = propertyTitle;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
    </script>
</body>
</html>
