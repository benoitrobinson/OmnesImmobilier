<?php
require '../includes/functions.php'; require '../config/database.php';
requireRole('agent');
$pdo=Database::getInstance();
// handle POST save
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $days = $_POST['days'] ?? [];
  $pdo->beginTransaction();
  // Only delete general availability records (not specific appointments)
  $pdo->prepare("DELETE FROM agent_availability WHERE agent_id=? AND specific_date IS NULL")->execute([$_SESSION['user_id']]);
  foreach ($days as $dow) {
    $s=$_POST['start'][$dow]; $e=$_POST['end'][$dow];
    $stmt=$pdo->prepare("INSERT INTO agent_availability(agent_id,day_of_week,start_time,end_time) VALUES(?,?,?,?)");
    $stmt->execute([$_SESSION['user_id'],$dow,$s,$e]);
  }
  $pdo->commit();
  $msg="Availability saved";
}
include '../includes/navigation.php';
// fetch existing general availability (not specific appointments)
$stmt=$pdo->prepare("SELECT * FROM agent_availability WHERE agent_id=? AND specific_date IS NULL");
$stmt->execute([$_SESSION['user_id']]);
$current=$stmt->fetchAll(PDO::FETCH_KEY_PAIR|PDO::FETCH_UNIQUE);
?>
<div class="container py-4">
  <h2>Manage Availability</h2>
  <?php if(!empty($msg)):?><div class="alert alert-success"><?=$msg?></div><?php endif;?>
  <form method="POST">
    <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): 
      $s=$current[$d]['start_time'] ?? '09:00';
      $e=$current[$d]['end_time']   ?? '17:00';
    ?>
    <div class="row align-items-center mb-2">
      <div class="col-2"><?=$d?></div>
      <div class="col-3">
        <input type="time" name="start[<?=$d?>]" value="<?=$s?>" class="form-control">
      </div>
      <div class="col-3">
        <input type="time" name="end[<?=$d?>]" value="<?=$e?>" class="form-control">
      </div>
      <div class="col-2">
        <input type="checkbox" name="days[]" value="<?=$d?>" <?=isset($current[$d])?'checked':''?>>
      </div>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-primary">Save</button>
  </form>

  <h3 class="mt-4">Upcoming Appointments</h3>
  <div class="table-responsive">
    <?php
    // Get upcoming appointments (stored as specific unavailability records)
    $appointmentsStmt = $pdo->prepare("
        SELECT id, day_of_week, specific_date, start_time, end_time, availability_type 
        FROM agent_availability 
        WHERE agent_id = ? 
        AND specific_date IS NOT NULL 
        AND specific_date >= CURDATE()
        AND is_available = 0
        ORDER BY specific_date, start_time
    ");
    $appointmentsStmt->execute([$_SESSION['user_id']]);
    $appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get actual appointment details from appointments table
    $appointmentDetails = [];
    if (!empty($appointments)) {
        foreach ($appointments as $appointment) {
            $apptStmt = $pdo->prepare("
                SELECT a.*, u.first_name, u.last_name, p.title as property_title 
                FROM appointments a 
                LEFT JOIN users u ON a.client_id = u.id 
                LEFT JOIN properties p ON a.property_id = p.id 
                WHERE a.agent_id = ? 
                AND DATE(a.appointment_date) = ? 
                AND TIME(a.appointment_date) = ?
            ");
            $apptStmt->execute([$_SESSION['user_id'], $appointment['specific_date'], $appointment['start_time']]);
            $apptDetail = $apptStmt->fetch(PDO::FETCH_ASSOC);
            if ($apptDetail) {
                $appointmentDetails[] = array_merge($appointment, $apptDetail);
            }
        }
    }
    ?>
    
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Date</th>
          <th>Time</th>
          <th>Duration</th>
          <th>Client</th>
          <th>Property</th>
          <th>Location</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($appointmentDetails)): ?>
          <tr>
            <td colspan="7" class="text-center">No upcoming appointments</td>
          </tr>
        <?php else: ?>
          <?php foreach($appointmentDetails as $appointment): ?>
            <tr>
              <td><?= date('l, M j, Y', strtotime($appointment['specific_date'])) ?></td>
              <td><?= date('g:i A', strtotime($appointment['start_time'])) ?></td>
              <td>
                <?php 
                $start = strtotime($appointment['start_time']);
                $end = strtotime($appointment['end_time']);
                $duration = ($end - $start) / 60;
                echo $duration . ' min';
                ?>
              </td>
              <td><?= htmlspecialchars(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? '')) ?></td>
              <td><?= htmlspecialchars($appointment['property_title'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($appointment['location'] ?? 'N/A') ?></td>
              <td>
                <a href="?cancel=<?= $appointment['id'] ?>" class="btn btn-sm btn-danger" 
                   onclick="return confirm('Cancel this appointment?')">Cancel</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Handle appointment cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $pdo->beginTransaction();
    try {
        // Delete the blocking record from agent_availability
        $cancelStmt = $pdo->prepare("DELETE FROM agent_availability WHERE id = ? AND agent_id = ? AND specific_date IS NOT NULL AND is_available = 0");
        $cancelStmt->execute([$_GET['cancel'], $_SESSION['user_id']]);
        
        // Also cancel the actual appointment
        $apptCancelStmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE agent_id = ? AND id = ?");
        $apptCancelStmt->execute([$_SESSION['user_id'], $_GET['cancel']]);
        
        $pdo->commit();
        header('Location: availability.php?msg=Appointment cancelled');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: availability.php?msg=Error cancelling appointment');
        exit;
    }
}

// Display success message from URL parameter
if (isset($_GET['msg'])) {
    echo '<script>document.addEventListener("DOMContentLoaded", function() { 
        const alert = document.createElement("div");
        alert.className = "alert alert-success alert-dismissible fade show";
        alert.innerHTML = "' . htmlspecialchars($_GET['msg']) . ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>";
        document.querySelector(".container").insertBefore(alert, document.querySelector("h2").nextSibling);
    });</script>';
}
?>
