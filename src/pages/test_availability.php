<?php
// test_availability.php - Minimal test for agent availability
session_start();
require_once '../config/database.php';

// Clear all output buffers and turn off output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set content type to JSON
header('Content-Type: application/json');

// Get parameters
$date = $_GET['date'] ?? date('Y-m-d');
$agent_id = $_GET['agent_id'] ?? 5; // Default to John Doe

// Connect to database
$pdo = Database::getInstance()->getConnection();

// Get day of week
$day_of_week = date('l', strtotime($date));

// Array to hold available times
$available_times = [];

try {
    // Combined query: get both weekly schedule (NULL specific_date) AND specific date overrides
    $query = "SELECT * FROM agent_availability 
              WHERE agent_id = ? 
              AND is_available = 1
              AND (
                  (day_of_week = ? AND specific_date IS NULL) 
                  OR specific_date = ?
              )
              ORDER BY specific_date DESC"; // Specific dates override weekly schedule
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$agent_id, $day_of_week, $date]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate time slots
    foreach ($slots as $slot) {
        $start_time = $slot['start_time'];
        $end_time = $slot['end_time'];
        
        if (!$start_time || !$end_time) {
            continue;
        }
        
        $current = strtotime($start_time);
        $end = strtotime($end_time);
        
        while ($current < $end) {
            $time_slot = date('H:i:s', $current);
            $time_display = date('g:i A', $current);
            
            $available_times[] = [
                'value' => $time_slot,
                'display' => $time_display
            ];
            
            $current += 1800; // 30 minutes
        }
    }
    
    // Output JSON
    echo json_encode($available_times);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>