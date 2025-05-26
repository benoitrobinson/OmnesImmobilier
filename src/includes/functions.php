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
?>