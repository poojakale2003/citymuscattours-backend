<?php
/**
 * Debug Package Creation Authentication
 * This mimics the package creation endpoint to see what's happening with auth
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/env.php';
require_once __DIR__ . '/src/middleware/authMiddleware.php';
require_once __DIR__ . '/src/routes/router.php';

try {
    Env::load();
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to load environment: ' . $e->getMessage()]));
}

// Simulate the request structure that router would create
$headers = [];
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    if ($allHeaders) {
        foreach ($allHeaders as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
    }
}

// Fallback to $_SERVER
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerKey = str_replace('_', '-', substr($key, 5));
        $headers[strtolower($headerKey)] = $value;
    } elseif (strpos($key, 'REDIRECT_HTTP_') === 0) {
        $headerKey = str_replace('_', '-', substr($key, 14));
        $headers[strtolower($headerKey)] = $value;
    }
}

$req = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
];

$debug = [
    'headers_received' => $headers,
    'authorization_header' => $headers['authorization'] ?? 'not found',
    'authorization_keys' => array_keys(array_filter($headers, function($k) {
        return stripos($k, 'auth') !== false;
    }, ARRAY_FILTER_USE_KEY)),
];

// Try to authenticate
try {
    $res = [];
    $next = function($req, $res) {
        return ['authenticated' => true, 'user' => $req['user'] ?? null];
    };
    
    authenticate($req, $res, $next);
    $debug['auth_status'] = 'SUCCESS';
    $debug['user'] = $req['user'] ?? null;
} catch (Exception $e) {
    $debug['auth_status'] = 'FAILED';
    $debug['auth_error'] = $e->getMessage();
    $debug['error_class'] = get_class($e);
}

// Extract token info
$authHeader = $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
if ($authHeader) {
    $debug['token_info'] = [
        'header_found' => true,
        'header_value_preview' => substr($authHeader, 0, 50) . '...',
        'starts_with_bearer' => stripos($authHeader, 'Bearer ') === 0,
    ];
    
    if (stripos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        $debug['token_info']['token_extracted'] = true;
        $debug['token_info']['token_length'] = strlen($token);
        
        // Try to validate token
        try {
            require_once __DIR__ . '/src/utils/token.php';
            $decoded = verifyAccessToken($token);
            $debug['token_info']['token_valid'] = true;
            $debug['token_info']['decoded'] = [
                'user_id' => $decoded->sub ?? null,
                'role' => $decoded->role ?? null,
                'expires_at' => isset($decoded->exp) ? date('Y-m-d H:i:s', $decoded->exp) : null,
            ];
        } catch (Exception $e) {
            $debug['token_info']['token_valid'] = false;
            $debug['token_info']['validation_error'] = $e->getMessage();
        }
    }
} else {
    $debug['token_info'] = ['header_found' => false];
}

echo json_encode($debug, JSON_PRETTY_PRINT);

