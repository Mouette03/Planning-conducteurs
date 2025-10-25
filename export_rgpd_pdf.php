<?php
/**
 * Export RGPD au format PDF
 * Génère un document PDF professionnel avec toutes les données personnelles
 * Utilise FPDF (bibliothèque simple sans dépendances)
 */

// Nettoyer tout output précédent pour éviter les erreurs FPDF
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Fonction de conversion UTF-8 pour FPDF (remplace utf8_decode déprécié)
function utf8ToLatin1($text) {
    if (empty($text)) return $text;
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
}

// Fonction pour formater une date/string de congé
function formatConge($conge) {
    if (is_array($conge)) {
        // Si c'est un array, extraire les infos pertinentes
        if (isset($conge['date'])) {
            return $conge['date'];
        }
        return implode(' - ', array_filter($conge));
    }
    return $conge;
}

// Vérifier l'authentification
verifierAuthentification();

$conducteurId = $_GET['conducteur_id'] ?? 0;

if (!$conducteurId) {
    die('Erreur : ID conducteur manquant');
}

// Télécharger FPDF si pas encore présent
if (!file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    // Créer le dossier fpdf
    if (!is_dir(__DIR__ . '/fpdf')) {
        mkdir(__DIR__ . '/fpdf', 0755, true);
    }
    
    // Télécharger FPDF
    $fpdfUrl = 'http://www.fpdf.org/en/dl.php?v=186&f=zip';
    $fpdfZip = __DIR__ . '/fpdf.zip';
    
    file_put_contents($fpdfZip, file_get_contents($fpdfUrl));
    
    $zip = new ZipArchive;
    if ($zip->open($fpdfZip) === TRUE) {
        $zip->extractTo(__DIR__ . '/fpdf');
        $zip->close();
        unlink($fpdfZip);
    }
}

require(__DIR__ . '/fpdf/fpdf.php');

// Récupérer les données
$pdo = Database::getInstance();

$conducteur = getConducteur($conducteurId);
if (!$conducteur) {
    die('Erreur : Conducteur introuvable');
}

// Historique de planning (30 derniers jours)
$dateDebut = date('Y-m-d', strtotime('-30 days'));
$stmt = $pdo->prepare("
    SELECT p.*, t.nom as tournee_nom, t.type_tournee 
    FROM " . DB_PREFIX . "planning p
    LEFT JOIN " . DB_PREFIX . "tournees t ON p.tournee_id = t.id
    WHERE p.conducteur_id = ? AND p.date >= ?
    ORDER BY p.date DESC, p.periode ASC
    LIMIT 100
");
$stmt->execute([$conducteurId, $dateDebut]);
$historiquePlanning = $stmt->fetchAll();

// Statistiques de performance
$dateDebutStats = date('Y-m-d', strtotime('-30 days'));
$dateFin = date('Y-m-d');
$performance = getPerformanceConducteur($conducteurId, $dateDebutStats, $dateFin);

// Créer le PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// En-tête
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(13, 110, 253);
$pdf->Cell(0, 10, utf8ToLatin1('EXPORT DES DONNÉES PERSONNELLES (RGPD)'), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, utf8ToLatin1('Document généré le : ') . date('d/m/Y à H:i'), 0, 1, 'C');
$pdf->Cell(0, 6, utf8ToLatin1('Conducteur : ') . utf8ToLatin1($conducteur['prenom'] . ' ' . $conducteur['nom']), 0, 1, 'C');
$pdf->Ln(10);

// Finalité
$pdf->SetFillColor(209, 236, 241);
$pdf->SetFont('Arial', 'B', 9);
$pdf->MultiCell(0, 6, utf8ToLatin1('Finalité : Export des données personnelles conformément au RGPD - Article 15 (Droit d\'accès).'), 0, 'L', true);
$pdf->Ln(5);

// Section 1 : Informations personnelles
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(13, 110, 253);
$pdf->Cell(0, 8, utf8ToLatin1('1. Informations Personnelles'), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFillColor(248, 249, 250);
$pdf->SetFont('Arial', '', 10);

$permis = is_array($conducteur['permis']) ? implode(', ', $conducteur['permis']) : $conducteur['permis'];

$infos = [
    'Nom' => $conducteur['nom'] ?? '-',
    'Prénom' => $conducteur['prenom'] ?? '-',
    'Permis de conduire' => $permis ?? '-',
    'Années d\'expérience' => ($conducteur['experience'] ?? '0') . ' ans',
    'Statut entreprise' => $conducteur['statut_entreprise'] ?? '-',
    'Zone géographique' => $conducteur['zone_geo'] ?? '-',
    'Connaissance tournées' => ($conducteur['connaissance'] ?? '0') . '/100',
    'Date de création' => $conducteur['date_creation'] ?? '-'
];

foreach ($infos as $label => $value) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(60, 6, utf8ToLatin1($label . ' :'), 0, 0, 'L', true);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, utf8ToLatin1($value), 0, 1, 'L', true);
}

$pdf->Ln(5);

