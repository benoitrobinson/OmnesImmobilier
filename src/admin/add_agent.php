<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$database = Database::getInstance();
$db = $database->getConnection();

$success = '';
$error = '';
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'agency_name' => 'Omnes Immobilier',
    'agency_email' => '',
    'years_experience' => 0,
    'bio' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $form_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'agency_name' => trim($_POST['agency_name'] ?? ''),
        'agency_email' => trim($_POST['agency_email'] ?? ''),
        'years_experience' => (int)($_POST['years_experience'] ?? 0),
        'bio' => trim($_POST['bio'] ?? '')
    ];
    
    // Handle CV upload
    $cv_file_path = '';
    if (isset($_FILES['agent_cv']) && $_FILES['agent_cv']['error'] === UPLOAD_ERR_OK) {
        $file_info = $_FILES['agent_cv'];
        $file_name = $file_info['name'];
        $file_tmp = $file_info['tmp_name'];
        $file_size = $file_info['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file extension and size
        $allowed_exts = ['pdf', 'doc', 'docx'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_ext, $allowed_exts)) {
            $error = 'Only PDF, DOC, and DOCX files are allowed for CV.';
        } elseif ($file_size > $max_size) {
            $error = 'CV file size must not exceed 5MB.';
        } else {
            // Create directory if it doesn't exist
            $upload_dir = 'uploads/agent_cvs/';
            $full_upload_dir = '../' . $upload_dir;
            
            if (!is_dir($full_upload_dir)) {
                mkdir($full_upload_dir, 0755, true);
            }
            
            // Generate safe filename
            $safe_filename = 'cv_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $cv_file_path = $upload_dir . $safe_filename;
        }
    }
    
    // Validation
    if (empty($form_data['first_name']) || empty($form_data['last_name'])) {
        $error = 'First name and last name are required.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!empty($form_data['agency_email']) && !filter_var($form_data['agency_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid agency email address.';
    } elseif (strlen($form_data['password']) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            // Check if email already exists
            $check_stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $check_stmt->execute(['email' => $form_data['email']]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'This email address is already registered.';
            } else {
                $db->beginTransaction();
                
                // Hash password
                $password_hash = password_hash($form_data['password'], PASSWORD_DEFAULT);
                
                // Insert into users table
                $user_stmt = $db->prepare("
                    INSERT INTO users (first_name, last_name, email, password_hash, phone, role, created_at)
                    VALUES (:first_name, :last_name, :email, :password_hash, :phone, 'agent', NOW())
                ");
                $user_stmt->execute([
                    'first_name' => $form_data['first_name'],  // Stored HERE in users table
                    'last_name' => $form_data['last_name'],    // Stored HERE in users table
                    'email' => $form_data['email'],
                    'password_hash' => $password_hash,
                    'phone' => $form_data['phone']
                ]);
                
                $user_id = $db->lastInsertId();
                
                // Move CV file if uploaded
                if (!empty($cv_file_path) && isset($_FILES['agent_cv']) && $_FILES['agent_cv']['error'] === UPLOAD_ERR_OK) {
                    if (!move_uploaded_file($_FILES['agent_cv']['tmp_name'], '../' . $cv_file_path)) {
                        // Failed to move file
                        $cv_file_path = '';
                        error_log('Failed to move uploaded CV file');
                    }
                }
                
                // Insert into agents table with CV file path
                $agent_stmt = $db->prepare("
                    INSERT INTO agents (
                        user_id, cv_file_path, profile_picture_path, agency_name, agency_email, 
                        years_experience, first_name, last_name
                    ) VALUES (
                        :user_id, :cv_file_path, '', :agency_name, :agency_email, 
                        :years_experience, :first_name, :last_name
                    )
                ");
                
                $agent_stmt->execute([
                    'user_id' => $user_id,
                    'cv_file_path' => $cv_file_path,
                    'agency_name' => $form_data['agency_name'],
                    'agency_email' => $form_data['agency_email'] ?: $form_data['email'],
                    'years_experience' => $form_data['years_experience'],
                    'first_name' => $form_data['first_name'],  // Added to save in agents table
                    'last_name' => $form_data['last_name']     // Added to save in agents table
                ]);
                
                $db->commit();
                
                $_SESSION['success_message'] = 'Agent created successfully!';
                redirect('manage_agents.php');
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Add agent error: " . $e->getMessage());
            $error = 'Error creating agent: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Agent - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: #dc3545;
            --admin-secondary: #c82333;
            --admin-dark: #721c24;
            --admin-light: #f8d7da;
            --admin-bg: #f8f9fa;
        }

        body {
            background: var(--admin-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .admin-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
        }

        .brand-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none !important;
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--admin-primary);
            margin-bottom: 1rem;
        }

        .form-control-custom {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
            background: white;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            border-color: var(--admin-primary);
        }

        .checkbox-item input:checked + label {
            color: var(--admin-primary);
            font-weight: 600;
        }

        .btn-admin-primary {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-admin-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
            color: white;
        }

        .password-requirements {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .requirement {
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.valid::before {
            content: '✓ ';
        }

        .requirement:not(.valid)::before {
            content: '× ';
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="../pages/home.php" class="brand-logo">
                        <i class="fas fa-building me-2"></i>Omnes Real Estate - Admin
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <a href="manage_agents.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Agents
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <h1 class="h4 mb-0">
                    <i class="fas fa-user-plus me-2"></i>Add New Agent
                </h1>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">Create a new agent account with full access to property management.</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" class="needs-validation" enctype="multipart/form-data" novalidate>
            <div class="content-card">
                <div class="card-body p-4">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-user me-2"></i>Personal Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">First Name *</label>
                                    <input type="text" class="form-control form-control-custom" name="first_name" 
                                           value="<?= htmlspecialchars($form_data['first_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Last Name *</label>
                                    <input type="text" class="form-control form-control-custom" name="last_name" 
                                           value="<?= htmlspecialchars($form_data['last_name']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Address *</label>
                                    <input type="email" class="form-control form-control-custom" name="email" 
                                           value="<?= htmlspecialchars($form_data['email']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Phone Number *</label>
                                    <input type="tel" class="form-control form-control-custom" name="phone" 
                                           value="<?= htmlspecialchars($form_data['phone']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Password *</label>
                                    <input type="password" class="form-control form-control-custom" name="password" 
                                           id="password" required>
                                    <div class="password-requirements">
                                        <div class="requirement" id="length">At least 8 characters</div>
                                        <div class="requirement" id="uppercase">One uppercase letter</div>
                                        <div class="requirement" id="lowercase">One lowercase letter</div>
                                        <div class="requirement" id="number">One number</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">CV/Resume</label>
                                    <input type="file" class="form-control form-control-custom" name="agent_cv" 
                                           accept=".pdf,.doc,.docx">
                                    <small class="text-muted">Upload CV/resume (PDF, DOC, DOCX, max 5MB)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agency Information -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-building me-2"></i>Agency Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Agency Name *</label>
                                    <input type="text" class="form-control form-control-custom" name="agency_name" 
                                           value="<?= htmlspecialchars($form_data['agency_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Agency Email</label>
                                    <input type="email" class="form-control form-control-custom" name="agency_email" 
                                           value="<?= htmlspecialchars($form_data['agency_email']) ?>">
                                    <small class="text-muted">If left blank, agent's personal email will be used</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Details -->
                    <div class="form-section">
                        <h5 class="form-section-title">
                            <i class="fas fa-briefcase me-2"></i>Professional Details
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Years of Experience</label>
                            <input type="number" class="form-control form-control-custom" name="years_experience" 
                                   value="<?= htmlspecialchars($form_data['years_experience']) ?>" min="0" max="50">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Professional Bio</label>
                            <textarea class="form-control form-control-custom" name="bio" rows="4" 
                                      placeholder="Brief description of agent's experience and expertise..."><?= htmlspecialchars($form_data['bio']) ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="manage_agents.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn-admin-primary">
                            <i class="fas fa-save me-2"></i>Create Agent Account
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password validation
        const passwordInput = document.getElementById('password');
        const requirements = {
            length: { regex: /.{8,}/, element: document.getElementById('length') },
            uppercase: { regex: /[A-Z]/, element: document.getElementById('uppercase') },
            lowercase: { regex: /[a-z]/, element: document.getElementById('lowercase') },
            number: { regex: /[0-9]/, element: document.getElementById('number') }
        };
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            for (const [key, requirement] of Object.entries(requirements)) {
                if (requirement.regex.test(password)) {
                    requirement.element.classList.add('valid');
                } else {
                    requirement.element.classList.remove('valid');
                }
            }
        });

        // Form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>