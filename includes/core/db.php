<?php
/**
 * Database Connection Handler
 * Singleton pattern for PDO connection
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                error_log("Database connection failed: " . $e->getMessage());
                die("Database connection error. Please contact support.");
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
        return $this->pdo;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Base Model class
 * All models extend this for basic CRUD operations
 */
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find record by ID
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find all records with optional conditions
     */
    public function findAll($where = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Create new record
     */
    public function create($data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->db->lastInsertId();
    }

    /**
     * Update record
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) .
               " WHERE {$this->primaryKey} = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete record
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Execute raw query (returns all rows)
     */
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute raw query (returns single row)
     */
    public function queryOne($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
}
