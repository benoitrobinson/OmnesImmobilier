<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn() || !isAgent()) {
    redirect('../auth/login.php');
}

// Get database connection using the Database class
$database = Database::getInstance();
$db = $database->getConnection();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'calendar';

// Create agent_availability table if it doesn't exist
try {
    $create_table_query = "CREATE TABLE IF NOT EXISTS agent_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        specific_date DATE NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        is_available BOOLEAN NOT NULL DEFAULT TRUE,
        availability_type ENUM('weekly', 'exception', 'lunch_break', 'quick_available', 'quick_blocked') NOT NULL DEFAULT 'weekly',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_agent_date (agent_id, specific_date),
        INDEX idx_agent_day (agent_id, day_of_week, availability_type)
    )";
    $db->exec($create_table_query);
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'update_weekly_schedule') {
        try {
            $db->beginTransaction();
            
            // Clear existing weekly schedule (general availability)
            $clear_query = "DELETE FROM agent_availability WHERE agent_id = :agent_id AND availability_type IN ('weekly', 'lunch_break')";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->execute(['agent_id' => $_SESSION['user_id']]);
            
            // Insert new weekly schedule
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($days as $day) {
                $is_available = isset($_POST[$day . '_available']);
                
                if ($is_available) {
                    $start_time = $_POST[$day . '_start'] ?? '09:00';
                    $end_time = $_POST[$day . '_end'] ?? '17:00';
                    $lunch_break = isset($_POST[$day . '_lunch']);
                    $lunch_start = $_POST[$day . '_lunch_start'] ?? '12:00';
                    $lunch_end = $_POST[$day . '_lunch_end'] ?? '13:00';
                    
                    // Validate times
                    if ($start_time >= $end_time) {
                        throw new Exception("End time must be after start time for " . ucfirst($day));
                    }
                    
                    // Insert main availability
                    $insert_query = "INSERT INTO agent_availability 
                                   (agent_id, day_of_week, start_time, end_time, is_available, availability_type, created_at) 
                                   VALUES (:agent_id, :day_of_week, :start_time, :end_time, 1, 'weekly', NOW())";
                    
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->execute([
                        'agent_id' => $_SESSION['user_id'],
                        'day_of_week' => ucfirst($day),
                        'start_time' => $start_time,
                        'end_time' => $end_time
                    ]);
                    
                    // Insert lunch break if specified
                    if ($lunch_break && $lunch_start < $lunch_end && $lunch_start >= $start_time && $lunch_end <= $end_time) {
                        $lunch_query = "INSERT INTO agent_availability 
                                      (agent_id, day_of_week, start_time, end_time, is_available, availability_type, created_at) 
                                      VALUES (:agent_id, :day_of_week, :start_time, :end_time, 0, 'lunch_break', NOW())";
                        
                        $lunch_stmt = $db->prepare($lunch_query);
                        $lunch_stmt->execute([
                            'agent_id' => $_SESSION['user_id'],
                            'day_of_week' => ucfirst($day),
                            'start_time' => $lunch_start,
                            'end_time' => $lunch_end
                        ]);
                    }
                }
            }
            
            $db->commit();
            $success = 'Weekly schedule updated successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Weekly schedule update error: " . $e->getMessage());
            $error = 'Error updating weekly schedule: ' . $e->getMessage();
        }
    }
    
    elseif ($form_action === 'add_exception') {
        $exception_date = $_POST['exception_date'] ?? '';
        $exception_type = $_POST['exception_type'] ?? 'blocked';
        $start_time = $_POST['start_time'] ?? null;
        $end_time = $_POST['end_time'] ?? null;
        
        if (!empty($exception_date)) {
            // Validate date is not in the past
            if ($exception_date < date('Y-m-d')) {
                $error = 'Cannot add exceptions for past dates.';
            } else {
                try {
                    $day_of_week = date('l', strtotime($exception_date));
                    
                    // Validate times if provided
                    if ($start_time && $end_time && $start_time >= $end_time) {
                        throw new Exception("End time must be after start time.");
                    }
                    
                    // Check if exception already exists for this date
                    $check_query = "SELECT id FROM agent_availability 
                                   WHERE agent_id = :agent_id AND specific_date = :specific_date AND availability_type = 'exception'";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([
                        'agent_id' => $_SESSION['user_id'],
                        'specific_date' => $exception_date
                    ]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        // Update existing exception
                        $update_query = "UPDATE agent_availability SET 
                                        start_time = :start_time, 
                                        end_time = :end_time, 
                                        is_available = :is_available 
                                        WHERE agent_id = :agent_id AND specific_date = :specific_date AND availability_type = 'exception'";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([
                            'agent_id' => $_SESSION['user_id'],
                            'specific_date' => $exception_date,
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'is_available' => ($exception_type === 'available' ? 1 : 0)
                        ]);
                    } else {
                        // Insert new exception
                        $query = "INSERT INTO agent_availability 
                                 (agent_id, day_of_week, specific_date, start_time, end_time, is_available, availability_type, created_at) 
                                 VALUES (:agent_id, :day_of_week, :specific_date, :start_time, :end_time, :is_available, :availability_type, NOW())";
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'agent_id' => $_SESSION['user_id'],
                            'day_of_week' => $day_of_week,
                            'specific_date' => $exception_date,
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'is_available' => ($exception_type === 'available' ? 1 : 0),
                            'availability_type' => 'exception'
                        ]);
                    }
                    
                    $success = 'Schedule exception added successfully!';
                    
                } catch (Exception $e) {
                    error_log("Exception add error: " . $e->getMessage());
                    $error = 'Error adding schedule exception: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Please select a date for the exception.';
        }
    }
    
    elseif ($form_action === 'quick_toggle') {
        $toggle_type = $_POST['toggle_type'] ?? '';
        $duration = (int)($_POST['duration'] ?? 120); // minutes
        
        try {
            $current_date = date('Y-m-d');
            $current_time = date('H:i:s');
            $end_time = date('H:i:s', strtotime("+{$duration} minutes"));
            $day_of_week = date('l');
            
            // Clean up any existing quick toggles for today that have expired
            $cleanup_query = "DELETE FROM agent_availability 
                             WHERE agent_id = :agent_id 
                             AND specific_date = :current_date 
                             AND availability_type IN ('quick_available', 'quick_blocked')
                             AND end_time < :current_time";
            $cleanup_stmt = $db->prepare($cleanup_query);
            $cleanup_stmt->execute([
                'agent_id' => $_SESSION['user_id'],
                'current_date' => $current_date,
                'current_time' => $current_time
            ]);
            
            if ($toggle_type === 'available') {
                $query = "INSERT INTO agent_availability 
                         (agent_id, day_of_week, specific_date, start_time, end_time, is_available, availability_type, created_at) 
                         VALUES (:agent_id, :day_of_week, :specific_date, :start_time, :end_time, 1, 'quick_available', NOW())";
                $message = "Quick availability set for {$duration} minutes";
            } else {
                $query = "INSERT INTO agent_availability 
                         (agent_id, day_of_week, specific_date, start_time, end_time, is_available, availability_type, created_at) 
                         VALUES (:agent_id, :day_of_week, :specific_date, :start_time, :end_time, 0, 'quick_blocked', NOW())";
                $message = "Blocked time set for {$duration} minutes";
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                'agent_id' => $_SESSION['user_id'],
                'day_of_week' => $day_of_week,
                'specific_date' => $current_date,
                'start_time' => $current_time,
                'end_time' => $end_time
            ]);
            
            $success = $message;
            
        } catch (Exception $e) {
            error_log("Quick toggle error: " . $e->getMessage());
            $error = 'Error updating availability status: ' . $e->getMessage();
        }
    }
    
    elseif ($form_action === 'delete_exception') {
        $exception_id = $_POST['exception_id'] ?? '';
        
        try {
            $query = "DELETE FROM agent_availability WHERE id = :id AND agent_id = :agent_id";
            $stmt = $db->prepare($query);
            if ($stmt->execute(['id' => $exception_id, 'agent_id' => $_SESSION['user_id']])) {
                $success = 'Exception deleted successfully!';
            } else {
                $error = 'Error deleting exception.';
            }
        } catch (Exception $e) {
            error_log("Exception delete error: " . $e->getMessage());
            $error = 'Error deleting exception: ' . $e->getMessage();
        }
    }
}

