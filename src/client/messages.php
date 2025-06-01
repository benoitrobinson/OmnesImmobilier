<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || !isClient()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$message_id = $_GET['id'] ?? null;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Handle client replies
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'send_new_message') {
        $property_id = (int)($_POST['property_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $message_content = trim($_POST['message'] ?? '');
        
        if ($property_id <= 0) {
            $error = 'Please select a property.';
        } elseif (empty($subject)) {
            $error = 'Subject is required.';
        } elseif (empty($message_content)) {
            $error = 'Message content is required.';
        } else {
            try {
                // Get property and agent details
                $property_query = "SELECT p.*, p.agent_id, CONCAT(u.first_name, ' ', u.last_name) as agent_name 
                                 FROM properties p 
                                 LEFT JOIN users u ON p.agent_id = u.id 
                                 WHERE p.id = ? AND p.status = 'available'";
                $property_stmt = $pdo->prepare($property_query);
                $property_stmt->execute([$property_id]);
                $property = $property_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($property && $property['agent_id']) {
                    // Send message to agent
                    $query = "INSERT INTO messages (sender_id, receiver_id, property_id, message, subject, sent_at, is_read) 
                             VALUES (?, ?, ?, ?, ?, NOW(), 0)";
                    $stmt = $pdo->prepare($query);
                    
                    if ($stmt->execute([$_SESSION['user_id'], $property['agent_id'], $property_id, $message_content, $subject])) {
                        $success = 'Message sent successfully to ' . htmlspecialchars($property['agent_name']) . '!';

                        // Clear the form fields after success
                        $_POST['property_id'] = 0;
                        $_POST['subject'] = '';
                        $_POST['message'] = '';
                    } else {
                        $error = 'Error sending message.';
                    }
                } else {
                    $error = 'Property not found or no agent assigned.';
                }
            } catch (PDOException $e) {
                error_log("New message error: " . $e->getMessage());
                $error = 'Error sending message.';
            }
        }
    }
    
    elseif ($form_action === 'reply_to_agent') {
        $original_message_id = (int)($_POST['original_message_id'] ?? 0);
        $message_content = trim($_POST['message'] ?? '');
        
        if (empty($message_content)) {
            $error = 'Reply content is required.';
        } else {
            try {
                // Get original message details
                $original_query = "SELECT sender_id, subject, property_id FROM messages WHERE id = ? AND receiver_id = ?";
                $original_stmt = $pdo->prepare($original_query);
                $original_stmt->execute([$original_message_id, $_SESSION['user_id']]);
                $original = $original_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($original) {
                    $reply_subject = strpos($original['subject'], 'Re: ') === 0 ? $original['subject'] : 'Re: ' . $original['subject'];
                    
                    // Insert client's reply
                    $query = "INSERT INTO messages (sender_id, receiver_id, property_id, message, subject, reply_to_id, sent_at, is_read) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)";
                    $stmt = $pdo->prepare($query);
                    
                    $reply_property_id = ($original['property_id'] && $original['property_id'] > 0) ? $original['property_id'] : null;
                    
                    if ($stmt->execute([$_SESSION['user_id'], $original['sender_id'], $reply_property_id, $message_content, $reply_subject, $original_message_id])) {
                        $success = 'Reply sent successfully!';
                        
                        // Mark original message as read
                        $mark_read = "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?";
                        $read_stmt = $pdo->prepare($mark_read);
                        $read_stmt->execute([$original_message_id, $_SESSION['user_id']]);
                    } else {
                        $error = 'Error sending reply.';
                    }
                } else {
                    $error = 'Original message not found.';
                }
            } catch (PDOException $e) {
                error_log("Client reply error: " . $e->getMessage());
                $error = 'Error sending reply.';
            }
        }
    }
}

// Get client's messages with proper column names and null handling
$messages = [];
$conversations = [];
try {
    $base_query = "SELECT m.*, 
                     CONCAT(COALESCE(agent.first_name, ''), ' ', COALESCE(agent.last_name, '')) as agent_name,
                     COALESCE(agent.email, '') as agent_email,
                     COALESCE(agent.phone, '') as agent_phone,
                     COALESCE(p.title, '') as property_title,
                     COALESCE(p.price, 0) as property_price,
                     COALESCE(p.city, '') as property_city,
                     CASE 
                        WHEN m.sender_id = :client_id THEN 'sent'
                        ELSE 'received'
                     END as message_type
              FROM messages m 
              LEFT JOIN users agent ON (CASE WHEN m.sender_id = :client_id THEN m.receiver_id ELSE m.sender_id END) = agent.id
              LEFT JOIN properties p ON m.property_id = p.id
              WHERE m.sender_id = :client_id OR m.receiver_id = :client_id";
    
    // Apply filters
    $params = ['client_id' => $_SESSION['user_id']];
    
    if ($filter === 'unread' && ($action === 'list' || $action === 'inbox')) {
        $base_query .= " AND m.receiver_id = :client_id AND m.is_read = 0";
    } elseif ($filter === 'property' && ($action === 'list' || $action === 'inbox')) {
        $property_id = $_GET['property_id'] ?? null;
        if ($property_id) {
            $base_query .= " AND m.property_id = :property_id";
            $params['property_id'] = $property_id;
        }
    }
    
    if (!empty($search)) {
        $base_query .= " AND (m.subject LIKE :search OR m.message LIKE :search OR 
                             CONCAT(agent.first_name, ' ', agent.last_name) LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    $base_query .= " ORDER BY m.sent_at DESC";
    
    $stmt = $pdo->prepare($base_query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For inbox view, group messages into conversations
    if ($action === 'list' || $action === 'inbox') {
        $conversations_temp = [];
        
        foreach ($messages as $message) {
            // Determine conversation partner
            $partner_id = ($message['sender_id'] == $_SESSION['user_id']) ? $message['receiver_id'] : $message['sender_id'];
            $conversation_key = $partner_id . '_' . ($message['property_id'] ?? 'general');
            
            if (!isset($conversations_temp[$conversation_key])) {
                $conversations_temp[$conversation_key] = [
                    'partner_id' => $partner_id,
                    'partner_name' => $message['agent_name'],
                    'property_id' => $message['property_id'],
                    'property_title' => $message['property_title'],
                    'latest_message' => $message,
                    'unread_count' => 0,
                    'message_count' => 0
                ];
            }
            
            // Update conversation stats
            $conversations_temp[$conversation_key]['message_count']++;
            
            // Count unread messages where client is receiver
            if ($message['receiver_id'] == $_SESSION['user_id'] && $message['is_read'] == 0) {
                $conversations_temp[$conversation_key]['unread_count']++;
            }
            
            // Keep the latest message
            if (strtotime($message['sent_at']) > strtotime($conversations_temp[$conversation_key]['latest_message']['sent_at'])) {
                $conversations_temp[$conversation_key]['latest_message'] = $message;
            }
        }
        
        // For unread filter, only show conversations with unread messages
        if ($filter === 'unread') {
            $conversations_temp = array_filter($conversations_temp, function($conv) {
                return $conv['unread_count'] > 0;
            });
        }
        
        // Convert to indexed array and sort by latest message
        $conversations = array_values($conversations_temp);
        usort($conversations, function($a, $b) {
            return strtotime($b['latest_message']['sent_at']) - strtotime($a['latest_message']['sent_at']);
        });
    }
    
} catch (Exception $e) {
    error_log("Client messages fetch error: " . $e->getMessage());
    $messages = [];
    $conversations = [];
}

// Get conversation messages for view
$conversation_messages = [];
if ($action === 'conversation') {
    $partner_id = $_GET['partner'] ?? null;
    $property_id = $_GET['property'] ?? null;
    
    if ($partner_id) {
        try {
            $conv_query = "SELECT m.*, 
                          CONCAT(COALESCE(agent.first_name, ''), ' ', COALESCE(agent.last_name, '')) as agent_name,
                          COALESCE(agent.email, '') as agent_email,
                          COALESCE(agent.phone, '') as agent_phone,
                          COALESCE(p.title, '') as property_title
                   FROM messages m 
                   LEFT JOIN users agent ON agent.id = ?
                   LEFT JOIN properties p ON m.property_id = p.id
                   WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))";
            
            $conv_params = [$partner_id, $_SESSION['user_id'], $partner_id, $partner_id, $_SESSION['user_id']];
            
            if ($property_id) {
                $conv_query .= " AND m.property_id = ?";
                $conv_params[] = $property_id;
            }
            
            $conv_query .= " ORDER BY m.sent_at ASC";
            
            $conv_stmt = $pdo->prepare($conv_query);
            $conv_stmt->execute($conv_params);
            $conversation_messages = $conv_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark unread messages as read
            $mark_read_query = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
            if ($property_id) {
                $mark_read_query .= " AND property_id = ?";
                $mark_read_params = [$_SESSION['user_id'], $partner_id, $property_id];
            } else {
                $mark_read_params = [$_SESSION['user_id'], $partner_id];
            }
            
            $mark_stmt = $pdo->prepare($mark_read_query);
            $mark_stmt->execute($mark_read_params);
            
        } catch (Exception $e) {
            error_log("Conversation fetch error: " . $e->getMessage());
            $conversation_messages = [];
        }
    }
}

// Get client's properties for property filter (properties they've inquired about)
$client_properties = [];
try {
    $prop_query = "SELECT DISTINCT p.id, p.title, p.address_line1 
                   FROM properties p
                   INNER JOIN messages m ON p.id = m.property_id
                   WHERE m.sender_id = ? OR m.receiver_id = ?
                   ORDER BY p.title";
    $prop_stmt = $pdo->prepare($prop_query);
    $prop_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $client_properties = $prop_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Properties fetch error: " . $e->getMessage());
    $client_properties = [];
}

// Get available properties for new message form
$available_properties = [];
try {
    $prop_query = "SELECT p.id, p.title, p.address_line1, p.city, p.price, 
                          CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                          u.email as agent_email
                   FROM properties p
                   LEFT JOIN users u ON p.agent_id = u.id
                   WHERE p.status = 'available' AND p.agent_id IS NOT NULL
                   ORDER BY p.title";
    $prop_stmt = $pdo->prepare($prop_query);
    $prop_stmt->execute();
    $available_properties = $prop_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Available properties fetch error: " . $e->getMessage());
    $available_properties = [];
}

// Calculate message statistics
$message_stats = [
    'total_received' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $_SESSION['user_id'])),
    'unread' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $_SESSION['user_id'] && $m['is_read'] == 0)),
    'sent' => count(array_filter($messages, fn($m) => $m['sender_id'] == $_SESSION['user_id'])),
    'today' => count(array_filter($messages, fn($m) => date('Y-m-d', strtotime($m['sent_at'])) == date('Y-m-d'))),
    'unread_messages' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $_SESSION['user_id'] && $m['is_read'] == 0))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Client Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --client-primary: #2e8b57;
            --client-secondary: #3cb371;
            --client-success: #28a745;
            --client-warning: #ffc107;
            --client-danger: #dc3545;
            --client-info: #17a2b8;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-top: 100px; /* Add space for fixed navbar */
        }

        /* Navigation Styles */
        .navbar {
            background: #212529 !important;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white !important;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            height: 40px;
            width: auto;
            margin-right: 0.5rem;
        }

        .navbar-brand:hover {
            color: var(--client-primary) !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,.8) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,.1);
        }

        .dropdown-menu {
            background: white;
            border: none;
            box-shadow: 0 10px 25px rgba(0,0,0,.15);
            border-radius: 0.75rem;
            padding: 0.5rem;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--client-primary);
            color: white;
        }

        .dropdown-divider {
            margin: 0.5rem 0;
            border-color: #e9ecef;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--client-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.5rem;
        }

        .main-content {
            padding-top: 1rem; /* Reduce top padding since body already has padding */
        }

        .page-header {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stats-value.primary { color: var(--client-primary); }
        .stats-value.success { color: var(--client-success); }
        .stats-value.warning { color: var(--client-warning); }
        .stats-value.danger { color: var(--client-danger); }

        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .content-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }

        .content-card-body {
            padding: 2rem;
        }

        .messages-sidebar {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            position: sticky;
            top: 2rem;
        }

        .sidebar-nav {
            padding: 0;
        }

        .sidebar-nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
        }

        .sidebar-nav-item:hover {
            background: #f8f9fa;
            color: var(--client-primary);
            border-left: 4px solid var(--client-primary);
            padding-left: calc(1.5rem - 4px);
        }

        .sidebar-nav-item.active {
            background: #f8f9fa;
            color: var(--client-primary);
            font-weight: 600;
            border-left: 4px solid var(--client-primary);
            padding-left: calc(1.5rem - 4px);
        }

        .sidebar-nav-item i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .conversation-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .conversation-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .conversation-item.has-unread {
            border-left: 4px solid var(--client-warning);
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(253, 126, 20, 0.05) 100%);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--client-primary) 0%, var(--client-secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .conversation-preview {
            flex-grow: 1;
            margin-left: 1rem;
        }

        .conversation-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .conversation-last-message {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .conversation-time {
            color: #adb5bd;
            font-size: 0.75rem;
        }

        .unread-badge {
            background: var(--client-warning);
            color: white;
            border-radius: 50px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
        }

        .message-thread {
            max-height: 600px;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .message-bubble {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
            max-width: 80%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .message-bubble.sent {
            background: linear-gradient(135deg, var(--client-primary) 0%, var(--client-secondary) 100%);
            color: white;
            margin-left: auto;
        }

        .message-bubble.received {
            background: white;
            margin-right: auto;
            border: 1px solid #e9ecef;
        }

        .property-tag {
            background: linear-gradient(135deg, var(--client-success) 0%, #20c997 100%);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .filter-tabs {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-tabs .nav-pills .nav-link {
            border-radius: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-tabs .nav-pills .nav-link.active {
            background: var(--client-primary);
        }

        .search-box {
            position: relative;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
        }

        .search-box .fa-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .compose-form {
            background: #f8f9fa;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-control-custom {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--client-primary);
            box-shadow: 0 0 0 0.25rem rgba(46, 139, 87, 0.15);
        }

        .btn-client-primary {
            background: linear-gradient(135deg, var(--client-primary) 0%, var(--client-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-client-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.4);
            color: white;
        }

        @media (max-width: 768px) {
            .messages-sidebar {
                position: relative;
                margin-bottom: 2rem;
            }
            
            .message-thread {
                max-height: 400px;
            }
            
            .message-bubble {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../pages/home.php">
                <img src="../assets/images/logo1.png" alt="OmnesImmobilier Logo">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <?= strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($_SESSION['first_name'] ?? 'User') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-chart-pie me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="../pages/explore.php"><i class="fas fa-search me-2"></i>Search Properties</a></li>
                            <li><a class="dropdown-item" href="messages.php"><i class="fas fa-envelope me-2"></i>Messages</a></li>
                            <li><a class="dropdown-item" href="dashboard.php?section=appointments"><i class="fas fa-calendar-alt me-2"></i>Appointments</a></li>
                            <li><a class="dropdown-item" href="account.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-envelope me-3"></i>
                        <?php
                        switch ($action) {
                            case 'sent':
                                echo 'Sent Messages';
                                break;
                            case 'conversation':
                                echo 'Conversation';
                                break;
                            default:
                                echo 'Messages Inbox';
                        }
                        ?>
                    </h1>
                    <p class="mb-0 opacity-90">
                        Communicate with agents about property inquiries
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-chart-pie me-2"></i>Dashboard
                    </a>
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

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value primary"><?= $message_stats['total_received'] ?></div>
                    <div class="stats-label">Total Received</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value warning"><?= $message_stats['unread'] ?></div>
                    <div class="stats-label">Unread</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value success"><?= $message_stats['sent'] ?></div>
                    <div class="stats-label">Sent</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value primary"><?= $message_stats['today'] ?></div>
                    <div class="stats-label">Today</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="messages-sidebar">
                    <nav class="sidebar-nav">
                        <a href="?action=list" class="sidebar-nav-item <?= ($action === 'list' || $action === 'inbox') ? 'active' : '' ?>">
                            <i class="fas fa-inbox"></i>
                            <span>Inbox</span>
                            <?php if ($message_stats['unread_messages'] > 0): ?>
                                <span class="unread-badge ms-auto"><?= $message_stats['unread_messages'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?action=sent" class="sidebar-nav-item <?= $action === 'sent' ? 'active' : '' ?>">
                            <i class="fas fa-paper-plane"></i>
                            <span>Sent</span>
                        </a>
                    </nav>

                    <!-- Quick Actions -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem;">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                <i class="fas fa-plus me-1"></i>New Message
                            </button>
                            <a href="../pages/explore.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-search me-1"></i>Search Properties
                            </a>
                            <a href="dashboard.php?section=appointments" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-calendar-alt me-1"></i>Appointments
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-chart-pie me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if ($action === 'conversation'): ?>
                    <!-- Conversation Thread View -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-comments me-2"></i>
                                    Conversation with <?= htmlspecialchars($conversation_messages[0]['agent_name'] ?? 'Agent') ?>
                                    <?php if (!empty($conversation_messages[0]['property_title'])): ?>
                                        <small class="text-muted">about <?= htmlspecialchars($conversation_messages[0]['property_title']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <a href="messages.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Inbox
                                </a>
                            </div>
                        </div>
                        <div class="content-card-body">
                            <!-- Message Thread -->
                            <div class="message-thread">
                                <?php foreach ($conversation_messages as $msg): ?>
                                    <div class="message-bubble <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received' ?>">
                                        <?php if ($msg['property_title'] && $msg === reset($conversation_messages)): ?>
                                            <div class="property-tag">
                                                <i class="fas fa-home me-1"></i><?= htmlspecialchars($msg['property_title']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($msg['subject'] && $msg === reset($conversation_messages)): ?>
                                            <div class="fw-semibold mb-2"><?= htmlspecialchars($msg['subject']) ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="message-content mb-2">
                                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        </div>
                                        
                                        <div class="message-meta small">
                                            <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'You' : htmlspecialchars($msg['agent_name']) ?>
                                            • <?= date('M j, Y g:i A', strtotime($msg['sent_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Reply Form -->
                            <form method="POST" class="compose-form">
                                <input type="hidden" name="action" value="reply_to_agent">
                                <input type="hidden" name="original_message_id" value="<?= end($conversation_messages)['id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Reply</label>
                                    <textarea class="form-control form-control-custom" name="message" rows="4" 
                                              placeholder="Type your reply..." required></textarea>
                                </div>
                                
                                <button type="submit" class="btn-client-primary">
                                    <i class="fas fa-reply me-2"></i>Send Reply
                                </button>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Filters -->
                    <div class="filter-tabs">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <ul class="nav nav-pills">
                                    <li class="nav-item">
                                        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" 
                                           href="?action=<?= $action ?>&filter=all">All</a>
                                    </li>
                                    <?php if ($action === 'list' || $action === 'inbox'): ?>
                                        <li class="nav-item">
                                            <a class="nav-link <?= $filter === 'unread' ? 'active' : '' ?>" 
                                               href="?action=<?= $action ?>&filter=unread">Unread</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link <?= $filter === 'property' ? 'active' : '' ?>" 
                                               href="?action=<?= $action ?>&filter=property">Property Related</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <form method="GET" class="search-box">
                                    <input type="hidden" name="action" value="<?= $action ?>">
                                    <input type="hidden" name="filter" value="<?= $filter ?>">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search messages..." value="<?= htmlspecialchars($search) ?>">
                                    <i class="fas fa-search"></i>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Property Filter Section -->
                        <?php if ($filter === 'property' && ($action === 'list' || $action === 'inbox')): ?>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Select Property:</label>
                                    <select class="form-control form-control-custom" id="propertySelect" onchange="filterByProperty()">
                                        <option value="">Choose a property...</option>
                                        <?php foreach ($client_properties as $property): ?>
                                            <option value="<?= $property['id'] ?>" <?= ($_GET['property_id'] ?? '') == $property['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($property['title']) ?> - <?= htmlspecialchars($property['address_line1']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if (!empty($_GET['property_id'])): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Showing messages about <strong><?= htmlspecialchars($client_properties[array_search($_GET['property_id'], array_column($client_properties, 'id'))]['title'] ?? 'Property') ?></strong>
                                    <button type="button" class="btn-close float-end" onclick="clearPropertyFilter()"></button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($action === 'inbox' || $action === 'list'): ?>
                        <!-- Conversations List for Inbox -->
                        <?php if (!empty($conversations)): ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <div class="conversation-item <?= $conversation['unread_count'] > 0 ? 'has-unread' : '' ?>"
                                     onclick="window.location.href='?action=conversation&partner=<?= $conversation['partner_id'] ?><?= $conversation['property_id'] ? '&property=' . $conversation['property_id'] : '' ?>'">
                                    <div class="d-flex align-items-center">
                                        <div class="conversation-avatar">
                                            <?= strtoupper(substr($conversation['partner_name'] ?? 'A', 0, 2)) ?>
                                        </div>
                                        
                                        <div class="conversation-preview">
                                            <div class="conversation-name">
                                                <?= htmlspecialchars($conversation['partner_name'] ?? 'Unknown Agent') ?>
                                                <?php if ($conversation['property_title']): ?>
                                                    <small class="text-muted">
                                                        • <?= htmlspecialchars($conversation['property_title']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-last-message">
                                                <strong><?= htmlspecialchars($conversation['latest_message']['subject']) ?></strong>
                                            </div>
                                            <div class="conversation-last-message">
                                                <?= htmlspecialchars(substr($conversation['latest_message']['message'], 0, 80)) ?>
                                                <?= strlen($conversation['latest_message']['message']) > 80 ? '...' : '' ?>
                                            </div>
                                            <div class="conversation-time">
                                                <?= date('M j, Y g:i A', strtotime($conversation['latest_message']['sent_at'])) ?>
                                                • <?= $conversation['message_count'] ?> message<?= $conversation['message_count'] > 1 ? 's' : '' ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <div class="unread-badge">
                                                <?= $conversation['unread_count'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="content-card">
                                <div class="content-card-body text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-3">No Messages Found</h4>
                                    <p class="text-muted mb-4">
                                        <?php if (!empty($search) || $filter !== 'all'): ?>
                                            No messages match your current search or filter criteria.
                                        <?php else: ?>
                                            You don't have any messages yet. Start by searching for properties and contacting agents.
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($search) || $filter !== 'all'): ?>
                                        <a href="messages.php" class="btn-client-primary">
                                            <i class="fas fa-filter"></i>Clear Filters
                                        </a>
                                    <?php else: ?>
                                        <a href="../properties.php" class="btn-client-primary">
                                            <i class="fas fa-search me-2"></i>Search Properties
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Sent Messages List -->
                        <?php 
                        $sent_messages = array_filter($messages, fn($m) => $m['sender_id'] == $_SESSION['user_id']);
                        ?>
                        <?php if (!empty($sent_messages)): ?>
                            <?php foreach ($sent_messages as $message): ?>
                                <div class="conversation-item">
                                    <div class="d-flex align-items-center">
                                        <div class="conversation-avatar">
                                            <?= strtoupper(substr($message['agent_name'] ?? 'A', 0, 2)) ?>
                                        </div>
                                        
                                        <div class="conversation-preview">
                                            <div class="conversation-name">
                                                <strong>To:</strong> <?= htmlspecialchars($message['agent_name'] ?? 'Unknown Agent') ?>
                                                <?php if ($message['property_title']): ?>
                                                    <small class="text-muted">
                                                        • <?= htmlspecialchars($message['property_title']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-last-message">
                                                <strong><?= htmlspecialchars($message['subject']) ?></strong>
                                            </div>
                                            <div class="conversation-last-message">
                                                <?= htmlspecialchars(substr($message['message'], 0, 80)) ?>
                                                <?= strlen($message['message']) > 80 ? '...' : '' ?>
                                            </div>
                                            <div class="conversation-time">
                                                <?= date('M j, Y g:i A', strtotime($message['sent_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="content-card">
                                <div class="content-card-body text-center py-5">
                                    <i class="fas fa-paper-plane fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-3">No Sent Messages</h4>
                                    <p class="text-muted mb-4">You haven't sent any messages yet. Start by contacting agents about properties.</p>
                                    <a href="../properties.php" class="btn-client-primary">
                                        <i class="fas fa-search me-2"></i>Search Properties
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newMessageModalLabel">
                        <i class="fas fa-envelope me-2"></i>Send New Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_new_message">
                        
                        <div class="mb-3">
                            <label for="property_id" class="form-label fw-semibold">
                                <i class="fas fa-home me-1"></i>Select Property *
                            </label>
                            <select class="form-control form-control-custom" id="property_id" name="property_id" required onchange="updateAgentInfo()">
                                <option value="">Choose a property...</option>
                                <?php foreach ($available_properties as $property): ?>
                                    <option value="<?= $property['id'] ?>" 
                                            data-agent="<?= htmlspecialchars($property['agent_name']) ?>"
                                            data-agent-email="<?= htmlspecialchars($property['agent_email']) ?>"
                                            data-price="<?= number_format($property['price']) ?>">
                                        <?= htmlspecialchars($property['title']) ?> - <?= htmlspecialchars($property['address_line1']) ?>, <?= htmlspecialchars($property['city']) ?>
                                        (€<?= number_format($property['price']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="agentInfo" style="display: none;" class="mb-3">
                            <div class="alert alert-info">
                                <i class="fas fa-user-tie me-2"></i>
                                <strong>Message will be sent to:</strong> <span id="agentName"></span>
                                <br><small class="text-muted"><span id="agentEmail"></span></small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label fw-semibold">
                                <i class="fas fa-tag me-1"></i>Subject *
                            </label>
                            <input type="text" class="form-control form-control-custom" id="subject" name="subject" 
                                   placeholder="Enter message subject..." required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label fw-semibold">
                                <i class="fas fa-comment me-1"></i>Message *
                            </label>
                            <textarea class="form-control form-control-custom" id="message" name="message" 
                                      rows="5" placeholder="Type your message here..." required></textarea>
                        </div>
                        
                        <div class="alert alert-light">
                            <i class="fas fa-info-circle me-2"></i>
                            <small class="text-muted">
                                Your message will be sent directly to the property agent. They will be able to reply and start a conversation with you.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn-client-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-scroll message thread to bottom
        document.addEventListener('DOMContentLoaded', function() {
            const messageThread = document.querySelector('.message-thread');
            if (messageThread) {
                messageThread.scrollTop = messageThread.scrollHeight;
            }
        });

        // Auto-expand textarea
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });

        // Search functionality
        document.querySelector('input[name="search"]')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Property filter functions
        function filterByProperty() {
            const propertyId = document.getElementById('propertySelect').value;
            if (propertyId) {
                window.location.href = `?action=<?= $action ?>&filter=property&property_id=${propertyId}`;
            } else {
                window.location.href = `?action=<?= $action ?>&filter=property`;
            }
        }
        
        function clearPropertyFilter() {
            window.location.href = `?action=<?= $action ?>&filter=all`;
        }

        // Update agent info when property is selected
        function updateAgentInfo() {
            const propertySelect = document.getElementById('property_id');
            const agentInfo = document.getElementById('agentInfo');
            const agentName = document.getElementById('agentName');
            const agentEmail = document.getElementById('agentEmail');
            const subjectField = document.getElementById('subject');
            
            if (propertySelect.value) {
                const selectedOption = propertySelect.selectedOptions[0];
                const agent = selectedOption.getAttribute('data-agent');
                const email = selectedOption.getAttribute('data-agent-email');
                const propertyTitle = selectedOption.text.split(' - ')[0];
                
                agentName.textContent = agent;
                agentEmail.textContent = email;
                agentInfo.style.display = 'block';
                
                // Auto-fill subject if empty
                if (!subjectField.value) {
                    subjectField.value = `Inquiry about ${propertyTitle}`;
                }
            } else {
                agentInfo.style.display = 'none';
                subjectField.value = '';
            }
        }
    </script>

</body>
</html>
