<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// This page allows users to manage their personal account
// It uses object-oriented programming concepts and web security

// Check if user is logged in - fundamental security concept
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get complete user information
// This query uses a LEFT JOIN to retrieve agent information if applicable
$query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name 
          FROM users u 
          LEFT JOIN agents a ON u.id = a.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Profile update form processing
// This section illustrates server-side validation and data security
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Validation and cleaning of input data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        
        // Validation of required fields
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } else {
            // Update user information with prepared query (security)
            $update_query = "UPDATE users SET 
                           first_name = :first_name, last_name = :last_name, phone = :phone,
                           updated_at = CURRENT_TIMESTAMP
                           WHERE id = :user_id";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':first_name', $first_name);
            $update_stmt->bindParam(':last_name', $last_name);
            $update_stmt->bindParam(':phone', $phone);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                // Update session variables
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                
                $success = 'Profile updated successfully!';
                
                // Reload user data
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Error updating profile.';
            }
        }
    }
    
    // Password change processing - enhanced security
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must contain at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            // Hash new password with bcrypt (security)
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $password_query = "UPDATE users SET password_hash = :password WHERE id = :user_id";
            $password_stmt = $db->prepare($password_query);
            $password_stmt->bindParam(':password', $hashed_password);
            $password_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($password_stmt->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Error changing password.';
            }
        }
    }
}

