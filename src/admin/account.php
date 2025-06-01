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

// Get current user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id AND role = 'admin'");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        redirect('../auth/login.php');
    }
} catch (Exception $e) {
    error_log("Error fetching admin data: " . $e->getMessage());
    $error = "An error occurred while retrieving your account information.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Profile update logic
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email exists and belongs to another user
                if ($email !== $user['email']) {
                    $check_stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
                    $check_stmt->execute([
                        'email' => $email,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        $error = 'This email address is already registered to another account.';
                    }
                }
                
                // If no error, update profile
                if (empty($error)) {
                    $update_stmt = $db->prepare("
                        UPDATE users 
                        SET first_name = :first_name, 
                            last_name = :last_name, 
                            email = :email, 
                            phone = :phone,
                            updated_at = NOW()
                        WHERE id = :user_id
                    ");
                    
                    $update_stmt->execute([
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => $phone,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    
                    $success = 'Profile updated successfully.';
                    
                    // Update session data
                    $_SESSION['name'] = $first_name . ' ' . $last_name;
                    $_SESSION['email'] = $email;
                    
                    // Refresh user data
                    $stmt->execute(['user_id' => $_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'Error updating profile. Please try again.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change logic
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } else {
            try {
                // Hash new password and update
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $db->prepare("
                    UPDATE users 
                    SET password_hash = :password_hash,
                        updated_at = NOW()
                    WHERE id = :user_id
                ");
                
                $update_stmt->execute([
                    'password_hash' => $password_hash,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $success = 'Password changed successfully.';
            } catch (Exception $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = 'Error changing password. Please try again.';
            }
        }
    } elseif (isset($_POST['upload_profile_picture'])) {
        // Profile picture upload logic
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file_info = $_FILES['profile_picture'];
            $file_name = $file_info['name'];
            $file_tmp = $file_info['tmp_name'];
            $file_size = $file_info['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file extension and size
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file_ext, $allowed_exts)) {
                $error = 'Only JPG, JPEG, PNG, and GIF files are allowed for profile pictures.';
            } elseif ($file_size > $max_size) {
                $error = 'Profile picture file size must not exceed 2MB.';
            } else {
                // Create directory if it doesn't exist
                $upload_dir = 'uploads/profile_pictures/';
                $full_upload_dir = '../' . $upload_dir;
                
                if (!is_dir($full_upload_dir)) {
                    mkdir($full_upload_dir, 0755, true);
                }
                
                // Generate safe filename
                $safe_filename = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
                $profile_pic_path = $upload_dir . $safe_filename;
                $full_path = '../' . $profile_pic_path;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $full_path)) {
                    // Update database with new profile picture path
                    $update_stmt = $db->prepare("
                        UPDATE users 
                        SET profile_picture_path = :profile_picture_path,
                            updated_at = NOW()
                        WHERE id = :user_id
                    ");
                    
                    $update_stmt->execute([
                        'profile_picture_path' => $profile_pic_path,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    
                    $success = 'Profile picture updated successfully.';
                    
                    // Refresh user data
                    $stmt->execute(['user_id' => $_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to upload profile picture. Please try again.';
                }
            }
        } else {
            $error = 'Please select a profile picture to upload.';
        }
    }
}

// Set current page for navigation highlighting
$current_page = 'account';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Settings - Omnes Real Estate Admin</title>
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Header Styles */
    .admin-header {
      background-color: #000;
      padding: 15px 0;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      color: white;
    }
    
    .brand-logo {
      display: flex;
      align-items: center;
      font-size: 1.25rem;
      font-weight: bold;
      color: white;
      text-decoration: none;
    }
    
    .brand-logo:hover {
      color: rgba(255, 255, 255, 0.8);
    }
    
    .brand-logo img {
      height: 32px;
      margin-right: 10px;
    }
    
    /* Navigation */
    .admin-nav {
      background-color: #000;
      color: white;
      padding: 10px 0;
    }
    
    .nav-link {
      color: #ccc;
      padding: 8px 16px;
      border-radius: 4px;
      transition: all 0.3s ease;
    }
    
    .nav-link:hover, .nav-link.active {
      color: white;
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    .nav-link i {
      width: 20px;
      text-align: center;
      margin-right: 8px;
    }
    
    /* Main Content */
    .main-content {
      background-color: #f8f9fa;
      padding: 20px 0;
      min-height: calc(100vh - 60px);
    }
    
    .card {
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      margin-bottom: 20px;
      border: none;
    }
    
    .card-header {
      background-color: #fff;
      border-bottom: 1px solid #eee;
      padding: 15px 20px;
    }
    
    .card-body {
      padding: 20px;
    }
    
    /* Profile Picture */
    .profile-img {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      border: 4px solid #fff;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
    
    .profile-img-placeholder {
      width: 150px;
      height: 150px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #f8f9fa;
      border-radius: 50%;
      color: #adb5bd;
      font-size: 3rem;
      margin: 0 auto 20px;
    }
    
    /* Password Requirements */
    .password-requirements {
      background: #f8f9fa;
      border-radius: 4px;
      padding: 12px;
      font-size: 0.875rem;
      margin-top: 10px;
    }
    
    .requirement {
      color: #6c757d;
      margin-bottom: 5px;
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
  <!-- Main Header -->
  <header class="admin-header">
    <div class="container">
      <div class="row align-items-center">
        <div class="col">
          <a href="dashboard.php" class="brand-logo">
            <img src="../assets/images/logo.png" alt="Omnes Immobilier" onerror="this.src='../assets/images/logo-placeholder.png'; this.onerror='';">
            OMNES IMMOBILIER
          </a>
        </div>
        <div class="col text-end">
          <div class="dropdown">
            <button class="btn btn-dark dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
              <?php if (!empty($user['profile_picture_path'])): ?>
                <img src="../<?= htmlspecialchars($user['profile_picture_path']) ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
              <?php else: ?>
                <i class="fas fa-user-circle me-2" style="font-size: 32px;"></i>
              <?php endif; ?>
              <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="account.php"><i class="fas fa-user-cog me-2"></i>Account Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Navigation -->
  <nav class="admin-nav">
    <div class="container">
      <ul class="nav">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'clients' ? 'active' : '' ?>" href="manage_clients.php">
            <i class="fas fa-users"></i> Clients
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'agents' ? 'active' : '' ?>" href="manage_agents.php">
            <i class="fas fa-user-tie"></i> Agents
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'properties' ? 'active' : '' ?>" href="manage_properties.php">
            <i class="fas fa-home"></i> Properties
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'appointments' ? 'active' : '' ?>" href="manage_appointments.php">
            <i class="fas fa-calendar-alt"></i> Appointments
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>" href="reports.php">
            <i class="fas fa-chart-bar"></i> Analytics & Reports
          </a>
        </li>
        <li class="nav-item ms-auto">
          <a class="nav-link <?= $current_page === 'account' ? 'active' : '' ?>" href="account.php">
            <i class="fas fa-user-cog"></i> Account
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Main Content -->
  <section class="main-content">
    <div class="container">
      <!-- Page Title -->
      <div class="row mb-4">
        <div class="col">
          <h2 class="h4 mb-0">
            <i class="fas fa-user-cog me-2"></i>Account Settings
          </h2>
          <p class="text-muted mb-0">Manage your account information and preferences</p>
        </div>
      </div>
      
      <!-- Alerts -->
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      
      <div class="row">
        <!-- Profile Picture Column -->
        <div class="col-lg-4 mb-4">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Profile Picture</h5>
            </div>
            <div class="card-body text-center">
              <?php if (!empty($user['profile_picture_path'])): ?>
                <img src="../<?= htmlspecialchars($user['profile_picture_path']) ?>" alt="Profile Picture" class="profile-img mb-3">
              <?php else: ?>
                <div class="profile-img-placeholder">
                  <i class="fas fa-user"></i>
                </div>
              <?php endif; ?>
              
              <h5 class="mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
              <p class="text-muted">Administrator</p>
              
              <hr>
              
              <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                  <label for="profile_picture" class="form-label">Update Profile Picture</label>
                  <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                  <small class="form-text text-muted">JPG, PNG or GIF, Max 2MB</small>
                </div>
                <button type="submit" name="upload_profile_picture" class="btn btn-dark">Upload Picture</button>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Account Details Column -->
        <div class="col-lg-8">
          <!-- Personal Information -->
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Personal Information</h5>
            </div>
            <div class="card-body">
              <form method="post">
                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                  </div>
                </div>
                
                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                  </div>
                </div>
                
                <div class="text-end">
                  <button type="submit" name="update_profile" class="btn btn-dark">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
          
          <!-- Change Password -->
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
              <form method="post">
                <div class="mb-3">
                  <label for="current_password" class="form-label">Current Password</label>
                  <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                
                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                  </div>
                  <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                  </div>
                </div>
                
                <div class="password-requirements mb-3">
                  <div class="requirement" id="length">At least 8 characters</div>
                  <div class="requirement" id="uppercase">At least one uppercase letter</div>
                  <div class="requirement" id="lowercase">At least one lowercase letter</div>
                  <div class="requirement" id="number">At least one number</div>
                </div>
                
                <div class="text-end">
                  <button type="submit" name="change_password" class="btn btn-dark">Update Password</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Password validation
    const passwordInput = document.getElementById('new_password');
    const requirements = {
      length: { regex: /.{8,}/, element: document.getElementById('length') },
      uppercase: { regex: /[A-Z]/, element: document.getElementById('uppercase') },
      lowercase: { regex: /[a-z]/, element: document.getElementById('lowercase') },
      number: { regex: /[0-9]/, element: document.getElementById('number') }
    };
    
    if (passwordInput) {
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
    }
    
    // Confirm password validation
    const confirmInput = document.getElementById('confirm_password');
    if (confirmInput && passwordInput) {
      confirmInput.addEventListener('input', function() {
        if (this.value === passwordInput.value) {
          this.setCustomValidity('');
        } else {
          this.setCustomValidity('Passwords do not match');
        }
      });
    }
  </script>
</body>
</html>
