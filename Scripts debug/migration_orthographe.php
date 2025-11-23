<?php
/**
 * Interface web pour la migration orthographe des durées
 * ATTENTION : Supprimer ce fichier après utilisation !
 */

// Pas de vérification de sécurité - migration unique
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Orthographe Durées</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">🔄 Migration : Orthographe des Durées</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Cette migration va :</strong>
                            <ul class="mb-0 mt-2">
                                <li>"apres-midi" → "après-midi" (avec accent)</li>
                                <li>"journee" → "journée" (avec accent)</li>
                                <li>"matin-et-apres-midi" → "matin et après-midi" (sans tirets, avec accents)</li>
                            </ul>
                        </div>

                        <?php
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            require_once 'database.php';
                            require_once 'config.php';
                            
                            try {
                                $pdo = Database::getInstance();
                                
                                echo '<div class="alert alert-primary"><strong>Démarrage de la migration...</strong></div>';
                                
                                // Étape 1
                                echo '<h5>1. Type actuel de la colonne</h5>';
                                $stmt = $pdo->query("SHOW COLUMNS FROM " . DB_PREFIX . "tournees WHERE Field = 'duree'");
                                $column = $stmt->fetch(PDO::FETCH_ASSOC);
                                echo '<pre>Type: ' . htmlspecialchars($column['Type']) . '</pre>';
                                
                                // Étape 2
                                echo '<h5>2. Conversion en VARCHAR</h5>';
                                $pdo->exec("ALTER TABLE " . DB_PREFIX . "tournees MODIFY COLUMN duree VARCHAR(50) DEFAULT 'journée'");
                                echo '<div class="alert alert-success">✓ Converti en VARCHAR(50)</div>';
                                
                                // Étape 3
                                echo '<h5>3. Mise à jour des données</h5>';
                                
                                // Compter
                                $stmt = $pdo->query("SELECT COUNT(*) as nb FROM " . DB_PREFIX . "tournees WHERE duree = 'apres-midi'");
                                $count1 = $stmt->fetch()['nb'];
                                
                                $stmt = $pdo->query("SELECT COUNT(*) as nb FROM " . DB_PREFIX . "tournees WHERE duree = 'journee'");
                                $count2 = $stmt->fetch()['nb'];
                                
                                $stmt = $pdo->query("SELECT COUNT(*) as nb FROM " . DB_PREFIX . "tournees WHERE duree = 'matin-et-apres-midi'");
                                $count3 = $stmt->fetch()['nb'];
                                
                                echo '<ul>';
                                echo "<li>'apres-midi' → 'après-midi' : <strong>$count1</strong> tournée(s)</li>";
                                echo "<li>'journee' → 'journée' : <strong>$count2</strong> tournée(s)</li>";
                                echo "<li>'matin-et-apres-midi' → 'matin et après-midi' : <strong>$count3</strong> tournée(s)</li>";
                                echo '</ul>';
                                
                                // Mettre à jour
                                $pdo->exec("UPDATE " . DB_PREFIX . "tournees SET duree = 'après-midi' WHERE duree = 'apres-midi'");
                                $pdo->exec("UPDATE " . DB_PREFIX . "tournees SET duree = 'journée' WHERE duree = 'journee'");
                                $pdo->exec("UPDATE " . DB_PREFIX . "tournees SET duree = 'matin et après-midi' WHERE duree = 'matin-et-apres-midi'");
                                
                                echo '<div class="alert alert-success">✓ Données mises à jour</div>';
                                
                                // Étape 4
                                echo '<h5>4. Liste des tournées après migration</h5>';
                                $stmt = $pdo->query("SELECT id, nom, duree FROM " . DB_PREFIX . "tournees ORDER BY id");
                                $tournees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                echo '<table class="table table-sm table-striped">';
                                echo '<thead><tr><th>ID</th><th>Nom</th><th>Durée</th></tr></thead><tbody>';
                                foreach ($tournees as $t) {
                                    echo '<tr>';
                                    echo '<td>T' . $t['id'] . '</td>';
                                    echo '<td>' . htmlspecialchars($t['nom']) . '</td>';
                                    echo '<td><strong>' . htmlspecialchars($t['duree']) . '</strong></td>';
                                    echo '</tr>';
                                }
                                echo '</tbody></table>';
                                
                                echo '<div class="alert alert-success mt-4"><strong>✅ MIGRATION TERMINÉE AVEC SUCCÈS !</strong></div>';
                                
                                echo '<div class="alert alert-warning">';
                                echo '<strong>⚠️ IMPORTANT :</strong> Supprimez maintenant le fichier <code>migration_orthographe.php</code> pour des raisons de sécurité.';
                                echo '</div>';
                                
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger">';
                                echo '<strong>❌ ERREUR :</strong> ' . htmlspecialchars($e->getMessage());
                                echo '</div>';
                            }
                        } else {
                            ?>
                            <form method="POST">
                                <div class="alert alert-warning">
                                    <strong>⚠️ Attention :</strong> Cette migration va modifier la structure de la base de données et mettre à jour toutes les tournées existantes.
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    🚀 Lancer la Migration
                                </button>
                            </form>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
