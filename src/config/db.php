<?php

require_once __DIR__ . '/env.php';

function getDB() {
    return Database::getInstance()->getConnection();
}

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $config = Env::get('db');
        
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            error_log("Database connection error: " . $errorMsg);
            
            // Provide more helpful error messages
            if (strpos($errorMsg, 'could not find driver') !== false) {
                throw new Exception("PDO MySQL driver not found. Please enable 'pdo_mysql' extension in php.ini");
            } elseif (strpos($errorMsg, 'Access denied') !== false) {
                throw new Exception("Database access denied. Check username and password in .env file");
            } elseif (strpos($errorMsg, 'Unknown database') !== false) {
                throw new Exception("Database '{$config['name']}' does not exist. Please create it first.");
            } else {
                throw new Exception("Database connection failed: " . $errorMsg);
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function __clone() {
        throw new Exception("Cannot clone singleton");
    }

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

