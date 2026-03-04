<?php
/**
 * UBIDS Student ID Card Photo Portal - Database Connection
 * 
 * Handles all database operations using PDO with prepared statements
 */

// Prevent direct access
if (!defined('UBIDS_PORTAL')) {
    exit('Direct access denied');
}

class Database {
    private static $instance = null;
    private $pdo;
    private $statement;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prepare SQL statement
     */
    public function prepare($sql) {
        $this->statement = $this->pdo->prepare($sql);
        return $this;
    }
    
    /**
     * Bind values to prepared statement
     */
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        
        $this->statement->bindValue($param, $value, $type);
        return $this;
    }
    
    /**
     * Execute prepared statement
     */
    public function execute() {
        try {
            return $this->statement->execute();
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die("Query execution failed: " . $e->getMessage());
            } else {
                die("Database error. Please try again later.");
            }
        }
    }
    
    /**
     * Get multiple records
     */
    public function fetchAll() {
        $this->execute();
        return $this->statement->fetchAll();
    }
    
    /**
     * Get single record
     */
    public function fetch() {
        $this->execute();
        return $this->statement->fetch();
    }
    
    /**
     * Get single column value
     */
    public function fetchColumn() {
        $this->execute();
        return $this->statement->fetchColumn();
    }
    
    /**
     * Get last inserted ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        $this->prepare("SHOW TABLES LIKE :table");
        $this->bind(':table', $table);
        $result = $this->fetch();
        return !empty($result);
    }
    
    /**
     * Get row count
     */
    public function rowCount() {
        return $this->statement->rowCount();
    }
    
    /**
     * Close connection
     */
    public function close() {
        $this->pdo = null;
        self::$instance = null;
    }
}

/**
 * Database helper functions
 */

/**
 * Execute a query and return all results
 */
function db_query($sql, $params = []) {
    $db = Database::getInstance();
    $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    return $db->fetchAll();
}

/**
 * Execute a query and return single result
 */
function db_query_one($sql, $params = []) {
    $db = Database::getInstance();
    $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    return $db->fetch();
}

/**
 * Execute a query and return single column
 */
function db_query_column($sql, $params = []) {
    $db = Database::getInstance();
    $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    return $db->fetchColumn();
}

/**
 * Execute insert/update/delete query
 */
function db_execute($sql, $params = []) {
    $db = Database::getInstance();
    $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $db->bind($key, $value);
    }
    
    return $db->execute();
}

/**
 * Get last insert ID
 */
function db_last_insert_id() {
    return Database::getInstance()->lastInsertId();
}

/**
 * Check if database is installed
 */
function db_is_installed() {
    $db = Database::getInstance();
    return $db->tableExists('students_new') && 
           $db->tableExists('students_continuing') && 
           $db->tableExists('submissions') && 
           $db->tableExists('admins');
}

/**
 * Log database errors
 */
function db_log_error($error, $query = '', $params = []) {
    $log_file = __DIR__ . '/../logs/database_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] Error: $error\n";
    
    if ($query) {
        $log_entry .= "Query: $query\n";
    }
    
    if ($params) {
        $log_entry .= "Parameters: " . json_encode($params) . "\n";
    }
    
    $log_entry .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    $log_entry .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
    $log_entry .= str_repeat("-", 80) . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Set error handler for database errors
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    if (strpos($message, 'PDO') !== false || strpos($message, 'mysql') !== false) {
        db_log_error($message);
    }
    
    return false;
});
?>
