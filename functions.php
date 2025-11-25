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
    
    // CORRECTION 1 : Gérer les tournées "journée" correctement
    $messageLiberation = '';
    
    if (!empty($d['conducteur_id'])) {
        // Récupérer la tournée de destination
        $tourneeDestination = getTournee($d['tournee_id']);
        $estDestinationJournee = ($tourneeDestination && $tourneeDestination['duree'] === 'journée');
        
        // Chercher les attributions actuelles du conducteur ce jour
        $sqlCheckAll = "SELECT p.*, t.duree, t.nom as tournee_nom
                       FROM " . DB_PREFIX . "planning p
                       JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
                       WHERE p.date = :date AND p.conducteur_id = :conducteur_id";
        $stmtCheckAll = $pdo->prepare($sqlCheckAll);
        $stmtCheckAll->execute([
            ':date' => $d['date'],
            ':conducteur_id' => $d['conducteur_id']
        ]);
        $attributionsExistantes = $stmtCheckAll->fetchAll();
        
        // Vérifier si le conducteur est sur une tournée "journée"
        $aUneJournee = false;
        $tourneeJournee = null;
        $tourneeJourneeId = null;
        foreach ($attributionsExistantes as $attr) {
            if ($attr['duree'] === 'journée') {
                $aUneJournee = true;
                $tourneeJournee = $attr['tournee_nom'];
                $tourneeJourneeId = $attr['tournee_id'];
                break;
            }
        }
        
        // RÈGLE 1 : Si conducteur sur tournée journée ET on l'affecte à une AUTRE tournée
        // → Supprimer TOUTES ses attributions (matin + après-midi)
        if ($aUneJournee && $tourneeJourneeId != $d['tournee_id']) {
            $sqlDeleteAll = "DELETE FROM " . DB_PREFIX . "planning
                            WHERE date = :date AND conducteur_id = :conducteur_id";
            $stmtDeleteAll = $pdo->prepare($sqlDeleteAll);
            $stmtDeleteAll->execute([
                ':date' => $d['date'],
                ':conducteur_id' => $d['conducteur_id']
            ]);
            $messageLiberation = "Le conducteur a été libéré de la tournée « {$tourneeJournee} » (journée complète).";
        }
        // RÈGLE 2 : Si on affecte à une tournée journée
        // → Supprimer TOUTES ses attributions pour libérer toute la journée
        // SAUF celles de la même tournée (pour ne pas supprimer ce qu'on vient d'insérer)
        elseif ($estDestinationJournee) {
            // Construire le message avec toutes les tournées libérées
            $tourneesLiberees = [];
            foreach ($attributionsExistantes as $attr) {
                if ($attr['tournee_id'] != $d['tournee_id']) {
                    $key = $attr['tournee_nom'];
                    if (!in_array($key, $tourneesLiberees)) {
                        $tourneesLiberees[] = $key;
                    }
                }
            }
            
            // Supprimer UNIQUEMENT les attributions des AUTRES tournées
            $sqlDeleteAll = "DELETE FROM " . DB_PREFIX . "planning
                            WHERE date = :date AND conducteur_id = :conducteur_id AND tournee_id != :tournee_id";
            $stmtDeleteAll = $pdo->prepare($sqlDeleteAll);
            $stmtDeleteAll->execute([
                ':date' => $d['date'],
                ':conducteur_id' => $d['conducteur_id'],
                ':tournee_id' => $d['tournee_id']
            ]);
            
            if (!empty($tourneesLiberees)) {
                $messageLiberation = "Le conducteur a été libéré de : " . implode(', ', $tourneesLiberees) . " (pour tournée journée complète).";
            }
        }
        // RÈGLE 3 : Cas normal (ni source ni destination journée)
        // → Supprimer seulement la période concernée
        else {
            $sqlDeletePeriode = "DELETE FROM " . DB_PREFIX . "planning
                                WHERE date = :date AND periode = :periode AND conducteur_id = :conducteur_id";
            $stmtDeletePeriode = $pdo->prepare($sqlDeletePeriode);
            $stmtDeletePeriode->execute([
                ':date' => $d['date'],
                ':periode' => $d['periode'],
                ':conducteur_id' => $d['conducteur_id']
            ]);
        }
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
        if (!empty($messageLiberation)) {
            return ['success' => true, 'message_liberation' => $messageLiberation];
        }
        return true;
    }
    
    // Insertion simple sans ON DUPLICATE KEY UPDATE
    $sqlInsert = "INSERT INTO " . DB_PREFIX . "planning
                  (date, periode, conducteur_id, tournee_id, score_ia, statut)
                  VALUES (:date, :periode, :conducteur_id, :tournee_id, :score_ia, 'planifie')";
    $stmtInsert = $pdo->prepare($sqlInsert);
    
    $result = $stmtInsert->execute([
        ':date' => $d['date'],
        ':periode' => $d['periode'],
        ':conducteur_id' => $d['conducteur_id'],
        ':tournee_id' => $d['tournee_id'],
        ':score_ia' => $d['score_ia'] ?? 0
    ]);
    
    // Retourner avec le message de libération s'il existe
    if (!empty($messageLiberation)) {
        return ['success' => true, 'message_liberation' => $messageLiberation, 'result' => $result];
    }
    return $result;
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
    $experience = max(0, (int)($conducteur['experience'] ?? 0)); // S'assurer que c'est au moins 0
    $pointsExp = min(100, $experience * $poidsExperience);
    $score += $pointsExp;
    if ($experience > 0) {
        $details[] = "Exp. {$experience} ans (+{$pointsExp})";
    }
    
    // 4. Bonus/Malus selon statut
    if ($conducteur['statut_entreprise'] === 'CDI') {
        $score += 10;
        $details[] = "CDI (+10)";
    } elseif ($conducteur['statut_entreprise'] === 'interimaire') {
        $score += $penaliteInterimaire;
        $details[] = "Intérimaire ({$penaliteInterimaire})";
    }
    
    // 5. Ajustement selon la difficulté de la tournée
    $difficulte = (int)($tournee['difficulte'] ?? 3); // Par défaut difficulté moyenne
    
    if ($difficulte >= 5 && $experience < 10) {
        // Tournée très difficile : pénalité importante si < 10 ans d'expérience
        $penalite = -30;
        $score += $penalite;
        $details[] = "Tournée difficile 5 ({$penalite})";
    } elseif ($difficulte === 4 && $experience < 5) {
        // Tournée difficile : pénalité si < 5 ans d'expérience
        $penalite = -20;
        $score += $penalite;
        $details[] = "Tournée difficile 4 ({$penalite})";
    } elseif ($difficulte <= 2 && $experience <= 2) {
        // Tournée facile : bonus pour les débutants (apprentissage)
        $bonus = 15;
        $score += $bonus;
        $details[] = "Tournée facile (+{$bonus})";
    }
    
    // Score final normalisé sur 100
    // scoreMax = Connaissance + Expérience max (100) + CDI bonus (10) + Bonus facile (15)
    $scoreMax = $poidsConnaissance + 100 + 10 + 15;
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