// Section 2 : Disponibilités
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(13, 110, 253);
$pdf->Cell(0, 8, utf8ToLatin1('2. Disponibilités et Absences'), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Repos récurrents :'), 0, 1);
$pdf->SetFont('Arial', '', 9);

$reposRecurrents = json_decode($conducteur['repos_recurrents'] ?? '[]', true);
if (!empty($reposRecurrents) && is_array($reposRecurrents)) {
    foreach ($reposRecurrents as $jour) {
        $pdf->Cell(10);
        $jourText = is_array($jour) ? implode(', ', $jour) : $jour;
        $pdf->Cell(0, 5, utf8ToLatin1('- ' . $jourText), 0, 1);
    }
} else {
    $pdf->Cell(10);
    $pdf->Cell(0, 5, utf8ToLatin1('Aucun repos récurrent défini'), 0, 1);
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Congés planifiés :'), 0, 1);
$pdf->SetFont('Arial', '', 9);

$conges = json_decode($conducteur['conges'] ?? '[]', true);
if (!empty($conges) && is_array($conges)) {
    foreach (array_slice($conges, 0, 10) as $conge) {
        $pdf->Cell(10);
        $congeText = formatConge($conge);
        $pdf->Cell(0, 5, utf8ToLatin1('- ' . $congeText), 0, 1);
    }
} else {
    $pdf->Cell(10);
    $pdf->Cell(0, 5, utf8ToLatin1('Aucun congé planifié'), 0, 1);
}

$pdf->Ln(5);

// Section 3 : Historique de planning
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(13, 110, 253);
$pdf->Cell(0, 8, utf8ToLatin1('3. Historique de Planning'), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8ToLatin1('Période : Du ' . date('d/m/Y', strtotime($dateDebut)) . ' à aujourd\'hui'), 0, 1);
$pdf->Cell(0, 5, utf8ToLatin1('Nombre d\'attributions : ' . count($historiquePlanning)), 0, 1);
$pdf->Ln(3);

if (!empty($historiquePlanning)) {
    // Tableau d'historique (limité aux 30 premières lignes)
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(13, 110, 253);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(25, 6, 'Date', 1, 0, 'C', true);
    $pdf->Cell(25, 6, utf8ToLatin1('Période'), 1, 0, 'C', true);
    $pdf->Cell(70, 6, utf8ToLatin1('Tournée'), 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Type', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'Score', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 7);
    
    foreach (array_slice($historiquePlanning, 0, 30) as $i => $h) {
        $fill = ($i % 2 == 0);
        $pdf->SetFillColor(248, 249, 250);
        
        $pdf->Cell(25, 5, date('d/m/Y', strtotime($h['date'])), 1, 0, 'C', $fill);
        $pdf->Cell(25, 5, utf8ToLatin1($h['periode'] ?? '-'), 1, 0, 'C', $fill);
        $pdf->Cell(70, 5, utf8ToLatin1(substr($h['tournee_nom'] ?? '-', 0, 30)), 1, 0, 'L', $fill);
        $pdf->Cell(40, 5, utf8ToLatin1($h['type_tournee'] ?? '-'), 1, 0, 'C', $fill);
        $pdf->Cell(20, 5, round($h['score_ia'] ?? 0) . '/100', 1, 1, 'C', $fill);
    }
    
    if (count($historiquePlanning) > 30) {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, utf8ToLatin1('... et ' . (count($historiquePlanning) - 30) . ' autres attributions'), 0, 1, 'C');
    }
}

// Nouvelle page pour les informations RGPD
$pdf->AddPage();

// Section 4 : Performance
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(13, 110, 253);
$pdf->Cell(0, 8, utf8ToLatin1('4. Statistiques de Performance'), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$performanceScore = $performance['score'] ?? 0;
$pdf->Cell(60, 6, utf8ToLatin1('Score de performance :'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, $performanceScore . '%', 0, 1);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, utf8ToLatin1('(Calculé sur les 3 derniers mois)'), 0, 1);
$pdf->Ln(5);

// Section 5 : Informations RGPD
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(13, 110, 253);
$pdf->Cell(0, 8, utf8ToLatin1('5. Informations RGPD'), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Responsable du traitement :'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 5, utf8ToLatin1('[NOM DE VOTRE ENTREPRISE]' . "\n" . '[ADRESSE]' . "\n" . 'Email : [EMAIL]'));
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Finalité du traitement :'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 5, utf8ToLatin1('Gestion et planification des tournées de livraison, suivi des performances et des disponibilités.'));
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Vos droits RGPD :'), 0, 1);
$pdf->SetFont('Arial', '', 8);
$droits = [
    'Droit d\'accès à vos données personnelles',
    'Droit de rectification de vos données',
    'Droit à l\'effacement de vos données',
    'Droit à la limitation du traitement',
    'Droit à la portabilité de vos données',
    'Droit d\'opposition au traitement'
];
foreach ($droits as $droit) {
    $pdf->Cell(5);
    $pdf->Cell(0, 5, utf8ToLatin1('- ' . $droit), 0, 1);
}
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Contact pour exercer vos droits :'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8ToLatin1('Email : [EMAIL DPO/CONTACT]'), 0, 1);
$pdf->Cell(0, 5, utf8ToLatin1('Délai de réponse : Maximum 1 mois'), 0, 1);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, utf8ToLatin1('Réclamation :'), 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 5, utf8ToLatin1('CNIL - 3 Place de Fontenoy, TSA 80715, 75334 PARIS CEDEX 07' . "\n" . 'Tél : 01 53 73 22 22 - www.cnil.fr'));

// Footer
$pdf->Ln(10);
$pdf->SetFillColor(248, 249, 250);
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 5, utf8ToLatin1('Document confidentiel - Contient des données personnelles protégées par le RGPD.' . "\n" . 'Généré automatiquement par Planning Conducteur Pro le ' . date('d/m/Y à H:i')), 0, 'C', true);

// Output PDF
$pdf->Output('I', 'export_rgpd_' . $conducteur['nom'] . '_' . date('Y-m-d') . '.pdf');
