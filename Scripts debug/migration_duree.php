<?php
/**
 * Interface web pour exécuter la migration de la colonne duree
 * ATTENTION : Supprimer ce fichier après utilisation !
 */

// Pas de vérification de sécurité - migration unique
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Base de Données</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            max-height: 600px;
            overflow-y: auto;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>🔧 Migration : Ajout de 'matin-et-apres-midi'</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Cette migration va :</strong>
                    <ul class="mb-0">
                        <li>Vérifier la structure de la colonne <code>duree</code> dans la table <code>tournees</code></li>
                        <li>Modifier le type ENUM pour accepter la valeur <code>'matin-et-apres-midi'</code></li>
                        <li>Permettre aux tournées d'avoir 2 tours séparés (matin + après-midi)</li>
                    </ul>
                </div>

                <h5>Résultat de la migration :</h5>
                <pre><?php

require_once 'database.php';
require_once 'config.php';

try {
    $pdo = Database::getInstance();
    
    echo "=== MIGRATION : Ajout de 'matin-et-apres-midi' dans la colonne duree ===\n\n";
    
    // 1. Vérifier la structure actuelle
    echo "1. Vérification de la structure actuelle...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "tournees LIKE 'duree'");
    $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnInfo) {
        echo "   ✓ Colonne 'duree' trouvée\n";
        echo "   Type actuel : " . $columnInfo['Type'] . "\n";
        echo "   Default : " . ($columnInfo['Default'] ?? 'NULL') . "\n\n";
        
        // 2. Vérifier si c'est un ENUM
        if (strpos($columnInfo['Type'], 'enum') === 0) {
            echo "   ⚠️  La colonne est un ENUM (liste limitée de valeurs)\n";
            echo "   → Modification nécessaire\n\n";
            
            // 3. Modifier le ENUM
            echo "2. Modification du ENUM...\n";
            $sql = "ALTER TABLE " . DB_PREFIX . "tournees 
                    MODIFY COLUMN duree ENUM('matin', 'apres-midi', 'journee', 'matin-et-apres-midi') 
                    DEFAULT 'journee'";
            
            $pdo->exec($sql);
            echo "   ✅ ENUM modifié avec succès !\n\n";
            
        } else if (strpos($columnInfo['Type'], 'varchar') === 0) {
            echo "   ✅ La colonne est déjà de type VARCHAR\n";
            echo "   → Aucune modification nécessaire\n\n";
        } else {
            echo "   Type inconnu : " . $columnInfo['Type'] . "\n";
            echo "   → Conversion en VARCHAR...\n";
            $sql = "ALTER TABLE " . DB_PREFIX . "tournees 
                    MODIFY COLUMN duree VARCHAR(50) DEFAULT 'journee'";
            $pdo->exec($sql);
            echo "   ✅ Colonne convertie en VARCHAR(50)\n\n";
        }
        
        // 4. Vérifier la nouvelle structure
        echo "3. Vérification de la nouvelle structure...\n";
        $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "tournees LIKE 'duree'");
        $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Type final : " . $columnInfo['Type'] . "\n\n";
        
        // 5. Lister les tournées
        echo "4. Tournées existantes :\n";
        $stmt = $pdo->query("SELECT id, nom, duree FROM " . DB_PREFIX . "tournees ORDER BY id");
        $tournees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($tournees) > 0) {
            foreach ($tournees as $t) {
                $duree = $t['duree'] ?? 'NULL';
                echo sprintf("   - #%-2d %-20s → duree = '%s'\n", $t['id'], $t['nom'], $duree);
            }
        } else {
            echo "   (Aucune tournée trouvée)\n";
        }
        
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║  ✅ MIGRATION TERMINÉE AVEC SUCCÈS !                        ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
        echo "Vous pouvez maintenant :\n";
        echo "  • Modifier vos tournées pour utiliser 'matin-et-apres-midi'\n";
        echo "  • Ces tournées afficheront 2 cases séparées dans le planning\n";
        echo "  • L'IA prendra en compte les 2 périodes distinctes\n";
        
    } else {
        echo "❌ ERREUR : Colonne 'duree' non trouvée\n";
        echo "   → Création de la colonne...\n";
        
        $sql = "ALTER TABLE " . DB_PREFIX . "tournees 
                ADD COLUMN duree VARCHAR(50) DEFAULT 'journee'";
        $pdo->exec($sql);
        echo "   ✅ Colonne créée avec succès\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR LORS DE LA MIGRATION :\n";
    echo $e->getMessage() . "\n\n";
    echo "Détails techniques :\n";
    echo $e->getTraceAsString() . "\n";
}

?></pre>

                <div class="mt-3">
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Retour au planning
                    </a>
                    <button onclick="location.reload()" class="btn btn-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Réexécuter la migration
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
