<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$db = $database->getConnection();

$error = '';
$success = '';

// Get complete user information
$query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name 
          FROM users u 
          LEFT JOIN agents a ON u.id = a.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user preferences
$user_prefs = [
    'theme' => $_SESSION['user_theme'] ?? 'light',
    'language' => $_SESSION['user_language'] ?? 'en',
    'notifications_email' => $_SESSION['notifications_email'] ?? true,
    'notifications_sms' => $_SESSION['notifications_sms'] ?? false,
    'marketing_emails' => $_SESSION['marketing_emails'] ?? true
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Validation and cleaning of input data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $new_email = trim($_POST['email']);
        
        // Validation of required fields
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email is already taken by another user
            if ($new_email !== $user['email']) {
                $email_check = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $email_stmt = $db->prepare($email_check);
                $email_stmt->bindParam(':email', $new_email);
                $email_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $email_stmt->execute();
                
                if ($email_stmt->rowCount() > 0) {
                    $error = 'This email address is already in use by another account.';
                }
            }
            
            if (empty($error)) {
                // Update user information
                $update_query = "UPDATE users SET 
                               first_name = :first_name, last_name = :last_name, 
                               email = :email, phone = :phone,
                               updated_at = CURRENT_TIMESTAMP
                               WHERE id = :user_id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':first_name', $first_name);
                $update_stmt->bindParam(':last_name', $last_name);
                $update_stmt->bindParam(':email', $new_email);
                $update_stmt->bindParam(':phone', $phone);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    // Update session variables
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $new_email;
                    
                    $success = 'Profile updated successfully!';
                    
                    // Reload user data
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Error updating profile.';
                }
            }
        }
    }
    
    elseif ($action === 'update_preferences') {
        $theme = $_POST['theme'] ?? 'light';
        $language = $_POST['language'] ?? 'en';
        $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
        $notifications_sms = isset($_POST['notifications_sms']) ? 1 : 0;
        $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
        
        // Update session preferences
        $_SESSION['user_theme'] = $theme;
        $_SESSION['user_language'] = $language;
        $_SESSION['notifications_email'] = $notifications_email;
        $_SESSION['notifications_sms'] = $notifications_sms;
        $_SESSION['marketing_emails'] = $marketing_emails;
        
        // Update user preferences array
        $user_prefs = [
            'theme' => $theme,
            'language' => $language,
            'notifications_email' => $notifications_email,
            'notifications_sms' => $notifications_sms,
            'marketing_emails' => $marketing_emails
        ];
        
        // You could also save these to database in a user_preferences table
        // For now, we'll just use session storage
        
        $success = 'Preferences updated successfully!';
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must contain at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            // Hash new password with bcrypt
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
    
    elseif ($action === 'delete_account') {
        $confirm_password = $_POST['delete_password'];
        
        if (!password_verify($confirm_password, $user['password_hash'])) {
            $error = 'Password confirmation failed. Account deletion cancelled.';
        } else {
            // In a real application, you might want to soft delete or archive the account
            // For now, we'll just show a message
            $error = 'Account deletion feature is currently under maintenance. Please contact support.';
        }
    }
}

// Get user statistics
$stats = [
    'total_appointments' => 0,
    'confirmed_appointments' => 0,
    'properties_viewed' => 0,
    'favorites' => 0
];

