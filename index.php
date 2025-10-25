<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
if (!file_exists('config.php')) {
    header('Location: install.php');
    exit;
}
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';
require_once 'auth.php';

// Vérification de l'authentification
verifierAuthentification();

// Récupération des informations utilisateur
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Conducteur Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-truck me-2"></i>Planning Conducteur Pro
            </a>
            
            <!-- Score de performance global (centré) -->
            <div class="mx-auto">
                <div class="badge bg-white text-dark" id="score-performance-header" style="font-size: 0.95rem; padding: 0.6rem 1rem;">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Performance : <span id="score-performance-value">--</span>%
                </div>
            </div>
            
            <div class="d-flex align-items-center">
                <?php if ($logoPath = getConfig('logo_path')): ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="navbar-logo me-3" 
                     style="max-height: 40px; width: auto;">
                <?php endif; ?>
                
                <span class="navbar-text text-white me-3">
                    <i class="bi bi-calendar-check me-2"></i><?php echo date('d/m/Y'); ?>
                </span>
                
                <!-- Bouton Notice -->
                <button class="btn btn-outline-light me-2" onclick="ouvrirNotice()" title="Guide d'utilisation">
                    <i class="bi bi-info-circle"></i>
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($user['username']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="ouvrirMonProfil(); return false;">
                            <i class="bi bi-person me-2"></i>Mon profil
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <!-- Onglets de navigation -->
        <ul class="nav nav-tabs nav-tabs-modern mb-3" id="mainTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="accueil-tab" data-bs-toggle="tab" data-bs-target="#accueil" type="button">
                    <i class="bi bi-house-door me-2"></i>Accueil
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="conducteurs-tab" data-bs-toggle="tab" data-bs-target="#conducteurs" type="button">
                    <i class="bi bi-people me-2"></i>Conducteurs
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tournees-tab" data-bs-toggle="tab" data-bs-target="#tournees" type="button">
                    <i class="bi bi-geo-alt me-2"></i>Tournées
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="planning-tab" data-bs-toggle="tab" data-bs-target="#planning" type="button">
                    <i class="bi bi-calendar3 me-2"></i>Planning
                </button>
            </li>
            <?php if ($user['role'] === 'admin'): ?>
            <li class="nav-item">
                <button class="nav-link" id="parametres-tab" data-bs-toggle="tab" data-bs-target="#parametres" type="button">
                    <i class="bi bi-gear me-2"></i>Paramètres
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="utilisateurs-tab" data-bs-toggle="tab" data-bs-target="#utilisateurs" type="button">
                    <i class="bi bi-people me-2"></i>Utilisateurs
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content" id="mainTabsContent">
            <!-- ONGLET ACCUEIL -->
            <div class="tab-pane fade show active" id="accueil" role="tabpanel">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-gradient-primary text-white">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div class="stat-content">
                                <h5>Conducteurs</h5>
                                <h2 class="stat-number" id="stat-conducteurs">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-gradient-success text-white">
                            <div class="stat-icon"><i class="bi bi-geo-alt"></i></div>
                            <div class="stat-content">
                                <h5>Tournées</h5>
                                <h2 class="stat-number" id="stat-tournees">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-gradient-info text-white">
                            <div class="stat-icon"><i class="bi bi-clipboard-check"></i></div>
                            <div class="stat-content">
                                <h5>Taux d'occupation</h5>
                                <h2 class="stat-number" id="stat-taux-occupation">0%</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-gradient-warning text-white">
                            <div class="stat-icon"><i class="bi bi-robot"></i></div>
                            <div class="stat-content">
                                <h5>Performance Planning</h5>
                                <h2 class="stat-number" id="stat-score-ia">0</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ONGLET CONDUCTEURS -->
            <div class="tab-pane fade" id="conducteurs" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="bi bi-people me-2"></i>Gestion des Conducteurs</h4>
                    <button class="btn btn-primary" onclick="afficherModalConducteur()">
                        <i class="bi bi-plus-circle me-2"></i>Ajouter un conducteur
                    </button>
                </div>
                <div id="liste-conducteurs" class="row g-4"></div>
            </div>

            <!-- ONGLET TOURNÉES -->
            <div class="tab-pane fade" id="tournees" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4><i class="bi bi-geo-alt me-2"></i>Gestion des Tournées</h4>
                    <button class="btn btn-success" onclick="afficherModalTournee()">
                        <i class="bi bi-plus-circle me-2"></i>Ajouter une tournée
                    </button>
                </div>
                <div id="liste-tournees" class="row g-4"></div>
            </div>

            <!-- ONGLET PLANNING -->
            <div class="tab-pane fade" id="planning" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-header bg-gradient-primary text-white">
                        <div class="row align-items-center">
                            <div class="col-md-8 d-flex align-items-center gap-3">
                                <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Planning Hebdomadaire</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="text-white mb-0 small">Période :</label>
                                    <input type="date" id="planning-date-debut" class="form-control form-control-sm" style="width: 150px;">
                                    <span class="text-white small">au</span>
                                    <input type="date" id="planning-date-fin" class="form-control form-control-sm" style="width: 150px;">
                                    <button class="btn btn-light btn-sm" onclick="chargerPlanning()">
                                        <i class="bi bi-check-lg me-1"></i>Valider
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-info" onclick="actualiserPlanning()" title="Régénère le planning en tenant compte des modifications de disponibilité">
                                        <i class="bi bi-arrow-repeat me-1"></i>Actualiser suite modifications
                                    </button>
                                    <div class="btn-group">
                                        <button class="btn btn-danger" onclick="effacerPlanningPeriode()" title="Efface uniquement la période affichée">
                                            <i class="bi bi-trash me-1"></i>Effacer période
                                        </button>
                                        <?php if ($user['role'] === 'admin'): ?>
                                        <button class="btn btn-danger" onclick="effacerPlanningComplet()" title="Efface TOUT le planning (toutes les semaines) - Admin uniquement">
                                            <i class="bi bi-trash-fill me-1"></i>Effacer TOUT
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-warning" onclick="remplirPlanningAuto()">
                                            <i class="bi bi-robot me-1"></i>IA Auto
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Navigation semaine par semaine -->
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                            <button class="btn btn-outline-primary" onclick="naviguerSemaine(-1)" title="Semaine précédente">
                                <i class="bi bi-chevron-left"></i> Semaine précédente
                            </button>
                            <div class="text-center">
                                <strong id="semaine-affichee">Semaine en cours</strong>
                                <br>
                                <small class="text-muted" id="periode-affichee"></small>
                            </div>
                            <button class="btn btn-outline-primary" onclick="naviguerSemaine(1)" title="Semaine suivante">
                                Semaine suivante <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div id="planning-grid" class="planning-scroll-container"></div>
                    </div>
                </div>
            </div>

            <!-- ONGLET PARAMÈTRES -->
            <div class="tab-pane fade" id="parametres" role="tabpanel">
                <div class="row g-4">
                    <!-- COLONNE GAUCHE : Critères IA + Logo -->
                    <div class="col-md-4">
                        <!-- Critères IA -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-gradient-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-robot me-2"></i>Critères IA</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small d-flex justify-content-between">
                                        <span>Connaissance tournée</span>
                                        <span class="badge bg-primary" id="label-poids-connaissance">80</span>
                                    </label>
                                    <input type="range" id="poids-connaissance" class="form-range" min="0" max="100" step="5"
                                           oninput="document.getElementById('label-poids-connaissance').textContent = this.value">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small d-flex justify-content-between">
                                        <span>Expérience (par année)</span>
                                        <span class="badge bg-primary" id="label-poids-experience">2</span>
                                    </label>
                                    <input type="range" id="poids-experience" class="form-range" min="0" max="5" step="0.5"
                                           oninput="document.getElementById('label-poids-experience').textContent = this.value">
                                    <small class="text-muted">Max 100 pts (40 ans × valeur)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small d-flex justify-content-between">
                                        <span>Disponibilité</span>
                                        <span class="badge bg-primary" id="label-poids-disponibilite">60</span>
                                    </label>
                                    <input type="range" id="poids-disponibilite" class="form-range" min="0" max="100" step="5"
                                           oninput="document.getElementById('label-poids-disponibilite').textContent = this.value">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small d-flex justify-content-between">
                                        <span>Pénalité intérimaire</span>
                                        <span class="badge bg-danger" id="label-penalite-interimaire">-50</span>
                                    </label>
                                    <input type="range" id="penalite-interimaire" class="form-range" min="-100" max="0" step="5"
                                           oninput="document.getElementById('label-penalite-interimaire').textContent = this.value">
                                </div>
                                <button class="btn btn-primary btn-sm w-100" onclick="sauvegarderCriteresIA()">
                                    <i class="bi bi-save me-1"></i>Sauvegarder
                                </button>
                            </div>
                        </div>
                        
                        <!-- Logo de l'entreprise -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-image me-2"></i>Logo de l'entreprise</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3" id="logo-preview">
                                    <?php 
                                    $logoPath = getConfig('logo_path');
                                    if ($logoPath): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($logoPath); ?>" 
                                             alt="Logo" class="img-fluid mb-2" style="max-height: 100px">
                                        <button class="btn btn-sm btn-danger d-block w-100" onclick="supprimerLogo()">
                                            <i class="bi bi-trash me-1"></i>Supprimer le logo
                                        </button>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <i class="bi bi-image" style="font-size: 3rem;"></i>
                                            <p>Aucun logo</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <form id="formLogo" enctype="multipart/form-data" class="mt-3">
                                    <div class="mb-2">
                                        <input type="file" id="logo-file" class="form-control" 
                                               accept="image/png,image/jpeg,image/gif">
                                        <small class="text-muted d-block mt-1">
                                            Format : PNG, JPEG ou GIF (max 2MB)
                                        </small>
                                    </div>
                                    <button type="button" class="btn btn-primary w-100" onclick="uploadLogo()">
                                        <i class="bi bi-cloud-upload me-1"></i>Télécharger
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- COLONNE MILIEU : Types de Permis + Types de Véhicules -->
                    <div class="col-md-4">
                        <!-- Types de permis -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-award me-2"></i>Types de Permis</h6>
                            </div>
                            <div class="card-body">
                                <div id="liste-permis" class="mb-3"></div>
                                <div class="input-group">
                                    <input type="text" id="nouveau-permis" class="form-control" placeholder="Ex: C, C+E, D">
                                    <button class="btn btn-primary" onclick="ajouterPermis()">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Types de véhicules -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-truck me-2"></i>Types de Véhicules</h6>
                            </div>
                            <div class="card-body">
                                <div id="liste-vehicules" class="mb-3"></div>
                                <div class="input-group">
                                    <input type="text" id="nouveau-vehicule" class="form-control" placeholder="Ex: 12T, 19T">
                                    <button class="btn btn-success" onclick="ajouterVehicule()">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- COLONNE DROITE : Types de Tournée -->
                    <div class="col-md-4">
                        <!-- Types de tournée -->
                        <div class="card shadow-sm mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-signpost-2 me-2"></i>Types de Tournée</h6>
                            </div>
                            <div class="card-body">
                                <div id="liste-types-tournee" class="mb-3"></div>
                                <div class="input-group">
                                    <input type="text" id="nouveau-type-tournee" class="form-control" placeholder="Ex: Express, Messagerie">
                                    <input type="number" id="ordre-type-tournee" class="form-control" style="max-width:70px" placeholder="Ordre" min="1">
                                    <button class="btn btn-info" onclick="ajouterTypeTournee()">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                </div>
                                <small class="text-muted">L'ordre détermine la priorité d'affichage dans le planning</small>
                            </div>
                        </div>

                        <!-- Export RGPD -->
                        <div class="card shadow-sm border-primary">
                            <div class="card-header bg-gradient-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-download me-2"></i>Export RGPD</h6>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Exportez toutes les données personnelles d'un conducteur (conformité RGPD)
                                </p>
                                <div class="mb-3">
                                    <label class="form-label small">Sélectionner un conducteur</label>
                                    <select id="conducteur-export-rgpd" class="form-select form-select-sm">
                                        <option value="">-- Choisir un conducteur --</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Format d'export</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="format-export" id="format-json" value="json" checked>
                                        <label class="btn btn-outline-primary btn-sm" for="format-json">
                                            <i class="bi bi-filetype-json"></i> JSON
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="format-export" id="format-pdf" value="pdf">
                                        <label class="btn btn-outline-primary btn-sm" for="format-pdf">
                                            <i class="bi bi-file-pdf"></i> PDF
                                        </label>
                                    </div>
                                </div>
                                <button class="btn btn-primary btn-sm w-100" onclick="exporterDonneesRGPD()">
                                    <i class="bi bi-file-earmark-arrow-down me-2"></i>Télécharger les données
                                </button>
                                <small class="text-muted d-block mt-2">
                                    <strong>JSON :</strong> Données structurées<br>
                                    <strong>PDF :</strong> Document officiel imprimable
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ONGLET UTILISATEURS (ADMIN ONLY) -->
            <?php if ($user['role'] === 'admin'): ?>
            <div class="tab-pane fade" id="utilisateurs" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Gestion des Utilisateurs</h5>
                        <button class="btn btn-light" onclick="afficherModalUtilisateur()">
                            <i class="bi bi-person-plus me-2"></i>Nouvel utilisateur
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Rôle</th>
                                        <th>Email</th>
                                        <th>Dernière connexion</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="liste-utilisateurs"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Notice / Guide d'utilisation -->
    <div class="modal fade" id="modalNotice" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-book me-2"></i>Guide d'utilisation - Planning Conducteur Pro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="accordionNotice">
                        
                        <!-- Section 1: Vue d'ensemble -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#section1">
                                    <i class="bi bi-house-door me-2"></i>Vue d'ensemble
                                </button>
                            </h2>
                            <div id="section1" class="accordion-collapse collapse show" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Statistiques en temps réel</h6>
                                    <p>Le tableau de bord affiche les indicateurs clés :</p>
                                    <ul>
                                        <li><strong>Conducteurs :</strong> Nombre total de conducteurs enregistrés</li>
                                        <li><strong>Tournées :</strong> Nombre total de tournées actives</li>
                                        <li><strong>Taux d'occupation :</strong> Pourcentage de cases remplies dans le planning de la semaine en cours (tournées × périodes × jours actifs)</li>
                                        <li><strong>Performance Planning :</strong> Score global de qualité des attributions (0-100%)</li>
                                    </ul>
                                    <p class="mb-0"><em>💡 Le score de performance est aussi affiché en permanence dans le bandeau supérieur.</em></p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Gestion du Planning -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section2">
                                    <i class="bi bi-calendar3 me-2"></i>Gestion du Planning
                                </button>
                            </h2>
                            <div id="section2" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Navigation</h6>
                                    <ul>
                                        <li>Utilisez les boutons <strong>Semaine précédente / Semaine suivante</strong> pour naviguer</li>
                                        <li>Le planning affiche une semaine complète (Lundi à Dimanche)</li>
                                        <li>Chaque journée est divisée en <strong>Matin</strong> et <strong>Après-midi</strong></li>
                                    </ul>
                                    
                                    <h6 class="mt-3">Attribution manuelle</h6>
                                    <ol>
                                        <li>Cliquez sur le menu déroulant d'une case (tournée + période)</li>
                                        <li>Sélectionnez un conducteur dans la liste</li>
                                        <li>Le système vérifie automatiquement :
                                            <ul>
                                                <li>✅ Permis requis</li>
                                                <li>✅ Disponibilité (congés, repos récurrents)</li>
                                                <li>✅ Double attribution</li>
                                            </ul>
                                        </li>
                                        <li>Un score IA est affiché pour chaque conducteur disponible</li>
                                    </ol>
                                    
                                    <h6 class="mt-3">Remplissage automatique (IA)</h6>
                                    <ol>
                                        <li>Cliquez sur <strong>"Remplir auto (IA)"</strong></li>
                                        <li><strong>Phase 1 :</strong> Tous les titulaires sont affectés à leur tournée</li>
                                        <li><strong>Phase 2 :</strong> Les tournées restantes sont complétées avec les meilleurs remplaçants</li>
                                        <li>Les titulaires ne sont <strong>jamais</strong> réaffectés ailleurs en mode auto</li>
                                    </ol>
                                    
                                    <h6 class="mt-3">Actualisation</h6>
                                    <p>Le bouton <strong>"Actualiser scores"</strong> réanalyse le planning et :</p>
                                    <ol>
                                        <li>Retire les conducteurs devenus indisponibles (nouveaux congés, repos récurrents, fin de contrat)</li>
                                        <li>Réattribue les créneaux vides avec la logique IA (titulaires prioritaires)</li>
                                        <li>Recalcule tous les scores de performance</li>
                                    </ol>
                                    <p><em>💡 Utile après avoir modifié les disponibilités des conducteurs ou les critères IA.</em></p>
                                    
                                    <p class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Attention :</strong> En mode manuel, vous pouvez affecter un titulaire à une autre tournée, mais une confirmation sera demandée.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Conducteurs -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section3">
                                    <i class="bi bi-people me-2"></i>Gestion des Conducteurs
                                </button>
                            </h2>
                            <div id="section3" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Création d'un conducteur</h6>
                                    <ol>
                                        <li>Cliquez sur <strong>"Nouveau conducteur"</strong></li>
                                        <li>Renseignez les informations :
                                            <ul>
                                                <li><strong>Nom, Prénom</strong> (obligatoires)</li>
                                                <li><strong>Permis :</strong> Sélection multiple possible</li>
                                                <li><strong>Expérience :</strong> Nombre d'années (influence le score IA)</li>
                                                <li><strong>Statut :</strong> CDI, CDD, Intérimaire, Temporaire</li>
                                                <li><strong>Tournée titulaire :</strong> Tournée principale du conducteur</li>
                                                <li><strong>Tournées maîtrisées :</strong> Tournées qu'il connaît bien</li>
                                            </ul>
                                        </li>
                                    </ol>
                                    
                                    <h6 class="mt-3">Disponibilité</h6>
                                    <ul>
                                        <li><strong>Repos récurrents :</strong> Jours fixes de repos chaque semaine (ex: Dimanche)</li>
                                        <li><strong>Congés :</strong> Périodes d'absence avec dates de début et fin</li>
                                        <li><strong>CDD/Temporaire :</strong> Date de fin de contrat</li>
                                    </ul>
                                    
                                    <p class="alert alert-info mb-0">
                                        <i class="bi bi-lightbulb me-2"></i>
                                        Un conducteur est automatiquement bloqué dans le planning s'il est en congé, repos récurrent, ou hors période de contrat.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Tournées -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section4">
                                    <i class="bi bi-geo-alt me-2"></i>Gestion des Tournées
                                </button>
                            </h2>
                            <div id="section4" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Création d'une tournée</h6>
                                    <ol>
                                        <li>Cliquez sur <strong>"Nouvelle tournée"</strong></li>
                                        <li>Configurez :
                                            <ul>
                                                <li><strong>Nom :</strong> Identifiant de la tournée</li>
                                                <li><strong>Type de tournée :</strong> Express, Messagerie, Standard, etc. (détermine l'ordre d'affichage automatiquement)</li>
                                                <li><strong>Permis requis :</strong> Sélection multiple des permis nécessaires</li>
                                                <li><strong>Type de véhicule</strong></li>
                                                <li><strong>Jours actifs :</strong> Quels jours de la semaine cette tournée fonctionne</li>
                                                <li><strong>Périodes :</strong> Matin, Après-midi, ou les deux</li>
                                            </ul>
                                        </li>
                                    </ol>
                                    
                                    <p class="alert alert-success mb-0">
                                        <i class="bi bi-lightbulb me-2"></i>
                                        L'ordre d'affichage dans le planning est <strong>automatiquement déterminé par le type de tournée</strong>. Modifiez l'ordre des types dans l'onglet Paramètres pour réorganiser le planning.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 5: Critères IA -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section5">
                                    <i class="bi bi-robot me-2"></i>Critères IA
                                </button>
                            </h2>
                            <div id="section5" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Comment fonctionne le score IA ?</h6>
                                    <p>Le score IA (0-100%) évalue l'adéquation entre un conducteur et une tournée. Il est calculé uniquement pour les <strong>remplaçants</strong> car les titulaires sont toujours prioritaires.</p>
                                    
                                    <h6 class="mt-3">Critères ajustables</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Critère</th>
                                                    <th>Range</th>
                                                    <th>Défaut</th>
                                                    <th>Impact</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong>Connaissance tournée</strong></td>
                                                    <td>0-100</td>
                                                    <td>80</td>
                                                    <td>Bonus si le conducteur maîtrise cette tournée</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Expérience</strong></td>
                                                    <td>0-5 pts/an</td>
                                                    <td>2</td>
                                                    <td>Points selon années d'expérience (max 100 pts pour 40 ans)</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Disponibilité</strong></td>
                                                    <td>0-100</td>
                                                    <td>60</td>
                                                    <td>Bonus de base pour tous les conducteurs disponibles</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Pénalité intérimaire</strong></td>
                                                    <td>-100 à 0</td>
                                                    <td>-50</td>
                                                    <td>Malus appliqué aux intérimaires (les CDI ont +10 pts)</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <h6 class="mt-3">Exemple de calcul</h6>
                                    <p><strong>Conducteur CDI, 10 ans d'expérience, maîtrise la tournée :</strong></p>
                                    <ul>
                                        <li>Connaissance : +80</li>
                                        <li>Expérience : +20 (10 ans × 2)</li>
                                        <li>Disponibilité : +60</li>
                                        <li>CDI : +10</li>
                                        <li><strong>Total :</strong> 170 / 250 = <span class="badge bg-success">68%</span></li>
                                    </ul>
                                    
                                    <p class="alert alert-warning mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Ajustez ces critères dans l'onglet <strong>Paramètres</strong> pour adapter l'IA à vos priorités.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 6: Paramètres -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section6">
                                    <i class="bi bi-gear me-2"></i>Paramètres
                                </button>
                            </h2>
                            <div id="section6" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Configuration globale</h6>
                                    <ul>
                                        <li><strong>Critères IA :</strong> Barres ajustables pour pondérer chaque critère</li>
                                        <li><strong>Logo entreprise :</strong> Personnalisez le bandeau (PNG, JPEG, GIF max 2MB)</li>
                                        <li><strong>Types de permis :</strong> Gérez la liste des permis (B, C, C+E, D, etc.)</li>
                                        <li><strong>Types de véhicules :</strong> Ajoutez vos catégories (12T, 19T, Fourgon, etc.)</li>
                                        <li><strong>Types de tournée :</strong> Créez vos types et définissez leur ordre d'affichage (Express=1, Standard=2, etc.) - Cet ordre détermine automatiquement l'ordre des tournées dans le planning</li>
                                        <li><strong>Export RGPD :</strong> Exportez toutes les données personnelles d'un conducteur au format JSON ou PDF (conformité RGPD - Droit d'accès)</li>
                                    </ul>
                                    
                                    <h6 class="mt-3">Export RGPD des données</h6>
                                    <p>Dans le respect du Règlement Général sur la Protection des Données (RGPD), vous pouvez :</p>
                                    <ul>
                                        <li>Sélectionner n'importe quel conducteur</li>
                                        <li><strong>Format JSON :</strong> Télécharger un fichier JSON structuré contenant toutes les données (idéal pour traitement informatique)</li>
                                        <li><strong>Format PDF :</strong> Générer un document professionnel lisible et imprimable avec logo de l'entreprise</li>
                                        <li>Comprend : informations personnelles, disponibilités, historique de planning (6 mois), performances (3 mois)</li>
                                        <li>Documents horodatés pour conformité RGPD</li>
                                    </ul>
                                    
                                    <p class="alert alert-info mb-0">
                                        <i class="bi bi-shield-check me-2"></i>
                                        Ces paramètres sont réservés aux <strong>administrateurs</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 7: Utilisateurs -->
                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section7">
                                    <i class="bi bi-shield-lock me-2"></i>Gestion des Utilisateurs (Admin)
                                </button>
                            </h2>
                            <div id="section7" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>Créer un utilisateur</h6>
                                    <ol>
                                        <li>Allez dans l'onglet <strong>Utilisateurs</strong></li>
                                        <li>Cliquez sur <strong>"Nouvel utilisateur"</strong></li>
                                        <li>Définissez le rôle :
                                            <ul>
                                                <li><strong>Utilisateur :</strong> Peut gérer conducteurs, tournées et planning</li>
                                                <li><strong>Administrateur :</strong> Accès complet + paramètres + utilisateurs</li>
                                            </ul>
                                        </li>
                                    </ol>
                                    
                                    <p class="alert alert-danger mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Seuls les administrateurs peuvent créer/modifier/supprimer des utilisateurs.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Section 8: Astuces -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section8">
                                    <i class="bi bi-lightbulb me-2"></i>Astuces et Bonnes Pratiques
                                </button>
                            </h2>
                            <div id="section8" class="accordion-collapse collapse" data-bs-parent="#accordionNotice">
                                <div class="accordion-body">
                                    <h6>🎯 Optimiser l'attribution automatique</h6>
                                    <ul>
                                        <li>Définissez un <strong>titulaire</strong> pour chaque tournée régulière</li>
                                        <li>Indiquez les <strong>tournées maîtrisées</strong> pour les remplaçants</li>
                                        <li>Maintenez à jour les <strong>congés</strong> et <strong>repos récurrents</strong></li>
                                    </ul>
                                    
                                    <h6 class="mt-3">📊 Interpréter le score de performance</h6>
                                    <ul>
                                        <li><span class="badge bg-success">≥ 80%</span> Excellent : Optimisation maximale</li>
                                        <li><span class="badge bg-info">60-79%</span> Bon : Planning équilibré</li>
                                        <li><span class="badge bg-warning">40-59%</span> Moyen : Possibilités d'amélioration</li>
                                        <li><span class="badge bg-danger">&lt; 40%</span> Faible : Réviser les attributions</li>
                                    </ul>
                                    
                                    <h6 class="mt-3">⚡ Raccourcis</h6>
                                    <ul>
                                        <li>Double-cliquez sur une tournée pour voir ses détails</li>
                                        <li>Le badge de performance dans le bandeau se met à jour automatiquement</li>
                                        <li>Les tournées inactives pour un jour donné n'apparaissent pas</li>
                                    </ul>
                                    
                                    <h6 class="mt-3">🔒 Règles strictes</h6>
                                    <ul>
                                        <li>❌ Un conducteur ne peut pas être attribué deux fois le même jour/période</li>
                                        <li>❌ Les permis requis doivent correspondre</li>
                                        <li>❌ Les congés et repos bloquent automatiquement</li>
                                        <li>✅ Les titulaires sont toujours prioritaires en mode auto</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Utilisateur -->
    <?php if ($user['role'] === 'admin'): ?>
    <div class="modal fade" id="modalUtilisateur" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalUtilisateurTitle">Nouvel utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUtilisateur">
                        <input type="hidden" id="u-id">
                        <div class="mb-3">
                            <label class="form-label">Nom d'utilisateur *</label>
                            <input type="text" id="u-username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe <span id="pwd-info">(requis)</span></label>
                            <input type="password" id="u-password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nom complet</label>
                            <input type="text" id="u-nom" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="u-email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rôle</label>
                            <select id="u-role" class="form-select">
                                <option value="user">Utilisateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="u-actif" class="form-check-input" checked>
                            <label class="form-check-label">Compte actif</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="sauvegarderUtilisateur()">
                        <i class="bi bi-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Conducteur -->
    <div class="modal fade" id="modalConducteur" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalConducteurTitle">Conducteur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formConducteur"></form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="sauvegarderConducteur()">
                        <i class="bi bi-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tournée -->
    <div class="modal fade" id="modalTournee" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalTourneeTitle">Tournée</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formTournee"></form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" onclick="sauvegarderTournee()">
                        <i class="bi bi-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer RGPD -->
    <footer class="bg-light border-top mt-5 py-3">
        <div class="container text-center text-muted small">
            <p class="mb-1">
                &copy; <?php echo date('Y'); ?> Planning Conducteur Pro - Tous droits réservés
            </p>
            <p class="mb-0">
                <a href="#" onclick="ouvrirModalRGPD(); return false;" class="text-decoration-none">
                    <i class="bi bi-shield-check me-1"></i>Politique de confidentialité (RGPD)
                </a>
                |
                <a href="#" onclick="ouvrirNotice(); return false;" class="text-decoration-none">
                    <i class="bi bi-question-circle me-1"></i>Aide
                </a>
            </p>
        </div>
    </footer>

    <!-- Modal RGPD -->
    <div class="modal fade" id="modalRGPD" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
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
                        <strong>Dernière mise à jour :</strong> <?php echo date('d/m/Y'); ?>
                    </div>

                    <h4>1. Responsable du traitement</h4>
                    <p>
                        <strong>[NOM DE VOTRE ENTREPRISE]</strong><br>
                        Adresse : [ADRESSE COMPLÈTE]<br>
                        Email : <a href="mailto:[EMAIL]">[EMAIL DE CONTACT]</a><br>
                        Téléphone : [NUMÉRO DE TÉLÉPHONE]
                    </p>

                    <h4>2. Données collectées</h4>
                    <p>Dans le cadre de l'utilisation de notre application de planification des conducteurs, nous collectons les données personnelles suivantes :</p>
                    
                    <h5>2.1 Données des utilisateurs</h5>
                    <ul>
                        <li>Nom d'utilisateur, nom et prénom</li>
                        <li>Adresse email</li>
                        <li>Rôle (administrateur/utilisateur)</li>
                        <li>Dates de connexion et adresse IP</li>
                    </ul>

                    <h5>2.2 Données des conducteurs</h5>
                    <ul>
                        <li>Nom, prénom et permis de conduire</li>
                        <li>Expérience professionnelle</li>
                        <li>Statut d'entreprise (CDI, CDD, sous-traitant, intérimaire)</li>
                        <li>Disponibilités et congés</li>
                        <li>Performances et statistiques de travail</li>
                    </ul>

                    <h4>3. Finalités du traitement</h4>
                    <p>Les données sont collectées pour :</p>
                    <ul>
                        <li>La gestion et planification des tournées de livraison</li>
                        <li>L'attribution optimisée des conducteurs aux tournées</li>
                        <li>Le suivi des performances et statistiques</li>
                        <li>La gestion des disponibilités et congés</li>
                        <li>L'administration des comptes utilisateurs</li>
                    </ul>

                    <h4>4. Base légale du traitement</h4>
                    <ul>
                        <li><strong>Exécution d'un contrat :</strong> Gestion de l'activité professionnelle</li>
                        <li><strong>Intérêt légitime :</strong> Optimisation de la planification</li>
                        <li><strong>Consentement :</strong> Pour certaines données spécifiques</li>
                    </ul>

                    <h4>5. Destinataires des données</h4>
                    <p>Les données sont accessibles uniquement :</p>
                    <ul>
                        <li>Aux administrateurs de l'application</li>
                        <li>Aux utilisateurs selon leurs droits d'accès</li>
                        <li>Aux conducteurs (leurs propres données)</li>
                    </ul>
                    <p class="alert alert-success">
                        <i class="bi bi-shield-lock-fill me-2"></i>
                        <strong>Aucune donnée n'est transmise à des tiers sans votre consentement explicite.</strong>
                    </p>

                    <h4>6. Durée de conservation</h4>
                    <ul>
                        <li>Comptes utilisateurs actifs : Pendant toute la durée d'utilisation</li>
                        <li>Comptes inactifs : Suppression après 2 ans d'inactivité</li>
                        <li>Données de planning : 3 ans (obligations légales)</li>
                        <li>Logs de connexion : 12 mois maximum</li>
                    </ul>

                    <h4>7. Sécurité des données</h4>
                    <p>Mesures de sécurité mises en œuvre :</p>
                    <ul>
                        <li>✅ Chiffrement des mots de passe (hachage bcrypt)</li>
                        <li>✅ Accès sécurisé par authentification</li>
                        <li>✅ Gestion des droits d'accès par rôle</li>
                        <li>✅ Sauvegardes régulières des données</li>
                        <li>✅ Hébergement sécurisé</li>
                    </ul>

                    <h4>8. Vos droits (RGPD)</h4>
                    <p>Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez des droits suivants :</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-eye me-2"></i>Droit d'accès</h6>
                                    <p class="small mb-0">Accéder à vos données personnelles</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-pencil me-2"></i>Droit de rectification</h6>
                                    <p class="small mb-0">Corriger vos données inexactes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-trash me-2"></i>Droit à l'effacement</h6>
                                    <p class="small mb-0">Demander la suppression de vos données</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-pause-circle me-2"></i>Droit à la limitation</h6>
                                    <p class="small mb-0">Limiter le traitement de vos données</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-download me-2"></i>Droit à la portabilité</h6>
                                    <p class="small mb-0">Récupérer vos données dans un format structuré</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-x-circle me-2"></i>Droit d'opposition</h6>
                                    <p class="small mb-0">Vous opposer au traitement de vos données</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-primary">
                        <h6><i class="bi bi-envelope me-2"></i>Exercice de vos droits</h6>
                        <p class="mb-0">
                            Pour exercer vos droits, contactez-nous :<br>
                            <strong>Email :</strong> <a href="mailto:[EMAIL]">[EMAIL DPO/CONTACT]</a><br>
                            <strong>Délai de réponse :</strong> Maximum 1 mois
                        </p>
                    </div>

                    <h4>9. Droit de réclamation</h4>
                    <p>Vous avez le droit d'introduire une réclamation auprès de la CNIL :</p>
                    <div class="card">
                        <div class="card-body">
                            <strong>CNIL</strong><br>
                            3 Place de Fontenoy - TSA 80715<br>
                            75334 PARIS CEDEX 07<br>
                            Téléphone : 01 53 73 22 22<br>
                            Site web : <a href="https://www.cnil.fr" target="_blank">www.cnil.fr</a>
                        </div>
                    </div>

                    <h4 class="mt-4">10. Cookies</h4>
                    <p>Notre application utilise uniquement des <strong>cookies essentiels</strong> pour :</p>
                    <ul>
                        <li>Maintenir la session utilisateur connectée</li>
                        <li>Stocker les préférences d'affichage</li>
                    </ul>
                    <p class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Aucun cookie de tracking ou publicitaire n'est utilisé.</strong>
                    </p>

                    <h4>11. Contact</h4>
                    <p>Pour toute question concernant cette politique :</p>
                    <ul>
                        <li><strong>Email :</strong> <a href="mailto:[EMAIL]">[EMAIL DE CONTACT]</a></li>
                        <li><strong>Téléphone :</strong> [NUMÉRO]</li>
                        <li><strong>Adresse :</strong> [ADRESSE POSTALE]</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="appToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="bi bi-info-circle me-2"></i>
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toastBody"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script src="js/users.js"></script>
</body>
</html>
