/**
 * script.js - Gère l'interactivité et les appels API de l'application
 */

// Variables globales
let conducteurs = [], tournees = [], config = {};

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    initDates();
    initApp();
    setupTabListeners();
});

// Initialise les dates par défaut (semaine en cours)
function initDates() {
    const debut = document.getElementById('planning-date-debut');
    const fin = document.getElementById('planning-date-fin');
    if (debut && fin) {
        debut.value = getMonday();
        fin.value = getSunday();
    }
}

// Initialise l'application
async function initApp() {
    try {
        await Promise.all([
            chargerStats(),
            chargerConducteurs(),
            chargerTournees(),
            chargerConfig()
        ]);
    } catch (e) {
        console.error('Init error', e);
        showToast('Erreur', 'Impossible de charger les données', 'danger');
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
                case '#planning': chargerPlanning(); break;
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
    m.setDate(m.getDate() + 6);
    return m.toISOString().split('T')[0];
}

// ==================== STATISTIQUES ====================
async function chargerStats() {
    try {
        const { data } = await apiCall('get_stats');
        document.getElementById('stat-conducteurs').textContent = data.conducteurs || 0;
        document.getElementById('stat-tournees').textContent = data.tournees || 0;
        document.getElementById('stat-semaine').textContent = data.attributions_semaine || 0;
        
        // Score de performance global du planning
        const scoreRes = await apiCall(`get_score_global&debut=${getMonday()}&fin=${getSunday()}`);
        if (scoreRes.success && scoreRes.data) {
            const score = scoreRes.data.score_global;
            document.getElementById('stat-score-ia').textContent = `${score}%`;
            
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

// ==================== CONDUCTEURS ====================
async function chargerConducteurs() {
    try {
        const { data } = await apiCall('get_conducteurs');
        conducteurs = data || [];
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
                else if (scorePerformance > 0) badgeClass = 'bg-danger';
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
            <div class="col-md-6 mb-3">
                <label class="form-label">Permis *</label>
                <select id="c-permis" class="form-select" required>
                    <option value="">Sélectionner...</option>
                    ${(config.types_permis || ['B','C','C+E','D','EC']).map(p => 
                        `<option value="${p}" ${c.permis===p?'selected':''}>${p}</option>`
                    ).join('')}
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Contact</label>
                <input id="c-contact" class="form-control" value="${c.contact || ''}" type="email">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Expérience (années)</label>
                <input type="number" id="c-exp" class="form-control" value="${c.experience || 0}" min="0">
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
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-star me-2"></i>Tournée titulaire</label>
            <select id="c-titulaire" class="form-select">
                <option value="">Aucune</option>
                ${tournees.map(t => 
                    `<option value="${t.id}" ${c.tournee_titulaire==t.id?'selected':''}>${t.nom}</option>`
                ).join('')}
            </select>
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
            <select id="c-statut-temp" class="form-select">
                <option value="disponible" ${c.statut_temporaire==='disponible'||!c.statut_temporaire?'selected':''}>Disponible</option>
                <option value="conge" ${c.statut_temporaire==='conge'?'selected':''}>En congé</option>
                <option value="malade" ${c.statut_temporaire==='malade'?'selected':''}>Malade</option>
                <option value="formation" ${c.statut_temporaire==='formation'?'selected':''}>En formation</option>
                <option value="repos" ${c.statut_temporaire==='repos'?'selected':''}>Repos</option>
            </select>
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
    
    const data = {
        prenom: document.getElementById('c-prenom').value,
        nom: document.getElementById('c-nom').value,
        permis: document.getElementById('c-permis').value,
        contact: document.getElementById('c-contact').value,
        experience: +document.getElementById('c-exp').value,
        statut_entreprise: document.getElementById('c-statut').value,
        tournees_maitrisees: tourneesMaitrisees,
        tournee_titulaire: document.getElementById('c-titulaire').value || null,
        repos_recurrents: { jours: reposJours, type: typeRepos },
        conges: conges,
        statut_temporaire: document.getElementById('c-statut-temp').value
    };
    
    try {
        if (id) {
            await apiCall('update_conducteur', 'POST', { id: +id, ...data });
        } else {
            await apiCall('add_conducteur', 'POST', data);
        }
        showToast('Succès', 'Conducteur enregistré', 'success');
        chargerConducteurs();
        bootstrap.Modal.getInstance(document.getElementById('modalConducteur')).hide();
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
        tournees = data || [];
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
    
    form.innerHTML = `
        <input type="hidden" id="t-id" value="${t.id || ''}">
        <div class="mb-3">
            <label class="form-label">Nom *</label>
            <input id="t-nom" class="form-control" value="${t.nom || ''}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Zone géographique</label>
            <input id="t-zone" class="form-control" value="${t.zone_geo || ''}">
        </div>
        <div class="mb-3">
            <label class="form-label">Type de véhicule</label>
            <input id="t-veh" class="form-control" value="${t.type_vehicule || ''}">
        </div>
        <div class="mb-3">
            <label class="form-label">Difficulté (1-5)</label>
            <input type="number" id="t-diff" class="form-control" value="${t.difficulte || 1}" min="1" max="5">
        </div>
        <div class="mb-3">
            <label class="form-label">Durée</label>
            <select id="t-duree" class="form-select">
                <option value="matin" ${t.duree==='matin'?'selected':''}>Matin</option>
                <option value="apres-midi" ${t.duree==='apres-midi'?'selected':''}>Après-midi</option>
                <option value="journee" ${t.duree==='journee'?'selected':''}>Journée</option>
            </select>
        </div>
    `;
    modal.show();
}

async function sauvegarderTournee() {
    const id = document.getElementById('t-id').value;
    const data = {
        nom: document.getElementById('t-nom').value,
        zone_geo: document.getElementById('t-zone').value,
        type_vehicule: document.getElementById('t-veh').value,
        difficulte: +document.getElementById('t-diff').value,
        duree: document.getElementById('t-duree').value
    };
    
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
        console.error('Erreur sauvegarde:', error);
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
    
    try {
        const { data } = await apiCall(`get_planning&debut=${debut}&fin=${fin}`);
        renderPlanning(data || [], debut, fin);
    } catch (error) {
        console.error('Erreur planning:', error);
    }
}

function renderPlanning(data, debut, fin) {
    const dates = [];
    for (let d = new Date(debut); d <= new Date(fin); d.setDate(d.getDate() + 1)) {
        dates.push(new Date(d));
    }

    let html = `<table class="table table-bordered planning-table"><thead><tr><th>Tournée</th>`;
    dates.forEach(d => {
        html += `<th class="text-center">${d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' })}</th>`;
    });
    html += `</tr></thead><tbody>`;

    tournees.forEach(t => {
        html += `<tr><td><strong>${t.nom}</strong><br><small class="text-muted">${t.duree}</small></td>`;
        dates.forEach(d => {
            const dateStr = d.toISOString().split('T')[0];
            html += `<td class="p-1">`;
            
            if (t.duree === 'journee' || t.duree === 'matin') {
                const attrMatin = data.find(x => x.date === dateStr && x.periode === 'matin' && x.tournee_id == t.id);
                html += createCellContent(t, attrMatin, dateStr, 'matin');
            }
            
            if (t.duree === 'journee' || t.duree === 'apres-midi') {
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
    
    if (attr && attr.score_ia !== undefined) {
        const score = Math.round(attr.score_ia);
        
        if (score >= 80) cellClass = 'bg-success bg-opacity-25';
        else if (score >= 60) cellClass = 'bg-info bg-opacity-25';
        else if (score >= 40) cellClass = 'bg-warning bg-opacity-25';
        else if (score > 0) cellClass = 'bg-danger bg-opacity-25';
        
        let badgeClass = 'bg-secondary';
        if (score >= 80) badgeClass = 'bg-success';
        else if (score >= 60) badgeClass = 'bg-info';
        else if (score >= 40) badgeClass = 'bg-warning';
        else if (score > 0) badgeClass = 'bg-danger';
        
        scoreDisplay = `<div class="mt-1">
            <span class="badge ${badgeClass} small">${score}/100</span>
        </div>`;
    }
    
    return `<div class="mb-1 p-2 rounded ${cellClass} planning-cell">
        <small class="text-muted d-block">${periode === 'matin' ? '🌅 Matin' : '🌆 Après-midi'}</small>
        <select class="form-select form-select-sm mb-1" onchange="sauvegarderAttribution(this)" 
                data-date="${date}" data-periode="${periode}" data-tournee="${tournee.id}">
            <option value="">-- Libre --</option>
            ${conducteurs.map(c => `<option value="${c.id}" ${c.id == attr?.conducteur_id ? 'selected' : ''}>
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
    
    // Retirer d'anciennes surbrillances
    document.querySelectorAll('.planning-cell.conflict').forEach(el => {
        el.classList.remove('conflict');
    });
    
    // Vérifier conflit : même conducteur sur la MÊME PÉRIODE du même jour
    if (conducteurId) {
        // Tous les selects pour cette date ET CETTE PÉRIODE, SAUF celui en cours
        const selects = Array.from(document.querySelectorAll(`select[data-date="${date}"][data-periode="${periode}"]`))
            .filter(s => s !== select); // Exclure le select actuel
        
        // Comptage des occurences sur la même période
        const occurrences = selects.filter(s => +s.value === conducteurId);
        
        if (occurrences.length > 0) {
            // Conflit détecté : même conducteur, même jour, même période, autre tournée
            occurrences.forEach(s => {
                s.closest('div').classList.add('conflict');
            });
            select.closest('div').classList.add('conflict');
            showToast('Conflit', `Ce conducteur est déjà affecté ${periode === 'matin' ? 'le matin' : 'l\'après-midi'} sur une autre tournée.`, 'danger');
            // Annuler la sélection
            select.value = '';
            return;
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
        }
        
        await apiCall('add_attribution', 'POST', {
            date, periode, tournee_id: +tourneeId, conducteur_id: conducteurId, score_ia: scoreIA
        });
        
        showToast('Succès', conducteurId 
            ? `Conducteur affecté (Score: ${Math.round(scoreIA)}/100)` 
            : 'Attribution supprimée', 'success');
        
        setTimeout(chargerPlanning, 200);
        
    } catch (error) {
        console.error('Erreur sauvegarde attribution:', error);
        showToast('Erreur', 'Impossible de sauvegarder l\'attribution.', 'danger');
    } finally {
        select.disabled = false;
        parent.classList.remove('loading');
    }
}


async function remplirPlanningAuto() {
    const debut = document.getElementById('planning-date-debut').value;
    const fin = document.getElementById('planning-date-fin').value;
    
    if (!debut || !fin) {
        showToast('Attention', 'Sélectionnez une période valide', 'warning');
        return;
    }

    if (!confirm(`Générer automatiquement le planning d'IA du ${debut} au ${fin} ?`)) {
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Génération...';

    try {
        const response = await fetch('api.php?action=remplir_auto', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json;charset=utf-8' },
            body: JSON.stringify({ debut: debut, fin: fin })
        });
        const result = await response.json();

        if (response.ok && result.success) {
            const { succes, echecs } = result.data;
            showToast('IA Générée', `${succes} attributions créées, ${echecs} échecs`, 'success');
            setTimeout(() => chargerPlanning(), 300);
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

// ==================== CONFIGURATION ====================
async function chargerConfig() {
    try {
        const { data } = await apiCall('get_config');
        config = data || {};
        renderConfig();
        chargerCriteresIA();
    } catch (error) {
        console.error('Erreur config:', error);
    }
}

function renderConfig() {
    const lp = document.getElementById('liste-permis');
    const lv = document.getElementById('liste-vehicules');
    if (!lp || !lv) return;
    
    lp.innerHTML = '';
    lv.innerHTML = '';
    
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
}

function chargerCriteresIA() {
    document.getElementById('poids-titulaire').value = config.poids_titulaire || 100;
    document.getElementById('poids-connaissance').value = config.poids_connaissance || 80;
    document.getElementById('poids-experience').value = config.poids_experience || 4;
    document.getElementById('poids-disponibilite').value = config.poids_disponibilite || 60;
    document.getElementById('penalite-interimaire').value = config.penalite_interimaire || -50;
}

async function sauvegarderCriteresIA() {
    const criteres = {
        poids_titulaire: +document.getElementById('poids-titulaire').value,
        poids_connaissance: +document.getElementById('poids-connaissance').value,
        poids_experience: +document.getElementById('poids-experience').value,
        poids_disponibilite: +document.getElementById('poids-disponibilite').value,
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
