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

$agent_id = $_GET['id'] ?? 0;
$agent = null;
$error = '';

// Get agent details
try {
    $query = "
        SELECT 
            u.id,
            u.email,
            u.role,
            u.created_at,
            u.updated_at,
            a.user_id as agent_user_id,
            a.first_name,
            a.last_name,
            a.phone,
            a.agency_name,
            a.agency_email,
            a.cv_file_path,
            a.profile_picture_path,
            a.years_experience,
            a.average_rating,
            a.total_sales as agent_total_sales,
            a.total_transactions,
            COUNT(DISTINCT p.id) as total_properties,
            COUNT(DISTINCT CASE WHEN p.status = 'available' THEN p.id END) as available_properties,
            COUNT(DISTINCT CASE WHEN p.status = 'sold' THEN p.id END) as sold_properties,
            COUNT(DISTINCT CASE WHEN p.status = 'pending' THEN p.id END) as pending_properties,
            COUNT(DISTINCT ap.id) as total_appointments,
            COUNT(DISTINCT CASE WHEN ap.status = 'scheduled' AND ap.appointment_date >= NOW() THEN ap.id END) as upcoming_appointments,
            COUNT(DISTINCT CASE WHEN ap.status = 'completed' THEN ap.id END) as completed_appointments,
            COALESCE(SUM(CASE WHEN p.status = 'sold' THEN p.price END), 0) as calculated_total_sales
        FROM agents a
        INNER JOIN users u ON a.user_id = u.id
        LEFT JOIN properties p ON a.user_id = p.agent_id
        LEFT JOIN appointments ap ON a.user_id = ap.agent_id
        WHERE u.id = :agent_id
        GROUP BY a.user_id, u.id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['agent_id' => $agent_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        $error = 'Agent not found.';
    }
} catch (Exception $e) {
    error_log("View agent error: " . $e->getMessage());
    $error = 'Error loading agent details.';
}

// Get recent properties
$recent_properties = [];
if ($agent) {
    try {
        $props_query = "
            SELECT id, title, price, property_type, status, city, created_at
            FROM properties 
            WHERE agent_id = :agent_id 
            ORDER BY created_at DESC 
            LIMIT 5
        ";
        $props_stmt = $pdo->prepare($props_query);
        $props_stmt->execute(['agent_id' => $agent_id]);
        $recent_properties = $props_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Properties fetch error: " . $e->getMessage());
    }
}

