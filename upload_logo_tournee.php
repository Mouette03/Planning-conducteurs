<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Vérification de l'authentification et des droits admin
verifierAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    if (!isset($_FILES['logo'])) {
        throw new Exception('Aucun fichier envoyé');
    }

    if (!isset($_POST['type_nom'])) {
        throw new Exception('Nom du type de tournée manquant');
    }

    $typeNom = $_POST['type_nom'];
    $file = $_FILES['logo'];
    
    // Vérifications de base
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur upload: ' . $file['error']);
    }

    // Vérification de la taille (1MB max pour les icônes)
    if ($file['size'] > 1 * 1024 * 1024) {
        throw new Exception('Le fichier est trop volumineux (max 1MB)');
    }

    // Vérification du type MIME
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé (JPG, PNG, GIF, BMP uniquement)');
    }

    // Générer un nom de fichier unique basé sur le type de tournée
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $typeNom);
    $newFilename = 'tournee_' . $safeName . '_' . time() . '.' . $extension;
    $uploadDir = __DIR__ . '/uploads/logos_tournees/';
    $uploadPath = $uploadDir . $newFilename;

    // Créer le répertoire si nécessaire
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier de destination');
        }
    }

    if (!is_writable($uploadDir)) {
        throw new Exception('Le dossier de destination n\'est pas accessible en écriture');
    }

    // Récupérer la configuration actuelle des types de tournée
    $config = getConfig();
    $typesTournee = $config['types_tournee'] ?? [];
    
    // Trouver le type de tournée
    $typeIndex = -1;
    foreach ($typesTournee as $index => $type) {
        if ($type['nom'] === $typeNom) {
            $typeIndex = $index;
            break;
        }
    }

    if ($typeIndex === -1) {
        throw new Exception('Type de tournée introuvable');
    }

    // Supprimer l'ancien logo si c'est un fichier (pas un emoji)
    $oldLogo = $typesTournee[$typeIndex]['logo'] ?? '';
    if ($oldLogo && strpos($oldLogo, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $oldLogo)) {
        unlink(__DIR__ . '/' . $oldLogo);
    }

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }

    // Mettre à jour la configuration avec le chemin du fichier
    $typesTournee[$typeIndex]['logo'] = 'uploads/logos_tournees/' . $newFilename;
    setConfig('types_tournee', $typesTournee);

    echo json_encode([
        'success' => true,
        'message' => 'Logo du type de tournée mis à jour avec succès',
        'path' => 'uploads/logos_tournees/' . $newFilename
    ]);

} catch (Exception $e) {
    error_log('Erreur upload_logo_tournee.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
