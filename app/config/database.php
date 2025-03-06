<?php

/**
 * Database Configuration for Central Resource Dashboard (CRD)
 * 
 * This class provides database connectivity using PDO for MySQL/MariaDB databases.
 * It automatically detects the environment (development/production) and uses 
 * appropriate connection settings.
 * 
 * @author PCIT CRD Development Team
 * @version 1.0
 */

class Database {
    // Environment detection
    private static $env;
    
    // Database connection
    private static $conn = null;
    
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
        if (defined('ENVIRONMENT')) {
            return constant('ENVIRONMENT');
        }
        
        // Check for environment variable
        $env = getenv('ENVIRONMENT');
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
            
            // Load configuration based on environment
            if (self::$env === 'production') {
                $host = defined('DB_HOST_PROD') ? constant('DB_HOST_PROD') : getenv('DB_HOST_PROD');
                $db_name = defined('DB_NAME_PROD') ? constant('DB_NAME_PROD') : getenv('DB_NAME_PROD');
                $username = defined('DB_USERNAME_PROD') ? constant('DB_USERNAME_PROD') : getenv('DB_USERNAME_PROD');
                $password = defined('DB_PASSWORD_PROD') ? constant('DB_PASSWORD_PROD') : getenv('DB_PASSWORD_PROD');
                $port = defined('DB_PORT_PROD') ? constant('DB_PORT_PROD') : getenv('DB_PORT_PROD');
                $skipSSL = false; // Always use SSL in production
            } else {
                $host = defined('DB_HOST_DEV') ? constant('DB_HOST_DEV') : getenv('DB_HOST_DEV');
                $db_name = defined('DB_NAME_DEV') ? constant('DB_NAME_DEV') : getenv('DB_NAME_DEV');
                $username = defined('DB_USERNAME_DEV') ? constant('DB_USERNAME_DEV') : getenv('DB_USERNAME_DEV');
                $password = defined('DB_PASSWORD_DEV') ? constant('DB_PASSWORD_DEV') : getenv('DB_PASSWORD_DEV');
                $port = defined('DB_PORT_DEV') ? constant('DB_PORT_DEV') : getenv('DB_PORT_DEV');
                $skipSSL = true; // Can skip SSL in development
            }
            
            // Default port if not set
            if (empty($port)) {
                $port = 3306;
            }
            
            // Standard charset
            $charset = 'utf8mb4';
            
            // Build DSN
            $dsn = "mysql:host={$host};dbname={$db_name};port={$port};charset={$charset}";
            
            // Set PDO options
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true // Use persistent connections
            ];
            
            // Skip SSL if specified (development only)
            if ($skipSSL && self::$env === 'development') {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            
            // Create connection
            self::$conn = new PDO($dsn, $username, $password, $options);
            
            // Set timezone to India
            self::$conn->exec("SET time_zone = '+05:30'");
            
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