<?php
/**
 * Debug Refresh Token
 * This script helps debug refresh token issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/env.php';
require_once __DIR__ . '/src/utils/token.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/config/db.php';

try {
    Env::load();
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to load environment: ' . $e->getMessage()]));
}

$debug = [];

// Get refresh token from request
$token = null;
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

if (isset($data['refreshToken'])) {
    $token = $data['refreshToken'];
} elseif (isset($data['refresh_token'])) {
    $token = $data['refresh_token'];
} elseif (isset($_COOKIE['refreshToken'])) {
    $token = $_COOKIE['refreshToken'];
}

if (!$token) {
    $debug['error'] = 'No refresh token found. Send it in body as: {"refreshToken": "your_token"}';
    $debug['received_data'] = $data;
    echo json_encode($debug, JSON_PRETTY_PRINT);
    exit;
}

$debug['token_received'] = true;
$debug['token_preview'] = substr($token, 0, 30) . '...';
$debug['token_length'] = strlen($token);

// Check JWT configuration
try {
    $config = Env::get('jwt');
    $debug['jwt_config'] = [
        'has_secret' => !empty($config['secret']),
        'has_refresh_secret' => !empty($config['refreshSecret']),
        'access_expiry' => $config['accessExpiry'] ?? 'not set',
        'refresh_expiry' => $config['refreshExpiry'] ?? 'not set',
    ];
} catch (Exception $e) {
    $debug['jwt_config_error'] = $e->getMessage();
}

// Try to verify the refresh token
try {
    $decoded = verifyRefreshToken($token);
    $debug['token_valid'] = true;
    $debug['token_type'] = 'refresh';
    $debug['decoded'] = [
        'user_id' => $decoded->sub,
        'role' => $decoded->role,
        'issued_at' => date('Y-m-d H:i:s', $decoded->iat),
        'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
        'expires_in' => $decoded->exp - time() . ' seconds',
        'is_expired' => $decoded->exp < time(),
    ];
    
    // Check if token exists in database
    $userModel = new User();
    $tokenRecord = $userModel->findRefreshToken($decoded->sub, $token);
    
    $debug['token_in_database'] = $tokenRecord !== false;
    
    if ($tokenRecord) {
        $debug['token_record'] = [
            'id' => $tokenRecord['id'] ?? null,
            'user_id' => $tokenRecord['user_id'] ?? null,
            'token_hash' => isset($tokenRecord['token_hash']) ? substr($tokenRecord['token_hash'], 0, 30) . '...' : null,
            'expires_at' => $tokenRecord['expires_at'] ?? null,
            'is_expired_in_db' => isset($tokenRecord['expires_at']) && strtotime($tokenRecord['expires_at']) < time(),
        ];
    } else {
        // Check if there are any tokens for this user
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM refresh_tokens WHERE user_id = ?");
        $stmt->execute([$decoded->sub]);
        $count = $stmt->fetch()['count'];
        $debug['user_token_count'] = $count;
    }
    
    // Get user info
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$decoded->sub]);
    $user = $stmt->fetch();
    $debug['user'] = $user;
    
} catch (Exception $e) {
    $debug['token_valid'] = false;
    $debug['token_type'] = 'unknown';
    $debug['error'] = $e->getMessage();
    $debug['error_class'] = get_class($e);
    
    // Try to decode as access token to see if wrong token type was sent
    try {
        require_once __DIR__ . '/src/utils/token.php';
        $config = Env::get('jwt');
        $decoded = JWT::decode($token, new Key($config['secret'], 'HS256'));
        $debug['note'] = 'This appears to be an ACCESS token, not a REFRESH token';
        $debug['decoded_as_access'] = [
            'user_id' => $decoded->sub,
            'role' => $decoded->role,
        ];
    } catch (Exception $e2) {
        // Not an access token either
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT);

