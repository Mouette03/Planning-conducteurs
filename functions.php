<?php
/**
 * functions.php - Logique métier complète avec IA, scoring, absences
 */

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

// ==================== CONDUCTEURS ====================

function getConducteurs() {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "conducteurs ORDER BY nom, prenom");
    return $stmt->fetchAll();
}

function getConducteur($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "conducteurs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addConducteur($data) {
    $pdo = Database::getInstance();
    $sql = "INSERT INTO " . DB_PREFIX . "conducteurs
            (nom, prenom, permis, contact, experience, statut_entreprise, tournees_maitrisees, tournee_titulaire, repos_recurrents, conges, statut_temporaire)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['nom'],
        $data['prenom'],
        $data['permis'],
        $data['contact'] ?? null,
        $data['experience'] ?? 0,
        $data['statut_entreprise'] ?? 'CDI',
        json_encode($data['tournees_maitrisees'] ?? []),
        $data['tournee_titulaire'] ?? null,
        isset($data['repos_recurrents']) ? json_encode($data['repos_recurrents']) : null,
        isset($data['conges']) ? json_encode($data['conges']) : null,
        $data['statut_temporaire'] ?? 'disponible'
    ]);
    return $pdo->lastInsertId();
}

function updateConducteur($id, $data) {
    $pdo = Database::getInstance();
    $sql = "UPDATE " . DB_PREFIX . "conducteurs
            SET nom=?, prenom=?, permis=?, contact=?, experience=?, statut_entreprise=?,
                tournees_maitrisees=?, tournee_titulaire=?, repos_recurrents=?, conges=?, statut_temporaire=?
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['nom'],
        $data['prenom'],
        $data['permis'],
        $data['contact'] ?? null,
        $data['experience'] ?? 0,
        $data['statut_entreprise'] ?? 'CDI',
        json_encode($data['tournees_maitrisees'] ?? []),
        $data['tournee_titulaire'] ?? null,
        isset($data['repos_recurrents']) ? json_encode($data['repos_recurrents']) : null,
        isset($data['conges']) ? json_encode($data['conges']) : null,
        $data['statut_temporaire'] ?? 'disponible',
        $id
    ]);
}

function deleteConducteur($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "conducteurs WHERE id = ?");
    return $stmt->execute([$id]);
}

// ==================== TOURNÉES ====================

function getTournees() {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT * FROM " . DB_PREFIX . "tournees ORDER BY nom");
    return $stmt->fetchAll();
}

function getTournee($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM " . DB_PREFIX . "tournees WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addTournee($data) {
    $pdo = Database::getInstance();
    $sql = "INSERT INTO " . DB_PREFIX . "tournees
            (nom, description, zone_geo, type_vehicule, difficulte, duree)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['nom'],
        $data['description'] ?? null,
        $data['zone_geo'] ?? null,
        $data['type_vehicule'] ?? null,
        $data['difficulte'] ?? 1,
        $data['duree'] ?? 'journee'
    ]);
    return $pdo->lastInsertId();
}

function updateTournee($id, $data) {
    $pdo = Database::getInstance();
    $sql = "UPDATE " . DB_PREFIX . "tournees
            SET nom=?, description=?, zone_geo=?, type_vehicule=?, difficulte=?, duree=?
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['nom'],
        $data['description'] ?? null,
        $data['zone_geo'] ?? null,
        $data['type_vehicule'] ?? null,
        $data['difficulte'] ?? 1,
        $data['duree'] ?? 'journee',
        $id
    ]);
}

function deleteTournee($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "tournees WHERE id = ?");
    return $stmt->execute([$id]);
}

// ==================== PLANNING ====================

function getPlanning($dateDebut, $dateFin) {
    $pdo = Database::getInstance();
    $sql = "SELECT p.*, c.nom as conducteur_nom, c.prenom as conducteur_prenom,
                   t.nom as tournee_nom, t.duree as tournee_duree
            FROM " . DB_PREFIX . "planning p
            LEFT JOIN " . DB_PREFIX . "conducteurs c ON p.conducteur_id = c.id
            JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
            WHERE p.date BETWEEN ? AND ?
            ORDER BY p.date, t.nom, p.periode";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dateDebut, $dateFin]);
    return $stmt->fetchAll();
}

