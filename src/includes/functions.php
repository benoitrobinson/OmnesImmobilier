<?php
// ========================================
// ENHANCED HELPER FUNCTIONS
// Global utility functions with role-based routing and improved functionality
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
        // Redirect to appropriate dashboard based on user role
        $dashboard_url = getDashboardUrl($_SESSION['role']);
        redirect($dashboard_url . '?error=' . urlencode('Access denied for this section'));
    }
}

/**
 * Get dashboard URL based on user role
 * @param string $role
 * @return string
 */
function getDashboardUrl($role) {
    switch ($role) {
        case 'agent':
            return '../agent/dashboard.php';
        case 'admin':
            return '../admin/dashboard.php';
        case 'client':
        default:
            return '../client/dashboard.php';
    }
}

/**
 * Get account URL based on user role
 * @param string $role
 * @return string
 */
function getAccountUrl($role) {
    switch ($role) {
        case 'agent':
            return '../agent/account.php';
        case 'admin':
            return '../admin/account.php';
        case 'client':
        default:
            return '../client/account.php';
    }
}

/**
 * Redirect user to appropriate dashboard based on role
 */
function redirectToDashboard() {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
    
    $dashboard_url = getDashboardUrl($_SESSION['role']);
    redirect($dashboard_url);
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
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("getCurrentUser error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user statistics based on role
 * @param int $user_id
 * @param string $role
 * @return array
 */
function getUserStats($user_id, $role) {
    global $pdo;
    
    $stats = [
        'appointments' => 0,
        'properties' => 0,
        'favorites' => 0,
        'messages' => 0,
        'views' => 0
    ];
    
    try {
        if ($role === 'client') {
            $query = "SELECT 
                     (SELECT COUNT(*) FROM appointments WHERE client_id = ?) as appointments,
                     (SELECT COUNT(*) FROM favorites WHERE user_id = ?) as favorites,
                     (SELECT COUNT(*) FROM property_views WHERE user_id = ?) as views";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $stats = array_merge($stats, $result);
            }
        } elseif ($role === 'agent') {
            $query = "SELECT 
                     (SELECT COUNT(*) FROM properties WHERE agent_id = ?) as properties,
                     (SELECT COUNT(*) FROM appointments WHERE agent_id = ? AND status = 'scheduled') as appointments,
                     (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0) as messages";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id, $user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $stats = array_merge($stats, $result);
            }
        }
    } catch (Exception $e) {
        error_log("getUserStats error: " . $e->getMessage());
    }
    
    return $stats;
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
            return 'Licensed Agent';
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
        case 'view_analytics':
            return in_array($role, ['admin', 'agent']);
        case 'modify_system_settings':
            return $role === 'admin';
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

function requireAgent() {
    requireRole('agent');
}

function requireAdmin() {
    requireRole('admin');
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

/**
 * Get breadcrumb navigation based on current page
 * @param string $current_page
 * @param string $role
 * @return array
 */
function getBreadcrumb($current_page, $role = 'client') {
    $breadcrumb = [
        ['name' => 'Home', 'url' => '../pages/home.php', 'icon' => 'fas fa-home']
    ];
    
    switch ($current_page) {
        case 'dashboard':
            $breadcrumb[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($role), 'icon' => 'fas fa-chart-pie'];
            break;
        case 'account':
            $breadcrumb[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($role), 'icon' => 'fas fa-chart-pie'];
            $breadcrumb[] = ['name' => 'Account Settings', 'url' => getAccountUrl($role), 'icon' => 'fas fa-user-cog'];
            break;
        case 'properties':
            if ($role === 'agent') {
                $breadcrumb[] = ['name' => 'Agent Portal', 'url' => '../agent/dashboard.php', 'icon' => 'fas fa-chart-pie'];
                $breadcrumb[] = ['name' => 'Properties', 'url' => '../agent/properties.php', 'icon' => 'fas fa-home'];
            }
            break;
        case 'appointments':
            $breadcrumb[] = ['name' => 'Dashboard', 'url' => getDashboardUrl($role), 'icon' => 'fas fa-chart-pie'];
            $breadcrumb[] = ['name' => 'Appointments', 'url' => '../pages/appointment.php', 'icon' => 'fas fa-calendar-alt'];
            break;
        case 'explore':
            $breadcrumb[] = ['name' => 'Explore Properties', 'url' => '../pages/explore.php', 'icon' => 'fas fa-search'];
            break;
    }
    
    return $breadcrumb;
}

/**
 * Render breadcrumb HTML
 * @param array $breadcrumb
 * @return string
 */
function renderBreadcrumb($breadcrumb) {
    if (empty($breadcrumb)) {
        return '';
    }
    
    $html = '<nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">';
    
    $total = count($breadcrumb);
    foreach ($breadcrumb as $index => $item) {
        $isLast = ($index === $total - 1);
        
        if ($isLast) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            if (isset($item['icon'])) {
                $html .= '<i class="' . $item['icon'] . ' me-1"></i>';
            }
            $html .= $item['name'] . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item">';
            $html .= '<a href="' . $item['url'] . '">';
            if (isset($item['icon'])) {
                $html .= '<i class="' . $item['icon'] . ' me-1"></i>';
            }
            $html .= $item['name'] . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Set success message in session
 * @param string $message
 */
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * Set error message in session
 * @param string $message
 */
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * Get and clear success message from session
 * @return string|null
 */
function getSuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}

/**
 * Get and clear error message from session
 * @return string|null
 */
function getErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}

/**
 * Check if user can access a specific property
 * @param int $property_id
 * @param int $user_id
 * @param string $role
 * @return bool
 */
function canAccessProperty($property_id, $user_id, $role) {
    global $pdo;
    
    if ($role === 'admin') {
        return true; // Admins can access all properties
    }
    
    if ($role === 'agent') {
        try {
            $stmt = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND agent_id = ?");
            $stmt->execute([$property_id, $user_id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("canAccessProperty error: " . $e->getMessage());
            return false;
        }
    }
    
    // Clients can access all available properties
    return $role === 'client';
}

/**
 * Generate user avatar initials
 * @param string $first_name
 * @param string $last_name
 * @return string
 */
function getAvatarInitials($first_name, $last_name) {
    $first_initial = !empty($first_name) ? strtoupper($first_name[0]) : '';
    $last_initial = !empty($last_name) ? strtoupper($last_name[0]) : '';
    return $first_initial . $last_initial;
}

/**
 * Safe database query execution
 * @param PDO $pdo
 * @param string $query
 * @param array $params
 * @return array|false
 */
function safeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database update/insert execution
 * @param PDO $pdo
 * @param string $query
 * @param array $params
 * @return bool
 */
function safeExecute($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Database execute error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get default theme for user
 * @return string
 */
function getDefaultTheme() {
    return $_SESSION['user_theme'] ?? 'light';
}

/**
 * Set user theme preference
 * @param string $theme
 */
function setUserTheme($theme) {
    if (in_array($theme, ['light', 'dark'])) {
        $_SESSION['user_theme'] = $theme;
    }
}
/**
 * Generate available appointment slots for an agent
 * @param int $agent_id
 * @param PDO $pdo
 * @return array
 */
function generateAvailableSlots($agent_id, $pdo) {
    $slots = [];
    // fetch availability rules
    $stmt = $pdo->prepare("SELECT * FROM agent_availability WHERE agent_id=? AND is_available=1");
    $stmt->execute([$agent_id]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // fetch existing appointments next 14 days
    $stmt = $pdo->prepare("
      SELECT appointment_date FROM appointments
      WHERE agent_id=? AND appointment_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 14 DAY)
    ");
    $stmt->execute([$agent_id]);
    $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $booked = array_map(function($dt){ return date('Y-m-d H:i', strtotime($dt)); }, $booked);
    for ($i=0; $i<14; $i++) {
        $day = date('Y-m-d', strtotime("+$i days"));
        $dow = date('l', strtotime($day));
        foreach ($rules as $r) {
            if ($r['day_of_week']!==$dow) continue;
            $start = strtotime("$day {$r['start_time']}");
            $end   = strtotime("$day {$r['end_time']}");
            for ($t=$start; $t<$end; $t+=3600) {
                $time = date('Y-m-d H:i', $t);
                $hour = (int)date('H', $t);
                if ($hour>=12 && $hour<13) continue; // skip lunch
                if (!in_array($time, $booked) && strtotime($time)>time()) {
                    $slots[] = ['date'=>substr($time,0,10),'time'=>substr($time,11,5)];
                }
            }
        }
    }
    return $slots;
}
?>