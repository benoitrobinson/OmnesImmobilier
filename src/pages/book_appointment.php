<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$pdo = $database->getConnection();

$property_id = $_GET['property_id'] ?? null;
$agent_id = $_GET['agent_id'] ?? null;
$error = '';
$success = '';

if (!$property_id) {
    redirect('explore.php');
}

// Get property details with agent information
try {
    $query = "SELECT p.*, u.first_name, u.last_name, u.email as agent_email, u.phone as agent_phone,
              a.cv_file_path
              FROM properties p 
              LEFT JOIN users u ON p.agent_id = u.id 
              LEFT JOIN agents a ON p.agent_id = a.user_id
              WHERE p.id = :property_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['property_id' => $property_id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$property) {
        $error = "Property not found.";
    } else {
        // If agent exists but CV is missing, check directly from agents table
        if (!empty($property['agent_id']) && empty($property['cv_file_path'])) {
            $cv_query = "SELECT cv_file_path FROM agents WHERE user_id = :agent_id";
            $cv_stmt = $pdo->prepare($cv_query);
            $cv_stmt->execute(['agent_id' => $property['agent_id']]);
            $cv_result = $cv_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cv_result && !empty($cv_result['cv_file_path'])) {
                $property['cv_file_path'] = $cv_result['cv_file_path'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Property fetch error: " . $e->getMessage());
    $error = "Error loading property details.";
}

// Handle AJAX request for available times
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_times') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $date = $_GET['date'] ?? '';
    $agent_id = $_GET['agent_id'] ?? '';
    
    if (empty($date) || empty($agent_id)) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    try {
        $day_of_week = date('l', strtotime($date));
        $available_times = [];
        
        // Get general availability for this day (is_available = 1, no specific_date)
        $query = "SELECT start_time, end_time FROM agent_availability 
                 WHERE agent_id = ? AND day_of_week = ? AND specific_date IS NULL AND is_available = 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$agent_id, $day_of_week]);
        $general_availability = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$general_availability) {
            echo json_encode([]);
            exit;
        }
        
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
            
            // Only show time if not blocked and no existing appointment
            if (!$is_blocked && !$has_appointment) {
                $available_times[] = [
                    'value' => $time_slot,
                    'display' => $time_display
                ];
            }
            
            $current += 1800; // Add 30 minutes
        }
        
        echo json_encode($available_times);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error']);
        exit;
    }
}

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $location = $_POST['location'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    if (empty($appointment_date) || empty($appointment_time) || empty($location)) {
        $error = "Please select date, time, and meeting location for your appointment.";
    } else {
        // Combine date and time
        $appointment_datetime = $appointment_date . ' ' . $appointment_time;
        
        // Validate that appointment is in the future
        if (strtotime($appointment_datetime) <= time()) {
            $error = "Please select a future date and time.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Insert appointment
                $insert_query = "INSERT INTO appointments (client_id, agent_id, property_id, appointment_date, status, location) 
                                VALUES (:client_id, :agent_id, :property_id, :appointment_date, 'scheduled', :location)";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->execute([
                    'client_id' => $_SESSION['user_id'],
                    'agent_id' => $property['agent_id'],
                    'property_id' => $property_id,
                    'appointment_date' => $appointment_datetime,
                    'location' => $location
                ]);
                
                // Block the time slot
                $appointment_datetime_obj = new DateTime($appointment_datetime);
                $day_of_week = $appointment_datetime_obj->format('l');
                $specific_date = $appointment_datetime_obj->format('Y-m-d');
                $start_time = $appointment_datetime_obj->format('H:i:s');
                $end_datetime = clone $appointment_datetime_obj;
                $end_datetime->add(new DateInterval('PT30M'));
                $end_time = $end_datetime->format('H:i:s');
                
                $block_query = "INSERT INTO agent_availability 
                               (agent_id, day_of_week, specific_date, start_time, end_time, is_available, availability_type)
                               VALUES (?, ?, ?, ?, ?, 0, 'exception')";
                $block_stmt = $pdo->prepare($block_query);
                $block_stmt->execute([
                    $property['agent_id'],
                    $day_of_week,
                    $specific_date,
                    $start_time,
                    $end_time
                ]);
                
                $pdo->commit();
                $success = "Your appointment request has been submitted successfully! The agent will contact you to confirm.";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Appointment booking error: " . $e->getMessage());
                $error = "Error booking appointment. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Omnes Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    
    <style>
        body {
            background: #f8f9fa;
            padding-top: 0 !important; /* Remove padding that creates gap */
            margin: 0; /* Ensure no default margin */
        }
        
        /* Fix for navigation positioning */
        .navbar {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
        
        /* Add margin to container instead of body padding */
        .main-container {
            margin-top: 2rem;
            padding-top: 1rem;
        }
        
        .appointment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .property-summary {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: white;
            padding: 2rem;
        }
        
        .agent-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #b8941f 0%, #e6c036 100%);
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="container main-container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <div class="mt-3">
                            <a href="explore.php" class="btn btn-outline-primary me-2">Browse More Properties</a>
                            <a href="../client/dashboard.php" class="btn btn-primary">View My Appointments</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($property && !$success): ?>
                    <div class="appointment-card">
                        <!-- Property Summary -->
                        <div class="property-summary">
                            <h2><i class="fas fa-calendar-alt me-2"></i>Book an Appointment</h2>
                            <h4><?= htmlspecialchars($property['title']) ?></h4>
                            <p class="mb-0">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?= htmlspecialchars($property['address_line1'] . ', ' . $property['city']) ?>
                            </p>
                            <p class="h5 mt-2 mb-0">€<?= number_format($property['price'], 0, ',', ' ') ?></p>
                        </div>
                        
                        <div class="p-4">
                            <!-- Agent Information -->
                            <?php if ($property['first_name']): ?>
                                <div class="agent-info">
                                    <h6><i class="fas fa-user-tie me-2"></i>Your Agent</h6>
                                    <div class="d-flex align-items-start">
                                        <!-- Agent Avatar -->
                                        <div class="me-3">
                                            <div style="width: 60px; height: 60px; background: #d4af37; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
                                                <?= strtoupper(substr($property['first_name'], 0, 1) . substr($property['last_name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Agent Details -->
                                        <div class="flex-grow-1">
                                            <div class="fw-bold fs-5 mb-1"><?= htmlspecialchars($property['first_name'] . ' ' . $property['last_name']) ?></div>
                                            
                                            <!-- Agent Contact Info -->
                                            <div class="mb-2">
                                                <?php if ($property['agent_email']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                                        <span><?= htmlspecialchars($property['agent_email']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($property['agent_phone']): ?>
                                                    <div>
                                                        <i class="fas fa-phone me-2 text-muted"></i>
                                                        <span><?= htmlspecialchars($property['agent_phone']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- View CV Button - Always show but conditionally enable -->
                                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#agentCVModal"
                                                    <?= empty($property['cv_file_path']) ? 'disabled' : '' ?>>
                                                <i class="fas fa-file-alt me-1"></i> 
                                                <?= empty($property['cv_file_path']) ? 'CV Not Available' : 'View CV' ?>
                                            </button>
                                            
                                            <!-- Debug information - remove in production -->
                                            <?php if (isset($_GET['debug'])): ?>
                                                <div class="mt-2 p-2 bg-light rounded">
                                                    <small>Debug: CV Path = "<?= htmlspecialchars($property['cv_file_path'] ?? 'null') ?>"</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Booking Form -->
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="appointment_date" class="form-label">Preferred Date *</label>
                                        <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                        <small id="date-help" class="text-muted">Select a date to see available times</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="appointment_time" class="form-label">Preferred Time *</label>
                                        <select class="form-select" id="appointment_time" name="appointment_time" required disabled>
                                            <option value="">Select a date first</option>
                                        </select>
                                        <small id="time-help" class="text-muted">Please select a date to see available times.</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="location" class="form-label">Meeting Location *</label>
                                    <select class="form-select" id="location" name="location" required>
                                        <option value="">Select meeting location</option>
                                        <option value="office">Office Visit</option>
                                        <option value="visio">Online Meeting (Visio)</option>
                                    </select>
                                    <small class="text-muted">Choose whether to meet at the office or have an online meeting.</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="message" class="form-label">Message (Optional)</label>
                                    <textarea class="form-control" id="message" name="message" rows="4" 
                                              placeholder="Any specific requirements or questions about the property..."></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="explore.php" class="btn btn-outline-secondary me-md-2">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Properties
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- CV Modal -->
    <?php if (!empty($property['cv_file_path'])): ?>
    <div class="modal fade" id="agentCVModal" tabindex="-1" aria-labelledby="agentCVModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="agentCVModalLabel">
                        <i class="fas fa-file-alt me-2"></i>
                        <?= htmlspecialchars($property['first_name'] . ' ' . $property['last_name']) ?>'s CV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <?php
                    $file_ext = strtolower(pathinfo($property['cv_file_path'], PATHINFO_EXTENSION));
                    $cv_path = '../' . $property['cv_file_path']; // Make sure we have the correct path
                    
                    if ($file_ext == 'pdf' && file_exists($cv_path)): ?>
                        <!-- PDF Viewer - Fixed path -->
                        <div class="ratio ratio-16x9" style="min-height: 600px;">
                            <iframe src="../<?= $property['cv_file_path'] ?>" 
                                    allowfullscreen allow="fullscreen"
                                    class="w-100 h-100 border-0"></iframe>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p>CV file not found or cannot be previewed directly.</p>
                            <?php if (file_exists($cv_path)): ?>
                                <a href="../<?= $property['cv_file_path'] ?>" 
                                   class="btn btn-primary" target="_blank">
                                    <i class="fas fa-download me-2"></i>Download CV
                                </a>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    CV file not found at expected path: <?= htmlspecialchars($property['cv_file_path']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Debug info -->
                    <?php if (isset($_GET['debug'])): ?>
                    <div class="p-3 bg-light border-top">
                        <h6>Debug Information:</h6>
                        <ul>
                            <li>CV path: <?= htmlspecialchars($property['cv_file_path'] ?? 'Not set') ?></li>
                            <li>Full path: <?= htmlspecialchars($cv_path ?? 'Not available') ?></li>
                            <li>File exists: <?= file_exists($cv_path) ? 'Yes' : 'No' ?></li>
                            <li>File extension: <?= $file_ext ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if (file_exists($cv_path)): ?>
                    <a href="../<?= $property['cv_file_path'] ?>" 
                       class="btn btn-primary" target="_blank" download>
                        <i class="fas fa-download me-2"></i>Download CV
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Set minimum date to tomorrow
    document.getElementById('appointment_date').min = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    
    // Update available times when date changes
    document.getElementById('appointment_date').addEventListener('change', function() {
        const selectedDate = this.value;
        const timeSelect = document.getElementById('appointment_time');
        const timeHelp = document.getElementById('time-help');
        const agentId = <?= $property['agent_id'] ?? 'null' ?>;
        
        if (!selectedDate || !agentId) {
            timeSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Select a date first</option>';
            timeHelp.textContent = 'Please select a date to see available times.';
            timeHelp.className = 'text-muted';
            return;
        }
        
        // Show loading state
        timeSelect.disabled = true;
        timeSelect.innerHTML = '<option value="">Loading available times...</option>';
        timeHelp.textContent = 'Loading available times...';
        timeHelp.className = 'text-info';
        
        // Use the test file for now while we debug
        const timestamp = new Date().getTime();
        const url = `test_availability.php?date=${selectedDate}&agent_id=${agentId}&_=${timestamp}`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json(); // Use .json() directly since we now have clean JSON
            })
            .then(times => {
                // Clear any previous options
                timeSelect.innerHTML = '';
                
                if (times.error) {
                    // Handle error in response
                    timeSelect.innerHTML = '<option value="">Error loading times</option>';
                    timeSelect.disabled = true;
                    timeHelp.textContent = 'Error: ' + times.error;
                    timeHelp.className = 'text-danger';
                } else if (times.length === 0) {
                    // No available times
                    timeSelect.innerHTML = '<option value="" disabled>No available times</option>';
                    timeSelect.disabled = true;
                    timeHelp.textContent = 'Agent is not available on this date. Please select a different date.';
                    timeHelp.className = 'text-warning';
                } else {
                    // Add default "select time" option
                    timeSelect.innerHTML = '<option value="">Select time</option>';
                    
                    // Add available times
                    times.forEach(time => {
                        const option = document.createElement('option');
                        option.value = time.value;
                        option.textContent = time.display;
                        timeSelect.appendChild(option);
                    });
                    
                    // Enable the select
                    timeSelect.disabled = false;
                    timeHelp.textContent = `${times.length} time slots available`;
                    timeHelp.className = 'text-success';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                timeSelect.innerHTML = '<option value="">Error loading times</option>';
                timeSelect.disabled = true;
                timeHelp.textContent = 'Error loading available times. Please try again.';
                timeHelp.className = 'text-danger';
            });
    });
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const date = document.getElementById('appointment_date').value;
        const time = document.getElementById('appointment_time').value;
        const location = document.getElementById('location').value;
        
        if (!date || !time || !location) {
            e.preventDefault();
            alert('Please select date, time, and meeting location for your appointment.');
        }
    });
</script>
</body>
</html>