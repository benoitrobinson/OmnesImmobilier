<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
// Check if user is logged in
if (!isLoggedIn()) {
$_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
redirect('../auth/login.php');
}
$database = Database::getInstance();
$db = $database->getConnection();
$property_id = $_GET['property_id'] ?? null;
$agent_id = $_GET['agent_id'] ?? null;
$error = '';
$success = '';
// Handle form submission for booking appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes            = trim($_POST['notes'] ?? '');

    // Read IDs from POST when submitting, else from GET
    $property_id = $_POST['property_id']  ?? $_GET['property_id'] ?? null;
    $agent_id    = $_POST['agent_id']     ?? $_GET['agent_id']    ?? null;

    // Validation
    if (empty($appointment_date) || empty($appointment_time)) {
        $error = 'Please select both date and time for your appointment.';
    } elseif (empty($property_id) || empty($agent_id)) {
        $error = 'Missing property or agent information.';
    } else {
        // Combine date and time
        $appointment_datetime = $appointment_date . ' ' . $appointment_time;
        
        // Validate the datetime is in the future
        if (strtotime($appointment_datetime) < time()) {
            $error = 'Please select a future date and time.';
        } else {
            try {
                // Check if the time slot is already taken
                $check_stmt = $db->prepare("
                    SELECT id FROM appointments 
                    WHERE agent_id = ? AND appointment_date = ? AND status = 'scheduled'
                ");
                $check_stmt->execute([$agent_id, $appointment_datetime]);
                
                if ($check_stmt->rowCount() > 0) {
                    $error = 'This time slot is already booked. Please select another time.';
                } else {
                    // Get property address for location
                    $location_stmt = $db->prepare("SELECT address_line1, city FROM properties WHERE id = ?");
                    $location_stmt->execute([$property_id]);
                    $property_info = $location_stmt->fetch(PDO::FETCH_ASSOC);
                    $location = $property_info['address_line1'] . ', ' . $property_info['city'];
                    
                    // Insert the appointment
                    $insert_stmt = $db->prepare("
                        INSERT INTO appointments (client_id, agent_id, property_id, appointment_date, location, status, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                    ");
                    
                    if ($insert_stmt->execute([$_SESSION['user_id'], $agent_id, $property_id, $appointment_datetime, $location, $notes])) {
                        $_SESSION['success_message'] = 'Your appointment has been successfully booked! You will receive a confirmation shortly.';
                        redirect('../client/dashboard.php?section=appointments');
                    } else {
                        $error = 'Error booking appointment. Please try again.';
                    }
                }
            } catch (Exception $e) {
                error_log("Appointment booking error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        }
    }
}

// Get property details
$property = null;
if ($property_id) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as agent_name, u.id as agent_user_id,
                   a.agency_name, a.agency_phone, a.agency_email
            FROM properties p
            JOIN agents a ON p.agent_id = a.user_id
            JOIN users u ON a.user_id = u.id
            WHERE p.id = ?
        ");
$stmt->execute([$property_id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($property) {
        $agent_id = $property['agent_user_id'];
    } else {
        $error = 'Property not found.';
    }
} catch (Exception $e) {
    $error = 'Error loading property details.';
}
}
// Get agent details
$agent = null;
if ($agent_id && !$error) {
    try {
        $agent_stmt = $db->prepare("
            SELECT u.*, a.agency_name, a.agency_phone, a.agency_email
            FROM users u
            JOIN agents a ON u.id = a.user_id
            WHERE u.id = ?
        ");
        $agent_stmt->execute([$agent_id]);
        $agent = $agent_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            $error = 'Agent not found.';
        }
    } catch (Exception $e) {
        $error = 'Error loading agent information.';
    }
}
// Get agent availability
$agent_availability = [];
// Get agent availability
$agent_availability = [];
if ($agent_id && !$error) {
    try {
        $availability_stmt = $db->prepare("
            SELECT day_of_week, start_time, end_time
            FROM agent_availability
            WHERE agent_id = ? AND is_available = 1
            ORDER BY
                CASE day_of_week
                    WHEN 'Monday' THEN 1
                    WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3
                    WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5
                    WHEN 'Saturday' THEN 6
                    WHEN 'Sunday' THEN 7
                END
        ");
        $availability_stmt->execute([$agent_id]);
        $agent_availability = $availability_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Continue without availability data
        error_log("Availability loading error: " . $e->getMessage());
    }
}
// Get existing appointments for this agent to block taken time slots
$taken_slots = [];
if ($agent_id) {
    try {
        $taken_stmt = $db->prepare("
            SELECT DATE(appointment_date) AS appointment_date,
                   TIME(appointment_date) AS appointment_time
            FROM appointments
            WHERE agent_id = ? AND status = 'scheduled' AND appointment_date >= NOW()
        ");
        $taken_stmt->execute([$agent_id]);
        while ($row = $taken_stmt->fetch(PDO::FETCH_ASSOC)) {
            $taken_slots[$row['appointment_date']][] = $row['appointment_time'];
        }
    } catch (Exception $e) {
        error_log("Taken slots load error: " . $e->getMessage());
    }
}

// End PHP before outputting HTML
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Omnes Real Estate</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/navigation.css" rel="stylesheet">
<style>
    body {
        background: #f8f9fa;
        padding-top: 80px;
    }
    
    .booking-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
    }
    
    .booking-header {
        background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
        color: white;
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .property-card, .agent-card, .booking-form {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border: 1px solid #e9ecef;
    }
    
    .agent-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.5rem;
        margin-right: 1rem;
        border: 3px solid white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .property-price {
        color: #d4af37;
        font-weight: 700;
        font-size: 1.5rem;
    }
    
    .form-control, .form-select {
        border: 2px solid #e9ecef;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #d4af37;
        box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.15);
    }
    
    .btn-book {
        background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 0.75rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-book:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        color: white;
    }
    
    .time-slot {
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 0.5rem 1rem;
        margin: 0.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        background: white;
        font-size: 0.9rem;
    }
    
    .time-slot:hover {
        border-color: #d4af37;
        background: rgba(212, 175, 55, 0.1);
    }
    
    .time-slot.selected {
        background: #d4af37;
        color: white;
        border-color: #d4af37;
    }
    
    .time-slot.taken {
        background: #f8f9fa;
        color: #6c757d;
        cursor: not-allowed;
        opacity: 0.6;
        text-decoration: line-through;
    }
    
    .availability-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .day-schedule {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .day-schedule:last-child {
        border-bottom: none;
    }
</style>
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
<div class="booking-container">
    <!-- Header -->
    <div class="booking-header">
        <h1><i class="fas fa-calendar-plus me-2"></i>Book Your Property Viewing</h1>
        <p class="mb-0">Schedule a personalized tour with our professional agent</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($property): ?>
    <!-- Property Information -->
    <div class="property-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><?= htmlspecialchars($property['title']) ?></h3>
                <p class="text-muted mb-2">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    <?= htmlspecialchars($property['address_line1'] . ', ' . $property['city']) ?>
                </p>
                <div class="property-price mb-2">€<?= number_format($property['price'], 0, ',', ' ') ?></div>
                <div class="property-features">
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-tag me-1"></i><?= ucfirst($property['property_type']) ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <img
                    src="../assets/images/property<?= $property['id'] ?>-2.jpg"
                    alt="<?= htmlspecialchars($property['title']) ?>"
                    class="img-fluid rounded"
                    style="max-height:150px; object-fit:cover;"
                    onerror="this.src='../assets/images/placeholder.jpg'">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($agent): ?>
    <!-- Agent Information -->
    <div class="agent-card">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="agent-avatar">
                    <?= strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1)) ?>
                </div>
            </div>
            <div class="col">
                <h5 class="mb-1"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></h5>
                <p class="text-muted mb-1"><?= htmlspecialchars($agent['agency_name']) ?></p>
                <div class="contact-info">
                    <small class="text-muted">
                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($agent['agency_phone']) ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($agent['agency_email']) ?>
                    </small>
                </div>
            </div>
            <div class="col-auto">
                <a href="tel:<?= htmlspecialchars($agent['agency_phone']) ?>" class="btn btn-outline-primary">
                    <i class="fas fa-phone me-1"></i>Call Now
                </a>
            </div>
        </div>
        
        <!-- Agent Availability Schedule -->
        <?php if (!empty($agent_availability)): ?>
        <div class="availability-info mt-3">
            <h6><i class="fas fa-clock me-2"></i>Agent Availability</h6>
            <?php foreach ($agent_availability as $schedule): ?>
                <div class="day-schedule">
                    <span class="fw-semibold"><?= $schedule['day_of_week'] ?></span>
                    <span class="text-muted">
                        <?= date('g:i A', strtotime($schedule['start_time'])) ?> - 
                        <?= date('g:i A', strtotime($schedule['end_time'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($property && $agent): ?>
    <!-- Booking Form -->
    <div class="booking-form">
        <h5 class="mb-4"><i class="fas fa-clock me-2"></i>Select Your Preferred Time</h5>
        
        <form method="POST" action="" id="bookingForm">
            <!-- persist IDs -->
            <input type="hidden" name="property_id" value="<?= htmlspecialchars($property_id) ?>">
            <input type="hidden" name="agent_id"    value="<?= htmlspecialchars($agent_id) ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="appointment_date" class="form-label">Select Date</label>
                        <input type="date" 
                               class="form-control" 
                               id="appointment_date" 
                               name="appointment_date"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                               required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Select Time</label>
                        <input type="hidden" id="appointment_time" name="appointment_time" required>
                        <div class="time-slots" id="timeSlots">
                            <p class="text-muted">Please select a date first to see available times</p>
                        </div>
                        <small class="text-muted">Click on a time slot to select it</small>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notes (Optional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" 
                          placeholder="Any special requests or information for the agent..."></textarea>
            </div>
            
            <div class="d-flex gap-3">
                <button type="submit" class="btn-book" id="bookBtn" disabled>
                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                </button>
                <a href="explore.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Properties
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!$property_id): ?>
    <!-- No Property Selected -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
            <h5 class="text-muted">No Property Selected</h5>
            <p class="text-muted">Please select a property first to book an appointment.</p>
            <a href="explore.php" class="btn btn-outline-primary">
                <i class="fas fa-search me-2"></i>Browse Properties
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    // Agent availability data for JavaScript
    const agentAvailability = <?= json_encode($agent_availability) ?>;
    const takenSlots = <?= json_encode($taken_slots) ?>;
    const agentId = <?= json_encode($agent_id) ?>;
    
    // Generate time slots based on agent availability
    function generateTimeSlots(selectedDate) {
        const dayOfWeek = new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long' });
        const timeSlots = document.getElementById('timeSlots');
        const timeInput = document.getElementById('appointment_time');
        const bookBtn = document.getElementById('bookBtn');
        
        // Find availability for this day
        const dayAvailability = agentAvailability.find(avail => avail.day_of_week === dayOfWeek);
        
        if (!dayAvailability) {
            timeSlots.innerHTML = '<p class="text-muted">Agent is not available on ' + dayOfWeek + 's</p>';
            timeInput.value = '';
            bookBtn.disabled = true;
            return;
        }
        
        // Generate hourly slots between start and end time
        const startTime = dayAvailability.start_time;
        const endTime = dayAvailability.end_time;
        
        let slotsHtml = '';
        let currentTime = new Date('2000-01-01 ' + startTime);
        const endTimeObj = new Date('2000-01-01 ' + endTime);
        
        while (currentTime < endTimeObj) {
            const timeStr = currentTime.toTimeString().substring(0, 8);
            const displayTime = currentTime.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
            
            // Check if this slot is taken
            const isTaken = takenSlots[selectedDate] && takenSlots[selectedDate].includes(timeStr);
            const slotClass = isTaken ? 'time-slot taken' : 'time-slot';
            const clickable = isTaken ? '' : `onclick="selectTimeSlot('${timeStr}', this)"`;
            
            slotsHtml += `<div class="${slotClass}" data-time="${timeStr}" ${clickable}>${displayTime}</div>`;
            
            // Add 1 hour
            currentTime.setHours(currentTime.getHours() + 1);
        }
        
        timeSlots.innerHTML = slotsHtml;
        timeInput.value = '';
        bookBtn.disabled = true;
    }
    
    // Select time slot
    function selectTimeSlot(time, element) {
        // Remove selection from other slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });
        
        // Select this slot
        element.classList.add('selected');
        document.getElementById('appointment_time').value = time;
        document.getElementById('bookBtn').disabled = false;
    }
    
    // Date change handler
    document.getElementById('appointment_date').addEventListener('change', function() {
        if (this.value) {
            generateTimeSlots(this.value);
        }
    });
    
    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const date = document.getElementById('appointment_date').value;
        const time = document.getElementById('appointment_time').value;
        
        if (!date || !time) {
            e.preventDefault();
            alert('Please select both date and time for your appointment.');
        }
    });
</script>
</body>
</html>