try {
    if (isClient()) {
        $stats_query = "SELECT 
                       (SELECT COUNT(*) FROM appointments WHERE client_id = :user_id) as total_appointments,
                       (SELECT COUNT(*) FROM appointments WHERE client_id = :user_id AND status = 'scheduled') as confirmed_appointments
                       ";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stats_stmt->execute();
        $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_stats) {
            $stats = array_merge($stats, $user_stats);
        }
    }
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= $user_prefs['theme'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Base CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Dashboard CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <style>
        /* Theme Variables */
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-primary: #333333;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --bg-primary: #1a1d21;
            --bg-secondary: #2c3034;
            --bg-tertiary: #3a3f44;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --border-color: #495057;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding-top: 80px;
            transition: all 0.3s ease;
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .settings-header {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .settings-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .settings-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .card-header-custom {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .card-body-custom {
            padding: 2rem;
        }

        .form-group-custom {
            margin-bottom: 1.5rem;
        }

        .form-label-custom {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control-custom {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control-custom:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.15);
            background: var(--bg-primary);
            outline: none;
        }

        .btn-luxury {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-luxury:hover {
            background: linear-gradient(135deg, #b8941f 0%, #e6c036 100%);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-danger-custom {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger-custom:hover {
            background: linear-gradient(135deg, #c82333 0%, #d63031 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .switch-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .switch-container:last-child {
            border-bottom: none;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: 0.3s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #d4af37;
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .theme-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .theme-option {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .theme-option:hover {
            border-color: #d4af37;
            transform: translateY(-3px);
        }

        .theme-option.active {
            border-color: #d4af37;
            background: rgba(212, 175, 55, 0.1);
        }

        .theme-preview {
            width: 100%;
            height: 60px;
            border-radius: 8px;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .theme-preview.light {
            background: linear-gradient(45deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #dee2e6;
        }

        .theme-preview.dark {
            background: linear-gradient(45deg, #1a1d21 0%, #2c3034 100%);
            border: 1px solid #495057;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #d4af37;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .danger-zone {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: var(--border-color);
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }

        .breadcrumb-custom {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-custom .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumb-custom .breadcrumb-item.active {
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .settings-container {
                padding: 1rem;
            }
            
            .settings-header {
                padding: 1.5rem;
            }
            
            .card-body-custom {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body data-user-logged-in="true">
    <!-- Include Enhanced Navigation -->
    <?php include '../includes/navigation.php'; ?>

    <div class="settings-container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-custom">
                <li class="breadcrumb-item"><a href="../pages/home.php"><i class="fas fa-home me-1"></i>Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Account Settings</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="settings-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="fas fa-user-cog me-3"></i>Account Settings</h1>
                    <p class="mb-0 opacity-90">Manage your profile, preferences, and security settings</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

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

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['confirmed_appointments'] ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['favorites'] ?></div>
                <div class="stat-label">Favorites</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= date('Y', strtotime($user['created_at'] ?? '2023-01-01')) ?></div>
                <div class="stat-label">Member Since</div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Personal Information -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-user me-2"></i>Personal Information
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="first_name">First Name *</label>
                                        <input type="text" class="form-control-custom" id="first_name" name="first_name" 
                                               value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="last_name">Last Name *</label>
                                        <input type="text" class="form-control-custom" id="last_name" name="last_name" 
                                               value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="email">Email Address *</label>
                                <input type="email" class="form-control-custom" id="email" name="email"
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                <small class="text-muted">Changing your email will require verification</small>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="phone">Phone Number</label>
                                <input type="tel" class="form-control-custom" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            
                            <button type="submit" class="btn-luxury">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Appearance & Preferences -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-palette me-2"></i>Appearance & Preferences
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="" id="preferencesForm">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <!-- Theme Selection -->
                            <div class="form-group-custom">
                                <label class="form-label-custom">Theme Preference</label>
                                <div class="theme-selector">
                                    <div class="theme-option <?= $user_prefs['theme'] === 'light' ? 'active' : '' ?>" 
                                         onclick="selectTheme('light')">
                                        <div class="theme-preview light"></div>
                                        <div class="fw-semibold">Light Theme</div>
                                        <small class="text-muted">Clean and bright interface</small>
                                        <input type="radio" name="theme" value="light" <?= $user_prefs['theme'] === 'light' ? 'checked' : '' ?> style="display: none;">
                                    </div>
                                    <div class="theme-option <?= $user_prefs['theme'] === 'dark' ? 'active' : '' ?>" 
                                         onclick="selectTheme('dark')">
                                        <div class="theme-preview dark"></div>
                                        <div class="fw-semibold">Dark Theme</div>
                                        <small class="text-muted">Easy on the eyes</small>
                                        <input type="radio" name="theme" value="dark" <?= $user_prefs['theme'] === 'dark' ? 'checked' : '' ?> style="display: none;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Language Selection -->
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="language">Language</label>
                                <select class="form-control-custom" id="language" name="language">
                                    <option value="en" <?= $user_prefs['language'] === 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="fr" <?= $user_prefs['language'] === 'fr' ? 'selected' : '' ?>>Français</option>
                                    <option value="es" <?= $user_prefs['language'] === 'es' ? 'selected' : '' ?>>Español</option>
                                </select>
                            </div>
                            
                            <!-- Notification Preferences -->
                            <div class="form-group-custom">
                                <label class="form-label-custom">Notification Preferences</label>
                                
                                <div class="switch-container">
                                    <div>
                                        <div class="fw-semibold">Email Notifications</div>
                                        <small class="text-muted">Appointment reminders and updates</small>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="notifications_email" <?= $user_prefs['notifications_email'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div>
                                        <div class="fw-semibold">SMS Notifications</div>
                                        <small class="text-muted">Urgent appointment updates</small>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="notifications_sms" <?= $user_prefs['notifications_sms'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="switch-container">
                                    <div>
                                        <div class="fw-semibold">Marketing Emails</div>
                                        <small class="text-muted">New properties and special offers</small>
                                    </div>
                                    <label class="switch">
                                        <input type="checkbox" name="marketing_emails" <?= $user_prefs['marketing_emails'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-luxury">
                                <i class="fas fa-save me-2"></i>Save Preferences
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-shield-alt me-2"></i>Security Settings
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="current_password">Current Password *</label>
                                <input type="password" class="form-control-custom" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="new_password">New Password *</label>
                                <input type="password" class="form-control-custom" id="new_password" name="new_password" required>
                                <div class="password-strength">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthFill" style="width: 0%;"></div>
                                    </div>
                                    <small id="strengthText" class="text-muted">Enter a password to see strength</small>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="confirm_password">Confirm New Password *</label>
                                <input type="password" class="form-control-custom" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn-luxury">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </div>
                    <div class="card-body-custom">
                        <div class="d-grid gap-3">
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-pie me-2"></i>Go to Dashboard
                            </a>
                            <a href="dashboard.php?section=appointments" class="btn btn-outline-success">
                                <i class="fas fa-calendar-alt me-2"></i>My Appointments
                            </a>
                            <a href="dashboard.php?section=favorites" class="btn btn-outline-danger">
                                <i class="fas fa-heart me-2"></i>My Favorites
                            </a>
                            <a href="../pages/explore.php" class="btn btn-outline-warning">
                                <i class="fas fa-search me-2"></i>Browse Properties
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-info-circle me-2"></i>Account Information
                    </div>
                    <div class="card-body-custom">
                        <div class="text-center mb-3">
                            <div class="user-avatar-large mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.5rem;">
                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                            </div>
                            <h5><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                            <p class="text-muted"><?= ucfirst($user['role']) ?> Member</p>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Member since <?= date('F Y', strtotime($user['created_at'] ?? '2023-01-01')) ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="settings-card">
                    <div class="card-header-custom text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                    </div>
                    <div class="card-body-custom">
                        <div class="danger-zone">
                            <h6 class="text-danger mb-3">Delete Account</h6>
                            <p class="text-muted small mb-3">
                                Once you delete your account, there is no going back. Please be certain.
                            </p>
                            <button type="button" class="btn-danger-custom" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash me-2"></i>Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_account">
                        <div class="alert alert-danger">
                            <strong>Warning!</strong> This action cannot be undone. All your data will be permanently deleted.
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom" for="delete_password">Enter your password to confirm:</label>
                            <input type="password" class="form-control-custom" id="delete_password" name="delete_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-danger-custom">
                            <i class="fas fa-trash me-2"></i>Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/navigation.js"></script>

    <script>
        // Theme selection
        function selectTheme(theme) {
            // Update UI
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Update radio button
            document.querySelector(`input[name="theme"][value="${theme}"]`).checked = true;
            
            // Apply theme immediately
            document.documentElement.setAttribute('data-theme', theme);
            
            // Update navigation theme
            updateThemeIcon(theme);
            updateThemeStatus(theme);
            
            // Save to localStorage
            localStorage.setItem('userTheme', theme);
        }

        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            if (password.match(/[^A-Za-z0-9]/)) strength += 25;
            
            strengthFill.style.width = Math.min(strength, 100) + '%';
            
            if (strength < 50) {
                strengthFill.className = 'strength-fill strength-weak';
                feedback = 'Weak password';
            } else if (strength < 75) {
                strengthFill.className = 'strength-fill strength-medium';
                feedback = 'Medium strength';
            } else {
                strengthFill.className = 'strength-fill strength-strong';
                feedback = 'Strong password';
            }
            
            strengthText.textContent = password.length > 0 ? feedback : 'Enter a password to see strength';
        });

        // Auto-save preferences on change
        document.getElementById('preferencesForm').addEventListener('change', function() {
            // You could implement auto-save here
            console.log('Preferences changed');
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            updateThemeIcon(currentTheme);
            updateThemeStatus(currentTheme);
        });
    </script>
</body>
</html>