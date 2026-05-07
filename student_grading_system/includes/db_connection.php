<?php
// includes/db_connection.php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                die("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database Error: " . $e->getMessage());
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
    
    public function prepare($sql) {
        $result = $this->connection->prepare($sql);
        if (!$result) {
            error_log("Prepare failed: " . $this->connection->error);
        }
        return $result;
    }
    
    public function query($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            error_log("Query failed: " . $this->connection->error);
        }
        return $result;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    public function commit() {
        $this->connection->commit();
    }
    
    public function rollback() {
        $this->connection->rollback();
    }
    
    public function error() {
        return $this->connection->error;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Global helper functions
function db() {
    return Database::getInstance()->getConnection();
}

function prepare($sql) {
    return Database::getInstance()->prepare($sql);
}

function query($sql) {
    return Database::getInstance()->query($sql);
}

function escape($string) {
    return Database::getInstance()->escape($string);
}

function lastInsertId() {
    return Database::getInstance()->lastInsertId();
}

function beginTransaction() {
    return Database::getInstance()->beginTransaction();
}

function commit() {
    return Database::getInstance()->commit();
}

function rollback() {
    return Database::getInstance()->rollback();
}

function db_error() {
    return Database::getInstance()->error();
}
?>