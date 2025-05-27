<?php
session_start();
require_once '../includes/functions.php';

// Log the logout event if user was logged in
if (isLoggedIn()) {
    $user_email = $_SESSION['email'] ?? 'unknown';
    
    // Log security event
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('user_logout', 'User logged out: ' . $user_email);
    }
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// Destroy the session
session_unset();
session_destroy();

// Start a new session for the success message
session_start();
session_regenerate_id(true);

// Redirect to login page with success message
redirect('login.php?logout=success');
?>