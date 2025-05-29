<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            redirect('../admin/dashboard.php');
            break;
        case 'agent':
            redirect('../agent/dashboard.php');
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
    
    // Agent-specific fields
    $agent_data = [];
    if ($form_data['role'] === 'agent') {
        $agent_data = [
            'license_number' => trim($_POST['license_number'] ?? ''),
            'years_experience' => (int)($_POST['years_experience'] ?? 0),
            'bio' => trim($_POST['bio'] ?? ''),
            'agency_name' => trim($_POST['agency_name'] ?? ''),
            'agency_address' => trim($_POST['agency_address'] ?? ''),
            'agency_phone' => trim($_POST['agency_phone'] ?? ''),
            'agency_email' => trim(strtolower($_POST['agency_email'] ?? '')),
            'languages_spoken' => $_POST['languages_spoken'] ?? []
        ];
    }
    
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

    // Agent-specific validation
    if ($form_data['role'] === 'agent') {
        if (empty($agent_data['agency_name'])) {
            $validation_errors['agency_name'] = 'Agency name is required for agents.';
        }
        
        if (!empty($agent_data['agency_email']) && !filter_var($agent_data['agency_email'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors['agency_email'] = 'Please enter a valid agency email address.';
        }
        
        if (!empty($agent_data['agency_phone']) && !preg_match('/^[+]?[0-9\s\-\(\)\.]{10,}$/', $agent_data['agency_phone'])) {
            $validation_errors['agency_phone'] = 'Please enter a valid agency phone number.';
        }
        
        if ($agent_data['years_experience'] < 0 || $agent_data['years_experience'] > 50) {
            $validation_errors['years_experience'] = 'Years of experience must be between 0 and 50.';
        }
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

                // Insert into users table
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

                // Role-specific table insertions
                if ($form_data['role'] === 'agent') {
                    // Insert into agents table with actual form data
                    $agent_stmt = $pdo->prepare("INSERT INTO agents (
                        user_id, cv_file_path, profile_picture_path, agency_name, agency_address, 
                        agency_phone, agency_email, license_number, languages_spoken, 
                        years_experience, commission_rate, bio, average_rating, total_sales, total_transactions
                    ) VALUES (
                        :user_id, :cv_file_path, :profile_picture_path, :agency_name, :agency_address,
                        :agency_phone, :agency_email, :license_number, :languages_spoken,
                        :years_experience, :commission_rate, :bio, :average_rating, :total_sales, :total_transactions
                    )");
                    
                    $agent_stmt->execute([
                        'user_id' => $user_id,
                        'cv_file_path' => '',
                        'profile_picture_path' => '',
                        'agency_name' => $agent_data['agency_name'] ?: 'Independent Agent',
                        'agency_address' => $agent_data['agency_address'],
                        'agency_phone' => $agent_data['agency_phone'] ?: $form_data['phone'],
                        'agency_email' => $agent_data['agency_email'] ?: $form_data['email'],
                        'license_number' => $agent_data['license_number'],
                        'languages_spoken' => json_encode($agent_data['languages_spoken']),
                        'years_experience' => $agent_data['years_experience'],
                        'commission_rate' => 3.00, // Default commission rate
                        'bio' => $agent_data['bio'],
                        'average_rating' => 0.00,
                        'total_sales' => 0.00,
                        'total_transactions' => 0
                    ]);
                } elseif ($form_data['role'] === 'client') {
                    // Insert into clients table
                    $client_stmt = $pdo->prepare("INSERT INTO clients (user_id, address_line1, address_line2, city, state, postal_code, country, financial_info) 
                                                  VALUES (:user_id, :address_line1, :address_line2, :city, :state, :postal_code, :country, :financial_info)");
                    $client_stmt->execute([
                        'user_id' => $user_id,
                        'address_line1' => '',
                        'address_line2' => '',
                        'city' => '',
                        'state' => '',
                        'postal_code' => '',
                        'country' => 'France',
                        'financial_info' => '{}'
                    ]);
                }

                $pdo->commit();

                // Log security event
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('user_registered', 'New user registered: ' . $form_data['email'] . ' (Role: ' . $form_data['role'] . ')');
                }

                // AUTOMATICALLY LOG IN THE USER AFTER REGISTRATION
                session_regenerate_id(true);
                
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
                        redirect('../agent/dashboard.php');
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

// Available languages for agents
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
                <!-- Basic Information -->
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

                <!-- Account Type Selection -->
                <div class="form-floating mb-3">
                    <select class="form-select <?= isset($validation_errors['role']) ? 'is-invalid' : '' ?>" 
                            id="role" 
                            name="role" 
                            required
                            onchange="toggleAgentFields()">
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

                <!-- Agent-Specific Fields (Hidden by default) -->
                <div id="agentFields" class="d-none">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Agent Information</strong> - Please provide your professional details
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       class="form-control <?= isset($validation_errors['license_number']) ? 'is-invalid' : '' ?>" 
                                       id="license_number" 
                                       name="license_number"
                                       placeholder="License Number"
                                       value="<?= htmlspecialchars($agent_data['license_number'] ?? '') ?>">
                                <label for="license_number">
                                    <i class="fas fa-id-card me-2"></i>License Number
                                </label>
                                <?php if (isset($validation_errors['license_number'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($validation_errors['license_number']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="number" 
                                       class="form-control <?= isset($validation_errors['years_experience']) ? 'is-invalid' : '' ?>" 
                                       id="years_experience" 
                                       name="years_experience"
                                       placeholder="Years of Experience"
                                       min="0" max="50"
                                       value="<?= htmlspecialchars($agent_data['years_experience'] ?? '0') ?>">
                                <label for="years_experience">
                                    <i class="fas fa-briefcase me-2"></i>Years of Experience
                                </label>
                                <?php if (isset($validation_errors['years_experience'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($validation_errors['years_experience']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" 
                               class="form-control <?= isset($validation_errors['agency_name']) ? 'is-invalid' : '' ?>" 
                               id="agency_name" 
                               name="agency_name"
                               placeholder="Agency Name"
                               value="<?= htmlspecialchars($agent_data['agency_name'] ?? '') ?>">
                        <label for="agency_name">
                            <i class="fas fa-building me-2"></i>Agency Name *
                        </label>
                        <?php if (isset($validation_errors['agency_name'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($validation_errors['agency_name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-floating mb-3">
                        <textarea class="form-control <?= isset($validation_errors['agency_address']) ? 'is-invalid' : '' ?>" 
                                  id="agency_address" 
                                  name="agency_address"
                                  placeholder="Agency Address"
                                  style="height: 100px"><?= htmlspecialchars($agent_data['agency_address'] ?? '') ?></textarea>
                        <label for="agency_address">
                            <i class="fas fa-map-marker-alt me-2"></i>Agency Address
                        </label>
                        <?php if (isset($validation_errors['agency_address'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($validation_errors['agency_address']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="tel" 
                                       class="form-control <?= isset($validation_errors['agency_phone']) ? 'is-invalid' : '' ?>" 
                                       id="agency_phone" 
                                       name="agency_phone"
                                       placeholder="Agency Phone"
                                       value="<?= htmlspecialchars($agent_data['agency_phone'] ?? '') ?>">
                                <label for="agency_phone">
                                    <i class="fas fa-phone me-2"></i>Agency Phone
                                </label>
                                <?php if (isset($validation_errors['agency_phone'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($validation_errors['agency_phone']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="email" 
                                       class="form-control <?= isset($validation_errors['agency_email']) ? 'is-invalid' : '' ?>" 
                                       id="agency_email" 
                                       name="agency_email"
                                       placeholder="Agency Email"
                                       value="<?= htmlspecialchars($agent_data['agency_email'] ?? '') ?>">
                                <label for="agency_email">
                                    <i class="fas fa-envelope me-2"></i>Agency Email
                                </label>
                                <?php if (isset($validation_errors['agency_email'])): ?>
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($validation_errors['agency_email']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <textarea class="form-control <?= isset($validation_errors['bio']) ? 'is-invalid' : '' ?>" 
                                  id="bio" 
                                  name="bio"
                                  placeholder="Professional Bio"
                                  style="height: 120px"><?= htmlspecialchars($agent_data['bio'] ?? '') ?></textarea>
                        <label for="bio">
                            <i class="fas fa-user-edit me-2"></i>Professional Bio
                        </label>
                        <div class="form-text">Tell clients about your experience and expertise</div>
                        <?php if (isset($validation_errors['bio'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($validation_errors['bio']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Languages Spoken -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-language me-2"></i>Languages Spoken
                        </label>
                        <div class="row">
                            <?php foreach ($available_languages as $key => $label): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="lang_<?= $key ?>" name="languages_spoken[]" value="<?= $key ?>"
                                               <?= in_array($key, $agent_data['languages_spoken'] ?? []) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="lang_<?= $key ?>">
                                            <?= $label ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Password Fields -->
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

<script>
// Toggle agent fields based on role selection
function toggleAgentFields() {
    const roleSelect = document.getElementById('role');
    const agentFields = document.getElementById('agentFields');
    const submitText = document.getElementById('submitText');
    
    if (roleSelect.value === 'agent') {
        agentFields.classList.remove('d-none');
        submitText.textContent = 'Create Agent Account';
        
        // Make agency name required for agents
        document.getElementById('agency_name').required = true;
    } else {
        agentFields.classList.add('d-none');
        submitText.textContent = 'Create Professional Account';
        
        // Remove required from agency fields
        document.getElementById('agency_name').required = false;
    }
}

// Initialize the form state on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleAgentFields();
    
    // Set up form validation
    const form = document.getElementById('registerForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    const strengthBar = document.getElementById('strengthBar');
    
    if (password.length > 0) {
        strengthDiv.classList.remove('d-none');
        
        let strength = 0;
        let strengthLabel = '';
        
        // Check password criteria
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z\d]/.test(password)) strength++;
        
        // Set strength level
        switch (strength) {
            case 0:
            case 1:
                strengthLabel = 'Very Weak';
                strengthBar.className = 'progress-bar bg-danger';
                strengthBar.style.width = '20%';
                break;
            case 2:
                strengthLabel = 'Weak';
                strengthBar.className = 'progress-bar bg-warning';
                strengthBar.style.width = '40%';
                break;
            case 3:
                strengthLabel = 'Fair';
                strengthBar.className = 'progress-bar bg-info';
                strengthBar.style.width = '60%';
                break;
            case 4:
                strengthLabel = 'Good';
                strengthBar.className = 'progress-bar bg-primary';
                strengthBar.style.width = '80%';
                break;
            case 5:
                strengthLabel = 'Strong';
                strengthBar.className = 'progress-bar bg-success';
                strengthBar.style.width = '100%';
                break;
        }
        
        strengthText.textContent = `Password strength: ${strengthLabel}`;
    } else {
        strengthDiv.classList.add('d-none');
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/auth_template.php';
renderAuthPage('Create Account - Omnes Immobilier', $content, 'Join our exclusive real estate community and access premium properties and professional services.');
?>