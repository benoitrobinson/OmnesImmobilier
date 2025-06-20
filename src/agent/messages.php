<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn() || !isAgent()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$message_id = $_GET['id'] ?? null;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Handle agent replies
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'reply_to_client') {
        $original_message_id = (int)($_POST['original_message_id'] ?? 0);
        $message_content = trim($_POST['message'] ?? '');
        
        if (empty($message_content)) {
            $error = 'Reply content is required.';
        } else {
            try {
                // Get original message details using correct column names
                $original_query = "SELECT sender_id, subject, property_id FROM messages WHERE id = ? AND receiver_id = ?";
                $original_stmt = $pdo->prepare($original_query);
                $original_stmt->execute([$original_message_id, $_SESSION['user_id']]);
                $original = $original_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($original) {
                    $reply_subject = strpos($original['subject'], 'Re: ') === 0 ? $original['subject'] : 'Re: ' . $original['subject'];
                    
                    // Insert agent's reply with property reference
                    $query = "INSERT INTO messages (sender_id, receiver_id, property_id, message, subject, reply_to_id, sent_at, is_read) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)";
                    $stmt = $pdo->prepare($query);
                    
                    $reply_property_id = ($original['property_id'] && $original['property_id'] > 0) ? $original['property_id'] : 1;
                    
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
                error_log("Agent reply error: " . $e->getMessage());
                $error = 'Error sending reply.';
            }
        }
    }
}

// Get agent's messages with proper column names and null handling
$messages = [];
$conversations = [];
try {
    $base_query = "SELECT m.*, 
                     CONCAT(COALESCE(client.first_name, ''), ' ', COALESCE(client.last_name, '')) as client_name,
                     COALESCE(client.email, '') as client_email,
                     COALESCE(client.phone, '') as client_phone,
                     COALESCE(p.title, '') as property_title,
                     COALESCE(p.price, 0) as property_price,
                     COALESCE(p.city, '') as property_city,
                     CASE 
                        WHEN m.sender_id = :agent_id THEN 'sent'
                        ELSE 'received'
                     END as message_type
              FROM messages m 
              LEFT JOIN users client ON (CASE WHEN m.sender_id = :agent_id THEN m.receiver_id ELSE m.sender_id END) = client.id
              LEFT JOIN properties p ON m.property_id = p.id
              WHERE m.sender_id = :agent_id OR m.receiver_id = :agent_id";
    
    // Apply filters
    $params = ['agent_id' => $_SESSION['user_id']];
    
    if ($filter === 'unread' && ($action === 'list' || $action === 'inbox')) {
        $base_query .= " AND m.receiver_id = :agent_id AND m.is_read = 0";
    } elseif ($filter === 'property' && ($action === 'list' || $action === 'inbox')) {
        $property_id = $_GET['property_id'] ?? null;
        $client_id = $_GET['client_id'] ?? null;
        
        if ($property_id) {
            $base_query .= " AND m.property_id = :property_id";
            $params['property_id'] = $property_id;
            
            if ($client_id) {
                $base_query .= " AND (m.sender_id = :client_id OR m.receiver_id = :client_id)";
                $params['client_id'] = $client_id;
            }
        }
    }
    
    if (!empty($search)) {
        $base_query .= " AND (m.subject LIKE :search OR m.message LIKE :search OR 
                             CONCAT(client.first_name, ' ', client.last_name) LIKE :search)";
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
                    'partner_name' => $message['client_name'],
                    'property_id' => $message['property_id'],
                    'property_title' => $message['property_title'],
                    'latest_message' => $message,
                    'unread_count' => 0,
                    'message_count' => 0
                ];
            }
            
            // Update conversation stats
            $conversations_temp[$conversation_key]['message_count']++;
            
            // Count unread messages where agent is receiver
            if ($message['receiver_id'] == $_SESSION['user_id'] && $message['is_read'] == 0) {
                $conversations_temp[$conversation_key]['unread_count']++;
            }
            
            // Keep the latest message
            if (strtotime($message['sent_at']) > strtotime($conversations_temp[$conversation_key]['latest_message']['sent_at'])) {
                $conversations_temp[$conversation_key]['latest_message'] = $message;
            }
        }
        
        // Convert to indexed array and sort by latest message
        $conversations = array_values($conversations_temp);
        usort($conversations, function($a, $b) {
            return strtotime($b['latest_message']['sent_at']) - strtotime($a['latest_message']['sent_at']);
        });
    }
    
} catch (Exception $e) {
    error_log("Agent messages fetch error: " . $e->getMessage());
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
                          CONCAT(COALESCE(client.first_name, ''), ' ', COALESCE(client.last_name, '')) as client_name,
                          COALESCE(client.email, '') as client_email,
                          COALESCE(client.phone, '') as client_phone,
                          COALESCE(p.title, '') as property_title
                   FROM messages m 
                   LEFT JOIN users client ON client.id = ?
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

// Get agent's properties for property filter
$agent_properties = [];
try {
    $prop_query = "SELECT id, title, address_line1 FROM properties WHERE agent_id = ? ORDER BY title";
    $prop_stmt = $pdo->prepare($prop_query);
    $prop_stmt->execute([$_SESSION['user_id']]);
    $agent_properties = $prop_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Properties fetch error: " . $e->getMessage());
    $agent_properties = [];
}

// Get clients who have messaged about a specific property
$property_clients = [];
if ($filter === 'property' && !empty($_GET['property_id'])) {
    try {
        $clients_query = "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
                         FROM messages m 
                         JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
                         WHERE (m.sender_id = ? OR m.receiver_id = ?) 
                         AND m.property_id = ? 
                         AND u.role = 'client'
                         ORDER BY name";
        $clients_stmt = $pdo->prepare($clients_query);
        $clients_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_GET['property_id']]);
        $property_clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Property clients fetch error: " . $e->getMessage());
        $property_clients = [];
    }
}

