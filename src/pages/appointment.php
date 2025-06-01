<?php
// File: src/pages/appointment.php
// require login
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('../auth/login.php');
}

$pdo = Database::getInstance()->getConnection();

// First, update any scheduled appointments that are past their date to "completed"
$update_stmt = $pdo->prepare("
    UPDATE appointments 
    SET status = 'completed', updated_at = NOW() 
    WHERE status = 'scheduled' 
    AND appointment_date < NOW()
");
$update_stmt->execute();

// fetch all client appointments
$stmt = $pdo->prepare("
  SELECT 
    a.id AS appointment_id, 
    a.appointment_date, 
    a.status, 
    p.title AS property,
    CONCAT(u.first_name,' ',u.last_name) AS agent
  FROM appointments a
  JOIN properties p ON a.property_id=p.id
  JOIN users u ON a.agent_id=u.id
  WHERE a.client_id = ?
  ORDER BY a.appointment_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count appointments by status
$status_counts = [
    'all' => count($appointments),
    'scheduled' => 0,
    'cancelled' => 0,
    'completed' => 0,
    'pending' => 0
];

foreach ($appointments as $appointment) {
    if (isset($status_counts[$appointment['status']])) {
        $status_counts[$appointment['status']]++;
    }
}

// Get active tab from URL or default to 'all'
$active_tab = isset($_GET['status']) ? $_GET['status'] : 'all';
if (!in_array($active_tab, ['all', 'scheduled', 'cancelled', 'completed', 'pending'])) {
    $active_tab = 'all';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Appointments - Omnes Immobilier</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/navigation.css" rel="stylesheet">
  
  <style>
    body {
      background: #f8f9fa;
      padding-top: 0 !important; /* Remove the padding that's causing the gap */
    }
    
    .page-header {
      background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
      color: white;
      padding: 3rem 0 2rem;
      margin-bottom: 2rem;
      margin-top: 2rem; /* Add top margin instead of body padding */
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
    
    .page-header .container {
      position: relative;
      z-index: 1;
    }
    
    .page-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    .page-header p {
      font-size: 1.1rem;
      opacity: 0.9;
      margin-bottom: 0;
    }
    
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
    
    .table-hover tbody tr:hover {
      background-color: rgba(212, 175, 55, 0.05) !important;
    }
    
    .btn-luxury-primary {
      background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
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
    
    .btn-luxury-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
      color: white;
      text-decoration: none;
    }
    
    .alert {
      border: none;
      border-radius: 1rem;
      padding: 1rem 1.5rem;
      margin-bottom: 2rem;
    }
    
    .alert-success {
      background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
      color: #155724;
    }
  </style>
</head>
<body>
  <?php include '../includes/navigation.php'; ?>

  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h1><i class="fas fa-calendar-alt me-3"></i>My Appointments</h1>
            <p class="mb-0">Manage and track all your property viewing appointments</p>
          </div>
          <div class="col-md-4 text-end">
            <a href="../pages/explore.php" class="btn btn-light">
              <i class="fas fa-search me-2"></i>Browse Properties
            </a>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($appointments)): ?>
      <div class="content-card">
        <div class="content-card-body text-center py-5">
          <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
          <h4 class="text-muted mb-3">No Appointments Found</h4>
          <p class="text-muted mb-4">You haven't booked any appointments yet.</p>
          <a href="../pages/explore.php" class="btn-luxury-primary">
            <i class="fas fa-search"></i>
            Browse Properties
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="content-card">
        <div class="content-card-header">
          <i class="fas fa-calendar-alt me-2"></i>My Appointments (<?= count($appointments) ?>)
        </div>
        
        <!-- Filter tabs -->
        <div class="content-card-body pb-0">
          <ul class="nav nav-tabs">
            <li class="nav-item">
              <a class="nav-link <?= $active_tab === 'all' ? 'active' : '' ?>" href="?status=all">
                All <span class="badge bg-secondary ms-1"><?= $status_counts['all'] ?></span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $active_tab === 'scheduled' ? 'active' : '' ?>" href="?status=scheduled">
                <i class="fas fa-calendar-check me-1 text-success"></i>
                Scheduled <span class="badge bg-success ms-1"><?= $status_counts['scheduled'] ?></span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $active_tab === 'completed' ? 'active' : '' ?>" href="?status=completed">
                <i class="fas fa-check-circle me-1 text-primary"></i>
                Completed <span class="badge bg-primary ms-1"><?= $status_counts['completed'] ?></span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $active_tab === 'cancelled' ? 'active' : '' ?>" href="?status=cancelled">
                <i class="fas fa-times-circle me-1 text-danger"></i>
                Cancelled <span class="badge bg-danger ms-1"><?= $status_counts['cancelled'] ?></span>
              </a>
            </li>
          </ul>
        </div>
        
        <div class="content-card-body pt-4">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Date & Time</th>
                  <th>Property</th>
                  <th>Agent</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $filtered_count = 0;
                foreach($appointments as $a): 
                  // Filter based on selected tab
                  if ($active_tab !== 'all' && $a['status'] !== $active_tab) continue;
                  $filtered_count++;
                ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= date('d M Y', strtotime($a['appointment_date'])) ?></div>
                      <small class="text-muted"><?= date('H:i', strtotime($a['appointment_date'])) ?></small>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($a['property']) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($a['agent']) ?></div>
                    </td>
                    <td>
                      <?php 
                      $status_colors = [
                        'scheduled' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'primary',
                        'pending' => 'warning'
                      ];
                      $status_icons = [
                        'scheduled' => 'fa-calendar-check',
                        'cancelled' => 'fa-times-circle',
                        'completed' => 'fa-check-circle',
                        'pending' => 'fa-clock'
                      ];
                      $status_color = $status_colors[$a['status']] ?? 'secondary';
                      $status_icon = $status_icons[$a['status']] ?? 'fa-question-circle';
                      ?>
                      <span class="badge bg-<?= $status_color ?>">
                        <i class="fas <?= $status_icon ?> me-1"></i>
                        <?= ucfirst(htmlspecialchars($a['status'])) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($a['status'] === 'scheduled' && strtotime($a['appointment_date']) > time()): ?>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="cancelAppointment(<?= $a['appointment_id'] ?>, this)">
                          <i class="fas fa-times me-1"></i>Cancel
                        </button>
                      <?php elseif ($a['status'] === 'cancelled'): ?>
                        <span class="text-muted">
                          <i class="fas fa-ban me-1"></i>Cancelled
                        </span>
                      <?php elseif ($a['status'] === 'completed'): ?>
                        <span class="text-success">
                          <i class="fas fa-check-circle me-1"></i>Completed
                        </span>
                      <?php elseif ($a['status'] === 'scheduled' && strtotime($a['appointment_date']) <= time()): ?>
                        <span class="text-primary">
                          <i class="fas fa-check-circle me-1"></i>Completed
                        </span>
                      <?php else: ?>
                        <span class="text-muted">
                          <i class="fas fa-clock me-1"></i>Pending
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                
                <?php if ($filtered_count === 0): ?>
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted">
                    <i class="fas fa-filter me-2"></i>
                    No <?= $active_tab ?> appointments found
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php
  // First, ensure the required columns exist in agent_availability table
  function ensureAgentAvailabilityColumns($pdo) {
      try {
          // Check if columns exist and add them if they don't
          $columns_to_add = [
              'specific_date' => 'DATE NULL',
              'user_id' => 'INT NULL', 
              'notes' => 'TEXT NULL'
          ];
            
          foreach ($columns_to_add as $column => $definition) {
              try {
                  $pdo->exec("ALTER TABLE agent_availability ADD COLUMN $column $definition");
              } catch (PDOException $e) {
                  // Column might already exist, continue
              }
          }
          
          // Update existing records to have is_available = 1 for general availability
          $pdo->exec("UPDATE agent_availability SET is_available = 1 WHERE specific_date IS NULL AND is_available IS NULL");
          
      } catch (PDOException $e) {
          // Handle any other errors silently
      }
  }
  
  // Sync appointments with agent_availability table
  function syncAppointmentsWithAvailability($pdo) {
      // First ensure columns exist
      ensureAgentAvailabilityColumns($pdo);
      
      // Get all scheduled appointments that don't have corresponding agent_availability records
      $sync_stmt = $pdo->prepare("
          SELECT a.id, a.agent_id, a.client_id, a.appointment_date
          FROM appointments a
          WHERE a.status = 'scheduled' 
          AND a.appointment_date > NOW()
          AND NOT EXISTS (
              SELECT 1 FROM agent_availability aa 
              WHERE aa.agent_id = a.agent_id 
              AND aa.specific_date = DATE(a.appointment_date)
              AND aa.start_time = TIME(a.appointment_date)
              AND aa.is_available = 0
          )
      ");
      $sync_stmt->execute();
      $appointments_to_sync = $sync_stmt->fetchAll(PDO::FETCH_ASSOC);
      
      foreach ($appointments_to_sync as $appt) {
          $appointment_date = new DateTime($appt['appointment_date']);
          $day_of_week = $appointment_date->format('l'); // Monday, Tuesday, etc.
          $specific_date = $appointment_date->format('Y-m-d');
          $start_time = $appointment_date->format('H:i:s');
          
          // Calculate end time (30 minutes later)
          $end_time = clone $appointment_date;
          $end_time->add(new DateInterval('PT30M'));
          $end_time_str = $end_time->format('H:i:s');
          
          // Insert into agent_availability
          try {
              $insert_stmt = $pdo->prepare("
                  INSERT INTO agent_availability 
                  (agent_id, day_of_week, specific_date, start_time, end_time, user_id, is_available, availability_type, notes)
                  VALUES (?, ?, ?, ?, ?, ?, 0, 'exception', 'Appointment booking')
              ");
              $insert_stmt->execute([
                  $appt['agent_id'],
                  $day_of_week,
                  $specific_date,
                  $start_time,
                  $end_time_str,
                  $appt['client_id']
              ]);
          } catch (PDOException $e) {
              // If availability_type column doesn't exist, try without it
              try {
                  $insert_stmt = $pdo->prepare("
                      INSERT INTO agent_availability 
                      (agent_id, day_of_week, specific_date, start_time, end_time, user_id, is_available, notes)
                      VALUES (?, ?, ?, ?, ?, ?, 0, 'Appointment booking')
                  ");
                  $insert_stmt->execute([
                      $appt['agent_id'],
                      $day_of_week,
                      $specific_date,
                      $start_time,
                      $end_time_str,
                      $appt['client_id']
                  ]);
              } catch (PDOException $e2) {
                  // If that fails too, try minimal insert
                  $insert_stmt = $pdo->prepare("
                      INSERT INTO agent_availability 
                      (agent_id, day_of_week, specific_date, start_time, end_time, is_available)
                      VALUES (?, ?, ?, ?, ?, 0)
                  ");
                  $insert_stmt->execute([
                      $appt['agent_id'],
                      $day_of_week,
                      $specific_date,
                      $start_time,
                      $end_time_str
                  ]);
              }
          }
      }
      
      // Clean up cancelled appointments from agent_availability
      try {
          $cleanup_stmt = $pdo->prepare("
              DELETE aa FROM agent_availability aa
              WHERE aa.is_available = 0 
              AND aa.specific_date IS NOT NULL
              AND aa.specific_date < CURDATE()
              AND NOT EXISTS (
                  SELECT 1 FROM appointments a 
                  WHERE a.agent_id = aa.agent_id 
                  AND DATE(a.appointment_date) = aa.specific_date
                  AND TIME(a.appointment_date) = aa.start_time
                  AND a.status = 'scheduled'
              )
          ");
          $cleanup_stmt->execute();
      } catch (PDOException $e) {
          // Handle cleanup error silently
      }
  }
  
  // Run the sync
  try {
      syncAppointmentsWithAvailability($pdo);
  } catch (PDOException $e) {
      // Handle sync error silently for now
      error_log("Appointment sync error: " . $e->getMessage());
  }
  ?>

  <script>
    function cancelAppointment(id, btn) {
      if(!confirm('Are you sure you want to cancel this appointment?')) return;
      
      // Show loading state
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cancelling...';
      btn.disabled = true;
      
      fetch('../ajax/appointments.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=cancel&appointment_id=${id}`
      })
      .then(r=>r.json())
      .then(js=>{
        if(js.success) {
          // Show success message
          showNotification('Appointment cancelled successfully!', 'success');
          // Update the row instead of removing it
          const row = btn.closest('tr');
          const statusCell = row.cells[3];
          const actionCell = row.cells[4];
          
          statusCell.innerHTML = '<span class="badge bg-danger">Cancelled</span>';
          actionCell.innerHTML = '<span class="text-muted"><i class="fas fa-ban me-1"></i>Cancelled</span>';
        } else {
          btn.innerHTML = originalText;
          btn.disabled = false;
          showNotification('Error: ' + (js.message || 'Failed to cancel appointment'), 'error');
        }
      })
      .catch(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showNotification('Request failed. Please try again.', 'error');
      });
    }
    
    // Show notification function
    function showNotification(message, type) {
      const notification = document.createElement('div');
      notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
      notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
      notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      
      document.body.appendChild(notification);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 5000);
    }
  </script>
</body>
</html>