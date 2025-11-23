<?php
/**
 * Vérification de la structure de la base de données
 */

require_once 'config.php';
require_once 'database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== VÉRIFICATION STRUCTURE BASE DE DONNÉES ===\n\n";

try {
    $pdo = Database::getInstance();
    
    // Vérifier la structure de la table planning
    echo "--- Structure table PLANNING ---\n";
    $stmt = $pdo->query("DESCRIBE " . DB_PREFIX . "planning");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "{$col['Field']}: {$col['Type']} ";
        echo ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
        echo ($col['Key'] ? " [{$col['Key']}]" : "");
        echo ($col['Default'] !== null ? " DEFAULT {$col['Default']}" : "");
        echo "\n";
    }
    
    // Vérifier les index et clés
    echo "\n--- Index et Contraintes ---\n";
    $stmt = $pdo->query("SHOW INDEX FROM " . DB_PREFIX . "planning");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($indexes as $idx) {
        echo "{$idx['Key_name']}: {$idx['Column_name']} (Non_unique: {$idx['Non_unique']})\n";
    }
    
    // Vérifier s'il y a des contraintes UNIQUE
    echo "\n--- Contraintes UNIQUE problématiques ? ---\n";
    $uniqueIndexes = array_filter($indexes, function($idx) {
        return $idx['Non_unique'] == 0 && $idx['Key_name'] != 'PRIMARY';
    });
    
    if (empty($uniqueIndexes)) {
        echo "✅ Aucune contrainte UNIQUE (OK)\n";
    } else {
        echo "⚠️ CONTRAINTES UNIQUE TROUVÉES:\n";
        foreach ($uniqueIndexes as $idx) {
            echo "  - {$idx['Key_name']} sur {$idx['Column_name']}\n";
        }
        echo "\n❌ Ces contraintes UNIQUE peuvent empêcher les insertions !\n";
        echo "Solution: Supprimer les contraintes UNIQUE qui bloquent.\n";
    }
    
    // Compter les lignes dans la table
    echo "\n--- Données actuelles ---\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "planning");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Nombre d'attributions: {$count['total']}\n";
    
    // Afficher quelques exemples
    echo "\n--- Exemples d'attributions ---\n";
    $stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "planning LIMIT 5");
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($examples)) {
        echo "Aucune attribution en base\n";
    } else {
        foreach ($examples as $ex) {
            echo "ID {$ex['id']}: Date={$ex['date']}, Période={$ex['periode']}, ";
            echo "Tournée={$ex['tournee_id']}, Conducteur={$ex['conducteur_id']}, ";
            echo "Score={$ex['score_ia']}\n";
        }
    }
    
    // Test d'insertion
    echo "\n--- TEST D'INSERTION ---\n";
    try {
        // Essayer d'insérer une ligne de test
        $testDate = date('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO " . DB_PREFIX . "planning 
            (date, periode, tournee_id, conducteur_id, score_ia, statut) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $testDate,
            'matin',
            999, // ID tournée fictif
            999, // ID conducteur fictif
            50,
            'planifie'
        ]);
        
        if ($result) {
            $insertId = $pdo->lastInsertId();
            echo "✅ Insertion réussie (ID: $insertId)\n";
            
            // Supprimer la ligne de test
            $pdo->prepare("DELETE FROM " . DB_PREFIX . "planning WHERE id = ?")->execute([$insertId]);
            echo "✅ Ligne de test supprimée\n";
        }
    } catch (PDOException $e) {
        echo "❌ ERREUR INSERTION: {$e->getMessage()}\n";
        echo "Code erreur: {$e->getCode()}\n";
        
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "\n⚠️ PROBLÈME DÉTECTÉ: Contrainte UNIQUE bloque les insertions!\n";
            echo "Il faut supprimer la contrainte UNIQUE sur (date, periode, tournee_id)\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERREUR: {$e->getMessage()}\n";
}

echo "\n=== FIN VÉRIFICATION ===\n";
