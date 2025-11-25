<?php
require_once 'config.php';
require_once 'database.php';

$pdo = Database::getInstance();
$stmt = $pdo->query("SELECT id, nom, prenom, tournees_maitrisees, tournee_titulaire FROM " . DB_PREFIX . "conducteurs ORDER BY id");
$conducteurs = $stmt->fetchAll();

echo "<h1>Vérification des tournées maîtrisées</h1>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Tournées maîtrisées (JSON)</th><th>Tournée titulaire</th></tr>";

foreach ($conducteurs as $c) {
    echo "<tr>";
    echo "<td>{$c['id']}</td>";
    echo "<td>{$c['nom']}</td>";
    echo "<td>{$c['prenom']}</td>";
    echo "<td>" . htmlspecialchars($c['tournees_maitrisees'] ?? 'NULL') . "</td>";
    echo "<td>{$c['tournee_titulaire']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h2>Décodage des tournées maîtrisées</h2>";

foreach ($conducteurs as $c) {
    echo "<h3>{$c['prenom']} {$c['nom']} (ID: {$c['id']})</h3>";
    echo "<p>Valeur brute: <code>" . htmlspecialchars($c['tournees_maitrisees'] ?? 'NULL') . "</code></p>";
    
    if (!empty($c['tournees_maitrisees'])) {
        $decoded = json_decode($c['tournees_maitrisees'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded) && count($decoded) > 0) {
                echo "<p>✅ Décodé avec succès: " . implode(', ', $decoded) . "</p>";
            } else {
                echo "<p>⚠️ Tableau vide</p>";
            }
        } else {
            echo "<p>❌ Erreur décodage: " . json_last_error_msg() . "</p>";
        }
    } else {
        echo "<p>⚪ Aucune donnée (NULL ou vide)</p>";
    }
}
?>
