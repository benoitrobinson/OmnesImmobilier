<?php
// Check if user is logged in (you may need to adjust this path)
session_start();
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userFirstName = $_SESSION['first_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';
?>
<link href="../assets/css/navigation.css" rel="stylesheet">
<nav class="navbar navbar-expand-lg custom-navbar">
    <div class="container-fluid" style="padding:0;">
        <a class="navbar-brand" href="../pages/home.php">
            <img src="../assets/images/logo1.png" alt="Logo" style="height:60px; max-height:60px; object-fit:contain;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
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
                
                <?php if ($isLoggedIn): ?>
                    <!-- Logged in user - show appointments link -->
                    <li class="nav-item">
                        <a class="nav-link" href="../client/dashboard.php?section=appointments">
                            <i class="fas fa-calendar-alt me-1"></i>Appointments
                        </a>
                    </li>
                    
                    <!-- Account dropdown for logged in users -->
                    <li class="nav-item dropdown account-dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-small me-2">
                                <?= strtoupper(substr($userFirstName, 0, 1)) ?>
                            </div>
                            <span class="d-none d-md-inline"><?= htmlspecialchars($userFirstName) ?></span>
                            <i class="fas fa-chevron-down ms-1"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end account-dropdown-menu" aria-labelledby="accountDropdown">
                            <li class="dropdown-header">
                                <div class="user-info">
                                    <div class="fw-semibold"><?= htmlspecialchars($userFirstName) ?></div>
                                    <small class="text-muted"><?= ucfirst($userRole) ?> Member</small>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../client/dashboard.php">
                                    <i class="fas fa-chart-pie me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../client/account.php">
                                    <i class="fas fa-user-cog me-2"></i>Account Settings
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../client/dashboard.php?section=favorites">
                                    <i class="fas fa-heart me-2"></i>My Favorites
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Not logged in - show sign in/register dropdown -->
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/explore.php">
                            <i class="fas fa-calendar-alt me-1"></i>Appointments
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown account-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i>My Account
                            <i class="fas fa-chevron-down ms-1"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end account-dropdown-menu" aria-labelledby="accountDropdown">
                            <li class="dropdown-header">
                                <small class="text-muted">Welcome to Omnes Immobilier</small>
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

<!-- Enhanced Navigation CSS -->
<style>
    .account-dropdown .dropdown-toggle::after {
        display: none; /* Hide default Bootstrap arrow */
    }
    
    .account-dropdown:hover .dropdown-menu {
        display: block;
        margin-top: 0; /* Remove gap */
    }
    
    .account-dropdown-menu {
        min-width: 280px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        padding: 0.5rem 0;
        background: white;
        backdrop-filter: blur(10px);
        margin-top: 8px;
    }
    
    .account-dropdown-menu .dropdown-item {
        padding: 0.75rem 1.5rem;
        border: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
    }
    
    .account-dropdown-menu .dropdown-item:hover {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        transform: translateX(5px);
    }
    
    .account-dropdown-menu .dropdown-item i {
        width: 20px;
        text-align: center;
    }
    
    .account-dropdown-menu .dropdown-header {
        padding: 1rem 1.5rem 0.5rem;
        color: #6c757d;
        font-weight: 600;
    }
    
    .user-avatar-small {
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .user-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    /* Smooth animations */
    .account-dropdown-menu {
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
    }
    
    .account-dropdown:hover .account-dropdown-menu,
    .account-dropdown .dropdown-menu.show {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    
    /* Enhanced nav-link styling */
    .custom-navbar .nav-link {
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin: 0 0.25rem;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .custom-navbar .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }
    
    .custom-navbar .nav-link.active {
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37 !important;
    }
    
    /* Mobile responsive */
    @media (max-width: 991.98px) {
        .account-dropdown-menu {
            position: static !important;
            box-shadow: none;
            background: rgba(255, 255, 255, 0.95);
            margin: 0.5rem 0;
            border-radius: 8px;
        }
        
        .account-dropdown:hover .dropdown-menu {
            display: none; /* Disable hover on mobile */
        }
        
        .user-avatar-small {
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
        }
    }
    
    /* Luxury gold accent */
    .text-gold {
        color: #d4af37 !important;
    }
    
    /* Professional spacing */
    .dropdown-divider {
        margin: 0.5rem 0;
        opacity: 0.1;
    }
</style>

<script src="../assets/js/navigation.js"></script>