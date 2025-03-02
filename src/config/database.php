<?php

/**
 * Database Configuration for Central Resource Dashboard (CRD)
 * 
 * This file contains the database connection settings and utility functions
 * for interacting with the MySQL/MariaDB database in cPanel environment.
 * 
 * Supports both development and production environments with appropriate settings.
 */

class Database {
    // Environment detection
    private static $env;
    
    // Database connection
    private static $conn = null;
    
    // Default configuration
    private static $config = [
        'development' => [
            'host' => 'sas-nl.webhostmall.com',
            'db_name' => 'pcit_crd_project',
            'username' => 'pcit_crd_user',
            'password' => 'crd_user',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'skip_ssl' => true,
            'persistent' => true
        ],
        'production' => [
            'host' => 'sas-nl.webhostmall.com',
            'db_name' => 'pcit_crd_project',
            'username' => 'pcit_crd_user',
            'password' => 'crd_user',
            'port' => 3306,
            'charset' => 'utf8mb4',
            'skip_ssl' => false,
            'persistent' => true
        ]
    ];
    
    /**
     * Constructor - initialize environment
     */
    public function __construct() {
        self::$env = self::determineEnvironment();
    }
    
    /**
     * Determine current environment
     * @return string Environment name ('development' or 'production')
     */
    private static function determineEnvironment() {
        // Check for defined constant
        if (defined('APP_ENV')) {
            return constant('APP_ENV');
        }
        
        // Check for environment variable
        $env = getenv('APP_ENV');
        if ($env !== false) {
            return $env;
        }
        
        // Default to development for safety
        return 'development';
    }
    
    /**
     * Get the database connection
     * @return PDO|null Database connection object or null on failure
     */
    public static function getConnection() {
        // Return existing connection if available
        if (self::$conn !== null) {
            return self::$conn;
        }
        
        try {
            // Determine environment if not already set
            if (!isset(self::$env)) {
                self::$env = self::determineEnvironment();
            }
            
            // Load configuration for current environment
            $config = self::$config[self::$env];
            
            // Override with constants/environment variables if defined
            $host = defined('DB_HOST') ? constant('DB_HOST') : (getenv('DB_HOST') ?: $config['host']);
            $db_name = defined('DB_NAME') ? constant('DB_NAME') : (getenv('DB_NAME') ?: $config['db_name']);
            $username = defined('DB_USER') ? constant('DB_USER') : (getenv('DB_USER') ?: $config['username']);
            $password = defined('DB_PASS') ? constant('DB_PASS') : (getenv('DB_PASS') ?: $config['password']);
            $port = defined('DB_PORT') ? constant('DB_PORT') : (getenv('DB_PORT') ?: $config['port']);
            $charset = defined('DB_CHARSET') ? constant('DB_CHARSET') : (getenv('DB_CHARSET') ?: $config['charset']);
            $skipSSL = defined('DB_SKIP_SSL') ? constant('DB_SKIP_SSL') : (getenv('DB_SKIP_SSL') ?: $config['skip_ssl']);
            $persistent = defined('DB_PERSISTENT') ? constant('DB_PERSISTENT') : (getenv('DB_PERSISTENT') ?: $config['persistent']);
            
            // Build DSN
            $dsn = "mysql:host={$host};dbname={$db_name};port={$port};charset={$charset}";
            
            // Set PDO options
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            // Add persistent connection if enabled
            if ($persistent) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }
            
            // Skip SSL if specified (development only)
            if ($skipSSL && self::$env === 'development') {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            
            // Create connection
            self::$conn = new PDO($dsn, $username, $password, $options);
            
            // Set timezone and other session variables if needed
            self::$conn->exec("SET time_zone = '+05:30'"); // India timezone for MySQL
            
            return self::$conn;
            
        } catch (PDOException $e) {
            // Log error
            self::logError($e->getMessage());
            
            // Return null to indicate failure
            return null;
        }
    }
    
    /**
     * Execute a query with prepared statements
     * @param string $query SQL query
     * @param array $params Parameters for prepared statement
     * @return array|bool|null Result set, boolean for operations, or null on error
     */
    public static function executeQuery($query, $params = []) {
        try {
            $conn = self::getConnection();
            
            // Check if connection was successful
            if ($conn === null) {
                return null;
            }
            
            $stmt = $conn->prepare($query);
            
            // Execute the statement with parameters
            $stmt->execute($params);
            
            // For SELECT queries, return the results
            if (stripos($query, 'SELECT') === 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // For INSERT, UPDATE, DELETE, return true on success
            return true;
            
        } catch (PDOException $e) {
            self::logError("Query execution error: " . $e->getMessage() . " - Query: " . $query);
            return null;
        }
    }
    
    /**
     * Get the last inserted ID
     * @return string|null Last inserted ID or null on error
     */
    public static function getLastInsertId() {
        $conn = self::getConnection();
        if ($conn === null) {
            return null;
        }
        return $conn->lastInsertId();
    }
    
    /**
     * Begin a transaction
     * @return bool Success status
     */
    public static function beginTransaction() {
        $conn = self::getConnection();
        if ($conn === null) {
            return false;
        }
        return $conn->beginTransaction();
    }
    
    /**
     * Commit a transaction
     * @return bool Success status
     */
    public static function commitTransaction() {
        $conn = self::getConnection();
        if ($conn === null) {
            return false;
        }
        return $conn->commit();
    }
    
    /**
     * Rollback a transaction
     * @return bool Success status
     */
    public static function rollbackTransaction() {
        $conn = self::getConnection();
        if ($conn === null) {
            return false;
        }
        return $conn->rollBack();
    }
    
    /**
     * Escape string to prevent SQL injection
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function escapeString($string) {
        return htmlspecialchars(strip_tags($string));
    }
    
    /**
     * Log database errors
     * @param string $message Error message to log
     */
    private static function logError($message) {
        // Log to file
        $logFile = dirname(__FILE__) . '/../../logs/db_errors.log';
        $logDir = dirname($logFile);
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Format log message
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Append to log file
        error_log($logMessage, 3, $logFile);
        
        // Also log to PHP error log
        error_log("Database error: {$message}");
    }
    
    /**
     * Check if a record exists in a table
     * @param string $table Table name
     * @param string $column Column name
     * @param mixed $value Value to check
     * @return bool Whether record exists
     */
    public static function recordExists($table, $column, $value) {
        $query = "SELECT COUNT(*) as count FROM " . $table . " WHERE " . $column . " = :value";
        $params = [':value' => $value];
        
        $result = self::executeQuery($query, $params);
        
        if ($result && isset($result[0]['count']) && $result[0]['count'] > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get database connection status
     * @return array Status information
     */
    public static function getStatus() {
        $status = [
            'connected' => (self::$conn !== null),
            'environment' => self::$env ?? self::determineEnvironment()
        ];
        
        // Add server info if connected
        if ($status['connected']) {
            try {
                $status['server_info'] = self::$conn->getAttribute(PDO::ATTR_SERVER_VERSION);
                $status['client_info'] = self::$conn->getAttribute(PDO::ATTR_CLIENT_VERSION);
            } catch (PDOException $e) {
                $status['error'] = 'Could not retrieve server information';
            }
        }
        
        return $status;
    }
}
?>