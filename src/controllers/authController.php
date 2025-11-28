<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/ApiError.php';
require_once __DIR__ . '/../utils/token.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../config/env.php';

function sanitizeUser($user) {
    return [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'phone' => $user['phone'] ?? null,
        'createdAt' => $user['created_at'],
        'updatedAt' => $user['updated_at'],
    ];
}

function buildTokens($user) {
    $payload = ['sub' => $user['id'], 'role' => $user['role']];
    $config = Env::get('jwt');
    
    $accessToken = generateAccessToken($payload, $config['accessExpiry']);
    $refreshToken = generateRefreshToken($payload);
    
    return ['accessToken' => $accessToken, 'refreshToken' => $refreshToken];
}

function sendAuthSuccess($res, $user, $tokens, $status = 200) {
    $config = Env::get('jwt');
    $accessMaxAge = parseExpiryToMs($config['accessExpiry']) / 1000;
    
    http_response_code($status);
    header('Content-Type: application/json');
    
    // Set refresh token cookie
    $refreshMaxAge = parseExpiryToMs($config['refreshExpiry']) / 1000;
    setcookie('refreshToken', $tokens['refreshToken'], [
        'expires' => time() + $refreshMaxAge,
        'path' => '/',
        'domain' => '',
        'secure' => Env::get('nodeEnv') === 'production',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    $response = [
        'user' => sanitizeUser($user),
        'tokens' => [
            'accessToken' => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
            'expiresIn' => (int)$accessMaxAge,
        ],
        // Backwards compatibility for existing clients reading top-level tokens
        'accessToken' => $tokens['accessToken'],
        'refreshToken' => $tokens['refreshToken'],
        'expiresIn' => (int)$accessMaxAge,
    ];

    echo json_encode($response);
}

function register($req, $res) {
    $data = json_decode($req['body'], true);
    $name = $data['name'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    $phone = $data['phone'] ?? null;

    if (!$name || !$email || !$password) {
        throw new ApiError(400, 'Name, email, and password are required');
    }

    $userModel = new User();
    $existingUser = $userModel->findByEmail($email);
    
    if ($existingUser) {
        throw new ApiError(409, 'Email already registered');
    }

    $userId = $userModel->createUser([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'phone' => $phone,
    ]);

    $user = $userModel->getById($userId);
    $tokens = buildTokens($user);
    
    $config = Env::get('jwt');
    $refreshMaxAge = parseExpiryToMs($config['refreshExpiry']) / 1000;
    $expiresAt = date('Y-m-d H:i:s', time() + $refreshMaxAge);
    $userModel->addRefreshToken($userId, $tokens['refreshToken'], $expiresAt);

    sendAuthSuccess($res, $user, $tokens, 201);
}

function login($req, $res) {
    $data = json_decode($req['body'], true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (!$email || !$password) {
        throw new ApiError(400, 'Email and password are required');
    }

    $userModel = new User();
    $user = $userModel->findByEmail($email);

    if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
        throw new ApiError(401, 'Invalid email or password');
    }

    $userModel->purgeExpiredTokens($user['id']);

    $tokens = buildTokens($user);
    
    $config = Env::get('jwt');
    $refreshMaxAge = parseExpiryToMs($config['refreshExpiry']) / 1000;
    $expiresAt = date('Y-m-d H:i:s', time() + $refreshMaxAge);
    $userModel->addRefreshToken($user['id'], $tokens['refreshToken'], $expiresAt);

    sendAuthSuccess($res, $user, $tokens);
}

function refreshToken($req, $res) {
    // Try to get refresh token from cookie first
    $token = $_COOKIE['refreshToken'] ?? null;
    
    // If not in cookie, try request body - handle both parsed bodyData and raw body
    if (!$token) {
        // Try parsed bodyData from router
        $data = $req['bodyData'] ?? [];
        
        // If empty, try parsing raw body
        if (empty($data)) {
            $data = json_decode($req['body'], true) ?? [];
        }
        
        if (isset($data['refreshToken'])) {
            $token = $data['refreshToken'];
        } elseif (isset($data['refresh_token'])) {
            $token = $data['refresh_token'];
        }
    }

    if (!$token) {
        error_log("Refresh token failed: No token found in cookie or body");
        throw new ApiError(401, 'Refresh token missing');
    }

    try {
        $decoded = verifyRefreshToken($token);
    } catch (Exception $e) {
        error_log("Refresh token failed: Invalid token - " . $e->getMessage());
        throw new ApiError(401, 'Invalid refresh token: ' . $e->getMessage());
    }

    $userModel = new User();
    $tokenRecord = $userModel->findRefreshToken($decoded->sub, $token);

    if (!$tokenRecord) {
        error_log("Refresh token failed: Token not found in database for user {$decoded->sub}");
        throw new ApiError(401, 'Refresh token not recognized or expired');
    }

    // Get user directly from database instead of using protected method
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$decoded->sub]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new ApiError(404, 'User not found');
    }

    $tokens = buildTokens($user);
    
    // Remove old refresh token and add new one
    $userModel->removeRefreshToken($decoded->sub, $token);
    
    $config = Env::get('jwt');
    $refreshMaxAge = parseExpiryToMs($config['refreshExpiry']) / 1000;
    $expiresAt = date('Y-m-d H:i:s', time() + $refreshMaxAge);
    $userModel->addRefreshToken($user['id'], $tokens['refreshToken'], $expiresAt);

    sendAuthSuccess($res, $user, $tokens);
}

function logout($req, $res) {
    $token = $_COOKIE['refreshToken'] ?? null;
    $data = json_decode($req['body'], true);
    if (!$token && isset($data['refreshToken'])) {
        $token = $data['refreshToken'];
    }

    if ($token) {
        try {
            require_once __DIR__ . '/../config/env.php';
            $config = Env::get('jwt');
            $decoded = verifyRefreshToken($token);
            $userModel = new User();
            $userModel->removeRefreshToken($decoded->sub, $token);
        } catch (Exception $e) {
            Logger::warn('Failed to invalidate refresh token on logout', ['error' => $e->getMessage()]);
        }
    }

    setcookie('refreshToken', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => Env::get('nodeEnv') === 'production',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Logged out successfully']);
}

