<?php
/**
 * Script de nettoyage du planning (version web)
 * 
 * Supprime toutes les attributions de conducteurs dans le planning
 * Remet le taux d'occupation à 0%
 */

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

// Vérifier que l'utilisateur est admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('❌ Accès refusé. Vous devez être administrateur.');
}

$action = $_GET['action'] ?? 'info';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyage du Planning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-trash me-2"></i>Nettoyage du Planning</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $pdo = Database::getInstance();
                            
                            if ($action === 'info') {
                                // Afficher les informations
                                $stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "planning WHERE conducteur_id IS NOT NULL");
                                $nbTotal = $stmt->fetchColumn();
                                
                                $stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "planning");
                                $nbLignes = $stmt->fetchColumn();
                                
                                // Attributions de la semaine en cours
                                $debutSemaine = date('Y-m-d', strtotime('monday this week'));
                                $finSemaine = date('Y-m-d', strtotime('sunday this week'));
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "planning WHERE date BETWEEN ? AND ? AND conducteur_id IS NOT NULL");
                                $stmt->execute([$debutSemaine, $finSemaine]);
                                $nbSemaine = $stmt->fetchColumn();
                                
                                echo '<div class="alert alert-info">';
                                echo '<h5><i class="bi bi-info-circle me-2"></i>État actuel du planning</h5>';
                                echo '<ul class="mb-0">';
                                echo "<li><strong>Total de lignes dans la table planning :</strong> $nbLignes</li>";
                                echo "<li><strong>Attributions avec conducteur :</strong> $nbTotal</li>";
                                echo "<li><strong>Attributions cette semaine :</strong> $nbSemaine</li>";
                                echo '</ul>';
                                echo '</div>';
                                
                                if ($nbTotal > 0) {
                                    echo '<div class="alert alert-warning">';
                                    echo '<h5><i class="bi bi-exclamation-triangle me-2"></i>Attention</h5>';
                                    echo '<p>Cette action va supprimer <strong>TOUTES les ' . $nbTotal . ' attributions</strong> de conducteurs dans le planning.</p>';
                                    echo '<p class="mb-0">Les lignes du planning resteront en base de données, mais les conducteurs seront retirés et les scores IA remis à 0.</p>';
                                    echo '</div>';
                                    
                                    echo '<div class="d-grid gap-2">';
                                    echo '<a href="?action=nettoyer" class="btn btn-danger btn-lg">';
                                    echo '<i class="bi bi-trash me-2"></i>Confirmer le nettoyage';
                                    echo '</a>';
                                    echo '<a href="index.php" class="btn btn-secondary">';
                                    echo '<i class="bi bi-arrow-left me-2"></i>Annuler et retourner au planning';
                                    echo '</a>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="alert alert-success">';
                                    echo '<h5><i class="bi bi-check-circle me-2"></i>Planning déjà vide</h5>';
                                    echo '<p class="mb-0">Il n\'y a aucune attribution à supprimer.</p>';
                                    echo '</div>';
                                    
                                    echo '<div class="d-grid">';
                                    echo '<a href="index.php" class="btn btn-primary">';
                                    echo '<i class="bi bi-arrow-left me-2"></i>Retourner au planning';
                                    echo '</a>';
                                    echo '</div>';
                                }
                                
                            } elseif ($action === 'nettoyer') {
                                // Effectuer le nettoyage
                                $stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "planning WHERE conducteur_id IS NOT NULL");
                                $nbAvant = $stmt->fetchColumn();
                                
                                $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "planning SET conducteur_id = NULL, score_ia = 0");
                                $stmt->execute();
                                $nbMisAJour = $stmt->rowCount();
                                
                                $stmt = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "planning WHERE conducteur_id IS NOT NULL");
                                $nbApres = $stmt->fetchColumn();
                                
                                echo '<div class="alert alert-success">';
                                echo '<h5><i class="bi bi-check-circle me-2"></i>Nettoyage terminé avec succès !</h5>';
                                echo '<ul class="mb-0">';
                                echo "<li><strong>Attributions avant :</strong> $nbAvant</li>";
                                echo "<li><strong>Lignes mises à jour :</strong> $nbMisAJour</li>";
                                echo "<li><strong>Attributions restantes :</strong> $nbApres</li>";
                                echo '</ul>';
                                echo '</div>';
                                
                                echo '<div class="alert alert-info">';
                                echo '<p class="mb-0"><i class="bi bi-lightbulb me-2"></i>Vous pouvez maintenant utiliser le bouton <strong>"Remplir auto (IA)"</strong> dans le planning pour reconstruire les attributions de manière optimisée.</p>';
                                echo '</div>';
                                
                                echo '<div class="d-grid">';
                                echo '<a href="index.php" class="btn btn-primary btn-lg">';
                                echo '<i class="bi bi-arrow-left me-2"></i>Retourner au planning';
                                echo '</a>';
                                echo '</div>';
                            }
                            
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">';
                            echo '<h5><i class="bi bi-x-circle me-2"></i>Erreur</h5>';
                            echo '<p class="mb-0">' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
