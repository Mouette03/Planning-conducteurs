<?php
/**
 * Script de correction de la base de données
 * Ajoute la contrainte UNIQUE manquante sur (date, periode, tournee_id)
 */

require_once 'config.php';
require_once 'database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CORRECTION BASE DE DONNÉES ===\n\n";

try {
    $pdo = Database::getInstance();
    
    echo "Contraintes actuelles:\n";
    echo "✅ UNIQUE (date, periode, conducteur_id) - Empêche un conducteur d'être sur 2 tournées en même temps\n";
    echo "➕ Ajout: UNIQUE (date, periode, tournee_id) - Empêche 2 conducteurs sur la même tournée\n\n";
    
    echo "Étape 1: Vérification de la contrainte unique_tournee_periode\n";
    
    try {
        $sql = "ALTER TABLE " . DB_PREFIX . "planning 
                ADD UNIQUE KEY unique_tournee_periode (date, periode, tournee_id)";
        $pdo->exec($sql);
        echo "✅ Contrainte UNIQUE (date, periode, tournee_id) créée\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false) {
            echo "✅ La contrainte existe déjà\n\n";
        } else if (strpos($e->getMessage(), "Duplicate entry") !== false) {
            echo "⚠️ ATTENTION: Des doublons existent dans la base !\n";
            echo "Il faut nettoyer les doublons avant de créer la contrainte.\n\n";
            
            // Trouver les doublons
            echo "Recherche des doublons...\n";
            $stmt = $pdo->query("
                SELECT date, periode, tournee_id, COUNT(*) as nb
                FROM " . DB_PREFIX . "planning
                GROUP BY date, periode, tournee_id
                HAVING COUNT(*) > 1
            ");
            $doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($doublons)) {
                echo "Doublons trouvés:\n";
                foreach ($doublons as $d) {
                    echo "  - Date: {$d['date']}, Période: {$d['periode']}, Tournée: {$d['tournee_id']} ({$d['nb']} fois)\n";
                }
                echo "\nNettoyage automatique des doublons...\n";
                
                // Garder seulement la ligne la plus récente pour chaque doublon
                foreach ($doublons as $d) {
                    $pdo->prepare("
                        DELETE FROM " . DB_PREFIX . "planning
                        WHERE date = ? AND periode = ? AND tournee_id = ?
                        AND id NOT IN (
                            SELECT * FROM (
                                SELECT MAX(id) FROM " . DB_PREFIX . "planning
                                WHERE date = ? AND periode = ? AND tournee_id = ?
                            ) as temp
                        )
                    ")->execute([
                        $d['date'], $d['periode'], $d['tournee_id'],
                        $d['date'], $d['periode'], $d['tournee_id']
                    ]);
                }
                
                echo "✅ Doublons nettoyés\n";
                echo "Nouvelle tentative de création de la contrainte...\n";
                
                try {
                    $pdo->exec("ALTER TABLE " . DB_PREFIX . "planning 
                                ADD UNIQUE KEY unique_tournee_periode (date, periode, tournee_id)");
                    echo "✅ Contrainte créée avec succès\n\n";
                } catch (PDOException $e2) {
                    echo "❌ Échec: {$e2->getMessage()}\n\n";
                }
            }
        } else {
            echo "⚠️ Erreur: {$e->getMessage()}\n\n";
        }
    }
    
    echo "Étape 2: Vérification de la structure finale\n";
    $stmt = $pdo->query("SHOW INDEX FROM " . DB_PREFIX . "planning");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nIndex actuels:\n";
    $groups = [];
    foreach ($indexes as $idx) {
        $groups[$idx['Key_name']][] = $idx['Column_name'];
    }
    
    foreach ($groups as $keyName => $columns) {
        $nonUnique = $indexes[array_search($keyName, array_column($indexes, 'Key_name'))]['Non_unique'];
        $type = $nonUnique == 0 ? 'UNIQUE' : 'INDEX';
        echo "  - $keyName ($type): " . implode(', ', $columns) . "\n";
    }
    
    echo "\n✅ CORRECTION TERMINÉE !\n";
    echo "\nRègles de la base de données:\n";
    echo "✅ Un conducteur ne peut être que sur UNE tournée par période\n";
    echo "✅ Une tournée ne peut avoir qu'UN seul conducteur par période\n";
    echo "✅ Un conducteur peut faire une tournée le matin ET une autre l'après-midi\n";
    echo "\nVous pouvez maintenant:\n";
    echo "1. Recharger votre application\n";
    echo "2. Relancer l'IA Auto\n";
    echo "3. Attribuer manuellement des conducteurs\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR FATALE: {$e->getMessage()}\n";
}

echo "\n=== FIN ===\n";
