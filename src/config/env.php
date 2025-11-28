<?php

class Env {
    private static $config = null;

    public static function load() {
        if (self::$config !== null) {
            return self::$config;
        }

        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
            $dotenv->load();
        }

        $requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'JWT_SECRET', 'CLIENT_URL'];
        foreach ($requiredVars as $key) {
            if (!isset($_ENV[$key]) && !getenv($key)) {
                throw new Exception("Missing required environment variable: {$key}");
            }
        }

        self::$config = [
            'nodeEnv' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development',
            'port' => (int)($_ENV['PORT'] ?? getenv('PORT') ?? 5000),
            'db' => [
                'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST'),
                'port' => (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 3306),
                'name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME'),
                'user' => $_ENV['DB_USER'] ?? getenv('DB_USER'),
                'pass' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '',
            ],
            'jwt' => [
                'secret' => $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET'),
                // Use JWT_REFRESH_SECRET if set and not empty, otherwise fall back to JWT_SECRET
                'refreshSecret' => (!empty($_ENV['JWT_REFRESH_SECRET']) ? $_ENV['JWT_REFRESH_SECRET'] : null) 
                    ?? (!empty(getenv('JWT_REFRESH_SECRET')) ? getenv('JWT_REFRESH_SECRET') : null)
                    ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET')),
                'accessExpiry' => $_ENV['JWT_EXPIRY'] ?? getenv('JWT_EXPIRY') ?? '24h',
                'refreshExpiry' => $_ENV['JWT_REFRESH_EXPIRY'] ?? getenv('JWT_REFRESH_EXPIRY') ?? '30d',
            ],
            'clientUrl' => $_ENV['CLIENT_URL'] ?? getenv('CLIENT_URL'),
            'email' => [
                'from' => $_ENV['EMAIL_FROM'] ?? getenv('EMAIL_FROM') ?? 'no-reply@travelapp.com',
                'host' => $_ENV['EMAIL_HOST'] ?? getenv('EMAIL_HOST') ?? '',
                'port' => (int)($_ENV['EMAIL_PORT'] ?? getenv('EMAIL_PORT') ?? 587),
                'user' => $_ENV['EMAIL_USER'] ?? getenv('EMAIL_USER') ?? '',
                'pass' => $_ENV['EMAIL_PASS'] ?? getenv('EMAIL_PASS') ?? '',
            ],
            'razorpay' => [
                'keyId' => $_ENV['RAZORPAY_KEY_ID'] ?? getenv('RAZORPAY_KEY_ID') ?? '',
                'keySecret' => $_ENV['RAZORPAY_KEY_SECRET'] ?? getenv('RAZORPAY_KEY_SECRET') ?? '',
            ],
            'stripe' => [
                'secretKey' => $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY') ?? '',
                'webhookSecret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?? '',
            ],
        ];

        return self::$config;
    }

    public static function get($key = null) {
        $config = self::load();
        if ($key === null) {
            return $config;
        }
        return $config[$key] ?? null;
    }
}