// Create agent_availability table if it doesn't exist
try {
    $create_table_query = "CREATE TABLE IF NOT EXISTS agent_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        specific_date DATE NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        is_available BOOLEAN NOT NULL DEFAULT TRUE,
        availability_type ENUM('weekly', 'exception', 'lunch_break', 'quick_available', 'quick_blocked') NOT NULL DEFAULT 'weekly',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_agent_date (agent_id, specific_date),
        INDEX idx_agent_day (agent_id, day_of_week, availability_type)
    )";
    $db->exec($create_table_query);
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Get current weekly schedule
$weekly_schedule = [];
try {
    $query = "SELECT * FROM agent_availability 
              WHERE agent_id = :agent_id AND availability_type IN ('weekly', 'lunch_break') 
              ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), availability_type";
    $stmt = $db->prepare($query);
    $stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $schedule_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($schedule_data as $slot) {
        $day = strtolower($slot['day_of_week']);
        if (!isset($weekly_schedule[$day])) {
            $weekly_schedule[$day] = ['available' => false, 'lunch_break' => false];
        }
        
        if ($slot['availability_type'] === 'weekly' && $slot['is_available']) {
            $weekly_schedule[$day]['available'] = true;
            $weekly_schedule[$day]['start_time'] = $slot['start_time'];
            $weekly_schedule[$day]['end_time'] = $slot['end_time'];
        } elseif ($slot['availability_type'] === 'lunch_break') {
            $weekly_schedule[$day]['lunch_break'] = true;
            $weekly_schedule[$day]['lunch_start'] = $slot['start_time'];
            $weekly_schedule[$day]['lunch_end'] = $slot['end_time'];
        }
    }
} catch (Exception $e) {
    error_log("Weekly schedule fetch error: " . $e->getMessage());
}

