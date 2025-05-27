<?php
// ajax/save_theme.php
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['theme'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$theme = $input['theme'];

// Validate theme value
if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid theme value']);
    exit;
}

// Save to session
$_SESSION['user_theme'] = $theme;

// Optionally save to database (if you have a user_preferences table)
/*
try {
    require_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if preference exists
    $check_query = "SELECT id FROM user_preferences WHERE user_id = :user_id AND preference_key = 'theme'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Update existing preference
        $update_query = "UPDATE user_preferences SET preference_value = :theme, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND preference_key = 'theme'";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':theme', $theme);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $update_stmt->execute();
    } else {
        // Insert new preference
        $insert_query = "INSERT INTO user_preferences (user_id, preference_key, preference_value, created_at) VALUES (:user_id, 'theme', :theme, CURRENT_TIMESTAMP)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $insert_stmt->bindParam(':theme', $theme);
        $insert_stmt->execute();
    }
} catch (Exception $e) {
    // Log error but don't fail the request
    error_log("Theme save error: " . $e->getMessage());
}
*/

echo json_encode([
    'success' => true,
    'theme' => $theme,
    'message' => 'Theme preference saved successfully'
]);
?>