<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointments - Omnes Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/appointment.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
<div class="container" style="max-width: 900px;">
    <div class="text-center mt-5 mb-4">
        <h1 class="page-title">Book an Appointment</h1>
        <div class="page-subtitle">Select an agent and choose a free day to schedule your visit</div>
    </div>

    <!-- Agent Selection -->
    <div class="calendar-agent-select mb-4">
        <label for="agentSelect" class="form-label fw-semibold">Choose an Agent:</label>
        <select id="agentSelect" class="form-select" style="max-width: 320px;">
            <option value="john" selected>John Doe</option>
            <option value="jane">Jane Smith</option>
            <option value="alex">Alex Martin</option>
        </select>
    </div>

    <!-- Modern Calendar -->
    <div class="calendar-container mb-4">
        <div class="calendar-header">
            <button class="btn btn-outline-secondary btn-sm" id="prevMonthBtn">&lt;</button>
            <div id="calendarMonthYear" class="fw-bold fs-5"></div>
            <button class="btn btn-outline-secondary btn-sm" id="nextMonthBtn">&gt;</button>
        </div>
        <table class="calendar-table table-bordered">
            <thead>
                <tr>
                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                </tr>
            </thead>
            <tbody id="calendarBody">
                <!-- Calendar days will be injected here -->
            </tbody>
        </table>
        <div class="calendar-legend">
            <span><span class="legend-free"></span>Free</span>
            <span><span class="legend-busy"></span>Busy</span>
            <span><span class="legend-selected"></span>Selected</span>
        </div>
    </div>

    <!-- Time Slot Selection -->
    <div id="timeSlotsSection" class="mb-4" style="display:none;">
        <h5 class="mb-3">Available Time Slots for <span id="selectedDateLabel"></span></h5>
        <div id="timeSlots" class="d-flex flex-wrap gap-2"></div>
        <button id="confirmBtn" class="btn btn-primary mt-3" style="display:none;">Confirm Appointment</button>
    </div>

    <!-- Contact/Help -->
    <div class="footer-btn text-center mt-4">
        <button class="btn btn-outline-secondary">Need help? Contact us</button>
    </div>
</div>

<script>
// Debug version to fix the selectedDateLabel issue
console.log('Debug: appointment.js should handle date selection correctly');
// The issue is likely in appointment.js - check the selectDate function
// Make sure it uses the correct month/year when setting selectedDateLabel
</script>
<script src="../assets/js/appointment.js"></script>
<script src="../assets/js/navigation.js"></script>
</body>
</html>
