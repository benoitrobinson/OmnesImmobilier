<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn() || !isAgent()) {
    redirect('../auth/login.php');
}

// ✅ CORRECT way to get database connection
$database = Database::getInstance();
$db = $database->getConnection();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$appointment_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'update_status') {
        $status = $_POST['status'] ?? '';
        $appointment_id = $_POST['appointment_id'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (in_array($status, ['scheduled', 'completed', 'cancelled'])) {
            try {
                $query = "UPDATE appointments SET 
                         status = :status, 
                         updated_at = NOW()
                         WHERE id = :appointment_id AND agent_id = :agent_id";
                $stmt = $db->prepare($query);
                if ($stmt->execute([
                    'status' => $status,
                    'appointment_id' => $appointment_id,
                    'agent_id' => $_SESSION['user_id']
                ])) {
                    $success = 'Appointment status updated successfully!';
                } else {
                    $error = 'Error updating appointment status.';
                }
            } catch (PDOException $e) {
                error_log("Appointment status update error: " . $e->getMessage());
                $error = 'Error updating appointment status.';
            }
        }
    }
    
    elseif ($form_action === 'reschedule') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        $new_date = $_POST['new_date'] ?? '';
        $new_time = $_POST['new_time'] ?? '';
        $new_location = trim($_POST['new_location'] ?? '');
        
        if (!empty($new_date) && !empty($new_time)) {
            try {
                $new_datetime = $new_date . ' ' . $new_time;
                $query = "UPDATE appointments SET 
                         appointment_date = :appointment_date,
                         location = :location,
                         updated_at = NOW()
                         WHERE id = :appointment_id AND agent_id = :agent_id";
                $stmt = $db->prepare($query);
                if ($stmt->execute([
                    'appointment_date' => $new_datetime,
                    'location' => $new_location,
                    'appointment_id' => $appointment_id,
                    'agent_id' => $_SESSION['user_id']
                ])) {
                    $success = 'Appointment rescheduled successfully!';
                } else {
                    $error = 'Error rescheduling appointment.';
                }
            } catch (PDOException $e) {
                error_log("Appointment reschedule error: " . $e->getMessage());
                $error = 'Error rescheduling appointment.';
            }
        } else {
            $error = 'Please provide both date and time for rescheduling.';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$property_filter = $_GET['property'] ?? 'all';

// Build WHERE clause for filters
$where_conditions = ['a.agent_id = :agent_id'];
$params = ['agent_id' => $_SESSION['user_id']];

if ($status_filter !== 'all') {
    $where_conditions[] = 'a.status = :status';
    $params['status'] = $status_filter;
}

if ($date_filter === 'today') {
    $where_conditions[] = 'DATE(a.appointment_date) = CURDATE()';
} elseif ($date_filter === 'week') {
    $where_conditions[] = 'a.appointment_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)';
} elseif ($date_filter === 'upcoming') {
    $where_conditions[] = 'a.appointment_date >= NOW()';
} elseif ($date_filter === 'past') {
    $where_conditions[] = 'a.appointment_date < NOW()';
}

$where_clause = implode(' AND ', $where_conditions);

// Get agent's appointments
$appointments = [];
try {
    $query = "SELECT a.*, 
                     p.title as property_title, 
                     p.address_line1, 
                     p.city, 
                     p.price as property_price,
                     p.property_type,
                     CONCAT(u.first_name, ' ', u.last_name) as client_name,
                     u.phone as client_phone, 
                     u.email as client_email,
                     DATE(a.appointment_date) as appointment_date_only,
                     TIME(a.appointment_date) as appointment_time_only
              FROM appointments a 
              JOIN properties p ON a.property_id = p.id 
              JOIN users u ON a.client_id = u.id 
              WHERE $where_clause
              ORDER BY a.appointment_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Appointments fetch error: " . $e->getMessage());
    $appointments = [];
}

// Get appointment statistics
$stats = [
    'total_appointments' => 0,
    'scheduled_appointments' => 0,
    'completed_appointments' => 0,
    'cancelled_appointments' => 0,
    'today_appointments' => 0,
    'upcoming_appointments' => 0
];

try {
    $stats_query = "SELECT 
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_appointments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                    SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today_appointments,
                    SUM(CASE WHEN appointment_date >= NOW() AND status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments
                    FROM appointments WHERE agent_id = :agent_id";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $appointment_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment_stats) {
        $stats = array_merge($stats, $appointment_stats);
    }
    
} catch (Exception $e) {
    error_log("Appointment stats error: " . $e->getMessage());
}

// Get specific appointment for details/editing
$appointment = null;
if ($action === 'view' && $appointment_id) {
    try {
        $query = "SELECT a.*, 
                         p.title as property_title, 
                         p.address_line1, 
                         p.address_line2,
                         p.city, 
                         p.state,
                         p.postal_code,
                         p.price as property_price,
                         p.property_type,
                         p.description as property_description,
                         CONCAT(u.first_name, ' ', u.last_name) as client_name,
                         u.phone as client_phone, 
                         u.email as client_email,
                         u.first_name as client_first_name,
                         u.last_name as client_last_name
                  FROM appointments a 
                  JOIN properties p ON a.property_id = p.id 
                  JOIN users u ON a.client_id = u.id 
                  WHERE a.id = :appointment_id AND a.agent_id = :agent_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['appointment_id' => $appointment_id, 'agent_id' => $_SESSION['user_id']]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            $error = 'Appointment not found.';
            $action = 'list';
        }
    } catch (Exception $e) {
        error_log("Appointment fetch error: " . $e->getMessage());
        $error = 'Error loading appointment.';
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management - Agent Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Navigation CSS -->
    <link href="../assets/css/agent_navigation.css" rel="stylesheet">
    
    <style>
        :root {
            --agent-primary: #2c5aa0;
            --agent-secondary: #4a90e2;
            --agent-success: #28a745;
            --agent-warning: #ffc107;
            --agent-danger: #dc3545;
            --agent-info: #17a2b8;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-top: 80px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stats-value.primary { color: var(--agent-primary); }
        .stats-value.success { color: var(--agent-success); }
        .stats-value.warning { color: var(--agent-warning); }
        .stats-value.danger { color: var(--agent-danger); }
        .stats-value.info { color: var(--agent-info); }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .content-card-body {
            padding: 2rem;
        }

        .appointment-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .appointment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-scheduled { background: var(--agent-info); color: white; }
        .status-completed { background: var(--agent-success); color: white; }
        .status-cancelled { background: var(--agent-danger); color: white; }

        .appointment-date {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            color: white;
            padding: 1rem;
            text-align: center;
            min-width: 100px;
        }

        .appointment-date .day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .appointment-date .month {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .appointment-date .time {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            opacity: 0.9;
        }

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

        .form-control-custom {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--agent-primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
            background: white;
        }

        .client-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        .property-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-tabs {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-tabs .nav-pills .nav-link {
            border-radius: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-tabs .nav-pills .nav-link.active {
            background: var(--agent-primary);
        }

        .upcoming-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .past-badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .appointment-card .row {
                flex-direction: column;
            }
            
            .appointment-date {
                margin-bottom: 1rem;
                text-align: center;
            }
            
            .appointment-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body data-user-role="agent">
    <!-- Include Navigation -->
    <?php include '../includes/agent_navigation.php'; ?>

    <div class="container mt-4">

        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-alt me-3"></i>
                        <?php if ($action === 'view'): ?>
                            Appointment Details
                        <?php else: ?>
                            Appointments Management
                        <?php endif; ?>
                    </h1>
                    <p class="mb-0 opacity-90">
                        <?php if ($action === 'list'): ?>
                            Manage your client appointments and schedule meetings
                        <?php else: ?>
                            View and manage appointment details
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($action === 'view'): ?>
                        <a href="manage_appointments.php" class="btn btn-light">
                            <i class="fas fa-list me-2"></i>Back to List
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-light">
                            <i class="fas fa-chart-pie me-2"></i>Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-value primary"><?= $stats['total_appointments'] ?></div>
                        <div class="stats-label">Total</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-value info"><?= $stats['scheduled_appointments'] ?></div>
                        <div class="stats-label">Scheduled</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-value success"><?= $stats['completed_appointments'] ?></div>
                        <div class="stats-label">Completed</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-value danger"><?= $stats['cancelled_appointments'] ?></div>
                        <div class="stats-label">Cancelled</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-value warning"><?= $stats['today_appointments'] ?></div>
                        <div class="stats-label">Today</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-3">
                    <div class="stats-card">
                        <div class="stats-value success"><?= $stats['upcoming_appointments'] ?></div>
                        <div class="stats-label">Upcoming</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-tabs">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <ul class="nav nav-pills">
                            <li class="nav-item">
                                <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" 
                                   href="?status=all&date=<?= $date_filter ?>">All</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $status_filter === 'scheduled' ? 'active' : '' ?>" 
                                   href="?status=scheduled&date=<?= $date_filter ?>">Scheduled</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $status_filter === 'completed' ? 'active' : '' ?>" 
                                   href="?status=completed&date=<?= $date_filter ?>">Completed</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $status_filter === 'cancelled' ? 'active' : '' ?>" 
                                   href="?status=cancelled&date=<?= $date_filter ?>">Cancelled</a>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" onchange="window.location.href='?status=<?= $status_filter ?>&date=' + this.value">
                            <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Dates</option>
                            <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
                            <option value="upcoming" <?= $date_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                            <option value="past" <?= $date_filter === 'past' ? 'selected' : '' ?>>Past</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Appointments List -->
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $apt): ?>
                    <div class="appointment-card">
                        <div class="row g-0">
                            <!-- Date Column -->
                            <div class="col-md-2">
                                <div class="appointment-date">
                                    <div class="day"><?= date('d', strtotime($apt['appointment_date'])) ?></div>
                                    <div class="month"><?= date('M Y', strtotime($apt['appointment_date'])) ?></div>
                                    <div class="time"><?= date('H:i', strtotime($apt['appointment_date'])) ?></div>
                                </div>
                            </div>
                            
                            <!-- Content Column -->
                            <div class="col-md-7">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-1"><?= htmlspecialchars($apt['property_title']) ?></h5>
                                        <div class="appointment-status status-<?= $apt['status'] ?>">
                                            <?= ucfirst($apt['status']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="client-info">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong><i class="fas fa-user me-2"></i><?= htmlspecialchars($apt['client_name']) ?></strong>
                                            </div>
                                            <div class="col-md-6">
                                                <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($apt['client_email']) ?>
                                            </div>
                                        </div>
                                        <div class="row mt-1">
                                            <div class="col-md-6">
                                                <i class="fas fa-phone me-2"></i><?= htmlspecialchars($apt['client_phone']) ?>
                                            </div>
                                            <div class="col-md-6">
                                                <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($apt['address_line1'] . ', ' . $apt['city']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="property-info">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Property:</strong> <?= ucfirst($apt['property_type']) ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Price:</strong> €<?= number_format($apt['property_price'], 0, ',', ' ') ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($apt['location'])): ?>
                                        <div class="mt-1">
                                            <strong>Meeting Location:</strong> <?= htmlspecialchars($apt['location']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php
                                    $is_upcoming = strtotime($apt['appointment_date']) > time();
                                    $is_today = date('Y-m-d', strtotime($apt['appointment_date'])) === date('Y-m-d');
                                    ?>
                                    
                                    <?php if ($is_today): ?>
                                        <span class="upcoming-badge me-2">
                                            <i class="fas fa-calendar-day me-1"></i>Today
                                        </span>
                                    <?php elseif ($is_upcoming): ?>
                                        <span class="upcoming-badge me-2">
                                            <i class="fas fa-clock me-1"></i>Upcoming
                                        </span>
                                    <?php else: ?>
                                        <span class="past-badge me-2">
                                            <i class="fas fa-history me-1"></i>Past
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Actions Column -->
                            <div class="col-md-3">
                                <div class="p-3 h-100 d-flex flex-column justify-content-center">
                                    <div class="appointment-actions">
                                        <a href="?action=view&id=<?= $apt['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($apt['status'] === 'scheduled'): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="fas fa-check me-2"></i>Mark Completed
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="fas fa-times me-2"></i>Cancel
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <a href="tel:<?= htmlspecialchars($apt['client_phone']) ?>" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                        
                                        <a href="mailto:<?= htmlspecialchars($apt['client_email']) ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="content-card">
                    <div class="content-card-body text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Appointments Found</h4>
                        <p class="text-muted mb-4">
                            <?php if ($status_filter !== 'all' || $date_filter !== 'all'): ?>
                                No appointments match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                You don't have any appointments yet. Clients will be able to book appointments with you for property viewings.
                            <?php endif; ?>
                        </p>
                        <?php if ($status_filter !== 'all' || $date_filter !== 'all'): ?>
                            <a href="manage_appointments.php" class="btn-agent-primary">
                                <i class="fas fa-filter"></i>Clear Filters
                            </a>
                        <?php else: ?>
                            <a href="manage_properties.php" class="btn-agent-primary">
                                <i class="fas fa-plus"></i>Add Properties
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'view' && $appointment): ?>
            <!-- Appointment Details View -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-calendar-alt me-2"></i>Appointment Details
                        </div>
                        <div class="content-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3">
                                        <?= htmlspecialchars($appointment['property_title']) ?>
                                        <span class="appointment-status status-<?= $appointment['status'] ?> ms-2">
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                    </h5>
                                    
                                    <div class="mb-3">
                                        <strong><i class="fas fa-calendar me-2"></i>Date & Time:</strong><br>
                                        <?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?><br>
                                        <strong><?= date('g:i A', strtotime($appointment['appointment_date'])) ?></strong>
                                    </div>
                                    
                                    <?php if (!empty($appointment['location'])): ?>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-map-marker-alt me-2"></i>Meeting Location:</strong><br>
                                        <?= htmlspecialchars($appointment['location']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="client-info">
                                        <h6><i class="fas fa-user me-2"></i>Client Information</h6>
                                        <p class="mb-2">
                                            <strong>Name:</strong> <?= htmlspecialchars($appointment['client_name']) ?><br>
                                            <strong>Email:</strong> 
                                            <a href="mailto:<?= htmlspecialchars($appointment['client_email']) ?>">
                                                <?= htmlspecialchars($appointment['client_email']) ?>
                                            </a><br>
                                            <strong>Phone:</strong> 
                                            <a href="tel:<?= htmlspecialchars($appointment['client_phone']) ?>">
                                                <?= htmlspecialchars($appointment['client_phone']) ?>
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="property-info mt-4">
                                <h6><i class="fas fa-home me-2"></i>Property Details</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p>
                                            <strong>Type:</strong> <?= ucfirst($appointment['property_type']) ?><br>
                                            <strong>Price:</strong> €<?= number_format($appointment['property_price'], 0, ',', ' ') ?><br>
                                            <strong>Address:</strong> <?= htmlspecialchars($appointment['address_line1']) ?>
                                            <?php if ($appointment['address_line2']): ?>
                                                <br><?= htmlspecialchars($appointment['address_line2']) ?>
                                            <?php endif; ?>
                                            <br><?= htmlspecialchars($appointment['city']) ?>
                                            <?php if ($appointment['state']): ?>
                                                , <?= htmlspecialchars($appointment['state']) ?>
                                            <?php endif; ?>
                                            <?php if ($appointment['postal_code']): ?>
                                                <?= htmlspecialchars($appointment['postal_code']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($appointment['property_description']): ?>
                                        <p>
                                            <strong>Description:</strong><br>
                                            <?= htmlspecialchars(substr($appointment['property_description'], 0, 200)) ?>
                                            <?= strlen($appointment['property_description']) > 200 ? '...' : '' ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="mt-4 pt-3 border-top">
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check me-2"></i>Mark as Completed
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                                            <i class="fas fa-calendar-alt me-2"></i>Reschedule
                                        </button>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="fas fa-times me-2"></i>Cancel Appointment
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="tel:<?= htmlspecialchars($appointment['client_phone']) ?>" class="btn btn-outline-success">
                                        <i class="fas fa-phone me-2"></i>Call Client
                                    </a>
                                    
                                    <a href="mailto:<?= htmlspecialchars($appointment['client_email']) ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-envelope me-2"></i>Email Client
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </div>
                        <div class="content-card-body">
                            <div class="d-grid gap-3">
                                <a href="manage_appointments.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>All Appointments
                                </a>
                                <a href="manage_properties.php?action=edit&id=<?= $appointment['property_id'] ?>" class="btn btn-outline-success">
                                    <i class="fas fa-home me-2"></i>Edit Property
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-chart-pie me-2"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Timeline -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-history me-2"></i>Timeline
                        </div>
                        <div class="content-card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <i class="fas fa-plus-circle text-primary"></i>
                                    <div>
                                        <strong>Appointment Created</strong><br>
                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($appointment['created_at'])) ?></small>
                                    </div>
                                </div>
                                
                                <?php if ($appointment['updated_at'] !== $appointment['created_at']): ?>
                                <div class="timeline-item">
                                    <i class="fas fa-edit text-warning"></i>
                                    <div>
                                        <strong>Last Updated</strong><br>
                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($appointment['updated_at'])) ?></small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="timeline-item">
                                    <i class="fas fa-calendar text-info"></i>
                                    <div>
                                        <strong>Scheduled for</strong><br>
                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($appointment['appointment_date'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reschedule Modal -->
            <?php if ($appointment['status'] === 'scheduled'): ?>
            <div class="modal fade" id="rescheduleModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">Reschedule Appointment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="reschedule">
                                <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">New Date</label>
                                    <input type="date" class="form-control form-control-custom" name="new_date" 
                                           value="<?= date('Y-m-d', strtotime($appointment['appointment_date'])) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Time</label>
                                    <input type="time" class="form-control form-control-custom" name="new_time" 
                                           value="<?= date('H:i', strtotime($appointment['appointment_date'])) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Meeting Location</label>
                                    <input type="text" class="form-control form-control-custom" name="new_location" 
                                           value="<?= htmlspecialchars($appointment['location']) ?>" 
                                           placeholder="Property address or meeting location">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Reschedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/navigation.js"></script>

    <style>
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            display: flex;
            align-items-flex-start;
            gap: 1rem;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 0.6rem;
            top: 2rem;
            width: 2px;
            height: calc(100% - 1rem);
            background: #e9ecef;
        }

        .timeline-item i {
            flex-shrink: 0;
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
    </style>
    <script src="../assets/js/agent_navigation.js"></script>

</body>
</html>