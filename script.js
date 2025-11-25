/**
 * script.js - Gère l'interactivité et les appels API de l'application
 */

// Configuration et état de l'application
const AppState = {
    conducteurs: [],
    tournees: [],
    config: {},
    cache: new Map(),
    selectedPeriod: {
        debut: null,
        fin: null
    },
    currentWeekOffset: 0, // Pour la navigation semaine par semaine
    planningFullData: [] // Stocke toutes les données du planning
};

// Variables globales (alias vers AppState pour rétro-compatibilité)
let conducteurs = AppState.conducteurs;
let tournees = AppState.tournees;
let config = AppState.config;

// Gestionnaire d'état et de cache
class StateManager {
    static CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

    static get(key) {
        const cached = AppState.cache.get(key);
        if (cached && Date.now() - cached.timestamp < this.CACHE_DURATION) {
            return cached.data;
        }
        return null;
    }

    static set(key, data) {
        AppState.cache.set(key, {
            data,
            timestamp: Date.now()
        });
    }

    static clear(key) {
        if (key) {
            AppState.cache.delete(key);
        } else {
            AppState.cache.clear();
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await initApp();
    } catch (error) {
        console.error('Erreur initialisation:', error);
        showToast('Erreur', 'Impossible d\'initialiser l\'application', 'danger');
    }
});

// Initialise l'application
async function initApp() {
    try {
        await Promise.all([
            chargerStats(),
            chargerConducteurs(),
            chargerTournees(),
            chargerConfig(),
            chargerUtilisateurs() // Chargement des utilisateurs si admin
        ]);
        
        // Charger le score de performance dans le header
        await updateScoreHeader();
        
        // Restaurer la période de planning depuis localStorage
        restaurerPeriodePlanning();
    } catch (e) {
        console.error('Init error', e);
        showToast('Erreur', 'Impossible de charger les données', 'danger');
    }

    // Configuration des onglets
    setupTabListeners();

    // Mise à jour du texte de la page en fonction du rôle
    if (document.getElementById('mainTabs')) {
        const isAdmin = document.getElementById('utilisateurs-tab') !== null;
        if (!isAdmin) {
            // Masquer les fonctionnalités réservées aux administrateurs
            document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
        }
    }
}

// Restaure la période de planning depuis localStorage
function restaurerPeriodePlanning() {
    const periodeData = JSON.parse(localStorage.getItem('planning_periode') || '{}');
    
    if (periodeData.debut && periodeData.fin) {
        // Restaurer les valeurs dans les champs
        const debutInput = document.getElementById('planning-date-debut');
        const finInput = document.getElementById('planning-date-fin');
        
        if (debutInput && finInput) {
            debutInput.value = periodeData.debut;
            finInput.value = periodeData.fin;
            
            // Restaurer l'état
            AppState.selectedPeriod.debut = periodeData.debut;
            AppState.selectedPeriod.fin = periodeData.fin;
            AppState.currentWeekOffset = periodeData.weekOffset || 0;
            
            // Recharger le planning automatiquement
            apiCall(`get_planning&debut=${periodeData.debut}&fin=${periodeData.fin}`)
                .then(({ data }) => {
                    AppState.planningFullData = data || [];
                    renderPlanningWithNavigation();
                })
                .catch(err => console.error('Erreur restauration planning:', err));
        }
    }
}

// Met à jour les données à chaque changement d'onglet
function setupTabListeners() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', ({ target }) => {
            const pane = target.getAttribute('data-bs-target');
            switch (pane) {
                case '#accueil': chargerStats(); break;
                case '#conducteurs': chargerConducteurs(); break;
                case '#tournees': chargerTournees(); break;
                case '#planning': 
                    // Initialiser les dates de la semaine en cours
                    const debut = document.getElementById('planning-date-debut');
                    const fin = document.getElementById('planning-date-fin');
                    if (debut && fin && !debut.value) {
                        debut.value = getMonday();
                        fin.value = getSunday();
                    }
                    chargerPlanning(); 
                    break;
                case '#parametres': chargerConfig(); break;
            }
        });
    });
}

// Appel API générique
async function apiCall(action, method='GET', data=null) {
    const headers = { 'Content-Type': 'application/json;charset=utf-8' };
    const options = { method, headers };
    let url = `api.php?action=${action}`;

    if (data && method==='GET') {
        url += '&' + new URLSearchParams(data).toString();
    } else if (data) {
        options.body = JSON.stringify(data);
    }

    const res = await fetch(url, options);
    const text = await res.text();
    try {
        const json = JSON.parse(text);
        if (!json.success) throw new Error(json.error || 'Erreur API');
        return json;
    } catch {
        console.error('API response:', text);
        throw new Error('Réponse serveur invalide');
    }
}

// Toast notifications
const toastEl = document.getElementById('appToast');
const toast = toastEl ? new bootstrap.Toast(toastEl) : null;

function showToast(title, message, type='info') {
    if (!toast) return;
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastBody').textContent = message;
    const header = document.querySelector('#appToast .toast-header');
    header.className = `toast-header bg-${type} text-white`;
    toast.show();
}

// Dates semaine
function getMonday() {
    const d = new Date();
    const day = d.getDay() || 7;
    d.setDate(d.getDate() - day + 1);
    return d.toISOString().split('T')[0];
}

function getSunday() {
    const m = new Date(getMonday());
    m.setDate(m.getDate() + 6); // +6 pour dimanche (lundi à samedi = 6 jours)
    return m.toISOString().split('T')[0];
}

// ==================== STATISTIQUES ====================
async function chargerStats() {
    try {
        const { data } = await apiCall('get_stats');
        console.log('Stats reçues:', data); // Debug
        document.getElementById('stat-conducteurs').textContent = data.conducteurs || 0;
        document.getElementById('stat-tournees').textContent = data.tournees || 0;
        document.getElementById('stat-taux-occupation').textContent = `${data.taux_occupation || 0}%`;
        console.log('Taux occupation:', data.taux_occupation); // Debug
        
        // Score de performance global du planning
        const scoreRes = await apiCall(`get_score_global&debut=${getMonday()}&fin=${getSunday()}`);
        if (scoreRes.success && scoreRes.data) {
            const score = scoreRes.data.score_global;
            document.getElementById('stat-score-ia').textContent = `${score}%`;
            
            // Mettre à jour aussi le badge dans le header
            updateScoreHeader(score);
            
            // Changer la couleur selon le score
            const statCard = document.getElementById('stat-score-ia').closest('.stat-card');
            statCard.className = statCard.className.replace(/bg-gradient-\w+/,
                score >= 80 ? 'bg-gradient-success' :
                score >= 60 ? 'bg-gradient-info' :
                score >= 40 ? 'bg-gradient-warning' : 'bg-gradient-danger'
            );
        }
    } catch (error) {
        console.error('Erreur stats:', error);
    }
}

// Fonction pour mettre à jour le score de performance dans le header
async function updateScoreHeader(score = null) {
    try {
        if (score === null) {
            const scoreRes = await apiCall(`get_score_global&debut=${getMonday()}&fin=${getSunday()}`);
            if (scoreRes.success && scoreRes.data) {
                score = scoreRes.data.score_global;
            }
        }
        
        if (score !== null) {
            const scoreValue = document.getElementById('score-performance-value');
            const scoreBadge = document.getElementById('score-performance-header');
            
            if (scoreValue) {
                scoreValue.textContent = score;
            }
            
            if (scoreBadge) {
                // Changer la couleur du badge selon le score
                scoreBadge.className = 'badge me-3';
                if (score >= 80) {
                    scoreBadge.classList.add('bg-success', 'text-white');
                } else if (score >= 60) {
                    scoreBadge.classList.add('bg-info', 'text-white');
                } else if (score >= 40) {
                    scoreBadge.classList.add('bg-warning', 'text-dark');
                } else {
                    scoreBadge.classList.add('bg-danger', 'text-white');
                }
                scoreBadge.style.fontSize = '0.85rem';
                scoreBadge.style.padding = '0.5rem 0.75rem';
            }
        }
    } catch (error) {
        console.error('Erreur mise à jour score header:', error);
    }
}

// ==================== CONDUCTEURS ====================
async function chargerConducteurs() {
    try {
        const { data } = await apiCall('get_conducteurs');
        AppState.conducteurs = data || [];
        conducteurs = AppState.conducteurs; // Mise à jour de la variable globale
        await renderConducteurs();
    } catch (e) {
        console.error('Conducteurs error', e);
    }
}

