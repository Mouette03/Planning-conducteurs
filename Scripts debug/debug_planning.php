<?php
/**
 * Script de débogage pour analyser l'attribution des tournées
 * Génère un fichier log détaillé avec timestamp
 */

require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Nom de fichier fixe (écrase l'ancien)
$logFile = __DIR__ . "/debug_planning.log";

$logs = [];
$logs[] = "===========================================";
$logs[] = "DÉBOGAGE ATTRIBUTION TOURNÉES";
$logs[] = "Date génération: " . date('Y-m-d H:i:s');
$logs[] = "===========================================\n";

// Récupérer la date à analyser (paramètre GET ou date du jour)
$dateAnalyse = $_GET['date'] ?? date('Y-m-d');
$logs[] = "Date analysée: $dateAnalyse\n";

try {
    $pdo = Database::getInstance();
    
    // 1. LISTE DES TOURNÉES
    $logs[] = "\n### 1. TOURNÉES CONFIGURÉES ###";
    $stmtTournees = $pdo->query("SELECT * FROM " . DB_PREFIX . "tournees ORDER BY nom");
    $tournees = $stmtTournees->fetchAll();
    
    foreach ($tournees as $t) {
        $logs[] = "\n[{$t['nom']}] ID: {$t['id']}";
        $logs[] = "  - Durée: {$t['duree']}";
        $logs[] = "  - Permis requis: " . ($t['permis_requis'] ?: 'Aucun');
    }
    
    // 2. LISTE DES CONDUCTEURS
    $logs[] = "\n\n### 2. CONDUCTEURS DISPONIBLES ###";
    $stmtConducteurs = $pdo->query("SELECT * FROM " . DB_PREFIX . "conducteurs ORDER BY nom, prenom");
    $conducteurs = $stmtConducteurs->fetchAll();
    
    foreach ($conducteurs as $c) {
        $logs[] = "\n[{$c['prenom']} {$c['nom']}] ID: {$c['id']}";
        $logs[] = "  - Permis: {$c['permis']}";
        
        if ($c['tournee_titulaire']) {
            $stmtTit = $pdo->prepare("SELECT nom FROM " . DB_PREFIX . "tournees WHERE id = ?");
            $stmtTit->execute([$c['tournee_titulaire']]);
            $tourneeNom = $stmtTit->fetchColumn();
            $logs[] = "  - TITULAIRE de: {$tourneeNom} (ID: {$c['tournee_titulaire']})";
        } else {
            $logs[] = "  - Statut: Remplaçant";
        }
        
        if (!empty($c['tournees_maitrisees'])) {
            $decoded = json_decode($c['tournees_maitrisees'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                $nomsTournees = [];
                foreach ($decoded as $tid) {
                    $stmtMait = $pdo->prepare("SELECT nom FROM " . DB_PREFIX . "tournees WHERE id = ?");
                    $stmtMait->execute([$tid]);
                    $nomsTournees[] = $stmtMait->fetchColumn() . " (ID: $tid)";
                }
                $logs[] = "  - Maîtrise: " . implode(', ', $nomsTournees);
            }
        }
    }
    
    // 3. ATTRIBUTIONS ACTUELLES POUR LA DATE
    $logs[] = "\n\n### 3. ATTRIBUTIONS POUR LE $dateAnalyse ###";
    $stmtAttr = $pdo->prepare("
        SELECT 
            p.*,
            c.prenom,
            c.nom,
            c.tournee_titulaire,
            c.tournees_maitrisees,
            t.nom as tournee_nom,
            t.duree as tournee_duree
        FROM " . DB_PREFIX . "planning p
        JOIN " . DB_PREFIX . "conducteurs c ON p.conducteur_id = c.id
        JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
        WHERE p.date = ?
        ORDER BY p.periode, t.nom
    ");
    $stmtAttr->execute([$dateAnalyse]);
    $attributions = $stmtAttr->fetchAll();
    
    if (empty($attributions)) {
        $logs[] = "\n⚠️ AUCUNE ATTRIBUTION TROUVÉE pour cette date";
    } else {
        $logs[] = "\nTotal: " . count($attributions) . " attributions\n";
        
        foreach ($attributions as $attr) {
            $periode = strtoupper($attr['periode']);
            $logs[] = "\n[$periode] {$attr['tournee_nom']} (ID: {$attr['tournee_id']}, Durée: {$attr['tournee_duree']})";
            $logs[] = "  → Conducteur: {$attr['prenom']} {$attr['nom']} (ID: {$attr['conducteur_id']})";
            $logs[] = "  → Score IA: {$attr['score_ia']}";
            
            // Vérifier si le conducteur est titulaire
            if ($attr['tournee_titulaire']) {
                if ($attr['tournee_titulaire'] == $attr['tournee_id']) {
                    $logs[] = "  → ✅ TITULAIRE de cette tournée";
                } else {
                    $stmtTitAutre = $pdo->prepare("SELECT nom FROM " . DB_PREFIX . "tournees WHERE id = ?");
                    $stmtTitAutre->execute([$attr['tournee_titulaire']]);
                    $autreTournee = $stmtTitAutre->fetchColumn();
                    $logs[] = "  → ⚠️ ATTENTION: Titulaire d'une AUTRE tournée ({$autreTournee}, ID: {$attr['tournee_titulaire']})";
                }
            }
            
            // Vérifier si le conducteur maîtrise cette tournée
            if (!empty($attr['tournees_maitrisees'])) {
                $decoded = json_decode($attr['tournees_maitrisees'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    if (in_array($attr['tournee_id'], $decoded)) {
                        $logs[] = "  → ✅ MAÎTRISE cette tournée";
                    } else {
                        $logs[] = "  → ⚪ Ne maîtrise PAS cette tournée";
                        if (!empty($decoded)) {
                            $autresMaitrises = [];
                            foreach ($decoded as $tid) {
                                $stmtMaitAutre = $pdo->prepare("SELECT nom FROM " . DB_PREFIX . "tournees WHERE id = ?");
                                $stmtMaitAutre->execute([$tid]);
                                $autresMaitrises[] = $stmtMaitAutre->fetchColumn();
                            }
                            $logs[] = "  → Maîtrise plutôt: " . implode(', ', $autresMaitrises);
                        }
                    }
                }
            }
        }
    }
    
    // 4. ANALYSE DES CONFLITS
    $logs[] = "\n\n### 4. ANALYSE DES CONFLITS ###\n";
    
    $conflits = [];
    
    // Vérifier les titulaires sur d'autres tournées
    foreach ($attributions as $attr) {
        if ($attr['tournee_titulaire'] && $attr['tournee_titulaire'] != $attr['tournee_id']) {
            $stmtTitAutre = $pdo->prepare("SELECT nom FROM " . DB_PREFIX . "tournees WHERE id = ?");
            $stmtTitAutre->execute([$attr['tournee_titulaire']]);
            $autreTournee = $stmtTitAutre->fetchColumn();
            
            $conflits[] = "❌ CONFLIT TITULAIRE: {$attr['prenom']} {$attr['nom']} est TITULAIRE de {$autreTournee} mais affecté à {$attr['tournee_nom']} ({$attr['periode']})";
        }
    }
    
    // Vérifier les conducteurs affectés plusieurs fois à la même période
    $periodes = ['matin' => [], 'apres-midi' => []];
    foreach ($attributions as $attr) {
        $p = $attr['periode'];
        if (!isset($periodes[$p])) {
            $periodes[$p] = [];
        }
        
        if (isset($periodes[$p][$attr['conducteur_id']])) {
            $conflits[] = "❌ CONFLIT DOUBLE AFFECTATION: {$attr['prenom']} {$attr['nom']} affecté à {$periodes[$p][$attr['conducteur_id']]} ET {$attr['tournee_nom']} le même {$p}";
        } else {
            $periodes[$p][$attr['conducteur_id']] = $attr['tournee_nom'];
        }
    }
    
    // Vérifier les tournées "journée" qui devraient bloquer matin ET après-midi
    foreach ($attributions as $attr) {
        if ($attr['tournee_duree'] === 'journée') {
            // Ce conducteur ne devrait avoir AUCUNE autre attribution ce jour
            $stmtAutres = $pdo->prepare("
                SELECT t.nom, p.periode
                FROM " . DB_PREFIX . "planning p
                JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
                WHERE p.conducteur_id = ? AND p.date = ? AND p.tournee_id != ?
            ");
            $stmtAutres->execute([$attr['conducteur_id'], $dateAnalyse, $attr['tournee_id']]);
            $autres = $stmtAutres->fetchAll();
            
            foreach ($autres as $autre) {
                $conflits[] = "❌ CONFLIT JOURNÉE: {$attr['prenom']} {$attr['nom']} affecté à {$attr['tournee_nom']} (journée) mais aussi à {$autre['nom']} ({$autre['periode']})";
            }
        }
    }
    
    if (empty($conflits)) {
        $logs[] = "✅ Aucun conflit détecté";
    } else {
        foreach ($conflits as $conflit) {
            $logs[] = $conflit;
        }
    }
    
    // 5. TOURNÉES NON COUVERTES
    $logs[] = "\n\n### 5. TOURNÉES NON COUVERTES ###\n";
    
    $tourneesCouvertes = [];
    foreach ($attributions as $attr) {
        $key = $attr['tournee_id'] . '_' . $attr['periode'];
        $tourneesCouvertes[$key] = true;
    }
    
    $nonCouvertes = [];
    foreach ($tournees as $t) {
        $periodes = [];
        if ($t['duree'] === 'matin' || $t['duree'] === 'journée') {
            $periodes[] = 'matin';
        }
        if ($t['duree'] === 'après-midi' || $t['duree'] === 'journée') {
            $periodes[] = 'apres-midi';
        }
        if ($t['duree'] === 'matin et après-midi') {
            $periodes[] = 'matin';
            $periodes[] = 'apres-midi';
        }
        
        foreach ($periodes as $p) {
            $key = $t['id'] . '_' . $p;
            if (!isset($tourneesCouvertes[$key])) {
                $nonCouvertes[] = "⚠️ {$t['nom']} ({$p}) - ID: {$t['id']}";
            }
        }
    }
    
    if (empty($nonCouvertes)) {
        $logs[] = "✅ Toutes les tournées sont couvertes";
    } else {
        foreach ($nonCouvertes as $nc) {
            $logs[] = $nc;
        }
    }
    
    // 6. VÉRIFICATION DES DISPONIBILITÉS
    $logs[] = "\n\n### 6. DISPONIBILITÉS DES CONDUCTEURS ###\n";
    
    // Vérifier si la table absences existe
    $tableAbsencesExiste = false;
    try {
        $stmtCheckTable = $pdo->query("SHOW TABLES LIKE '" . DB_PREFIX . "absences'");
        $tableAbsencesExiste = ($stmtCheckTable->rowCount() > 0);
    } catch (Exception $e) {
        $logs[] = "⚠️ Impossible de vérifier l'existence de la table absences";
    }
    
    foreach ($conducteurs as $c) {
        $logs[] = "\n{$c['prenom']} {$c['nom']}:";
        
        // Vérifier absences (si la table existe)
        if ($tableAbsencesExiste) {
            try {
                $stmtAbs = $pdo->prepare("
                    SELECT type_absence, periode 
                    FROM " . DB_PREFIX . "absences 
                    WHERE conducteur_id = ? AND date = ?
                ");
                $stmtAbs->execute([$c['id'], $dateAnalyse]);
                $absences = $stmtAbs->fetchAll();
                
                if (!empty($absences)) {
                    foreach ($absences as $abs) {
                        $logs[] = "  - ❌ ABSENT ({$abs['type_absence']}) - {$abs['periode']}";
                    }
                } else {
                    $logs[] = "  - ✅ Disponible (pas d'absence enregistrée)";
                }
            } catch (Exception $e) {
                $logs[] = "  - ⚠️ Erreur lors de la vérification des absences";
            }
        } else {
            $logs[] = "  - ⚠️ Table absences non configurée";
        }
        
        // Vérifier jours de repos
        $jourSemaine = date('N', strtotime($dateAnalyse)); // 1=lundi, 7=dimanche
        $joursRepos = !empty($c['jours_repos']) ? json_decode($c['jours_repos'], true) : [];
        
        if (is_array($joursRepos) && in_array($jourSemaine, $joursRepos)) {
            $nomsJours = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
            $logs[] = "  - 🌙 JOUR DE REPOS ({$nomsJours[$jourSemaine]})";
        }
    }
    
    $logs[] = "\n\n===========================================";
    $logs[] = "FIN DU RAPPORT";
    $logs[] = "===========================================";
    
} catch (Exception $e) {
    $logs[] = "\n\n❌ ERREUR: " . $e->getMessage();
    $logs[] = "Trace: " . $e->getTraceAsString();
}

// Écrire dans le fichier
$contenu = implode("\n", $logs);
file_put_contents($logFile, $contenu);

// Afficher à l'écran
header('Content-Type: text/plain; charset=utf-8');
echo $contenu;
echo "\n\n📄 Log sauvegardé dans: " . basename($logFile);
