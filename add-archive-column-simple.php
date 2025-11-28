<?php
/**
 * Simple migration script to add is_archived column
 * Update the database credentials below and run: php add-archive-column-simple.php
 */

// Update these with your database credentials
$host = 'localhost';
$dbname = 'tour_travels';
$username = 'root';  // Change if different
$password = '';      // Change if different

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM packages LIKE 'is_archived'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Column 'is_archived' already exists. No changes needed.\n";
        exit(0);
    }
    
    // Add the column
    $pdo->exec("ALTER TABLE packages ADD COLUMN is_archived BOOLEAN DEFAULT FALSE");
    echo "✓ Added 'is_archived' column to packages table\n";
    
    // Add index
    $pdo->exec("ALTER TABLE packages ADD INDEX idx_archived (is_archived)");
    echo "✓ Added index on 'is_archived' column\n";
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

