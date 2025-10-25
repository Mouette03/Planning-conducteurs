<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'functions.php';

// Si déjà connecté, redirection vers l'accueil
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$logoPath = getConfig('logo_path');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        if (authentifier($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = "Nom d'utilisateur ou mot de passe incorrect";
        }
    } catch (Exception $e) {
        $error = "Erreur lors de la connexion";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Planning Conducteur Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0d6efd 0%, #0099ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            font-size: 2rem;
            color: #0d6efd;
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <?php if ($logoPath): ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" style="max-height: 80px; width: auto; margin-bottom: 1rem;">
            <?php else: ?>
                <i class="bi bi-truck"></i>
            <?php endif; ?>
            <div class="h4">Planning Conducteur Pro</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="acceptRGPD" required>
                <label class="form-check-label small" for="acceptRGPD">
                    J'accepte la <a href="#" data-bs-toggle="modal" data-bs-target="#modalRGPD" onclick="event.stopPropagation();">politique de confidentialité (RGPD)</a>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Connexion
            </button>
        </form>
    </div>

    <!-- Modal RGPD (version simplifiée pour login) -->
    <div class="modal fade" id="modalRGPD" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-shield-check me-2"></i>Politique de Confidentialité & RGPD
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        En vous connectant, vous acceptez notre politique de confidentialité.
                    </div>

                    <h5>Données collectées</h5>
                    <p>Nous collectons uniquement les données nécessaires au fonctionnement de l'application :</p>
                    <ul>
                        <li>Identifiants de connexion (nom d'utilisateur, mot de passe chiffré)</li>
                        <li>Informations professionnelles (conducteurs, tournées, plannings)</li>
                        <li>Logs de connexion (date, heure, adresse IP)</li>
                    </ul>

                    <h5>Utilisation des données</h5>
                    <ul>
                        <li>Gestion de votre compte et authentification</li>
                        <li>Planification et organisation des tournées</li>
                        <li>Statistiques et optimisation du planning</li>
                        <li>Sécurité et traçabilité des opérations</li>
                    </ul>

                    <h5>Vos droits</h5>
                    <p>Conformément au RGPD, vous disposez des droits suivants :</p>
                    <ul>
                        <li><strong>Droit d'accès</strong> à vos données personnelles</li>
                        <li><strong>Droit de rectification</strong> de vos données</li>
                        <li><strong>Droit à l'effacement</strong> de vos données</li>
                        <li><strong>Droit d'opposition</strong> au traitement</li>
                        <li><strong>Droit à la portabilité</strong> de vos données</li>
                    </ul>

                    <h5>Sécurité</h5>
                    <ul>
                        <li>✅ Mots de passe chiffrés (bcrypt)</li>
                        <li>✅ Connexions sécurisées (HTTPS recommandé)</li>
                        <li>✅ Gestion des accès par rôles</li>
                        <li>✅ Sauvegardes régulières</li>
                    </ul>

                    <h5>Contact</h5>
                    <p>
                        Pour toute question concernant vos données personnelles :<br>
                        <strong>Email :</strong> <a href="mailto:[VOTRE-EMAIL]">[VOTRE-EMAIL]</a>
                    </p>

                    <div class="alert alert-success">
                        <i class="bi bi-shield-lock-fill me-2"></i>
                        <strong>Aucune donnée n'est partagée avec des tiers.</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>