<?php
/**
 * Test Authorization Header Reception
 * This script helps debug why Authorization headers aren't being received
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

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

// Get from $_SERVER as fallback
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerKey = str_replace('_', '-', substr($key, 5));
        $headers[strtolower($headerKey)] = $value;
    } elseif (strpos($key, 'REDIRECT_HTTP_') === 0) {
        $headerKey = str_replace('_', '-', substr($key, 14));
        $headers[strtolower($headerKey)] = $value;
    }
}

// Also check for Authorization directly
$directAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

$debug = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'getallheaders_exists' => function_exists('getallheaders'),
    'all_headers' => $headers,
    'authorization_header_found' => isset($headers['authorization']) || $directAuth !== null,
    'authorization_value' => $headers['authorization'] ?? $directAuth ?? null,
    'authorization_preview' => ($headers['authorization'] ?? $directAuth ?? '') ? substr($headers['authorization'] ?? $directAuth ?? '', 0, 50) . '...' : null,
];

// Extract token if found
$token = null;
$authHeader = $headers['authorization'] ?? $directAuth ?? null;
if ($authHeader) {
    if (stripos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        $debug['token_extracted'] = true;
        $debug['token_length'] = strlen($token);
        $debug['token_preview'] = substr($token, 0, 30) . '...';
    } else {
        $debug['token_extraction_failed'] = 'Header does not start with "Bearer "';
    }
} else {
    $debug['token_extraction_failed'] = 'No Authorization header found';
}

// Show all $_SERVER keys related to headers
$serverHeaders = [];
foreach ($_SERVER as $key => $value) {
    if (stripos($key, 'HTTP') !== false || stripos($key, 'AUTH') !== false) {
        $serverHeaders[$key] = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
    }
}
$debug['server_header_keys'] = $serverHeaders;

echo json_encode($debug, JSON_PRETTY_PRINT);

