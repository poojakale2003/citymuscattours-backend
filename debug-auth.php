<?php
/**
 * Debug Authentication Script
 * This script helps debug authentication issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Get all headers
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
}

$debug = [
    'getallheaders_exists' => function_exists('getallheaders'),
    'headers_from_getallheaders' => $headers,
    'server_auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'server_redirect_auth' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
    'all_server_headers' => [],
];

// Get all HTTP_ prefixed headers
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0 || strpos($key, 'REDIRECT_HTTP_') === 0) {
        $debug['all_server_headers'][$key] = $value;
    }
}

// Try to extract Authorization header
$authHeader = null;
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
} elseif (isset($headers['authorization'])) {
    $authHeader = $headers['authorization'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$debug['authorization_header_found'] = $authHeader !== null;
$debug['authorization_header_value'] = $authHeader ? (strlen($authHeader) > 50 ? substr($authHeader, 0, 50) . '...' : $authHeader) : null;

if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
    $debug['token_extracted'] = true;
    $debug['token_length'] = strlen($token);
    $debug['token_preview'] = substr($token, 0, 20) . '...';
} else {
    $debug['token_extracted'] = false;
}

echo json_encode($debug, JSON_PRETTY_PRINT);

