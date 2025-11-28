<?php
/**
 * Migration script to create package_variants table
 * Run this script once to set up the variants table
 */

require_once __DIR__ . '/src/config/db.php';
require_once __DIR__ . '/src/config/env.php';

try {
    Env::load();
    $db = getDB();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS package_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        package_id INT NOT NULL,
        variant_id VARCHAR(100) NOT NULL,
        label VARCHAR(255) NOT NULL,
        subtitle VARCHAR(500),
        description TEXT,
        language VARCHAR(255),
        start_time VARCHAR(50),
        meeting_point VARCHAR(500),
        perks TEXT,
        price_modifier DECIMAL(5, 2) DEFAULT 0.00,
        base_price_override DECIMAL(10, 2) NULL,
        rating DECIMAL(3, 2),
        reviews INT DEFAULT 0,
        cancellation_policy VARCHAR(255),
        pickup_included BOOLEAN DEFAULT TRUE,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
        UNIQUE KEY unique_package_variant (package_id, variant_id),
        INDEX idx_package (package_id),
        INDEX idx_active (is_active),
        INDEX idx_display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    
    echo "✅ Package variants table created successfully!\n";
    echo "You can now add variants for your packages through the admin panel or directly in the database.\n";
    
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}


