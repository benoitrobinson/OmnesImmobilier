<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            redirect('../client/dashboard.php'); // Temporarily redirect to client dashboard
            break;
        case 'agent':
            redirect('../client/dashboard.php'); // Temporarily redirect to client dashboard
            break;
        case 'client':
        default:
            redirect('../client/dashboard.php');
    }
}

$error = '';
$success = '';
$form_data = [];
$validation_errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and sanitize form input
    $form_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim(strtolower($_POST['email'] ?? '')),
        'phone' => trim($_POST['phone'] ?? ''),
        'role' => $_POST['role'] ?? 'client'
    ];
    
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Enhanced validation
    if (empty($form_data['first_name'])) {
        $validation_errors['first_name'] = 'First name is required.';
    } elseif (strlen($form_data['first_name']) < 2) {
        $validation_errors['first_name'] = 'First name must be at least 2 characters.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/', $form_data['first_name'])) {
        $validation_errors['first_name'] = 'First name contains invalid characters.';
    }

    if (empty($form_data['last_name'])) {
        $validation_errors['last_name'] = 'Last name is required.';
    } elseif (strlen($form_data['last_name']) < 2) {
        $validation_errors['last_name'] = 'Last name must be at least 2 characters.';
    } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/', $form_data['last_name'])) {
        $validation_errors['last_name'] = 'Last name contains invalid characters.';
    }

    if (empty($form_data['email'])) {
        $validation_errors['email'] = 'Email address is required.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = 'Please enter a valid email address.';
    }

    if (!empty($form_data['phone']) && !preg_match('/^[+]?[0-9\s\-\(\)\.]{10,}$/', $form_data['phone'])) {
        $validation_errors['phone'] = 'Please enter a valid phone number.';
    }

    if (empty($password)) {
        $validation_errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $validation_errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $validation_errors['password'] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }

    if (empty($password_confirm)) {
        $validation_errors['password_confirm'] = 'Please confirm your password.';
    } elseif ($password !== $password_confirm) {
        $validation_errors['password_confirm'] = 'Passwords do not match.';
    }

    if (!in_array($form_data['role'], ['client', 'agent'])) {
        $validation_errors['role'] = 'Please select a valid account type.';
    }

    if (!isset($_POST['terms'])) {
        $validation_errors['terms'] = 'You must accept the Terms of Use and Privacy Policy.';
    }

    // If no validation errors, proceed with registration
    if (empty($validation_errors)) {
        try {
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $form_data['email']]);

            if ($stmt->rowCount() > 0) {
                $validation_errors['email'] = 'This email address is already registered. Please use a different email or try logging in.';
            } else {
                $pdo->beginTransaction();

                // Hash the password with enhanced security
                $password_hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

                // Insert into users table (matches your database structure)
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, phone, role, created_at) 
                                       VALUES (:first_name, :last_name, :email, :password_hash, :phone, :role, NOW())");
                $stmt->execute([
                    'first_name' => $form_data['first_name'],
                    'last_name' => $form_data['last_name'],
                    'email' => $form_data['email'],
                    'password_hash' => $password_hash,
                    'phone' => $form_data['phone'],
                    'role' => $form_data['role']
                ]);

                $user_id = $pdo->lastInsertId();

                // Role-specific table insertions (FIXED to match your database structure)
                if ($form_data['role'] === 'agent') {
                    // Insert into agents table with columns that exist in your database
                    $agent_stmt = $pdo->prepare("INSERT INTO agents (user_id, cv_file_path, profile_picture_path, agency_name, agency_address, agency_phone, agency_email) 
                                                 VALUES (:user_id, :cv_file_path, :profile_picture_path, :agency_name, :agency_address, :agency_phone, :agency_email)");
                    $agent_stmt->execute([
                        'user_id' => $user_id,
                        'cv_file_path' => '', // Empty for now
                        'profile_picture_path' => '', // Empty for now
                        'agency_name' => 'Independent Agent',
                        'agency_address' => 'To be specified',
                        'agency_phone' => $form_data['phone'],
                        'agency_email' => $form_data['email']
                    ]);
                } elseif ($form_data['role'] === 'client') {
                    // Insert into clients table with columns that exist in your database
                    $client_stmt = $pdo->prepare("INSERT INTO clients (user_id, address_line1, address_line2, city, state, postal_code, country, financial_info) 
                                                  VALUES (:user_id, :address_line1, :address_line2, :city, :state, :postal_code, :country, :financial_info)");
                    $client_stmt->execute([
                        'user_id' => $user_id,
                        'address_line1' => '', // Empty for now
                        'address_line2' => '', // Empty for now
                        'city' => '',          // Empty for now
                        'state' => '',         // Empty for now
                        'postal_code' => '',   // Empty for now
                        'country' => 'France', // Default country
                        'financial_info' => '{}' // Empty JSON object
                    ]);
                }

                $pdo->commit();

                // Log security event
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('user_registered', 'New user registered: ' . $form_data['email'] . ' (Role: ' . $form_data['role'] . ')');
                }

                // AUTOMATICALLY LOG IN THE USER AFTER REGISTRATION
                session_regenerate_id(true); // Prevent session fixation
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['first_name'] = $form_data['first_name'];
                $_SESSION['last_name'] = $form_data['last_name'];
                $_SESSION['email'] = $form_data['email'];
                $_SESSION['role'] = $form_data['role'];
                $_SESSION['login_time'] = time();

                // Set welcome message
                $_SESSION['success_message'] = 'Welcome to Omnes Immobilier, ' . $form_data['first_name'] . '! Your account has been created successfully and you are now logged in.';

                // Log successful registration and auto-login
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('user_auto_login', 'User auto-logged in after registration: ' . $form_data['email']);
                }

                // Redirect based on user role to their dashboard
                switch ($form_data['role']) {
                    case 'agent':
                        $_SESSION['success_message'] = 'Welcome to Omnes Immobilier, ' . $form_data['first_name'] . '! Your agent account has been created successfully. You can now manage properties and connect with clients.';
                        redirect('../client/dashboard.php'); // Temporarily redirect to client dashboard
                        break;
                    case 'client':
                    default:
                        $_SESSION['success_message'] = 'Welcome to Omnes Immobilier, ' . $form_data['first_name'] . '! Your account has been created successfully. Start exploring our exclusive properties.';
                        redirect('../client/dashboard.php');
                        break;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Registration error: ' . $e->getMessage());
            $error = 'We apologize, but there was an error creating your account. Please try again or contact support if the problem persists.';
        }
    } else {
        $error = 'Please correct the errors below and try again.';
    }
}

