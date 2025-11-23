<?php
/**
 * Script de diagnostic pour l'IA de planning
 * Vérifie les permis des conducteurs et des tournées
 */

require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC IA PLANNING ===\n\n";

// Récupérer toutes les tournées
$tournees = getTournees();
echo "--- TOURNÉES (" . count($tournees) . ") ---\n";
foreach ($tournees as $t) {
    echo "\n[{$t['id']}] {$t['nom']}\n";
    echo "  Type: " . ($t['type_tournee'] ?? 'N/A') . "\n";
    echo "  Durée: {$t['duree']}\n";
    echo "  Permis requis (brut): ";
    var_dump($t['permis_requis']);
    
    // Parser les permis requis
    $permisRequis = is_array($t['permis_requis']) 
        ? $t['permis_requis'] 
        : json_decode($t['permis_requis'] ?? '[]', true);
    if (!is_array($permisRequis)) {
        $permisRequis = [$permisRequis];
    }
    echo "  Permis requis (parsé): " . implode(', ', $permisRequis) . "\n";
}

// Récupérer tous les conducteurs
$conducteurs = getConducteurs();
echo "\n\n--- CONDUCTEURS (" . count($conducteurs) . ") ---\n";
foreach ($conducteurs as $c) {
    echo "\n[{$c['id']}] {$c['prenom']} {$c['nom']}\n";
    echo "  Tournée titulaire: " . ($c['tournee_titulaire'] ?? 'Aucune') . "\n";
    echo "  Permis (brut): ";
    var_dump($c['permis']);
    
    // Parser les permis du conducteur
    $permisConducteur = $c['permis'];
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
    echo "  Permis (parsé): " . implode(', ', $permisConducteur) . "\n";
}

// Test de compatibilité
echo "\n\n--- COMPATIBILITÉ CONDUCTEURS/TOURNÉES ---\n";
foreach ($tournees as $t) {
    echo "\n{$t['nom']}:\n";
    
    $permisRequis = is_array($t['permis_requis']) 
        ? $t['permis_requis'] 
        : json_decode($t['permis_requis'] ?? '[]', true);
    if (!is_array($permisRequis)) {
        $permisRequis = [$permisRequis];
    }
    
    $compatible = [];
    foreach ($conducteurs as $c) {
        $permisConducteur = $c['permis'];
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
        
        // Vérifier compatibilité
        $aPermisValide = false;
        if (!empty($permisRequis)) {
            foreach ($permisRequis as $permisReq) {
                if (in_array(trim($permisReq), $permisConducteur)) {
                    $aPermisValide = true;
                    break;
                }
            }
        } else {
            $aPermisValide = true; // Pas de permis requis
        }
        
        if ($aPermisValide) {
            $compatible[] = $c['prenom'] . ' ' . $c['nom'];
        }
    }
    
    echo "  Conducteurs compatibles: " . (empty($compatible) ? "AUCUN !" : implode(', ', $compatible)) . "\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";