function getAttribution($date, $periode, $tourneeId) {
    $pdo = Database::getInstance();
    $sql = "SELECT * FROM " . DB_PREFIX . "planning
            WHERE date = ? AND periode = ? AND tournee_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date, $periode, $tourneeId]);
    return $stmt->fetch();
}

function addAttribution($d) {
    $pdo = Database::getInstance();
    
    // CORRECTION 1 : Supprime TOUTES les anciennes attributions du conducteur à cette date/période
    if (!empty($d['conducteur_id'])) {
        $sqlDeleteConducteur = "DELETE FROM " . DB_PREFIX . "planning
                                WHERE date = :date AND periode = :periode AND conducteur_id = :conducteur_id";
        $stmtDeleteConducteur = $pdo->prepare($sqlDeleteConducteur);
        $stmtDeleteConducteur->execute([
            ':date' => $d['date'],
            ':periode' => $d['periode'],
            ':conducteur_id' => $d['conducteur_id']
        ]);
    }
    
    // CORRECTION 2 : Supprime l'ancienne attribution sur ce créneau/tournée
    $sqlDelete = "DELETE FROM " . DB_PREFIX . "planning
                  WHERE date = :date AND periode = :periode AND tournee_id = :tournee_id";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute([
        ':date' => $d['date'],
        ':periode' => $d['periode'],
        ':tournee_id' => $d['tournee_id']
    ]);
    
    // Si aucun conducteur assigné, on arrête là (suppression uniquement)
    if (empty($d['conducteur_id'])) {
        return true;
    }
    
    // Insertion simple sans ON DUPLICATE KEY UPDATE
    $sqlInsert = "INSERT INTO " . DB_PREFIX . "planning
                  (date, periode, conducteur_id, tournee_id, score_ia, statut)
                  VALUES (:date, :periode, :conducteur_id, :tournee_id, :score_ia, 'planifie')";
    $stmtInsert = $pdo->prepare($sqlInsert);
    
    return $stmtInsert->execute([
        ':date' => $d['date'],
        ':periode' => $d['periode'],
        ':conducteur_id' => $d['conducteur_id'],
        ':tournee_id' => $d['tournee_id'],
        ':score_ia' => $d['score_ia'] ?? 0
    ]);
}

function deleteAttribution($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "planning WHERE id = ?");
    return $stmt->execute([$id]);
}

// ==================== ALGORITHME IA ====================

function calculerScoreConducteur($conducteurId, $tourneeId, $date, $periode) {
    $conducteur = getConducteur($conducteurId);
    $tournee = getTournee($tourneeId);
    
    if (!$conducteur || !$tournee) {
        return ['score' => 0, 'details' => 'Conducteur ou tournée introuvable', 'disponible' => false];
    }
    
    // Vérifier disponibilité (bloquant)
    $disponible = verifierDisponibilite($conducteur, $date, $periode);
    if (!$disponible['disponible']) {
        return ['score' => 0, 'details' => "❌ " . $disponible['raison'], 'disponible' => false];
    }
    
    // Récupérer les critères configurables
    $poidsTitulaire = getConfig('poids_titulaire') ?: 100;
    $poidsConnaissance = getConfig('poids_connaissance') ?: 80;
    $poidsExperience = getConfig('poids_experience') ?: 4;
    $poidsDisponibilite = getConfig('poids_disponibilite') ?: 60;
    $penaliteInterimaire = getConfig('penalite_interimaire') ?: -50;
    
    $score = 0;
    $details = [];
    
    // 1. Conducteur titulaire
    if ($conducteur['tournee_titulaire'] == $tourneeId) {
        $score += $poidsTitulaire;
        $details[] = "Titulaire (+{$poidsTitulaire})";
    }
    
    // 2. Tournée maîtrisée
    $tourneesMaitrisees = json_decode($conducteur['tournees_maitrisees'] ?? '[]', true);
    if (in_array($tourneeId, $tourneesMaitrisees)) {
        $score += $poidsConnaissance;
        $details[] = "Maîtrise (+{$poidsConnaissance})";
    }
    
    // 3. Expérience
    $pointsExp = min(40, (int)$conducteur['experience'] * $poidsExperience);
    $score += $pointsExp;
    $details[] = "Exp. {$conducteur['experience']} ans (+{$pointsExp})";
    
    // 4. Bonus disponibilité
    $score += $poidsDisponibilite;
    $details[] = "Disponible (+{$poidsDisponibilite})";
    
    // 5. Bonus/Malus selon statut
    if ($conducteur['statut_entreprise'] === 'CDI') {
        $score += 10;
        $details[] = "CDI (+10)";
    } elseif ($conducteur['statut_entreprise'] === 'interimaire') {
        $score += $penaliteInterimaire;
        $details[] = "Intérimaire ({$penaliteInterimaire})";
    }
    
    // Score final normalisé sur 100
    $scoreMax = $poidsTitulaire + $poidsConnaissance + 40 + $poidsDisponibilite + 10;
    $scoreFinal = max(0, min(100, round($score * 100 / $scoreMax)));
    
    return [
        'score' => $scoreFinal,
        'details' => implode(', ', $details),
        'disponible' => true
    ];
}

