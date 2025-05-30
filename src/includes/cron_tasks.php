<?php
/**
 * Cron tasks for automatic appointment status updates
 * This should be run periodically (e.g., every hour) via cron job
 */

require_once '../config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Update scheduled appointments that have passed to completed
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'completed', updated_at = NOW() 
        WHERE status = 'scheduled' 
        AND appointment_date < NOW()
    ");
    
    $stmt->execute();
    $affected_rows = $stmt->rowCount();
    
    // Log the update
    error_log("Cron: Updated {$affected_rows} appointments from scheduled to completed");
    
    // You can also add other periodic tasks here
    // For example: cleanup old data, send reminders, etc.
    
} catch (Exception $e) {
    error_log("Cron task error: " . $e->getMessage());
}
?>