ob_start();
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <h1>
                    <i class="fas fa-building"></i>
                    <span class="brand-accent">Omnes</span> Immobilier
                </h1>
                <p>Join our exclusive real estate community</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <div class="mt-3">
                        <a href="../client/dashboard.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-chart-pie me-1"></i>Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>

            <form method="POST" action="" id="registerForm" novalidate class="needs-validation">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" 
                                   class="form-control <?= isset($validation_errors['first_name']) ? 'is-invalid' : '' ?>" 
                                   id="first_name" 
                                   name="first_name"
                                   placeholder="First Name" 
                                   required 
                                   value="<?= htmlspecialchars($form_data['first_name'] ?? '') ?>"
                                   autocomplete="given-name">
                            <label for="first_name">
                                <i class="fas fa-user me-2"></i>First Name *
                            </label>
                            <?php if (isset($validation_errors['first_name'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($validation_errors['first_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" 
                                   class="form-control <?= isset($validation_errors['last_name']) ? 'is-invalid' : '' ?>" 
                                   id="last_name" 
                                   name="last_name"
                                   placeholder="Last Name" 
                                   required 
                                   value="<?= htmlspecialchars($form_data['last_name'] ?? '') ?>"
                                   autocomplete="family-name">
                            <label for="last_name">
                                <i class="fas fa-user me-2"></i>Last Name *
                            </label>
                            <?php if (isset($validation_errors['last_name'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($validation_errors['last_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" 
                           class="form-control <?= isset($validation_errors['email']) ? 'is-invalid' : '' ?>" 
                           id="email" 
                           name="email"
                           placeholder="Email Address" 
                           required 
                           value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                           autocomplete="email">
                    <label for="email">
                        <i class="fas fa-envelope me-2"></i>Email Address *
                    </label>
                    <?php if (isset($validation_errors['email'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($validation_errors['email']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="tel" 
                           class="form-control <?= isset($validation_errors['phone']) ? 'is-invalid' : '' ?>" 
                           id="phone" 
                           name="phone"
                           placeholder="Phone Number" 
                           value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>"
                           autocomplete="tel">
                    <label for="phone">
                        <i class="fas fa-phone me-2"></i>Phone Number
                    </label>
                    <?php if (isset($validation_errors['phone'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($validation_errors['phone']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <select class="form-select <?= isset($validation_errors['role']) ? 'is-invalid' : '' ?>" 
                            id="role" 
                            name="role" 
                            required>
                        <option value="" disabled <?= empty($form_data['role']) ? 'selected' : '' ?>>Choose your account type</option>
                        <option value="client" <?= ($form_data['role'] ?? 'client') === 'client' ? 'selected' : '' ?>>
                            Client – I'm looking for properties
                        </option>
                        <option value="agent" <?= ($form_data['role'] ?? '') === 'agent' ? 'selected' : '' ?>>
                            Agent – I represent properties
                        </option>
                    </select>
                    <label for="role">
                        <i class="fas fa-user-tag me-2"></i>Account Type *
                    </label>
                    <?php if (isset($validation_errors['role'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($validation_errors['role']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" 
                           class="form-control <?= isset($validation_errors['password']) ? 'is-invalid' : '' ?>" 
                           id="password" 
                           name="password"
                           placeholder="Password" 
                           required
                           autocomplete="new-password">
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Password *
                    </label>
                    <div id="passwordStrength" class="password-strength d-none">
                        <div class="d-flex align-items-center mt-2">
                            <span id="strengthText" class="small">Password strength: </span>
                            <div class="progress ms-2 flex-fill" style="height: 4px;">
                                <div id="strengthBar" class="progress-bar" role="progressbar"></div>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($validation_errors['password'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($validation_errors['password']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" 
                           class="form-control <?= isset($validation_errors['password_confirm']) ? 'is-invalid' : '' ?>" 
                           id="password_confirm" 
                           name="password_confirm"
                           placeholder="Confirm Password" 
                           required
                           autocomplete="new-password">
                    <label for="password_confirm">
                        <i class="fas fa-lock me-2"></i>Confirm Password *
                    </label>
                    <?php if (isset($validation_errors['password_confirm'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($validation_errors['password_confirm']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input <?= isset($validation_errors['terms']) ? 'is-invalid' : '' ?>" 
                           type="checkbox" 
                           id="terms" 
                           name="terms" 
                           required>
                    <label class="form-check-label" for="terms">
                        I acknowledge that I have read and agree to the 
                        <a href="../pages/cgu.php" target="_blank" class="text-decoration-none">Terms of Use</a>
                        and 
                        <a href="../pages/politique-confidentialite.php" target="_blank" class="text-decoration-none">Privacy Policy</a>
                    </label>
                    <?php if (isset($validation_errors['terms'])): ?>
                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars($validation_errors['terms']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg" id="submitBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                    <i class="fas fa-user-plus me-2" id="submitIcon"></i>
                    <span id="submitText">Create Professional Account</span>
                </button>
            </form>

            <?php endif; ?>

            <div class="auth-links mt-4">
                <p class="mb-2">Already have an account?
                    <a href="login.php" class="text-decoration-none fw-semibold">
                        <i class="fas fa-sign-in-alt me-1"></i>Sign In
                    </a>
                </p>
                <p class="mb-0">
                    <a href="../pages/home.php" class="text-muted text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Return to Homepage
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/auth_template.php';
renderAuthPage('Create Account - Omnes Immobilier', $content, 'Join our exclusive real estate community and access premium properties and professional services.');
?>