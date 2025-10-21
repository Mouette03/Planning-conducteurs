<?php
/**
 * Classe Database - Singleton PDO avec gestion UTF-8
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        if (!defined('DB_HOST')) {
            die("Configuration manquante. Accédez à install.php");
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
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->pdo->exec("SET NAMES utf8mb4");
            
        } catch (PDOException $e) {
            error_log("Erreur PDO : " . $e->getMessage());
            die("Erreur de connexion à la base de données.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Désérialisation interdite");
    }
}
