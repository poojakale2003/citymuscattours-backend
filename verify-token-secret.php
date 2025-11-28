<?php
/**
 * Verify Token Secret
 * This script helps diagnose token signature issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/env.php';
require_once __DIR__ . '/src/utils/token.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    Env::load();
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to load environment: ' . $e->getMessage()]));
}

$config = Env::get('jwt');
$secret = $config['secret'] ?? null;
$refreshSecret = $config['refreshSecret'] ?? $config['secret'] ?? null;

$result = [
    'current_config' => [
        'jwt_secret_set' => !empty($secret),
        'jwt_secret_preview' => $secret ? substr($secret, 0, 10) . '...' : null,
        'jwt_refresh_secret_set' => !empty($refreshSecret),
        'jwt_refresh_secret_preview' => $refreshSecret ? substr($refreshSecret, 0, 10) . '...' : null,
        'refresh_secret_source' => !empty($config['refreshSecret']) ? 'JWT_REFRESH_SECRET' : 'JWT_SECRET (fallback)',
    ],
    'note' => 'If you just changed JWT_SECRET, you need to login again to get new tokens signed with the new secret.',
    'solution' => [
        'step1' => 'Login again at POST /api/auth/login to get new tokens',
        'step2' => 'Use the new refresh token to refresh access tokens',
        'step3' => 'Old tokens signed with the previous secret will not work',
    ],
];

// Get token from request body if provided
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $data['token'] ?? $_GET['token'] ?? null;

if ($token) {
    $result['token_provided'] = true;
    $result['token_preview'] = substr($token, 0, 30) . '...';
    
    // Try to decode with current secret
    try {
        // Try with access token secret
        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $result['token_validation'] = [
                'type' => 'access_token',
                'valid' => true,
                'signed_with' => 'JWT_SECRET',
            ];
        } catch (Exception $e1) {
            // Try with refresh token secret
            try {
                $decoded = JWT::decode($token, new Key($refreshSecret, 'HS256'));
                $result['token_validation'] = [
                    'type' => 'refresh_token',
                    'valid' => true,
                    'signed_with' => !empty($config['refreshSecret']) ? 'JWT_REFRESH_SECRET' : 'JWT_SECRET (fallback)',
                ];
            } catch (Exception $e2) {
                $result['token_validation'] = [
                    'type' => 'unknown',
                    'valid' => false,
                    'error' => 'Token signature invalid - token was signed with a different secret',
                    'access_token_error' => $e1->getMessage(),
                    'refresh_token_error' => $e2->getMessage(),
                ];
                $result['solution'] = [
                    'action' => 'Login again to get new tokens signed with current secret',
                    'reason' => 'This token was created with a different JWT_SECRET than what is currently configured',
                ];
            }
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
} else {
    $result['token_provided'] = false;
    $result['instructions'] = 'Send a token in the request body as {"token": "your_token"} or as query parameter ?token=your_token';
}

echo json_encode($result, JSON_PRETTY_PRINT);

