<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$admin_data = getCurrentUser();

$database = Database::getInstance();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle property actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_property' && isset($_POST['property_id'])) {
        try {
            $property_id = (int)$_POST['property_id'];
            
            // Check if property has active appointments
            $check_stmt = $db->prepare("
                SELECT COUNT(*) as active_appointments
                FROM appointments 
                WHERE property_id = :property_id 
                AND status = 'scheduled' 
                AND appointment_date >= NOW()
            ");
            $check_stmt->execute(['property_id' => $property_id]);
            $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($check['active_appointments'] > 0) {
                $error = "Cannot delete property. It has {$check['active_appointments']} scheduled appointments.";
            } else {
                // Delete property
                $delete_stmt = $db->prepare("DELETE FROM properties WHERE id = :property_id");
                $delete_stmt->execute(['property_id' => $property_id]);
                $success = 'Property deleted successfully.';
            }
        } catch (Exception $e) {
            error_log("Delete property error: " . $e->getMessage());
            $error = 'Error deleting property.';
        }
    }
    
    elseif ($action === 'change_status' && isset($_POST['property_id'])) {
        try {
            $property_id = (int)$_POST['property_id'];
            $new_status = $_POST['new_status'] ?? 'available';
            
            if (in_array($new_status, ['available', 'sold', 'rented'])) {
                $update_stmt = $db->prepare("
                    UPDATE properties 
                    SET status = :status, updated_at = NOW() 
                    WHERE id = :property_id
                ");
                $update_stmt->execute([
                    'status' => $new_status,
                    'property_id' => $property_id
                ]);
                $success = 'Property status updated successfully.';
            }
        } catch (Exception $e) {
            error_log("Status update error: " . $e->getMessage());
            $error = 'Error updating property status.';
        }
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_agent = $_GET['agent'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query with filters
$query = "
    SELECT 
        p.*,
        CONCAT(u.first_name, ' ', u.last_name) as agent_name,
        u.email as agent_email,
        COUNT(DISTINCT a.id) as appointment_count,
        COUNT(DISTINCT CASE WHEN a.status = 'scheduled' AND a.appointment_date >= NOW() THEN a.id END) as active_appointments
    FROM properties p
    INNER JOIN users u ON p.agent_id = u.id
    LEFT JOIN appointments a ON p.id = a.property_id
    WHERE 1=1
";

$params = [];

// Apply filters
if ($filter_type) {
    $query .= " AND p.property_type = :type";
    $params['type'] = $filter_type;
}

if ($filter_status) {
    $query .= " AND p.status = :status";
    $params['status'] = $filter_status;
}

if ($filter_agent) {
    $query .= " AND p.agent_id = :agent_id";
    $params['agent_id'] = $filter_agent;
}

if ($search) {
    $query .= " AND (p.title LIKE :search OR p.address_line1 LIKE :search2 OR p.city LIKE :search3)";
    $params['search'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

$query .= " GROUP BY p.id";

// Apply sorting
switch ($sort) {
    case 'price-high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'price-low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'oldest':
        $query .= " ORDER BY p.created_at ASC";
        break;
    default:
        $query .= " ORDER BY p.created_at DESC";
}

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get agents for filter dropdown - updated to use agents table
    $agents_stmt = $db->query("
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
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold,
            SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented,
            AVG(price) as avg_price,
            SUM(CASE WHEN status = 'sold' THEN price ELSE 0 END) as total_sales_value
        FROM properties
    ";
    $stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Properties fetch error: " . $e->getMessage());
    $error = 'Error loading properties.';
    $properties = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Properties - Omnes Real Estate</title>
    
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

        .property-table {
            font-size: 0.9rem;
        }

        .property-image-small {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-sold {
            background: #f8d7da;
            color: #721c24;
        }

        .status-rented {
            background: #cce5ff;
            color: #004085;
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
                        <a href="manage_properties.php" class="nav-link-admin active">
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
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="h4 mb-0">
                                <i class="fas fa-home me-2"></i>Manage Properties
                            </h1>
                            <a href="add_property.php" class="btn-admin-primary">
                                <i class="fas fa-plus me-2"></i>Add New Property
                            </a>
                        </div>
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
                        <div class="stat-mini-label">Total Properties</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['available'] ?? 0 ?></div>
                        <div class="stat-mini-label">Available</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['sold'] ?? 0 ?></div>
                        <div class="stat-mini-label">Sold</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value"><?= $stats['rented'] ?? 0 ?></div>
                        <div class="stat-mini-label">Rented</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value">€<?= number_format($stats['avg_price'] ?? 0, 0, ',', ' ') ?></div>
                        <div class="stat-mini-label">Avg Price</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-value">€<?= number_format($stats['total_sales_value'] ?? 0, 0, ',', ' ') ?></div>
                        <div class="stat-mini-label">Total Sales</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search properties..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="house" <?= $filter_type === 'house' ? 'selected' : '' ?>>House</option>
                                    <option value="apartment" <?= $filter_type === 'apartment' ? 'selected' : '' ?>>Apartment</option>
                                    <option value="commercial" <?= $filter_type === 'commercial' ? 'selected' : '' ?>>Commercial</option>
                                    <option value="land" <?= $filter_type === 'land' ? 'selected' : '' ?>>Land</option>
                                    <option value="rental" <?= $filter_type === 'rental' ? 'selected' : '' ?>>Rental</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="available" <?= $filter_status === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="sold" <?= $filter_status === 'sold' ? 'selected' : '' ?>>Sold</option>
                                    <option value="rented" <?= $filter_status === 'rented' ? 'selected' : '' ?>>Rented</option>
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
                                <select name="sort" class="form-select">
                                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                    <option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price High to Low</option>
                                    <option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price Low to High</option>
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

                <!-- Properties Table -->
                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table property-table align-middle">
                            <thead>
                                <tr>
                                    <th>Property</th>
                                    <th>Agent</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Appointments</th>
                                    <th>Listed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($properties)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-home fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No properties found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($properties as $property): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="property-image-small me-3">
                                                        <?= $property['property_type'] === 'apartment' ? '🏠' : ($property['property_type'] === 'commercial' ? '🏢' : '🏘️') ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($property['title']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($property['city']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($property['agent_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($property['agent_email']) ?></small>
                                            </td>
                                            <td><?= ucfirst($property['property_type']) ?></td>
                                            <td class="fw-semibold">€<?= number_format($property['price'], 0, ',', ' ') ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $property['status'] ?>">
                                                    <?= ucfirst($property['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $property['appointment_count'] ?> total</span>
                                                <?php if ($property['active_appointments'] > 0): ?>
                                                    <span class="badge bg-warning"><?= $property['active_appointments'] ?> active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('M d, Y', strtotime($property['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit_property.php?id=<?= $property['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view_property.php?id=<?= $property['id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <!-- Status Change Dropdown -->
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php foreach (['available', 'sold', 'rented'] as $status): ?>
                                                                <?php if ($status !== $property['status']): ?>
                                                                    <li>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="action" value="change_status">
                                                                            <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
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
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete(<?= $property['id'] ?>, '<?= htmlspecialchars(addslashes($property['title'])) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                    <p>Are you sure you want to delete property <strong id="propertyTitle"></strong>?</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete_property">
                        <input type="hidden" name="property_id" id="deletePropertyId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Property</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(propertyId, propertyTitle) {
            document.getElementById('deletePropertyId').value = propertyId;
            document.getElementById('propertyTitle').textContent = propertyTitle;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>