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

// Get filter parameters
$filter_role = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query with filters - Let's simplify to test
$query = "
    SELECT u.*
    FROM users u
    WHERE u.role IN ('agent', 'client')
";

$params = [];

// Apply filters
if ($filter_role) {
    $query .= " AND u.role = :role";
    $params['role'] = $filter_role;
}

if ($search) {
    $query .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE :search OR u.email LIKE :search2)";
    $params['search'] = "%$search%";
    $params['search2'] = "%$search%";
}

// Apply sorting
switch ($sort) {
    case 'name-asc':
        $query .= " ORDER BY u.first_name ASC, u.last_name ASC";
        break;
    case 'name-desc':
        $query .= " ORDER BY u.first_name DESC, u.last_name DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY u.created_at ASC";
        break;
    default:
        $query .= " ORDER BY u.created_at DESC";
}

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Let's see exactly what the query returned
    $debug_query_result = count($users);
    $debug_first_user = !empty($users) ? $users[0] : null;
    
    // Get statistics - Fixed to use correct database connection
    $stats_query = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'agent' THEN 1 ELSE 0 END) as total_agents,
            SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as total_clients,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as new_this_month
        FROM users 
        WHERE role IN ('agent', 'client')
    ";
    $stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Users fetch error: " . $e->getMessage());
    $users = [];
    $stats = ['total_users' => 0, 'total_agents' => 0, 'total_clients' => 0, 'new_this_month' => 0];
    $debug_query_result = "Error: " . $e->getMessage();
    $debug_first_user = null;
}

// Debug: Check if we have any users in the database at all
try {
    $debug_query = "SELECT COUNT(*) as total FROM users";
    $debug_result = $db->query($debug_query)->fetch(PDO::FETCH_ASSOC);
    $total_users_in_db = $debug_result['total'];
    
    // Additional debug: Check what roles exist
    $roles_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $roles_result = $db->query($roles_query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if agents table exists and has data
    $agents_check = $db->query("SELECT COUNT(*) as count FROM agents")->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $total_users_in_db = 0;
    $roles_result = [];
    $agents_check = ['count' => 0];
    error_log("Debug queries error: " . $e->getMessage());
}

// Debug: Let's also check what the actual query returns
try {
    $debug_users_query = "SELECT u.*, u.role FROM users u WHERE 1=1 LIMIT 5";
    $debug_users = $db->query($debug_users_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $debug_users = [];
    error_log("Debug users query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage All Users - Admin Panel</title>
    
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

        .user-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-agent { 
            background: #d4edda; 
            color: #155724; 
        }
        
        .role-client { 
            background: #cce5ff; 
            color: #004085; 
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .filter-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
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
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <h1 class="h4 mb-0">
                    <i class="fas fa-users-cog me-2"></i>Manage All Users
                </h1>
                <p class="mb-0 text-muted">Showing <?= count($users) ?> of <?= $total_users_in_db ?> total users</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <div class="stat-mini-value"><?= $stats['total_users'] ?? 0 ?></div>
                <div class="stat-mini-label">Total Users</div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value"><?= $stats['total_agents'] ?? 0 ?></div>
                <div class="stat-mini-label">Agents</div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value"><?= $stats['total_clients'] ?? 0 ?></div>
                <div class="stat-mini-label">Clients</div>
            </div>
            <div class="stat-mini-card">
                <div class="stat-mini-value"><?= $stats['new_this_month'] ?? 0 ?></div>
                <div class="stat-mini-label">New This Month</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="agent" <?= $filter_role === 'agent' ? 'selected' : '' ?>>Agents</option>
                            <option value="client" <?= $filter_role === 'client' ? 'selected' : '' ?>>Clients</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="sort" class="form-select">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="name-asc" <?= $sort === 'name-asc' ? 'selected' : '' ?>>Name A-Z</option>
                            <option value="name-desc" <?= $sort === 'name-desc' ? 'selected' : '' ?>>Name Z-A</option>
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

        <!-- Users List -->
        <div class="row">
            <?php if (empty($users)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No users found</h5>
                        <p class="text-muted">Try adjusting your search criteria or filters.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-12">
                    <h6 class="mb-3">Found <?= count($users) ?> user(s)</h6>
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <div class="row align-items-center">
                                <div class="col-md-1">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <h6 class="mb-1"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h6>
                                    <p class="mb-1 text-muted">
                                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['email'] ?? '') ?>
                                    </p>
                                    <?php if (!empty($user['phone'])): ?>
                                        <p class="mb-0 text-muted small">
                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($user['phone']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2">
                                    <span class="role-badge role-<?= $user['role'] ?? 'client' ?>">
                                        <?= ucfirst($user['role'] ?? 'Unknown') ?>
                                    </span>
                                </div>
                                
                                <div class="col-md-3">
                                    <?php if (($user['role'] ?? '') === 'agent'): ?>
                                        <p class="mb-1 text-muted">
                                            <i class="fas fa-user-tie me-1"></i>Real Estate Agent
                                        </p>
                                    <?php else: ?>
                                        <p class="mb-1 text-muted">
                                            <i class="fas fa-user me-1"></i>Property Client
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Joined: <?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?>
                                    </small>
                                </div>
                                
                                <div class="col-md-1">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if (($user['role'] ?? '') === 'agent'): ?>
                                                <li><a class="dropdown-item" href="view_agent.php?id=<?= $user['id'] ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a></li>
                                                <li><a class="dropdown-item" href="edit_agent.php?id=<?= $user['id'] ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                            <?php else: ?>
                                                <li><a class="dropdown-item" href="view_client.php?id=<?= $user['id'] ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
