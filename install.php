<?php
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=utf-8');

if (file_exists('config.php')) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $dbname = $_POST['dbname'] ?? 'planning_conducteurs';
    $user = $_POST['user'] ?? 'root';
    $pass = $_POST['password'] ?? '';
    $prefix = $_POST['prefix'] ?? 'plan_';
    $admin_username = $_POST['admin_username'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");

        $config_content = "<?php\n";
        $config_content .= "define('DB_HOST', '$host');\n";
        $config_content .= "define('DB_NAME', '$dbname');\n";
        $config_content .= "define('DB_USER', '$user');\n";
        $config_content .= "define('DB_PASS', '$pass');\n";
        $config_content .= "define('DB_PREFIX', '$prefix');\n";
        $config_content .= "define('DB_CHARSET', 'utf8mb4');\n";
        $config_content .= "define('APP_DEBUG', true);\n";

        if (!file_put_contents('config.php', $config_content)) {
            throw new Exception("Impossible de créer config.php. Vérifiez les permissions.");
        }

        // Création des tables
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS `{$prefix}users` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            `nom` VARCHAR(100),
            `email` VARCHAR(255),
            `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `dernier_login` TIMESTAMP NULL,
            `actif` BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `{$prefix}conducteurs` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `nom` VARCHAR(100) NOT NULL,
            `prenom` VARCHAR(100) NOT NULL,
            `permis` VARCHAR(50) NOT NULL,
            `contact` VARCHAR(100),
            `experience` INT DEFAULT 0,
            `tournees_maitrisees` JSON,
            `tournee_titulaire` INT,
            `statut_entreprise` ENUM('CDI','CDD','sous-traitant','interimaire') DEFAULT 'CDI',
            `repos_recurrents` JSON,
            `conges` JSON,
            `statut_temporaire` ENUM('disponible','conge','malade','formation','repos') DEFAULT 'disponible',
            `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `{$prefix}tournees` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `nom` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `zone_geo` VARCHAR(100),
            `type_vehicule` VARCHAR(50),
            `difficulte` INT DEFAULT 1,
            `duree` ENUM('journee','matin','apres-midi') DEFAULT 'journee',
            `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `{$prefix}planning` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `date` DATE NOT NULL,
            `periode` ENUM('matin','apres-midi') NOT NULL,
            `conducteur_id` INT,
            `tournee_id` INT NOT NULL,
            `score_ia` FLOAT DEFAULT 0,
            `statut` VARCHAR(50) DEFAULT 'planifie',
            FOREIGN KEY (`conducteur_id`) REFERENCES `{$prefix}conducteurs`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`tournee_id`) REFERENCES `{$prefix}tournees`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_attribution` (`date`, `periode`, `conducteur_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `{$prefix}config` (
            `cle` VARCHAR(100) PRIMARY KEY,
            `valeur` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Création de l'utilisateur administrateur
        if (empty($admin_username) || empty($admin_password)) {
            throw new Exception("Le nom d'utilisateur et le mot de passe administrateur sont requis");
        }

        $hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `{$prefix}users` (username, password, role, nom) VALUES (?, ?, 'admin', 'Administrateur')");
        $stmt->execute([$admin_username, $hash]);

        // Configuration initiale
        $pdo->exec("
        INSERT INTO `{$prefix}config` (`cle`, `valeur`) VALUES
        ('types_permis', '[\"B\",\"C\",\"C+E\",\"D\",\"EC\"]'),
        ('types_vehicules', '[\"3.5T\",\"7.5T\",\"12T\",\"19T\",\"40T\",\"Porteur\",\"Semi-remorque\"]'),
        ('poids_titulaire', '100'),
        ('poids_connaissance', '80'),
        ('poids_disponibilite', '60'),
        ('poids_experience', '40'),
        ('penalite_interimaire', '-50')
        ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
        ");

        // Création des tournées d'exemple
        $pdo->exec("
        INSERT INTO `{$prefix}tournees` (`nom`, `description`, `zone_geo`, `type_vehicule`, `difficulte`, `duree`) VALUES
        ('Paris Centre', 'Livraison centre de Paris', 'Paris 75', '12T', 3, 'matin'),
        ('Banlieue Nord', 'Tournée banlieue nord', '93/95', '19T', 4, 'journee'),
        ('Express Matin', 'Livraisons express matinales', 'Île-de-France', '7.5T', 2, 'matin'),
        ('Express Après-midi', 'Livraisons express après-midi', 'Île-de-France', '7.5T', 2, 'apres-midi'),
        ('Longue Distance', 'Trajet longue distance', 'National', '40T', 5, 'journee'),
        ('Livraison Locale AM', 'Livraisons locales matin', 'Local', '3.5T', 1, 'matin'),
        ('Livraison Locale PM', 'Livraisons locales après-midi', 'Local', '3.5T', 1, 'apres-midi')
        ");

        // Création des conducteurs d'exemple
        $pdo->exec("
        INSERT INTO `{$prefix}conducteurs` (`nom`, `prenom`, `permis`, `contact`, `experience`, `tournees_maitrisees`, `tournee_titulaire`, `statut_entreprise`, `repos_recurrents`, `conges`) VALUES
        ('Dupont', 'Jean', 'C', 'jean.dupont@email.fr', 5, '[1]', 1, 'CDI', NULL, NULL),
        ('Martin', 'Marie', 'C+E', 'marie.martin@email.fr', 8, '[2, 5]', 2, 'CDI', NULL, NULL),
        ('Durand', 'Pierre', 'C', 'pierre.durand@email.fr', 2, '[3, 4]', NULL, 'CDD', NULL, NULL),
        ('Bernard', 'Sophie', 'C+E', 'sophie.bernard@email.fr', 10, '[1, 2, 3, 4, 5, 6, 7]', NULL, 'sous-traitant', NULL, NULL),
        ('Petit', 'Luc', 'C', 'luc.petit@email.fr', 1, '[]', NULL, 'interimaire', NULL, NULL),
        ('Rousseau', 'Claire', 'C', 'claire.rousseau@email.fr', 4, '[3, 6, 7]', 3, 'CDI', '{\"jours\": [3]}', NULL)
        ");

        $success = "✅ Installation réussie ! Redirection...";
        header("refresh:2;url=index.php");

    } catch (Exception $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Planning Conducteur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .install-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="install-card p-5">
                    <h2 class="text-center mb-4">🚚 Installation Planning Conducteur</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success text-center">
                            <?= htmlspecialchars($success) ?>
                            <div class="spinner-border mt-3" role="status"></div>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Serveur MySQL</label>
                                <input type="text" class="form-control" name="host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nom de la base</label>
                                <input type="text" class="form-control" name="dbname" value="planning_conducteurs" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Utilisateur</label>
                                <input type="text" class="form-control" name="user" value="root" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Préfixe tables</label>
                                <input type="text" class="form-control" name="prefix" value="plan_" required>
                            </div>
                            <hr>
                            <h4 class="mb-3">Compte administrateur</h4>
                            <div class="mb-3">
                                <label class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" name="admin_username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" name="admin_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 btn-lg">🚀 Installer</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
