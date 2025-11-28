<?php
/**
 * Test script to check archived packages
 * Run: php test-archived-packages.php
 */

require_once __DIR__ . '/src/config/db.php';

try {
    $db = getDB();
    
    // Check if column exists
    $checkColumn = $db->query("SHOW COLUMNS FROM packages LIKE 'is_archived'");
    if ($checkColumn->rowCount() === 0) {
        echo "❌ ERROR: Column 'is_archived' does not exist in packages table!\n";
        echo "Please run the migration:\n";
        echo "ALTER TABLE packages ADD COLUMN is_archived BOOLEAN DEFAULT FALSE;\n";
        echo "ALTER TABLE packages ADD INDEX idx_archived (is_archived);\n";
        exit(1);
    }
    echo "✓ Column 'is_archived' exists\n\n";
    
    // Get all packages with their archive status
    $stmt = $db->query("SELECT id, name, is_archived FROM packages ORDER BY id");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total packages: " . count($packages) . "\n\n";
    
    $archived = [];
    $notArchived = [];
    
    foreach ($packages as $pkg) {
        $isArchived = $pkg['is_archived'] == 1 || $pkg['is_archived'] === '1' || $pkg['is_archived'] === true;
        if ($isArchived) {
            $archived[] = $pkg;
        } else {
            $notArchived[] = $pkg;
        }
    }
    
    echo "Archived packages (" . count($archived) . "):\n";
    if (empty($archived)) {
        echo "  (none)\n";
    } else {
        foreach ($archived as $pkg) {
            echo "  - ID: {$pkg['id']}, Name: {$pkg['name']}, is_archived: {$pkg['is_archived']} (type: " . gettype($pkg['is_archived']) . ")\n";
        }
    }
    
    echo "\nNon-archived packages (" . count($notArchived) . "):\n";
    if (empty($notArchived)) {
        echo "  (none)\n";
    } else {
        foreach ($notArchived as $pkg) {
            echo "  - ID: {$pkg['id']}, Name: {$pkg['name']}, is_archived: {$pkg['is_archived']} (type: " . gettype($pkg['is_archived']) . ")\n";
        }
    }
    
    // Test the query that should be used
    echo "\n\nTesting query: SELECT * FROM packages WHERE is_archived = 1\n";
    $stmt = $db->query("SELECT id, name, is_archived FROM packages WHERE is_archived = 1");
    $archivedQuery = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Results: " . count($archivedQuery) . " packages\n";
    foreach ($archivedQuery as $pkg) {
        echo "  - ID: {$pkg['id']}, Name: {$pkg['name']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}