// Get recent appointments
$recent_appointments = [];
if ($agent) {
    try {
        $appt_query = "
            SELECT 
                ap.id, ap.appointment_date, ap.status, ap.location,
                p.title as property_title,
                CONCAT(u.first_name, ' ', u.last_name) as client_name
            FROM appointments ap
            LEFT JOIN properties p ON ap.property_id = p.id
            LEFT JOIN users u ON ap.client_id = u.id
            WHERE ap.agent_id = :agent_id 
            ORDER BY ap.appointment_date DESC 
            LIMIT 5
        ";
        $appt_stmt = $pdo->prepare($appt_query);
        $appt_stmt->execute(['agent_id' => $agent_id]);
        $recent_appointments = $appt_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Appointments fetch error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Agent - <?= htmlspecialchars($agent['first_name'] ?? 'Agent') ?> <?= htmlspecialchars($agent['last_name'] ?? '') ?></title>
    
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
            margin-bottom: 2rem;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .agent-profile-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 2rem;
            position: relative;
        }

        .agent-avatar-large {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-primary);
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--admin-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .property-item, .appointment-item {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .property-item:hover, .appointment-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-available { background: #d4edda; color: #155724; }
        .status-sold { background: #d1ecf1; color: #0c5460; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-scheduled { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
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
                <a href="manage_agents.php" class="btn btn-light me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Agents
                </a>
                <?php if ($agent): ?>
                    <a href="edit_agent.php?id=<?= $agent['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>Edit Agent
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($agent): ?>
            <!-- Agent Profile Header -->
            <div class="content-card">
                <div class="agent-profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="agent-avatar-large mx-auto">
                                <?= strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1)) ?>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h1 class="h2 mb-2"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></h1>
                            <p class="h5 mb-3 opacity-75">
                                <i class="fas fa-building me-2"></i><?= htmlspecialchars($agent['agency_name']) ?>
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($agent['email']) ?></p>
                                    <p class="mb-1"><i class="fas fa-phone me-2"></i><?= htmlspecialchars($agent['phone']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><i class="fas fa-calendar me-2"></i>Joined <?= date('F Y', strtotime($agent['created_at'])) ?></p>
                                    <div class="d-flex align-items-center">
                                        <span class="rating-stars me-2">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= $agent['average_rating'] ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <span><?= number_format($agent['average_rating'], 1) ?>/5.0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $agent['total_properties'] ?></div>
                        <div class="stat-label">Total Properties</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $agent['sold_properties'] ?></div>
                        <div class="stat-label">Properties Sold</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-value"><?= $agent['total_appointments'] ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value">€<?= number_format($agent['calculated_total_sales'], 0, ',', ' ') ?></div>
                        <div class="stat-label">Total Sales Value</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?= $agent['years_experience'] ?></div>
                        <div class="stat-label">Years Experience</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Agent Information -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Agent Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <strong>Role</strong><br>
                                    <span class="text-capitalize">Agent</span>
                                    <?php if ($agent['role'] !== 'agent'): ?>
                                        <small class="text-muted">(User role: <?= htmlspecialchars($agent['role']) ?>)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($agent['agency_email'])): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-building"></i></div>
                                    <div>
                                        <strong>Agency Email</strong><br>
                                        <a href="mailto:<?= htmlspecialchars($agent['agency_email']) ?>"><?= htmlspecialchars($agent['agency_email']) ?></a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-chart-line"></i></div>
                                <div>
                                    <strong>Transactions</strong><br>
                                    <?= $agent['total_transactions'] ?> completed
                                </div>
                            </div>

                            <?php if (!empty($agent['cv_file_path'])): ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-file-pdf"></i></div>
                                    <div>
                                        <strong>CV Document</strong><br>
                                        <a href="<?= htmlspecialchars($agent['cv_file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download me-1"></i>Download CV
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="info-item">
                                    <div class="info-icon"><i class="fas fa-file-pdf"></i></div>
                                    <div>
                                        <strong>CV Document</strong><br>
                                        <span class="text-muted">No CV uploaded</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Properties -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="mb-0"><i class="fas fa-home me-2"></i>Recent Properties</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_properties)): ?>
                                <p class="text-muted text-center py-3">No properties found</p>
                            <?php else: ?>
                                <?php foreach ($recent_properties as $property): ?>
                                    <div class="property-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($property['title']) ?></h6>
                                                <p class="mb-1 text-muted small">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($property['city']) ?>
                                                </p>
                                                <p class="mb-0"><strong>€<?= number_format($property['price'], 0, ',', ' ') ?></strong></p>
                                            </div>
                                            <span class="status-badge status-<?= $property['status'] ?>"><?= ucfirst($property['status']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recent Appointments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_appointments)): ?>
                                <p class="text-muted text-center py-3">No appointments found</p>
                            <?php else: ?>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($appointment['property_title']) ?></h6>
                                                <p class="mb-1 text-muted small">
                                                    <i class="fas fa-user me-1"></i><?= htmlspecialchars($appointment['client_name'] ?? 'N/A') ?>
                                                </p>
                                                <p class="mb-0 small">
                                                    <i class="fas fa-clock me-1"></i><?= date('M j, Y H:i', strtotime($appointment['appointment_date'])) ?>
                                                </p>
                                            </div>
                                            <span class="status-badge status-<?= $appointment['status'] ?>"><?= ucfirst($appointment['status']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-user-times fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">Agent Not Found</h3>
                <p class="text-muted">The requested agent could not be found.</p>
                <a href="manage_agents.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Agents
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
