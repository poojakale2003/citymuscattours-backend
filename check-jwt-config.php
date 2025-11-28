<?php
/**
 * Check JWT Configuration
 * This script checks if JWT secrets are properly configured
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config/env.php';

try {
    Env::load();
} catch (Exception $e) {
    die(json_encode(['error' => 'Failed to load environment: ' . $e->getMessage()]));
}

$config = Env::get('jwt');
$envVars = [];

// Check environment variables
$envVars['JWT_SECRET'] = [
    'set' => !empty($_ENV['JWT_SECRET']) || !empty(getenv('JWT_SECRET')),
    'value' => !empty($_ENV['JWT_SECRET']) || !empty(getenv('JWT_SECRET')) ? substr($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET'), 0, 10) . '...' : null,
];

$envVars['JWT_REFRESH_SECRET'] = [
    'set' => !empty($_ENV['JWT_REFRESH_SECRET']) || !empty(getenv('JWT_REFRESH_SECRET')),
    'value' => !empty($_ENV['JWT_REFRESH_SECRET']) || !empty(getenv('JWT_REFRESH_SECRET')) ? substr($_ENV['JWT_REFRESH_SECRET'] ?? getenv('JWT_REFRESH_SECRET'), 0, 10) . '...' : null,
];

$result = [
    'jwt_config' => [
        'secret_set' => !empty($config['secret']),
        'secret_preview' => !empty($config['secret']) ? substr($config['secret'], 0, 10) . '...' : null,
        'refresh_secret_set' => !empty($config['refreshSecret']),
        'refresh_secret_preview' => !empty($config['refreshSecret']) ? substr($config['refreshSecret'], 0, 10) . '...' : null,
        'refresh_secret_source' => !empty($_ENV['JWT_REFRESH_SECRET']) || !empty(getenv('JWT_REFRESH_SECRET')) ? 'JWT_REFRESH_SECRET' : 'JWT_SECRET (fallback)',
        'access_expiry' => $config['accessExpiry'] ?? 'not set',
        'refresh_expiry' => $config['refreshExpiry'] ?? 'not set',
    ],
    'environment_variables' => $envVars,
    'status' => 'ok',
    'recommendations' => [],
];

// Check if refresh secret will work
$refreshSecret = $config['refreshSecret'] ?? $config['secret'] ?? null;
if (empty($refreshSecret)) {
    $result['status'] = 'error';
    $result['error'] = 'Neither JWT_REFRESH_SECRET nor JWT_SECRET is configured';
    $result['recommendations'][] = 'Set JWT_SECRET in your .env file';
    $result['recommendations'][] = 'Optionally set JWT_REFRESH_SECRET (will use JWT_SECRET as fallback)';
} else {
    $result['effective_refresh_secret'] = [
        'set' => true,
        'source' => !empty($config['refreshSecret']) ? 'JWT_REFRESH_SECRET' : 'JWT_SECRET (fallback)',
        'preview' => substr($refreshSecret, 0, 10) . '...',
    ];
    
    if (empty($config['refreshSecret'])) {
        $result['recommendations'][] = 'JWT_REFRESH_SECRET is not set - using JWT_SECRET as fallback';
        $result['recommendations'][] = 'For better security, set a separate JWT_REFRESH_SECRET in .env';
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);

