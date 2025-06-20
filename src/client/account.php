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
    
    elseif ($action === 'update_payment') {
        $card_number = trim($_POST['card_number'] ?? '');
        $card_holder_name = trim($_POST['card_holder_name'] ?? '');
        $expiration_month = trim($_POST['expiration_month'] ?? '');
        $expiration_year = trim($_POST['expiration_year'] ?? '');
        $billing_address_line1 = trim($_POST['billing_address_line1'] ?? '');
        $billing_city = trim($_POST['billing_city'] ?? '');
        $billing_state = trim($_POST['billing_state'] ?? '');
        $billing_postal_code = trim($_POST['billing_postal_code'] ?? '');
        $billing_country = trim($_POST['billing_country'] ?? 'France');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validation
        if (empty($card_number) || empty($card_holder_name) || empty($expiration_month) || empty($expiration_year) ||
            empty($billing_address_line1) || empty($billing_city) || empty($billing_state) || empty($billing_postal_code)) {
            $error = 'All payment and billing fields are required.';
        } elseif (!preg_match('/^\d{16}$/', $card_number)) {
            $error = 'Card number must be exactly 16 digits.';
        } elseif (!preg_match('/^[a-zA-Z\s\'-]{2,100}$/', $card_holder_name)) {
            $error = 'Card holder name must be between 2-100 characters and contain only letters, spaces, hyphens, and apostrophes.';
        } elseif (!in_array($expiration_month, range(1, 12))) {
            $error = 'Invalid expiration month.';
        } elseif ($expiration_year < date('Y') || $expiration_year > (date('Y') + 20)) {
            $error = 'Invalid expiration year.';
        } else {
            // Validate expiration date is not in the past
            $current_year = (int)date('Y');
            $current_month = (int)date('m');
            
            if ($expiration_year < $current_year || ($expiration_year == $current_year && $expiration_month < $current_month)) {
                $error = 'Card expiration date cannot be in the past.';
            } else {
                try {
                    // Determine card type based on first digit
                    $first_digit = substr($card_number, 0, 1);
                    $first_two_digits = substr($card_number, 0, 2);
                    $card_type = 'Unknown';
                    
                    if ($first_digit == '4') {
                        $card_type = 'Visa';
                    } elseif (in_array($first_two_digits, ['51', '52', '53', '54', '55']) || 
                              (intval($first_two_digits) >= 22 && intval($first_two_digits) <= 27)) {
                        $card_type = 'MasterCard';
                    } elseif (in_array($first_two_digits, ['34', '37'])) {
                        $card_type = 'American Express';
                    } elseif ($first_two_digits == '60') {
                        $card_type = 'Discover';
                    }
                    
                    // Get last 4 digits only for storage
                    $card_last_four = substr($card_number, -4);
                    
                    // Check if payment_information table exists with correct schema
                    $table_check = "SHOW TABLES LIKE 'payment_information'";
                    $table_stmt = $db->prepare($table_check);
                    $table_stmt->execute();
                    
                    if ($table_stmt->rowCount() == 0) {
                        // Create payment_information table with secure design
                        $create_table = "CREATE TABLE payment_information (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            card_type VARCHAR(50) NOT NULL,
                            card_last_four VARCHAR(4) NOT NULL,
                            card_holder_name VARCHAR(100) NOT NULL,
                            expiration_month TINYINT NOT NULL,
                            expiration_year SMALLINT NOT NULL,
                            billing_address_line1 VARCHAR(255) NOT NULL,
                            billing_city VARCHAR(100) NOT NULL,
                            billing_state VARCHAR(100) NOT NULL,
                            billing_postal_code VARCHAR(20) NOT NULL,
                            billing_country VARCHAR(100) NOT NULL DEFAULT 'France',
                            is_default BOOLEAN DEFAULT FALSE,
                            is_verified BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            INDEX idx_user_id (user_id)
                        )";
                        $db->exec($create_table);
                    }
                    
                    // Insert or update payment information
                    $payment_check = "SELECT id FROM payment_information WHERE user_id = :user_id";
                    $payment_stmt = $db->prepare($payment_check);
                    $payment_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $payment_stmt->execute();
                    
                    if ($payment_stmt->rowCount() > 0) {
                        // Update existing payment info
                        $update_payment = "UPDATE payment_information SET 
                                         card_type = :card_type,
                                         card_last_four = :card_last_four,
                                         card_holder_name = :card_holder_name,
                                         expiration_month = :expiration_month,
                                         expiration_year = :expiration_year,
                                         billing_address_line1 = :billing_address_line1,
                                         billing_city = :billing_city,
                                         billing_state = :billing_state,
                                         billing_postal_code = :billing_postal_code,
                                         billing_country = :billing_country,
                                         is_default = :is_default,
                                         updated_at = CURRENT_TIMESTAMP
                                         WHERE user_id = :user_id";
                        $update_stmt = $db->prepare($update_payment);
                    } else {
                        // Insert new payment info
                        $update_payment = "INSERT INTO payment_information (
                                         user_id, card_type, card_last_four, card_holder_name,
                                         expiration_month, expiration_year, billing_address_line1,
                                         billing_city, billing_state, billing_postal_code,
                                         billing_country, is_default)
                                         VALUES (:user_id, :card_type, :card_last_four, :card_holder_name,
                                         :expiration_month, :expiration_year, :billing_address_line1,
                                         :billing_city, :billing_state, :billing_postal_code,
                                         :billing_country, :is_default)";
                        $update_stmt = $db->prepare($update_payment);
                    }
                    
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $update_stmt->bindParam(':card_type', $card_type);
                    $update_stmt->bindParam(':card_last_four', $card_last_four);
                    $update_stmt->bindParam(':card_holder_name', $card_holder_name);
                    $update_stmt->bindParam(':expiration_month', $expiration_month);
                    $update_stmt->bindParam(':expiration_year', $expiration_year);
                    $update_stmt->bindParam(':billing_address_line1', $billing_address_line1);
                    $update_stmt->bindParam(':billing_city', $billing_city);
                    $update_stmt->bindParam(':billing_state', $billing_state);
                    $update_stmt->bindParam(':billing_postal_code', $billing_postal_code);
                    $update_stmt->bindParam(':billing_country', $billing_country);
                    $update_stmt->bindParam(':is_default', $is_default);
                    
                    if ($update_stmt->execute()) {
                        $success = 'Payment information updated successfully!';
                    } else {
                        $error = 'Error updating payment information. Please try again.';
                    }
                } catch (PDOException $e) {
                    error_log("Payment update error: " . $e->getMessage());
                    $error = 'Database error occurred. Please try again.';
                }
            }
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

