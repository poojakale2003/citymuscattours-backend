<?php
/**
 * Debug Token Validation
 * This script helps debug why tokens aren't being accepted
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/env.php';
require_once __DIR__ . '/src/utils/token.php';
require_once __DIR__ . '/src/config/db.php';

try {
    Env::load();
    $config = Env::get('jwt');
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to load environment: ' . $e->getMessage()]));
}

// Get token from Authorization header
$token = null;
$authHeader = null;

// Try multiple ways to get the Authorization header
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if ($headers) {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
    }
}

// Fallback to $_SERVER
if (!$authHeader) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
}

// Also check query parameter for testing
if (!$authHeader && isset($_GET['token'])) {
    $authHeader = 'Bearer ' . $_GET['token'];
}

$debug = [
    'jwt_secret_set' => !empty($config['secret']),
    'jwt_secret_length' => strlen($config['secret'] ?? ''),
    'jwt_refresh_secret_set' => !empty($config['refreshSecret']),
    'authorization_header_received' => $authHeader !== null,
    'authorization_header_value' => $authHeader ? (strlen($authHeader) > 50 ? substr($authHeader, 0, 50) . '...' : $authHeader) : null,
];

if ($authHeader) {
    // Extract token
    if (stripos($authHeader, 'Bearer ') === 0) {
        $token = trim(substr($authHeader, 7));
    } else {
        $token = trim($authHeader);
    }
    
    $debug['token_extracted'] = $token !== null;
    $debug['token_length'] = strlen($token);
    $debug['token_preview'] = substr($token, 0, 30) . '...';
    
    if ($token) {
        // Try to decode as access token
        try {
            // "use" statements cannot be inside functions/blocks, so we must assume
            // Firebase\JWT\JWT and Firebase\JWT\Key have been imported at the top of the file.
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($config['secret'], 'HS256'));
            $debug['token_type'] = 'access';
            $debug['token_valid'] = true;
            $debug['decoded'] = [
                'user_id' => $decoded->sub,
                'role' => $decoded->role,
                'issued_at' => date('Y-m-d H:i:s', $decoded->iat),
                'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
                'is_expired' => $decoded->exp < time(),
                'expires_in_seconds' => $decoded->exp - time(),
            ];
            
            // Check user in database
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
                $stmt->execute([$decoded->sub]);
                $user = $stmt->fetch();
                $debug['user_exists'] = $user !== false;
                $debug['user'] = $user;
            } catch (Exception $e) {
                $debug['user_check_error'] = $e->getMessage();
            }
            
        } catch (Exception $e) {
            $debug['access_token_error'] = $e->getMessage();
            $debug['access_token_error_type'] = get_class($e);
            
            // Try as refresh token
            try {
                $decoded = JWT::decode($token, new Key($config['refreshSecret'], 'HS256'));
                $debug['token_type'] = 'refresh';
                $debug['token_valid'] = true;
                $debug['decoded'] = [
                    'user_id' => $decoded->sub,
                    'role' => $decoded->role,
                    'issued_at' => date('Y-m-d H:i:s', $decoded->iat),
                    'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
                    'is_expired' => $decoded->exp < time(),
                ];
            } catch (Exception $e2) {
                $debug['refresh_token_error'] = $e2->getMessage();
                $debug['refresh_token_error_type'] = get_class($e2);
                $debug['token_valid'] = false;
            }
        }
    }
} else {
    $debug['error'] = 'No Authorization header found. Send it as: Authorization: Bearer YOUR_TOKEN';
    $debug['all_headers'] = function_exists('getallheaders') ? getallheaders() : [];
    $debug['server_headers'] = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0 || strpos($key, 'REDIRECT_HTTP_') === 0) {
            $debug['server_headers'][$key] = $value;
        }
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT);

