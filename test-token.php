<?php
/**
 * Test Token Validation Script
 * This helps debug token validation issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/env.php';
require_once __DIR__ . '/src/utils/token.php';
require_once __DIR__ . '/src/config/db.php';
require_once __DIR__ . '/src/models/User.php';

header('Content-Type: application/json');

try {
    Env::load();
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to load environment: ' . $e->getMessage()]));
}

// Get token from Authorization header or query parameter
$token = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
}

if (!$token) {
    die(json_encode(['error' => 'No token provided. Add ?token=YOUR_TOKEN or Authorization header']));
}

$result = [
    'token_provided' => true,
    'token_length' => strlen($token),
    'token_preview' => substr($token, 0, 20) . '...',
];

// Try to verify as access token
try {
    $decoded = verifyAccessToken($token);
    $result['token_type'] = 'access';
    $result['decoded'] = [
        'user_id' => $decoded->sub,
        'role' => $decoded->role,
        'issued_at' => date('Y-m-d H:i:s', $decoded->iat),
        'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
        'expires_in' => $decoded->exp - time() . ' seconds',
    ];
    
    // Check if user exists
    $userModel = new User();
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$decoded->sub]);
    $user = $stmt->fetch();
    
    if ($user) {
        $result['user'] = $user;
        $result['is_admin'] = $user['role'] === 'admin';
    } else {
        $result['user'] = null;
        $result['error'] = 'User not found';
    }
} catch (Exception $e) {
    $result['access_token_error'] = $e->getMessage();
    
    // Try as refresh token
    try {
        $decoded = verifyRefreshToken($token);
        $result['token_type'] = 'refresh';
        $result['decoded'] = [
            'user_id' => $decoded->sub,
            'role' => $decoded->role,
            'issued_at' => date('Y-m-d H:i:s', $decoded->iat),
            'expires_at' => date('Y-m-d H:i:s', $decoded->exp),
            'expires_in' => $decoded->exp - time() . ' seconds',
        ];
        
        // Check if refresh token exists in database
        $userModel = new User();
        $tokenRecord = $userModel->findRefreshToken($decoded->sub, $token);
        $result['token_in_database'] = $tokenRecord !== false;
        
        // Get user info
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$decoded->sub]);
        $user = $stmt->fetch();
        $result['user'] = $user;
        
    } catch (Exception $e2) {
        $result['refresh_token_error'] = $e2->getMessage();
        $result['token_type'] = 'unknown';
        $result['error'] = 'Token is neither a valid access nor refresh token';
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);

