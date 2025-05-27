<?php
// ========================================
// HELPER FUNCTIONS
// Global utility functions used across the application
// ========================================

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirect to specified URL and exit
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Require user to be logged in, redirect to login if not
 * @param string $redirect_to Optional redirect after login
 */
function requireLogin($redirect_to = null) {
    if (!isLoggedIn()) {
        $login_url = '../auth/login.php';
        if ($redirect_to) {
            $login_url .= '?redirect=' . urlencode($redirect_to);
        }
        redirect($login_url);
    }
}

/**
 * Require specific user role
 * @param string|array $required_roles
 */
function requireRole($required_roles) {
    requireLogin();
    
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    if (!in_array($_SESSION['role'], $required_roles)) {
        redirect('../index.php?error=' . urlencode('Accès non autorisé'));
    }
}

/**
 * Get current user information
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Sanitize output for HTML display
 * @param string $string
 * @return string
 */
function clean($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format phone number for display
 * @param string $phone
 * @return string
 */
function formatPhone($phone) {
    // Remove all non-digits
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format French phone numbers
    if (strlen($phone) === 10) {
        return preg_replace('/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', '$1 $2 $3 $4 $5', $phone);
    }
    
    return $phone;
}

/**
 * Generate a random token for security purposes
 * @param int $length
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validate French postal code
 * @param string $postalCode
 * @return bool
 */
function isValidPostalCode($postalCode) {
    return preg_match('/^[0-9]{5}$/', $postalCode);
}

/**
 * Get user's full name
 * @param array $user User array from database
 * @return string
 */
function getFullName($user) {
    return trim($user['first_name'] . ' ' . $user['last_name']);
}

/**
 * Check if email domain is from Omnes Education
 * @param string $email
 * @return bool
 */
function isOmnesEmail($email) {
    $omnes_domains = ['omnes.fr', 'edu.ece.fr', 'inseec.com'];
    $domain = substr(strrchr($email, "@"), 1);
    return in_array($domain, $omnes_domains);
}

/**
 * Log security events
 * @param string $event
 * @param string $details
 */
function logSecurityEvent($event, $details = '') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? null,
        'event' => $event,
        'details' => $details
    ];
    
    // In a real application, you'd write this to a log file or database
    error_log('SECURITY: ' . json_encode($log_entry));
}

/**
 * Check if current user is a client
 * @return bool
 */
function isClient() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'client';
}

/**
 * Check if current user is an agent
 * @return bool
 */
function isAgent() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'agent';
}

/**
 * Check if current user is an admin
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure password hash
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format currency for display
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatCurrency($amount, $currency = 'EUR') {
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

/**
 * Get user role display name
 * @param string $role
 * @return string
 */
function getRoleDisplayName($role) {
    switch ($role) {
        case 'admin':
            return 'Administrator';
        case 'agent':
            return 'Real Estate Agent';
        case 'client':
            return 'Client';
        default:
            return 'User';
    }
}

/**
 * Check if user has permission for specific action
 * @param string $action
 * @return bool
 */
function hasPermission($action) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role = $_SESSION['role'];
    
    switch ($action) {
        case 'view_dashboard':
            return in_array($role, ['admin', 'agent', 'client']);
        case 'manage_properties':
            return in_array($role, ['admin', 'agent']);
        case 'manage_users':
            return $role === 'admin';
        case 'book_appointment':
            return $role === 'client';
        case 'manage_appointments':
            return in_array($role, ['admin', 'agent']);
        default:
            return false;
    }
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Clean filename for upload
 * @param string $filename
 * @return string
 */
function cleanFilename($filename) {
    // Remove any character that isn't alphanumeric, dash, underscore, or dot
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
    return $filename;
}

/**
 * Get file extension
 * @param string $filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file type is allowed
 * @param string $filename
 * @param array $allowed_types
 * @return bool
 */
function isAllowedFileType($filename, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf']) {
    $extension = getFileExtension($filename);
    return in_array($extension, $allowed_types);
}
?>