function verifierDisponibilite($conducteur, $date, $periode) {
    // Vérifier statut temporaire
    if ($conducteur['statut_temporaire'] !== 'disponible') {
        return ['disponible' => false, 'raison' => ucfirst($conducteur['statut_temporaire'])];
    }
    
    // Vérifier repos récurrents avec semaines paires/impaires
    if (!empty($conducteur['repos_recurrents'])) {
        $repos = json_decode($conducteur['repos_recurrents'], true);
        $jourSemaine = date('N', strtotime($date));
        $numeroSemaine = date('W', strtotime($date));
        $estSemainePaire = ($numeroSemaine % 2 === 0);
        
        if (isset($repos['jours']) && in_array($jourSemaine, $repos['jours'])) {
            $typeRepos = $repos['type'] ?? 'toutes';
            
            if ($typeRepos === 'toutes') {
                return ['disponible' => false, 'raison' => 'Repos hebdomadaire'];
            } elseif ($typeRepos === 'paires' && $estSemainePaire) {
                return ['disponible' => false, 'raison' => 'Repos semaine paire'];
            } elseif ($typeRepos === 'impaires' && !$estSemainePaire) {
                return ['disponible' => false, 'raison' => 'Repos semaine impaire'];
            }
        }
    }
    
    // Vérifier congés
    if (!empty($conducteur['conges'])) {
        $conges = json_decode($conducteur['conges'], true);
        foreach ($conges as $conge) {
            if ($date >= $conge['debut'] && $date <= $conge['fin']) {
                return ['disponible' => false, 'raison' => 'En congé'];
            }
        }
    }
    
    return ['disponible' => true, 'raison' => ''];
}

// ==================== SCORE DE PERFORMANCE ====================

function getPerformanceConducteur($conducteurId, $dateDebut, $dateFin) {
    $pdo = Database::getInstance();
    $sql = "SELECT AVG(score_ia) as score_moyen, COUNT(*) as nb_attributions
            FROM " . DB_PREFIX . "planning
            WHERE conducteur_id = ? AND date BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conducteurId, $dateDebut, $dateFin]);
    $result = $stmt->fetch();
    
    return [
        'score_moyen' => $result['score_moyen'] ? round($result['score_moyen'], 1) : 0,
        'nb_attributions' => $result['nb_attributions'] ?? 0
    ];
}

function getScorePerformanceGlobal($dateDebut, $dateFin) {
    $pdo = Database::getInstance();
    
    $sql = "SELECT p.score_ia, t.difficulte, c.statut_entreprise
            FROM " . DB_PREFIX . "planning p
            LEFT JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
            LEFT JOIN " . DB_PREFIX . "conducteurs c ON p.conducteur_id = c.id
            WHERE p.date BETWEEN ? AND ? AND p.score_ia > 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dateDebut, $dateFin]);
    $attributions = $stmt->fetchAll();
    
    if (empty($attributions)) {
        return ['score_global' => 0, 'nb_attributions' => 0, 'details' => 'Aucune attribution'];
    }
    
    $totalScore = 0;
    $nbAttributions = count($attributions);
    $bonusQualite = 0;
    
    foreach ($attributions as $attr) {
        $score = $attr['score_ia'];
        $totalScore += $score;
        
        if ($score >= 80 && $attr['difficulte'] >= 4) {
            $bonusQualite += 5;
        }
        
        if ($attr['statut_entreprise'] === 'CDI' && $score >= 70) {
            $bonusQualite += 2;
        }
    }
    
    $scoreMoyen = $totalScore / $nbAttributions;
    $scoreGlobal = min(100, $scoreMoyen + ($bonusQualite / $nbAttributions * 10));
    
    return [
        'score_global' => round($scoreGlobal, 1),
        'nb_attributions' => $nbAttributions,
        'score_moyen' => round($scoreMoyen, 1),
        'bonus_qualite' => round($bonusQualite / $nbAttributions * 10, 1)
    ];
}

