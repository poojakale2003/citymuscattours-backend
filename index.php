<?php

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load environment variables
require_once __DIR__ . '/src/config/env.php';
try {
    Env::load();
    
    // Verify database connection on startup
    require_once __DIR__ . '/src/config/db.php';
    try {
        $db = getDB();
        $db->query("SELECT 1");
        error_log("Database connection successful");
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("Environment loading error: " . $e->getMessage());
}

// Set CORS headers
$clientUrl = Env::get('clientUrl');
$appEnv = Env::get('nodeEnv') ?? 'development';

// For development, allow common frontend ports and configured URL
// For production, only allow the configured CLIENT_URL
if ($appEnv === 'development') {
    $allowedOrigins = [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173', // Vite default
        'http://localhost:4200', // Angular default
        'http://localhost:5174',
        $clientUrl
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // Also check Referer header as fallback for same-origin requests
    if (empty($origin) && isset($_SERVER['HTTP_REFERER'])) {
        $refererParts = parse_url($_SERVER['HTTP_REFERER']);
        if ($refererParts && isset($refererParts['scheme']) && isset($refererParts['host'])) {
            $referer = $refererParts['scheme'] . '://' . $refererParts['host'];
            if (isset($refererParts['port'])) {
                $referer .= ':' . $refererParts['port'];
            }
            if (in_array($referer, $allowedOrigins)) {
                $origin = $referer;
            }
        }
    }
    
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else if ($origin && $appEnv === 'development') {
        // In development, log the origin for debugging
        error_log("CORS: Origin '{$origin}' not in allowed list. Allowed: " . implode(', ', $allowedOrigins));
        header("Access-Control-Allow-Origin: {$clientUrl}");
    } else {
        header("Access-Control-Allow-Origin: {$clientUrl}");
    }
} else {
    header("Access-Control-Allow-Origin: {$clientUrl}");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Health check endpoint - handle both /health and /php-backend/health
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');
$isHealthCheck = ($requestUri === '/health' || substr($requestUri, -7) === '/health');

if ($isHealthCheck) {
    header('Content-Type: application/json');
    
    $health = [
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => 'unknown'
    ];
    
    // Test database connection
    try {
        require_once __DIR__ . '/src/config/db.php';
        $db = getDB();
        $db->query("SELECT 1");
        $health['database'] = 'connected';
        http_response_code(200);
    } catch (Exception $e) {
        $health['database'] = 'disconnected';
        $health['error'] = $e->getMessage();
        http_response_code(503); // Service Unavailable
    }
    
    echo json_encode($health);
    exit;
}

// Load routes
$routes = require_once __DIR__ . '/src/routes/index.php';

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Parse URI to get path component
$parsedUri = parse_url($uri);
$queryString = $parsedUri['query'] ?? '';
$uri = $parsedUri['path'] ?? $uri;

// Remove query string from URI for routing (if not already parsed)
$uri = strtok($uri, '?');

// Normalize URI - ensure it starts with /
if ($uri === '' || $uri[0] !== '/') {
    $uri = '/' . $uri;
}

// Strip /php-backend prefix if present (for WAMP Apache)
// This allows routes to work both with and without the prefix
$basePath = '/php-backend';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
    // Ensure URI starts with / after stripping prefix
    if ($uri === '' || $uri[0] !== '/') {
        $uri = '/' . $uri;
    }
}

// Remove trailing slash (except for root)
if (strlen($uri) > 1 && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// Reconstruct URI with query string for router (so it can parse ?archived=true, etc.)
$uriForRouter = $uri;
if (!empty($queryString)) {
    $uriForRouter .= '?' . $queryString;
}

try {
    // Try to handle the route
    $handled = $routes->handle($method, $uriForRouter);
    
    if (!$handled) {
        // Route not found
        require_once __DIR__ . '/src/middleware/notFound.php';
        notFound(['uri' => $uri, 'method' => $method], []);
    }
} catch (Exception $e) {
    // Handle errors
    require_once __DIR__ . '/src/middleware/errorHandler.php';
    errorHandler($e, ['uri' => $uri, 'method' => $method], []);
}

