<?php
// Agent Navigation Bar - Cleaned Version
// To be included in agent dashboard pages

// Get current page for active states
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Check if user is logged in and is an agent
$isAgent = isset($_SESSION['role']) && $_SESSION['role'] === 'agent';
if (!$isAgent) {
    return; // Don't show agent nav if not an agent
}

// Get agent data if available
$agent_name = ($_SESSION['first_name'] ?? 'Agent') . ' ' . ($_SESSION['last_name'] ?? '');
$agent_initials = strtoupper(substr($_SESSION['first_name'] ?? 'A', 0, 1) . substr($_SESSION['last_name'] ?? 'G', 0, 1));

// Navigation items
$nav_items = [
    [
        'label' => 'Dashboard',
        'icon' => 'chart-pie',
        'url' => '../agent/dashboard.php',
        'active' => in_array($current_page, ['dashboard'])
    ],
    [
        'label' => 'Properties',
        'icon' => 'home',
        'url' => '../agent/manage_properties.php',
        'active' => in_array($current_page, ['manage_properties', 'properties'])
    ],
    [
        'label' => 'Appointments',
        'icon' => 'calendar-alt',
        'url' => '../agent/manage_appointments.php',
        'active' => in_array($current_page, ['appointments', 'manage_appointments'])
    ],
    [
        'label' => 'Messages',
        'icon' => 'envelope',
        'url' => '../agent/messages.php',
        'active' => in_array($current_page, ['messages', 'manage_messages'])
    ],
    [
        'label' => 'Availability',
        'icon' => 'clock',
        'url' => '../agent/manage_availability.php',
        'active' => in_array($current_page, ['availability', 'manage_availability'])
    ]
];

// Get some quick stats (you might want to pass these as parameters)
$quick_stats = [
    'properties' => $_SESSION['agent_properties_count'] ?? 0,
    'appointments' => $_SESSION['agent_appointments_count'] ?? 0,
    'messages' => $_SESSION['agent_unread_messages'] ?? 0
];
?>

<!-- Agent Navigation Bar -->
<nav class="agent-top-nav">
    <div class="container-fluid">
        <div class="nav-content">
            <!-- Brand/Logo Section -->
            <div class="nav-brand">
                <a href="../pages/home.php" class="brand-link">
                    <i class="fas fa-building"></i>
                    <span class="brand-text">Omnes Real Estate</span>
                </a>
                <div class="nav-divider"></div>
                <span class="portal-label">Agent Portal</span>
                <button class="mobile-nav-toggle d-lg-none" onclick="toggleMobileNav()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Main Navigation -->
            <div class="nav-main">
                <?php foreach ($nav_items as $item): ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                       class="nav-item <?= $item['active'] ? 'active' : '' ?>">
                        <i class="fas fa-<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <?php if ($item['label'] === 'Messages' && $quick_stats['messages'] > 0): ?>
                            <span class="nav-badge"><?= $quick_stats['messages'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- User Profile Section -->
            <div class="nav-user">
                <!-- User Profile Dropdown -->
                <div class="dropdown user-dropdown">
                    <button class="user-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <?= htmlspecialchars($agent_initials) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($agent_name) ?></div>
                            <div class="user-role">Licensed Agent</div>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                        <!-- User Info Header -->
                        <li class="dropdown-header">
                            <div class="user-dropdown-header">
                                <div class="user-avatar-large">
                                    <?= htmlspecialchars($agent_initials) ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name-large"><?= htmlspecialchars($agent_name) ?></div>
                                    <div class="user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                                    <div class="user-status">
                                        <span class="status-indicator online"></span>
                                        Available
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Quick Stats in Dropdown -->
                        <li class="dropdown-stats">
                            <div class="stats-grid">
                                <div class="stat-mini">
                                    <span class="stat-mini-value"><?= $quick_stats['properties'] ?></span>
                                    <span class="stat-mini-label">Properties</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-mini-value"><?= $quick_stats['appointments'] ?></span>
                                    <span class="stat-mini-label">Appointments</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-mini-value"><?= $quick_stats['messages'] ?></span>
                                    <span class="stat-mini-label">Messages</span>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Navigation Links -->
                        <li>
                            <a class="dropdown-item" href="../agent/dashboard.php">
                                <i class="fas fa-chart-pie text-primary"></i>
                                <div class="dropdown-item-content">
                                    <div class="item-title">Dashboard</div>
                                    <div class="item-subtitle">Overview & Analytics</div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../agent/account.php">
                                <i class="fas fa-user-cog text-secondary"></i>
                                <div class="dropdown-item-content">
                                    <div class="item-title">Account Settings</div>
                                    <div class="item-subtitle">Profile & Preferences</div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../agent/manage_properties.php?action=add">
                                <i class="fas fa-plus text-success"></i>
                                <div class="dropdown-item-content">
                                    <div class="item-title">Add Property</div>
                                    <div class="item-subtitle">Create new listing</div>
                                </div>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Settings & Help -->
                        <li>
                            <a class="dropdown-item" href="../pages/help.php">
                                <i class="fas fa-question-circle text-info"></i>
                                <div class="dropdown-item-content">
                                    <div class="item-title">Help & Support</div>
                                    <div class="item-subtitle">Get assistance</div>
                                </div>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Logout -->
                        <li>
                            <a class="dropdown-item text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <div class="dropdown-item-content">
                                    <div class="item-title">Sign Out</div>
                                    <div class="item-subtitle">End your session</div>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Include Agent Navigation CSS -->
<link href="../assets/css/agent_navigation.css" rel="stylesheet">

<!-- Navigation JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize agent navigation
    initAgentNavigation();
});

function initAgentNavigation() {
    // Handle dropdown hover effects on desktop
    const userDropdown = document.querySelector('.user-dropdown');
    if (userDropdown && window.innerWidth > 992) {
        userDropdown.addEventListener('mouseenter', function() {
            const dropdownMenu = this.querySelector('.dropdown-menu');
            if (dropdownMenu) {
                dropdownMenu.classList.add('show');
            }
        });
        
        userDropdown.addEventListener('mouseleave', function() {
            const dropdownMenu = this.querySelector('.dropdown-menu');
            if (dropdownMenu) {
                setTimeout(() => {
                    if (!dropdownMenu.matches(':hover')) {
                        dropdownMenu.classList.remove('show');
                    }
                }, 100);
            }
        });
    }
    
    // Add smooth transitions for nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Update notification badges dynamically (if needed)
    updateNotificationBadges();
}

function toggleMobileNav() {
    const navMain = document.querySelector('.nav-main');
    if (navMain) {
        navMain.classList.toggle('mobile-open');
    }
}

function updateNotificationBadges() {
    // This function can be called to update badges via AJAX
    // Implementation depends on your notification system
}

// Auto-hide mobile menu when clicking outside
document.addEventListener('click', function(e) {
    const navMain = document.querySelector('.nav-main');
    const mobileToggle = document.querySelector('.mobile-nav-toggle');
    
    if (navMain && !navMain.contains(e.target) && !mobileToggle?.contains(e.target)) {
        navMain.classList.remove('mobile-open');
    }
});
</script>