async function renderConducteurs() {
    const container = document.getElementById('liste-conducteurs');
    if (!container) return;

    if (conducteurs.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted">Aucun conducteur</div>';
        return;
    }
    
    // Peupler le select d'export RGPD
    const selectRGPD = document.getElementById('conducteur-export-rgpd');
    if (selectRGPD) {
        selectRGPD.innerHTML = '<option value="">-- Choisir un conducteur --</option>';
        conducteurs.forEach(c => {
            selectRGPD.innerHTML += `<option value="${c.id}">${c.prenom} ${c.nom}</option>`;
        });
    }

    container.innerHTML = '';
    for (const c of conducteurs) {
        let scorePerformance = 0;
        let badgeClass = 'bg-secondary';
        
        try {
            const perfRes = await apiCall(`get_performance&conducteur_id=${c.id}&debut=${getMonday()}&fin=${getSunday()}`);
            if (perfRes.success && perfRes.data) {
                scorePerformance = Math.round(perfRes.data.score_moyen) || 0;
                if (scorePerformance >= 80) badgeClass = 'bg-success';
                else if (scorePerformance >= 60) badgeClass = 'bg-info';
                else if (scorePerformance >= 40) badgeClass = 'bg-warning';
                else badgeClass = 'bg-danger'; // Score < 40 ou = 0
            }
        } catch (e) {
            console.warn('Erreur performance:', e);
        }

        container.innerHTML += `
        <div class="col-md-4">
            <div class="card conducteur-card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="mb-0">${c.prenom} ${c.nom}</h5>
                        <span class="badge ${badgeClass}">${scorePerformance}/100</span>
                    </div>
                    <p class="text-muted mb-2">
                        <i class="bi bi-award"></i> ${c.permis} |
                        <i class="bi bi-clock"></i> ${c.experience} an(s)
                    </p>
                    <span class="badge bg-secondary">${c.statut_entreprise}</span>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-primary" onclick="afficherModalConducteur(${c.id})">
                            <i class="bi bi-pencil"></i> Modifier
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="supprimerConducteur(${c.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    }
}

function afficherModalConducteur(id=null) {
    const modal = new bootstrap.Modal(document.getElementById('modalConducteur'));
    const form = document.getElementById('formConducteur');
    const title = document.getElementById('modalConducteurTitle');
    const c = id ? conducteurs.find(x => x.id === id) : {};
    
    const tourneesMaitrisees = c.tournees_maitrisees ? JSON.parse(c.tournees_maitrisees) : [];
    const reposRecurrents = c.repos_recurrents ? JSON.parse(c.repos_recurrents) : { jours: [], type: 'toutes' };
    const conges = c.conges ? JSON.parse(c.conges) : [];
    
    title.textContent = id ? 'Modifier le conducteur' : 'Ajouter un conducteur';
    
    form.innerHTML = `
        <input type="hidden" id="c-id" value="${c.id || ''}">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Prénom *</label>
                <input id="c-prenom" class="form-control" value="${c.prenom || ''}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Nom *</label>
                <input id="c-nom" class="form-control" value="${c.nom || ''}" required>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label">Permis détenus *</label>
                <div id="c-permis-container" class="border rounded p-2">
                    ${(config.types_permis || ['B','C','C+E','D','EC']).map(p => {
                        // Parse JSON string if needed
                        let permisArray = [];
                        if (typeof c.permis === 'string' && c.permis.startsWith('[')) {
                            try {
                                permisArray = JSON.parse(c.permis);
                            } catch(e) {
                                permisArray = c.permis ? [c.permis] : [];
                            }
                        } else if (Array.isArray(c.permis)) {
                            permisArray = c.permis;
                        } else if (c.permis) {
                            permisArray = [c.permis];
                        }
                        const checked = permisArray.includes(p) ? 'checked' : '';
                        return `<div class="form-check form-check-inline">
                            <input class="form-check-input permis-checkbox" type="checkbox" 
                                   id="permis-${p}" value="${p}" ${checked}>
                            <label class="form-check-label" for="permis-${p}">${p}</label>
                        </div>`;
                    }).join('')}
                </div>
                <small class="text-muted">Sélectionner tous les permis que le conducteur possède</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Contact</label>
                <input id="c-contact" class="form-control" value="${c.contact || ''}" type="email">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">
                    Date d'embauche 
                    <small class="text-muted">(ancienneté calculée auto)</small>
                </label>
                <input type="date" id="c-date-embauche" class="form-control" value="${c.date_embauche || ''}">
                <small class="text-muted">
                    ${c.date_embauche ? `Ancienneté : ${c.experience || 0} an(s)` : 'Ou renseignez l\'ancienneté manuellement ci-dessous'}
                </small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">
                    Ancienneté (années)
                    <small class="text-muted">(si pas de date d'embauche)</small>
                </label>
                <input type="number" id="c-exp" class="form-control" value="${c.experience || 0}" min="0" 
                       ${c.date_embauche ? 'readonly style="background:#e9ecef"' : ''}>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Statut</label>
                <select id="c-statut" class="form-select">
                    <option value="CDI" ${c.statut_entreprise==='CDI'?'selected':''}>CDI</option>
                    <option value="CDD" ${c.statut_entreprise==='CDD'?'selected':''}>CDD</option>
                    <option value="interimaire" ${c.statut_entreprise==='interimaire'?'selected':''}>Intérimaire</option>
                    <option value="sous-traitant" ${c.statut_entreprise==='sous-traitant'?'selected':''}>Sous-traitant</option>
                </select>
            </div>
        </div>
        <hr>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-star me-2"></i>Tournée titulaire</label>
            <select id="c-titulaire" class="form-select">
                <option value="">Aucune</option>
                ${tournees.map(t => 
                    `<option value="${t.id}" ${c.tournee_titulaire==t.id?'selected':''}>${t.nom}</option>`
                ).join('')}
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-geo-alt me-2"></i>Tournées maîtrisées</label>
            <div id="c-tournees-maitrisees" class="border rounded p-2" style="max-height:150px; overflow-y:auto;">
                ${tournees.map(t => `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${t.id}" 
                               id="tournee-${t.id}" ${tourneesMaitrisees.includes(t.id)?'checked':''}>
                        <label class="form-check-label" for="tournee-${t.id}">${t.nom}</label>
                    </div>
                `).join('')}
            </div>
        </div>
        <hr>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-calendar-x me-2"></i>Repos récurrents</label>
            <div class="mb-2">
                <label class="small text-muted">Jours de repos :</label>
                <div class="d-flex gap-2 flex-wrap">
                    ${['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'].map((jour, i) => `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="${i+1}" 
                                   id="repos-${i+1}" ${reposRecurrents.jours.includes(i+1)?'checked':''}>
                            <label class="form-check-label small" for="repos-${i+1}">${jour.substr(0,3)}</label>
                        </div>
                    `).join('')}
                </div>
            </div>
            <div class="mt-2">
                <label class="small text-muted">Fréquence :</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="type-repos" id="repos-toutes" value="toutes" ${reposRecurrents.type==='toutes'||!reposRecurrents.type?'checked':''}>
                    <label class="btn btn-outline-primary" for="repos-toutes">Toutes les semaines</label>
                    
                    <input type="radio" class="btn-check" name="type-repos" id="repos-paires" value="paires" ${reposRecurrents.type==='paires'?'checked':''}>
                    <label class="btn btn-outline-info" for="repos-paires">Semaines paires</label>
                    
                    <input type="radio" class="btn-check" name="type-repos" id="repos-impaires" value="impaires" ${reposRecurrents.type==='impaires'?'checked':''}>
                    <label class="btn btn-outline-warning" for="repos-impaires">Semaines impaires</label>
                </div>
                <small class="text-muted d-block mt-1">
                    <i class="bi bi-info-circle"></i> Semaine ${getCurrentWeekNumber()} = ${getCurrentWeekNumber()%2===0?'Paire':'Impaire'}
                </small>
            </div>
        </div>
        <hr>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-info-circle me-2"></i>Statut temporaire</label>
            <div class="row g-2">
                <div class="col">
                    <select id="c-statut-temp" class="form-select" onchange="toggleStatutTemporaireDate(this)">
                        <option value="disponible" ${c.statut_temporaire==='disponible'||!c.statut_temporaire?'selected':''}>Disponible</option>
                        <option value="conge" ${c.statut_temporaire==='conge'?'selected':''}>En congé</option>
                        <option value="malade" ${c.statut_temporaire==='malade'?'selected':''}>Malade</option>
                        <option value="formation" ${c.statut_temporaire==='formation'?'selected':''}>En formation</option>
                        <option value="repos" ${c.statut_temporaire==='repos'?'selected':''}>Repos</option>
                    </select>
                </div>
                <div class="col-auto" id="statut-temp-date-group" style="display: ${c.statut_temporaire !== 'disponible' ? 'block' : 'none'}">
                    <div class="input-group">
                        <span class="input-group-text">Jusqu'au</span>
                        <input type="date" id="c-statut-temp-fin" class="form-control" 
                               value="${c.statut_temporaire_fin || ''}"
                               min="${new Date().toISOString().split('T')[0]}">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearStatutTemporaireFin()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <small class="text-muted">Laisser vide si pas de date de fin</small>
                </div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-calendar-event me-2"></i>Périodes de congés</label>
            <div id="c-conges-list">
                ${conges.map((cg) => `
                    <div class="input-group mb-2">
                        <input type="date" class="form-control conge-debut" value="${cg.debut}">
                        <span class="input-group-text">au</span>
                        <input type="date" class="form-control conge-fin" value="${cg.fin}">
                        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `).join('')}
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterConge()">
                <i class="bi bi-plus-circle me-1"></i>Ajouter une période de congé
            </button>
        </div>
    `;
    modal.show();
}

function getCurrentWeekNumber() {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + 3 - (d.getDay() + 6) % 7);
    const week1 = new Date(d.getFullYear(), 0, 4);
    return 1 + Math.round(((d - week1) / 86400000 - 3 + (week1.getDay() + 6) % 7) / 7);
}

function ajouterConge() {
    const list = document.getElementById('c-conges-list');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="date" class="form-control conge-debut">
        <span class="input-group-text">au</span>
        <input type="date" class="form-control conge-fin">
        <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()">
            <i class="bi bi-trash"></i>
        </button>
    `;
    list.appendChild(div);
}

// Gestion de l'affichage du champ de date pour le statut temporaire
function toggleStatutTemporaireDate(select) {
    const dateGroup = document.getElementById('statut-temp-date-group');
    if (select.value === 'disponible') {
        dateGroup.style.display = 'none';
        document.getElementById('c-statut-temp-fin').value = '';
    } else {
        dateGroup.style.display = 'block';
    }
}

// Effacer la date de fin du statut temporaire
function clearStatutTemporaireFin() {
    document.getElementById('c-statut-temp-fin').value = '';
}

async function sauvegarderConducteur() {
    const id = document.getElementById('c-id').value;
    
    const tourneesMaitrisees = [];
    document.querySelectorAll('#c-tournees-maitrisees input:checked').forEach(cb => {
        tourneesMaitrisees.push(+cb.value);
    });
    
    const reposJours = [];
    document.querySelectorAll('[id^="repos-"]:checked').forEach(cb => {
        reposJours.push(+cb.value);
    });
    
    const typeRepos = document.querySelector('input[name="type-repos"]:checked')?.value || 'toutes';
    
    const conges = [];
    document.querySelectorAll('#c-conges-list .input-group').forEach(grp => {
        const debut = grp.querySelector('.conge-debut').value;
        const fin = grp.querySelector('.conge-fin').value;
        if (debut && fin) {
            conges.push({ debut, fin });
        }
    });
    
    const statutTemp = document.getElementById('c-statut-temp').value;
    const statutTempFin = document.getElementById('c-statut-temp-fin').value;
    
    // Récupérer les permis cochés
    const permisSelectionnes = [];
    document.querySelectorAll('.permis-checkbox:checked').forEach(cb => {
        permisSelectionnes.push(cb.value);
    });
    
    if (permisSelectionnes.length === 0) {
        showToast('Attention', 'Sélectionner au moins un permis', 'warning');
        return;
    }

    const data = {
        prenom: document.getElementById('c-prenom').value,
        nom: document.getElementById('c-nom').value,
        permis: permisSelectionnes,
        contact: document.getElementById('c-contact').value,
        date_embauche: document.getElementById('c-date-embauche').value || null,
        experience: +document.getElementById('c-exp').value,
        statut_entreprise: document.getElementById('c-statut').value,
        tournees_maitrisees: tourneesMaitrisees,
        tournee_titulaire: document.getElementById('c-titulaire').value || null,
        repos_recurrents: { jours: reposJours, type: typeRepos },
        conges: conges,
        statut_temporaire: statutTemp,
        statut_temporaire_fin: statutTemp === 'disponible' ? null : (statutTempFin || null)
    };
    
    try {
        const conducteurModifie = !!id; // true si modification, false si création
        
        if (id) {
            await apiCall('update_conducteur', 'POST', { id: +id, ...data });
        } else {
            await apiCall('add_conducteur', 'POST', data);
        }
        showToast('Succès', 'Conducteur enregistré', 'success');
        chargerConducteurs();
        
        // Fermer la modale d'abord
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalConducteur'));
        if (modal) {
            modal.hide();
        }
        
        // Attendre que la modale soit fermée
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Si on a modifié un conducteur (peu importe l'onglet actuel)
        if (conducteurModifie) {
            // Proposer de régénérer le planning
            const regenerer = confirm(`✅ Conducteur enregistré avec succès !\n\n🔄 Voulez-vous RÉGÉNÉRER le planning maintenant ?\n\nCela va :\n- Basculer sur l'onglet Planning\n- EFFACER toutes les attributions de la période\n- RÉGÉNÉRER complètement le planning avec la nouvelle logique\n- Placer les conducteurs sur leurs tournées maîtrisées en priorité\n\n⚠️ ATTENTION : Toutes les attributions manuelles seront perdues !`);
            
            if (regenerer) {
                // Basculer sur l'onglet planning
                window.location.hash = '#planning';
                
                // Attendre le changement d'onglet et que le planning soit chargé
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Lancer la régénération IA avec effacement
                const debut = document.getElementById('planning-date-debut').value;
                const fin = document.getElementById('planning-date-fin').value;
                
                if (!debut || !fin) {
                    showToast('Erreur', 'Période non définie', 'danger');
                    return;
                }
                
                try {
                    // Effacer la période
                    showToast('Info', 'Effacement du planning...', 'info');
                    const effacementResponse = await apiCall('effacer_planning_periode', 'POST', { debut, fin });
                    
                    if (effacementResponse.success) {
                        showToast('Info', `${effacementResponse.nb_supprimees || 0} attributions effacées`, 'info');
                        
                        // Régénérer
                        showToast('Info', 'Régénération avec IA...', 'info');
                        const response = await fetch('api.php?action=remplir_auto', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json;charset=utf-8' },
                            body: JSON.stringify({ debut, fin })
                        });
                        const result = await response.json();
                        
                        if (response.ok && result.success) {
                            const { succes, echecs } = result.data;
                            showToast('Succès', `Planning régénéré : ${succes} attributions créées`, 'success');
                            await chargerPlanning();
                        } else {
                            showToast('Erreur', 'Échec de la régénération', 'danger');
                        }
                    }
                } catch (error) {
                    console.error('Erreur régénération:', error);
                    showToast('Erreur', 'Impossible de régénérer le planning', 'danger');
                }
            }
        }
    } catch (error) {
        console.error('Erreur sauvegarde:', error);
    }
}

async function supprimerConducteur(id) {
    if (!confirm('Confirmer suppression ?')) return;
    await apiCall('delete_conducteur', 'POST', { id });
    showToast('OK', 'Conducteur supprimé', 'warning');
    chargerConducteurs();
}

// ==================== TOURNÉES ====================
async function chargerTournees() {
    try {
        const { data } = await apiCall('get_tournees');
        AppState.tournees = data || [];
        tournees = AppState.tournees; // Mise à jour de la variable globale
        renderTournees();
    } catch (e) {
        console.error('Tournees error', e);
    }
}

function renderTournees() {
    const container = document.getElementById('liste-tournees');
    if (!container) return;
    
    if (tournees.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted">Aucune tournée</div>';
        return;
    }
    
    container.innerHTML = '';
    tournees.forEach(t => {
        container.innerHTML += `
        <div class="col-md-4">
            <div class="card tournee-card shadow-sm">
                <div class="card-body">
                    <h5>${t.nom}</h5>
                    <p class="text-muted mb-2">${t.zone_geo||''} - ${t.type_vehicule||''}</p>
                    <span class="badge bg-info">${t.duree}</span>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-success" onclick="afficherModalTournee(${t.id})">
                            <i class="bi bi-pencil"></i> Modifier
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="supprimerTournee(${t.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    });
}

function afficherModalTournee(id=null) {
    const modal = new bootstrap.Modal(document.getElementById('modalTournee'));
    const form = document.getElementById('formTournee');
    const title = document.getElementById('modalTourneeTitle');
    const t = id ? tournees.find(x => x.id === id) : {};
    
    title.textContent = id ? 'Modifier la tournée' : 'Ajouter une tournée';
    
    // Parser les permis requis (peut être string JSON ou array)
    let permisRequis = [];
    if (t.permis_requis) {
        if (typeof t.permis_requis === 'string' && t.permis_requis.startsWith('[')) {
            try {
                permisRequis = JSON.parse(t.permis_requis);
            } catch(e) {
                console.error('Erreur parsing permis_requis:', e);
                permisRequis = [];
            }
        } else if (Array.isArray(t.permis_requis)) {
            permisRequis = t.permis_requis;
        }
    }
    
    form.innerHTML = `
        <input type="hidden" id="t-id" value="${t.id || ''}">
        <div class="mb-3">
            <label class="form-label">Nom *</label>
            <input id="t-nom" class="form-control" value="${t.nom || ''}" required>
        </div>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label">Type de tournée</label>
                <select id="t-type" class="form-select">
                    <option value="">-- Sélectionner --</option>
                    ${(config.types_tournee || []).sort((a,b) => a.ordre - b.ordre).map(type => 
                        `<option value="${type.nom}" ${t.type_tournee===type.nom?'selected':''}>${type.nom} (ordre ${type.ordre})</option>`
                    ).join('')}
                </select>
                <small class="text-muted">L'ordre d'affichage dans le planning est automatiquement déterminé par le type - Configurez les types dans l'onglet Paramètres</small>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Zone géographique</label>
            <input id="t-zone" class="form-control" value="${t.zone_geo || ''}">
        </div>
        <div class="mb-3">
            <label class="form-label">Type de véhicule</label>
            <select id="t-veh" class="form-select">
                <option value="">-- Sélectionner --</option>
                ${(config.types_vehicules || ['7.5T','12T','19T','26T']).map(v => 
                    `<option value="${v}" ${t.type_vehicule===v?'selected':''}>${v}</option>`
                ).join('')}
            </select>
            <small class="text-muted">Configurez les types de véhicules dans l'onglet Paramètres</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Permis requis *</label>
            <div id="t-permis-container" class="border rounded p-2">
                ${(config.types_permis || ['B','C','C+E','D','EC']).map(p => {
                    const checked = permisRequis.includes(p) ? 'checked' : '';
                    return `<div class="form-check form-check-inline">
                        <input class="form-check-input permis-tournee-checkbox" type="checkbox" 
                               id="tournee-permis-${p}" value="${p}" ${checked}>
                        <label class="form-check-label" for="tournee-permis-${p}">${p}</label>
                    </div>`;
                }).join('')}
            </div>
            <small class="text-muted">Permis acceptés pour cette tournée (un conducteur doit avoir au moins un de ces permis)</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Difficulté (1-5)</label>
            <input type="number" id="t-diff" class="form-control" value="${t.difficulte || 1}" min="1" max="5">
        </div>
        <div class="mb-3">
            <label class="form-label">Durée</label>
            <select id="t-duree" class="form-select">
                <option value="matin" ${t.duree==='matin'?'selected':''}>Matin uniquement</option>
                <option value="après-midi" ${t.duree==='après-midi'?'selected':''}>Après-midi uniquement</option>
                <option value="journée" ${t.duree==='journée'?'selected':''}>Journée (1 seul tour)</option>
                <option value="matin et après-midi" ${t.duree==='matin et après-midi'?'selected':''}>Matin et après-midi (2 tours)</option>
            </select>
            <small class="text-muted">
                "Journée" = 1 case (camion part toute la journée)<br>
                "Matin et après-midi" = 2 cases séparées (le camion rentre le midi)
            </small>
        </div>
    `;
    modal.show();
}

async function sauvegarderTournee() {
    const id = document.getElementById('t-id').value;
    
    // Récupérer les permis requis cochés
    const permisRequis = [];
    document.querySelectorAll('.permis-tournee-checkbox:checked').forEach(cb => {
        permisRequis.push(cb.value);
    });
    
    if (permisRequis.length === 0) {
        showToast('Attention', 'Sélectionner au moins un permis requis', 'warning');
        return;
    }
    
    const data = {
        nom: document.getElementById('t-nom').value,
        type_tournee: document.getElementById('t-type').value || null,
        zone_geo: document.getElementById('t-zone').value,
        type_vehicule: document.getElementById('t-veh').value,
        permis_requis: permisRequis,
        difficulte: +document.getElementById('t-diff').value,
        duree: document.getElementById('t-duree').value
    };
    
    console.log('Données à enregistrer:', data);
    
    try {
        if (id) {
            await apiCall('update_tournee', 'POST', { id: +id, ...data });
        } else {
            await apiCall('add_tournee', 'POST', data);
        }
        showToast('Succès', 'Tournée enregistrée', 'success');
        chargerTournees();
        bootstrap.Modal.getInstance(document.getElementById('modalTournee')).hide();
    } catch (error) {
        console.error('Erreur sauvegarde tournee:', error);
        showToast('Erreur', error.message || 'Impossible d\'enregistrer la tournée', 'danger');
    }
}

async function supprimerTournee(id) {
    if (!confirm('Confirmer suppression ?')) return;
    await apiCall('delete_tournee', 'POST', { id });
    showToast('OK', 'Tournée supprimée', 'warning');
    chargerTournees();
}

// ==================== PLANNING ====================
async function chargerPlanning() {
    const debut = document.getElementById('planning-date-debut').value;
    const fin = document.getElementById('planning-date-fin').value;
    
    if (!debut || !fin) {
        showToast('Attention', 'Sélectionner période', 'warning');
        return;
    }
    
    // Sauvegarder la période sélectionnée
    AppState.selectedPeriod.debut = debut;
    AppState.selectedPeriod.fin = fin;
    AppState.currentWeekOffset = 0;
    
    // Sauvegarder dans localStorage
    localStorage.setItem('planning_periode', JSON.stringify({
        debut: debut,
        fin: fin,
        weekOffset: 0
    }));
    
    try {
        const { data } = await apiCall(`get_planning&debut=${debut}&fin=${fin}`);
        AppState.planningFullData = data || [];
        renderPlanningWithNavigation();
        
        // Mettre à jour le score de performance dans le header
        await updateScoreHeader();
    } catch (error) {
        console.error('Erreur planning:', error);
    }
}

// Navigation semaine par semaine
function naviguerSemaine(direction) {
    AppState.currentWeekOffset += direction;
    
    // Sauvegarder la position dans localStorage
    const periodeData = JSON.parse(localStorage.getItem('planning_periode') || '{}');
    periodeData.weekOffset = AppState.currentWeekOffset;
    localStorage.setItem('planning_periode', JSON.stringify(periodeData));
    
    renderPlanningWithNavigation();
}

function renderPlanningWithNavigation() {
    const debut = AppState.selectedPeriod.debut;
    const fin = AppState.selectedPeriod.fin;
    
    if (!debut || !fin) return;
    
    const debutDate = new Date(debut);
    const finDate = new Date(fin);
    const aujourdhui = new Date();
    aujourdhui.setHours(0, 0, 0, 0);
    
    // Calculer toutes les semaines dans la période
    const toutesLesSemaines = [];
    let currentWeekStart = new Date(debutDate);
    
    while (currentWeekStart <= finDate) {
        const weekEnd = new Date(currentWeekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);
        
        toutesLesSemaines.push({
            debut: new Date(currentWeekStart),
            fin: weekEnd > finDate ? new Date(finDate) : weekEnd
        });
        
        currentWeekStart.setDate(currentWeekStart.getDate() + 7);
    }
    
    // Filtrer : garder seulement les semaines >= aujourd'hui (masquer les semaines passées)
    const semaines = toutesLesSemaines.filter(semaine => {
        return semaine.fin >= aujourdhui; // Garder si la fin de semaine n'est pas encore passée
    });
    
    // Si toutes les semaines sont passées, garder au moins la dernière
    if (semaines.length === 0 && toutesLesSemaines.length > 0) {
        semaines.push(toutesLesSemaines[toutesLesSemaines.length - 1]);
    }
    
    // Vérifier que l'offset est valide
    if (AppState.currentWeekOffset < 0) AppState.currentWeekOffset = 0;
    if (AppState.currentWeekOffset >= semaines.length) AppState.currentWeekOffset = semaines.length - 1;
    
    // Obtenir la semaine à afficher
    const semaineAffichee = semaines[AppState.currentWeekOffset];
    
    // Mettre à jour l'affichage de la semaine
    const semaineNum = AppState.currentWeekOffset + 1;
    document.getElementById('semaine-affichee').textContent = 
        `Semaine ${semaineNum} / ${semaines.length}`;
    document.getElementById('periode-affichee').textContent = 
        `Du ${semaineAffichee.debut.toLocaleDateString('fr-FR')} au ${semaineAffichee.fin.toLocaleDateString('fr-FR')}`;
    
    // Afficher le planning pour cette semaine
    renderPlanning(
        AppState.planningFullData, 
        semaineAffichee.debut.toISOString().split('T')[0], 
        semaineAffichee.fin.toISOString().split('T')[0]
    );
}

function renderPlanning(data, debut, fin) {
    const dates = [];
    for (let d = new Date(debut); d <= new Date(fin); d.setDate(d.getDate() + 1)) {
        // Exclure le dimanche (jour 0)
        if (d.getDay() !== 0) {
            dates.push(new Date(d));
        }
    }

    let html = `<table class="table table-bordered planning-table"><thead><tr><th>Tournée</th>`;
    dates.forEach(d => {
        html += `<th class="text-center">${d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' })}</th>`;
    });
    html += `</tr></thead><tbody>`;

    tournees.forEach(t => {
        // Récupérer le logo du type de tournée
        let logoTournee = '';
        if (t.type_tournee) {
            const typeTournee = (config.types_tournee || []).find(type => type.nom === t.type_tournee);
            if (typeTournee && typeTournee.logo) {
                if (typeTournee.logo.startsWith('uploads/')) {
                    // C'est une image uploadée
                    logoTournee = `<br><img src="${typeTournee.logo}" alt="${typeTournee.nom}" style="width: 32px; height: 32px; object-fit: contain;">`;
                } else {
                    // C'est un emoji
                    logoTournee = `<br><span style="font-size: 1.5rem;">${typeTournee.logo}</span>`;
                }
            }
        }
        
        html += `<tr><td><strong>${t.nom}</strong>${logoTournee}<br><small class="text-muted">${t.duree || 'non défini'}</small></td>`;
        dates.forEach(d => {
            const dateStr = d.toISOString().split('T')[0];
            html += `<td class="p-1">`;
            
            // Gestion selon la durée
            if (t.duree === 'journée') {
                // Journée : UNE SEULE case pour toute la journée
                const attrMatin = data.find(x => x.date === dateStr && x.periode === 'matin' && x.tournee_id == t.id);
                html += createCellContent(t, attrMatin, dateStr, 'journée');
            } else if (t.duree === 'matin et après-midi') {
                // Matin et après-midi : DEUX cases séparées (2 tours)
                const attrMatin = data.find(x => x.date === dateStr && x.periode === 'matin' && x.tournee_id == t.id);
                html += createCellContent(t, attrMatin, dateStr, 'matin');
                html += '<div style="height: 3px;"></div>'; // Séparateur
                const attrAM = data.find(x => x.date === dateStr && x.periode === 'apres-midi' && x.tournee_id == t.id);
                html += createCellContent(t, attrAM, dateStr, 'apres-midi');
            } else if (t.duree === 'matin') {
                // Matin uniquement
                const attrMatin = data.find(x => x.date === dateStr && x.periode === 'matin' && x.tournee_id == t.id);
                html += createCellContent(t, attrMatin, dateStr, 'matin');
            } else if (t.duree === 'après-midi') {
                // Après-midi uniquement
                const attrAM = data.find(x => x.date === dateStr && x.periode === 'apres-midi' && x.tournee_id == t.id);
                html += createCellContent(t, attrAM, dateStr, 'apres-midi');
            } else {
                // CAS PAR DÉFAUT : si duree est null/vide ou inconnue, afficher matin + après-midi
                const attrMatin = data.find(x => x.date === dateStr && x.periode === 'matin' && x.tournee_id == t.id);
                html += createCellContent(t, attrMatin, dateStr, 'matin');
                html += '<div style="height: 3px;"></div>'; // Séparateur
                const attrAM = data.find(x => x.date === dateStr && x.periode === 'apres-midi' && x.tournee_id == t.id);
                html += createCellContent(t, attrAM, dateStr, 'apres-midi');
            }
            
            html += `</td>`;
        });
        html += `</tr>`;
    });

    html += `</tbody></table>`;
    document.getElementById('planning-grid').innerHTML = html;
}

function createCellContent(tournee, attr, date, periode) {
    let scoreDisplay = '';
    let cellClass = 'bg-light';
    
    // N'afficher le score que si un conducteur est attribué
    if (attr && attr.conducteur_id && attr.score_ia !== undefined && attr.score_ia !== null) {
        const score = Math.round(attr.score_ia);
        
        if (score >= 80) cellClass = 'bg-success bg-opacity-25';
        else if (score >= 60) cellClass = 'bg-info bg-opacity-25';
        else if (score >= 40) cellClass = 'bg-warning bg-opacity-25';
        else cellClass = 'bg-danger bg-opacity-25'; // Score < 40 ou = 0
        
        let badgeClass = 'bg-secondary';
        if (score >= 80) badgeClass = 'bg-success';
        else if (score >= 60) badgeClass = 'bg-info';
        else if (score >= 40) badgeClass = 'bg-warning';
        else badgeClass = 'bg-danger'; // Score < 40 ou = 0
        
        // Trouver le conducteur pour récupérer son statut
        const conducteur = AppState.conducteurs.find(c => c.id == attr.conducteur_id);
        let statutBadge = '';
        
        if (conducteur) {
            const statut = conducteur.statut_entreprise || 'CDI';
            let statutClass = 'bg-secondary';
            let statutLabel = statut;
            
            switch(statut.toLowerCase()) {
                case 'cdi':
                    statutClass = 'bg-primary';
                    statutLabel = 'CDI';
                    break;
                case 'cdd':
                    statutClass = 'bg-info';
                    statutLabel = 'CDD';
                    break;
                case 'interimaire':
                    statutClass = 'bg-warning text-dark';
                    statutLabel = 'INT';
                    break;
                case 'sous-traitant':
                    statutClass = 'bg-secondary';
                    statutLabel = 'ST';
                    break;
            }
            
            statutBadge = `<span class="badge ${statutClass} small me-1" style="font-size: 0.6rem;">${statutLabel}</span>`;
        }
        
        scoreDisplay = `<div class="mt-1 d-flex align-items-center justify-content-center gap-1">
            ${statutBadge}
            <span class="badge ${badgeClass} small">${score}/100</span>
        </div>`;
    } else {
        // Espace réservé pour maintenir la hauteur constante
        scoreDisplay = `<div class="mt-1" style="height: 20px;">
            <span class="badge bg-secondary small invisible">0/100</span>
        </div>`;
    }
    
    // Libellé de la période (AFFICHAGE avec accents)
    let periodeLabel = '';
    if (periode === 'journée') {
        periodeLabel = '📅 Journée';
    } else if (periode === 'matin') {
        periodeLabel = '🌅 Matin';
    } else if (periode === 'apres-midi') {
        periodeLabel = '🌆 Après-midi';
    } else {
        periodeLabel = '🌆 Après-midi'; // par défaut
    }
    
    // Période en base de données (SANS accent pour compatibilité ENUM)
    let periodeData = periode;
    if (periode === 'journée') {
        periodeData = 'matin'; // une journée s'enregistre comme 'matin' en base
    }
    
    return `<div class="p-1 rounded ${cellClass} planning-cell">
        <small class="text-muted d-block" style="font-size: 0.7rem; line-height: 1;">${periodeLabel}</small>
        <select class="form-select form-select-sm mb-1" onchange="sauvegarderAttribution(this)" 
                data-date="${date}" data-periode="${periodeData}" data-tournee="${tournee.id}" 
                data-old-value="${attr?.conducteur_id || ''}" 
                style="font-size: 0.75rem; padding: 0.2rem 0.4rem;">
            <option value="">-- Libre --</option>
            ${AppState.conducteurs.map(c => `<option value="${c.id}" ${c.id == attr?.conducteur_id ? 'selected' : ''}>
                ${c.prenom.charAt(0)}. ${c.nom}
            </option>`).join('')}
        </select>
        ${scoreDisplay}
    </div>`;
}

async function sauvegarderAttribution(select) {
    const date = select.dataset.date;
    const periode = select.dataset.periode;
    const tourneeId = select.dataset.tournee;
    const conducteurId = select.value ? +select.value : null;
    const oldValue = select.dataset.oldValue || ''; // Sauvegarder l'ancienne valeur
    
    // Sauvegarder la nouvelle valeur pour la prochaine fois
    select.dataset.oldValue = select.value;
    
    // Retirer d'anciennes surbrillances
    document.querySelectorAll('.planning-cell.conflict').forEach(el => {
        el.classList.remove('conflict');
    });
    
    // VÉRIFICATION 1 : Vérifier que le conducteur possède le permis requis (BLOQUANT)
    if (conducteurId) {
        const conducteur = conducteurs.find(c => c.id === conducteurId);
        const tournee = tournees.find(t => t.id == tourneeId);
        
        if (conducteur && tournee && tournee.permis_requis) {
            // Récupérer les permis requis pour la tournée
            let permisRequis = [];
            if (Array.isArray(tournee.permis_requis)) {
                permisRequis = tournee.permis_requis;
            } else if (typeof tournee.permis_requis === 'string') {
                try {
                    const parsed = JSON.parse(tournee.permis_requis);
                    permisRequis = Array.isArray(parsed) ? parsed : [parsed];
                } catch {
                    // Si ce n'est pas du JSON, c'est peut-être une simple string
                    permisRequis = [tournee.permis_requis];
                }
            }
            
            // Récupérer les permis du conducteur
            let permisConducteur = [];
            if (Array.isArray(conducteur.permis)) {
                permisConducteur = conducteur.permis;
            } else if (typeof conducteur.permis === 'string') {
                try {
                    const parsed = JSON.parse(conducteur.permis);
                    permisConducteur = Array.isArray(parsed) ? parsed : [parsed];
                } catch {
                    // Si ce n'est pas du JSON, split par virgules
                    permisConducteur = conducteur.permis.split(',').map(p => p.trim());
                }
            }
            
            // Vérifier si le conducteur a AU MOINS UN des permis requis
            const aPermisValide = permisRequis.some(permisReq => 
                permisConducteur.includes(permisReq)
            );
            
            if (!aPermisValide && permisRequis.length > 0) {
                showToast(
                    'Permis manquant', 
                    `❌ ${conducteur.prenom} ${conducteur.nom} ne possède pas le(s) permis requis pour "${tournee.nom}".\n\nPermis requis : ${permisRequis.join(', ')}\nPermis du conducteur : ${permisConducteur.join(', ')}`,
                    'danger'
                );
                select.value = ''; // Annuler la sélection
                return;
            }
        }
    }
    
    // VÉRIFICATION 2 : Avertir si on attribue un titulaire à une autre tournée (mais permettre)
    if (conducteurId) {
        const conducteur = conducteurs.find(c => c.id === conducteurId);
        if (conducteur && conducteur.tournee_titulaire && conducteur.tournee_titulaire != tourneeId) {
            const tourneeTit = tournees.find(t => t.id == conducteur.tournee_titulaire);
            const tourneeCible = tournees.find(t => t.id == tourneeId);
            if (!confirm(`⚠️ ATTENTION :\n\n${conducteur.prenom} ${conducteur.nom} est titulaire de "${tourneeTit?.nom}".\n\nVoulez-vous vraiment l'affecter à "${tourneeCible?.nom}" ?\n\n(Cette action est déconseillée mais possible en manuel)`)) {
                select.value = '';
                return;
            }
        }
    }
    
    // VÉRIFICATION 3 : Conflit - même conducteur sur la MÊME PÉRIODE du même jour
    if (conducteurId) {
        // Récupérer toutes les attributions du conducteur pour cette date
        const toutesAttributions = AppState.planningFullData.filter(attr => 
            attr.date === date && attr.conducteur_id == conducteurId
        );
        
        // Trouver la tournée actuelle
        const tourneeActuelle = tournees.find(t => t.id == tourneeId);
        
        // Vérifier s'il y a des conflits
        for (const attr of toutesAttributions) {
            // Ignorer si c'est la même tournée (modification en cours)
            if (attr.tournee_id == tourneeId && attr.periode === periode) continue;
            
            const tourneeDeja = tournees.find(t => t.id == attr.tournee_id);
            if (!tourneeDeja) continue;
            
            // CONFLIT 1 : Le conducteur est déjà sur une tournée "journée"
            if (tourneeDeja.duree === 'journée') {
                // Si on affecte sur n'importe quelle période, il faut libérer toute la journée
                const confirmMsg = `⚠️ CONFLIT DÉTECTÉ\n\n${conducteurs.find(c => c.id === conducteurId)?.prenom || 'Ce conducteur'} est déjà affecté à la tournée JOURNÉE "${tourneeDeja.nom}".\n\nVoulez-vous :\n• SUPPRIMER l'attribution de "${tourneeDeja.nom}" (journée complète)\n• ET affecter à "${tourneeActuelle?.nom || 'cette tournée'}" (${periode}) ?\n\n⚠️ Cela libérera le conducteur de toute la journée "${tourneeDeja.nom}".`;
                
                if (!confirm(confirmMsg)) {
                    select.value = '';
                    return;
                }
                
                // Supprimer toute la journée
                await apiCall('delete_attribution', 'POST', { id: attr.id });
                // Il faut aussi supprimer l'autre période de la journée
                const autrePeriode = attr.periode === 'matin' ? 'apres-midi' : 'matin';
                const autreAttr = toutesAttributions.find(a => 
                    a.conducteur_id === conducteurId && 
                    a.tournee_id === tourneeDeja.id && 
                    a.periode === autrePeriode
                );
                if (autreAttr) {
                    await apiCall('delete_attribution', 'POST', { id: autreAttr.id });
                }
                showToast('Modification', `Attribution à "${tourneeDeja.nom}" (journée) supprimée`, 'warning');
                // Continuer avec la nouvelle attribution
                break;
            }
            
            // CONFLIT 1-bis : Le conducteur est déjà sur une tournée "matin et après-midi"
            if (tourneeDeja.duree === 'matin et après-midi') {
                // Si on affecte sur la même période que celle déjà occupée
                if (attr.periode === periode) {
                    const confirmMsg = `⚠️ CONFLIT DÉTECTÉ\n\n${conducteurs.find(c => c.id === conducteurId)?.prenom || 'Ce conducteur'} est déjà affecté ${periode === 'matin' ? 'le matin' : 'l\'après-midi'} sur "${tourneeDeja.nom}".\n\nVoulez-vous :\n• SUPPRIMER l'attribution de "${tourneeDeja.nom}" (${periode})\n• ET affecter à "${tourneeActuelle?.nom || 'cette tournée'}" (${periode}) ?\n\n⚠️ Cela remplacera uniquement la période ${periode}.`;
                    
                    if (!confirm(confirmMsg)) {
                        select.value = '';
                        return;
                    }
                    
                    // Supprimer UNIQUEMENT cette période (pas toute la journée)
                    await apiCall('delete_attribution', 'POST', { id: attr.id });
                    showToast('Modification', `Attribution à "${tourneeDeja.nom}" (${periode}) supprimée`, 'warning');
                    // Continuer avec la nouvelle attribution
                    break;
                }
                
                // Si on affecte sur l'autre période (ex: déjà matin, on veut après-midi)
                // Pas de conflit, on peut ajouter normalement
                continue;
            }
            
            // CONFLIT 2 : On veut affecter à une tournée "journée" (mais PAS "matin et après-midi")
            // Une tournée "journée" occupe vraiment toute la journée avec le même conducteur
            // Une tournée "matin et après-midi" ce sont 2 tournées séparées
            if (tourneeActuelle && tourneeActuelle.duree === 'journée') {
                const confirmMsg = `⚠️ CONFLIT DÉTECTÉ\n\n${conducteurs.find(c => c.id === conducteurId)?.prenom || 'Ce conducteur'} est déjà affecté à "${tourneeDeja.nom}" (${attr.periode}) le ${new Date(date).toLocaleDateString('fr-FR')}.\n\nVous voulez l'affecter à une tournée JOURNÉE "${tourneeActuelle.nom}" qui occupe TOUTE la journée.\n\nVoulez-vous :\n• SUPPRIMER l'attribution de "${tourneeDeja.nom}" (${attr.periode})\n• ET affecter à "${tourneeActuelle.nom}" (journée complète) ?\n\n⚠️ Cela libérera le conducteur de "${tourneeDeja.nom}".`;
                
                if (!confirm(confirmMsg)) {
                    select.value = '';
                    return;
                }
                
                // Supprimer toutes les attributions du jour pour ce conducteur
                for (const attrASupprimer of toutesAttributions) {
                    await apiCall('delete_attribution', 'POST', { id: attrASupprimer.id });
                }
                showToast('Modification', `Attributions du jour supprimées`, 'warning');
                // Continuer avec la nouvelle attribution
                break;
            }
            
            // CONFLIT 3 : Même période, tournées différentes (proposer de remplacer)
            if (attr.periode === periode) {
                const confirmMsg = `⚠️ CONFLIT DÉTECTÉ\n\n${conducteurs.find(c => c.id === conducteurId)?.prenom || 'Ce conducteur'} est déjà affecté ${periode === 'matin' ? 'le matin' : 'l\'après-midi'} sur "${tourneeDeja.nom}".\n\nVoulez-vous :\n• SUPPRIMER l'attribution de "${tourneeDeja.nom}" (${periode})\n• ET affecter à "${tourneeActuelle?.nom || 'cette tournée'}" (${periode}) ?\n\n⚠️ Cela remplacera l'attribution actuelle.`;
                
                if (!confirm(confirmMsg)) {
                    select.value = '';
                    return;
                }
                
                // Supprimer l'ancienne attribution
                await apiCall('delete_attribution', 'POST', { id: attr.id });
                showToast('Modification', `Attribution à "${tourneeDeja.nom}" (${periode}) supprimée`, 'warning');
                // Continuer avec la nouvelle attribution
                break;
            }
        }
    }
    
    select.disabled = true;
    const parent = select.parentElement;
    parent.classList.add('loading');
    
    try {
        let scoreIA = 0;
        if (conducteurId) {
            const sc = await apiCall(
                `calculer_score&conducteur_id=${conducteurId}&tournee_id=${tourneeId}&date=${date}&periode=${periode}`
            );
            scoreIA = sc.data.score || 0;
            
            // Trouver le nom du conducteur pour le message
            const conducteur = conducteurs.find(c => c.id === conducteurId);
            const nomConducteur = conducteur ? `${conducteur.prenom} ${conducteur.nom}` : 'Conducteur';
            
            showToast('Succès', `${nomConducteur} affecté\n📊 Score IA : ${Math.round(scoreIA)}/100`, 'success');
        } else {
            showToast('Succès', 'Attribution supprimée', 'success');
        }
        
        await apiCall('add_attribution', 'POST', {
            date, periode, tournee_id: +tourneeId, conducteur_id: conducteurId, score_ia: scoreIA
        });
        
        // Recharger seulement les données sans réinitialiser la navigation
        const debut = AppState.selectedPeriod.debut;
        const fin = AppState.selectedPeriod.fin;
        const { data } = await apiCall(`get_planning&debut=${debut}&fin=${fin}`);
        AppState.planningFullData = data || [];
        renderPlanningWithNavigation();
        
    } catch (error) {
        console.error('Erreur sauvegarde attribution:', error);
        const errorMsg = error.message || 'Impossible de sauvegarder l\'attribution.';
        showToast('Erreur', errorMsg, 'danger');
        // Restaurer la sélection précédente
        select.value = oldValue;
        select.dataset.oldValue = oldValue;
    } finally {
        select.disabled = false;
        parent.classList.remove('loading');
    }
}


// Fonction pour effacer le planning de la période affichée uniquement
async function effacerPlanningPeriode() {
    const debut = document.getElementById('planning-date-debut').value;
    const fin = document.getElementById('planning-date-fin').value;
    
    if (!debut || !fin) {
        showToast('Attention', 'Sélectionnez une période valide', 'warning');
        return;
    }

    if (!confirm(`Êtes-vous sûr de vouloir effacer le planning du ${debut} au ${fin} ?\n\nToutes les attributions seront supprimées.`)) {
        return;
    }

    try {
        const response = await apiCall('effacer_planning_periode', 'POST', { 
            debut: debut,
            fin: fin
        });
        
        if (response.success) {
            showToast('Succès', `Planning effacé (${response.nb_supprimees || 0} attributions supprimées)`, 'success');
            await chargerPlanning(); // Recharge le planning
        }
    } catch (error) {
        console.error('Erreur effacement planning période:', error);
        showToast('Erreur', 'Impossible d\'effacer le planning', 'danger');
    }
}

// Fonction pour effacer TOUT le planning (toutes les semaines)
async function effacerPlanningComplet() {
    if (!confirm('⚠️ ATTENTION ⚠️\n\nVous allez supprimer TOUTES les attributions sur TOUTES les semaines !\n\nCette action est irréversible.\n\nContinuer ?')) {
        return;
    }

    // Double confirmation
    if (!confirm('Êtes-vous VRAIMENT sûr ? Toutes les données seront perdues !')) {
        return;
    }

    try {
        const response = await apiCall('effacer_planning_complet', 'POST', {});
        
        if (response.success) {
            showToast('Succès', `Planning complet effacé (${response.nb_supprimees || 0} attributions supprimées)`, 'success');
            await chargerPlanning(); // Recharge le planning
            await chargerStats(); // Met à jour les stats
        }
    } catch (error) {
        console.error('Erreur effacement planning complet:', error);
        // Message spécifique si c'est une erreur de permission
        const errorMsg = error.message || 'Impossible d\'effacer le planning complet';
        showToast('Erreur', errorMsg, 'danger');
    }
}

async function remplirPlanningAuto() {
    const debut = document.getElementById('planning-date-debut').value;
    const fin = document.getElementById('planning-date-fin').value;
    
    if (!debut || !fin) {
        showToast('Attention', 'Sélectionnez une période valide', 'warning');
        return;
    }

    // Proposer d'effacer le planning existant d'abord
    const effacer = confirm(`Générer automatiquement le planning d'IA du ${debut} au ${fin} ?\n\n⚠️ IMPORTANT :\n\n🔴 Voulez-vous EFFACER les attributions existantes AVANT de régénérer ?\n\n- Cliquez OK pour EFFACER puis régénérer (recommandé pour tenir compte des changements)\n- Cliquez ANNULER pour juste compléter les trous sans toucher aux attributions existantes`);
    
    if (effacer === null) {
        return; // L'utilisateur a fermé la boîte de dialogue
    }

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Génération...';

    try {
        // Si demandé, effacer les attributions de la période d'abord
        if (effacer) {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Effacement...';
            const effacementResponse = await apiCall('effacer_planning_periode', 'POST', { debut, fin });
            if (!effacementResponse.success) {
                showToast('Erreur', 'Impossible d\'effacer le planning', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-robot me-1"></i>Remplir automatiquement (IA)';
                return;
            }
            showToast('Info', `${effacementResponse.nb_supprimees || 0} attributions effacées`, 'info');
        }
        
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Génération IA...';
        
        const response = await fetch('api.php?action=remplir_auto', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json;charset=utf-8' },
            body: JSON.stringify({ debut: debut, fin: fin })
        });
        const result = await response.json();

        if (response.ok && result.success) {
            const { succes, echecs } = result.data;
            showToast('IA Générée', `${succes} attributions créées, ${echecs} échecs`, 'success');
            await chargerPlanning();
        } else {
            throw new Error(result.error || 'Erreur IA');
        }
    } catch (error) {
        console.error('Erreur remplir_auto:', error);
        showToast('Erreur', 'La génération IA a échoué', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-robot me-1"></i>IA Auto';
    }
}

// Actualiser le planning en recalculant les scores pour les attributions existantes
async function actualiserPlanning() {
    const debut = document.getElementById('planning-date-debut').value;
    const fin = document.getElementById('planning-date-fin').value;
    
    if (!debut || !fin) {
        showToast('Attention', 'Sélectionnez une période valide', 'warning');
        return;
    }

    if (!confirm(`Actualiser le planning du ${debut} au ${fin} ?\n\nCette opération va :\n- ✖️ Supprimer les attributions invalides (conducteurs indisponibles, permis manquants)\n- 🔄 Recalculer tous les scores existants\n- 🎯 Réoptimiser les attributions selon les tournées maîtrisées\n- ⭐ Réattribuer les titulaires en priorité sur leur tournée\n- ✅ Compléter les créneaux vides avec les meilleurs remplaçants\n- 🔃 Recharger les données\n\n⚠️ ATTENTION : Cette opération peut modifier les attributions manuelles !`)) {
        return;
    }

    const btn = event.target;
    const btnOriginalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Actualisation...';

    try {
        // Récupérer le planning actuel
        const { data: planningActuel } = await apiCall(`get_planning&debut=${debut}&fin=${fin}`);
        
        let suppressions = 0;
        
        // Phase 1 : Supprimer les attributions invalides
        for (const attribution of planningActuel) {
            if (!attribution.conducteur_id) continue;
            
            let doitSupprimer = false;
            let raison = '';
            
            // Trouver le conducteur
            const conducteur = conducteurs.find(c => c.id == attribution.conducteur_id);
            if (!conducteur) {
                doitSupprimer = true;
                raison = 'Conducteur introuvable';
            } else {
                // Vérifier si c'est un titulaire sur une mauvaise tournée
                if (conducteur.tournee_titulaire && conducteur.tournee_titulaire != attribution.tournee_id) {
                    doitSupprimer = true;
                    raison = `Titulaire de T${conducteur.tournee_titulaire}, pas de T${attribution.tournee_id}`;
                    console.log(`Conducteur ${conducteur.prenom} ${conducteur.nom} ${raison}`);
                }
                
                // Vérifier la disponibilité du conducteur (seulement si pas déjà marqué à supprimer)
                if (!doitSupprimer) {
                    const scoreResult = await apiCall(
                        `calculer_score&conducteur_id=${attribution.conducteur_id}&tournee_id=${attribution.tournee_id}&date=${attribution.date}&periode=${attribution.periode}`
                    );
                    
                    if (!scoreResult.data.disponible) {
                        doitSupprimer = true;
                        raison = scoreResult.data.details || 'Non disponible';
                    }
                }
            }
            
            if (doitSupprimer) {
                console.log(`Suppression attribution: ${conducteur?.prenom} ${conducteur?.nom} sur T${attribution.tournee_id} - ${raison}`);
                await apiCall('delete_attribution', 'POST', { id: attribution.id });
                suppressions++;
            }
        }
        
        // Phase 1.5 : Recalculer les scores des attributions restantes
        const { data: planningRestant } = await apiCall(`get_planning&debut=${debut}&fin=${fin}`);
        let recalculs = 0;
        
        for (const attribution of planningRestant) {
            if (!attribution.conducteur_id) continue;
            
            // Recalculer le score
            const scoreResult = await apiCall(
                `calculer_score&conducteur_id=${attribution.conducteur_id}&tournee_id=${attribution.tournee_id}&date=${attribution.date}&periode=${attribution.periode}`
            );
            
            const nouveauScore = scoreResult.data?.score || 0;
            
            // Mettre à jour si le score a changé
            if (Math.abs(nouveauScore - (attribution.score_ia || 0)) > 0.1) {
                await apiCall('update_attribution', 'POST', { 
                    id: attribution.id,
                    score_ia: nouveauScore
                });
                recalculs++;
            }
        }
        
        // Phase 2 : Remplir les créneaux vides avec la nouvelle logique (titulaires d'abord)
        const remplissageResult = await fetch('api.php?action=remplir_auto', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json;charset=utf-8' },
            body: JSON.stringify({ debut, fin })
        });
        const remplissage = await remplissageResult.json();
        const ajouts = remplissage.data?.succes || 0;
        
        let message = `Actualisation terminée :\n`;
        if (suppressions > 0) message += `✖️ ${suppressions} conducteur(s) retiré(s)\n`;
        if (recalculs > 0) message += `🔄 ${recalculs} score(s) recalculé(s)\n`;
        if (ajouts > 0) message += `✅ ${ajouts} créneau(x) rempli(s)\n`;
        message += `ℹ️ Les titulaires sont prioritaires sur leur tournée`;
        
        showToast('Actualisation terminée', message, 'success');
        
        // Recharger les conducteurs et tournées pour mettre à jour AppState
        await chargerConducteurs();
        await chargerTournees();
        await chargerPlanning();
        
    } catch (error) {
        console.error('Erreur actualisation planning:', error);
        showToast('Erreur', 'Impossible d\'actualiser le planning', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = btnOriginalContent;
    }
}

// ==================== CONFIGURATION ====================
async function chargerConfig() {
    try {
        const { data } = await apiCall('get_config');
        AppState.config = data || {};
        config = AppState.config; // Mise à jour de la variable globale
        renderConfig();
        chargerCriteresIA();
    } catch (error) {
        console.error('Erreur config:', error);
    }
}

function renderConfig() {
    const lp = document.getElementById('liste-permis');
    const lv = document.getElementById('liste-vehicules');
    const lt = document.getElementById('liste-types-tournee');
    if (!lp || !lv) return;
    
    lp.innerHTML = '';
    lv.innerHTML = '';
    if (lt) lt.innerHTML = '';
    
    (config.types_permis || []).forEach(p => {
        lp.innerHTML += `<div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <span>${p}</span>
            <button class="btn btn-sm btn-outline-danger" onclick="supprimerPermis('${p}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
    });
    
    (config.types_vehicules || []).forEach(v => {
        lv.innerHTML += `<div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <span>${v}</span>
            <button class="btn btn-sm btn-outline-danger" onclick="supprimerVehicule('${v}')">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
    });
    
    if (lt) {
        const typesTries = (config.types_tournee || []).sort((a, b) => a.ordre - b.ordre);
        typesTries.forEach(t => {
            // Affichage du logo : emoji OU image
            let logoDisplay = '';
            if (t.logo) {
                if (t.logo.startsWith('uploads/')) {
                    // C'est une image uploadée
                    logoDisplay = `<img src="${t.logo}" alt="${t.nom}" style="width: 24px; height: 24px; object-fit: contain;" class="me-2">`;
                } else {
                    // C'est un emoji
                    logoDisplay = `<span style="font-size: 1.2rem;" class="me-2">${t.logo}</span>`;
                }
            }
            
            lt.innerHTML += `<div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div class="flex-grow-1">
                    <span class="badge bg-secondary me-2">${t.ordre}</span>
                    ${logoDisplay}
                    <span>${t.nom}</span>
                </div>
                <div class="btn-group btn-group-sm">
                    <input type="text" class="form-control form-control-sm" style="width:50px" 
                           value="${t.logo && !t.logo.startsWith('uploads/') ? t.logo : ''}" placeholder="📦" 
                           onchange="modifierLogoTypeTournee('${t.nom}', this.value)"
                           title="Emoji/Icône (ou vide si image)">
                    <button class="btn btn-outline-primary" onclick="afficherModalUploadLogoTournee('${t.nom}')" title="Upload image">
                        <i class="bi bi-image"></i>
                    </button>
                    <input type="number" class="form-control form-control-sm" style="width:60px" 
                           value="${t.ordre}" min="1" 
                           onchange="modifierOrdreTypeTournee('${t.nom}', this.value)"
                           title="Ordre d'affichage">
                    <button class="btn btn-outline-danger" onclick="supprimerTypeTournee('${t.nom}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>`;
        });
    }
    
    // Peupler le select d'export RGPD
    const selectRGPD = document.getElementById('conducteur-export-rgpd');
    if (selectRGPD) {
        selectRGPD.innerHTML = '<option value="">-- Choisir un conducteur --</option>';
        conducteurs.forEach(c => {
            selectRGPD.innerHTML += `<option value="${c.id}">${c.prenom} ${c.nom}</option>`;
        });
    }
}

function chargerCriteresIA() {
    const poidsConnaissance = config.poids_connaissance || 80;
    const poidsExperience = config.poids_experience || 2.5; // 100/100 à 40 ans
    const penaliteInterimaire = config.penalite_interimaire || -50;
    
    document.getElementById('poids-connaissance').value = poidsConnaissance;
    document.getElementById('label-poids-connaissance').textContent = poidsConnaissance;
    
    document.getElementById('poids-experience').value = poidsExperience;
    document.getElementById('label-poids-experience').textContent = poidsExperience;
    
    document.getElementById('penalite-interimaire').value = penaliteInterimaire;
    document.getElementById('label-penalite-interimaire').textContent = penaliteInterimaire;
}

async function sauvegarderCriteresIA() {
    const criteres = {
        poids_connaissance: +document.getElementById('poids-connaissance').value,
        poids_experience: +document.getElementById('poids-experience').value,
        penalite_interimaire: +document.getElementById('penalite-interimaire').value
    };
    
    try {
        await apiCall('set_config', 'POST', criteres);
        showToast('Succès', 'Critères IA sauvegardés', 'success');
        await chargerConfig();
    } catch (error) {
        console.error('Erreur sauvegarde critères IA:', error);
        showToast('Erreur', 'Impossible de sauvegarder les critères', 'danger');
    }
}

async function ajouterPermis() {
    const input = document.getElementById('nouveau-permis');
    const val = input.value.trim();
    if (!val) return;
    
    config.types_permis = config.types_permis || [];
    if (config.types_permis.includes(val)) {
        showToast('Attention', 'Ce permis existe déjà', 'warning');
        return;
    }
    
    config.types_permis.push(val);
    await apiCall('set_config', 'POST', { types_permis: config.types_permis });
    showToast('Succès', 'Permis ajouté', 'success');
    input.value = '';
    chargerConfig();
}

async function supprimerPermis(val) {
    config.types_permis = config.types_permis.filter(x => x !== val);
    await apiCall('set_config', 'POST', { types_permis: config.types_permis });
    showToast('Succès', 'Permis supprimé', 'warning');
    chargerConfig();
}

async function ajouterVehicule() {
    const input = document.getElementById('nouveau-vehicule');
    const val = input.value.trim();
    if (!val) return;
    
    config.types_vehicules = config.types_vehicules || [];
    if (config.types_vehicules.includes(val)) {
        showToast('Attention', 'Ce véhicule existe déjà', 'warning');
        return;
    }
    
    config.types_vehicules.push(val);
    await apiCall('set_config', 'POST', { types_vehicules: config.types_vehicules });
    showToast('Succès', 'Véhicule ajouté', 'success');
    input.value = '';
    chargerConfig();
}

async function supprimerVehicule(val) {
    config.types_vehicules = config.types_vehicules.filter(x => x !== val);
    await apiCall('set_config', 'POST', { types_vehicules: config.types_vehicules });
    showToast('Succès', 'Véhicule supprimé', 'warning');
    chargerConfig();
}

async function ajouterTypeTournee() {
    const inputNom = document.getElementById('nouveau-type-tournee');
    const inputLogo = document.getElementById('logo-type-tournee');
    const inputOrdre = document.getElementById('ordre-type-tournee');
    const nom = inputNom.value.trim();
    const logo = inputLogo.value.trim();
    const ordre = parseInt(inputOrdre.value) || 999;
    
    if (!nom) return;
    
    config.types_tournee = config.types_tournee || [];
    if (config.types_tournee.some(t => t.nom === nom)) {
        showToast('Attention', 'Ce type de tournée existe déjà', 'warning');
        return;
    }
    
    config.types_tournee.push({ nom, logo, ordre });
    // Trier par ordre
    config.types_tournee.sort((a, b) => a.ordre - b.ordre);
    
    await apiCall('set_config', 'POST', { types_tournee: config.types_tournee });
    showToast('Succès', 'Type de tournée ajouté', 'success');
    inputNom.value = '';
    inputLogo.value = '';
    inputOrdre.value = '';
    chargerConfig();
}

async function supprimerTypeTournee(nom) {
    config.types_tournee = config.types_tournee.filter(x => x.nom !== nom);
    await apiCall('set_config', 'POST', { types_tournee: config.types_tournee });
    showToast('Succès', 'Type de tournée supprimé', 'warning');
    chargerConfig();
}

async function modifierOrdreTypeTournee(nom, nouvelOrdre) {
    const type = config.types_tournee.find(t => t.nom === nom);
    if (type) {
        type.ordre = parseInt(nouvelOrdre);
        config.types_tournee.sort((a, b) => a.ordre - b.ordre);
        await apiCall('set_config', 'POST', { types_tournee: config.types_tournee });
        showToast('Succès', 'Ordre modifié', 'success');
        chargerConfig();
    }
}

async function modifierLogoTypeTournee(nom, nouveauLogo) {
    const type = config.types_tournee.find(t => t.nom === nom);
    if (type) {
        type.logo = nouveauLogo.trim();
        await apiCall('set_config', 'POST', { types_tournee: config.types_tournee });
        showToast('Succès', 'Logo modifié', 'success');
        chargerConfig();
        // Recharger le planning pour afficher le nouveau logo
        if (AppState.selectedPeriod.debut && AppState.selectedPeriod.fin) {
            renderPlanningWithNavigation();
        }
    }
}

function afficherModalUploadLogoTournee(typeNom) {
    // Créer une modal dynamique pour l'upload
    const modalHtml = `
        <div class="modal fade" id="modalUploadLogoTournee" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload logo pour "${typeNom}"</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Fichier image (JPG, PNG, GIF, BMP - max 1MB)</label>
                            <input type="file" class="form-control" id="logo-tournee-file" accept="image/jpeg,image/png,image/gif,image/bmp">
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> L'image sera redimensionnée automatiquement pour s'adapter au planning.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary" onclick="uploadLogoTournee('${typeNom}')">
                            <i class="bi bi-upload me-1"></i>Uploader
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer l'ancienne modal si elle existe
    const oldModal = document.getElementById('modalUploadLogoTournee');
    if (oldModal) oldModal.remove();
    
    // Ajouter la nouvelle modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Afficher la modal
    const modal = new bootstrap.Modal(document.getElementById('modalUploadLogoTournee'));
    modal.show();
}

async function uploadLogoTournee(typeNom) {
    const fileInput = document.getElementById('logo-tournee-file');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Erreur', 'Veuillez sélectionner un fichier', 'warning');
        return;
    }

    // Vérification de la taille
    if (file.size > 1 * 1024 * 1024) {
        showToast('Erreur', 'Le fichier est trop volumineux (max 1MB)', 'danger');
        return;
    }

    // Vérification du type
    if (!['image/jpeg', 'image/png', 'image/gif', 'image/bmp'].includes(file.type)) {
        showToast('Erreur', 'Format de fichier non supporté (JPG, PNG, GIF, BMP uniquement)', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('logo', file);
    formData.append('type_nom', typeNom);

    try {
        const response = await fetch('upload_logo_tournee.php', {
            method: 'POST',
            body: formData
        });

        const respText = await response.text();
        let result;
        try {
            result = JSON.parse(respText);
        } catch (e) {
            throw new Error('Réponse serveur invalide: ' + respText);
        }
        
        if (result && result.success) {
            showToast('Succès', 'Logo uploadé avec succès', 'success');
            
            // Fermer la modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalUploadLogoTournee'));
            if (modal) modal.hide();
            
            // Recharger la config pour afficher le nouveau logo
            await chargerConfig();
            
            // Recharger le planning si affiché
            if (AppState.selectedPeriod.debut && AppState.selectedPeriod.fin) {
                renderPlanningWithNavigation();
            }
        } else {
            throw new Error(result ? (result.error || 'Erreur serveur') : 'Réponse serveur vide');
        }
    } catch (error) {
        console.error('Erreur upload logo tournée:', error);
        showToast('Erreur', error.message || 'Erreur lors de l\'upload', 'danger');
    }
}

// Gestion du logo
async function uploadLogo() {
    const fileInput = document.getElementById('logo-file');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Erreur', 'Veuillez sélectionner un fichier', 'warning');
        return;
    }

    // Vérification de la taille
    if (file.size > 2 * 1024 * 1024) {
        showToast('Erreur', 'Le fichier est trop volumineux (max 2MB)', 'danger');
        return;
    }

    // Vérification du type
    if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
        showToast('Erreur', 'Format de fichier non supporté', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('logo', file);

    try {
        const response = await fetch('upload_logo.php', {
            method: 'POST',
            body: formData
        });

        // Lire d'abord le texte (évite d'appeler json() puis text() qui consommerait le body deux fois)
        const respText = await response.text();
        let result;
        try {
            result = JSON.parse(respText);
        } catch (e) {
            throw new Error('Réponse serveur invalide: ' + respText);
        }
        
        if (result && result.success) {
            showToast('Succès', 'Logo mis à jour', 'success');
            // Mettre à jour l'aperçu
            const preview = document.getElementById('logo-preview');
            preview.innerHTML = `
                <img src="${result.path}" alt="Logo" class="img-fluid mb-2" style="max-height: 100px">
                <button class="btn btn-sm btn-danger d-block w-100" onclick="supprimerLogo()">
                    <i class="bi bi-trash me-1"></i>Supprimer le logo
                </button>
            `;
            // Mettre à jour le logo dans la navbar
            const navbarLogo = document.createElement('img');
            navbarLogo.src = result.path;
            navbarLogo.alt = 'Logo';
            navbarLogo.className = 'navbar-logo me-3';
            navbarLogo.style = 'max-height: 40px; width: auto;';
            
            const existingLogo = document.querySelector('.navbar-logo');
            if (existingLogo) {
                existingLogo.replaceWith(navbarLogo);
            } else {
                document.querySelector('.navbar .d-flex').prepend(navbarLogo);
            }
            
            // Réinitialiser l'input file
            fileInput.value = '';
        } else {
            throw new Error(result ? (result.error || 'Erreur serveur') : 'Réponse serveur vide');
        }
    } catch (error) {
        console.error('Erreur upload:', error);
        showToast('Erreur', error.message || 'Erreur lors de l\'upload', 'danger');
    }
}

async function supprimerLogo() {
    if (!confirm('Voulez-vous vraiment supprimer le logo ?')) {
        return;
    }

    try {
        await apiCall('set_config', 'POST', { logo_path: null });
        
        // Mettre à jour l'aperçu
        document.getElementById('logo-preview').innerHTML = `
            <div class="text-muted">
                <i class="bi bi-image" style="font-size: 3rem;"></i>
                <p>Aucun logo</p>
            </div>
        `;
        
        // Supprimer le logo de la navbar
        const navbarLogo = document.querySelector('.navbar-logo');
        if (navbarLogo) {
            navbarLogo.remove();
        }
        
        showToast('Succès', 'Logo supprimé', 'success');
    } catch (error) {
        console.error('Erreur suppression logo:', error);
        showToast('Erreur', 'Impossible de supprimer le logo', 'danger');
    }
}

// ==================== PROFIL UTILISATEUR ====================
// Ouvrir la modal de notice
function ouvrirNotice() {
    const modal = new bootstrap.Modal(document.getElementById('modalNotice'));
    modal.show();
}

function ouvrirModalRGPD() {
    const modal = new bootstrap.Modal(document.getElementById('modalRGPD'));
    modal.show();
}

async function ouvrirMonProfil() {
    try {
        // Récupérer l'ID de l'utilisateur connecté
        const { data } = await apiCall('get_current_user');
        
        if (data && data.id) {
            // Activer l'onglet Utilisateurs
            const utilisateursTab = document.getElementById('utilisateurs-tab');
            if (utilisateursTab) {
                // Cliquer sur l'onglet pour l'activer
                utilisateursTab.click();
                
                // Attendre que l'onglet soit chargé puis ouvrir le modal
                setTimeout(() => {
                    afficherModalUtilisateur(data.id);
                }, 100);
            } else {
                // Si l'utilisateur n'est pas admin, afficher un message
                showToast('Information', 'Seuls les administrateurs peuvent modifier leur profil', 'info');
            }
        }
    } catch (error) {
        console.error('Erreur ouverture profil:', error);
        showToast('Erreur', 'Impossible d\'ouvrir le profil', 'danger');
    }
}

// ==================== EXPORT RGPD ====================

async function exporterDonneesRGPD() {
    const conducteurId = document.getElementById('conducteur-export-rgpd').value;
    const format = document.querySelector('input[name="format-export"]:checked').value;
    
    if (!conducteurId) {
        showToast('Attention', 'Veuillez sélectionner un conducteur', 'warning');
        return;
    }
    
    try {
        if (format === 'pdf') {
            // Export PDF - Ouvrir dans nouvelle fenêtre
            const url = `export_rgpd_pdf.php?conducteur_id=${conducteurId}`;
            window.open(url, '_blank');
            
            const conducteur = conducteurs.find(c => c.id == conducteurId);
            if (conducteur) {
                showToast('Succès', `Export PDF de ${conducteur.prenom} ${conducteur.nom} en cours...`, 'success');
            }
        } else {
            // Export JSON - Téléchargement
            const response = await apiCall(`export_rgpd&conducteur_id=${conducteurId}`);
            
            if (response.success && response.data) {
                const data = response.data;
                
                // Créer un fichier JSON téléchargeable
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `donnees_rgpd_${data.conducteur.nom}_${data.conducteur.prenom}_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                showToast('Succès', `Données de ${data.conducteur.prenom} ${data.conducteur.nom} exportées avec succès`, 'success');
            }
        }
    } catch (error) {
        console.error('Erreur export RGPD:', error);
        showToast('Erreur', 'Impossible d\'exporter les données', 'danger');
    }
}
