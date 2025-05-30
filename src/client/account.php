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

// Initialize variables to prevent warnings
$error = '';
$success = '';

// Initialize stats array with default values
$stats = [
    'total_appointments' => 0,
    'confirmed_appointments' => 0,
    'properties_viewed' => 0,
    'favorites' => 0
];

// Get complete user information
$query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name 
          FROM users u 
          LEFT JOIN agents a ON u.id = a.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user preferences with default values
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
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        
        // Validation of required fields
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!empty($phone) && strlen($phone) > 15) {
            $error = 'Phone number is too long (maximum 15 characters).';
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
                try {
                    // Check if updated_at column exists
                    $columns_query = "SHOW COLUMNS FROM users LIKE 'updated_at'";
                    $columns_stmt = $db->prepare($columns_query);
                    $columns_stmt->execute();
                    $has_updated_at = $columns_stmt->rowCount() > 0;
                    
                    // Update user information
                    if ($has_updated_at) {
                        $update_query = "UPDATE users SET 
                                       first_name = :first_name, 
                                       last_name = :last_name, 
                                       email = :email, 
                                       phone = :phone,
                                       updated_at = CURRENT_TIMESTAMP
                                       WHERE id = :user_id";
                    } else {
                        $update_query = "UPDATE users SET 
                                       first_name = :first_name, 
                                       last_name = :last_name, 
                                       email = :email, 
                                       phone = :phone
                                       WHERE id = :user_id";
                    }
                    
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
                        $error = 'Error updating profile. Please try again.';
                    }
                } catch (PDOException $e) {
                    error_log("Profile update error: " . $e->getMessage());
                    $error = 'Database error occurred. Please try again.';
                }
            }
        }
    }
    
    elseif ($action === 'update_preferences') {
        try {
            $theme = $_POST['theme'] ?? 'light';
            $language = $_POST['language'] ?? 'en';
            $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
            $notifications_sms = isset($_POST['notifications_sms']) ? 1 : 0;
            $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
            
            // Validate inputs
            if (!in_array($theme, ['light', 'dark'])) {
                $theme = 'light';
            }
            if (!in_array($language, ['en', 'fr', 'es'])) {
                $language = 'en';
            }
            
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
            
            // Save to user_preferences table if it exists
            try {
                // Check if user_preferences table exists
                $table_check = "SHOW TABLES LIKE 'user_preferences'";
                $table_stmt = $db->prepare($table_check);
                $table_stmt->execute();
                
                if ($table_stmt->rowCount() > 0) {
                    // Save theme preference
                    $pref_query = "INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                                  VALUES (:user_id, 'theme', :theme)
                                  ON DUPLICATE KEY UPDATE preference_value = :theme";
                    $pref_stmt = $db->prepare($pref_query);
                    $pref_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $pref_stmt->bindParam(':theme', $theme);
                    $pref_stmt->execute();
                    
                    // Save language preference
                    $lang_query = "INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                                  VALUES (:user_id, 'language', :language)
                                  ON DUPLICATE KEY UPDATE preference_value = :language";
                    $lang_stmt = $db->prepare($lang_query);
                    $lang_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $lang_stmt->bindParam(':language', $language);
                    $lang_stmt->execute();
                }
            } catch (Exception $e) {
                error_log("Preferences save error: " . $e->getMessage());
                // Continue anyway, session storage works
            }
            
            $success = 'Preferences updated successfully!';
        } catch (Exception $e) {
            error_log("Preferences update error: " . $e->getMessage());
            $error = 'Error updating preferences. Please try again.';
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must contain at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            try {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $password_query = "UPDATE users SET password_hash = :password WHERE id = :user_id";
                $password_stmt = $db->prepare($password_query);
                $password_stmt->bindParam(':password', $hashed_password);
                $password_stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($password_stmt->execute()) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Error changing password. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        }
    }
    
    elseif ($action === 'delete_account') {
        $confirm_password = $_POST['delete_password'] ?? '';
        
        if (!password_verify($confirm_password, $user['password_hash'])) {
            $error = 'Password confirmation failed. Account deletion cancelled.';
        } else {
            $error = 'Account deletion feature is currently under maintenance. Please contact support.';
        }
    }
}

