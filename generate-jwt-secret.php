<?php
/**
 * Generate Secure JWT Secret
 * This script generates a secure random JWT secret key
 */

function generateSecureSecret($length = 64) {
    // Generate cryptographically secure random bytes
    $bytes = random_bytes($length);
    // Convert to base64 and make it URL-safe
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function generateJWTSecret($length = 32) {
    // Alternative: Generate hex string
    $bytes = random_bytes($length);
    return bin2hex($bytes);
}

echo "=== JWT Secret Generator ===\n\n";

// Generate both types of secrets
$base64Secret = generateSecureSecret(32);
$hexSecret = generateJWTSecret(32);

echo "Generated JWT Secrets:\n\n";
echo "Base64 (URL-safe):\n";
echo $base64Secret . "\n\n";
echo "Hexadecimal:\n";
echo $hexSecret . "\n\n";

echo "You can use either one. The base64 version is URL-safe and slightly shorter.\n";
echo "Copy one of these values to your .env file as JWT_SECRET\n\n";

// Optionally update .env file
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    
    // Check if JWT_SECRET needs to be updated
    if (preg_match('/^JWT_SECRET=your-secret-key-change-this-in-production/m', $envContent) || 
        preg_match('/^JWT_SECRET=\s*$/m', $envContent)) {
        
        echo "Would you like to update .env file automatically? (y/n): ";
        // For non-interactive use, we'll just show what to do
        echo "\n\nTo update automatically, run:\n";
        echo "php -r \"require 'generate-jwt-secret.php'; updateEnvFile();\"\n\n";
        
        // Show what needs to be changed
        echo "Or manually update .env file:\n";
        echo "JWT_SECRET=" . $base64Secret . "\n";
    }
}

function updateEnvFile() {
    $secret = generateSecureSecret(32);
    $envFile = '.env';
    
    if (!file_exists($envFile)) {
        echo "Error: .env file not found\n";
        return;
    }
    
    $content = file_get_contents($envFile);
    
    // Replace JWT_SECRET
    if (preg_match('/^JWT_SECRET=.*$/m', $content)) {
        $content = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $secret, $content);
    } else {
        // Add JWT_SECRET if not found
        $content .= "\nJWT_SECRET=" . $secret . "\n";
    }
    
    // Also set JWT_REFRESH_SECRET if it's empty
    if (preg_match('/^JWT_REFRESH_SECRET=\s*$/m', $content)) {
        $refreshSecret = generateSecureSecret(32);
        $content = preg_replace('/^JWT_REFRESH_SECRET=\s*$/m', 'JWT_REFRESH_SECRET=' . $refreshSecret, $content);
    }
    
    file_put_contents($envFile, $content);
    
    echo "✓ Updated .env file with new JWT secrets\n";
    echo "JWT_SECRET=" . substr($secret, 0, 10) . "...\n";
}

// If called directly from command line, show secrets
if (php_sapi_name() === 'cli') {
    echo "\nTo automatically update .env file, call: updateEnvFile();\n";
}

