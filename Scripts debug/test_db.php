<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'database.php';

try {
    // Test de connexion
    $pdo = Database::getInstance();
    echo "Connexion à la base de données réussie!<br>";
    
    // Vérification de l'existence de la table users
    $tableName = DB_PREFIX . 'users';
    $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
    if ($stmt->rowCount() > 0) {
        echo "La table $tableName existe!<br>";
        
        // Vérification de la structure de la table
        $stmt = $pdo->query("DESCRIBE $tableName");
        echo "Structure de la table $tableName:<br>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} - {$row['Type']}<br>";
        }
    } else {
        echo "La table $tableName n'existe pas!<br>";
        echo "Tentative de création de la table...<br>";
        
        // Création de la table avec le bon préfixe
        $sql = str_replace('pc_users', DB_PREFIX . 'users', file_get_contents('sql/users.sql'));
        $pdo->exec($sql);
        echo "Table créée avec succès!<br>";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}