<?php
/**
 * Migration : Ajouter la valeur 'matin-et-apres-midi' à la colonne duree de la table tournees
 * Date : 13 novembre 2025
 * Raison : Permettre aux tournées d'avoir 2 tours séparés (matin + après-midi)
 */

// Trouver le fichier database.php en remontant les dossiers
$possiblePaths = [
    __DIR__ . '/../database.php',
    __DIR__ . '/../../database.php',
    dirname(__DIR__) . '/database.php',
    './database.php',
    '../database.php',
];

$databasePath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $databasePath = $path;
        break;
    }
}

if (!$databasePath) {
    die("ERREUR: Impossible de trouver database.php. Veuillez utiliser le fichier migration_duree.php à la racine du projet.");
}

require_once $databasePath;
require_once dirname($databasePath) . '/config.php';

echo "=== MIGRATION : Ajout de 'matin-et-apres-midi' dans la colonne duree ===\n\n";

try {
    $pdo = Database::getInstance();
    
    // 1. Vérifier la structure actuelle de la colonne
    echo "1. Vérification de la structure actuelle...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "tournees LIKE 'duree'");
    $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnInfo) {
        echo "   Colonne 'duree' trouvée\n";
        echo "   Type actuel : " . $columnInfo['Type'] . "\n";
        echo "   NULL : " . $columnInfo['Null'] . "\n";
        echo "   Default : " . ($columnInfo['Default'] ?? 'NULL') . "\n\n";
        
        // 2. Vérifier si c'est un ENUM
        if (strpos($columnInfo['Type'], 'enum') === 0) {
            echo "   ⚠️  La colonne est un ENUM (liste limitée de valeurs)\n";
            echo "   Il faut la modifier pour accepter 'matin-et-apres-midi'\n\n";
            
            // 3. Modifier le ENUM pour ajouter la nouvelle valeur
            echo "2. Modification du ENUM...\n";
            $sql = "ALTER TABLE " . DB_PREFIX . "tournees 
                    MODIFY COLUMN duree ENUM('matin', 'apres-midi', 'journee', 'matin-et-apres-midi') 
                    DEFAULT 'journee'";
            
            $pdo->exec($sql);
            echo "   ✅ ENUM modifié avec succès\n\n";
            
        } else if (strpos($columnInfo['Type'], 'varchar') === 0) {
            echo "   ✅ La colonne est déjà de type VARCHAR, elle accepte toutes les valeurs\n";
            echo "   Aucune modification nécessaire\n\n";
        } else {
            echo "   Type inconnu : " . $columnInfo['Type'] . "\n";
            echo "   Conversion en VARCHAR pour plus de flexibilité...\n";
            $sql = "ALTER TABLE " . DB_PREFIX . "tournees 
                    MODIFY COLUMN duree VARCHAR(50) DEFAULT 'journee'";
            $pdo->exec($sql);
            echo "   ✅ Colonne convertie en VARCHAR(50)\n\n";
        }
        
        // 4. Vérifier la nouvelle structure
        echo "3. Vérification de la nouvelle structure...\n";
        $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "tournees LIKE 'duree'");
        $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Type final : " . $columnInfo['Type'] . "\n";
        echo "   ✅ Migration terminée avec succès !\n\n";
        
        // 5. Lister les tournées existantes
        echo "4. Tournées existantes :\n";
        $stmt = $pdo->query("SELECT id, nom, duree FROM " . DB_PREFIX . "tournees ORDER BY id");
        $tournees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tournees as $t) {
            $duree = $t['duree'] ?? 'NULL';
            echo "   - #{$t['id']}: {$t['nom']} → duree = '$duree'\n";
        }
        
        echo "\n✅ MIGRATION TERMINÉE AVEC SUCCÈS\n";
        echo "Vous pouvez maintenant utiliser 'matin-et-apres-midi' dans vos tournées.\n";
        
    } else {
        echo "❌ ERREUR : Colonne 'duree' non trouvée dans la table tournees\n";
        echo "Création de la colonne...\n";
        
        $sql = "ALTER TABLE " . DB_PREFIX . "tournees 
                ADD COLUMN duree VARCHAR(50) DEFAULT 'journee'";
        $pdo->exec($sql);
        echo "✅ Colonne 'duree' créée avec succès\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR LORS DE LA MIGRATION :\n";
    echo $e->getMessage() . "\n";
    echo "\nDétails techniques :\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== FIN DE LA MIGRATION ===\n";