// Get user statistics by type (simplified for now)
$stats = [
    'total_appointments' => 0,
    'confirmed_appointments' => 0,
    'properties_viewed' => 0,
    'favorites' => 0,
    'managed_properties' => 0,
    'scheduled_appointments' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Base CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Dashboard CSS for consistency -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body data-user-logged-in="true">
    <!-- Professional Header -->
    <header class="luxury-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="text-gold fw-bold mb-2">Account Management</h1>
                    <p class="text-muted mb-0">Manage your personal information and preferences</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn-luxury-secondary">
                        <i class="fas fa-chart-pie me-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb Navigation -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Account</li>
            </ol>
        </nav>
    </div>

    <div class="container my-5">
        <!-- Status Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Professional Sidebar -->
            <div class="col-lg-3">
                <div class="luxury-sidebar">
                    <div class="sidebar-header text-center">
                        <div class="sidebar-avatar mx-auto mb-3">
                            <?php if (isAgent() && !empty($user['profile_picture_path'])): ?>
                                <img src="../uploads/agents/<?= $user['profile_picture_path'] ?>" alt="Profile picture" class="rounded-circle" width="80" height="80">
                            <?php else: ?>
                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                        <small class="opacity-75">
                            <?php 
                            switch ($user['role']) {
                                case 'admin': echo 'Administrator'; break;
                                case 'agent': echo 'Real Estate Agent'; break;
                                case 'client': echo 'Client'; break;
                                default: echo 'User';
                            }
                            ?>
                        </small>
                    </div>

                    <!-- Personal Statistics -->
                    <?php if (!empty($stats)): ?>
                        <div class="p-3 border-top">
                            <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                                <i class="fas fa-chart-bar me-2"></i>My Statistics
                            </h6>
                            <div class="row g-2">
                                <?php if (isClient()): ?>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Total Appointments:</span>
                                            <strong class="text-gold"><?= $stats['total_appointments'] ?? 0 ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Confirmed:</span>
                                            <strong class="text-success"><?= $stats['confirmed_appointments'] ?? 0 ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">Favorites:</span>
                                            <strong class="text-primary"><?= $stats['favorites'] ?? 0 ?></strong>
                                        </div>
                                    </div>
                                <?php elseif (isAgent()): ?>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Properties Managed:</span>
                                            <strong class="text-gold"><?= $stats['managed_properties'] ?? 0 ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">Appointments:</span>
                                            <strong class="text-success"><?= $stats['scheduled_appointments'] ?? 0 ?></strong>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Navigation -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                            <i class="fas fa-compass me-2"></i>Quick Navigation
                        </h6>
                        <div class="d-grid gap-2">
                            <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chart-pie me-1"></i>Dashboard Overview
                            </a>
                            <?php if (isClient()): ?>
                                <a href="dashboard.php?section=appointments" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar me-1"></i>My Appointments
                                </a>
                                <a href="dashboard.php?section=favorites" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-heart me-1"></i>My Favorites
                                </a>
                            <?php elseif (isAgent()): ?>
                                <a href="../agent/dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-bar me-1"></i>Agent Dashboard
                                </a>
                                <a href="../agent/schedule.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-alt me-1"></i>Schedule
                                </a>
                            <?php elseif (isAdmin()): ?>
                                <a href="../admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-cogs me-1"></i>Administration
                                </a>
                            <?php endif; ?>
                            <a href="../auth/logout.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Professional Navigation Tabs -->
                <nav>
                    <div class="nav nav-tabs border-0" id="nav-tab" role="tablist">
                        <button class="nav-link active" id="nav-profile-tab" data-bs-toggle="tab" data-bs-target="#nav-profile" type="button" role="tab">
                            <i class="fas fa-user me-2"></i>Personal Information
                        </button>
                        <button class="nav-link" id="nav-password-tab" data-bs-toggle="tab" data-bs-target="#nav-password" type="button" role="tab">
                            <i class="fas fa-lock me-2"></i>Security
                        </button>
                        <?php if (isAgent()): ?>
                            <button class="nav-link" id="nav-agent-tab" data-bs-toggle="tab" data-bs-target="#nav-agent" type="button" role="tab">
                                <i class="fas fa-user-tie me-2"></i>Professional Profile
                            </button>
                        <?php endif; ?>
                    </div>
                </nav>

                <div class="tab-content mt-4" id="nav-tabContent">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane fade show active" id="nav-profile" role="tabpanel">
                        <div class="content-card">
                            <div class="content-card-header">
                                <i class="fas fa-user-edit me-2"></i>Personal Information
                            </div>
                            <div class="content-card-body">
                                <form method="POST" action="" class="needs-validation">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="dashboard-form-group">
                                                <label class="form-label-luxury" for="first_name">First Name *</label>
                                                <input type="text" class="form-control-luxury" id="first_name" name="first_name" 
                                                       value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="dashboard-form-group">
                                                <label class="form-label-luxury" for="last_name">Last Name *</label>
                                                <input type="text" class="form-control-luxury" id="last_name" name="last_name" 
                                                       value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="dashboard-form-group">
                                        <label class="form-label-luxury" for="email">Email Address (non-editable)</label>
                                        <input type="email" class="form-control-luxury bg-grey-light" id="email" 
                                               value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                    </div>
                                    
                                    <div class="dashboard-form-group">
                                        <label class="form-label-luxury" for="phone">Phone Number</label>
                                        <input type="tel" class="form-control-luxury" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn-luxury-primary">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security / Password Tab -->
                    <div class="tab-pane fade" id="nav-password" role="tabpanel">
                        <div class="content-card">
                            <div class="content-card-header">
                                <i class="fas fa-shield-alt me-2"></i>Change Password
                            </div>
                            <div class="content-card-body">
                                <form method="POST" action="" id="passwordForm" class="needs-validation">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="dashboard-form-group">
                                        <label class="form-label-luxury" for="current_password">Current Password *</label>
                                        <input type="password" class="form-control-luxury" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="dashboard-form-group">
                                        <label class="form-label-luxury" for="password">New Password *</label>
                                        <input type="password" class="form-control-luxury" id="password" name="new_password" required>
                                        <!-- Password strength indicator will be handled by auth.js -->
                                        <div id="passwordStrength" class="password-strength mt-2 d-none">
                                            <div class="progress" style="height: 5px;">
                                                <div id="strengthBar" class="progress-bar" role="progressbar"></div>
                                            </div>
                                            <small id="strengthText" class="form-text"></small>
                                        </div>
                                    </div>
                                    
                                    <div class="dashboard-form-group">
                                        <label class="form-label-luxury" for="password_confirm">Confirm New Password *</label>
                                        <input type="password" class="form-control-luxury" id="password_confirm" name="confirm_password" required>
                                        <div class="invalid-feedback">Passwords do not match.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning" id="submitBtn">
                                        <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                                        <i class="fas fa-key me-2" id="submitIcon"></i>
                                        <span id="submitText">Change Password</span>
                                    </button>
                                </form>
                                
                                <!-- Security Tips -->
                                <div class="content-card mt-4">
                                    <div class="content-card-header bg-success text-white">
                                        <i class="fas fa-shield-alt me-2"></i>Security Tips
                                    </div>
                                    <div class="content-card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use at least 8 characters</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mix uppercase, lowercase, numbers and symbols</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Avoid personal information</li>
                                            <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Change your password regularly</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agent Profile Tab (only for agents) -->
                    <?php if (isAgent()): ?>
                        <div class="tab-pane fade" id="nav-agent" role="tabpanel">
                            <div class="content-card">
                                <div class="content-card-header">
                                    <i class="fas fa-user-tie me-2"></i>Professional Agent Profile
                                </div>
                                <div class="content-card-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="dashboard-form-group">
                                            <label class="form-label-luxury" for="agency_name">Agency Name</label>
                                            <input type="text" class="form-control-luxury" id="agency_name" name="agency_name" 
                                                   value="<?= htmlspecialchars($user['agency_name'] ?? '') ?>">
                                        </div>
                                        
                                        <div class="dashboard-form-group">
                                            <label class="form-label-luxury" for="cv">Professional Bio / CV</label>
                                            <textarea class="form-control-luxury" id="cv" name="cv" rows="6"><?= htmlspecialchars($user['cv_file_path'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn-luxury-primary">
                                            <i class="fas fa-save me-2"></i>Save Agent Profile
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <!-- Main JavaScript functionality from external file -->
    <script src="../assets/js/auth.js"></script>

    <style>
        .password-strength {
            font-size: 0.875rem;
        }
        
        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all var(--transition-normal);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-bottom-color: rgba(var(--primary-rgb), 0.3);
            background: var(--primary-ultra-light);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: var(--primary-ultra-light);
            font-weight: 600;
        }
        
        .tab-content {
            margin-top: 2rem;
        }
        
        /* Enhanced form styling */
        .form-control-luxury {
            min-height: 50px;
            font-size: 1rem;
        }
        
        textarea.form-control-luxury {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Professional card styling */
        .content-card .content-card-header.bg-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%) !important;
        }

        /* Password strength styling */
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }
    </style>
</body>
</html>