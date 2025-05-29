<?php
require '../includes/functions.php'; require '../config/database.php';
requireRole('agent');
$pdo=Database::getInstance();
// handle POST save
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $days = $_POST['days'] ?? [];
  $pdo->beginTransaction();
  $pdo->prepare("DELETE FROM agent_availability WHERE agent_id=?")->execute([$_SESSION['user_id']]);
  foreach ($days as $dow) {
    $s=$_POST['start'][$dow]; $e=$_POST['end'][$dow];
    $stmt=$pdo->prepare("INSERT INTO agent_availability(agent_id,day_of_week,start_time,end_time) VALUES(?,?,?,?)");
    $stmt->execute([$_SESSION['user_id'],$dow,$s,$e]);
  }
  $pdo->commit();
  $msg="Availability saved";
}
include '../includes/navigation.php';
// fetch existing
$stmt=$pdo->prepare("SELECT * FROM agent_availability WHERE agent_id=?");
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
</div>
