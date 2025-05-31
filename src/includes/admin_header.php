<?php
// Get admin information if not already available
if (!isset($admin_data) && isAdmin()) {
    $admin_data = getCurrentUser();
}
?>

<!-- Header - Consistent admin navigation -->
<nav class="navbar navbar-expand-lg admin-header">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center ms-3" href="../pages/home.php">
            <img src="../assets/images/logo1.png" alt="Logo" width="120" height="50" class="me-3">
            <span>Admin Panel</span>
        </a>
        
        <button class="navbar-toggler me-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_clients.php">
                        <i class="fas fa-users me-1"></i>Clients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_agents.php">
                        <i class="fas fa-user-tie me-1"></i>Agents
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_properties.php">
                        <i class="fas fa-home me-1"></i>Properties
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_appointments.php">
                        <i class="fas fa-calendar-alt me-1"></i>Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-bar me-1"></i>Analytics & Reports
                    </a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center me-3">
                <div class="dropdown">
                    <a href="#" class="user-profile dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <?= strtoupper(substr($admin_data['first_name'] ?? 'A', 0, 1) . substr($admin_data['last_name'] ?? 'D', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars(($admin_data['first_name'] ?? '') . ' ' . ($admin_data['last_name'] ?? '')) ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <h6 class="dropdown-header">
                                <i class="fas fa-user-shield me-2"></i>Admin Actions
                            </h6>
                        </li>
                        <li><a class="dropdown-item" href="add_agent.php">
                            <i class="fas fa-user-plus me-2"></i>Add New Agent
                        </a></li>
                        <li><a class="dropdown-item" href="add_property.php">
                            <i class="fas fa-plus-circle me-2"></i>Add New Property
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <h6 class="dropdown-header">
                                <i class="fas fa-chart-line me-2"></i>Reports
                            </h6>
                        </li>
                        <li><a class="dropdown-item" href="analytics.php">
                            <i class="fas fa-chart-bar me-2"></i>Analytics Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="analytics.php?report=monthly">
                            <i class="fas fa-file-alt me-2"></i>Monthly Report
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
