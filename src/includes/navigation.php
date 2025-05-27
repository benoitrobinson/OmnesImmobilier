<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userFirstName = $_SESSION['first_name'] ?? '';
$userLastName = $_SESSION['last_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);

// Get user theme preference from session or default
$userTheme = $_SESSION['user_theme'] ?? 'light';

// Check if we're on dashboard page to hide navbar
$isDashboard = (strpos($_SERVER['REQUEST_URI'], 'dashboard.php') !== false);
?>

<!-- Only show navigation if NOT on dashboard -->
<?php if (!$isDashboard): ?>
<link href="../assets/css/navigation.css" rel="stylesheet">

<nav class="navbar navbar-expand-lg custom-navbar <?= $userTheme === 'dark' ? 'navbar-dark' : '' ?>">
    <div class="container-fluid">
        <!-- Brand - Only Logo -->
        <a class="navbar-brand d-flex align-items-center" href="../pages/home.php">
            <img src="../assets/images/logo1.png" alt="Omnes Immobilier" style="height:50px; max-height:50px; object-fit:contain;">
        </a>
        
        <!-- Mobile toggle button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Navigation Links -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'home.php' ? ' active' : '' ?>" href="../pages/home.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'explore.php' ? ' active' : '' ?>" href="../pages/explore.php">
                        <i class="fas fa-search me-1"></i>Explore
                    </a>
                </li>
                
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= strpos($currentPage, 'dashboard') !== false && isset($_GET['section']) && $_GET['section'] == 'appointments' ? ' active' : '' ?>" href="../client/dashboard.php?section=appointments">
                            <i class="fas fa-calendar-alt me-1"></i>Appointments
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/explore.php">
                            <i class="fas fa-calendar-alt me-1"></i>Appointments
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- Right Side - User Info or Login -->
            <div class="navbar-nav account-section">
                <?php if ($isLoggedIn): ?>
                    <!-- Dashboard link for logged-in users -->
                    <?php if ($currentPage !== 'dashboard.php'): ?>
                        <a class="nav-link me-3" href="../client/dashboard.php" title="Dashboard">
                            <i class="fas fa-chart-pie me-1"></i>
                            <span class="d-none d-lg-inline">Dashboard</span>
                        </a>
                    <?php endif; ?>
                    
                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown account-dropdown">
                        <a class="nav-link dropdown-toggle user-profile-nav d-flex align-items-center" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-nav me-2">
                                <?= strtoupper(substr($userFirstName, 0, 1) . substr($userLastName, 0, 1)) ?>
                            </div>
                            <div class="user-info-nav d-none d-lg-block">
                                <div class="user-name"><?= htmlspecialchars($userFirstName . ' ' . $userLastName) ?></div>
                                <small class="user-role"><?= ucfirst($userRole) ?> Member</small>
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
                                        <small class="text-muted"><?= ucfirst($userRole) ?> Member</small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Quick Stats -->
                            <li class="px-3 py-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-number">0</div>
                                        <small class="text-muted">Appointments</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number">1</div>
                                        <small class="text-muted">Favorites</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number">0</div>
                                        <small class="text-muted">Views</small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Navigation Links -->
                            <li>
                                <a class="dropdown-item" href="../client/dashboard.php">
                                    <i class="fas fa-chart-pie me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Dashboard</div>
                                        <small class="text-muted">Overview & Statistics</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../client/account.php">
                                    <i class="fas fa-user-cog me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-semibold">Account Settings</div>
                                        <small class="text-muted">Profile & Preferences</small>
                                    </div>
                                </a>
                            </li>
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
                                <a class="dropdown-item" href="../client/dashboard.php?section=appointments">
                                    <i class="fas fa-calendar-alt me-2 text-success"></i>
                                    <div>
                                        <div class="fw-semibold">Appointments</div>
                                        <small class="text-muted">Schedule & History</small>
                                    </div>
                                </a>
                            </li>
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
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
            </div>
        </div>
    </div>
</nav>

<!-- Enhanced Navigation CSS -->
<style>
/* Theme Variables */
:root {
    --nav-bg: rgba(255, 255, 255, 0.95);
    --nav-text: #333;
    --nav-hover: #d4af37;
    --nav-border: rgba(0, 0, 0, 0.1);
    --dropdown-bg: #fff;
    --dropdown-text: #333;
    --dropdown-hover: #f8f9fa;
}

[data-theme="dark"] {
    --nav-bg: rgba(33, 37, 41, 0.95);
    --nav-text: #e9ecef;
    --nav-hover: #f4d03f;
    --nav-border: rgba(255, 255, 255, 0.1);
    --dropdown-bg: #2c3034;
    --dropdown-text: #e9ecef;
    --dropdown-hover: #3a3f44;
}

/* Enhanced Navbar */
.custom-navbar {
    background: var(--nav-bg) !important;
    backdrop-filter: blur(15px);
    border-bottom: 1px solid var(--nav-border);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
    padding: 0.5rem 0;
    min-height: 70px;
}

.navbar-brand {
    font-weight: 700;
    color: var(--nav-text) !important;
}

/* Navigation Links - Ensure same vertical level */
.navbar-nav {
    align-items: center;
}

.navbar-nav .nav-link {
    color: var(--nav-text) !important;
    font-weight: 500;
    padding: 0.75rem 1rem !important;
    border-radius: 8px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    height: 48px; /* Fixed height for consistent alignment */
}

.navbar-nav .nav-link:hover {
    color: var(--nav-hover) !important;
    background: rgba(212, 175, 55, 0.1);
    transform: translateY(-2px);
}

.navbar-nav .nav-link.active {
    color: var(--nav-hover) !important;
    background: rgba(212, 175, 55, 0.2);
    font-weight: 600;
}

/* Account Section Positioning - Move further left */
.account-section {
    margin-right: 3rem !important; /* Move account section away from edge */
}

/* User Profile Navigation */
.user-profile-nav {
    background: rgba(212, 175, 55, 0.1) !important;
    border: 2px solid transparent;
    border-radius: 12px !important;
    padding: 0.5rem 1rem !important;
    height: 48px !important; /* Match other nav items */
}

.user-profile-nav:hover {
    background: rgba(212, 175, 55, 0.2) !important;
    border-color: rgba(212, 175, 55, 0.3) !important;
}

.user-avatar-nav {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.8rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.user-info-nav {
    text-align: left;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--nav-text);
    line-height: 1.2;
}

.user-role {
    color: var(--nav-hover);
    font-size: 0.75rem;
    opacity: 0.8;
}

/* Enhanced Dropdown - Better positioning */
.account-dropdown-menu {
    min-width: 320px;
    max-width: 380px;
    border: none;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
    border-radius: 16px;
    padding: 1rem 0;
    background: var(--dropdown-bg);
    backdrop-filter: blur(20px);
    margin-top: 8px;
    z-index: 10050;
    /* Better positioning - ensure it doesn't go off screen */
    right: 0 !important;
    left: auto !important;
    transform: translateX(-1rem) !important;
    border: 2px solid rgba(212, 175, 55, 0.2);
}

.user-info-detailed {
    padding: 0.5rem;
}

.user-avatar-large {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    border: 3px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.account-dropdown-menu .dropdown-item {
    padding: 0.8rem 1.5rem;
    border: none;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    color: var(--dropdown-text) !important;
    background: transparent;
    white-space: normal; /* Allow text wrapping */
    border-radius: 0;
}

/* FIX: Remove translateX on hover to keep content in dropdown */
.account-dropdown-menu .dropdown-item:hover {
    background: var(--dropdown-hover);
    color: var(--nav-hover) !important;
    /* Remove transform to prevent text going outside dropdown */
    transform: none !important;
    border-radius: 8px;
    margin: 0 0.5rem;
}

.account-dropdown-menu .dropdown-item i {
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
    flex-shrink: 0; /* Prevent icon from shrinking */
}

.stat-number {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--nav-hover);
}

/* Dropdown animations */
.account-dropdown-menu {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}

.account-dropdown:hover .account-dropdown-menu,
.account-dropdown .dropdown-menu.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

/* Mobile Responsive */
@media (max-width: 991.98px) {
    .custom-navbar {
        background: var(--nav-bg) !important;
    }
    
    .navbar-collapse {
        background: var(--dropdown-bg);
        border-radius: 12px;
        margin-top: 1rem;
        padding: 1rem;
        backdrop-filter: blur(15px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .account-dropdown-menu {
        position: static !important;
        box-shadow: none;
        background: var(--dropdown-hover);
        margin: 0.5rem 0;
        border-radius: 8px;
        transform: none !important;
        opacity: 1 !important;
        max-width: none;
        right: auto !important;
    }
    
    .account-dropdown:hover .dropdown-menu {
        display: none;
    }
    
    .user-info-nav {
        display: none !important;
    }
    
    .user-avatar-nav {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
    
    .navbar-nav .nav-link {
        height: auto !important;
        padding: 0.75rem 1rem !important;
    }
    
    .account-section {
        margin-right: 0 !important;
    }
}

/* Dark theme specific adjustments */
[data-theme="dark"] .custom-navbar {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .account-dropdown-menu {
    background: var(--dropdown-bg);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .user-role {
    color: #f4d03f;
}

/* Ensure proper z-index for dropdowns */
.dropdown-menu {
    z-index: 10050 !important;
}

.navbar {
    z-index: 10040;
}

/* Container alignment improvements */
.container-fluid {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.navbar-collapse {
    display: flex;
    align-items: center;
}

/* Ensure all nav items are on same level */
.navbar-nav.me-auto, 
.navbar-nav {
    display: flex;
    align-items: center;
    margin: 0;
}

.nav-item {
    display: flex;
    align-items: center;
}

/* Fix dropdown item content alignment */
.account-dropdown-menu .dropdown-item div {
    flex: 1;
    min-width: 0; /* Allow text to shrink if needed */
}

.account-dropdown-menu .dropdown-item .fw-semibold {
    margin-bottom: 2px;
}

.account-dropdown-menu .dropdown-item small {
    line-height: 1.2;
}
</style>

<script src="../assets/js/navigation.js"></script>
<?php endif; ?>