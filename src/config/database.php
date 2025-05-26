<?php
// Detect OS environment
$isMac = stripos(PHP_OS, 'Darwin') !== false; // Mac 
$isWindows = stripos(PHP_OS, 'WIN') !== false; // Windows

// Set DB config based on OS
if ($isMac) {
    $host = 'localhost';
    $port = '8889';          // MAMP default MySQL port
    $username = 'root';
    $password = 'root';      // MAMP default password
} elseif ($isWindows) {
    $host = 'localhost';
    $port = '3306';          // WAMP default port
    $username = 'root';
    $password = '';          // WAMP default password
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
    echo "Connected successfully to the database on " . PHP_OS;
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

return $pdo;
