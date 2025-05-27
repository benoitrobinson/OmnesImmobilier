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

// Handle registration success message
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success = 'Account created successfully! Please log in with your credentials.';
}

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out. Thank you for using Omnes Immobilier.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_data['email'] = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    // Enhanced validation
    if (empty($form_data['email'])) {
        $validation_errors['email'] = 'Email address is required.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $validation_errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $validation_errors['password'] = 'Password is required.';
    }

    // Simple rate limiting check using session
    $max_attempts = 5;
    $lockout_time = 900; // 15 minutes
    $attempt_key = 'login_attempts_' . md5($form_data['email'] ?? '');
    
    if (!empty($form_data['email']) && isset($_SESSION[$attempt_key])) {
        $attempts = $_SESSION[$attempt_key];
        if ($attempts['count'] >= $max_attempts && (time() - $attempts['last_attempt']) < $lockout_time) {
            $remaining_time = $lockout_time - (time() - $attempts['last_attempt']);
            $validation_errors['general'] = 'Too many failed login attempts. Please try again in ' . ceil($remaining_time / 60) . ' minutes.';
        }
    }

    // If no validation errors and not locked out, proceed with authentication
    if (empty($validation_errors)) {
        try {
            // Use the global PDO connection from database.php
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$form_data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debug: Check if user was found
            error_log("Login attempt for: " . $form_data['email']);
            error_log("User found: " . ($user ? "Yes" : "No"));

            // Check if user exists and password is correct
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // Successful login
                session_regenerate_id(true); // Prevent session fixation
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                // Clear failed attempts
                unset($_SESSION[$attempt_key]);

                // Set remember me cookie if requested (simplified)
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', isset($_SERVER['HTTPS']), true);
                }

                // Log successful login
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('user_login', 'User logged in: ' . $user['email'] . ' (Role: ' . $user['role'] . ')');
                }

                // Update last login time (optional - you may need to add this column)
                try {
                    $update_stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                    $update_stmt->execute([$user['id']]);
                } catch (Exception $e) {
                    // Ignore if column doesn't exist
                    error_log("Could not update last login time: " . $e->getMessage());
                }

                // Set success message
                $_SESSION['success_message'] = 'Welcome back, ' . $user['first_name'] . '!';

                // Redirect based on user role - FIXED PATHS
                switch ($user['role']) {
                    case 'admin':
                        $_SESSION['success_message'] = 'Welcome back, ' . $user['first_name'] . '! You have administrator access.';
                        redirect('../client/dashboard.php'); // Temporarily redirect to client dashboard
                        break;
                    case 'agent':
                        $_SESSION['success_message'] = 'Welcome back, ' . $user['first_name'] . '! Ready to manage your properties?';
                        redirect('../client/dashboard.php'); // Temporarily redirect to client dashboard
                        break;
                    case 'client':
                    default:
                        $_SESSION['success_message'] = 'Welcome back, ' . $user['first_name'] . '! Let\'s find your perfect property.';
                        redirect('../client/dashboard.php'); // Redirect to client dashboard
                }
            } else {
                // Invalid credentials - increment failed attempts
                if (!isset($_SESSION[$attempt_key])) {
                    $_SESSION[$attempt_key] = ['count' => 0, 'last_attempt' => 0];
                }
                $_SESSION[$attempt_key]['count']++;
                $_SESSION[$attempt_key]['last_attempt'] = time();

                // Debug password verification
                if ($user) {
                    error_log("Password verification failed for user: " . $user['email']);
                    error_log("Stored hash: " . $user['password_hash']);
                } else {
                    error_log("No user found with email: " . $form_data['email']);
                }

                $error = 'Invalid email address or password. Please check your credentials and try again.';
                
                // Log failed login attempt
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('failed_login', 'Failed login attempt for: ' . $form_data['email']);
                }
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'An error occurred during login. Please try again or contact support if the problem persists.';
        }
    } else {
        $error = 'Please correct the errors below and try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Omnes Immobilier</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Auth CSS - Luxury Authentication Styles -->
    <link href="../assets/css/auth.css" rel="stylesheet">
    <!-- Main Site CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <h1>
                        <i class="fas fa-building"></i>
                        <span class="brand-accent">Omnes</span> Immobilier
                    </h1>
                    <p>Welcome back to your professional portal</p>
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
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate class="needs-validation">
                    <div class="form-floating mb-3">
                        <input type="email" 
                               class="form-control <?= isset($validation_errors['email']) ? 'is-invalid' : '' ?>" 
                               id="email" 
                               name="email"
                               placeholder="Email Address" 
                               required 
                               value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                               autocomplete="email"
                               autofocus>
                        <label for="email">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                        <?php if (isset($validation_errors['email'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($validation_errors['email']) ?>
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
                               autocomplete="current-password">
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <?php if (isset($validation_errors['password'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($validation_errors['password']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">
                                Remember me for 30 days
                            </label>
                        </div>
                        <a href="forgot-password.php" class="text-decoration-none">
                            <i class="fas fa-question-circle me-1"></i>Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg" id="submitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="submitSpinner"></span>
                        <i class="fas fa-sign-in-alt me-2" id="submitIcon"></i>
                        <span id="submitText">Sign In to Your Account</span>
                    </button>
                </form>

                <div class="auth-links mt-4">
                    <p class="mb-2">New to Omnes Immobilier?
                        <a href="register.php" class="text-decoration-none fw-semibold">
                            <i class="fas fa-user-plus me-1"></i>Create Account
                        </a>
                    </p>
                    <p class="mb-0">
                        <a href="../pages/home.php" class="text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Return to Homepage
                        </a>
                    </p>
                </div>

                <!-- Professional testimonial or trust indicator -->
                <div class="mt-4 pt-3 border-top border-light text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1 text-primary"></i>
                        Your data is protected with bank-level security
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- Auth.js - Contains all authentication JavaScript functionality -->
    <script src="../assets/js/auth.js"></script>
</body>
</html>