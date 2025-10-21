<?php
/**
 * Classe Database - Singleton PDO avec gestion UTF-8
 */
class Database {
    private static $instance = null;
    private $pdo;
    private static $queryCount = 0;
    private static $queryLog = [];

    private function __construct() {
        if (!defined('DB_HOST')) {
            throw new Exception("Configuration manquante. Accédez à install.php");
        }

        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true, // Connexions persistantes
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_FOUND_ROWS   => true
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Erreur PDO : " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    // Méthode pour préparer une requête avec logging
    public static function prepare($sql, $params = []) {
        $pdo = self::getInstance();
        $start = microtime(true);
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if (APP_DEBUG) {
                self::logQuery($sql, $params, microtime(true) - $start);
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            self::logQuery($sql, $params, microtime(true) - $start, $e->getMessage());
            throw $e;
        }
    }

    private static function logQuery($sql, $params, $duration, $error = null) {
        self::$queryCount++;
        self::$queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => round($duration * 1000, 2), // en ms
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $error
        ];
        
        if (count(self::$queryLog) > 100) {
            array_shift(self::$queryLog); // Garde les 100 dernières requêtes
        }
    }

    public static function getQueryStats() {
        return [
            'count' => self::$queryCount,
            'log' => self::$queryLog
        ];
    }

    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Désérialisation interdite");
    }
}