// Get exceptions
$exceptions = [];
try {
    $query = "SELECT * FROM agent_availability 
              WHERE agent_id = :agent_id AND availability_type = 'exception' AND specific_date >= CURDATE()
              ORDER BY specific_date, start_time";
    $stmt = $db->prepare($query);
    $stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $exceptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Exceptions fetch error: " . $e->getMessage());
}

// Get upcoming appointments for conflict detection
$upcoming_appointments = [];
try {
    $query = "SELECT a.*, p.title as property_title 
              FROM appointments a 
              JOIN properties p ON a.property_id = p.id 
              WHERE a.agent_id = :agent_id AND a.appointment_date >= NOW() AND a.status = 'scheduled'
              ORDER BY a.appointment_date 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Upcoming appointments fetch error: " . $e->getMessage());
}

// Check current availability status with quick toggles
$current_status = 'unknown';
$current_day = strtolower(date('l'));
$current_time = date('H:i:s');
$current_date = date('Y-m-d');

// First check for quick toggles that override everything
try {
    $quick_query = "SELECT * FROM agent_availability 
                   WHERE agent_id = :agent_id 
                   AND specific_date = :current_date 
                   AND availability_type IN ('quick_available', 'quick_blocked')
                   AND start_time <= :current_time 
                   AND end_time >= :current_time
                   ORDER BY created_at DESC 
                   LIMIT 1";
    $quick_stmt = $db->prepare($quick_query);
    $quick_stmt->execute([
        'agent_id' => $_SESSION['user_id'],
        'current_date' => $current_date,
        'current_time' => $current_time
    ]);
    $quick_toggle = $quick_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quick_toggle) {
        $current_status = $quick_toggle['is_available'] ? 'available' : 'unavailable';
    } else {
        // Check regular schedule
        if (isset($weekly_schedule[$current_day]) && $weekly_schedule[$current_day]['available']) {
            $start_time = $weekly_schedule[$current_day]['start_time'];
            $end_time = $weekly_schedule[$current_day]['end_time'];
            
            if ($current_time >= $start_time && $current_time <= $end_time) {
                // Check if in lunch break
                if ($weekly_schedule[$current_day]['lunch_break']) {
                    $lunch_start = $weekly_schedule[$current_day]['lunch_start'];
                    $lunch_end = $weekly_schedule[$current_day]['lunch_end'];
                    if ($current_time >= $lunch_start && $current_time <= $lunch_end) {
                        $current_status = 'lunch';
                    } else {
                        $current_status = 'available';
                    }
                } else {
                    $current_status = 'available';
                }
            } else {
                $current_status = 'unavailable';
            }
        } else {
            $current_status = 'unavailable';
        }
    }
} catch (Exception $e) {
    error_log("Current status check error: " . $e->getMessage());
}

