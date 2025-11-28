<?php
/**
 * Migration script to add is_archived column to packages table
 * Run this file directly: php add-archive-column.php
 */

require_once __DIR__ . '/src/config/db.php';

try {
    $db = getDB();
    
    // Check if column already exists
    $checkColumn = $db->query("SHOW COLUMNS FROM packages LIKE 'is_archived'");
    if ($checkColumn->rowCount() > 0) {
        echo "Column 'is_archived' already exists. No changes needed.\n";
        exit(0);
    }
    
    // Add the column
    $db->exec("ALTER TABLE packages ADD COLUMN is_archived BOOLEAN DEFAULT FALSE");
    echo "✓ Added 'is_archived' column to packages table\n";
    
    // Add index
    $db->exec("ALTER TABLE packages ADD INDEX idx_archived (is_archived)");
    echo "✓ Added index on 'is_archived' column\n";
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

