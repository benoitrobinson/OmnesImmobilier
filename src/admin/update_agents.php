<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    redirect('../auth/login.php');
}

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Execute the update if confirmed
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        $db->beginTransaction();
        
        // Get agents who need name updates
        $query = "SELECT a.user_id, u.first_name, u.last_name, u.phone, u.email
                 FROM agents a 
                 JOIN users u ON a.user_id = u.id
                 WHERE (a.first_name IS NULL OR a.first_name = '' 
                     OR a.last_name IS NULL OR a.last_name = ''
                     OR a.phone IS NULL OR a.phone = '')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        
        // Update each agent
        foreach ($agents as $agent) {
            $update = "UPDATE agents 
                      SET first_name = :first_name, 
                          last_name = :last_name,
                          phone = :phone
                      WHERE user_id = :user_id";
            $updateStmt = $db->prepare($update);
            $updateStmt->execute([
                'first_name' => $agent['first_name'],
                'last_name' => $agent['last_name'],
                'phone' => $agent['phone'],
                'user_id' => $agent['user_id']
            ]);
            $updated++;
        }
        
        $db->commit();
        $message = "Successfully updated names and phone numbers for $updated agent(s).";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error updating agents: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Agent Names - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 2rem;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
        }
        .card-header {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
            border-radius: 0.5rem 0.5rem 0 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-sync-alt me-2"></i> Update Agent Names
            </div>
            <div class="card-body">
                <p class="mb-3">
                    This utility will copy the first name and last name from the users table to the agents table 
                    for all existing agents who don't have this information.
                </p>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="manage_agents.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Return to Manage Agents
                        </a>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="confirm" value="yes">
                        <div class="d-flex justify-content-between">
                            <a href="manage_agents.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-sync-alt me-2"></i>Try Again
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="confirm" value="yes">
                        <div class="d-flex justify-content-between">
                            <a href="manage_agents.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>Update Agent Names
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