// Get current week dates for calendar
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime($current_week_start . " +{$i} days"));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Management - Agent Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Navigation CSS -->
    <link href="../assets/css/agent_navigation.css" rel="stylesheet">
    
    <style>
        :root {
            --agent-primary: #2c5aa0;
            --agent-secondary: #4a90e2;
            --agent-success: #28a745;
            --agent-warning: #ffc107;
            --agent-danger: #dc3545;
            --agent-info: #17a2b8;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-top: 80px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .content-card-body {
            padding: 2rem;
        }

        .status-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .status-available {
            background: linear-gradient(135deg, var(--agent-success) 0%, #20c997 100%);
            color: white;
        }

        .status-unavailable {
            background: linear-gradient(135deg, var(--agent-danger) 0%, #e74c3c 100%);
            color: white;
        }

        .status-lunch {
            background: linear-gradient(135deg, var(--agent-warning) 0%, #fd7e14 100%);
            color: white;
        }

        .quick-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-control-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }

        .quick-control-btn:hover {
            border-color: var(--agent-primary);
            color: var(--agent-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .schedule-day {
            background: #f8f9fa;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .schedule-day.active {
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.1) 0%, rgba(74, 144, 226, 0.1) 100%);
            border: 2px solid rgba(44, 90, 160, 0.3);
        }

        .day-header {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--agent-primary);
        }

        .time-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            align-items: center;
        }

        .form-control-custom {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--agent-primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
        }

        .btn-agent-primary {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-agent-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
            color: white;
        }

        .calendar-view {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .calendar-day {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .calendar-day.today {
            border-color: var(--agent-primary);
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.05) 0%, rgba(74, 144, 226, 0.05) 100%);
        }

        .calendar-day.has-appointments {
            border-color: var(--agent-warning);
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(253, 126, 20, 0.1) 100%);
        }

        .calendar-day.blocked {
            border-color: var(--agent-danger);
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(231, 76, 60, 0.1) 100%);
        }

        .calendar-date {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--agent-primary);
        }

        .calendar-day-name {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .calendar-status {
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-indicator.available { background: var(--agent-success); }
        .status-indicator.unavailable { background: var(--agent-danger); }
        .status-indicator.partial { background: var(--agent-warning); }

        .exception-item {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--agent-warning);
        }

        .exception-item.blocked {
            border-left-color: var(--agent-danger);
        }

        .exception-item.available {
            border-left-color: var(--agent-success);
        }

        .appointment-conflict {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(231, 76, 60, 0.1) 100%);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .nav-tabs-custom {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--agent-primary);
            border-bottom-color: var(--agent-primary);
            background: none;
        }

        @media (max-width: 768px) {
            .calendar-view {
                grid-template-columns: 1fr;
            }
            
            .quick-controls {
                grid-template-columns: 1fr;
            }
            
            .time-controls {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-user-role="agent">
    <!-- Include Navigation -->
    <?php include '../includes/agent_navigation.php'; ?>

    <div class="container mt-4">

        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-cog me-3"></i>Availability Management
                    </h1>
                    <p class="mb-0 opacity-90">Manage your schedule and control when clients can book appointments</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="manage_appointments.php" class="btn btn-light">
                        <i class="fas fa-calendar-alt me-2"></i>View Appointments
                    </a>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Status & Quick Controls -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="status-card status-<?= $current_status ?>">
                    <div class="status-indicator <?= $current_status ?> me-2"></div>
                    <h5 class="mb-2">
                        <?php
                        switch ($current_status) {
                            case 'available':
                                echo '<i class="fas fa-check-circle me-2"></i>Available Now';
                                break;
                            case 'lunch':
                                echo '<i class="fas fa-utensils me-2"></i>On Lunch Break';
                                break;
                            case 'unavailable':
                                echo '<i class="fas fa-times-circle me-2"></i>Currently Unavailable';
                                break;
                            default:
                                echo '<i class="fas fa-question-circle me-2"></i>Status Unknown';
                        }
                        ?>
                    </h5>
                    <p class="mb-0"><?= date('l, F j - g:i A') ?></p>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="quick-controls">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="quick_toggle">
                        <input type="hidden" name="toggle_type" value="available">
                        <input type="hidden" name="duration" value="120">
                        <button type="submit" class="quick-control-btn w-100">
                            <i class="fas fa-clock text-success mb-2 d-block"></i>
                            <strong>Available for 2 hours</strong>
                            <small class="d-block text-muted">Override current schedule</small>
                        </button>
                    </form>
                    
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="quick_toggle">
                        <input type="hidden" name="toggle_type" value="blocked">
                        <input type="hidden" name="duration" value="60">
                        <button type="submit" class="quick-control-btn w-100">
                            <i class="fas fa-ban text-danger mb-2 d-block"></i>
                            <strong>Block next hour</strong>
                            <small class="d-block text-muted">Temporary unavailable</small>
                        </button>
                    </form>
                    
                    <button type="button" class="quick-control-btn w-100" data-bs-toggle="modal" data-bs-target="#exceptionModal">
                        <i class="fas fa-calendar-plus text-warning mb-2 d-block"></i>
                        <strong>Add Exception</strong>
                        <small class="d-block text-muted">Special date/time</small>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs nav-tabs-custom">
            <li class="nav-item">
                <a class="nav-link <?= $action === 'calendar' ? 'active' : '' ?>" href="?action=calendar">
                    <i class="fas fa-calendar-week me-2"></i>Calendar View
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $action === 'schedule' ? 'active' : '' ?>" href="?action=schedule">
                    <i class="fas fa-clock me-2"></i>Weekly Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $action === 'exceptions' ? 'active' : '' ?>" href="?action=exceptions">
                    <i class="fas fa-exclamation-triangle me-2"></i>Exceptions
                </a>
            </li>
        </ul>

        <?php if ($action === 'calendar'): ?>
            <!-- Calendar View -->
            <div class="content-card">
                <div class="content-card-header">
                    <i class="fas fa-calendar-week me-2"></i>This Week - <?= date('M j', strtotime($current_week_start)) ?> to <?= date('M j, Y', strtotime($current_week_start . ' +6 days')) ?>
                </div>
                <div class="content-card-body">
                    <div class="calendar-view">
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $i => $day): 
                            $date = $week_dates[$i];
                            $is_today = $date === date('Y-m-d');
                            $day_lower = strtolower($day);
                            
                            // Check for appointments this day
                            $day_appointments = array_filter($upcoming_appointments, function($apt) use ($date) {
                                return date('Y-m-d', strtotime($apt['appointment_date'])) === $date;
                            });
                            
                            // Check availability for this day
                            $day_available = isset($weekly_schedule[$day_lower]) && $weekly_schedule[$day_lower]['available'];
                            
                            // Check for exceptions
                            $day_exceptions = array_filter($exceptions, function($exc) use ($date) {
                                return $exc['specific_date'] === $date;
                            });
                            
                            $has_exceptions = !empty($day_exceptions);
                            $has_appointments = !empty($day_appointments);
                            
                            $classes = ['calendar-day'];
                            if ($is_today) $classes[] = 'today';
                            if ($has_appointments) $classes[] = 'has-appointments';
                            if ($has_exceptions && !$day_exceptions[0]['is_available']) $classes[] = 'blocked';
                        ?>
                            <div class="<?= implode(' ', $classes) ?>">
                                <div class="calendar-day-name"><?= $day ?></div>
                                <div class="calendar-date"><?= date('j', strtotime($date)) ?></div>
                                
                                <div class="calendar-status">
                                    <?php if ($day_available): ?>
                                        <div class="status-indicator available"></div>
                                        <small>Available</small>
                                        <?php if (isset($weekly_schedule[$day_lower]['start_time'])): ?>
                                            <br><small><?= date('H:i', strtotime($weekly_schedule[$day_lower]['start_time'])) ?>-<?= date('H:i', strtotime($weekly_schedule[$day_lower]['end_time'])) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="status-indicator unavailable"></div>
                                        <small>Unavailable</small>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_appointments): ?>
                                        <br><small class="text-warning"><?= count($day_appointments) ?> appointment<?= count($day_appointments) > 1 ? 's' : '' ?></small>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_exceptions): ?>
                                        <br><small class="text-danger">Exception</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Upcoming Conflicts -->
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="mt-4">
                            <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Upcoming Appointments</h6>
                            <?php foreach (array_slice($upcoming_appointments, 0, 3) as $appointment): ?>
                                <div class="appointment-conflict">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($appointment['property_title']) ?></strong><br>
                                            <small><?= date('l, M j - g:i A', strtotime($appointment['appointment_date'])) ?></small>
                                        </div>
                                        <a href="manage_appointments.php?action=view&id=<?= $appointment['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action === 'schedule'): ?>
            <!-- Weekly Schedule Setup -->
            <div class="content-card">
                <div class="content-card-header">
                    <i class="fas fa-clock me-2"></i>Weekly Schedule Setup
                </div>
                <div class="content-card-body">
                    <form method="POST" id="weeklyScheduleForm">
                        <input type="hidden" name="action" value="update_weekly_schedule">
                        
                        <?php 
                        $days = [
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday', 
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday'
                        ];
                        
                        foreach ($days as $day_key => $day_name):
                            $day_data = $weekly_schedule[$day_key] ?? ['available' => false];
                        ?>
                            <div class="schedule-day <?= $day_data['available'] ? 'active' : '' ?>">
                                <div class="day-header">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="<?= $day_key ?>_available" 
                                               name="<?= $day_key ?>_available"
                                               <?= $day_data['available'] ? 'checked' : '' ?>
                                               onchange="toggleDay('<?= $day_key ?>')">
                                        <label class="form-check-label" for="<?= $day_key ?>_available">
                                            <?= $day_name ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="time-controls" id="<?= $day_key ?>_controls" 
                                     style="<?= $day_data['available'] ? '' : 'display: none;' ?>">
                                    <div>
                                        <label class="form-label small">Start Time</label>
                                        <input type="time" class="form-control form-control-custom" 
                                               name="<?= $day_key ?>_start" 
                                               value="<?= $day_data['start_time'] ?? '09:00' ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="form-label small">End Time</label>
                                        <input type="time" class="form-control form-control-custom" 
                                               name="<?= $day_key ?>_end" 
                                               value="<?= $day_data['end_time'] ?? '17:00' ?>">
                                    </div>
                                    
                                    <div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="<?= $day_key ?>_lunch" 
                                                   name="<?= $day_key ?>_lunch"
                                                   <?= ($day_data['lunch_break'] ?? false) ? 'checked' : '' ?>
                                                   onchange="toggleLunch('<?= $day_key ?>')">
                                            <label class="form-check-label small" for="<?= $day_key ?>_lunch">
                                                Lunch Break
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div id="<?= $day_key ?>_lunch_controls" 
                                         style="<?= ($day_data['lunch_break'] ?? false) ? '' : 'display: none;' ?>">
                                        <label class="form-label small">Lunch Start</label>
                                        <input type="time" class="form-control form-control-custom" 
                                               name="<?= $day_key ?>_lunch_start" 
                                               value="<?= $day_data['lunch_start'] ?? '12:00' ?>">
                                    </div>
                                    
                                    <div id="<?= $day_key ?>_lunch_end_controls" 
                                         style="<?= ($day_data['lunch_break'] ?? false) ? '' : 'display: none;' ?>">
                                        <label class="form-label small">Lunch End</label>
                                        <input type="time" class="form-control form-control-custom" 
                                               name="<?= $day_key ?>_lunch_end" 
                                               value="<?= $day_data['lunch_end'] ?? '13:00' ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn-agent-primary">
                                <i class="fas fa-save me-2"></i>Save Weekly Schedule
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="setPreset('business')">
                                <i class="fas fa-briefcase me-2"></i>Business Hours (9-5)
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="setPreset('extended')">
                                <i class="fas fa-clock me-2"></i>Extended Hours (8-7)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'exceptions'): ?>
            <!-- Exceptions Management -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-exclamation-triangle me-2"></i>Schedule Exceptions
                        </div>
                        <div class="content-card-body">
                            <?php if (!empty($exceptions)): ?>
                                <?php foreach ($exceptions as $exception): ?>
                                    <div class="exception-item <?= $exception['is_available'] ? 'available' : 'blocked' ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>
                                                    <?= date('l, F j, Y', strtotime($exception['specific_date'])) ?>
                                                    <?= $exception['is_available'] ? '(Available)' : '(Blocked)' ?>
                                                </strong>
                                                <?php if ($exception['start_time'] && $exception['end_time']): ?>
                                                    <br><small><?= date('g:i A', strtotime($exception['start_time'])) ?> - <?= date('g:i A', strtotime($exception['end_time'])) ?></small>
                                                <?php else: ?>
                                                    <br><small>All Day</small>
                                                <?php endif; ?>
                                            </div>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_exception">
                                                <input type="hidden" name="exception_id" value="<?= $exception['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                        onclick="return confirm('Delete this exception?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Schedule Exceptions</h5>
                                    <p class="text-muted">You haven't set any exceptions to your regular schedule.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-plus me-2"></i>Quick Actions
                        </div>
                        <div class="content-card-body">
                            <div class="d-grid gap-3">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exceptionModal">
                                    <i class="fas fa-calendar-plus me-2"></i>Add Exception
                                </button>
                                <a href="?action=calendar" class="btn btn-outline-secondary">
                                    <i class="fas fa-calendar-week me-2"></i>Calendar View
                                </a>
                                <a href="manage_appointments.php" class="btn btn-outline-success">
                                    <i class="fas fa-calendar-alt me-2"></i>View Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Exception Modal -->
    <div class="modal fade" id="exceptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Schedule Exception</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_exception">
                        
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control form-control-custom" name="exception_date" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Exception Type</label>
                            <select class="form-control form-control-custom" name="exception_type" required>
                                <option value="blocked">Block Time (Unavailable)</option>
                                <option value="available">Force Available (Override regular schedule)</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Time (Optional)</label>
                                    <input type="time" class="form-control form-control-custom" name="start_time">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Time (Optional)</label>
                                    <input type="time" class="form-control form-control-custom" name="end_time">
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle me-2"></i>Leave times blank to block/allow the entire day.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Exception</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/navigation.js"></script>

    <script>
        function toggleDay(day) {
            const checkbox = document.getElementById(day + '_available');
            const controls = document.getElementById(day + '_controls');
            const scheduleDay = controls.closest('.schedule-day');
            
            if (checkbox.checked) {
                controls.style.display = 'grid';
                scheduleDay.classList.add('active');
            } else {
                controls.style.display = 'none';
                scheduleDay.classList.remove('active');
            }
        }

        function toggleLunch(day) {
            const checkbox = document.getElementById(day + '_lunch');
            const lunchControls = document.getElementById(day + '_lunch_controls');
            const lunchEndControls = document.getElementById(day + '_lunch_end_controls');
            
            if (checkbox.checked) {
                lunchControls.style.display = 'block';
                lunchEndControls.style.display = 'block';
            } else {
                lunchControls.style.display = 'none';
                lunchEndControls.style.display = 'none';
            }
        }

        function setPreset(type) {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            const weekend = ['saturday', 'sunday'];
            
            // Clear all first
            [...days, ...weekend].forEach(day => {
                document.getElementById(day + '_available').checked = false;
                toggleDay(day);
            });
            
            if (type === 'business') {
                days.forEach(day => {
                    document.getElementById(day + '_available').checked = true;
                    document.querySelector(`input[name="${day}_start"]`).value = '09:00';
                    document.querySelector(`input[name="${day}_end"]`).value = '17:00';
                    document.getElementById(day + '_lunch').checked = true;
                    document.querySelector(`input[name="${day}_lunch_start"]`).value = '12:00';
                    document.querySelector(`input[name="${day}_lunch_end"]`).value = '13:00';
                    toggleDay(day);
                    toggleLunch(day);
                });
            } else if (type === 'extended') {
                days.forEach(day => {
                    document.getElementById(day + '_available').checked = true;
                    document.querySelector(`input[name="${day}_start"]`).value = '08:00';
                    document.querySelector(`input[name="${day}_end"]`).value = '19:00';
                    document.getElementById(day + '_lunch').checked = true;
                    document.querySelector(`input[name="${day}_lunch_start"]`).value = '12:00';
                    document.querySelector(`input[name="${day}_lunch_end"]`).value = '13:00';
                    toggleDay(day);
                    toggleLunch(day);
                });
            }
        }

        // Auto-update end time if start time changes
        document.querySelectorAll('input[type="time"]').forEach(input => {
            if (input.name.includes('_start')) {
                input.addEventListener('change', function() {
                    const day = this.name.replace('_start', '');
                    const endInput = document.querySelector(`input[name="${day}_end"]`);
                    if (endInput && !endInput.value) {
                        const startTime = new Date('2000-01-01 ' + this.value);
                        startTime.setHours(startTime.getHours() + 8); // 8 hour workday
                        endInput.value = startTime.toTimeString().slice(0, 5);
                    }
                });
            }
        });
    </script>
    <script src="../assets/js/agent_navigation.js"></script>

</body>
</html>