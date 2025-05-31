<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn() || !isAgent()) {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$db = $database->getConnection();

$error = '';
$success = '';

// Get complete agent information - match actual database columns
$query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name, 
                 a.agency_email, a.years_experience, a.average_rating, a.total_sales, a.total_transactions
          FROM users u 
          INNER JOIN agents a ON u.id = a.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Basic user information
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        
        // Agent-specific information (only fields that exist)
        $agency_name = trim($_POST['agency_name'] ?? '');
        $agency_email = trim($_POST['agency_email'] ?? '');
        $years_experience = (int)($_POST['years_experience'] ?? 0);
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!empty($agency_email) && !filter_var($agency_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid agency email address.';
        } else {
            // Check if email is already taken
            if ($new_email !== $agent['email']) {
                $email_check = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $email_stmt = $db->prepare($email_check);
                $email_stmt->bindParam(':email', $new_email);
                $email_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $email_stmt->execute();
                
                if ($email_stmt->rowCount() > 0) {
                    $error = 'This email address is already in use.';
                }
            }
            
            // Check if agency email is already taken
            if (!empty($agency_email) && $agency_email !== $agent['agency_email']) {
                $agency_email_check = "SELECT user_id FROM agents WHERE agency_email = :agency_email AND user_id != :user_id";
                $agency_email_stmt = $db->prepare($agency_email_check);
                $agency_email_stmt->bindParam(':agency_email', $agency_email);
                $agency_email_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $agency_email_stmt->execute();
                
                if ($agency_email_stmt->rowCount() > 0) {
                    $error = 'This agency email address is already in use.';
                }
            }
            
            if (empty($error)) {
                try {
                    $db->beginTransaction();
                    
                    // Update users table
                    $update_user_query = "UPDATE users SET 
                                         first_name = :first_name, 
                                         last_name = :last_name, 
                                         email = :email, 
                                         phone = :phone,
                                         updated_at = CURRENT_TIMESTAMP
                                         WHERE id = :user_id";
                    
                    $update_user_stmt = $db->prepare($update_user_query);
                    $update_user_stmt->bindParam(':first_name', $first_name);
                    $update_user_stmt->bindParam(':last_name', $last_name);
                    $update_user_stmt->bindParam(':email', $new_email);
                    $update_user_stmt->bindParam(':phone', $phone);
                    $update_user_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $update_user_stmt->execute();
                    
                    // Update agents table (only existing columns)
                    $update_agent_query = "UPDATE agents SET 
                                          agency_name = :agency_name,
                                          agency_email = :agency_email,
                                          years_experience = :years_experience
                                          WHERE user_id = :user_id";
                    
                    $update_agent_stmt = $db->prepare($update_agent_query);
                    $update_agent_stmt->bindParam(':agency_name', $agency_name);
                    $update_agent_stmt->bindParam(':agency_email', $agency_email);
                    $update_agent_stmt->bindParam(':years_experience', $years_experience);
                    $update_agent_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $update_agent_stmt->execute();
                    
                    $db->commit();
                    
                    // Update session variables
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $new_email;
                    
                    $success = 'Profile updated successfully!';
                    
                    // Reload agent data
                    $stmt->execute();
                    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $db->rollBack();
                    error_log("Agent profile update error: " . $e->getMessage());
                    $error = 'Database error occurred. Please try again.';
                }
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current_password, $agent['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must contain at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            try {
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
            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = 'Database error occurred.';
            }
        }
    }
}

// Parse JSON fields (only existing fields)
$specializations = !empty($agent['specializations']) ? json_decode($agent['specializations'], true) : [];

