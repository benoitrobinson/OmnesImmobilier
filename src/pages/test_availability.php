<?php
// test_availability.php - Test for agent availability
session_start();
require_once '../config/database.php';

// Clear all output buffers and turn off output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set content type to JSON
header('Content-Type: application/json');

// Get parameters
$date = $_GET['date'] ?? '';
$agent_id = $_GET['agent_id'] ?? '';

// Log request for debugging
error_log("test_availability.php - Date: $date, Agent ID: $agent_id");

// Check if parameters are provided
if (empty($date) || empty($agent_id)) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    $day_of_week = date('l', strtotime($date));
    $available_times = [];
    
    // Get general availability for this day of week (is_available = 1, no specific_date)
    $query = "SELECT start_time, end_time FROM agent_availability 
             WHERE agent_id = ? AND day_of_week = ? AND specific_date IS NULL AND is_available = 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$agent_id, $day_of_week]);
    $general_availability = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("Day of week: $day_of_week, Agent: $agent_id");
    error_log("General availability: " . json_encode($general_availability));
    
    if ($general_availability) {
        // Generate time slots from general availability
        $start = strtotime($general_availability['start_time']);
        $end = strtotime($general_availability['end_time']);
        $current = $start;
        
        while ($current < $end) {
            $time_slot = date('H:i:s', $current);
            $time_display = date('g:i A', $current);
            
            // Check if this specific time is blocked (is_available = 0) for this specific date
            $blocked_query = "SELECT COUNT(*) FROM agent_availability 
                             WHERE agent_id = ? AND specific_date = ? AND is_available = 0 
                             AND start_time <= ? AND end_time > ?";
            $blocked_stmt = $pdo->prepare($blocked_query);
            $blocked_stmt->execute([$agent_id, $date, $time_slot, $time_slot]);
            $is_blocked = $blocked_stmt->fetchColumn() > 0;
            
            // Check existing appointments
            $appt_query = "SELECT COUNT(*) FROM appointments 
                          WHERE agent_id = ? AND DATE(appointment_date) = ? 
                          AND TIME(appointment_date) = ? AND status = 'scheduled'";
            $appt_stmt = $pdo->prepare($appt_query);
            $appt_stmt->execute([$agent_id, $date, $time_slot]);
            $has_appointment = $appt_stmt->fetchColumn() > 0;
            
            // Log for debugging
            error_log("Time $time_slot: Blocked=$is_blocked, Has appointment=$has_appointment");
            
            // Only show time if not blocked and no existing appointment
            if (!$is_blocked && !$has_appointment) {
                $available_times[] = [
                    'value' => $time_slot,
                    'display' => $time_display
                ];
            }
            
            $current += 1800; // Add 30 minutes
        }
    } else {
        // If no availability found, we'll return empty array
        error_log("No availability found for agent $agent_id on $day_of_week");
    }
    
    echo json_encode($available_times);
    
} catch (Exception $e) {
    error_log("test_availability.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>