// ==================== OPTIMISATION DE LA CONTINUITE ====================

/**
 * Optimise les attributions pour maximiser la continuité des conducteurs sur plusieurs jours
 * Analyse les changements de tournée et effectue des échanges quand c'est bénéfique
 * 
 * @param string $dateDebut Date de début au format Y-m-d
 * @param string $dateFin Date de fin au format Y-m-d
 * @param array $logs Tableau de logs (passé par référence)
 * @return array ['count' => nombre d'optimisations, 'logs' => logs détaillés]
 */
function optimiserContinuiteConducteurs($dateDebut, $dateFin, &$logs) {
    $pdo = Database::getInstance();
    $optimisationCount = 0;
    $optimisationLogs = [];
    
    // Récupérer TOUTES les attributions de la période
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.prenom,
            c.nom,
            c.statut_entreprise,
            c.tournee_titulaire,
            t.nom as tournee_nom
        FROM " . DB_PREFIX . "planning p
        JOIN " . DB_PREFIX . "conducteurs c ON p.conducteur_id = c.id
        JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
        WHERE p.date BETWEEN ? AND ?
        ORDER BY c.id, p.date, p.periode
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    $attributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grouper par conducteur
    $parConducteur = [];
    foreach ($attributions as $attr) {
        $conducteurId = $attr['conducteur_id'];
        if (!isset($parConducteur[$conducteurId])) {
            $parConducteur[$conducteurId] = [
                'nom' => $attr['prenom'] . ' ' . $attr['nom'],
                'attributions' => []
            ];
        }
        $parConducteur[$conducteurId]['attributions'][] = $attr;
    }
    
    // Pour chaque conducteur, analyser les séquences sur toute la période
    foreach ($parConducteur as $conducteurId => $data) {
        $attrs = $data['attributions'];
        $nom = $data['nom'];
        
        // Ignorer les titulaires sur leur propre tournée
        $estTitulaireSurSaTournee = true;
        foreach ($attrs as $attr) {
            if (!isset($attr['tournee_titulaire']) || $attr['tournee_titulaire'] != $attr['tournee_id']) {
                $estTitulaireSurSaTournee = false;
                break;
            }
        }
        if ($estTitulaireSurSaTournee) {
            continue;
        }
        
        // Analyser les séquences (suite de jours sur même tournée, même période)
        $sequences = [];
        foreach ($attrs as $attr) {
            $key = $attr['tournee_id'] . '_' . $attr['periode'];
            
            if (!isset($sequences[$key])) {
                $sequences[$key] = [];
            }
            $sequences[$key][] = $attr;
        }
        
        // Trouver la tournée dominante (celle avec le plus de jours)
        $tourneeDominante = null;
        $maxJours = 0;
        
        foreach ($sequences as $key => $attrs_seq) {
            if (count($attrs_seq) > $maxJours) {
                $maxJours = count($attrs_seq);
                $tourneeDominante = [
                    'tournee_id' => $attrs_seq[0]['tournee_id'],
                    'tournee_nom' => $attrs_seq[0]['tournee_nom'],
                    'periode' => $attrs_seq[0]['periode'],
                    'count' => count($attrs_seq)
                ];
            }
        }
        
        // Si pas de tournée dominante claire (même nombre partout), on ne fait rien
        if (!$tourneeDominante || $maxJours < 2) {
            continue;
        }
        
        // Maintenant, chercher les jours où le conducteur est sur une AUTRE tournée
        // et essayer de les échanger pour maximiser la continuité
        foreach ($attrs as $attr) {
            $periode = $attr['periode'];
            
            // Si ce jour il est sur sa tournée dominante, OK
            if ($attr['tournee_id'] == $tourneeDominante['tournee_id'] && $periode == $tourneeDominante['periode']) {
                continue;
            }
            
            // Sinon, vérifier si on peut l'échanger avec qui est sur la tournée dominante ce jour
            $date = $attr['date'];
            $tourneeActuelle = $attr['tournee_id'];
            $tourneeVoulue = $tourneeDominante['tournee_id'];
            
            // Trouver qui est sur la tournée dominante ce jour-là
            $stmtAutre = $pdo->prepare("
                SELECT p.*, c.prenom, c.nom, c.tournee_titulaire
                FROM " . DB_PREFIX . "planning p
                JOIN " . DB_PREFIX . "conducteurs c ON p.conducteur_id = c.id
                WHERE p.date = ? AND p.periode = ? AND p.tournee_id = ? AND p.conducteur_id != ?
            ");
            $stmtAutre->execute([$date, $periode, $tourneeVoulue, $conducteurId]);
            $autreConducteur = $stmtAutre->fetch(PDO::FETCH_ASSOC);
            
            if (!$autreConducteur) {
                continue; // Personne sur cette tournée ce jour
            }
            
            // Ne PAS échanger si l'autre est le titulaire de cette tournée
            if (isset($autreConducteur['tournee_titulaire']) && $autreConducteur['tournee_titulaire'] == $tourneeVoulue) {
                $optimisationLogs[] = "  ⏭️ Pas d'échange [$date $periode] : {$autreConducteur['prenom']} {$autreConducteur['nom']} est titulaire de {$tourneeDominante['tournee_nom']}";
                continue;
            }
            
            // Calculer les scores pour les deux configurations
            try {
                $scoreActuel1 = (float)($attr['score_ia'] ?? 0);
                $scoreActuel2 = (float)($autreConducteur['score_ia'] ?? 0);
                
                $scoreEchange1 = calculerScoreConducteur($conducteurId, $tourneeVoulue, $date, $periode);
                $scoreEchange2 = calculerScoreConducteur($autreConducteur['conducteur_id'], $tourneeActuelle, $date, $periode);
                
                if (!$scoreEchange1['disponible'] || !$scoreEchange2['disponible']) {
                    continue;
                }
                
                $scoreActuelTotal = $scoreActuel1 + $scoreActuel2;
                $scoreEchangeTotal = $scoreEchange1['score'] + $scoreEchange2['score'];
                $gainScore = $scoreEchangeTotal - $scoreActuelTotal;
                
                // Calculer les pertes/gains individuels
                $perteGainConducteur1 = $scoreEchange1['score'] - $scoreActuel1;
                $perteGainConducteur2 = $scoreEchange2['score'] - $scoreActuel2;
                
                // RÈGLE : Ne PAS échanger si un des conducteurs perd plus de 5 points
                // (évite de sacrifier un conducteur sur sa tournée maîtrisée)
                if ($perteGainConducteur1 < -5 || $perteGainConducteur2 < -5) {
                    $optimisationLogs[] = "  ⏭️ Pas d'échange [$date $periode] : Perte individuelle trop importante (C1: " . round($perteGainConducteur1, 1) . ", C2: " . round($perteGainConducteur2, 1) . ")";
                    continue;
                }
                
                // Échanger si gain global >= +2 points ET aucune perte individuelle > 5 points
                if ($gainScore >= 2) {
                    $pdo->beginTransaction();
                    try {
                        // Échanger les tournées
                        $stmtUpdate1 = $pdo->prepare("
                            UPDATE " . DB_PREFIX . "planning 
                            SET tournee_id = ?, score_ia = ?
                            WHERE id = ?
                        ");
                        $stmtUpdate1->execute([$tourneeVoulue, $scoreEchange1['score'], $attr['id']]);
                        
                        $stmtUpdate2 = $pdo->prepare("
                            UPDATE " . DB_PREFIX . "planning 
                            SET tournee_id = ?, score_ia = ?
                            WHERE id = ?
                        ");
                        $stmtUpdate2->execute([$tourneeActuelle, $scoreEchange2['score'], $autreConducteur['id']]);
                        
                        $pdo->commit();
                        
                        $optimisationCount++;
                        $optimisationLogs[] = "  🔄 CONTINUITÉ [$date $periode] : $nom reste sur {$tourneeDominante['tournee_nom']} ({$tourneeDominante['count']} jours) <-> {$autreConducteur['prenom']} {$autreConducteur['nom']} (gain: " . round($gainScore, 1) . ")";
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $optimisationLogs[] = "  ❌ Erreur DB : " . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $optimisationLogs[] = "  ⚠️ Erreur calcul : " . $e->getMessage();
            }
        }
    }
    
    return [
        'count' => $optimisationCount,
        'logs' => $optimisationLogs
    ];
}

// ==================== REMPLISSAGE AUTOMATIQUE ====================

function remplirPlanningAuto($dateDebut, $dateFin) {
    $tournees = getTournees();
    $conducteurs = getConducteurs();
    $succes = 0;
    $echecs = 0;
    $logs = ["🔴🔴🔴 FICHIER FUNCTIONS.PHP VERSION 1817 LIGNES - PAS DE PHASE 2.5 🔴🔴🔴"]; // Pour diagnostiquer
    
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
        
        // ÉTAPE 2.1 : Trier les tournées par priorité
        // Priorité ABSOLUE : Tournées SANS titulaire (besoin permanent comme T39)
        // Priorité 2 : Tournées avec maîtrise disponible (mais qui ont un titulaire)
        // Priorité 3 : Autres tournées
        $tourneesSansTitulaire = [];
        $tourneesAvecTitulaireEtMaitrise = [];
        $tourneesAutres = [];
        
        foreach ($tournees as $tournee) {
            // Vérifier si la tournée a un titulaire
            $aTitulaire = false;
            foreach ($conducteurs as $c) {
                if ($c['tournee_titulaire'] == $tournee['id']) {
                    $aTitulaire = true;
                    break;
                }
            }
            
            // Si pas de titulaire = priorité absolue (ex: T39, Rennes)
            if (!$aTitulaire) {
                $tourneesSansTitulaire[] = $tournee;
                continue;
            }
            
            // Sinon, vérifier si quelqu'un maîtrise cette tournée (pour remplacement ponctuel)
            $aConducteurQuiMaitrise = false;
            foreach ($conducteurs as $conducteur) {
                if ($conducteur['tournee_titulaire'] && $conducteur['tournee_titulaire'] != $tournee['id']) {
                    continue;
                }
                
                if (!empty($conducteur['tournees_maitrisees'])) {
                    $maitrisees = json_decode($conducteur['tournees_maitrisees'], true);
                    if (is_array($maitrisees) && in_array($tournee['id'], $maitrisees)) {
                        $aConducteurQuiMaitrise = true;
                        break;
                    }
                }
            }
            
            if ($aConducteurQuiMaitrise) {
                $tourneesAvecTitulaireEtMaitrise[] = $tournee;
            } else {
                $tourneesAutres[] = $tournee;
            }
        }
        
        // Fusionner : 1) sans titulaire, 2) avec maîtrise, 3) autres
        $tourneesOrdonnees = array_merge($tourneesSansTitulaire, $tourneesAvecTitulaireEtMaitrise, $tourneesAutres);
        
        $logs[] = "  🎯 Ordre: " . count($tourneesSansTitulaire) . " sans titulaire (priorité), " . count($tourneesAvecTitulaireEtMaitrise) . " avec maîtrise, " . count($tourneesAutres) . " autres";
        
        // Créer un index des périodes restantes à traiter par tournée (pour la réservation RÈGLE 6)
        // Structure: $periodesATraiter[tournee_id] = ['matin' => true, 'apres-midi' => true]
        $periodesATraiter = [];
        foreach ($tourneesOrdonnees as $t) {
            $periodesATraiter[$t['id']] = [];
            if ($t['duree'] === 'matin' || $t['duree'] === 'journée' || $t['duree'] === 'matin et après-midi') {
                $periodesATraiter[$t['id']]['matin'] = true;
            }
            if ($t['duree'] === 'après-midi' || $t['duree'] === 'journée' || $t['duree'] === 'matin et après-midi') {
                $periodesATraiter[$t['id']]['apres-midi'] = true;
            }
        }
        
        foreach ($tourneesOrdonnees as $indexTournee => $tournee) {
            $logs[] = "  [{$tournee['nom']}]";
            $periodes = [];
            $estJournee = false;
            
            if ($tournee['duree'] === 'journée') {
                // Tournée journée : traiter comme un bloc atomique
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
                $estJournee = true;
            } elseif ($tournee['duree'] === 'matin') {
                $periodes[] = 'matin';
            } elseif ($tournee['duree'] === 'après-midi') {
                $periodes[] = 'apres-midi';
            } elseif ($tournee['duree'] === 'matin et après-midi') {
                // Cas spécial : "matin et après-midi" = 2 tours séparés
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
            } elseif (empty($tournee['duree'])) {
                // CAS PAR DÉFAUT : si duree est null/vide, traiter comme "matin et après-midi"
                $periodes[] = 'matin';
                $periodes[] = 'apres-midi';
                $logs[] = "    ⚠️ Durée non définie, traité comme matin et après-midi";
            }
            
            // CAS SPÉCIAL : Tournée "journée" - traiter atomiquement
            if ($estJournee) {
                // Vérifier si déjà complètement attribué au titulaire
                $attrMatin = getAttribution($dateStr, 'matin', $tournee['id']);
                $attrApresMidi = getAttribution($dateStr, 'apres-midi', $tournee['id']);
                
                $estCompletementAttribueAuTitulaire = false;
                if ($attrMatin && $attrApresMidi && $attrMatin['conducteur_id'] == $attrApresMidi['conducteur_id']) {
                    // Vérifier si c'est le titulaire
                    $conducteurActuel = null;
                    foreach ($conducteurs as $c) {
                        if ($c['id'] == $attrMatin['conducteur_id']) {
                            $conducteurActuel = $c;
                            break;
                        }
                    }
                    
                    if ($conducteurActuel && $conducteurActuel['tournee_titulaire'] == $tournee['id']) {
                        $logs[] = "    [journée] Déjà attribué au titulaire";
                        $estCompletementAttribueAuTitulaire = true;
                    }
                }
                
                if (!$estCompletementAttribueAuTitulaire) {
                    // Chercher un conducteur disponible pour TOUTE LA JOURNÉE
                    $candidatsAvecMaitrise = [];
                    $candidatsSansMaitrise = [];
                    
                    // Récupérer les permis requis
                    $permisRequis = is_array($tournee['permis_requis']) 
                        ? $tournee['permis_requis'] 
                        : json_decode($tournee['permis_requis'] ?? '[]', true);
                    if (!is_array($permisRequis)) {
                        $permisRequis = [$permisRequis];
                    }
                    
                    foreach ($conducteurs as $conducteur) {
                        // RÈGLE 1 : Ne JAMAIS prendre un conducteur titulaire pour une autre tournée
                        if ($conducteur['tournee_titulaire'] && $conducteur['tournee_titulaire'] != $tournee['id']) {
                            continue;
                        }
                        
                        // RÈGLE 2 : Vérifier les permis
                        if (!empty($permisRequis)) {
                            $permisConducteur = $conducteur['permis'];
                            
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
                            
                            $aPermisValide = false;
                            foreach ($permisRequis as $permisReq) {
                                if (in_array(trim($permisReq), $permisConducteur)) {
                                    $aPermisValide = true;
                                    break;
                                }
                            }
                            
                            if (!$aPermisValide) {
                                continue;
                            }
                        }
                        
                        // RÈGLE 3 : Vérifier si déjà attribué ailleurs pour MATIN OU APRÈS-MIDI
                        $dejaAttribueMatin = getConducteurAttribution($conducteur['id'], $dateStr, 'matin');
                        $dejaAttribueApresMidi = getConducteurAttribution($conducteur['id'], $dateStr, 'apres-midi');
                        
                        if ($dejaAttribueMatin || $dejaAttribueApresMidi) {
                            continue; // Doit être libre toute la journée
                        }
                        
                        // RÈGLE 4 : Vérifier la disponibilité pour les deux périodes
                        $resultatMatin = calculerScoreConducteur($conducteur['id'], $tournee['id'], $dateStr, 'matin');
                        $resultatApresMidi = calculerScoreConducteur($conducteur['id'], $tournee['id'], $dateStr, 'apres-midi');
                        
                        if (!$resultatMatin['disponible'] || !$resultatApresMidi['disponible']) {
                            continue;
                        }
                        
                        // Utiliser le score moyen
                        $scoreMoyen = ($resultatMatin['score'] + $resultatApresMidi['score']) / 2;
                        
                        // RÈGLE 5 : Vérifier la maîtrise
                        $maitriseCetteTournee = false;
                        $tourneesQuIlMaitrise = [];
                        
                        if (!empty($conducteur['tournees_maitrisees'])) {
                            $maitrisees = json_decode($conducteur['tournees_maitrisees'], true);
                            if (is_array($maitrisees)) {
                                $tourneesQuIlMaitrise = $maitrisees;
                                $maitriseCetteTournee = in_array($tournee['id'], $maitrisees);
                            }
                        }
                        
                        // RÈGLE 6 : Réservation pour tournées maîtrisées
                        if (!$maitriseCetteTournee && !empty($tourneesQuIlMaitrise)) {
                            $aTourneeMaitriseeNonCoverte = false;
                            
                            foreach ($tourneesQuIlMaitrise as $tourneeIdMaitrisee) {
                                // Vérifier périodes à venir
                                if (isset($periodesATraiter[$tourneeIdMaitrisee]) && !empty($periodesATraiter[$tourneeIdMaitrisee])) {
                                    foreach ($periodesATraiter[$tourneeIdMaitrisee] as $periodeRestante => $dummy) {
                                        $dejaAttribueCettePeriode = getConducteurAttribution($conducteur['id'], $dateStr, $periodeRestante);
                                        if (!$dejaAttribueCettePeriode) {
                                            $aTourneeMaitriseeNonCoverte = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                            
                            if ($aTourneeMaitriseeNonCoverte) {
                                continue;
                            }
                        }
                        
                        // Ajouter aux candidats
                        if ($maitriseCetteTournee) {
                            $candidatsAvecMaitrise[] = ['conducteur' => $conducteur, 'score' => $scoreMoyen];
                        } else {
                            $candidatsSansMaitrise[] = ['conducteur' => $conducteur, 'score' => $scoreMoyen];
                        }
                    }
                    
                    // Trier et choisir
                    usort($candidatsAvecMaitrise, function($a, $b) {
                        return $b['score'] - $a['score'];
                    });
                    usort($candidatsSansMaitrise, function($a, $b) {
                        return $b['score'] - $a['score'];
                    });
                    
                    $meilleurConducteur = null;
                    $meilleurScore = -1;
                    
                    if (!empty($candidatsAvecMaitrise)) {
                        $meilleurConducteur = $candidatsAvecMaitrise[0]['conducteur'];
                        $meilleurScore = $candidatsAvecMaitrise[0]['score'];
                    } elseif (!empty($candidatsSansMaitrise)) {
                        $meilleurConducteur = $candidatsSansMaitrise[0]['conducteur'];
                        $meilleurScore = $candidatsSansMaitrise[0]['score'];
                    }
                    
                    if ($meilleurConducteur) {
                        // Attribuer pour MATIN et APRÈS-MIDI
                        try {
                            $resultMatin = addAttribution([
                                'date' => $dateStr,
                                'periode' => 'matin',
                                'tournee_id' => $tournee['id'],
                                'conducteur_id' => $meilleurConducteur['id'],
                                'score_ia' => $meilleurScore
                            ]);
                            
                            $resultApresMidi = addAttribution([
                                'date' => $dateStr,
                                'periode' => 'apres-midi',
                                'tournee_id' => $tournee['id'],
                                'conducteur_id' => $meilleurConducteur['id'],
                                'score_ia' => $meilleurScore
                            ]);
                            
                            $successMatin = is_array($resultMatin) ? $resultMatin['success'] : $resultMatin;
                            $successAM = is_array($resultApresMidi) ? $resultApresMidi['success'] : $resultApresMidi;
                            
                            if ($successMatin && $successAM) {
                                $logs[] = "    [journée] ✅ Remplaçant: {$meilleurConducteur['nom']} {$meilleurConducteur['prenom']} (score: " . round($meilleurScore, 2) . ")";
                                $succes++;
                                
                                // Retirer les deux périodes de la liste à traiter
                                if (isset($periodesATraiter[$tournee['id']])) {
                                    unset($periodesATraiter[$tournee['id']]['matin']);
                                    unset($periodesATraiter[$tournee['id']]['apres-midi']);
                                }
                            } else {
                                $logs[] = "    [journée] ❌ Erreur lors de l'attribution (résultat négatif)";
                                $echecs++;
                            }
                        } catch (Exception $e) {
                            $logs[] = "    [journée] ❌ Exception: " . $e->getMessage();
                            $echecs++;
                        }
                    } else {
                        $logs[] = "    [journée] ⚠️ Aucun conducteur disponible pour toute la journée";
                    }
                }
                
                // Passer à la tournée suivante (ne pas traiter les périodes individuellement)
                continue;
            }
            
            // TRAITEMENT NORMAL pour les autres types de tournées
            foreach ($periodes as $periode) {
                // Retirer cette période de la liste des "à traiter" pour cette tournée
                if (isset($periodesATraiter[$tournee['id']])) {
                    unset($periodesATraiter[$tournee['id']][$periode]);
                }
                
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
                // ÉTAPE 2.2 : Séparer les candidats selon qu'ils maîtrisent ou non la tournée
                $candidatsAvecMaitrise = [];
                $candidatsSansMaitrise = [];
                
                // Récupérer les permis requis pour cette tournée
                $permisRequis = is_array($tournee['permis_requis']) 
                    ? $tournee['permis_requis'] 
                    : json_decode($tournee['permis_requis'] ?? '[]', true);
                if (!is_array($permisRequis)) {
                    $permisRequis = [$permisRequis];
                }
                
                foreach ($conducteurs as $conducteur) {
                    // RÈGLE 1 : Ne JAMAIS prendre un conducteur titulaire pour une autre tournée
                    if ($conducteur['tournee_titulaire'] && $conducteur['tournee_titulaire'] != $tournee['id']) {
                        continue;
                    }
                    
                    // RÈGLE 2 : Vérifier les permis
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
                        
                        $aPermisValide = false;
                        foreach ($permisRequis as $permisReq) {
                            if (in_array(trim($permisReq), $permisConducteur)) {
                                $aPermisValide = true;
                                break;
                            }
                        }
                        
                        if (!$aPermisValide) {
                            continue;
                        }
                    }
                    
                    // RÈGLE 3 : Vérifier si déjà attribué ailleurs
                    $dejaAttribue = getConducteurAttribution($conducteur['id'], $dateStr, $periode);
                    if ($dejaAttribue) {
                        continue; // Déjà attribué ailleurs, on passe au suivant
                    }
                    
                    // RÈGLE 4 : Vérifier la disponibilité
                    $resultat = calculerScoreConducteur($conducteur['id'], $tournee['id'], $dateStr, $periode);
                    
                    if (!$resultat['disponible']) {
                        continue;
                    }
                    
                    // RÈGLE 5 : Vérifier la maîtrise
                    $maitriseCetteTournee = false;
                    $tourneesQuIlMaitrise = [];
                    
                    if (!empty($conducteur['tournees_maitrisees'])) {
                        $maitrisees = json_decode($conducteur['tournees_maitrisees'], true);
                        if (is_array($maitrisees)) {
                            $tourneesQuIlMaitrise = $maitrisees;
                            $maitriseCetteTournee = in_array($tournee['id'], $maitrisees);
                        }
                    }
                    
                    // RÈGLE 6 ASSOUPLIE : Si le conducteur ne maîtrise PAS cette tournée mais en maîtrise d'autres
                    // vérifier qu'il n'a pas de tournée maîtrisée non encore attribuée OU à venir
                    // MAIS : On autorise quand même si AUCUN autre conducteur n'est disponible (logique de dernier recours)
                    if (!$maitriseCetteTournee && !empty($tourneesQuIlMaitrise)) {
                        $aTourneeMaitriseeNonCoverte = false;
                        
                        foreach ($tourneesQuIlMaitrise as $tourneeIdMaitrisee) {
                            // RÈGLE 6A-bis : Si cette tournée maîtrisée a déjà été traitée et le conducteur y est affecté
                            // alors on ne le prend PAS pour une autre période qui entrerait en conflit
                            $attrExistante = getConducteurAttribution($conducteur['id'], $dateStr, $periode);
                            if ($attrExistante && $attrExistante['tournee_id'] == $tourneeIdMaitrisee) {
                                // Le conducteur est déjà sur sa tournée maîtrisée pour cette période
                                // On ne peut pas le prendre (RÈGLE STRICTE)
                                $logs[] = "      🔒 PROTECTION: {$conducteur['nom']} {$conducteur['prenom']} déjà sur tournée maîtrisée ID:{$tourneeIdMaitrisee} [{$periode}] - non utilisable pour [{$tournee['nom']}]";
                                $aTourneeMaitriseeNonCoverte = true;
                                break;
                            }
                            
                            // RÈGLE 6B ASSOUPLIE : Vérifier si sa tournée maîtrisée n'est PAS attribuée
                            // mais seulement la bloquer si elle est en PRIORITÉ (sans titulaire)
                            $tourneeMaitrisee = null;
                            foreach ($tourneesOrdonnees as $t) {
                                if ($t['id'] == $tourneeIdMaitrisee) {
                                    $tourneeMaitrisee = $t;
                                    break;
                                }
                            }
                            
                            if (!$tourneeMaitrisee) continue;
                            
                            // Vérifier si cette tournée maîtrisée est SANS TITULAIRE (priorité absolue)
                            $tourneeMaitriseeEstSansTitulaire = true;
                            foreach ($conducteurs as $c) {
                                if ($c['tournee_titulaire'] == $tourneeIdMaitrisee) {
                                    $tourneeMaitriseeEstSansTitulaire = false;
                                    break;
                                }
                            }
                            
                            // Si la tournée maîtrisée est SANS TITULAIRE et non couverte, BLOQUER (priorité absolue)
                            if ($tourneeMaitriseeEstSansTitulaire) {
                                // Vérifier les périodes de cette tournée
                                $periodesMaitrisee = [];
                                if ($tourneeMaitrisee['duree'] === 'matin' || $tourneeMaitrisee['duree'] === 'journée') {
                                    $periodesMaitrisee[] = 'matin';
                                }
                                if ($tourneeMaitrisee['duree'] === 'après-midi' || $tourneeMaitrisee['duree'] === 'journée') {
                                    $periodesMaitrisee[] = 'apres-midi';
                                }
                                if ($tourneeMaitrisee['duree'] === 'matin et après-midi') {
                                    $periodesMaitrisee[] = 'matin';
                                    $periodesMaitrisee[] = 'apres-midi';
                                }
                                
                                foreach ($periodesMaitrisee as $pM) {
                                    $attrMaitrisee = getAttribution($dateStr, $pM, $tourneeIdMaitrisee);
                                    // Si cette période de sa tournée maîtrisée SANS TITULAIRE n'est pas attribuée
                                    if (!$attrMaitrisee) {
                                        $logs[] = "      🔒 RÉSERVATION PRIORITAIRE: {$conducteur['nom']} {$conducteur['prenom']} réservé pour tournée SANS TITULAIRE ID:{$tourneeIdMaitrisee} [{$pM}] - non utilisable pour [{$tournee['nom']}] [{$periode}]";
                                        $aTourneeMaitriseeNonCoverte = true;
                                        break 2;
                                    }
                                }
                            }
                            // Si la tournée maîtrisée a un titulaire, on AUTORISE le conducteur à aller ailleurs
                            // (le titulaire s'en occupera normalement)
                        }
                        
                        // Si une de ses tournées maîtrisées PRIORITAIRES n'est pas couverte, on ne le prend PAS
                        if ($aTourneeMaitriseeNonCoverte) {
                            continue;
                        }
                        // Sinon, on l'autorise à être candidat même sur une tournée non maîtrisée
                    }
                    
                    // Ajouter aux candidats selon la maîtrise
                    if ($maitriseCetteTournee) {
                        $candidatsAvecMaitrise[] = ['conducteur' => $conducteur, 'score' => $resultat['score']];
                    } else {
                        $candidatsSansMaitrise[] = ['conducteur' => $conducteur, 'score' => $resultat['score']];
                    }
                }
                
                // ÉTAPE 2.3 : Trier chaque liste par score
                usort($candidatsAvecMaitrise, function($a, $b) {
                    return $b['score'] - $a['score'];
                });
                usort($candidatsSansMaitrise, function($a, $b) {
                    return $b['score'] - $a['score'];
                });
                
                // ÉTAPE 2.4 : Choisir le meilleur candidat (priorité à ceux qui maîtrisent)
                $meilleurConducteur = null;
                $meilleurScore = -1;
                
                if (!empty($candidatsAvecMaitrise)) {
                    $meilleurConducteur = $candidatsAvecMaitrise[0]['conducteur'];
                    $meilleurScore = $candidatsAvecMaitrise[0]['score'];
                } elseif (!empty($candidatsSansMaitrise)) {
                    $meilleurConducteur = $candidatsSansMaitrise[0]['conducteur'];
                    $meilleurScore = $candidatsSansMaitrise[0]['score'];
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
    
    // PHASE 3 : OPTIMISATION DE LA CONTINUITÉ
    $logs[] = "\n=== PHASE 3 : OPTIMISATION DE LA CONTINUITÉ ===";
    try {
        $optimisations = optimiserContinuiteConducteurs($dateDebut, $dateFin, $logs);
        $logs = array_merge($logs, $optimisations['logs']);
        $logs[] = "✅ Optimisations effectuées : {$optimisations['count']}";
    } catch (Exception $e) {
        $logs[] = "⚠️ Erreur Phase 3 : " . $e->getMessage();
        $logs[] = "Trace : " . $e->getTraceAsString();
        $optimisations = ['count' => 0];
    }
    
    // Écrire les logs dans un fichier pour diagnostic
    file_put_contents(__DIR__ . '/ia_debug.log', implode("\n", $logs));
    
    return ['succes' => $succes, 'echecs' => $echecs, 'logs' => $logs, 'optimisations' => $optimisations['count'] ?? 0];
}
