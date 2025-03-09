<?php
/**
 * Vegan Messenger Social Network
 * Database Connection Class
 */

namespace VeganMessenger;

class Database {
    /**
     * @var \PDO The PDO connection instance
     */
    private $pdo;
    
    /**
     * @var array Database configuration
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param string $driver The database driver (mysql, pgsql, etc.)
     * @param string $host The database host
     * @param int $port The database port
     * @param string $database The database name
     * @param string $username The database username
     * @param string $password The database password
     * @param array $options Additional PDO options
     */
    public function __construct($driver, $host, $port, $database, $username, $password, array $options = []) {
        $this->config = [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'options' => $options
        ];
        
        $this->connect();
    }
    
    /**
     * Connect to the database
     * 
     * @return void
     * @throws \PDOException When connection fails
     */
    private function connect() {
        $dsn = $this->buildDsn();
        
        // Default PDO options for better security and performance
        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => true
        ];
        
        // Merge default options with user provided options
        $options = \array_merge($defaultOptions, $this->config['options']);
        
        try {
            $this->pdo = new \PDO($dsn, $this->config['username'], $this->config['password'], $options);
        } catch (\PDOException $e) {
            throw new \PDOException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Build the DSN string based on the database driver
     * 
     * @return string The DSN string
     */
    private function buildDsn() {
        switch ($this->config['driver']) {
            case 'mysql':
                return 'mysql:host=' . $this->config['host'] . ';port=' . $this->config['port'] . ';dbname=' . $this->config['database'] . ';charset=utf8mb4';
            
            case 'pgsql':
                return 'pgsql:host=' . $this->config['host'] . ';port=' . $this->config['port'] . ';dbname=' . $this->config['database'];
            
            case 'sqlite':
                return 'sqlite:' . $this->config['database'];
            
            default:
                throw new \InvalidArgumentException('Unsupported database driver: ' . $this->config['driver']);
        }
    }
    
    /**
     * Get the PDO instance
     * 
     * @return \PDO The PDO instance
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Execute a query and return the result
     * 
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @param bool $fetch Whether to fetch all results
     * @return mixed The query result or statement object
     */
    public function query($query, array $params = [], $fetch = true) {
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute($params);
            
            return $fetch ? $statement->fetchAll() : $statement;
        } catch (\PDOException $e) {
            throw new \PDOException('Query execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Execute a query and return a single row
     * 
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @return array|null The result row or null if not found
     */
    public function queryOne($query, array $params = []) {
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute($params);
            
            return $statement->fetch() ?: null;
        } catch (\PDOException $e) {
            throw new \PDOException('Query execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Execute a query and return a single value
     * 
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @return mixed The result value or null if not found
     */
    public function queryValue($query, array $params = []) {
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute($params);
            
            return $statement->fetchColumn() ?: null;
        } catch (\PDOException $e) {
            throw new \PDOException('Query execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Execute an insert query and return the last insert ID
     * 
     * @param string $table The table name
     * @param array $data The data to insert
     * @return int The last insert ID
     */
    public function insert($table, array $data) {
        try {
            $columns = \array_keys($data);
            $placeholders = \array_map(function($column) {
                return ':' . $column;
            }, $columns);
            
            $query = 'INSERT INTO ' . $table . ' (' . \implode(', ', $columns) . ') VALUES (' . \implode(', ', $placeholders) . ')';
            
            $statement = $this->pdo->prepare($query);
            
            foreach ($data as $column => $value) {
                $statement->bindValue(':' . $column, $value);
            }
            
            $statement->execute();
            
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \PDOException('Insert operation failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Execute an update query and return the number of affected rows
     * 
     * @param string $table The table name
     * @param array $data The data to update
     * @param string $where The WHERE clause
     * @param array $params The WHERE clause parameters
     * @return int The number of affected rows
     */
    public function update($table, array $data, $where, array $params = []) {
        try {
            $sets = [];
            
            foreach ($data as $column => $value) {
                $sets[] = $column . ' = :set_' . $column;
            }
            
            $query = 'UPDATE ' . $table . ' SET ' . \implode(', ', $sets) . ' WHERE ' . $where;
            
            $statement = $this->pdo->prepare($query);
            
            // Bind SET values
            foreach ($data as $column => $value) {
                $statement->bindValue(':set_' . $column, $value);
            }
            
            // Bind WHERE params
            foreach ($params as $param => $value) {
                $statement->bindValue(':' . $param, $value);
            }
            
            $statement->execute();
            
            return $statement->rowCount();
        } catch (\PDOException $e) {
            throw new \PDOException('Update operation failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Execute a delete query and return the number of affected rows
     * 
     * @param string $table The table name
     * @param string $where The WHERE clause
     * @param array $params The WHERE clause parameters
     * @return int The number of affected rows
     */
    public function delete($table, $where, array $params = []) {
        try {
            $query = 'DELETE FROM ' . $table . ' WHERE ' . $where;
            
            $statement = $this->pdo->prepare($query);
            $statement->execute($params);
            
            return $statement->rowCount();
        } catch (\PDOException $e) {
            throw new \PDOException('Delete operation failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Begin a transaction
     * 
     * @return bool True on success
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit a transaction
     * 
     * @return bool True on success
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback a transaction
     * 
     * @return bool True on success
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Check if a transaction is active
     * 
     * @return bool True if a transaction is active
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Get the last insert ID
     * 
     * @param string $name Optional name of the sequence object
     * @return string The last insert ID
     */
    public function lastInsertId($name = null) {
        return $this->pdo->lastInsertId($name);
    }
    
    /**
     * Quote a string for use in a query
     * 
     * @param string $string The string to quote
     * @param int $parameterType The data type
     * @return string The quoted string
     */
    public function quote($string, $parameterType = \PDO::PARAM_STR) {
        return $this->pdo->quote($string, $parameterType);
    }
} 