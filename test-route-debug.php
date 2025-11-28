<?php
/**
 * Debug script to test route matching
 * Access: http://localhost:8000/test-route-debug.php
 */

header('Content-Type: application/json');

$debug = [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT SET',
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT SET',
    'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT SET',
];

// Parse URI
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUri = parse_url($uri);
$debug['parsed_path'] = $parsedUri['path'] ?? 'NOT SET';
$debug['parsed_query'] = $parsedUri['query'] ?? 'NOT SET';

// Normalize URI
$normalizedUri = $parsedUri['path'] ?? $uri;
if ($normalizedUri === '' || $normalizedUri[0] !== '/') {
    $normalizedUri = '/' . $normalizedUri;
}

// Strip /php-backend prefix
$basePath = '/php-backend';
if (strpos($normalizedUri, $basePath) === 0) {
    $normalizedUri = substr($normalizedUri, strlen($basePath));
    if ($normalizedUri === '' || $normalizedUri[0] !== '/') {
        $normalizedUri = '/' . $normalizedUri;
    }
}

$debug['normalized_uri'] = $normalizedUri;
$debug['method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Check if route exists by testing route matching
$routes = require_once __DIR__ . '/src/routes/index.php';

// Test route matching
$testMethod = 'POST';
$testPath = '/api/auth/refresh';
$debug['test_route'] = $testPath;
$debug['test_method'] = $testMethod;

// Try to handle the route to see if it matches
$debug['route_matched'] = false;
$debug['routes_count'] = 0;
$debug['route_exists'] = false;

try {
    // Use reflection to check if routes are loaded (routes is private)
    $reflection = new ReflectionClass($routes);
    $routesProperty = $reflection->getProperty('routes');
    $routesProperty->setAccessible(true);
    $routesArray = $routesProperty->getValue($routes);
    $debug['routes_count'] = count($routesArray);
    
    // Check if our test route exists
    foreach ($routesArray as $route) {
        if ($route['method'] === 'POST' && $route['path'] === '/api/auth/refresh') {
            $debug['route_exists'] = true;
            break;
        }
    }
    
    // Actually test route matching (this will try to execute the handler)
    // We'll catch any exceptions since we're just testing
    ob_start();
    try {
        $matched = $routes->handle($testMethod, $testPath);
        $debug['route_matched'] = $matched;
    } catch (Exception $e) {
        // If we get an exception, it means the route was found but handler failed
        // This is actually good - it means the route exists!
        $debug['route_matched'] = true;
        $debug['route_handler_error'] = $e->getMessage();
    }
    ob_end_clean();
    
} catch (Exception $e) {
    $debug['route_error'] = $e->getMessage();
    $debug['route_matched'] = false;
}

// Also test with actual request
$debug['actual_request'] = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $normalizedUri
];

echo json_encode($debug, JSON_PRETTY_PRINT);