// Get user statistics safely
try {
    if (function_exists('isClient') && isClient()) {
        $stats_query = "SELECT 
                       COUNT(*) as total_appointments
                       FROM appointments WHERE client_id = :user_id";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stats_stmt->execute();
        $total_result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        $confirmed_query = "SELECT COUNT(*) as confirmed_appointments
                           FROM appointments WHERE client_id = :user_id AND status = 'scheduled'";
        $confirmed_stmt = $db->prepare($confirmed_query);
        $confirmed_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $confirmed_stmt->execute();
        $confirmed_result = $confirmed_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get actual favorites count from user_favorites table
        $favorites_query = "SELECT COUNT(*) as favorites_count
                           FROM user_favorites WHERE user_id = :user_id";
        $favorites_stmt = $db->prepare($favorites_query);
        $favorites_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $favorites_stmt->execute();
        $favorites_result = $favorites_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($total_result) {
            $stats['total_appointments'] = (int)$total_result['total_appointments'];
        }
        if ($confirmed_result) {
            $stats['confirmed_appointments'] = (int)$confirmed_result['confirmed_appointments'];
        }
        if ($favorites_result) {
            $stats['favorites'] = (int)$favorites_result['favorites_count'];
        }
    }
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    // Keep default stats values
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($user_prefs['theme']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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
            --primary-color: #d4af37;
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
            padding-top: 0 !important;
            transition: all 0.3s ease;
        }

        /* Hide any potential navigation */
        body .navbar,
        body nav {
            display: none !important;
        }

        /* Enhanced header styling to match dashboard */
        .luxury-header {
            background: #000 !important;
            padding: 1rem 0;
            color: white;
            margin-bottom: 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none !important;
            color: white !important;
        }

        .brand-logo img {
            height: 80px !important;
        }

        .brand-logo:hover {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .user-profile {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            margin-right: 2rem;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-info .fw-semibold {
            font-size: 0.95rem;
            margin-bottom: 2px;
        }

        .user-info small {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        /* Breadcrumb styling */
        .breadcrumb-container {
            background: white;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 0;
        }

        .breadcrumb-custom {
            background: none;
            margin-bottom: 0;
            padding: 0;
        }

        .breadcrumb-custom .breadcrumb-item {
            color: #6c757d;
        }

        .breadcrumb-custom .breadcrumb-item.active {
            color: #333;
            font-weight: 600;
        }

        .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
            content: "/";
            color: #dee2e6;
        }

        .breadcrumb-custom .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .settings-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #f4d03f 100%);
            color: white;
            padding: 3rem 0 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .settings-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .settings-header .container {
            position: relative;
            z-index: 1;
        }

        .settings-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .settings-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
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
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
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
            width: 100%;
        }

        .form-control-custom:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.15);
            background: var(--bg-primary);
            outline: none;
        }

        .btn-luxury {
            background: linear-gradient(135deg, var(--primary-color) 0%, #f4d03f 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
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
            background-color: var(--primary-color);
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
            border-color: var(--primary-color);
            transform: translateY(-3px);
        }

        .theme-option.active {
            border-color: var(--primary-color);
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

        body[data-theme="dark"] .settings-card {
            background: #2c3034;
            border-color: #495057;
        }

        body[data-theme="dark"] .card-header-custom {
            background: #3a3f44;
            color: #e9ecef;
        }

        body[data-theme="dark"] .form-control-custom {
            background: #3a3f44;
            border-color: #495057;
            color: #e9ecef;
        }

        body[data-theme="dark"] .form-label-custom {
            color: #e9ecef;
        }

        body[data-theme="dark"] .btn-luxury {
            background: linear-gradient(135deg, #b8941f 0%, #e6c036 100%);
        }

        body[data-theme="dark"] .btn-luxury:hover {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
        }

        body[data-theme="dark"] .btn-danger-custom {
            background: linear-gradient(135deg, #c82333 0%, #d63031 100%);
        }

        body[data-theme="dark"] .btn-danger-custom:hover {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
        }

        /* Enhanced dropdown in header - matching dashboard style */
        .luxury-header .dropdown-menu,
        .account-dropdown-menu {
            background: white !important;
            backdrop-filter: blur(20px);
            border: none !important;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25) !important;
            border-radius: 16px !important;
            margin-top: 20px !important;
            z-index: 10050 !important;
            min-width: 320px;
            max-width: 380px;
            padding: 1rem 0;
            right: 2rem !important;
            left: auto !important;
            transform: none !important;
            border: 2px solid rgba(212, 175, 55, 0.2) !important;
            position: absolute !important;
            top: 100% !important;
        }

        /* Account dropdown hover functionality */
        .account-dropdown:hover .account-dropdown-menu {
            display: block !important;
        }

        /* Account dropdown animations */
        .account-dropdown-menu {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            display: none;
        }

        .account-dropdown:hover .account-dropdown-menu,
        .account-dropdown .dropdown-menu.show {
            opacity: 1 !important;
            transform: translateY(0) scale(1) !important;
            pointer-events: auto !important;
            display: block !important;
        }

        .luxury-header .dropdown-header {
            padding: 1rem 1.5rem 0.5rem;
            color: #6c757d;
            font-weight: 600;
        }

        .luxury-header .user-info-detailed {
            padding: 0.5rem;
        }

        .luxury-header .user-avatar-large {
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

        .luxury-header .dropdown-item,
        .account-dropdown-menu .dropdown-item {
            color: #333 !important;
            padding: 0.8rem 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            border-radius: 0;
            white-space: normal;
            background: transparent;
        }

        .luxury-header .dropdown-item:hover,
        .account-dropdown-menu .dropdown-item:hover {
            background: #f8f9fa !important;
            color: #d4af37 !important;
            transform: none !important;
            border-radius: 8px;
            margin: 0 0.5rem;
        }

        .luxury-header .dropdown-item i,
        .account-dropdown-menu .dropdown-item i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .luxury-header .stat-number,
        .account-dropdown-menu .stat-number {
            font-weight: 700;
            font-size: 1.1rem;
            color: #d4af37;
        }
    </style>
</head>
<body data-user-logged-in="true" data-page="account">
    <!-- Header -->
    <header class="luxury-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../pages/home.php" class="brand-logo text-decoration-none">
                        <img src="../assets/images/logo1.png" alt="Omnes Real Estate" height="40">
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <div class="dropdown account-dropdown">
                        <div class="user-profile d-inline-flex align-items-center dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                            <div class="user-avatar me-2">
                                <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="user-info me-2">
                                <div class="fw-semibold"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
                                <small><?= ucfirst($user['role'] ?? 'Member') ?> Account</small>
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end account-dropdown-menu">
                            <li class="dropdown-header">
                                <div class="user-info-detailed">
                                    <div class="user-avatar-large mx-auto mb-2">
                                        <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-semibold"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
                                        <small class="text-muted"><?= ucfirst($user['role'] ?? 'Member') ?> Member</small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Quick Stats -->
                            <li class="px-3 py-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                                        <small class="text-muted">Appointments</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number"><?= $stats['favorites'] ?></div>
                                        <small class="text-muted">Favorites</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number"><?= $stats['properties_viewed'] ?></div>
                                        <small class="text-muted">Views</small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Navigation Links -->
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="fas fa-chart-pie me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Dashboard</div>
                                        <small class="text-muted">Overview & Statistics</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="account.php">
                                    <i class="fas fa-user-cog me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-semibold">Account Settings</div>
                                        <small class="text-muted">Profile & Preferences</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php?section=favorites">
                                    <i class="fas fa-heart me-2 text-danger"></i>
                                    <div>
                                        <div class="fw-semibold">My Favorites</div>
                                        <small class="text-muted">Saved Properties</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php?section=appointments">
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
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-custom">
                    <li class="breadcrumb-item"><a href="../pages/home.php"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Account Settings</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="settings-container">
        <!-- Header -->
        <div class="settings-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="fas fa-user-cog me-3"></i>Account Settings</h1>
                        <p class="mb-0">Manage your profile, preferences, and security settings</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="dashboard.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= htmlspecialchars($stats['total_appointments']) ?></div>
                <div class="stat-label">Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= htmlspecialchars($stats['confirmed_appointments']) ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= htmlspecialchars($stats['favorites']) ?></div>
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
                                               value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="last_name">Last Name *</label>
                                        <input type="text" class="form-control-custom" id="last_name" name="last_name" 
                                               value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="email">Email Address *</label>
                                <input type="email" class="form-control-custom" id="email" name="email"
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
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
                                <small class="text-muted">Password must be at least 8 characters long</small>
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
                                <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <h5><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h5>
                            <p class="text-muted"><?= ucfirst($user['role'] ?? 'Member') ?></p>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Member since <?= date('F Y', strtotime($user['created_at'] ?? '2023-01-01')) ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Account dropdown hover functionality
        document.addEventListener('DOMContentLoaded', function() {
            const accountDropdown = document.querySelector('.account-dropdown');
            const dropdownMenu = document.querySelector('.account-dropdown-menu');
            
            if (accountDropdown && dropdownMenu) {
                // Show dropdown on hover
                accountDropdown.addEventListener('mouseenter', function() {
                    dropdownMenu.style.display = 'block';
                    setTimeout(() => {
                        dropdownMenu.style.opacity = '1';
                        dropdownMenu.style.transform = 'translateY(0) scale(1)';
                        dropdownMenu.style.pointerEvents = 'auto';
                    }, 10);
                });
                
                // Hide dropdown when leaving both elements
                accountDropdown.addEventListener('mouseleave', function(e) {
                    if (!dropdownMenu.contains(e.relatedTarget)) {
                        hideDropdown();
                    }
                });
                
                dropdownMenu.addEventListener('mouseleave', function(e) {
                    if (!accountDropdown.contains(e.relatedTarget)) {
                        hideDropdown();
                    }
                });
                
                function hideDropdown() {
                    dropdownMenu.style.opacity = '0';
                    dropdownMenu.style.transform = 'translateY(-10px) scale(0.95)';
                    dropdownMenu.style.pointerEvents = 'none';
                    setTimeout(() => {
                        if (dropdownMenu.style.opacity === '0') {
                            dropdownMenu.style.display = 'none';
                        }
                    }, 300);
                }
            }
        });

        // Theme selection
        function selectTheme(theme) {
            // Update UI
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
            });
            
            // Find and activate the selected theme option
            const selectedOption = document.querySelector(`.theme-option input[value="${theme}"]`).closest('.theme-option');
            if (selectedOption) {
                selectedOption.classList.add('active');
            }
            
            // Update radio button
            const radioBtn = document.querySelector(`input[name="theme"][value="${theme}"]`);
            if (radioBtn) {
                radioBtn.checked = true;
            }
            
            // Apply theme immediately
            document.documentElement.setAttribute('data-theme', theme);
            
            // Save to localStorage
            localStorage.setItem('userTheme', theme);
        }

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
            const savedTheme = localStorage.getItem('userTheme');
            if (savedTheme && savedTheme !== currentTheme) {
                selectTheme(savedTheme);
            }
        });
    </script>
</body>
</html>