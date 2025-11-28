<?php

/**
 * Database Connection Test Script
 * 
 * This script tests the database connection configuration.
 * Run this from the command line or browser to verify database connectivity.
 * 
 * Usage:
 *   php test-db-connection.php
 *   OR visit: http://localhost/php-backend/test-db-connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Database Connection Test ===\n\n";

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✓ Composer autoloader loaded\n";
} else {
    echo "✗ Composer autoloader not found. Run 'composer install' first.\n";
    exit(1);
}

// Load environment variables
require_once __DIR__ . '/src/config/env.php';

echo "\n1. Testing Environment Configuration...\n";
try {
    $config = Env::load();
    echo "✓ Environment configuration loaded\n";
    
    // Check required variables
    $requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'JWT_SECRET', 'CLIENT_URL'];
    $missing = [];
    
    foreach ($requiredVars as $var) {
        if (!isset($_ENV[$var]) && !getenv($var)) {
            $missing[] = $var;
        }
    }
    
    if (!empty($missing)) {
        echo "✗ Missing required environment variables: " . implode(', ', $missing) . "\n";
        echo "  Please check your .env file\n";
        exit(1);
    }
    
    echo "✓ All required environment variables are set\n";
    
    // Display database config (without password)
    $dbConfig = Env::get('db');
    echo "  Database Host: " . $dbConfig['host'] . "\n";
    echo "  Database Port: " . $dbConfig['port'] . "\n";
    echo "  Database Name: " . $dbConfig['name'] . "\n";
    echo "  Database User: " . $dbConfig['user'] . "\n";
    echo "  Database Password: " . (empty($dbConfig['pass']) ? '(empty)' : '***') . "\n";
    
} catch (Exception $e) {
    echo "✗ Environment configuration error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n2. Testing Database Connection...\n";
try {
    require_once __DIR__ . '/src/config/db.php';
    
    $db = getDB();
    echo "✓ Database connection object created\n";
    
    // Test connection with a simple query
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        echo "✓ Database connection successful!\n";
        echo "  Connection test query executed successfully\n";
    } else {
        echo "✗ Database connection test query failed\n";
        exit(1);
    }
    
    // Get database version
    $stmt = $db->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "  MySQL Version: " . $version['version'] . "\n";
    
    // Check if database exists and has tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Tables found: " . count($tables) . "\n";
    
    if (count($tables) > 0) {
        echo "  Table list: " . implode(', ', array_slice($tables, 0, 10));
        if (count($tables) > 10) {
            echo " ... and " . (count($tables) - 10) . " more";
        }
        echo "\n";
    } else {
        echo "  ⚠ Warning: No tables found in database. You may need to import schema.sql\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed!\n";
    echo "  Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting tips:\n";
    echo "  1. Check if MySQL service is running\n";
    echo "  2. Verify database credentials in .env file\n";
    echo "  3. Ensure the database '{$dbConfig['name']}' exists\n";
    echo "  4. Check MySQL user permissions\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== All Tests Passed! ===\n";
echo "Database is properly configured and connected.\n";

