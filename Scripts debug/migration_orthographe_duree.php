<?php
/**
 * Migration : Modifier l'orthographe des valeurs de durée
 * - "apres-midi" → "après-midi" (avec accent)
 * - "journee" → "journée" (avec accent)
 * - "matin-et-apres-midi" → "matin et après-midi" (sans tirets, avec accents)
 * 
 * ATTENTION : Exécuter ce script UNE SEULE FOIS
 */

// Vérifier que le script est exécuté en CLI
if (php_sapi_name() !== 'cli') {
    die("Ce script doit être exécuté en ligne de commande.\n");
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../config.php';

echo "=== MIGRATION : Orthographe des durées ===\n\n";

try {
    $pdo = Database::getInstance();
    
    // Étape 1 : Afficher le type actuel de la colonne
    echo "1. Type actuel de la colonne 'duree' :\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "tournees WHERE Field = 'duree'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Type: {$column['Type']}\n\n";
    
    // Étape 2 : Modifier la colonne pour utiliser VARCHAR temporairement
    echo "2. Conversion en VARCHAR pour mise à jour...\n";
    $pdo->exec("ALTER TABLE " . DB_PREFIX . "tournees MODIFY COLUMN duree VARCHAR(50) DEFAULT 'journée'");
    echo "   ✓ Converti en VARCHAR(50)\n\n";
    
    // Étape 3 : Mettre à jour les données existantes
    echo "3. Mise à jour des données existantes :\n";
    
    // Compter les tournées à modifier
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM " . DB_PREFIX . "tournees WHERE duree = 'apres-midi'");
    $count1 = $stmt->fetch()['nb'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM " . DB_PREFIX . "tournees WHERE duree = 'journee'");
    $count2 = $stmt->fetch()['nb'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM " . DB_PREFIX . "tournees WHERE duree = 'matin-et-apres-midi'");
    $count3 = $stmt->fetch()['nb'];
    
    echo "   - 'apres-midi' → 'après-midi' : $count1 tournée(s)\n";
    echo "   - 'journee' → 'journée' : $count2 tournée(s)\n";
    echo "   - 'matin-et-apres-midi' → 'matin et après-midi' : $count3 tournée(s)\n\n";
    
    // Mettre à jour
    $pdo->exec("UPDATE " . DB_PREFIX . "tournees SET duree = 'après-midi' WHERE duree = 'apres-midi'");
    $pdo->exec("UPDATE " . DB_PREFIX . "tournees SET duree = 'journée' WHERE duree = 'journee'");
    $pdo->exec("UPDATE " . DB_PREFIX . "tournees SET duree = 'matin et après-midi' WHERE duree = 'matin-et-apres-midi'");
    
    echo "   ✓ Données mises à jour\n\n";
    
    // Étape 4 : Remettre en ENUM avec les nouvelles valeurs (optionnel, on peut garder VARCHAR)
    echo "4. Conservation en VARCHAR(50) pour plus de flexibilité\n";
    echo "   (Vous pouvez créer un ENUM si vous préférez)\n\n";
    
    // Alternative : créer l'ENUM
    // $pdo->exec("ALTER TABLE " . DB_PREFIX . "tournees MODIFY COLUMN duree ENUM('matin', 'après-midi', 'journée', 'matin et après-midi') DEFAULT 'journée'");
    
    // Étape 5 : Afficher les tournées modifiées
    echo "5. Liste des tournées après migration :\n";
    $stmt = $pdo->query("SELECT id, nom, duree FROM " . DB_PREFIX . "tournees ORDER BY id");
    $tournees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tournees as $t) {
        echo "   - T{$t['id']} ({$t['nom']}): {$t['duree']}\n";
    }
    
    echo "\n✅ MIGRATION TERMINÉE AVEC SUCCÈS !\n";
    echo "\nNouvelles valeurs de durée :\n";
    echo "  - matin\n";
    echo "  - après-midi (avec accent)\n";
    echo "  - journée (avec accent)\n";
    echo "  - matin et après-midi (sans tirets, avec accents)\n\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