// Calculate message statistics
$message_stats = [
    'total_received' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $_SESSION['user_id'])),
    'unread' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $_SESSION['user_id'] && $m['is_read'] == 0)),
    'sent' => count(array_filter($messages, fn($m) => $m['sender_id'] == $_SESSION['user_id'])),
    'today' => count(array_filter($messages, fn($m) => date('Y-m-d', strtotime($m['sent_at'])) == date('Y-m-d'))),
    'unread_messages' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $_SESSION['user_id'] && $m['is_read'] == 0))
];

// Get specific message for viewing
$message = null;
if ($action === 'view' && $message_id) {
    try {
        $query = "SELECT m.*, 
                         CONCAT(COALESCE(client.first_name, ''), ' ', COALESCE(client.last_name, '')) as client_name,
                         COALESCE(client.email, '') as client_email,
                         COALESCE(client.phone, '') as client_phone,
                         COALESCE(p.title, '') as property_title,
                         COALESCE(p.price, 0) as property_price,
                         COALESCE(p.city, '') as property_city
                  FROM messages m 
                  LEFT JOIN users client ON (CASE WHEN m.sender_id = :agent_id THEN m.receiver_id ELSE m.sender_id END) = client.id
                  LEFT JOIN properties p ON m.property_id = p.id
                  WHERE m.id = :message_id AND (m.sender_id = :agent_id OR m.receiver_id = :agent_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['message_id' => $message_id, 'agent_id' => $_SESSION['user_id']]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$message) {
            $error = 'Message not found.';
            $action = 'list';
        } else {
            // Mark message as read if agent is the receiver
            if ($message['receiver_id'] == $_SESSION['user_id'] && $message['is_read'] == 0) {
                $mark_read = "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?";
                $read_stmt = $pdo->prepare($mark_read);
                $read_stmt->execute([$message_id, $_SESSION['user_id']]);
            }
        }
    } catch (Exception $e) {
        error_log("Message fetch error: " . $e->getMessage());
        $error = 'Error loading message.';
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Agent Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Navigation CSS -->
    <link href="../assets/css/agent_navigation.css" rel="stylesheet">
    
    <style>
        :root {
            --agent-primary: #2c5aa0;
            --agent-secondary: #4a90e2;
            --agent-success: #28a745;
            --agent-warning: #ffc107;
            --agent-danger: #dc3545;
            --agent-info: #17a2b8;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-top: 80px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
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

        .stats-value.primary { color: var(--agent-primary); }
        .stats-value.success { color: var(--agent-success); }
        .stats-value.warning { color: var(--agent-warning); }
        .stats-value.danger { color: var(--agent-danger); }

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
            color: var(--agent-primary);
            border-left: 4px solid var(--agent-primary);
            padding-left: calc(1.5rem - 4px);
        }

        .sidebar-nav-item.active {
            background: #f8f9fa;
            color: var(--agent-primary);
            font-weight: 600;
            border-left: 4px solid var(--agent-primary);
            padding-left: calc(1.5rem - 4px);
        }

        .sidebar-nav-item i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .message-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .message-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .message-item.unread {
            border-left: 4px solid var(--agent-primary);
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.05) 0%, rgba(74, 144, 226, 0.05) 100%);
        }

        .message-item.selected {
            border-color: var(--agent-primary);
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.1) 0%, rgba(74, 144, 226, 0.1) 100%);
        }

        .message-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f8f9fa;
            background: #f8f9fa;
        }

        .message-body {
            padding: 1.5rem;
        }

        .message-meta {
            font-size: 0.875rem;
            color: #6c757d;
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
            border-left: 4px solid var(--agent-warning);
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(253, 126, 20, 0.05) 100%);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
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
            background: var(--agent-warning);
            color: white;
            border-radius: 50px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
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
            border-color: var(--agent-primary);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
        }

        .btn-agent-primary {
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
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

        .btn-agent-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
            color: white;
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
            background: linear-gradient(135deg, var(--agent-primary) 0%, var(--agent-secondary) 100%);
            color: white;
            margin-left: auto;
        }

        .message-bubble.received {
            background: white;
            margin-right: auto;
            border: 1px solid #e9ecef;
        }

        .property-tag {
            background: linear-gradient(135deg, var(--agent-success) 0%, #20c997 100%);
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
            background: var(--agent-primary);
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
<body data-user-role="agent">
    <!-- Include Navigation -->
    <?php include '../includes/agent_navigation.php'; ?>

    <div class="container mt-4">

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
                            case 'view':
                                echo 'Message Details';
                                break;
                            default:
                                echo 'Messages Inbox';
                        }
                        ?>
                    </h1>
                    <p class="mb-0 opacity-90">
                        Manage client communications and property inquiries
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
                            <a href="manage_appointments.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-calendar-alt me-1"></i>Appointments
                            </a>
                            <a href="manage_properties.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-home me-1"></i>Properties
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
                <?php if ($action === 'view' && $message): ?>
                    <!-- View Message Details -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-envelope-open me-2"></i>
                                    Message from <?= htmlspecialchars($message['client_name'] ?? 'Client') ?>
                                </div>
                                <a href="messages.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Inbox
                                </a>
                            </div>
                        </div>
                        <div class="content-card-body">
                            <?php if ($message['property_title']): ?>
                                <div class="property-tag mb-3">
                                    <i class="fas fa-home me-1"></i><?= htmlspecialchars($message['property_title']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <h5><?= htmlspecialchars($message['subject']) ?></h5>
                                    <div class="text-muted">
                                        <strong>From:</strong> <?= htmlspecialchars($message['client_name']) ?><br>
                                        <strong>Email:</strong> <?= htmlspecialchars($message['client_email']) ?><br>
                                        <strong>Date:</strong> <?= date('F j, Y - g:i A', strtotime($message['sent_at'])) ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="message-status">
                                        <?= ($message['is_read'] == 0) ? 'Unread' : 'Read' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-top pt-4 mb-4">
                                <div class="message-content">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>
                            </div>
                            
                            <!-- Reply Form -->
                            <div class="border-top pt-4">
                                <h6>Reply to this message:</h6>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="action" value="reply_to_client">
                                    <input type="hidden" name="original_message_id" value="<?= $message['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <textarea class="form-control form-control-custom" name="message" rows="6" 
                                                  placeholder="Type your reply..." required></textarea>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn-agent-primary">
                                            <i class="fas fa-reply me-2"></i>Send Reply
                                        </button>
                                        <a href="tel:<?= htmlspecialchars($message['client_phone']) ?>" class="btn btn-outline-success">
                                            <i class="fas fa-phone me-2"></i>Call Client
                                        </a>
                                        <a href="mailto:<?= htmlspecialchars($message['client_email']) ?>" class="btn btn-outline-info">
                                            <i class="fas fa-envelope me-2"></i>Email Client
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Message List (Inbox/Sent) -->
                    
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
                                        <?php foreach ($agent_properties as $property): ?>
                                            <option value="<?= $property['id'] ?>" <?= ($_GET['property_id'] ?? '') == $property['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($property['title']) ?> - <?= htmlspecialchars($property['address_line1']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (!empty($property_clients)): ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Select Client:</label>
                                        <select class="form-control form-control-custom" id="clientSelect" onchange="filterByClient()">
                                            <option value="">All clients for this property</option>
                                            <?php foreach ($property_clients as $client): ?>
                                                <option value="<?= $client['id'] ?>" <?= ($_GET['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($client['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($_GET['property_id']) && !empty($_GET['client_id'])): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Showing conversation with <strong><?= htmlspecialchars($property_clients[array_search($_GET['client_id'], array_column($property_clients, 'id'))]['name'] ?? 'Client') ?></strong> 
                                    about <strong><?= htmlspecialchars($agent_properties[array_search($_GET['property_id'], array_column($agent_properties, 'id'))]['title'] ?? 'Property') ?></strong>
                                    <button type="button" class="btn-close float-end" onclick="clearPropertyFilter()"></button>
                                </div>
                            <?php elseif (!empty($_GET['property_id'])): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Showing messages about <strong><?= htmlspecialchars($agent_properties[array_search($_GET['property_id'], array_column($agent_properties, 'id'))]['title'] ?? 'Property') ?></strong>
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
                                            <?= strtoupper(substr($conversation['partner_name'] ?? 'C', 0, 2)) ?>
                                        </div>
                                        
                                        <div class="conversation-preview">
                                            <div class="conversation-name">
                                                <?= htmlspecialchars($conversation['partner_name'] ?? 'Unknown Client') ?>
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
                                            You don't have any messages yet. Clients will contact you about properties and appointments.
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($search) || $filter !== 'all'): ?>
                                        <a href="messages.php" class="btn-agent-primary">
                                            <i class="fas fa-filter"></i>Clear Filters
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($action === 'conversation'): ?>
                        <!-- Conversation Thread View -->
                        <div class="content-card">
                            <div class="content-card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-comments me-2"></i>
                                        Conversation with <?= htmlspecialchars($conversation_messages[0]['client_name'] ?? 'Client') ?>
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
                                                <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'You' : htmlspecialchars($msg['client_name']) ?>
                                                • <?= date('M j, Y g:i A', strtotime($msg['sent_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Reply Form -->
                                <form method="POST" class="compose-form">
                                    <input type="hidden" name="action" value="reply_to_client">
                                    <input type="hidden" name="original_message_id" value="<?= end($conversation_messages)['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Reply</label>
                                        <textarea class="form-control form-control-custom" name="message" rows="4" 
                                                  placeholder="Type your reply..." required></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn-agent-primary">
                                        <i class="fas fa-reply me-2"></i>Send Reply
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Sent Messages List -->
                        <?php 
                        // Filter to only show messages sent BY the agent
                        $sent_messages = array_filter($messages, fn($m) => $m['sender_id'] == $_SESSION['user_id']);
                        ?>
                        <?php if (!empty($sent_messages)): ?>
                            <?php foreach ($sent_messages as $message): ?>
                                <div class="message-item">
                                    <div class="message-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>To:</strong> <?= htmlspecialchars($message['client_name'] ?? 'Unknown Client') ?>
                                                
                                                <?php if ($message['property_title']): ?>
                                                    <div class="property-tag">
                                                        <i class="fas fa-home me-1"></i><?= htmlspecialchars($message['property_title']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-meta">
                                                <?= date('M j, Y g:i A', strtotime($message['sent_at'])) ?>
                                            </div>
                                        </div>
                                        <?php if ($message['subject']): ?>
                                            <div class="fw-semibold mt-1"><?= htmlspecialchars($message['subject']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-body">
                                        <?= nl2br(htmlspecialchars(substr($message['message'], 0, 200))) ?>
                                        <?= strlen($message['message']) > 200 ? '...' : '' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="content-card">
                                <div class="content-card-body text-center py-5">
                                    <i class="fas fa-paper-plane fa-4x text-muted mb-4"></i>
                                    <h4 class="text-muted mb-3">No Sent Messages</h4>
                                    <p class="text-muted mb-4">You haven't sent any replies yet. Reply to client messages to see them here.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/navigation.js"></script>

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

        // Mark messages as read when clicked
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function() {
                // You could add AJAX call here to mark as read immediately
            });
        });

        // Auto-refresh unread count (every 30 seconds)
        setInterval(function() {
            // You could add AJAX call here to refresh unread count
        }, 30000);

        // Reply form functions
        function showReplyForm(messageId) {
            document.getElementById('reply-form-' + messageId).style.display = 'block';
        }
        
        function hideReplyForm(messageId) {
            document.getElementById('reply-form-' + messageId).style.display = 'none';
        }
        
        // Property filter functions
        function filterByProperty() {
            const propertyId = document.getElementById('propertySelect').value;
            if (propertyId) {
                window.location.href = `?action=<?= $action ?>&filter=property&property_id=${propertyId}`;
            } else {
                window.location.href = `?action=<?= $action ?>&filter=property`;
            }
        }
        
        function filterByClient() {
            const propertyId = document.getElementById('propertySelect').value;
            const clientId = document.getElementById('clientSelect').value;
            if (propertyId && clientId) {
                window.location.href = `?action=<?= $action ?>&filter=property&property_id=${propertyId}&client_id=${clientId}`;
            } else if (propertyId) {
                window.location.href = `?action=<?= $action ?>&filter=property&property_id=${propertyId}`;
            }
        }
        
        function clearPropertyFilter() {
            window.location.href = `?action=<?= $action ?>&filter=all`;
        }
    </script>
    <script src="../assets/js/agent_navigation.js"></script>

</body>
</html>