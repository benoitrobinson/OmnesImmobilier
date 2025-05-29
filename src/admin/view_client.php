<?php
// Quick example of view_client.php for detailed client information
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$client_id = (int)($_GET['id'] ?? 0);
$database = Database::getInstance();
$db = $database->getConnection();

// Get detailed client information
try {
    $query = "
        SELECT 
            u.*,
            c.*,
            COUNT(DISTINCT a.id) as total_appointments,
            COUNT(DISTINCT CASE WHEN a.status = 'scheduled' THEN a.id END) as scheduled_appointments,
            COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
            COUNT(DISTINCT m.id) as total_messages
        FROM users u
        LEFT JOIN clients c ON u.id = c.user_id
        LEFT JOIN appointments a ON u.id = a.client_id
        LEFT JOIN messages m ON u.id = m.sender_id
        WHERE u.id = :client_id AND u.role = 'client'
        GROUP BY u.id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['client_id' => $client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        redirect('manage_clients.php?error=Client not found');
    }
    
    // Get recent appointments
    $appointments_query = "
        SELECT a.*, p.title as property_title, 
               CONCAT(agent.first_name, ' ', agent.last_name) as agent_name
        FROM appointments a
        JOIN properties p ON a.property_id = p.id
        JOIN users agent ON a.agent_id = agent.id
        WHERE a.client_id = :client_id
        ORDER BY a.appointment_date DESC
        LIMIT 10
    ";
    
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->execute(['client_id' => $client_id]);
    $appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("View client error: " . $e->getMessage());
    redirect('manage_clients.php?error=Error loading client');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Details - <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Client Details</h1>
            <a href="manage_clients.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Clients
            </a>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="client-avatar mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 2rem;">
                            <?= strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)) ?>
                        </div>
                        <h4><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($client['email']) ?></p>
                        <?php if ($client['phone']): ?>
                            <p><i class="fas fa-phone me-2"></i><?= htmlspecialchars($client['phone']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Address Information</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($client['address_line1']): ?>
                            <p class="mb-1"><?= htmlspecialchars($client['address_line1']) ?></p>
                            <?php if ($client['address_line2']): ?>
                                <p class="mb-1"><?= htmlspecialchars($client['address_line2']) ?></p>
                            <?php endif; ?>
                            <p class="mb-1">
                                <?= htmlspecialchars($client['city']) ?> 
                                <?= htmlspecialchars($client['postal_code']) ?>
                            </p>
                            <p class="mb-0"><?= htmlspecialchars($client['country']) ?></p>
                        <?php else: ?>
                            <p class="text-muted">No address information available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3">
                                <h4 class="text-primary"><?= $client['total_appointments'] ?></h4>
                                <small class="text-muted">Total Appointments</small>
                            </div>
                            <div class="col-3">
                                <h4 class="text-warning"><?= $client['scheduled_appointments'] ?></h4>
                                <small class="text-muted">Scheduled</small>
                            </div>
                            <div class="col-3">
                                <h4 class="text-success"><?= $client['completed_appointments'] ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                            <div class="col-3">
                                <h4 class="text-info"><?= $client['total_messages'] ?></h4>
                                <small class="text-muted">Messages</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Recent Appointments</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <p class="text-muted">No appointments found.</p>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <div>
                                        <strong><?= htmlspecialchars($appointment['property_title']) ?></strong><br>
                                        <small class="text-muted">with <?= htmlspecialchars($appointment['agent_name']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div><?= date('M d, Y', strtotime($appointment['appointment_date'])) ?></div>
                                        <small class="badge bg-<?= $appointment['status'] === 'scheduled' ? 'warning' : ($appointment['status'] === 'completed' ? 'success' : 'secondary') ?>">
                                            <?= ucfirst($appointment['status']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>