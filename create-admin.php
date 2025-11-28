<?php
/**
 * Create Admin User Script
 * 
 * This script creates the first admin user in the database.
 * Run this from command line or browser.
 * 
 * Usage:
 *   php create-admin.php
 *   OR visit: http://localhost:8000/create-admin.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load environment variables
require_once __DIR__ . '/src/config/env.php';
require_once __DIR__ . '/src/config/db.php';
require_once __DIR__ . '/src/models/User.php';

// Check if running from command line or web
$isCli = php_sapi_name() === 'cli';

if ($isCli) {
    echo "=== Create Admin User ===\n\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Create Admin User</title>";
    echo "<style>body{font-family:Arial;max-width:600px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;padding:10px;background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;}";
    echo ".error{color:red;padding:10px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;}";
    echo ".info{color:#856404;padding:10px;background:#fff3cd;border:1px solid #ffeaa7;border-radius:4px;}";
    echo "input,button{padding:8px;margin:5px 0;width:100%;box-sizing:border-box;}</style></head><body>";
    echo "<h1>Create Admin User</h1>";
}

try {
    Env::load();
    
    // Get admin details from command line arguments or form
    if ($isCli) {
        // Command line mode
        $name = $argv[1] ?? 'Admin User';
        $email = $argv[2] ?? 'admin@example.com';
        $password = $argv[3] ?? 'admin123';
        
        if (count($argv) < 4) {
            echo "Usage: php create-admin.php [name] [email] [password]\n";
            echo "Example: php create-admin.php \"Admin User\" admin@example.com admin123\n\n";
            echo "Using defaults:\n";
            echo "  Name: $name\n";
            echo "  Email: $email\n";
            echo "  Password: $password\n\n";
        }
    } else {
        // Web mode - check if form submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? 'Admin User';
            $email = $_POST['email'] ?? 'admin@example.com';
            $password = $_POST['password'] ?? 'admin123';
        } else {
            // Show form
            echo '<form method="POST">';
            echo '<label>Name:</label><input type="text" name="name" value="Admin User" required><br>';
            echo '<label>Email:</label><input type="email" name="email" value="admin@example.com" required><br>';
            echo '<label>Password:</label><input type="password" name="password" value="admin123" required><br>';
            echo '<button type="submit">Create Admin User</button>';
            echo '</form>';
            echo '</body></html>';
            exit;
        }
    }
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        throw new Exception("Name, email, and password are required");
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address");
    }
    
    if (strlen($password) < 6) {
        throw new Exception("Password must be at least 6 characters long");
    }
    
    // Create admin user
    $userModel = new User();
    
    // Check if admin already exists
    $existingUser = $userModel->findByEmail($email);
    if ($existingUser) {
        if ($existingUser['role'] === 'admin') {
            $message = "Admin user with email '$email' already exists!";
            if ($isCli) {
                echo "⚠ WARNING: $message\n";
                echo "User ID: {$existingUser['id']}\n";
                echo "Role: {$existingUser['role']}\n";
            } else {
                echo "<div class='info'>$message<br>User ID: {$existingUser['id']}, Role: {$existingUser['role']}</div>";
            }
            exit(0);
        } else {
            // Update existing user to admin
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->execute([$existingUser['id']]);
            
            $message = "User '$email' updated to admin role!";
            if ($isCli) {
                echo "✓ $message\n";
                echo "User ID: {$existingUser['id']}\n";
            } else {
                echo "<div class='success'>$message<br>User ID: {$existingUser['id']}</div>";
            }
            exit(0);
        }
    }
    
    // Create new admin user
    $userId = $userModel->createUser([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => 'admin', // Set as admin
    ]);
    
    // Get the created user directly from database
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($isCli) {
        echo "✓ Admin user created successfully!\n\n";
        echo "User Details:\n";
        echo "  ID: {$user['id']}\n";
        echo "  Name: {$user['name']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Role: {$user['role']}\n";
        echo "  Created: {$user['created_at']}\n\n";
        echo "You can now login with:\n";
        echo "  Email: $email\n";
        echo "  Password: $password\n";
    } else {
        echo "<div class='success'>";
        echo "<h3>✓ Admin user created successfully!</h3>";
        echo "<p><strong>User Details:</strong></p>";
        echo "<ul>";
        echo "<li>ID: {$user['id']}</li>";
        echo "<li>Name: {$user['name']}</li>";
        echo "<li>Email: {$user['email']}</li>";
        echo "<li>Role: {$user['role']}</li>";
        echo "<li>Created: {$user['created_at']}</li>";
        echo "</ul>";
        echo "<p><strong>Login Credentials:</strong></p>";
        echo "<ul>";
        echo "<li>Email: <code>$email</code></li>";
        echo "<li>Password: <code>$password</code></li>";
        echo "</ul>";
        echo "</div>";
        echo "<p><a href='http://localhost:8000/api/auth/login'>Test Login</a></p>";
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    if ($isCli) {
        echo "✗ Error: $error\n";
        exit(1);
    } else {
        echo "<div class='error'><strong>Error:</strong> $error</div>";
    }
}

if (!$isCli) {
    echo "</body></html>";
}

