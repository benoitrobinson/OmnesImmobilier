<?php
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$pdo = Database::getInstance()->getConnection();

// Function to get available time slots
function getAvailableTimeSlots($pdo, $agentId, $date, $duration = 30) {
    $dayOfWeek = date('l', strtotime($date));
    
    // Get general availability for the day
    $availStmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM agent_availability 
        WHERE agent_id = ? AND day_of_week = ? AND specific_date IS NULL AND is_available = 1
    ");
    $availStmt->execute([$agentId, $dayOfWeek]);
    $availability = $availStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$availability) return [];
    
    // Get blocked time slots for this specific date (is_available = 0)
    $blockedStmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM agent_availability 
        WHERE agent_id = ? AND specific_date = ? AND is_available = 0
        ORDER BY start_time
    ");
    $blockedStmt->execute([$agentId, $date]);
    $blockedSlots = $blockedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate time slots
    $slots = [];
    $current = new DateTime($availability['start_time']);
    $end = new DateTime($availability['end_time']);
    
    while ($current < $end) {
        $slotEnd = clone $current;
        $slotEnd->add(new DateInterval('PT' . $duration . 'M'));
        
        if ($slotEnd <= $end) {
            $isAvailable = true;
            
            // Check if this slot conflicts with any blocked slots
            foreach ($blockedSlots as $blocked) {
                $blockedStart = new DateTime($blocked['start_time']);
                $blockedEnd = new DateTime($blocked['end_time']);
                
                // Check for overlap
                if (($current < $blockedEnd) && ($slotEnd > $blockedStart)) {
                    $isAvailable = false;
                    break;
                }
            }
            
            if ($isAvailable) {
                $slots[] = $current->format('H:i');
            }
        }
        
        $current->add(new DateInterval('PT30M'));
    }
    
    return $slots;
}

// Handle different actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_available_times':
            $agentId = (int)$_POST['agent_id'];
            $date = $_POST['date'];
            
            if (!$agentId || !$date) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit;
            }
            
            if (strtotime($date) < strtotime('today')) {
                echo json_encode(['success' => false, 'times' => []]);
                exit;
            }
            
            $availableTimes = getAvailableTimeSlots($pdo, $agentId, $date);
            echo json_encode(['success' => true, 'times' => $availableTimes]);
            exit;
            
        case 'book_appointment':
            $agentId = (int)$_POST['agent_id'];
            $propertyId = (int)$_POST['property_id'];
            $date = $_POST['date'];
            $time = $_POST['time'];
            
            if (!$agentId || !$propertyId || !$date || !$time) {
                echo json_encode(['success' => false, 'message' => 'Missing required information']);
                exit;
            }
            
            $availableSlots = getAvailableTimeSlots($pdo, $agentId, $date);
            if (!in_array($time, $availableSlots)) {
                echo json_encode(['success' => false, 'message' => 'This time slot is no longer available']);
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                $appointmentDate = $date . ' ' . $time . ':00';
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (client_id, agent_id, property_id, appointment_date, status, location) 
                    VALUES (?, ?, ?, ?, 'scheduled', 
                            (SELECT CONCAT(address_line1, ', ', city) FROM properties WHERE id = ?))
                ");
                $stmt->execute([$_SESSION['user_id'], $agentId, $propertyId, $appointmentDate, $propertyId]);
                $appointmentId = $pdo->lastInsertId();
                
                // Create unavailable slot in agent_availability
                $appointmentDateTime = new DateTime($appointmentDate);
                $dayOfWeek = $appointmentDateTime->format('l');
                $specificDate = $appointmentDateTime->format('Y-m-d');
                $startTime = $appointmentDateTime->format('H:i:s');
                
                $endDateTime = clone $appointmentDateTime;
                $endDateTime->add(new DateInterval('PT30M'));
                $endTime = $endDateTime->format('H:i:s');
                
                $availStmt = $pdo->prepare("
                    INSERT INTO agent_availability 
                    (agent_id, day_of_week, specific_date, start_time, end_time, user_id, is_available, notes)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?)
                ");
                $availStmt->execute([
                    $agentId, $dayOfWeek, $specificDate, $startTime, $endTime, 
                    $_SESSION['user_id'], 'Appointment #' . $appointmentId
                ]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Appointment booked successfully!']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to book appointment']);
            }
            exit;
            
        case 'cancel':
            if (!isset($_POST['appointment_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
                exit;
            }
            
            $appointment_id = (int)$_POST['appointment_id'];
            
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    SELECT agent_id, client_id, appointment_date 
                    FROM appointments 
                    WHERE id = ? AND client_id = ?
                ");
                $stmt->execute([$appointment_id, $_SESSION['user_id']]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$appointment) {
                    throw new Exception('Appointment not found');
                }
                
                $update_stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', updated_at = NOW() 
                    WHERE id = ? AND client_id = ?
                ");
                $update_stmt->execute([$appointment_id, $_SESSION['user_id']]);
                
                $delete_stmt = $pdo->prepare("
                    DELETE FROM agent_availability 
                    WHERE agent_id = ? 
                    AND specific_date = DATE(?) 
                    AND start_time = TIME(?) 
                    AND user_id = ? 
                    AND is_available = 0
                ");
                $delete_stmt->execute([
                    $appointment['agent_id'],
                    $appointment['appointment_date'],
                    $appointment['appointment_date'],
                    $appointment['client_id']
                ]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// If no valid action, return error
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
