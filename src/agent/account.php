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

// Get complete agent information
$query = "SELECT u.*, a.cv_file_path, a.profile_picture_path, a.agency_name, a.agency_address,
                 a.agency_phone, a.agency_email, a.license_number, a.specializations, 
                 a.languages_spoken, a.years_experience, a.commission_rate, a.bio,
                 a.average_rating, a.total_sales, a.total_transactions
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
        
        // Agent-specific information
        $bio = trim($_POST['bio'] ?? '');
        $years_experience = (int)($_POST['years_experience'] ?? 0);
        $license_number = trim($_POST['license_number'] ?? '');
        $specializations = $_POST['specializations'] ?? [];
        $languages_spoken = $_POST['languages_spoken'] ?? [];
        
        // Agency information
        $agency_name = trim($_POST['agency_name'] ?? '');
        $agency_address = trim($_POST['agency_address'] ?? '');
        $agency_phone = trim($_POST['agency_phone'] ?? '');
        $agency_email = trim($_POST['agency_email'] ?? '');
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!filter_var($agency_email, FILTER_VALIDATE_EMAIL)) {
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
                    
                    // Update agents table
                    $update_agent_query = "UPDATE agents SET 
                                          bio = :bio,
                                          years_experience = :years_experience,
                                          license_number = :license_number,
                                          specializations = :specializations,
                                          languages_spoken = :languages_spoken,
                                          agency_name = :agency_name,
                                          agency_address = :agency_address,
                                          agency_phone = :agency_phone,
                                          agency_email = :agency_email
                                          WHERE user_id = :user_id";
                    
                    $update_agent_stmt = $db->prepare($update_agent_query);
                    $update_agent_stmt->bindParam(':bio', $bio);
                    $update_agent_stmt->bindParam(':years_experience', $years_experience);
                    $update_agent_stmt->bindParam(':license_number', $license_number);
                    $update_agent_stmt->bindParam(':specializations', json_encode($specializations));
                    $update_agent_stmt->bindParam(':languages_spoken', json_encode($languages_spoken));
                    $update_agent_stmt->bindParam(':agency_name', $agency_name);
                    $update_agent_stmt->bindParam(':agency_address', $agency_address);
                    $update_agent_stmt->bindParam(':agency_phone', $agency_phone);
                    $update_agent_stmt->bindParam(':agency_email', $agency_email);
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

// Parse JSON fields
$specializations = !empty($agent['specializations']) ? json_decode($agent['specializations'], true) : [];
$languages_spoken = !empty($agent['languages_spoken']) ? json_decode($agent['languages_spoken'], true) : [];

// Available options
$available_specializations = [
    'residential' => 'Residential Properties',
    'commercial' => 'Commercial Properties', 
    'luxury' => 'Luxury Properties',
    'rental' => 'Rental Properties',
    'land' => 'Land & Development',
    'investment' => 'Investment Properties',
    'new_construction' => 'New Construction',
    'relocation' => 'Relocation Services'
];

$available_languages = [
    'french' => 'French',
    'english' => 'English',
    'spanish' => 'Spanish',
    'italian' => 'Italian',
    'german' => 'German',
    'arabic' => 'Arabic',
    'chinese' => 'Chinese',
    'portuguese' => 'Portuguese'
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

        .specialization-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .specialization-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .specialization-item:hover {
            border-color: var(--agent-primary);
        }

        .specialization-item input:checked + label {
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
            
            .specialization-grid {
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-3">
                <li class="breadcrumb-item"><a href="../pages/home.php"><i class="fas fa-home me-1"></i>Agent Portal</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Account Settings</li>
            </ol>
        </nav>

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
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="phone">Phone Number</label>
                                        <input type="tel" class="form-control-custom" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($agent['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="license_number">License Number</label>
                                        <input type="text" class="form-control-custom" id="license_number" name="license_number" 
                                               value="<?= htmlspecialchars($agent['license_number'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="years_experience">Years of Experience</label>
                                <input type="number" class="form-control-custom" id="years_experience" name="years_experience" 
                                       value="<?= htmlspecialchars($agent['years_experience'] ?? '0') ?>" min="0" max="50">
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="bio">Professional Bio</label>
                                <textarea class="form-control-custom" id="bio" name="bio" rows="4" 
                                          placeholder="Tell clients about your experience and expertise..."><?= htmlspecialchars($agent['bio'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Specializations -->
                            <div class="form-group-custom">
                                <label class="form-label-custom">Specializations</label>
                                <div class="specialization-grid">
                                    <?php foreach ($available_specializations as $key => $label): ?>
                                        <div class="specialization-item">
                                            <input type="checkbox" class="form-check-input me-2" 
                                                   id="spec_<?= $key ?>" name="specializations[]" value="<?= $key ?>"
                                                   <?= in_array($key, $specializations) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_<?= $key ?>"><?= $label ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Languages -->
                            <div class="form-group-custom">
                                <label class="form-label-custom">Languages Spoken</label>
                                <div class="specialization-grid">
                                    <?php foreach ($available_languages as $key => $label): ?>
                                        <div class="specialization-item">
                                            <input type="checkbox" class="form-check-input me-2" 
                                                   id="lang_<?= $key ?>" name="languages_spoken[]" value="<?= $key ?>"
                                                   <?= in_array($key, $languages_spoken) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="lang_<?= $key ?>"><?= $label ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <h5 class="mt-4 mb-3">Agency Information</h5>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="agency_name">Agency Name *</label>
                                <input type="text" class="form-control-custom" id="agency_name" name="agency_name" 
                                       value="<?= htmlspecialchars($agent['agency_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group-custom">
                                <label class="form-label-custom" for="agency_address">Agency Address</label>
                                <textarea class="form-control-custom" id="agency_address" name="agency_address" rows="2"><?= htmlspecialchars($agent['agency_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="agency_phone">Agency Phone</label>
                                        <input type="tel" class="form-control-custom" id="agency_phone" name="agency_phone" 
                                               value="<?= htmlspecialchars($agent['agency_phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label class="form-label-custom" for="agency_email">Agency Email</label>
                                        <input type="email" class="form-control-custom" id="agency_email" name="agency_email" 
                                               value="<?= htmlspecialchars($agent['agency_email'] ?? '') ?>">
                                    </div>
                                </div>
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
                            <a href="properties.php" class="btn btn-outline-success">
                                <i class="fas fa-home me-2"></i>Manage Properties
                            </a>
                            <a href="appointments.php" class="btn btn-outline-warning">
                                <i class="fas fa-calendar-alt me-2"></i>View Appointments
                            </a>
                            <a href="messages.php" class="btn btn-outline-info">
                                <i class="fas fa-envelope me-2"></i>Messages
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
                            
                            <?php if ($agent['license_number']): ?>
                                <p class="small text-muted">License: <?= htmlspecialchars($agent['license_number']) ?></p>
                            <?php endif; ?>
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
</body>
</html>