// Get existing payment information
$payment_info = null;
try {
    $payment_query = "SELECT * FROM payment_information WHERE user_id = :user_id";
    $payment_stmt = $db->prepare($payment_query);
    $payment_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $payment_stmt->execute();
    $payment_info = $payment_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet, that's ok
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
                        <h1><i class="fas fa-user-cog me-3"></i>Your Account Settings</h1>
                        <p class="mb-0">Manage your profile, preferences, and security settings</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="dashboard.php" class="btn btn-light">
                            <i class="fas fa-chart-pie me-2"></i>Return to Dashboard
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

                <!-- Payment Information -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-credit-card me-2"></i>Payment Information
                    </div>
                    <div class="card-body-custom">
                        <form method="POST" action="" id="paymentForm">
                            <input type="hidden" name="action" value="update_payment">
                            
                            <!-- Card Information -->
                            <h6 class="mb-3 fw-bold text-primary"><i class="fas fa-credit-card me-2"></i>Card Details</h6>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="card_number">Card Number *</label>
                                <input type="text" class="form-control-custom" id="card_number" name="card_number" 
                                       placeholder="1234567890123456" maxlength="16" required
                                       <?php if ($payment_info): ?>
                                           value="************<?= htmlspecialchars($payment_info['card_last_four']) ?>"
                                           data-masked="true"
                                           data-last-four="<?= htmlspecialchars($payment_info['card_last_four']) ?>"
                                       <?php endif; ?>>
                                <small class="text-muted">Enter your 16-digit card number (only last 4 digits will be stored)</small>
                                <?php if ($payment_info): ?>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Current card: <?= htmlspecialchars($payment_info['card_type']) ?> ending in <?= htmlspecialchars($payment_info['card_last_four']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="card_holder_name">Card Holder Name *</label>
                                <input type="text" class="form-control-custom" id="card_holder_name" name="card_holder_name" 
                                       value="<?= $payment_info ? htmlspecialchars($payment_info['card_holder_name']) : '' ?>"
                                       placeholder="John Doe" required>
                                <small class="text-muted">Name as it appears on your card</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="expiration_month">Expiration Month *</label>
                                        <select class="form-control-custom" id="expiration_month" name="expiration_month" required>
                                            <option value="">Select Month</option>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($payment_info && $payment_info['expiration_month'] == $i) ? 'selected' : '' ?>>
                                                    <?= sprintf('%02d - %s', $i, date('F', mktime(0, 0, 0, $i, 1))) ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="expiration_year">Expiration Year *</label>
                                        <select class="form-control-custom" id="expiration_year" name="expiration_year" required>
                                            <option value="">Select Year</option>
                                            <?php for ($i = date('Y'); $i <= date('Y') + 20; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($payment_info && $payment_info['expiration_year'] == $i) ? 'selected' : '' ?>>
                                                    <?= $i ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Billing Address -->
                            <h6 class="mb-3 fw-bold text-primary mt-4"><i class="fas fa-map-marker-alt me-2"></i>Billing Address</h6>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="billing_address_line1">Address Line 1 *</label>
                                <input type="text" class="form-control-custom" id="billing_address_line1" name="billing_address_line1" 
                                       value="<?= $payment_info ? htmlspecialchars($payment_info['billing_address_line1']) : '' ?>"
                                       placeholder="123 Main Street" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="billing_city">City *</label>
                                        <input type="text" class="form-control-custom" id="billing_city" name="billing_city" 
                                               value="<?= $payment_info ? htmlspecialchars($payment_info['billing_city']) : '' ?>"
                                               placeholder="Paris" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="billing_state">State/Region *</label>
                                        <input type="text" class="form-control-custom" id="billing_state" name="billing_state" 
                                               value="<?= $payment_info ? htmlspecialchars($payment_info['billing_state']) : '' ?>"
                                               placeholder="Île-de-France" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="billing_postal_code">Postal Code *</label>
                                        <input type="text" class="form-control-custom" id="billing_postal_code" name="billing_postal_code" 
                                               value="<?= $payment_info ? htmlspecialchars($payment_info['billing_postal_code']) : '' ?>"
                                               placeholder="75001" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="billing_country">Country *</label>
                                        <select class="form-control-custom" id="billing_country" name="billing_country" required>
                                            <option value="France" <?= (!$payment_info || $payment_info['billing_country'] == 'France') ? 'selected' : '' ?>>France</option>
                                            <option value="Belgium" <?= ($payment_info && $payment_info['billing_country'] == 'Belgium') ? 'selected' : '' ?>>Belgium</option>
                                            <option value="Switzerland" <?= ($payment_info && $payment_info['billing_country'] == 'Switzerland') ? 'selected' : '' ?>>Switzerland</option>
                                            <option value="Spain" <?= ($payment_info && $payment_info['billing_country'] == 'Spain') ? 'selected' : '' ?>>Spain</option>
                                            <option value="Italy" <?= ($payment_info && $payment_info['billing_country'] == 'Italy') ? 'selected' : '' ?>>Italy</option>
                                            <option value="Germany" <?= ($payment_info && $payment_info['billing_country'] == 'Germany') ? 'selected' : '' ?>>Germany</option>
                                            <option value="United Kingdom" <?= ($payment_info && $payment_info['billing_country'] == 'United Kingdom') ? 'selected' : '' ?>>United Kingdom</option>
                                            <option value="Other" <?= ($payment_info && !in_array($payment_info['billing_country'], ['France', 'Belgium', 'Switzerland', 'Spain', 'Italy', 'Germany', 'United Kingdom'])) ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" 
                                           <?= ($payment_info && $payment_info['is_default']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_default">
                                        Set as default payment method
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-shield-alt me-2"></i>
                                <strong>Security Notice:</strong> We use industry-standard encryption to protect your payment information. Only the last 4 digits of your card number are stored for identification purposes.
                            </div>
                            
                            <button type="submit" class="btn-luxury">
                                <i class="fas fa-save me-2"></i>Save Payment Information
                            </button>
                            
                            <?php if ($payment_info): ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Last updated: <?= date('F j, Y', strtotime($payment_info['updated_at'] ?? $payment_info['created_at'])) ?>
                                        <?php if ($payment_info['is_verified']): ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="fas fa-check me-1"></i>Verified
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
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

        // Payment form validation
        document.getElementById('card_number').addEventListener('input', function() {
            // Check if this is a masked field that user is starting to edit
            if (this.dataset.masked === 'true') {
                // Clear the masked value when user starts typing
                this.value = '';
                this.dataset.masked = 'false';
                this.placeholder = '1234567890123456';
                
                // Remove the current card info display
                const currentCardInfo = document.querySelector('.bg-light.rounded');
                if (currentCardInfo) {
                    currentCardInfo.style.display = 'none';
                }
            }
            
            // Only allow numbers
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 16 digits
            if (this.value.length > 16) {
                this.value = this.value.slice(0, 16);
            }
            
            // Show card type based on first digits
            const cardType = getCardType(this.value);
            updateCardTypeDisplay(cardType);
        });
        
        // Handle focus event to clear masked value
        document.getElementById('card_number').addEventListener('focus', function() {
            if (this.dataset.masked === 'true') {
                this.select(); // Select all text so user can easily replace it
            }
        });
        
        // Handle blur event to restore masked value if field is empty
        document.getElementById('card_number').addEventListener('blur', function() {
            if (this.value === '' && this.dataset.lastFour) {
                this.value = '************' + this.dataset.lastFour;
                this.dataset.masked = 'true';
                
                // Show the current card info again
                const currentCardInfo = document.querySelector('.bg-light.rounded');
                if (currentCardInfo) {
                    currentCardInfo.style.display = 'block';
                }
            }
        });
        
        // Payment form validation on submit
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const cardNumber = document.getElementById('card_number').value;
            const cardHolderName = document.getElementById('card_holder_name').value;
            const expirationMonth = document.getElementById('expiration_month').value;
            const expirationYear = document.getElementById('expiration_year').value;
            
            // Check if user is trying to submit with masked card number
            if (cardNumber.includes('*')) {
                e.preventDefault();
                alert('Please enter a new card number or keep the current one by leaving the field unchanged.');
                return;
            }
            
            // Validate card number (16 digits)
            if (!/^\d{16}$/.test(cardNumber)) {
                e.preventDefault();
                alert('Card number must be exactly 16 digits.');
                return;
            }
            
            // Validate card holder name
            if (!/^[a-zA-Z\s\'-]{2,100}$/.test(cardHolderName)) {
                e.preventDefault();
                alert('Card holder name must be between 2-100 characters and contain only letters, spaces, hyphens, and apostrophes.');
                return;
            }
            
            // Validate expiration date is not in the past
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth() + 1;
            
            if (parseInt(expirationYear) < currentYear || 
                (parseInt(expirationYear) === currentYear && parseInt(expirationMonth) < currentMonth)) {
                e.preventDefault();
                alert('Card expiration date cannot be in the past.');
                return;
            }
        });
        
        // Function to determine card type
        function getCardType(cardNumber) {
            const firstDigit = cardNumber.charAt(0);
            const firstTwoDigits = cardNumber.substring(0, 2);
            
            if (firstDigit === '4') {
                return 'Visa';
            } else if (['51', '52', '53', '54', '55'].includes(firstTwoDigits) || 
                       (parseInt(firstTwoDigits) >= 22 && parseInt(firstTwoDigits) <= 27)) {
                return 'MasterCard';
            } else if (['34', '37'].includes(firstTwoDigits)) {
                return 'American Express';
            } else if (firstTwoDigits === '60') {
                return 'Discover';
            }
            return 'Unknown';
        }
        
        // Function to update card type display
        function updateCardTypeDisplay(cardType) {
            const cardNumberField = document.getElementById('card_number');
            
            // Remove any existing background styling that interferes with visibility
            cardNumberField.style.backgroundImage = 'none';
            
            // You can add a subtle visual indicator next to the field instead
            let existingIndicator = document.querySelector('.card-type-indicator');
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (cardType !== 'Unknown' && cardNumberField.value.length >= 4) {
                const indicator = document.createElement('small');
                indicator.className = 'card-type-indicator text-muted mt-1 d-block';
                indicator.innerHTML = `<i class="fas fa-credit-card me-1"></i>Detected: ${cardType}`;
                cardNumberField.parentNode.appendChild(indicator);
            }
        }

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