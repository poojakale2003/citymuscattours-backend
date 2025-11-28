<?php
/**
 * Setup JWT Secret
 * This script generates and sets JWT secrets in .env file
 */

function generateSecureSecret($length = 32) {
    $bytes = random_bytes($length);
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

echo "=== JWT Secret Setup ===\n\n";

$envFile = '.env';

if (!file_exists($envFile)) {
    echo "Error: .env file not found!\n";
    echo "Please run setup.ps1 first to create .env file\n";
    exit(1);
}

echo "Found .env file\n\n";

// Read .env file
$content = file_get_contents($envFile);
$lines = explode("\n", $content);

// Check current JWT_SECRET
$needsUpdate = false;
$jwtSecretSet = false;
$jwtRefreshSecretSet = false;

foreach ($lines as $line) {
    if (preg_match('/^JWT_SECRET=(.*)$/', $line, $matches)) {
        $value = trim($matches[1]);
        if ($value === 'your-secret-key-change-this-in-production' || $value === '') {
            echo "Current JWT_SECRET is placeholder or empty\n";
            $needsUpdate = true;
        } else {
            echo "Current JWT_SECRET is already set\n";
            echo "Secret preview: " . substr($value, 0, 10) . "...\n";
            $jwtSecretSet = true;
        }
    }
    
    if (preg_match('/^JWT_REFRESH_SECRET=(.*)$/', $line, $matches)) {
        $value = trim($matches[1]);
        if ($value !== '') {
            $jwtRefreshSecretSet = true;
        }
    }
}

if (!$jwtSecretSet) {
    echo "\nGenerating secure JWT secrets...\n";
    
    $jwtSecret = generateSecureSecret(32);
    $jwtRefreshSecret = generateSecureSecret(32);
    
    echo "Generated secrets:\n";
    echo "JWT_SECRET: " . substr($jwtSecret, 0, 10) . "...\n";
    echo "JWT_REFRESH_SECRET: " . substr($jwtRefreshSecret, 0, 10) . "...\n\n";
    
    // Update .env file
    $newLines = [];
    foreach ($lines as $line) {
        if (preg_match('/^JWT_SECRET=/', $line)) {
            $newLines[] = "JWT_SECRET=$jwtSecret";
        } elseif (preg_match('/^JWT_REFRESH_SECRET=/', $line)) {
            $newLines[] = "JWT_REFRESH_SECRET=$jwtRefreshSecret";
        } else {
            $newLines[] = $line;
        }
    }
    
    $newContent = implode("\n", $newLines);
    file_put_contents($envFile, $newContent);
    
    echo "✓ Updated .env file with new JWT secrets\n";
    echo "\nYour JWT secrets have been configured:\n";
    echo "  JWT_SECRET: Set (32-byte base64 encoded)\n";
    echo "  JWT_REFRESH_SECRET: Set (32-byte base64 encoded)\n";
    echo "\nThe refresh secret will use JWT_SECRET as fallback if needed.\n";
} else {
    echo "\nNo changes needed. JWT_SECRET is already configured.\n";
}

echo "\n=== Setup Complete ===\n";
echo "\nNext steps:\n";
echo "1. Verify JWT config: php check-jwt-config.php\n";
echo "2. Test login: POST http://localhost:8000/api/auth/login\n";
echo "\n";

