<?php
// ========================================
// LOGOUT PAGE
// Secure logout with proper session handling
// ========================================

session_start();
require_once '../includes/functions.php';

// Log the logout event if user was logged in
if (isLoggedIn()) {
    logSecurityEvent('user_logout', 'User logged out: ' . ($_SESSION['email'] ?? 'unknown'));
}

// Destroy all session data
$_SESSION = array();

// Delete session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any remember me cookies
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login with success message
header('Location: login.php?message=' . urlencode('Vous avez été déconnecté avec succès.'));
exit();
?>