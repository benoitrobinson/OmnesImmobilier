<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';
if(!isLoggedIn() || $_SERVER['REQUEST_METHOD']!=='POST') {
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}
$action = $_POST['action'] ?? '';
$id = intval($_POST['appointment_id'] ?? 0);
if($action==='cancel' && $id>0) {
  $pdo = Database::getInstance()->getConnection();
  $stmt = $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND client_id=?");
  if($stmt->execute([$id, $_SESSION['user_id']])) {
    echo json_encode(['success'=>true]);
  } else {
    echo json_encode(['success'=>false,'message'=>'DB error']);
  }
} else {
  echo json_encode(['success'=>false,'message'=>'Invalid request']);
}
