<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function parseExpiryToMs($expiry) {
    if (is_numeric($expiry)) {
        return (int)$expiry * 1000;
    }

    if (!preg_match('/^(\d+)([smhd])$/', $expiry, $matches)) {
        throw new Exception("Unsupported expiry format: {$expiry}");
    }

    $value = (int)$matches[1];
    $unit = $matches[2];

    $multipliers = [
        's' => 1000,
        'm' => 60 * 1000,
        'h' => 60 * 60 * 1000,
        'd' => 24 * 60 * 60 * 1000,
    ];

    return $value * $multipliers[$unit];
}

function generateAccessToken($payload, $customExpiry = null) {
    $config = Env::get('jwt');
    $expiry = $customExpiry ?? $config['accessExpiry'];
    
    $issuedAt = time();
    $expirationTime = $issuedAt + (parseExpiryToMs($expiry) / 1000);
    
    $token = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'sub' => $payload['sub'],
        'role' => $payload['role'],
    ];

    return JWT::encode($token, $config['secret'], 'HS256');
}

function generateRefreshToken($payload) {
    $config = Env::get('jwt');
    
    // Use refreshSecret if set, otherwise fall back to secret
    $refreshSecret = $config['refreshSecret'] ?? $config['secret'] ?? null;
    
    if (empty($refreshSecret)) {
        error_log("JWT refresh secret and JWT secret are not set in environment configuration");
        throw new Exception('JWT refresh secret not configured');
    }
    
    $issuedAt = time();
    $expirationTime = $issuedAt + (parseExpiryToMs($config['refreshExpiry']) / 1000);
    
    $token = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'sub' => $payload['sub'],
        'role' => $payload['role'],
    ];

    return JWT::encode($token, $refreshSecret, 'HS256');
}

function verifyRefreshToken($token) {
    $config = Env::get('jwt');
    
    // Check if refreshSecret is set - fall back to secret if not set
    $refreshSecret = $config['refreshSecret'] ?? $config['secret'] ?? null;
    
    if (empty($refreshSecret)) {
        error_log("JWT refresh secret and JWT secret are not set in environment configuration");
        error_log("JWT config: " . json_encode($config));
        throw new Exception('JWT refresh secret not configured');
    }
    
    try {
        $decoded = JWT::decode($token, new Key($refreshSecret, 'HS256'));
        
        // Check if token is expired
        if (isset($decoded->exp) && $decoded->exp < time()) {
            error_log("Refresh token expired for user {$decoded->sub}");
            throw new Exception('Refresh token expired');
        }
        
        // Verify token has required claims
        if (!isset($decoded->sub) || !isset($decoded->role)) {
            error_log("Refresh token missing required claims (sub or role)");
            throw new Exception('Invalid refresh token format: missing required claims');
        }
        
        return $decoded;
    } catch (Firebase\JWT\ExpiredException $e) {
        error_log("Refresh token expired: " . $e->getMessage());
        throw new Exception('Refresh token expired');
    } catch (Firebase\JWT\SignatureInvalidException $e) {
        error_log("Refresh token signature invalid: " . $e->getMessage());
        throw new Exception('Invalid refresh token signature');
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        error_log("Refresh token validation error: " . $errorMsg);
        
        // Don't wrap our own errors
        if (strpos($errorMsg, 'Refresh token') !== false || strpos($errorMsg, 'JWT refresh secret') !== false) {
            throw $e;
        }
        
        throw new Exception('Invalid refresh token: ' . $errorMsg);
    }
}

function verifyAccessToken($token) {
    $config = Env::get('jwt');
    
    if (empty($config['secret'])) {
        error_log("JWT secret is not set in environment configuration");
        throw new Exception('JWT secret not configured');
    }
    
    try {
        $decoded = JWT::decode($token, new Key($config['secret'], 'HS256'));
        
        // Check if token is expired
        if (isset($decoded->exp) && $decoded->exp < time()) {
            error_log("Access token expired for user {$decoded->sub}");
            throw new Exception('Token expired');
        }
        
        return $decoded;
    } catch (Firebase\JWT\ExpiredException $e) {
        error_log("Access token expired: " . $e->getMessage());
        throw new Exception('Token expired');
    } catch (Firebase\JWT\SignatureInvalidException $e) {
        error_log("Access token signature invalid: " . $e->getMessage());
        throw new Exception('Invalid token signature');
    } catch (Exception $e) {
        error_log("Access token validation error: " . $e->getMessage());
        throw new Exception('Invalid or expired token: ' . $e->getMessage());
    }
}

function hashToken($token) {
    return hash('sha256', $token);
}

