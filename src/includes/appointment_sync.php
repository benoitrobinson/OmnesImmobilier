<?php
/**
 * Sync appointments table with agent_availability table
 * This ensures that when appointments are created, agents become unavailable
 */

function syncAppointmentToAvailability($pdo, $appointment_id) {
    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT agent_id, client_id, appointment_date, status
        FROM appointments 
        WHERE id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) return false;
    
    $appointment_date = new DateTime($appointment['appointment_date']);
    $day_of_week = $appointment_date->format('l');
    $specific_date = $appointment_date->format('Y-m-d');
    $start_time = $appointment_date->format('H:i:s');
    
    // Calculate end time (30 minutes duration)
    $end_time = clone $appointment_date;
    $end_time->add(new DateInterval('PT30M'));
    $end_time_str = $end_time->format('H:i:s');
    
    if ($appointment['status'] === 'scheduled') {
        // Create unavailable slot
        $check_stmt = $pdo->prepare("
            SELECT id FROM agent_availability 
            WHERE agent_id = ? AND specific_date = ? AND start_time = ? AND user_id = ?
        ");
        $check_stmt->execute([
            $appointment['agent_id'],
            $specific_date,
            $start_time,
            $appointment['client_id']
        ]);
        
        if ($check_stmt->rowCount() === 0) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO agent_availability 
                (agent_id, day_of_week, specific_date, start_time, end_time, user_id, is_available, availability_type, notes)
                VALUES (?, ?, ?, ?, ?, ?, 0, 'exception', ?)
            ");
            $insert_stmt->execute([
                $appointment['agent_id'],
                $day_of_week,
                $specific_date,
                $start_time,
                $end_time_str,
                $appointment['client_id'],
                'Appointment #' . $appointment_id
            ]);
        }
    } else {
        // Remove unavailable slot for cancelled/completed appointments
        $delete_stmt = $pdo->prepare("
            DELETE FROM agent_availability 
            WHERE agent_id = ? AND specific_date = ? AND start_time = ? AND user_id = ?
        ");
        $delete_stmt->execute([
            $appointment['agent_id'],
            $specific_date,
            $start_time,
            $appointment['client_id']
        ]);
    }
    
    return true;
}

/**
 * Batch sync all appointments with agent availability
 */
function batchSyncAppointments($pdo) {
    // Get all scheduled appointments
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE status = 'scheduled' 
        AND appointment_date > NOW()
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($appointments as $appointment_id) {
        syncAppointmentToAvailability($pdo, $appointment_id);
    }
    
    // Clean up old availability records for cancelled/completed appointments
    $cleanup_stmt = $pdo->prepare("
        DELETE aa FROM agent_availability aa
        LEFT JOIN appointments a ON (
            a.agent_id = aa.agent_id 
            AND DATE(a.appointment_date) = aa.specific_date
            AND TIME(a.appointment_date) = aa.start_time
            AND a.client_id = aa.user_id
            AND a.status = 'scheduled'
        )
        WHERE aa.is_available = 0 
        AND aa.specific_date IS NOT NULL
        AND aa.availability_type = 'exception'
        AND a.id IS NULL
    ");
    $cleanup_stmt->execute();
}
?>
