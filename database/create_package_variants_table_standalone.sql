-- Package variants/options table
-- This table stores different booking options for each package
-- Run this SQL in phpMyAdmin or MySQL command line

USE tour_travels;

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
    perks TEXT, -- JSON array of perks
    price_modifier DECIMAL(5, 2) DEFAULT 0.00, -- Percentage modifier (e.g., 35 for +35%, -20 for -20%)
    base_price_override DECIMAL(10, 2) NULL, -- Override base price if set
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


