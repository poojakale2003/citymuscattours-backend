<?php
/**
 * Comprehensive API Testing Script
 * Tests all API endpoints to ensure they're working correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configuration
$BASE_URL = 'http://localhost:8000';
$TEST_EMAIL = 'test@example.com';
$TEST_PASSWORD = 'Test123456!';
$TEST_NAME = 'Test User';
$ADMIN_EMAIL = 'admin@example.com';
$ADMIN_PASSWORD = 'admin123';

// Test results
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'base_url' => $BASE_URL,
    'tests' => [],
    'summary' => [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
        'skipped' => 0,
    ],
];

// Helper function to make API requests
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init($url);
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    curl_close($ch);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    return [
        'status' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'data' => json_decode($body, true),
    ];
}

// Test function
function testEndpoint($name, $url, $method = 'GET', $data = null, $headers = [], $expectedStatus = null) {
    global $results, $BASE_URL;
    
    $fullUrl = $BASE_URL . $url;
    $results['summary']['total']++;
    
    $test = [
        'name' => $name,
        'url' => $url,
        'method' => $method,
        'status' => 'pending',
        'message' => '',
    ];
    
    try {
        $response = makeRequest($fullUrl, $method, $data, $headers);
        $test['http_status'] = $response['status'];
        $test['response'] = $response['data'];
        
        if ($expectedStatus !== null) {
            if ($response['status'] === $expectedStatus) {
                $test['status'] = 'passed';
                $test['message'] = "Status {$response['status']} as expected";
                $results['summary']['passed']++;
            } else {
                $test['status'] = 'failed';
                $test['message'] = "Expected status {$expectedStatus}, got {$response['status']}";
                $test['error'] = $response['data']['message'] ?? 'Unknown error';
                $results['summary']['failed']++;
            }
        } else {
            // Accept any 2xx or 3xx status as success
            if ($response['status'] >= 200 && $response['status'] < 400) {
                $test['status'] = 'passed';
                $test['message'] = "Status {$response['status']} - Success";
                $results['summary']['passed']++;
            } else {
                $test['status'] = 'failed';
                $test['message'] = "Status {$response['status']} - Failed";
                $test['error'] = $response['data']['message'] ?? 'Unknown error';
                $results['summary']['failed']++;
            }
        }
    } catch (Exception $e) {
        $test['status'] = 'failed';
        $test['message'] = "Exception: " . $e->getMessage();
        $test['error'] = $e->getMessage();
        $results['summary']['failed']++;
    }
    
    $results['tests'][] = $test;
    return $test;
}

echo "=== Testing All APIs ===\n\n";

// 1. Health Check
echo "1. Testing Health Check...\n";
testEndpoint('Health Check', '/health', 'GET', null, [], 200);

// 2. Auth - Register
echo "2. Testing User Registration...\n";
$registerData = [
    'name' => $GLOBALS['TEST_NAME'],
    'email' => $GLOBALS['TEST_EMAIL'],
    'password' => $GLOBALS['TEST_PASSWORD'],
];
$registerTest = testEndpoint('User Registration', '/api/auth/register', 'POST', $registerData, [], 201);

// 3. Auth - Login (Test User)
echo "3. Testing User Login...\n";
$loginData = [
    'email' => $GLOBALS['TEST_EMAIL'],
    'password' => $GLOBALS['TEST_PASSWORD'],
];
$loginTest = testEndpoint('User Login', '/api/auth/login', 'POST', $loginData, [], 200);

$accessToken = null;
$refreshToken = null;

if ($loginTest['status'] === 'passed' && isset($loginTest['response']['tokens']['accessToken'])) {
    $accessToken = $loginTest['response']['tokens']['accessToken'];
    $refreshToken = $loginTest['response']['tokens']['refreshToken'] ?? $loginTest['response']['refreshToken'] ?? null;
}

// 4. Auth - Login (Admin)
echo "4. Testing Admin Login...\n";
$adminLoginData = [
    'email' => $GLOBALS['ADMIN_EMAIL'],
    'password' => $GLOBALS['ADMIN_PASSWORD'],
];
$adminLoginTest = testEndpoint('Admin Login', '/api/auth/login', 'POST', $adminLoginData, [], 200);

$adminAccessToken = null;
$adminRefreshToken = null;

if ($adminLoginTest['status'] === 'passed' && isset($adminLoginTest['response']['tokens']['accessToken'])) {
    $adminAccessToken = $adminLoginTest['response']['tokens']['accessToken'];
    $adminRefreshToken = $adminLoginTest['response']['tokens']['refreshToken'] ?? $adminLoginTest['response']['refreshToken'] ?? null;
} elseif ($adminLoginTest['status'] === 'failed') {
    echo "  Note: Admin login failed. You may need to create an admin user first.\n";
}

// 5. Packages - List (Public)
echo "5. Testing List Packages (Public)...\n";
testEndpoint('List Packages (Public)', '/api/packages', 'GET', null, [], 200);

// 6. Packages - Get Single (Public)
echo "6. Testing Get Package (Public)...\n";
// First get a package ID
$packagesTest = makeRequest($BASE_URL . '/api/packages', 'GET');
if ($packagesTest['status'] === 200 && isset($packagesTest['data']['data']) && !empty($packagesTest['data']['data'])) {
    $packageId = $packagesTest['data']['data'][0]['id'];
    testEndpoint('Get Package (Public)', "/api/packages/{$packageId}", 'GET', null, [], 200);
} else {
    testEndpoint('Get Package (Public)', '/api/packages/1', 'GET', null, [], null);
}

// 7. User Profile - Get (Protected)
echo "7. Testing Get User Profile (Protected)...\n";
if ($accessToken) {
    testEndpoint('Get User Profile', '/api/users/profile', 'GET', null, [
        "Authorization: Bearer {$accessToken}",
    ], 200);
} else {
    $results['summary']['skipped']++;
    $results['tests'][] = [
        'name' => 'Get User Profile',
        'status' => 'skipped',
        'message' => 'No access token available',
    ];
}

// 8. Packages - Create (Admin Only)
echo "8. Testing Create Package (Admin Only)...\n";
$packageData = [
    'title' => 'Test Package ' . time(),
    'destination' => 'Test Destination',
    'price' => 1999.99,
    'description' => 'This is a test package',
];
if ($adminAccessToken) {
    $createPackageTest = testEndpoint('Create Package (Admin)', '/api/packages', 'POST', $packageData, [
        "Authorization: Bearer {$adminAccessToken}",
    ], 201);
    
    $createdPackageId = null;
    if ($createPackageTest['status'] === 'passed' && isset($createPackageTest['response']['data']['id'])) {
        $createdPackageId = $createPackageTest['response']['data']['id'];
    }
} else {
    $results['summary']['skipped']++;
    $results['tests'][] = [
        'name' => 'Create Package (Admin)',
        'status' => 'skipped',
        'message' => 'No admin access token available',
    ];
}

// 9. Packages - Update (Admin Only)
echo "9. Testing Update Package (Admin Only)...\n";
if ($adminAccessToken && isset($createdPackageId)) {
    $updateData = [
        'title' => 'Updated Test Package',
        'destination' => 'Updated Destination',
        'price' => 2999.99,
    ];
    testEndpoint('Update Package (Admin)', "/api/packages/{$createdPackageId}", 'PUT', $updateData, [
        "Authorization: Bearer {$adminAccessToken}",
    ], 200);
} else {
    $results['summary']['skipped']++;
    $results['tests'][] = [
        'name' => 'Update Package (Admin)',
        'status' => 'skipped',
        'message' => 'No admin token or package ID available',
    ];
}

// 10. Auth - Refresh Token
echo "10. Testing Refresh Token...\n";
if ($refreshToken) {
    testEndpoint('Refresh Token', '/api/auth/refresh', 'POST', [
        'refreshToken' => $refreshToken,
    ], [], 200);
} else {
    $results['summary']['skipped']++;
    $results['tests'][] = [
        'name' => 'Refresh Token',
        'status' => 'skipped',
        'message' => 'No refresh token available',
    ];
}

// 11. Auth - Logout (Protected)
echo "11. Testing Logout (Protected)...\n";
if ($accessToken) {
    testEndpoint('Logout', '/api/auth/logout', 'POST', null, [
        "Authorization: Bearer {$accessToken}",
    ], 200);
} else {
    $results['summary']['skipped']++;
    $results['tests'][] = [
        'name' => 'Logout',
        'status' => 'skipped',
        'message' => 'No access token available',
    ];
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Total Tests: {$results['summary']['total']}\n";
echo "Passed: {$results['summary']['passed']}\n";
echo "Failed: {$results['summary']['failed']}\n";
echo "Skipped: {$results['summary']['skipped']}\n";

// Output JSON results
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
} else {
    echo "\n=== Detailed Results ===\n";
    foreach ($results['tests'] as $test) {
        $status = strtoupper($test['status']);
        $color = $test['status'] === 'passed' ? '✓' : ($test['status'] === 'failed' ? '✗' : '⊘');
        echo "{$color} {$test['name']}: {$test['status']} - {$test['message']}\n";
        if ($test['status'] === 'failed' && isset($test['error'])) {
            echo "  Error: {$test['error']}\n";
        }
    }
}



