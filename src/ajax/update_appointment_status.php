<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = Database::getInstance()->getConnection();
    
    if ($action === 'update_past_appointments') {
        // Update all past scheduled appointments to completed
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'completed', updated_at = NOW() 
            WHERE status = 'scheduled' 
            AND appointment_date < NOW()
        ");
        
        $stmt->execute();
        $affected_rows = $stmt->rowCount();
        
        echo json_encode([
            'success' => true, 
            'message' => "Updated {$affected_rows} appointments to completed",
            'affected_rows' => $affected_rows
        ]);
        
    } elseif ($action === 'mark_completed') {
        $appointment_id = (int)($_POST['appointment_id'] ?? 0);
        
        if (!$appointment_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
            exit;
        }
        
        // Verify the appointment belongs to the current user or they're an admin
        $check_stmt = $pdo->prepare("
            SELECT client_id, agent_id, status 
            FROM appointments 
            WHERE id = ?
        ");
        $check_stmt->execute([$appointment_id]);
        $appointment = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }
        
        $user_role = $_SESSION['role'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if ($user_role !== 'admin' && 
            $appointment['client_id'] != $user_id && 
            $appointment['agent_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        // Update the specific appointment
        $update_stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'completed', updated_at = NOW() 
            WHERE id = ?
        ");
        
        $update_stmt->execute([$appointment_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment marked as completed'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Appointment status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
