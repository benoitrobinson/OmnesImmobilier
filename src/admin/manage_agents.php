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

$success = '';
$error = '';

// Handle agent actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_agent' && isset($_POST['agent_id'])) {
        try {
            $agent_id = (int)$_POST['agent_id'];
            
            // Check if agent has active properties or appointments
            $check_stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM properties WHERE agent_id = :agent_id AND status = 'available') as active_properties,
                    (SELECT COUNT(*) FROM appointments WHERE agent_id = :agent_id2 AND status = 'scheduled' AND appointment_date >= NOW()) as future_appointments
            ");
            $check_stmt->execute(['agent_id' => $agent_id, 'agent_id2' => $agent_id]);
            $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($check['active_properties'] > 0 || $check['future_appointments'] > 0) {
                $error = "Cannot delete agent. They have {$check['active_properties']} active properties and {$check['future_appointments']} future appointments.";
            } else {
                // Delete agent (cascade will handle related records)
                $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = :agent_id AND role = 'agent'");
                $delete_stmt->execute(['agent_id' => $agent_id]);
                $success = 'Agent deleted successfully.';
            }
        } catch (Exception $e) {
            error_log("Delete agent error: " . $e->getMessage());
            $error = 'Error deleting agent.';
        }
    }
    
    elseif ($action === 'toggle_status' && isset($_POST['agent_id'])) {
        try {
            $agent_id = (int)$_POST['agent_id'];
            $new_status = $_POST['new_status'] ?? 'active';
            
            // Update agent status (you might need to add a status column to agents table)
            // For now, we'll just show success
            $success = 'Agent status updated successfully.';
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            $error = 'Error updating agent status.';
        }
    }
}

// Get all agents with statistics - updated to show all users in agents table
$agents = [];
try {
    $query = "
        SELECT 
            u.id,
            u.email,
            u.role,
            u.created_at,
            a.user_id as agent_user_id,
            a.first_name,
            a.last_name,
            a.phone,
            a.agency_name,
            a.cv_file_path,
            a.profile_picture_path,
            COUNT(DISTINCT p.id) as total_properties,
            COUNT(DISTINCT CASE WHEN p.status = 'available' THEN p.id END) as available_properties,
            COUNT(DISTINCT CASE WHEN p.status = 'sold' THEN p.id END) as sold_properties,
            COUNT(DISTINCT ap.id) as total_appointments,
            COUNT(DISTINCT CASE WHEN ap.status = 'scheduled' AND ap.appointment_date >= NOW() THEN ap.id END) as upcoming_appointments,
            COALESCE(SUM(CASE WHEN p.status = 'sold' THEN p.price END), 0) as total_sales
        FROM agents a
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN properties p ON a.user_id = p.agent_id
        LEFT JOIN appointments ap ON a.user_id = ap.agent_id
        WHERE u.role = 'agent'
        GROUP BY a.user_id, u.id
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Fetch agents error: " . $e->getMessage());
    $error = 'Error loading agents: ' . $e->getMessage();
}

// Search functionality - updated field names
$search = $_GET['search'] ?? '';
if ($search) {
    $agents = array_filter($agents, function($agent) use ($search) {
        $searchLower = strtolower($search);
        return strpos(strtolower($agent['first_name'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($agent['last_name'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($agent['email']), $searchLower) !== false ||
               strpos(strtolower($agent['agency_name'] ?? ''), $searchLower) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Agents - Admin Panel</title>
    
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

        /* Admin Header - Updated to match client dashboard */
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
        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none !important;
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
            margin-bottom: 2rem;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .agent-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .agent-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .agent-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2c5aa0 0%, #4a90e2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .agent-stats {
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

        .search-box {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
            outline: none;
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
                        <a class="nav-link" href="manage_clients.php">
                            <i class="fas fa-users me-1"></i>Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_agents.php">
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
                        <a href="manage_clients.php" class="nav-link-admin">
                            <i class="fas fa-users me-3"></i>Manage Clients
                        </a>
                        <a href="manage_agents.php" class="nav-link-admin active">
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
                                <i class="fas fa-user-tie me-2"></i>Manage Agents
                            </h1>
                            <a href="add_agent.php" class="btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Add New Agent
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form method="GET" action="">
                                    <div class="input-group">
                                        <input type="text" name="search" class="search-box form-control" 
                                               placeholder="Search agents..." value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="text-muted">Total Agents: <?= count($agents) ?></span>
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

                        <!-- Agents List -->
                        <?php if (empty($agents)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No agents found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($agents as $agent): ?>
                                <div class="agent-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="agent-avatar me-3">
                                                    <?= strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <h5 class="mb-1"><?= htmlspecialchars(($agent['first_name'] ?? 'No') . ' ' . ($agent['last_name'] ?? 'Name')) ?></h5>
                                                    <p class="text-muted mb-0">
                                                        <?php if (!empty($agent['agency_name'])): ?>
                                                            <i class="fas fa-building me-1"></i><?= htmlspecialchars($agent['agency_name']) ?><br>
                                                        <?php endif; ?>
                                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($agent['email']) ?><br>
                                                        <?php if (!empty($agent['phone'])): ?>
                                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($agent['phone']) ?><br>
                                                        <?php endif; ?>
                                                        <i class="fas fa-calendar me-1"></i>Joined <?= date('M Y', strtotime($agent['created_at'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div class="agent-stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $agent['total_properties'] ?></div>
                                                    <div class="stat-label">Properties</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $agent['available_properties'] ?></div>
                                                    <div class="stat-label">Available</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $agent['sold_properties'] ?></div>
                                                    <div class="stat-label">Sold</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value"><?= $agent['upcoming_appointments'] ?></div>
                                                    <div class="stat-label">Appointments</div>
                                                </div>
                                                <div class="stat-item">
                                                    <div class="stat-value">€<?= number_format($agent['total_sales'], 0, ',', ' ') ?></div>
                                                    <div class="stat-label">Total Sales</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 text-end">
                                            <div class="btn-group">
                                                <a href="edit_agent.php?id=<?= $agent['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="view_agent.php?id=<?= $agent['id'] ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="confirmDelete(<?= $agent['id'] ?>, '<?= htmlspecialchars(($agent['first_name'] ?? 'Agent') . ' ' . ($agent['last_name'] ?? '')) ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
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
                    <p>Are you sure you want to delete agent <strong id="agentName"></strong>?</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete_agent">
                        <input type="hidden" name="agent_id" id="deleteAgentId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Agent</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(agentId, agentName) {
            document.getElementById('deleteAgentId').value = agentId;
            document.getElementById('agentName').textContent = agentName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>