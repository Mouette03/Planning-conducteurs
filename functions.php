<?php
/**
 * functions.php - Logique métier complète avec IA, scoring, absences
 */

function jsonResponse($data, $statusCode = 200) {
    // If any accidental output was produced, capture it and log for debugging
    if (ob_get_level() > 0) {
        $buf = ob_get_clean();
        if (!empty($buf)) {
            error_log("Buffered output before JSON response: " . $buf);
        }
    }

    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

// ==================== CONDUCTEURS ====================

function validateConducteur($data) {
    $errors = [];
    
    if (empty($data['nom'])) $errors[] = "Le nom est requis";
    if (empty($data['prenom'])) $errors[] = "Le prénom est requis";
    if (empty($data['permis'])) $errors[] = "Le permis est requis";
    
    if (!empty($data['contact']) && !filter_var($data['contact'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    if (isset($data['experience']) && ($data['experience'] < 0 || $data['experience'] > 50)) {
        $errors[] = "L'expérience doit être comprise entre 0 et 50 ans";
    }
    
    $statutsValides = ['CDI', 'CDD', 'interimaire', 'sous-traitant'];
    if (!empty($data['statut_entreprise']) && !in_array($data['statut_entreprise'], $statutsValides)) {
        $errors[] = "Statut d'entreprise invalide";
    }
    
    return $errors;
}

function getConducteurs($withPerformance = false) {
    try {
        $sql = "SELECT c.*, 
                COALESCE(AVG(p.score_ia), 0) as score_moyen,
                COUNT(DISTINCT p.id) as nb_attributions,
                CASE 
                    WHEN c.date_embauche IS NOT NULL 
                    THEN TIMESTAMPDIFF(YEAR, c.date_embauche, CURDATE())
                    ELSE c.experience 
                END as experience_calculee
                FROM " . DB_PREFIX . "conducteurs c
                LEFT JOIN " . DB_PREFIX . "planning p ON c.id = p.conducteur_id";
        
        if ($withPerformance) {
            $sql .= " AND p.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.nom, c.prenom";
        
        $conducteurs = Database::prepare($sql)->fetchAll();
        
        // Remplacer experience par experience_calculee
        foreach ($conducteurs as &$c) {
            $c['experience'] = $c['experience_calculee'];
        }
        
        return $conducteurs;
    } catch (Exception $e) {
        error_log("Erreur getConducteurs: " . $e->getMessage());
        throw new Exception("Erreur lors de la récupération des conducteurs");
    }
}

function getConducteur($id) {
    try {
        $sql = "SELECT c.*, 
                COALESCE(AVG(p.score_ia), 0) as score_moyen,
                COUNT(DISTINCT p.id) as nb_attributions,
                CASE 
                    WHEN c.date_embauche IS NOT NULL 
                    THEN TIMESTAMPDIFF(YEAR, c.date_embauche, CURDATE())
                    ELSE c.experience 
                END as experience_calculee
                FROM " . DB_PREFIX . "conducteurs c
                LEFT JOIN " . DB_PREFIX . "planning p ON c.id = p.conducteur_id
                WHERE c.id = ?
                GROUP BY c.id";
                
        $conducteur = Database::prepare($sql, [$id])->fetch();
        
        if ($conducteur) {
            // Remplacer experience par experience_calculee
            $conducteur['experience'] = $conducteur['experience_calculee'];
        }
        
        return $conducteur;
    } catch (Exception $e) {
        error_log("Erreur getConducteur: " . $e->getMessage());
        throw new Exception("Erreur lors de la récupération du conducteur");
    }
}

function addConducteur($data) {
    try {
        $errors = validateConducteur($data);
        if (!empty($errors)) {
            throw new Exception(implode("\n", $errors));
        }

        $sql = "INSERT INTO " . DB_PREFIX . "conducteurs
                (nom, prenom, permis, contact, date_embauche, experience, statut_entreprise, 
                tournees_maitrisees, tournee_titulaire, repos_recurrents, 
                conges, statut_temporaire, statut_temporaire_fin, date_creation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $data['nom'],
            $data['prenom'],
            is_array($data['permis']) ? json_encode($data['permis']) : $data['permis'],
            $data['contact'] ?? null,
            $data['date_embauche'] ?? null,
            $data['experience'] ?? 0,
            $data['statut_entreprise'] ?? 'CDI',
            json_encode($data['tournees_maitrisees'] ?? []),
            $data['tournee_titulaire'] ?? null,
            isset($data['repos_recurrents']) ? json_encode($data['repos_recurrents']) : null,
            isset($data['conges']) ? json_encode($data['conges']) : null,
            $data['statut_temporaire'] ?? 'disponible',
            $data['statut_temporaire_fin'] ?? null
        ];
        
        $stmt = Database::prepare($sql, $params);
        return Database::getInstance()->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Erreur addConducteur: " . $e->getMessage());
        throw new Exception("Erreur lors de l'ajout du conducteur");
    }
}

function updateConducteur($id, $data) {
    $pdo = Database::getInstance();
    $sql = "UPDATE " . DB_PREFIX . "conducteurs
            SET nom=?, prenom=?, permis=?, contact=?, date_embauche=?, experience=?, statut_entreprise=?,
                tournees_maitrisees=?, tournee_titulaire=?, repos_recurrents=?, conges=?, 
                statut_temporaire=?, statut_temporaire_fin=?
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['nom'],
        $data['prenom'],
        is_array($data['permis']) ? json_encode($data['permis']) : $data['permis'],
        $data['contact'] ?? null,
        $data['date_embauche'] ?? null,
        $data['experience'] ?? 0,
        $data['statut_entreprise'] ?? 'CDI',
        json_encode($data['tournees_maitrisees'] ?? []),
        $data['tournee_titulaire'] ?? null,
        isset($data['repos_recurrents']) ? json_encode($data['repos_recurrents']) : null,
        isset($data['conges']) ? json_encode($data['conges']) : null,
        $data['statut_temporaire'] ?? 'disponible',
        $data['statut_temporaire_fin'] ?? null,
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
    
    // Récupérer toutes les tournées
    $sql = "SELECT * FROM " . DB_PREFIX . "tournees";
    $stmt = $pdo->query($sql);
    $tournees = $stmt->fetchAll();
    
    // Récupérer les types de tournées avec leur ordre
    $typesConfig = getConfig('types_tournee');
    $typesOrdre = [];
    
    if (is_array($typesConfig)) {
        foreach ($typesConfig as $type) {
            if (isset($type['nom']) && isset($type['ordre'])) {
                $typesOrdre[$type['nom']] = (int)$type['ordre'];
            }
        }
    }
    
    // Trier les tournées par ordre de type (puis par nom)
    usort($tournees, function($a, $b) use ($typesOrdre) {
        $ordreA = isset($a['type_tournee']) && isset($typesOrdre[$a['type_tournee']]) 
            ? $typesOrdre[$a['type_tournee']] 
            : 999;
        $ordreB = isset($b['type_tournee']) && isset($typesOrdre[$b['type_tournee']]) 
            ? $typesOrdre[$b['type_tournee']] 
            : 999;
        
        // Comparer d'abord par ordre
        if ($ordreA != $ordreB) {
            return $ordreA - $ordreB;
        }
        
        // Si même ordre, comparer par nom
        return strcmp($a['nom'] ?? '', $b['nom'] ?? '');
    });
    
    return $tournees;
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
            (nom, type_tournee, zone_geo, type_vehicule, permis_requis, difficulte, duree)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $permisJson = isset($data['permis_requis']) ? json_encode($data['permis_requis']) : '[]';
    $stmt->execute([
        $data['nom'],
        $data['type_tournee'] ?? null,
        $data['zone_geo'] ?? null,
        $data['type_vehicule'] ?? null,
        $permisJson,
        $data['difficulte'] ?? 1,
        $data['duree'] ?? 'journée'
    ]);
    return $pdo->lastInsertId();
}

function updateTournee($id, $data) {
    $pdo = Database::getInstance();
    
    // Log pour déboguer
    error_log("updateTournee - ID: $id, Durée: " . ($data['duree'] ?? 'NULL'));
    
    $sql = "UPDATE " . DB_PREFIX . "tournees
            SET nom=?, type_tournee=?, zone_geo=?, type_vehicule=?, permis_requis=?, difficulte=?, duree=?
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $permisJson = isset($data['permis_requis']) ? json_encode($data['permis_requis']) : '[]';
    
    $result = $stmt->execute([
        $data['nom'],
        $data['type_tournee'] ?? null,
        $data['zone_geo'] ?? null,
        $data['type_vehicule'] ?? null,
        $permisJson,
        $data['difficulte'] ?? 1,
        $data['duree'] ?? 'journée',
        $id
    ]);
    
    // Log du résultat
    error_log("updateTournee - Résultat: " . ($result ? 'SUCCESS' : 'FAILED'));
    
    return $result;
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

function getConducteurAttribution($conducteurId, $date, $periode) {
    $pdo = Database::getInstance();
    $sql = "SELECT * FROM " . DB_PREFIX . "planning
            WHERE date = ? AND periode = ? AND conducteur_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date, $periode, $conducteurId]);
    return $stmt->fetch();
}

function addAttribution($d) {
    $pdo = Database::getInstance();
    
    // VALIDATION : Vérifier que le conducteur possède le permis requis
    if (!empty($d['conducteur_id'])) {
        $conducteur = getConducteur($d['conducteur_id']);
        $tournee = getTournee($d['tournee_id']);
        
        if ($conducteur && $tournee) {
            // Récupérer les permis requis de la tournée
            $permisRequis = is_array($tournee['permis_requis']) 
                ? $tournee['permis_requis'] 
                : json_decode($tournee['permis_requis'] ?? '[]', true);
            
            // Normaliser les permis requis en array
            if (!is_array($permisRequis)) {
                $permisRequis = [$permisRequis];
            }
            
            // Récupérer les permis du conducteur et les normaliser en array
            $permisConducteur = $conducteur['permis'];
            
            // Si c'est une string JSON, la décoder
            if (is_string($permisConducteur)) {
                // Essayer de décoder comme JSON
                $decoded = json_decode($permisConducteur, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $permisConducteur = $decoded;
                } else {
                    // Sinon, split par virgule (ancien format)
                    $permisConducteur = explode(',', $permisConducteur);
                }
            }
            
            // S'assurer que c'est bien un array
            if (!is_array($permisConducteur)) {
                $permisConducteur = [$permisConducteur];
            }
            
            // Nettoyer les espaces
            $permisConducteur = array_map('trim', $permisConducteur);
            
            // Vérifier si le conducteur a AU MOINS UN des permis requis
            if (!empty($permisRequis)) {
                $aPermisValide = false;
                foreach ($permisRequis as $permisReq) {
                    if (in_array(trim($permisReq), $permisConducteur)) {
                        $aPermisValide = true;
                        break;
                    }
                }
                
                if (!$aPermisValide) {
                    throw new Exception(
                        "Le conducteur {$conducteur['prenom']} {$conducteur['nom']} ne possède pas le(s) permis requis pour cette tournée. " .
                        "Permis requis : " . implode(', ', $permisRequis) . ". " .
                        "Permis du conducteur : " . implode(', ', $permisConducteur) . "."
                    );
                }
            }
        }
    }
    
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

function updateAttribution($id, $data) {
    $pdo = Database::getInstance();
    
    $fields = [];
    $params = [];
    
    if (array_key_exists('conducteur_id', $data)) {
        $fields[] = "conducteur_id = ?";
        $params[] = $data['conducteur_id'];
    }
    if (array_key_exists('score_ia', $data)) {
        $fields[] = "score_ia = ?";
        $params[] = $data['score_ia'];
    }
    if (isset($data['statut'])) {
        $fields[] = "statut = ?";
        $params[] = $data['statut'];
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $params[] = $id;
    $sql = "UPDATE " . DB_PREFIX . "planning SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// ==================== ALGORITHME IA ====================

function calculerScoreConducteur($conducteurId, $tourneeId, $date, $periode) {
    $conducteur = getConducteur($conducteurId);
    $tournee = getTournee($tourneeId);
    
    if (!$conducteur || !$tournee) {
        return ['score' => 0, 'details' => 'Conducteur ou tournée introuvable', 'disponible' => false];
    }
    
    // VÉRIFICATION 1 : Permis requis (BLOQUANT)
    $permisRequis = json_decode($tournee['permis_requis'] ?? '[]', true);
    $permisConducteur = json_decode($conducteur['permis'] ?? '[]', true);
    
    // Si la tournée nécessite des permis spécifiques
    if (!empty($permisRequis)) {
        // Vérifier si le conducteur a au moins un des permis requis
        $hasPermis = false;
        foreach ($permisConducteur as $permis) {
            if (in_array($permis, $permisRequis)) {
                $hasPermis = true;
                break;
            }
        }
        
        if (!$hasPermis) {
            $permisManquants = implode(', ', $permisRequis);
            return ['score' => 0, 'details' => "❌ Permis requis : $permisManquants", 'disponible' => false];
        }
    }
    
    // VÉRIFICATION 2 : Disponibilité (BLOQUANT)
    $disponible = verifierDisponibilite($conducteur, $date, $periode);
    if (!$disponible['disponible']) {
        return ['score' => 0, 'details' => "❌ " . $disponible['raison'], 'disponible' => false];
    }
    
    // Récupérer les critères configurables
    $poidsConnaissance = getConfig('poids_connaissance') ?: 80;
    $poidsExperience = getConfig('poids_experience') ?: 2.5; // 100/100 à 40 ans
    $penaliteInterimaire = getConfig('penalite_interimaire') ?: -50;
    
    $score = 0;
    $details = [];
    
    // 1. Conducteur titulaire (bonus automatique + ajoute la tournée aux maîtrisées si manquant)
    $estTitulaire = ($conducteur['tournee_titulaire'] == $tourneeId);
    if ($estTitulaire) {
        $score += $poidsConnaissance; // Titulaire = maîtrise automatique
        $details[] = "⭐ Titulaire (+{$poidsConnaissance})";
    }
    
    // 2. Tournée maîtrisée (seulement si pas déjà compté comme titulaire)
    if (!$estTitulaire) {
        $tourneesMaitrisees = json_decode($conducteur['tournees_maitrisees'] ?? '[]', true);
        if (in_array($tourneeId, $tourneesMaitrisees)) {
            $score += $poidsConnaissance;
            $details[] = "Maîtrise (+{$poidsConnaissance})";
        }
    }
    
    // 3. Expérience (maximum 100 points pour 40 ans)
    $pointsExp = min(100, (int)$conducteur['experience'] * $poidsExperience);
    $score += $pointsExp;
    $details[] = "Exp. {$conducteur['experience']} ans (+{$pointsExp})";
    
    // 4. Bonus/Malus selon statut
    if ($conducteur['statut_entreprise'] === 'CDI') {
        $score += 10;
        $details[] = "CDI (+10)";
    } elseif ($conducteur['statut_entreprise'] === 'interimaire') {
        $score += $penaliteInterimaire;
        $details[] = "Intérimaire ({$penaliteInterimaire})";
    }
    
    // Score final normalisé sur 100
    // scoreMax = Connaissance + Expérience max (100) + CDI bonus (10)
    $scoreMax = $poidsConnaissance + 100 + 10;
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
        // Vérifier si le statut temporaire a une date de fin
        $statut_temp_fin = !empty($conducteur['statut_temporaire_fin']) ? 
            new DateTime($conducteur['statut_temporaire_fin']) : null;
        
        // Si pas de date de fin OU date actuelle <= date de fin
        if (!$statut_temp_fin || new DateTime($date) <= $statut_temp_fin) {
            return ['disponible' => false, 'raison' => ucfirst($conducteur['statut_temporaire'])];
        }
    }
    
    // Vérifier repos récurrents avec semaines paires/impaires
    if (!empty($conducteur['repos_recurrents'])) {
        $repos = json_decode($conducteur['repos_recurrents'], true);
        $jourSemaine = (int)date('N', strtotime($date)); // Convertir en entier
        $numeroSemaine = (int)date('W', strtotime($date));
        $estSemainePaire = ($numeroSemaine % 2 === 0);
        
        if (isset($repos['jours']) && in_array($jourSemaine, $repos['jours'], false)) {
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

function getTauxOccupation($dateDebut, $dateFin) {
    try {
        $pdo = Database::getInstance();
        
        // Calculer le nombre de jours dans la période
        $debut = new DateTime($dateDebut);
        $fin = new DateTime($dateFin);
        $interval = $debut->diff($fin);
        $nbJours = $interval->days + 1;
        
        // Compter les tournées actives
        $nbTournees = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "tournees")->fetchColumn();
        
        if ($nbTournees == 0 || $nbJours == 0) {
            return 0;
        }
        
        // Total de cases possibles (chaque tournée, chaque jour, 2 périodes)
        $totalCases = $nbTournees * $nbJours * 2;
        
        // Compter UNIQUEMENT les cases réellement remplies (avec un conducteur attribué)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM " . DB_PREFIX . "planning 
            WHERE date BETWEEN ? AND ? 
            AND conducteur_id IS NOT NULL
        ");
        $stmt->execute([$dateDebut, $dateFin]);
        $casesRemplies = (int)$stmt->fetchColumn();
        
        if ($totalCases === 0) {
            return 0;
        }
        
        $taux = round(($casesRemplies / $totalCases) * 100, 1);
        
        // Log pour debug
        error_log("Taux occupation: $casesRemplies cases remplies / $totalCases total = $taux%");
        
        return $taux;
        
    } catch (Exception $e) {
        error_log("Erreur getTauxOccupation: " . $e->getMessage());
        return 0;
    }
}

// ==================== CONFIGURATION ====================

function getConfig($cle = null) {
    $pdo = Database::getInstance();
    if ($cle) {
        $stmt = $pdo->prepare("SELECT cle, valeur FROM " . DB_PREFIX . "config WHERE cle = ?");
        $stmt->execute([$cle]);
        $result = $stmt->fetch();
        if (!$result) return null;
        
        // Liste des clés qui ne doivent pas être traitées comme JSON
        $nonJsonKeys = ['logo_path'];
        
        if (in_array($result['cle'], $nonJsonKeys)) {
            return $result['valeur'];
        }
        
        // Pour les autres clés, on essaie de décoder le JSON de manière sécurisée
        try {
            if (empty($result['valeur'])) {
                return null;
            }
            $decoded = json_decode($result['valeur'], true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (Exception $e) {
            error_log("Erreur décodage JSON pour la clé {$result['cle']}: " . $e->getMessage());
            return $result['valeur'];
        }
    } else {
        $stmt = $pdo->query("SELECT cle, valeur FROM " . DB_PREFIX . "config");
        $config = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['cle'] === 'logo_path') {
                $config[$row['cle']] = $row['valeur'];
            } else {
                try {
                    if (!empty($row['valeur'])) {
                        $decoded = json_decode($row['valeur'], true, 512, JSON_THROW_ON_ERROR);
                        $config[$row['cle']] = $decoded;
                    } else {
                        $config[$row['cle']] = null;
                    }
                } catch (Exception $e) {
                    error_log("Erreur décodage JSON pour la clé {$row['cle']}: " . $e->getMessage());
                    $config[$row['cle']] = $row['valeur'];
                }
            }
        }
        return $config;
    }
}

function setConfig($cle, $valeur) {
    $pdo = Database::getInstance();
    
    // Liste des clés qui ne doivent pas être traitées comme JSON
    $nonJsonKeys = ['logo_path'];
    
    if (in_array($cle, $nonJsonKeys)) {
        $valeurFinale = $valeur;
    } else {
        try {
            if (is_string($valeur)) {
                // Vérifie si c'est déjà du JSON valide
                json_decode($valeur, true, 512, JSON_THROW_ON_ERROR);
                $valeurFinale = $valeur;
            } else {
                // Encode en JSON avec gestion des caractères UTF-8
                $valeurFinale = json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if ($valeurFinale === false) {
                    throw new Exception("Erreur d'encodage JSON");
                }
            }
        } catch (Exception $e) {
            error_log("Erreur setConfig pour la clé $cle: " . $e->getMessage());
            if (is_string($valeur)) {
                $valeurFinale = $valeur;
            } else {
                $valeurFinale = json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
    }
    
    $sql = "INSERT INTO " . DB_PREFIX . "config (cle, valeur) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$cle, $valeurFinale]);
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
    
    // Calculer le taux d'occupation de la semaine en cours
    try {
        $stats['taux_occupation'] = getTauxOccupation($debutSemaine, $finSemaine);
        error_log("Taux occupation calculé: " . $stats['taux_occupation']);
    } catch (Exception $e) {
        error_log("Erreur calcul taux occupation: " . $e->getMessage());
        $stats['taux_occupation'] = 0;
    }
    
    return $stats;
}

// ==================== REMPLISSAGE AUTOMATIQUE ====================

function remplirPlanningAuto($dateDebut, $dateFin) {
    $tournees = getTournees();
    $conducteurs = getConducteurs();
    $succes = 0;
    $echecs = 0;
    $logs = []; // Pour diagnostiquer
    
    $dateActuelle = new DateTime($dateDebut);
    $dateLimite = new DateTime($dateFin);
    
    while ($dateActuelle <= $dateLimite) {
        $dateStr = $dateActuelle->format('Y-m-d');
        $logs[] = "\n=== DATE: $dateStr ===";
        
        // ==================== PHASE 1 : ATTRIBUER TOUS LES TITULAIRES ====================
        $logs[] = "PHASE 1: Titulaires";
        foreach ($tournees as $tournee) {
            // Trouver le conducteur titulaire de cette tournée
            $titulaire = null;
            foreach ($conducteurs as $conducteur) {
                if ($conducteur['tournee_titulaire'] == $tournee['id']) {
                    $titulaire = $conducteur;
                    break;
                }
            }
            
            // Si pas de titulaire, passer à la tournée suivante
            if (!$titulaire) {
                $logs[] = "  [{$tournee['nom']}] Pas de titulaire";
                continue;
            }
            
            $logs[] = "  [{$tournee['nom']}] Titulaire: {$titulaire['prenom']} {$titulaire['nom']}";
            
            // VÉRIFICATION DES PERMIS DU TITULAIRE
            $permisRequis = is_array($tournee['permis_requis']) 
                ? $tournee['permis_requis'] 
                : json_decode($tournee['permis_requis'] ?? '[]', true);
            if (!is_array($permisRequis)) {
                $permisRequis = [$permisRequis];
            }
            
            if (!empty($permisRequis)) {
                $permisTitulaire = $titulaire['permis'];
                
                // Normaliser les permis du titulaire
                if (is_string($permisTitulaire)) {
                    $decoded = json_decode($permisTitulaire, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $permisTitulaire = $decoded;
                    } else {
                        $permisTitulaire = explode(',', $permisTitulaire);
                    }
                }
                if (!is_array($permisTitulaire)) {
                    $permisTitulaire = [$permisTitulaire];
                }
                $permisTitulaire = array_map('trim', $permisTitulaire);
                
                // Vérifier si le titulaire possède au moins un permis requis
                $aPermisValide = false;
                foreach ($permisRequis as $permisReq) {
                    if (in_array(trim($permisReq), $permisTitulaire)) {
                        $aPermisValide = true;
                        break;
                    }
                }
                
                // Si le titulaire n'a pas le bon permis, on le saute
                if (!$aPermisValide) {
                    $logs[] = "    ❌ Permis invalide (requis: " . implode(',', $permisRequis) . ", a: " . implode(',', $permisTitulaire) . ")";
                    continue;
                }
            }
            
            // Déterminer les périodes de la tournée
            $periodes = [];
            if ($tournee['duree'] === 'matin' || $tournee['duree'] === 'journée') {
                $periodes[] = 'matin';
            }
            if ($tournee['duree'] === 'après-midi' || $tournee['duree'] === 'journée') {
                $periodes[] = 'apres-midi';
            }
            // Cas spécial : "matin et après-midi" = 2 tours séparés (comme journée mais 2 cases distinctes)
            if ($tournee['duree'] === 'matin et après-midi') {
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
            }
            
            // CAS PAR DÉFAUT : si duree est null/vide ou valeur inconnue, traiter comme "matin et après-midi"
            if (empty($periodes) && empty($tournee['duree'])) {
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
                $logs[] = "    ⚠️ Durée non définie, traité comme matin et après-midi";
            }
            
            foreach ($periodes as $periode) {
                // Vérifier si la tournée est déjà attribuée
                $attributionExistante = getAttribution($dateStr, $periode, $tournee['id']);
                
                if ($attributionExistante) {
                    // Si déjà attribué AU TITULAIRE, on ne touche pas
                    if ($attributionExistante['conducteur_id'] == $titulaire['id']) {
                        $logs[] = "    [{$periode}] Déjà attribué au titulaire";
                        continue;
                    }
                    // Sinon, on va REMPLACER par le titulaire (suppression puis réattribution)
                    $logs[] = "    [{$periode}] Remplace l'attribution existante par le titulaire";
                    deleteAttribution($attributionExistante['id']);
                }
                
                // Vérifier si le titulaire n'est pas déjà attribué ailleurs
                $dejaAttribue = getConducteurAttribution($titulaire['id'], $dateStr, $periode);
                if ($dejaAttribue) {
                    // LE TITULAIRE EST SUR UNE AUTRE TOURNÉE : on le retire pour le remettre sur SA tournée
                    $logs[] = "    [{$periode}] ⚠️ Titulaire occupé sur T{$dejaAttribue['tournee_id']}, on le retire";
                    deleteAttribution($dejaAttribue['id']);
                }
                
                // Vérifier la disponibilité du titulaire
                $resultat = calculerScoreConducteur($titulaire['id'], $tournee['id'], $dateStr, $periode);
                if ($resultat['disponible']) {
                    addAttribution([
                        'date' => $dateStr,
                        'periode' => $periode,
                        'conducteur_id' => $titulaire['id'],
                        'tournee_id' => $tournee['id'],
                        'score_ia' => $resultat['score']
                    ]);
                    $logs[] = "    [{$periode}] ✅ Attribué (score: {$resultat['score']})";
                    $succes++;
                } else {
                    $logs[] = "    [{$periode}] ❌ Non disponible: {$resultat['details']}";
                }
            }
        }
        
        // ==================== PHASE 2 : COMPLÉTER AVEC REMPLAÇANTS ====================
        $logs[] = "\nPHASE 2: Remplaçants";
        foreach ($tournees as $tournee) {
            $logs[] = "  [{$tournee['nom']}]";
            $periodes = [];
            if ($tournee['duree'] === 'matin' || $tournee['duree'] === 'journée') {
                $periodes[] = 'matin';
            }
            if ($tournee['duree'] === 'après-midi' || $tournee['duree'] === 'journée') {
                $periodes[] = 'apres-midi';
            }
            // Cas spécial : "matin et après-midi" = 2 tours séparés
            if ($tournee['duree'] === 'matin et après-midi') {
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
            }
            
            // CAS PAR DÉFAUT : si duree est null/vide, traiter comme "matin et après-midi"
            if (empty($periodes) && empty($tournee['duree'])) {
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
                $logs[] = "    ⚠️ Durée non définie, traité comme matin et après-midi";
            }
            
            foreach ($periodes as $periode) {
                // Vérifier si déjà attribué
                $attributionExistante = getAttribution($dateStr, $periode, $tournee['id']);
                
                if ($attributionExistante) {
                    // Si c'est un titulaire qui occupe cette place, on ne touche pas
                    $conducteurActuel = null;
                    foreach ($conducteurs as $c) {
                        if ($c['id'] == $attributionExistante['conducteur_id']) {
                            $conducteurActuel = $c;
                            break;
                        }
                    }
                    
                    if ($conducteurActuel && $conducteurActuel['tournee_titulaire'] == $tournee['id']) {
                        $logs[] = "    [{$periode}] Déjà attribué au titulaire";
                        continue;
                    }
                    
                    // Sinon, on va CHERCHER un meilleur remplaçant et remplacer si nécessaire
                    $logs[] = "    [{$periode}] Attribution existante (ID conducteur: {$attributionExistante['conducteur_id']}), recherche de meilleur candidat";
                }
                
                // Chercher le meilleur remplaçant disponible
                $meilleurScore = -1;
                $meilleurConducteur = null;
                
                // Récupérer les permis requis pour cette tournée
                $permisRequis = is_array($tournee['permis_requis']) 
                    ? $tournee['permis_requis'] 
                    : json_decode($tournee['permis_requis'] ?? '[]', true);
                if (!is_array($permisRequis)) {
                    $permisRequis = [$permisRequis];
                }
                
                foreach ($conducteurs as $conducteur) {
                    // RÈGLE STRICTE : Ne JAMAIS prendre un conducteur titulaire pour une autre tournée
                    if ($conducteur['tournee_titulaire'] && $conducteur['tournee_titulaire'] != $tournee['id']) {
                        continue; // Ce conducteur est titulaire d'une autre tournée, on ne le prend pas
                    }
                    
                    // VÉRIFICATION DES PERMIS : Le conducteur doit avoir AU MOINS UN des permis requis
                    if (!empty($permisRequis)) {
                        $permisConducteur = $conducteur['permis'];
                        
                        // Normaliser les permis du conducteur
                        if (is_string($permisConducteur)) {
                            $decoded = json_decode($permisConducteur, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $permisConducteur = $decoded;
                            } else {
                                $permisConducteur = explode(',', $permisConducteur);
                            }
                        }
                        if (!is_array($permisConducteur)) {
                            $permisConducteur = [$permisConducteur];
                        }
                        $permisConducteur = array_map('trim', $permisConducteur);
                        
                        // Vérifier si le conducteur possède au moins un permis requis
                        $aPermisValide = false;
                        foreach ($permisRequis as $permisReq) {
                            if (in_array(trim($permisReq), $permisConducteur)) {
                                $aPermisValide = true;
                                break;
                            }
                        }
                        
                        // Si le conducteur n'a pas le bon permis, on le saute
                        if (!$aPermisValide) {
                            continue;
                        }
                    }
                    
                    // Vérifier si le conducteur n'est pas déjà attribué à cette période
                    $dejaAttribue = getConducteurAttribution($conducteur['id'], $dateStr, $periode);
                    if ($dejaAttribue) {
                        continue;
                    }
                    
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
                    // Si une attribution existante, on la remplace
                    if ($attributionExistante) {
                        // Comparer le score actuel avec le nouveau
                        if ($meilleurScore > ($attributionExistante['score_ia'] ?? 0)) {
                            deleteAttribution($attributionExistante['id']);
                            addAttribution([
                                'date' => $dateStr,
                                'periode' => $periode,
                                'conducteur_id' => $meilleurConducteur['id'],
                                'tournee_id' => $tournee['id'],
                                'score_ia' => $meilleurScore
                            ]);
                            $logs[] = "    [{$periode}] ✅ REMPLACÉ par {$meilleurConducteur['prenom']} {$meilleurConducteur['nom']} (score: $meilleurScore > {$attributionExistante['score_ia']})";
                            $succes++;
                        } else {
                            $logs[] = "    [{$periode}] ⏸️ Conservé (score actuel {$attributionExistante['score_ia']} >= nouveau $meilleurScore)";
                        }
                    } else {
                        // Pas d'attribution existante, on ajoute
                        addAttribution([
                            'date' => $dateStr,
                            'periode' => $periode,
                            'conducteur_id' => $meilleurConducteur['id'],
                            'tournee_id' => $tournee['id'],
                            'score_ia' => $meilleurScore
                        ]);
                        $logs[] = "    [{$periode}] ✅ Remplaçant: {$meilleurConducteur['prenom']} {$meilleurConducteur['nom']} (score: $meilleurScore)";
                        $succes++;
                    }
                } else {
                    $logs[] = "    [{$periode}] ❌ Aucun remplaçant trouvé";
                    if (!$attributionExistante) {
                        $echecs++;
                    }
                }
            }
        }
        
        $dateActuelle->modify('+1 day');
    }
    
    // Écrire les logs dans un fichier pour diagnostic
    file_put_contents(__DIR__ . '/ia_debug.log', implode("\n", $logs));
    
    return ['succes' => $succes, 'echecs' => $echecs, 'logs' => $logs];
}
