<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Only allow admins to run this diagnostic
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$database = Database::getInstance();
$db = $database->getConnection();

echo "<h1>Auction System Diagnostic</h1>";

// 1. Check if auction tables exist
echo "<h2>1. Database Tables</h2>";
try {
    $tables = ['property_auctions', 'auction_participants', 'auction_bids', 'user_purchases'];
    foreach($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        echo "<p>Table '{$table}': " . ($stmt->rowCount() > 0 ? "EXISTS ✅" : "MISSING ❌") . "</p>";
    }
} catch (Exception $e) {
    echo "<p>Error checking tables: " . $e->getMessage() . "</p>";
}

// 2. Check auction properties
echo "<h2>2. Auction Properties</h2>";
try {
    $stmt = $db->query("SELECT id, title, price FROM properties WHERE property_type = 'auction'");
    $auctionProperties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($auctionProperties) > 0) {
        echo "<p>Found " . count($auctionProperties) . " auction properties:</p>";
        echo "<ul>";
        foreach ($auctionProperties as $property) {
            echo "<li>ID: {$property['id']} - {$property['title']} - €{$property['price']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No auction properties found. You need to set property_type='auction' for some properties.</p>";
    }
} catch (Exception $e) {
    echo "<p>Error checking properties: " . $e->getMessage() . "</p>";
}

// 3. Check active auctions
echo "<h2>3. Active Auctions</h2>";
try {
    $stmt = $db->query("
        SELECT pa.*, p.title 
        FROM property_auctions pa
        JOIN properties p ON pa.property_id = p.id
        WHERE pa.status = 'active'
    ");
    $activeAuctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($activeAuctions) > 0) {
        echo "<p>Found " . count($activeAuctions) . " active auctions:</p>";
        echo "<ul>";
        foreach ($activeAuctions as $auction) {
            echo "<li>ID: {$auction['id']} - Property: {$auction['title']} (#{$auction['property_id']}) - Current price: €{$auction['current_price']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No active auctions found. Use the Auction Setup page to create auctions.</p>";
    }
} catch (Exception $e) {
    echo "<p>Error checking auctions: " . $e->getMessage() . "</p>";
}

// 4. Fix missing tables if needed
echo "<h2>4. Fix Missing Tables</h2>";
echo "<p>If tables are missing above, click the button below to create them:</p>";
echo '<form method="POST">';
echo '<input type="hidden" name="action" value="create_tables">';
echo '<button type="submit" style="padding: 10px 20px; background-color: #dc3545; color: white; border: none; cursor: pointer;">Create Missing Tables</button>';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_tables') {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `property_auctions` (
              `id` int NOT NULL AUTO_INCREMENT,
              `property_id` int NOT NULL,
              `starting_price` decimal(12,2) NOT NULL,
              `current_price` decimal(12,2) NOT NULL,
              `highest_bidder_id` int DEFAULT NULL,
              `status` enum('active','ended','cancelled') NOT NULL DEFAULT 'active',
              `start_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `end_date` datetime DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `property_id` (`property_id`)
            )
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS `auction_participants` (
              `id` int NOT NULL AUTO_INCREMENT,
              `auction_id` int NOT NULL,
              `user_id` int NOT NULL,
              `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `auction_user` (`auction_id`,`user_id`)
            )
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS `auction_bids` (
              `id` int NOT NULL AUTO_INCREMENT,
              `auction_id` int NOT NULL,
              `user_id` int NOT NULL,
              `bid_amount` decimal(12,2) NOT NULL,
              `bid_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `auction_id` (`auction_id`),
              KEY `user_id` (`user_id`)
            )
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS `user_purchases` (
              `id` int NOT NULL AUTO_INCREMENT,
              `user_id` int NOT NULL,
              `property_id` int NOT NULL,
              `auction_id` int DEFAULT NULL,
              `purchase_price` decimal(12,2) NOT NULL,
              `purchase_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
              PRIMARY KEY (`id`),
              KEY `user_id` (`user_id`),
              KEY `property_id` (`property_id`),
              KEY `auction_id` (`auction_id`)
            )
        ");
        
        echo "<p style='color: green;'>Tables created successfully!</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error creating tables: " . $e->getMessage() . "</p>";
    }
}

// Add sample data if needed
echo "<h2>5. Sample Data</h2>";
echo "<p>To add a sample auction for testing, first ensure you have at least one property with property_type='auction'.</p>";
echo '<form method="POST">';
echo '<input type="hidden" name="action" value="add_sample">';
echo '<button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer;">Add Sample Auction</button>';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sample') {
    try {
        // Get first auction property
        $stmt = $db->query("SELECT id, price FROM properties WHERE property_type = 'auction' LIMIT 1");
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($property) {
            // Check if auction exists
            $checkStmt = $db->prepare("SELECT id FROM property_auctions WHERE property_id = ?");
            $checkStmt->execute([$property['id']]);
            
            if ($checkStmt->rowCount() === 0) {
                // Create auction
                $startingPrice = round($property['price'] * 0.8);
                $insertStmt = $db->prepare("
                    INSERT INTO property_auctions 
                    (property_id, starting_price, current_price, status, start_date)
                    VALUES (?, ?, ?, 'active', NOW())
                ");
                $insertStmt->execute([$property['id'], $startingPrice, $startingPrice]);
                
                echo "<p style='color: green;'>Sample auction created for property #{$property['id']} with starting price €{$startingPrice}!</p>";
            } else {
                echo "<p style='color: orange;'>Auction already exists for property #{$property['id']}.</p>";
            }
        } else {
            echo "<p style='color: red;'>No auction properties found. Please change at least one property to type 'auction'.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error creating sample: " . $e->getMessage() . "</p>";
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 10 seconds
    setTimeout(function() {
        location.reload();
    }, 10000);
});
</script>
