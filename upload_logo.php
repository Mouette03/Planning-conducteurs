<?php
require_once 'config.php';
require_once 'database.php';
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

    $file = $_FILES['logo'];
    
    // Vérifications de base
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur upload: ' . $file['error']);
    }

    // Vérification de la taille (2MB max)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('Le fichier est trop volumineux (max 2MB)');
    }

    // Vérification du type MIME
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé');
    }

    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = 'logo_' . time() . '.' . $extension;
    $uploadPath = __DIR__ . '/uploads/logos/' . $newFilename;

    // Supprimer l'ancien logo si existant
    $oldLogo = getConfig('logo_path');
    if ($oldLogo && file_exists(__DIR__ . '/' . $oldLogo)) {
        unlink(__DIR__ . '/' . $oldLogo);
    }

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }

    // Mettre à jour la configuration
    setConfig('logo_path', 'uploads/logos/' . $newFilename);

    echo json_encode([
        'success' => true,
        'message' => 'Logo mis à jour avec succès',
        'path' => 'uploads/logos/' . $newFilename
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}