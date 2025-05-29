<?php
// ========================================
// ROUTE HANDLER
// Automatic role-based routing and access control
// Usage: Include this file at the top of protected pages
// ========================================

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

class RouteHandler {
    private $user_role;
    private $current_page;
    private $allowed_roles;
    
    public function __construct() {
        $this->user_role = $_SESSION['role'] ?? null;
        $this->current_page = basename($_SERVER['PHP_SELF']);
        $this->allowed_roles = [];
    }
    
    /**
     * Set allowed roles for current page
     * @param array $roles
     */
    public function allowRoles($roles) {
        $this->allowed_roles = is_array($roles) ? $roles : [$roles];
        return $this;
    }
    
    /**
     * Check access and redirect if necessary
     */
    public function checkAccess() {
        // If no login required and no roles specified, allow access
        if (empty($this->allowed_roles)) {
            return;
        }
        
        // Check if user is logged in
        if (!isLoggedIn()) {
            $this->redirectToLogin();
            return;
        }
        
        // Check if user has required role
        if (!in_array($this->user_role, $this->allowed_roles)) {
            $this->redirectToAppropriateDashboard();
            return;
        }
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin() {
        $current_url = $_SERVER['REQUEST_URI'];
        $login_url = $this->getBasePath() . '/auth/login.php?redirect=' . urlencode($current_url);
        redirect($login_url);
    }
    
    /**
     * Redirect to appropriate dashboard based on user role
     */
    private function redirectToAppropriateDashboard() {
        $dashboard_url = $this->getDashboardUrl($this->user_role);
        $error_message = 'You do not have permission to access that page.';
        redirect($dashboard_url . '?error=' . urlencode($error_message));
    }
    
    /**
     * Get dashboard URL for role
     */
    private function getDashboardUrl($role) {
        $base_path = $this->getBasePath();
        
        switch ($role) {
            case 'agent':
                return $base_path . '/agent/dashboard.php';
            case 'admin':
                return $base_path . '/admin/dashboard.php';
            case 'client':
            default:
                return $base_path . '/client/dashboard.php';
        }
    }
    
    /**
     * Get base path for URLs
     */
    private function getBasePath() {
        $script_name = $_SERVER['SCRIPT_NAME'];
        $segments = explode('/', $script_name);
        
        // Remove filename and current directory
        array_pop($segments);
        array_pop($segments);
        
        return implode('/', $segments);
    }
    
    /**
     * Auto-redirect based on role if accessing wrong dashboard
     */
    public function autoRedirectDashboard() {
        if (!isLoggedIn()) {
            return;
        }
        
        $current_dir = dirname($_SERVER['SCRIPT_NAME']);
        $expected_dir = $this->getExpectedDirectory($this->user_role);
        
        // If user is in wrong directory, redirect to correct one
        if ($current_dir !== $expected_dir && $this->isDashboardPage()) {
            $correct_url = $this->getDashboardUrl($this->user_role);
            redirect($correct_url);
        }
    }
    
    /**
     * Get expected directory for user role
     */
    private function getExpectedDirectory($role) {
        $base_path = $this->getBasePath();
        
        switch ($role) {
            case 'agent':
                return $base_path . '/agent';
            case 'admin':
                return $base_path . '/admin';
            case 'client':
            default:
                return $base_path . '/client';
        }
    }
    
    /**
     * Check if current page is a dashboard page
     */
    private function isDashboardPage() {
        $dashboard_pages = ['dashboard.php', 'account.php'];
        return in_array($this->current_page, $dashboard_pages);
    }
    
    /**
     * Generate role-based navigation links
     */
    public function getNavigationLinks() {
        if (!isLoggedIn()) {
            return [];
        }
        
        $base_path = $this->getBasePath();
        $links = [];
        
        switch ($this->user_role) {
            case 'agent':
                $links = [
                    'dashboard' => $base_path . '/agent/dashboard.php',
                    'account' => $base_path . '/agent/account.php',
                    'properties' => $base_path . '/agent/properties.php',
                    'appointments' => $base_path . '/agent/appointments.php',
                    'messages' => $base_path . '/agent/messages.php'
                ];
                break;
                
            case 'admin':
                $links = [
                    'dashboard' => $base_path . '/admin/dashboard.php',
                    'account' => $base_path . '/admin/account.php',
                    'users' => $base_path . '/admin/users.php',
                    'properties' => $base_path . '/admin/properties.php',
                    'settings' => $base_path . '/admin/settings.php'
                ];
                break;
                
            case 'client':
            default:
                $links = [
                    'dashboard' => $base_path . '/client/dashboard.php',
                    'account' => $base_path . '/client/account.php',
                    'appointments' => $base_path . '/pages/appointment.php',
                    'explore' => $base_path . '/pages/explore.php'
                ];
                break;
        }
        
        return $links;
    }
    
    /**
     * Get user statistics for navigation
     */
    public function getUserStats() {
        if (!isLoggedIn()) {
            return [];
        }
        
        return getUserStats($_SESSION['user_id'], $this->user_role);
    }
    
    /**
     * Check if user can access specific resource
     */
    public function canAccess($resource, $resource_id = null) {
        if (!isLoggedIn()) {
            return false;
        }
        
        switch ($resource) {
            case 'property':
                return $resource_id ? canAccessProperty($resource_id, $_SESSION['user_id'], $this->user_role) : true;
                
            case 'admin_panel':
                return $this->user_role === 'admin';
                
            case 'agent_tools':
                return in_array($this->user_role, ['admin', 'agent']);
                
            case 'user_management':
                return $this->user_role === 'admin';
                
            default:
                return hasPermission($resource);
        }
    }
    
    /**
     * Log access attempt
     */
    public function logAccess($success = true) {
        $details = [
            'page' => $this->current_page,
            'user_role' => $this->user_role,
            'allowed_roles' => $this->allowed_roles,
            'success' => $success
        ];
        
        logSecurityEvent('page_access', json_encode($details));
    }
}

// Usage examples:

/**
 * Quick setup for pages that require login
 * Usage: requireLogin();
 */
function requireLogin() {
    $router = new RouteHandler();
    $router->allowRoles(['admin', 'agent', 'client'])->checkAccess();
}

/**
 * Quick setup for agent-only pages
 * Usage: requireAgent();
 */
function requireAgent() {
    $router = new RouteHandler();
    $router->allowRoles(['agent'])->checkAccess();
}

/**
 * Quick setup for admin-only pages
 * Usage: requireAdmin();
 */
function requireAdmin() {
    $router = new RouteHandler();
    $router->allowRoles(['admin'])->checkAccess();
}

/**
 * Quick setup for client-only pages
 * Usage: requireClient();
 */
function requireClient() {
    $router = new RouteHandler();
    $router->allowRoles(['client'])->checkAccess();
}

/**
 * Auto-redirect if user is in wrong dashboard
 * Usage: autoRedirect();
 */
function autoRedirect() {
    $router = new RouteHandler();
    $router->autoRedirectDashboard();
}

/**
 * Get navigation links for current user
 * Usage: $links = getNavLinks();
 */
function getNavLinks() {
    $router = new RouteHandler();
    return $router->getNavigationLinks();
}

/**
 * Check if current user can access resource
 * Usage: if (canUserAccess('property', $property_id)) { ... }
 */
function canUserAccess($resource, $resource_id = null) {
    $router = new RouteHandler();
    return $router->canAccess($resource, $resource_id);
}

// Global router instance for manual use
$GLOBALS['router'] = new RouteHandler();

// Auto-redirect functionality (call this at the top of dashboard pages)
if (isset($_GET['auto_redirect']) || 
    (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && 
     in_array(basename($_SERVER['PHP_SELF']), ['dashboard.php', 'account.php']))) {
    autoRedirect();
}
?>