// Available options (based on actual database)
$available_specializations = [
    'residential_sales' => 'Residential Sales',
    'commercial_sales' => 'Commercial Sales',
    'rentals' => 'Rentals',
    'luxury_properties' => 'Luxury Properties',
    'investment_properties' => 'Investment Properties',
    'new_construction' => 'New Construction',
    'land_sales' => 'Land Sales',
    'property_management' => 'Property Management'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Account Settings - Omnes Real Estate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --agent-primary: #2c5aa0;
            --agent-secondary: #4a90e2;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #333333;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
        }

        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding-top: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .agent-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            padding: 1rem 0;
            color: white;
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .settings-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .settings-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
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
            width: 100%;
        }

        .form-control-custom:focus {
            border-color: var(--agent-primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
            background: var(--bg-primary);
            outline: none;
        }

        .btn-agent-primary {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-agent-primary:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.3);
        }

        .language-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .language-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .language-item:hover {
            border-color: var(--agent-primary);
        }

        .language-item input:checked + label {
            color: var(--agent-primary);
            font-weight: 600;
        }

        .rating-display {
            color: #ffc107;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-mini {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .stat-mini-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--agent-primary);
        }

        .stat-mini-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .settings-container {
                padding: 1rem;
            }
            
            .language-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="agent-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../pages/home.php" class="text-white text-decoration-none fs-4 fw-bold">
                        <i class="fas fa-building me-2"></i>Omnes Real Estate
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="settings-container">

        <!-- Header -->
        <div class="settings-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="fas fa-user-cog me-3"></i>Agent Account Settings</h1>
                    <p class="mb-0 opacity-90">Manage your professional profile and agency information</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <div class="rating-display me-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= ($agent['average_rating'] ?? 0) ? '' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                            <span class="ms-1"><?= number_format($agent['average_rating'] ?? 0, 1) ?>/5</span>
                        </div>
                        <span class="badge bg-light text-dark">Licensed Agent</span>
                    </div>
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

        <!-- Performance Stats -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-value"><?= $agent['total_transactions'] ?? 0 ?></div>
                <div class="stat-mini-label">Transactions</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value">€<?= number_format($agent['total_sales'] ?? 0, 0, ',', ' ') ?></div>
                <div class="stat-mini-label">Total Sales</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?= $agent['years_experience'] ?? 0 ?></div>
                <div class="stat-mini-label">Years Experience</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?= date('Y', strtotime($agent['created_at'] ?? '2025-01-01')) ?></div>
                <div class="stat-mini-label">Member Since</div>
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
                                               value="<?= htmlspecialchars($agent['first_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="last_name">Last Name *</label>
                                        <input type="text" class="form-control-custom" id="last_name" name="last_name" 
                                               value="<?= htmlspecialchars($agent['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="email">Email Address *</label>
                                <input type="email" class="form-control-custom" id="email" name="email"
                                       value="<?= htmlspecialchars($agent['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="phone">Phone Number</label>
                                <input type="tel" class="form-control-custom" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($agent['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="years_experience">Years of Experience</label>
                                <input type="number" class="form-control-custom" id="years_experience" name="years_experience" 
                                       value="<?= htmlspecialchars($agent['years_experience'] ?? '0') ?>" min="0" max="50">
                            </div>
                            
                            <h5 class="mt-4 mb-3">Agency Information</h5>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="agency_name">Agency Name *</label>
                                <input type="text" class="form-control-custom" id="agency_name" name="agency_name" 
                                       value="<?= htmlspecialchars($agent['agency_name'] ?? 'Independent Agent') ?>" required>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="agency_email">Agency Email</label>
                                <input type="email" class="form-control-custom" id="agency_email" name="agency_email" 
                                       value="<?= htmlspecialchars($agent['agency_email'] ?? '') ?>">
                            </div>
                            
                            <button type="submit" class="btn-agent-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
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
                        <form method="POST" action="">
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
                            
                            <button type="submit" class="btn-agent-primary">
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
                            <a href="manage_properties.php" class="btn btn-outline-success">
                                <i class="fas fa-home me-2"></i>Manage Properties
                            </a>
                            <a href="../pages/appointments.php" class="btn btn-outline-warning">
                                <i class="fas fa-calendar-alt me-2"></i>View Appointments
                            </a>
                            <a href="../pages/explore.php" class="btn btn-outline-info">
                                <i class="fas fa-search me-2"></i>Explore Properties
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Agent Profile Card -->
                <div class="settings-card">
                    <div class="card-header-custom">
                        <i class="fas fa-id-card me-2"></i>Agent Profile
                    </div>
                    <div class="card-body-custom">
                        <div class="text-center mb-3">
                            <div class="user-avatar-large mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.5rem;">
                                <?= strtoupper(substr($agent['first_name'] ?? 'A', 0, 1) . substr($agent['last_name'] ?? 'G', 0, 1)) ?>
                            </div>
                            <h5><?= htmlspecialchars(($agent['first_name'] ?? '') . ' ' . ($agent['last_name'] ?? '')) ?></h5>
                            <p class="text-muted">Licensed Real Estate Agent</p>
                            <p class="text-muted small"><?= htmlspecialchars($agent['agency_name'] ?? 'Independent Agent') ?></p>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Member since <?= date('F Y', strtotime($agent['created_at'] ?? '2025-01-01')) ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/agent_navigation.js"></script>

</body>
</html>