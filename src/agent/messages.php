<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an agent
if (!isLoggedIn() || !isAgent()) {
    redirect('../auth/login.php');
}

// Use the global $pdo connection
$db = $pdo;

$error = '';
$success = '';
$action = $_GET['action'] ?? 'inbox';
$conversation_id = $_GET['conversation'] ?? null;
$message_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'send_message') {
        $recipient_id = $_POST['recipient_id'] ?? '';
        $property_id = $_POST['property_id'] ?? '';
        $message_text = trim($_POST['message'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        
        if (empty($recipient_id) || empty($message_text)) {
            $error = 'Please select a recipient and enter a message.';
        } else {
            try {
                $query = "INSERT INTO messages (sender_id, receiver_id, property_id, message, subject, sent_at, is_read) 
                         VALUES (:sender_id, :receiver_id, :property_id, :message, :subject, NOW(), 0)";
                
                $stmt = $db->prepare($query);
                if ($stmt->execute([
                    'sender_id' => $_SESSION['user_id'],
                    'receiver_id' => $recipient_id,
                    'property_id' => $property_id ?: null,
                    'message' => $message_text,
                    'subject' => $subject
                ])) {
                    $success = 'Message sent successfully!';
                    $action = 'sent'; // Redirect to sent messages
                } else {
                    $error = 'Error sending message.';
                }
            } catch (PDOException $e) {
                error_log("Message send error: " . $e->getMessage());
                $error = 'Error sending message.';
            }
        }
    }
    
    elseif ($form_action === 'reply_message') {
        $reply_to_id = $_POST['reply_to_id'] ?? '';
        $recipient_id = $_POST['recipient_id'] ?? '';
        $property_id = $_POST['property_id'] ?? '';
        $message_text = trim($_POST['message'] ?? '');
        
        if (empty($recipient_id) || empty($message_text)) {
            $error = 'Message content is required.';
        } else {
            try {
                // Get original message subject for reply threading
                $orig_query = "SELECT subject FROM messages WHERE id = :id";
                $orig_stmt = $db->prepare($orig_query);
                $orig_stmt->execute(['id' => $reply_to_id]);
                $orig_msg = $orig_stmt->fetch(PDO::FETCH_ASSOC);
                
                $reply_subject = 'Re: ' . ($orig_msg['subject'] ?? 'Property Inquiry');
                
                $query = "INSERT INTO messages (sender_id, receiver_id, property_id, message, subject, reply_to_id, sent_at, is_read) 
                         VALUES (:sender_id, :receiver_id, :property_id, :message, :subject, :reply_to_id, NOW(), 0)";
                
                $stmt = $db->prepare($query);
                if ($stmt->execute([
                    'sender_id' => $_SESSION['user_id'],
                    'receiver_id' => $recipient_id,
                    'property_id' => $property_id,
                    'message' => $message_text,
                    'subject' => $reply_subject,
                    'reply_to_id' => $reply_to_id
                ])) {
                    $success = 'Reply sent successfully!';
                } else {
                    $error = 'Error sending reply.';
                }
            } catch (PDOException $e) {
                error_log("Reply send error: " . $e->getMessage());
                $error = 'Error sending reply.';
            }
        }
    }
    
    elseif ($form_action === 'mark_read') {
        $message_ids = $_POST['message_ids'] ?? [];
        
        if (!empty($message_ids)) {
            try {
                $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
                $query = "UPDATE messages SET is_read = 1 WHERE id IN ($placeholders) AND receiver_id = ?";
                
                $params = array_merge($message_ids, [$_SESSION['user_id']]);
                $stmt = $db->prepare($query);
                
                if ($stmt->execute($params)) {
                    $success = 'Messages marked as read.';
                } else {
                    $error = 'Error updating messages.';
                }
            } catch (PDOException $e) {
                error_log("Mark read error: " . $e->getMessage());
                $error = 'Error updating messages.';
            }
        }
    }
    
    elseif ($form_action === 'delete_messages') {
        $message_ids = $_POST['message_ids'] ?? [];
        
        if (!empty($message_ids)) {
            try {
                $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
                $query = "DELETE FROM messages WHERE id IN ($placeholders) AND (sender_id = ? OR receiver_id = ?)";
                
                $params = array_merge($message_ids, [$_SESSION['user_id'], $_SESSION['user_id']]);
                $stmt = $db->prepare($query);
                
                if ($stmt->execute($params)) {
                    $success = 'Messages deleted successfully.';
                } else {
                    $error = 'Error deleting messages.';
                }
            } catch (PDOException $e) {
                error_log("Delete messages error: " . $e->getMessage());
                $error = 'Error deleting messages.';
            }
        }
    }
}

// Add missing columns to messages table if they don't exist
try {
    // Check if subject column exists
    $check_subject = $db->query("SHOW COLUMNS FROM messages LIKE 'subject'");
    if ($check_subject->rowCount() == 0) {
        $db->exec("ALTER TABLE messages ADD COLUMN subject VARCHAR(255) NULL AFTER message");
    }
    
    // Check if reply_to_id column exists  
    $check_reply = $db->query("SHOW COLUMNS FROM messages LIKE 'reply_to_id'");
    if ($check_reply->rowCount() == 0) {
        $db->exec("ALTER TABLE messages ADD COLUMN reply_to_id INT NULL AFTER subject");
    }
} catch (Exception $e) {
    error_log("Column addition error: " . $e->getMessage());
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build message queries based on action
$messages = [];
$message_stats = [
    'total_messages' => 0,
    'unread_messages' => 0,
    'sent_messages' => 0,
    'today_messages' => 0
];

try {
    // Get message statistics
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM messages WHERE receiver_id = :agent_id) as total_received,
                    (SELECT COUNT(*) FROM messages WHERE receiver_id = :agent_id AND is_read = 0) as unread_messages,
                    (SELECT COUNT(*) FROM messages WHERE sender_id = :agent_id) as sent_messages,
                    (SELECT COUNT(*) FROM messages WHERE (sender_id = :agent_id OR receiver_id = :agent_id) AND DATE(sent_at) = CURDATE()) as today_messages";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $stats_result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats_result) {
        $message_stats = [
            'total_messages' => $stats_result['total_received'],
            'unread_messages' => $stats_result['unread_messages'],
            'sent_messages' => $stats_result['sent_messages'],
            'today_messages' => $stats_result['today_messages']
        ];
    }
    
    // Get messages based on action
    if ($action === 'inbox' || $action === 'conversation') {
        $where_conditions = ['m.receiver_id = :agent_id'];
        $params = ['agent_id' => $_SESSION['user_id']];
        
        if ($filter === 'unread') {
            $where_conditions[] = 'm.is_read = 0';
        } elseif ($filter === 'property') {
            $where_conditions[] = 'm.property_id IS NOT NULL';
        }
        
        if (!empty($search)) {
            $where_conditions[] = '(m.message LIKE :search OR m.subject LIKE :search OR CONCAT(sender.first_name, " ", sender.last_name) LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        
        if ($conversation_id) {
            $where_conditions[] = '(m.sender_id = :conversation_id OR (m.receiver_id = :conversation_id AND m.sender_id = :agent_id))';
            $params['conversation_id'] = $conversation_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT m.*, 
                         CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
                         sender.email as sender_email,
                         sender.phone as sender_phone,
                         p.title as property_title,
                         p.address_line1 as property_address,
                         p.price as property_price
                  FROM messages m 
                  JOIN users sender ON m.sender_id = sender.id 
                  LEFT JOIN properties p ON m.property_id = p.id 
                  WHERE $where_clause
                  ORDER BY m.sent_at DESC";
                  
    } elseif ($action === 'sent') {
        $where_conditions = ['m.sender_id = :agent_id'];
        $params = ['agent_id' => $_SESSION['user_id']];
        
        if (!empty($search)) {
            $where_conditions[] = '(m.message LIKE :search OR m.subject LIKE :search OR CONCAT(receiver.first_name, " ", receiver.last_name) LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT m.*, 
                         CONCAT(receiver.first_name, ' ', receiver.last_name) as receiver_name,
                         receiver.email as receiver_email,
                         p.title as property_title,
                         p.address_line1 as property_address
                  FROM messages m 
                  JOIN users receiver ON m.receiver_id = receiver.id 
                  LEFT JOIN properties p ON m.property_id = p.id 
                  WHERE $where_clause
                  ORDER BY m.sent_at DESC";
    }
    
    if (isset($query)) {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Mark messages as read when viewing conversation
    if ($action === 'conversation' && $conversation_id) {
        $mark_read_query = "UPDATE messages SET is_read = 1 
                           WHERE receiver_id = :agent_id AND sender_id = :conversation_id AND is_read = 0";
        $mark_read_stmt = $db->prepare($mark_read_query);
        $mark_read_stmt->execute(['agent_id' => $_SESSION['user_id'], 'conversation_id' => $conversation_id]);
    }
    
} catch (Exception $e) {
    error_log("Messages fetch error: " . $e->getMessage());
    $messages = [];
}

// Get clients for compose message
$clients = [];
try {
    $clients_query = "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as client_name, u.email
                      FROM users u 
                      JOIN appointments a ON u.id = a.client_id 
                      WHERE a.agent_id = :agent_id AND u.role = 'client'
                      ORDER BY client_name";
    $clients_stmt = $db->prepare($clients_query);
    $clients_stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Clients fetch error: " . $e->getMessage());
}

// Get agent's properties for message context
$agent_properties = [];
try {
    $props_query = "SELECT id, title, address_line1, city FROM properties WHERE agent_id = :agent_id ORDER BY title";
    $props_stmt = $db->prepare($props_query);
    $props_stmt->execute(['agent_id' => $_SESSION['user_id']]);
    $agent_properties = $props_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Properties fetch error: " . $e->getMessage());
}

// Group messages by conversation for inbox view
$conversations = [];
if ($action === 'inbox' && !$conversation_id) {
    $conversation_map = [];
    foreach ($messages as $message) {
        $conv_key = $message['sender_id'];
        if (!isset($conversation_map[$conv_key])) {
            $conversation_map[$conv_key] = [
                'participant_id' => $message['sender_id'],
                'participant_name' => $message['sender_name'],
                'participant_email' => $message['sender_email'],
                'latest_message' => $message,
                'unread_count' => 0,
                'total_count' => 0
            ];
        }
        
        $conversation_map[$conv_key]['total_count']++;
        if (!$message['is_read']) {
            $conversation_map[$conv_key]['unread_count']++;
        }
        
        // Keep the latest message
        if (strtotime($message['sent_at']) > strtotime($conversation_map[$conv_key]['latest_message']['sent_at'])) {
            $conversation_map[$conv_key]['latest_message'] = $message;
        }
    }
    
    $conversations = array_values($conversation_map);
    usort($conversations, function($a, $b) {
        return strtotime($b['latest_message']['sent_at']) - strtotime($a['latest_message']['sent_at']);
    });
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
                            case 'compose':
                                echo 'Compose Message';
                                break;
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
                        <?php if ($action === 'inbox'): ?>
                            Manage client communications and property inquiries
                        <?php elseif ($action === 'compose'): ?>
                            Send a new message to your clients
                        <?php else: ?>
                            View and manage your message conversations
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($action !== 'compose'): ?>
                        <a href="?action=compose" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Compose
                        </a>
                    <?php else: ?>
                        <a href="manage_messages.php" class="btn btn-light">
                            <i class="fas fa-inbox me-2"></i>Back to Inbox
                        </a>
                    <?php endif; ?>
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
                    <div class="stats-value primary"><?= $message_stats['total_messages'] ?></div>
                    <div class="stats-label">Total Received</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value warning"><?= $message_stats['unread_messages'] ?></div>
                    <div class="stats-label">Unread</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value success"><?= $message_stats['sent_messages'] ?></div>
                    <div class="stats-label">Sent</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-value primary"><?= $message_stats['today_messages'] ?></div>
                    <div class="stats-label">Today</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="messages-sidebar">
                    <nav class="sidebar-nav">
                        <a href="?action=inbox" class="sidebar-nav-item <?= $action === 'inbox' ? 'active' : '' ?>">
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
                        <a href="?action=compose" class="sidebar-nav-item <?= $action === 'compose' ? 'active' : '' ?>">
                            <i class="fas fa-edit"></i>
                            <span>Compose</span>
                        </a>
                    </nav>

                    <!-- Quick Actions -->
                    <div class="p-3 border-top">
                        <h6 class="text-muted mb-3 text-uppercase fw-semibold" style="font-size: 0.75rem;">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="?action=compose" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>New Message
                            </a>
                            <a href="manage_appointments.php" class="btn btn-outline-success btn-sm">
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
                <?php if ($action === 'compose'): ?>
                    <!-- Compose Message Form -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <i class="fas fa-edit me-2"></i>Compose New Message
                        </div>
                        <div class="content-card-body">
                            <form method="POST" id="composeForm">
                                <input type="hidden" name="action" value="send_message">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Recipient *</label>
                                            <select class="form-control form-control-custom" name="recipient_id" required>
                                                <option value="">Select a client</option>
                                                <?php foreach ($clients as $client): ?>
                                                    <option value="<?= $client['id'] ?>">
                                                        <?= htmlspecialchars($client['client_name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Related Property (Optional)</label>
                                            <select class="form-control form-control-custom" name="property_id">
                                                <option value="">Select a property</option>
                                                <?php foreach ($agent_properties as $property): ?>
                                                    <option value="<?= $property['id'] ?>">
                                                        <?= htmlspecialchars($property['title']) ?> - <?= htmlspecialchars($property['address_line1']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Subject *</label>
                                    <input type="text" class="form-control form-control-custom" name="subject" 
                                           placeholder="Enter message subject" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Message *</label>
                                    <textarea class="form-control form-control-custom" name="message" rows="6" 
                                              placeholder="Enter your message here..." required></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn-agent-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                    <a href="manage_messages.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'conversation' && $conversation_id): ?>
                    <!-- Conversation Thread -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-comments me-2"></i>
                                    Conversation with <?= htmlspecialchars($messages[0]['sender_name'] ?? 'Client') ?>
                                </div>
                                <a href="manage_messages.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Inbox
                                </a>
                            </div>
                        </div>
                        <div class="content-card-body">
                            <!-- Message Thread -->
                            <div class="message-thread">
                                <?php foreach ($messages as $message): ?>
                                    <div class="message-bubble <?= $message['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received' ?>">
                                        <?php if ($message['property_title']): ?>
                                            <div class="property-tag">
                                                <i class="fas fa-home me-1"></i><?= htmlspecialchars($message['property_title']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($message['subject'] && $message !== end($messages)): ?>
                                            <div class="fw-semibold mb-2"><?= htmlspecialchars($message['subject']) ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="message-content mb-2">
                                            <?= nl2br(htmlspecialchars($message['message'])) ?>
                                        </div>
                                        
                                        <div class="message-meta small">
                                            <?= $message['sender_id'] == $_SESSION['user_id'] ? 'You' : htmlspecialchars($message['sender_name']) ?>
                                            • <?= date('M j, Y g:i A', strtotime($message['sent_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Reply Form -->
                            <form method="POST" class="compose-form">
                                <input type="hidden" name="action" value="reply_message">
                                <input type="hidden" name="reply_to_id" value="<?= end($messages)['id'] ?>">
                                <input type="hidden" name="recipient_id" value="<?= $conversation_id ?>">
                                <input type="hidden" name="property_id" value="<?= end($messages)['property_id'] ?>">
                                
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
                                    <?php if ($action === 'inbox'): ?>
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
                    </div>

                    <?php if ($action === 'inbox'): ?>
                        <!-- Conversations View -->
                        <?php if (!empty($conversations)): ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <div class="conversation-item <?= $conversation['unread_count'] > 0 ? 'has-unread' : '' ?>"
                                     onclick="window.location.href='?action=conversation&conversation=<?= $conversation['participant_id'] ?>'">
                                    <div class="d-flex align-items-center">
                                        <div class="conversation-avatar">
                                            <?= strtoupper(substr($conversation['participant_name'], 0, 2)) ?>
                                        </div>
                                        
                                        <div class="conversation-preview">
                                            <div class="conversation-name">
                                                <?= htmlspecialchars($conversation['participant_name']) ?>
                                                <?php if ($conversation['latest_message']['property_title']): ?>
                                                    <small class="text-muted">
                                                        • <?= htmlspecialchars($conversation['latest_message']['property_title']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-last-message">
                                                <?= htmlspecialchars(substr($conversation['latest_message']['message'], 0, 100)) ?>
                                                <?= strlen($conversation['latest_message']['message']) > 100 ? '...' : '' ?>
                                            </div>
                                            <div class="conversation-time">
                                                <?= date('M j, Y g:i A', strtotime($conversation['latest_message']['sent_at'])) ?>
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
                                            You don't have any messages yet. Clients will be able to contact you about properties and appointments.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($search) || $filter !== 'all'): ?>
                                        <a href="manage_messages.php" class="btn-agent-primary">
                                            <i class="fas fa-filter"></i>Clear Filters
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=compose" class="btn-agent-primary">
                                            <i class="fas fa-edit"></i>Compose Message
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Sent Messages List -->
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item">
                                    <div class="message-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>To: <?= htmlspecialchars($message['receiver_name']) ?></strong>
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
                                    <p class="text-muted mb-4">You haven't sent any messages yet.</p>
                                    <a href="?action=compose" class="btn-agent-primary">
                                        <i class="fas fa-edit"></i>Send Your First Message
                                    </a>
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
    </script>
    <script src="../assets/js/agent_navigation.js"></script>

</body>
</html>