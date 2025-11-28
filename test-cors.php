<?php
/**
 * CORS Test Script
 * Use this to test CORS configuration
 */

header('Content-Type: application/json');

// Get the origin from request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Load environment configuration
require_once __DIR__ . '/src/config/env.php';
try {
    Env::load();
    $clientUrl = Env::get('clientUrl');
    $appEnv = Env::get('nodeEnv') ?? 'development';
    
    // For development, allow common frontend ports
    if ($appEnv === 'development') {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:5173',
            'http://localhost:4200',
            'http://localhost:5174',
            $clientUrl
        ];
    } else {
        $allowedOrigins = [$clientUrl];
    }
    
    // Set CORS headers
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header("Access-Control-Allow-Origin: {$clientUrl}");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    
    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'CORS test successful',
        'requestOrigin' => $origin,
        'allowedOrigins' => $allowedOrigins,
        'clientUrl' => $clientUrl,
        'environment' => $appEnv,
        'originAllowed' => in_array($origin, $allowedOrigins) || $origin === $clientUrl,
        'testUrl' => 'http://localhost/php-backend/api/auth/login'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'error' => 'Failed to load environment configuration'
    ]);
}

