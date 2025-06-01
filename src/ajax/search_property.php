<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Only allow clients to search
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only clients can search properties.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$searchTerm = trim($_POST['search_term'] ?? '');

if (empty($searchTerm)) {
    echo json_encode(['success' => false, 'message' => 'Search term is required']);
    exit;
}

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // Check if search term is numeric (Property ID search)
    if (is_numeric($searchTerm)) {
        // Search by Property ID
        $query = "SELECT * FROM properties WHERE id = ? AND status = 'available'";
        $stmt = $db->prepare($query);
        $stmt->execute([(int)$searchTerm]);
    } else {
        // Search by City name (case insensitive)
        $query = "SELECT * FROM properties WHERE LOWER(city) LIKE LOWER(?) AND status = 'available' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['%' . $searchTerm . '%']);
    }
    
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($property) {
        echo json_encode([
            'success' => true,
            'property' => [
                'id' => $property['id'],
                'title' => $property['title'],
                'city' => $property['city'],
                'price' => $property['price']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'property' => null,
            'message' => 'No property found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Property search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
