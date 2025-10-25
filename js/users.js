// ==================== UTILISATEURS ====================

async function chargerUtilisateurs() {
    // Ne charger que si l'utilisateur est admin
    if (!document.getElementById('utilisateurs-tab')) return;
    
    try {
        const { data } = await apiCall('get_users');
        const tbody = document.getElementById('liste-utilisateurs');
        if (!tbody) return;

        tbody.innerHTML = '';
        data.forEach(u => {
            const dernierLogin = u.dernier_login ? 
                new Date(u.dernier_login).toLocaleString('fr-FR') : 'Jamais';
            
            tbody.innerHTML += `
            <tr>
                <td>
                    <strong>${u.username}</strong><br>
                    <small class="text-muted">${u.nom || ''}</small>
                </td>
                <td><span class="badge bg-${u.role === 'admin' ? 'danger' : 'info'}">${u.role}</span></td>
                <td>${u.email || ''}</td>
                <td><small>${dernierLogin}</small></td>
                <td>
                    <span class="badge bg-${u.actif ? 'success' : 'secondary'}">
                        ${u.actif ? 'Actif' : 'Inactif'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="afficherModalUtilisateur(${u.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="supprimerUtilisateur(${u.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        });
    } catch (error) {
        console.error('Erreur chargement utilisateurs:', error);
        showToast('Erreur', 'Impossible de charger les utilisateurs', 'danger');
    }
}

async function afficherModalUtilisateur(id = null) {
    const modal = new bootstrap.Modal(document.getElementById('modalUtilisateur'));
    const title = document.getElementById('modalUtilisateurTitle');
    const pwdInfo = document.getElementById('pwd-info');
    
    // Réinitialiser le formulaire
    document.getElementById('formUtilisateur').reset();
    document.getElementById('u-id').value = '';
    
    if (id) {
        title.textContent = 'Modifier l\'utilisateur';
        pwdInfo.textContent = '(laisser vide pour ne pas modifier)';
        
        try {
            const { data } = await apiCall(`get_user&id=${id}`);
            document.getElementById('u-id').value = data.id;
            document.getElementById('u-username').value = data.username;
            document.getElementById('u-nom').value = data.nom || '';
            document.getElementById('u-email').value = data.email || '';
            document.getElementById('u-role').value = data.role;
            document.getElementById('u-actif').checked = data.actif;
            document.getElementById('u-username').readOnly = true; // Ne pas permettre de changer le username
        } catch (error) {
            console.error('Erreur chargement utilisateur:', error);
            return;
        }
    } else {
        title.textContent = 'Nouvel utilisateur';
        pwdInfo.textContent = '(requis)';
        document.getElementById('u-username').readOnly = false;
    }
    
    modal.show();
}

async function sauvegarderUtilisateur() {
    const id = document.getElementById('u-id').value;
    const password = document.getElementById('u-password').value;
    
    // Validation
    if (!id && !password) {
        showToast('Erreur', 'Le mot de passe est requis pour un nouvel utilisateur', 'danger');
        return;
    }
    
    const data = {
        username: document.getElementById('u-username').value,
        nom: document.getElementById('u-nom').value,
        email: document.getElementById('u-email').value,
        role: document.getElementById('u-role').value,
        actif: document.getElementById('u-actif').checked
    };
    
    if (password) {
        data.password = password;
    }
    
    try {
        if (id) {
            await apiCall('update_user', 'POST', { id, ...data });
        } else {
            await apiCall('add_user', 'POST', data);
        }
        
        showToast('Succès', 'Utilisateur enregistré', 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalUtilisateur')).hide();
        chargerUtilisateurs();
    } catch (error) {
        console.error('Erreur sauvegarde:', error);
        showToast('Erreur', 'Impossible de sauvegarder l\'utilisateur', 'danger');
    }
}

async function supprimerUtilisateur(id) {
    if (!confirm('Confirmer la suppression de cet utilisateur ?')) return;
    
    try {
        await apiCall('delete_user', 'POST', { id });
        showToast('Succès', 'Utilisateur supprimé', 'success');
        chargerUtilisateurs();
    } catch (error) {
        console.error('Erreur suppression:', error);
        showToast('Erreur', 'Impossible de supprimer l\'utilisateur', 'danger');
    }
}

// ==================== PROFIL UTILISATEUR ====================

// Fonction pour mettre à jour son propre profil
async function afficherModalProfil() {
    const modal = new bootstrap.Modal(document.getElementById('modalProfil'));
    
    try {
        const { data } = await apiCall('get_profile');
        document.getElementById('p-username').value = data.username;
        document.getElementById('p-nom').value = data.nom || '';
        document.getElementById('p-email').value = data.email || '';
        modal.show();
    } catch (error) {
        console.error('Erreur chargement profil:', error);
        showToast('Erreur', 'Impossible de charger le profil', 'danger');
    }
}

async function sauvegarderProfil() {
    const data = {
        nom: document.getElementById('p-nom').value,
        email: document.getElementById('p-email').value,
        password: document.getElementById('p-password').value || undefined
    };
    
    try {
        await apiCall('update_profile', 'POST', data);
        showToast('Succès', 'Profil mis à jour', 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalProfil')).hide();
    } catch (error) {
        console.error('Erreur sauvegarde profil:', error);
        showToast('Erreur', 'Impossible de mettre à jour le profil', 'danger');
    }
}