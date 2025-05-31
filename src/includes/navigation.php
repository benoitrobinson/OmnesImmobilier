<?php
// Make sure we have the required functions and database connection
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Ensure we have a proper database connection
if (!isset($pdo) || $pdo === null) {
    require_once __DIR__ . '/../config/database.php';
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Get user notifications count if logged in (only if database is available)
$notification_count = 0;
if ($user_id && $user_role && isset($pdo) && $pdo !== null) {
    try {
        if ($user_role === 'client') {
            // Count unread messages for clients
            $notification_query = "SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0";
            $notification_stmt = $pdo->prepare($notification_query);
            $notification_stmt->execute([$user_id]);
            $result = $notification_stmt->fetch(PDO::FETCH_ASSOC);
            $notification_count = $result['count'] ?? 0;
        } elseif ($user_role === 'agent') {
            // Count pending appointments for agents
            $notification_query = "SELECT COUNT(*) as count FROM appointments a 
                                  INNER JOIN properties p ON a.property_id = p.id 
                                  WHERE p.agent_id = ? AND a.status = 'scheduled'";
            $notification_stmt = $pdo->prepare($notification_query);
            $notification_stmt->execute([$user_id]);
            $result = $notification_stmt->fetch(PDO::FETCH_ASSOC);
            $notification_count = $result['count'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Navigation notification error: " . $e->getMessage());
        $notification_count = 0;
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userFirstName = $_SESSION['first_name'] ?? '';
$userLastName = $_SESSION['last_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';

// Determine role-based paths
$dashboardPath = '../client/dashboard.php';
$accountPath = '../client/account.php';
$roleDisplayName = 'Client';

if ($userRole === 'agent') {
    $dashboardPath = '../agent/dashboard.php';
    $accountPath = '../agent/account.php';
    $roleDisplayName = 'Licensed Agent';
} elseif ($userRole === 'admin') {
    $dashboardPath = '../admin/dashboard.php';
    $accountPath = '../admin/account.php';
    $roleDisplayName = 'Administrator';
}
?>
<nav class="navbar navbar-expand-lg custom-navbar navbar-transparent">
    <div class="container-fluid" style="padding:0;">
        <a class="navbar-brand" href="../pages/home.php">
            <img src="../assets/images/logo1.png" alt="Logo" style="height:60px; max-height:60px; object-fit:contain;">
        </a>
        
        <!-- Mobile toggle button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Left-aligned navigation links -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) == 'home.php') echo ' active'; ?>" href="../pages/home.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) == 'explore.php') echo ' active'; ?>" href="../pages/explore.php">
                        <i class="fas fa-search me-1"></i>Explore
                    </a>
                </li>
                <!-- Always show appointments link -->
                <li class="nav-item">
                    <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) == 'appointment.php') echo ' active'; ?>" 
                       href="../pages/appointment.php">
                        <i class="fas fa-calendar-alt me-1"></i>Appointments
                    </a>
                </li>
                
            </ul>
            
            <!-- Right-aligned account dropdown -->
            <ul class="navbar-nav">
                <?php if ($isLoggedIn): ?>
                    <!-- Account dropdown for logged in users -->
                    <li class="nav-item dropdown account-dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-small me-2">
                                <?= strtoupper(substr($userFirstName, 0, 1)) ?>
                            </div>
                            <i class="fas fa-chevron-down ms-2"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end account-dropdown-menu" aria-labelledby="accountDropdown">
                            <li class="dropdown-header">
                                <div class="user-info-detailed">
                                    <div class="user-avatar-large mx-auto mb-2">
                                        <?= strtoupper(substr($userFirstName, 0, 1) . substr($userLastName, 0, 1)) ?>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-semibold"><?= htmlspecialchars($userFirstName . ' ' . $userLastName) ?></div>
                                        <small class="text-muted"><?= $roleDisplayName ?></small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Navigation Links (role-based) -->
                            <li>
                                <a class="dropdown-item" href="<?= $dashboardPath ?>">
                                    <i class="fas fa-chart-pie me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Dashboard</div>
                                        <small class="text-muted">Overview & <?= $userRole === 'agent' ? 'Analytics' : 'Statistics' ?></small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= $accountPath ?>">
                                    <i class="fas fa-user-cog me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-semibold">Account Settings</div>
                                        <small class="text-muted">Profile & Preferences</small>
                                    </div>
                                </a>
                            </li>
                            
                            <?php if ($userRole === 'agent'): ?>
                                <li>
                                    <a class="dropdown-item" href="../agent/properties.php">
                                        <i class="fas fa-home me-2 text-success"></i>
                                        <div>
                                            <div class="fw-semibold">Manage Properties</div>
                                            <small class="text-muted">Listings & Portfolio</small>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="../agent/appointments.php">
                                        <i class="fas fa-calendar-check me-2 text-info"></i>
                                        <div>
                                            <div class="fw-semibold">Appointments</div>
                                            <small class="text-muted">Schedule & Meetings</small>
                                        </div>
                                    </a>
                                </li>
                            <?php elseif ($userRole === 'client'): ?>
                                <li>
                                    <a class="dropdown-item" href="../client/dashboard.php?section=favorites">
                                        <i class="fas fa-heart me-2 text-danger"></i>
                                        <div>
                                            <div class="fw-semibold">My Favorites</div>
                                            <small class="text-muted">Saved Properties</small>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="../pages/appointment.php">
                                        <i class="fas fa-calendar-alt me-2 text-success"></i>
                                        <div>
                                            <div class="fw-semibold">Appointments</div>
                                            <small class="text-muted">Schedule & History</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Logout -->
                            <li>
                                <a class="dropdown-item text-danger" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    <div>
                                        <div class="fw-semibold">Sign Out</div>
                                        <small class="text-muted">End your session</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Login/Register Dropdown -->
                    <li class="nav-item dropdown account-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i>My Account
                            <i class="fas fa-chevron-down ms-1"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end account-dropdown-menu" aria-labelledby="accountDropdown">
                            <li class="dropdown-header text-center">
                                <i class="fas fa-home fa-2x text-primary mb-2"></i>
                                <div class="fw-semibold">Welcome to Omnes Immobilier</div>
                                <small class="text-muted">Your premium real estate partner</small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../auth/login.php">
                                    <i class="fas fa-sign-in-alt me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Sign In</div>
                                        <small class="text-muted">Access your account</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../auth/register.php">
                                    <i class="fas fa-user-plus me-2 text-success"></i>
                                    <div>
                                        <div class="fw-semibold">Create Account</div>
                                        <small class="text-muted">Join Omnes Immobilier</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../pages/explore.php">
                                    <i class="fas fa-search me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-semibold">Browse Properties</div>
                                        <small class="text-muted">No account needed</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script src="../assets/js/navigation.js"></script>