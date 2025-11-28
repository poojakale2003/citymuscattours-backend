<?php
/**
 * Test Token Request
 * This endpoint shows what headers and tokens are being received
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get all headers
$headers = [];
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    if ($allHeaders) {
        foreach ($allHeaders as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
    }
}

// Also check $_SERVER
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerKey = str_replace('_', '-', substr($key, 5));
        $headers[strtolower($headerKey)] = $value;
    } elseif (strpos($key, 'REDIRECT_HTTP_') === 0) {
        $headerKey = str_replace('_', '-', substr($key, 14));
        $headers[strtolower($headerKey)] = $value;
    }
}

// Extract Authorization header
$authHeader = $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

$response = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s'),
    'headers_received' => array_keys($headers),
    'authorization_header_found' => $authHeader !== null,
    'authorization_value' => $authHeader ? substr($authHeader, 0, 50) . '...' : null,
    'token_extracted' => false,
    'token_preview' => null,
];

// Extract token
$token = null;
if ($authHeader) {
    if (stripos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        $response['token_extracted'] = true;
        $response['token_preview'] = substr($token, 0, 30) . '...';
        $response['token_length'] = strlen($token);
    } else {
        $response['error'] = 'Authorization header does not start with "Bearer "';
        $response['authorization_preview'] = substr($authHeader, 0, 50);
    }
} else {
    $response['error'] = 'No Authorization header found';
    $response['instructions'] = 'Send Authorization header as: Authorization: Bearer YOUR_ACCESS_TOKEN';
}

echo json_encode($response, JSON_PRETTY_PRINT);

