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

$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Initialize variables to avoid undefined variable warnings
$agent = null;
$agent_stats = [];
$agent_properties = [];
$other_agents = [];

// Check if agent exists
try {
    $check_stmt = $db->prepare("
        SELECT 
            u.*,
            a.first_name, 
            a.last_name, 
            a.phone,
            a.agency_name,
            a.cv_file_path,
            a.profile_picture_path
        FROM users u
        INNER JOIN agents a ON u.id = a.user_id
        WHERE u.id = :id AND u.role = 'agent'
    ");
    $check_stmt->execute(['id' => $agent_id]);
    $agent = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        $error = "Agent not found with ID: $agent_id";
    } else {
        // Initialize missing fields with default values (these don't exist in DB)
        $agent['rating'] = 0;
        $agent['client_satisfaction'] = 0;
        $agent['total_sales'] = 0;
        $agent['total_transactions'] = 0;
        $agent['years_experience'] = 0;
        $agent['agency_email'] = '';
        $agent['bio'] = '';
    }
    
} catch (Exception $e) {
    error_log("Agent fetch error: " . $e->getMessage());
    $error = "Error loading agent: " . $e->getMessage();
}

// Get agent stats and related data only if agent exists
if ($agent) {
    try {
        $stats_stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT p.id) as total_properties,
                COUNT(DISTINCT CASE WHEN p.status = 'available' THEN p.id END) as available_properties,
                COUNT(DISTINCT CASE WHEN p.status = 'sold' THEN p.id END) as sold_properties,
                COUNT(DISTINCT CASE WHEN p.status = 'rented' THEN p.id END) as rented_properties,
                COUNT(DISTINCT ap.id) as total_appointments,
                COUNT(DISTINCT CASE WHEN ap.status = 'scheduled' AND ap.appointment_date >= NOW() THEN ap.id END) as upcoming_appointments,
                COALESCE(SUM(CASE WHEN p.status = 'sold' THEN p.price END), 0) as sales_volume
            FROM users u
            LEFT JOIN properties p ON u.id = p.agent_id
            LEFT JOIN appointments ap ON u.id = ap.agent_id
            WHERE u.id = :agent_id
        ");
        $stats_stmt->execute(['agent_id' => $agent_id]);
        $agent_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Agent stats fetch error: " . $e->getMessage());
        $agent_stats = [];
    }
    
    // Get all properties assigned to this agent
    try {
        $properties_stmt = $db->prepare("
            SELECT p.*, 
                   CONCAT(a.first_name, ' ', a.last_name) as agent_name
            FROM properties p 
            LEFT JOIN agents a ON p.agent_id = a.user_id
            WHERE p.agent_id = :agent_id
            ORDER BY p.created_at DESC
        ");
        $properties_stmt->execute(['agent_id' => $agent_id]);
        $agent_properties = $properties_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Agent properties fetch error: " . $e->getMessage());
        $agent_properties = [];
    }
    
    // Get all agents for reassigning properties
    try {
        $agents_stmt = $db->prepare("
            SELECT u.id, CONCAT(a.first_name, ' ', a.last_name) as name
            FROM users u
            INNER JOIN agents a ON u.id = a.user_id
            WHERE u.role = 'agent' AND u.id != :current_agent_id
            ORDER BY a.first_name, a.last_name
        ");
        $agents_stmt->execute(['current_agent_id' => $agent_id]);
        $other_agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Agents fetch error: " . $e->getMessage());
        $other_agents = [];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Agent details update
    if (isset($_POST['update_agent'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $agency_name = trim($_POST['agency_name'] ?? '');
        $agency_email = trim($_POST['agency_email'] ?? '');
        $years_experience = (int)($_POST['years_experience'] ?? 0);
        $bio = trim($_POST['bio'] ?? '');
        $rating = (float)($_POST['rating'] ?? 0);
        $client_satisfaction = (int)($_POST['client_satisfaction'] ?? 0);
        $total_sales = (float)($_POST['total_sales'] ?? 0);
        $total_transactions = (int)($_POST['total_transactions'] ?? 0);
        
        // Basic validation
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db->beginTransaction();
                
                // Update user data
                $update_user_stmt = $db->prepare("
                    UPDATE users SET
                        email = :email,
                        updated_at = NOW()
                    WHERE id = :agent_id
                ");
                
                $update_user_stmt->execute([
                    'email' => $email,
                    'agent_id' => $agent_id
                ]);
                
                // Update only the fields that exist in the agents table
                $update_agent_stmt = $db->prepare("
                    UPDATE agents SET
                        first_name = :first_name,
                        last_name = :last_name,
                        phone = :phone,
                        agency_name = :agency_name
                    WHERE user_id = :agent_id
                ");
                
                $update_agent_stmt->execute([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => $phone,
                    'agency_name' => $agency_name,
                    'agent_id' => $agent_id
                ]);
                
                $db->commit();
                $success = 'Agent information updated successfully.';
                
                // Reload agent data
                $check_stmt->execute(['id' => $agent_id]);
                $agent = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Re-initialize the missing fields
                $agent['rating'] = $rating;
                $agent['client_satisfaction'] = $client_satisfaction;
                $agent['total_sales'] = $total_sales;
                $agent['total_transactions'] = $total_transactions;
                $agent['years_experience'] = $years_experience;
                $agent['agency_email'] = $agency_email;
                $agent['bio'] = $bio;
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Agent update error: " . $e->getMessage());
                $error = 'Error updating agent: ' . $e->getMessage();
            }
        }
    }
    
    // Reassign property
    elseif (isset($_POST['reassign_property'])) {
        $property_id = (int)($_POST['property_id'] ?? 0);
        $new_agent_id = (int)($_POST['new_agent_id'] ?? 0);
        
        if ($property_id <= 0 || $new_agent_id <= 0) {
            $error = 'Invalid property or agent selection.';
        } else {
            try {
                // Update property with new agent
                $reassign_stmt = $db->prepare("UPDATE properties SET agent_id = :new_agent_id WHERE id = :property_id");
                $reassign_stmt->execute([
                    'new_agent_id' => $new_agent_id,
                    'property_id' => $property_id
                ]);
                
                $success = 'Property reassigned successfully.';
                
                // Reload properties and stats
                if (isset($properties_stmt)) {
                    $properties_stmt->execute(['agent_id' => $agent_id]);
                    $agent_properties = $properties_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                if (isset($stats_stmt)) {
                    $stats_stmt->execute(['agent_id' => $agent_id]);
                    $agent_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log("Property reassign error: " . $e->getMessage());
                $error = 'Error reassigning property: ' . $e->getMessage();
            }
        }
    }
}

// Set current page for navigation highlighting
$current_page = 'agents';

// Include the admin header
include '../includes/admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Agent - Admin Panel</title>
    
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
                    <a href="dashboard.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-1"></i>Dashboard
                    </a>
                    <a href="manage_agents.php" class="btn btn-light">
                        <i class="fas fa-list me-1"></i>All Agents
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
                    <i class="fas fa-user-edit me-2"></i>Edit Agent
                </h1>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">Edit agent information and manage their property assignments.</p>
            </div>
        </div>

        <!-- Messages -->
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

        <!-- Form -->
        <?php if ($agent): ?>
            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="content-card">
                    <div class="card-body p-4">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h5 class="form-section-title">
                                <i class="fas fa-info-circle me-2"></i>Basic Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">First Name *</label>
                                        <input type="text" class="form-control form-control-custom" name="first_name" 
                                               value="<?= htmlspecialchars($agent['first_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Last Name *</label>
                                        <input type="text" class="form-control form-control-custom" name="last_name" 
                                               value="<?= htmlspecialchars($agent['last_name']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Email *</label>
                                        <input type="email" class="form-control form-control-custom" name="email" 
                                               value="<?= htmlspecialchars($agent['email']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Phone Number</label>
                                        <input type="text" class="form-control form-control-custom" name="phone" 
                                               value="<?= htmlspecialchars($agent['phone']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Agency Name</label>
                                <input type="text" class="form-control form-control-custom" name="agency_name" 
                                       value="<?= htmlspecialchars($agent['agency_name']) ?>">
                            </div>
                        </div>

                        <!-- Additional Information (Display Only) -->
                        <div class="form-section">
                            <h5 class="form-section-title">
                                <i class="fas fa-info-circle me-2"></i>Additional Information
                            </h5>
                            <p class="text-muted">These fields are for display purposes and don't save to the database yet.</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Agency Email</label>
                                        <input type="email" class="form-control form-control-custom" name="agency_email" 
                                               value="<?= htmlspecialchars($agent['agency_email']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Years of Experience</label>
                                        <input type="number" class="form-control form-control-custom" name="years_experience" 
                                               min="0" max="50" value="<?= $agent['years_experience'] ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Bio</label>
                                <textarea class="form-control form-control-custom" name="bio" rows="4"><?= htmlspecialchars($agent['bio']) ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Rating (0-5)</label>
                                        <input type="number" class="form-control form-control-custom" name="rating" 
                                               min="0" max="5" step="0.1" value="<?= $agent['rating'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Client Satisfaction (%)</label>
                                        <input type="number" class="form-control form-control-custom" name="client_satisfaction" 
                                               min="0" max="100" value="<?= $agent['client_satisfaction'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Total Sales (€)</label>
                                        <input type="number" class="form-control form-control-custom" name="total_sales" 
                                               min="0" step="1000" value="<?= $agent['total_sales'] ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Total Transactions</label>
                                        <input type="number" class="form-control form-control-custom" name="total_transactions" 
                                               min="0" value="<?= $agent['total_transactions'] ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex gap-3 justify-content-end">
                            <a href="manage_agents.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" name="update_agent" class="btn-admin-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Property Assignment Section -->
            <?php if (!empty($agent_properties)): ?>
            <div class="content-card mt-4">
                <div class="content-card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-home me-2"></i>Assigned Properties (<?= count($agent_properties) ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Property</th>
                                    <th>Location</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agent_properties as $property): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <?php
                                                    $img_path = "../assets/images/property{$property['id']}-2.jpg";
                                                    $img_exists = file_exists($img_path);
                                                    ?>
                                                    <?php if ($img_exists): ?>
                                                        <img src="<?= $img_path ?>" alt="Property" 
                                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                                    <?php else: ?>
                                                        <div class="bg-light d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px; border-radius: 8px;">
                                                            <i class="fas fa-home text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($property['title']) ?></h6>
                                                    <small class="text-muted"><?= ucfirst($property['property_type']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($property['city']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($property['address_line1']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>€<?= number_format($property['price'], 0, ',', ' ') ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'available' => 'success',
                                                'sold' => 'primary',
                                                'rented' => 'warning',
                                                'pending' => 'info',
                                                'withdrawn' => 'danger'
                                            ];
                                            $color = $status_colors[$property['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= ucfirst($property['status']) ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#reassignModal" 
                                                        onclick="setReassignProperty(<?= $property['id'] ?>, '<?= htmlspecialchars($property['title']) ?>')">
                                                    <i class="fas fa-exchange-alt me-1"></i>Reassign
                                                </button>
                                                <a href="edit_property.php?id=<?= $property['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Reassign Property Modal -->
            <div class="modal fade" id="reassignModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reassign Property</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body">
                                <p>Reassign "<strong id="propertyTitle"></strong>" to another agent:</p>
                                
                                <input type="hidden" name="property_id" id="propertyId">
                                
                                <div class="mb-3">
                                    <label class="form-label">Select New Agent</label>
                                    <select class="form-control" name="new_agent_id" required>
                                        <option value="">Choose an agent...</option>
                                        <?php foreach ($other_agents as $other_agent): ?>
                                            <option value="<?= $other_agent['id'] ?>">
                                                <?= htmlspecialchars($other_agent['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="reassign_property" class="btn btn-primary">Reassign Property</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>No agent ID specified or agent not found.
                <div class="mt-3">
                    <a href="manage_agents.php" class="btn btn-dark">
                        <i class="fas fa-list me-2"></i>View All Agents
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function setReassignProperty(propertyId, propertyTitle) {
            document.getElementById('propertyId').value = propertyId;
            document.getElementById('propertyTitle').textContent = propertyTitle;
        }
    </script>
</body>
</html>
