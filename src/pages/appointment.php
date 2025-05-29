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
  <link href="../assets/css/appointment.css" rel="stylesheet">
  <style>
    .table-modern {
      width:100%; border-collapse: separate; border-spacing: 0 0.75rem;
    }
    .table-modern th, .table-modern td { border: none; }
    .table-modern tbody tr {
      background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      transition: transform .2s;
    }
    .table-modern tbody tr:hover { transform: translateY(-4px); }
    .table-modern th { color:#6c757d; font-weight:600; }
    .action-btn { padding: .25rem .75rem; font-size:.875rem; }
  </style>
</head>
<body>
  <?php include '../includes/navigation.php'; ?>

  <div class="container py-4">
    <h2>My Appointments</h2>
    <?php if (empty($appointments)): ?>
      <p>You have no appointments.</p>
    <?php else: ?>
      <table class="table-modern">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Property</th>
            <th>Agent</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($appointments as $a): ?>
          <tr>
            <td><?= date('d M Y, H:i', strtotime($a['appointment_date'])) ?></td>
            <td><?= htmlspecialchars($a['property']) ?></td>
            <td><?= htmlspecialchars($a['agent']) ?></td>
            <td><?= ucfirst(htmlspecialchars($a['status'])) ?></td>
            <td>
              <?php if($a['status']==='scheduled'): ?>
                <button class="btn btn-sm btn-danger action-btn"
                        onclick="cancelAppointment(<?= $a['appointment_id'] ?>, this)">
                  Cancel
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script>
    function cancelAppointment(id, btn) {
      if(!confirm('Cancel this appointment?')) return;
      fetch('../ajax/appointments.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=cancel&appointment_id=${id}`
      })
      .then(r=>r.json())
      .then(js=>{
        if(js.success) {
          btn.closest('tr').remove();
        } else {
          alert(js.message||'Error');
        }
      })
      .catch(()=>alert('Request failed'));
    }
  </script>
  <script src="../assets/js/appointment.js"></script>
</body>
</html>