// ==================== CONFIGURATION ====================

function getConfig($cle = null) {
    $pdo = Database::getInstance();
    if ($cle) {
        $stmt = $pdo->prepare("SELECT valeur FROM " . DB_PREFIX . "config WHERE cle = ?");
        $stmt->execute([$cle]);
        $result = $stmt->fetch();
        return $result ? json_decode($result['valeur'], true) : null;
    } else {
        $stmt = $pdo->query("SELECT cle, valeur FROM " . DB_PREFIX . "config");
        $config = [];
        foreach ($stmt->fetchAll() as $row) {
            $config[$row['cle']] = json_decode($row['valeur'], true);
        }
        return $config;
    }
}

function setConfig($cle, $valeur) {
    $pdo = Database::getInstance();
    $sql = "INSERT INTO " . DB_PREFIX . "config (cle, valeur) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$cle, json_encode($valeur)]);
}

// ==================== STATISTIQUES ====================

function getStatistiques() {
    $pdo = Database::getInstance();
    
    $stats = [];
    $stats['conducteurs'] = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "conducteurs")->fetchColumn();
    $stats['tournees'] = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "tournees")->fetchColumn();
    
    $debutSemaine = date('Y-m-d', strtotime('monday this week'));
    $finSemaine = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "planning WHERE date BETWEEN ? AND ?");
    $stmt->execute([$debutSemaine, $finSemaine]);
    $stats['attributions_semaine'] = $stmt->fetchColumn();
    
    return $stats;
}

// ==================== REMPLISSAGE AUTOMATIQUE ====================

function remplirPlanningAuto($dateDebut, $dateFin) {
    $tournees = getTournees();
    $conducteurs = getConducteurs();
    $succes = 0;
    $echecs = 0;
    
    $dateActuelle = new DateTime($dateDebut);
    $dateLimite = new DateTime($dateFin);
    
    while ($dateActuelle <= $dateLimite) {
        $dateStr = $dateActuelle->format('Y-m-d');
        
        foreach ($tournees as $tournee) {
            $periodes = [];
            if ($tournee['duree'] === 'matin' || $tournee['duree'] === 'journee') {
                $periodes[] = 'matin';
            }
            if ($tournee['duree'] === 'apres-midi' || $tournee['duree'] === 'journee') {
                $periodes[] = 'apres-midi';
            }
            
            foreach ($periodes as $periode) {
                if (getAttribution($dateStr, $periode, $tournee['id'])) {
                    continue;
                }
                
                $meilleurScore = -1;
                $meilleurConducteur = null;
                
                foreach ($conducteurs as $conducteur) {
                    $resultat = calculerScoreConducteur($conducteur['id'], $tournee['id'], $dateStr, $periode);
                    
                    if (!$resultat['disponible']) {
                        continue;
                    }
                    
                    if ($resultat['score'] > $meilleurScore) {
                        $meilleurScore = $resultat['score'];
                        $meilleurConducteur = $conducteur;
                    }
                }
                
                if ($meilleurConducteur) {
                    addAttribution([
                        'date' => $dateStr,
                        'periode' => $periode,
                        'conducteur_id' => $meilleurConducteur['id'],
                        'tournee_id' => $tournee['id'],
                        'score_ia' => $meilleurScore
                    ]);
                    $succes++;
                } else {
                    $echecs++;
                }
            }
        }
        
        $dateActuelle->modify('+1 day');
    }
    
    return ['succes' => $succes, 'echecs' => $echecs];
}
