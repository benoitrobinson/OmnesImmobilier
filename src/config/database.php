<?php
// database.php - Fixed version

// Detect OS environment
$isMac = stripos(PHP_OS, 'Darwin') !== false; // Mac
$isWindows = stripos(PHP_OS, 'WIN') !== false; // Windows

// Set DB config based on OS
if ($isMac) {
    $host = 'localhost';
    $port = '8889'; // MAMP default MySQL port
    $username = 'root';
    $password = 'root'; // MAMP default password
} elseif ($isWindows) {
    $host = 'localhost';
    $port = '3306'; // WAMP default port
    $username = 'root';
    $password = ''; // WAMP default password
} else {
    // Default fallback
    $host = 'localhost';
    $port = '3306';
    $username = 'root';
    $password = '';
}

$dbname = 'omnes_immobilier';

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// Database class for better organization (optional)
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        global $pdo;
        $this->connection = $pdo;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Make $pdo available globally
$GLOBALS['pdo'] = $pdo;
?>