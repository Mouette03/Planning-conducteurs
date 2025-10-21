<?php
ini_set('default_charset', 'UTF-8');
if (!file_exists('config.php')) {
    header('Location: install.php');
    exit;
}
require_once 'config.php';
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
            <span class="navbar-text text-white">
                <i class="bi bi-calendar-check me-2"></i><?php echo date('d/m/Y'); ?>
            </span>
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
            <li class="nav-item">
                <button class="nav-link" id="parametres-tab" data-bs-toggle="tab" data-bs-target="#parametres" type="button">
                    <i class="bi bi-gear me-2"></i>Paramètres
                </button>
            </li>
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
                            <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
                            <div class="stat-content">
                                <h5>Cette semaine</h5>
                                <h2 class="stat-number" id="stat-semaine">0</h2>
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
                            <div class="col-md-6">
                                <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Planning Hebdomadaire</h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="d-inline-flex gap-2 align-items-center">
                                    <label class="text-white mb-0">Période :</label>
                                    <input type="date" id="planning-date-debut" class="form-control form-control-sm">
                                    <span class="text-white">au</span>
                                    <input type="date" id="planning-date-fin" class="form-control form-control-sm">
                                    <button class="btn btn-light btn-sm" onclick="chargerPlanning()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="remplirPlanningAuto()">
                                        <i class="bi bi-robot me-1"></i>IA Auto
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="planning-grid" class="table-responsive"></div>
                    </div>
                </div>
            </div>

            <!-- ONGLET PARAMÈTRES -->
            <div class="tab-pane fade" id="parametres" role="tabpanel">
                <div class="row g-4">
                    <!-- Types de permis -->
                    <div class="col-md-4">
                        <div class="card shadow-sm">
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
                    </div>
                    
                    <!-- Types de véhicules -->
                    <div class="col-md-4">
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
                    
                    <!-- Critères IA -->
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-gradient-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-robot me-2"></i>Critères IA</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small">Titulaire</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="poids-titulaire" class="form-control" min="0" max="200">
                                        <span class="input-group-text">pts</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Connaissance tournée</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="poids-connaissance" class="form-control" min="0" max="200">
                                        <span class="input-group-text">pts</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Expérience (par année)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="poids-experience" class="form-control" min="0" max="50">
                                        <span class="input-group-text">pts</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Disponibilité</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="poids-disponibilite" class="form-control" min="0" max="200">
                                        <span class="input-group-text">pts</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Pénalité intérimaire</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="penalite-interimaire" class="form-control" min="-100" max="0">
                                        <span class="input-group-text">pts</span>
                                    </div>
                                </div>
                                <button class="btn btn-primary btn-sm w-100" onclick="sauvegarderCriteresIA()">
                                    <i class="bi bi-save me-1"></i>Sauvegarder
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
</body>
</html>
