<?php

require_once __DIR__ . '/../utils/ApiError.php';
require_once __DIR__ . '/../utils/token.php';

function authenticate($req, $res, $next) {
    // Get headers array (should be normalized to lowercase)
    $headers = $req['headers'] ?? [];
    
    // Try multiple variations of the Authorization header key
    $authHeader = null;
    if (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    
    $token = null;
    
    if ($authHeader) {
        // Handle both "Bearer token" and "bearer token" formats
        if (stripos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        } elseif (stripos($authHeader, 'Bearer ') !== false) {
            // Handle case where there might be spaces
            $parts = explode(' ', $authHeader);
            if (count($parts) >= 2) {
                $token = $parts[1];
            }
        } else {
            // Maybe just the token was sent
            $token = trim($authHeader);
        }
    }

    if (!$token) {
        // Enhanced debugging
        error_log("=== AUTHENTICATION FAILED ===");
        error_log("Request URI: " . ($req['uri'] ?? 'unknown'));
        error_log("Request Method: " . ($req['method'] ?? 'unknown'));
        error_log("Headers received: " . json_encode(array_keys($headers)));
        error_log("Header values: " . json_encode($headers));
        error_log("HTTP_AUTHORIZATION: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'not set'));
        error_log("REDIRECT_HTTP_AUTHORIZATION: " . ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'not set'));
        
        // Try to get raw Authorization header from apache_request_headers
        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            error_log("Apache headers: " . json_encode($apacheHeaders));
        }
        
        // Log all $_SERVER keys that might contain auth info
        $authKeys = [];
        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'AUTH') !== false || stripos($key, 'TOKEN') !== false) {
                $authKeys[$key] = is_string($value) && strlen($value) > 100 ? substr($value, 0, 50) . '...' : $value;
            }
        }
        if (!empty($authKeys)) {
            error_log("Auth-related $_SERVER keys: " . json_encode($authKeys));
        }
        
        throw new ApiError(401, 'Authentication required - Please send Authorization header with Bearer token. Example: Authorization: Bearer YOUR_ACCESS_TOKEN');
    }

    try {
        $decoded = verifyAccessToken($token);
        
        // Verify token has required claims
        if (!isset($decoded->sub) || !isset($decoded->role)) {
            error_log("Auth failed: Token missing required claims (sub or role)");
            throw new ApiError(401, 'Invalid token format');
        }
        
        $req['user'] = [
            'sub' => $decoded->sub,
            'role' => $decoded->role,
        ];
    } catch (ApiError $e) {
        // Re-throw ApiError as-is
        throw $e;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        error_log("Auth failed: Token validation error - " . $errorMsg);
        error_log("Token received: " . substr($token, 0, 50) . "...");
        
        // Provide more specific error messages for debugging
        $publicMsg = 'Invalid or expired token';
        if (stripos($errorMsg, 'expired') !== false) {
            $publicMsg = 'Token expired';
        } elseif (stripos($errorMsg, 'signature') !== false) {
            $publicMsg = 'Invalid token signature';
        } elseif (stripos($errorMsg, 'secret') !== false) {
            $publicMsg = 'Token configuration error';
        }
        
        throw new ApiError(401, $publicMsg);
    }
    
    // Run the next middleware/handler outside the token verification try/catch
    return $next($req, $res);
}

function optionalAuthenticate($req, $res, $next) {
    $authHeader = $req['headers']['Authorization'] ?? $req['headers']['authorization'] ?? null;
    $token = null;

    if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    }

    if ($token) {
        try {
            $decoded = verifyAccessToken($token);
            $req['user'] = [
                'sub' => $decoded->sub,
                'role' => $decoded->role,
            ];
        } catch (Exception $e) {
            $req['user'] = null;
        }
    }

    return $next($req, $res);
}

function authorize(...$roles) {
    return function($req, $res, $next) use ($roles) {
        if (!isset($req['user']) || !in_array($req['user']['role'], $roles)) {
            throw new ApiError(403, 'Insufficient permissions');
        }
        return $next($req, $res);
    };
}

