<?php
/**
 * Migration : Ajout de la colonne date_embauche
 * Remplace le champ experience (manuel) par un calcul automatique
 */

require_once 'config.php';
require_once 'database.php';

$pdo = Database::getInstance();

echo "<h1>Migration : Date d'embauche</h1>";

try {
    // Vérifier si la colonne existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "conducteurs LIKE 'date_embauche'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ La colonne 'date_embauche' existe déjà. Migration déjà effectuée.</p>";
        echo "<p><a href='index.php'>Retour à l'application</a></p>";
        exit;
    }
    
    // Ajouter la colonne date_embauche
    $pdo->exec("ALTER TABLE " . DB_PREFIX . "conducteurs 
                ADD COLUMN date_embauche DATE NULL COMMENT 'Date d\\'entrée dans l\\'entreprise' 
                AFTER experience");
    
    echo "<p style='color: green;'>✅ Colonne 'date_embauche' ajoutée avec succès</p>";
    
    // Convertir les anciennes valeurs d'expérience en dates approximatives
    $stmt = $pdo->query("SELECT id, experience FROM " . DB_PREFIX . "conducteurs WHERE experience > 0");
    $conducteurs = $stmt->fetchAll();
    
    $nbConverts = 0;
    foreach ($conducteurs as $c) {
        // Calculer une date approximative : aujourd'hui - X années
        $dateEmbauche = date('Y-m-d', strtotime("-{$c['experience']} years"));
        
        $update = $pdo->prepare("UPDATE " . DB_PREFIX . "conducteurs SET date_embauche = ? WHERE id = ?");
        $update->execute([$dateEmbauche, $c['id']]);
        $nbConverts++;
    }
    
    echo "<p style='color: green;'>✅ {$nbConverts} conducteur(s) : expérience convertie en date d'embauche approximative</p>";
    
    echo "<div style='background: #e7f3ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0d6efd;'>";
    echo "<h3>📝 Prochaines étapes :</h3>";
    echo "<ol>";
    echo "<li>Vérifiez les dates d'embauche dans la gestion des conducteurs</li>";
    echo "<li>Corrigez les dates approximatives si nécessaire</li>";
    echo "<li>L'ancienneté sera maintenant calculée automatiquement</li>";
    echo "<li>Supprimez ce fichier après vérification : <code>migration_date_embauche.php</code></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<p><strong>✨ Migration terminée avec succès !</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='index.php'>Retour à l'application</a></p>";
