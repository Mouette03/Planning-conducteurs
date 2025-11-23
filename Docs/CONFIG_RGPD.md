# 📋 Configuration RGPD - Guide Administrateur

## 🎯 Où modifier les informations RGPD ?

### ⚠️ IMPORTANT
Le fichier `Docs/rgpd_exemple.md` est un **TEMPLATE DE DOCUMENTATION** uniquement.  
Les **vraies informations RGPD** utilisées dans l'application sont dans le **code PHP**.

---

## 📝 Fichiers à Modifier

### 1. **`api.php`** (lignes 445-489)
**C'est LE fichier principal pour l'export RGPD JSON**

Modifiez les sections suivantes :

```php
'informations_rgpd' => [
    'responsable_traitement' => '[NOM DE VOTRE ENTREPRISE]',  // ← MODIFIER ICI
    'contact_dpo' => '[EMAIL DPO/CONTACT]',                   // ← MODIFIER ICI
    // ... autres informations
]
```

**Champs à personnaliser :**
- `responsable_traitement` : Nom de votre entreprise
- `contact_dpo` : Email du DPO ou contact RGPD

**Ce fichier est utilisé pour :**
- Export JSON depuis la fiche conducteur
- Bouton "Exporter RGPD" dans l'interface

---

### 2. **`export_rgpd_pdf.php`** (lignes 267-301)
**Pour l'export PDF (si activé)**

Modifiez les sections suivantes :

```php
// Ligne 267
$pdf->MultiCell(0, 5, utf8ToLatin1('[NOM DE VOTRE ENTREPRISE]' . "\n" . '[ADRESSE]' . "\n" . 'Email : [EMAIL]'));

// Ligne 298
$pdf->Cell(0, 5, utf8ToLatin1('Email : [EMAIL DPO/CONTACT]'), 0, 1);
```

**Champs à personnaliser :**
- `[NOM DE VOTRE ENTREPRISE]` : Nom de votre entreprise
- `[ADRESSE]` : Adresse complète
- `[EMAIL]` : Email général
- `[EMAIL DPO/CONTACT]` : Email du DPO

---

### 3. **`Docs/rgpd_exemple.md`** (optionnel)
**Documentation cliente/utilisateur**

Ce fichier sert de **modèle de politique de confidentialité** à afficher sur votre site web ou à fournir aux utilisateurs.

**Sections à personnaliser :**
- Section 1 : Responsable du traitement (nom, adresse, contact)
- Section 8.7 : Contact pour exercer les droits
- Section 12 : Contact général

**Ce fichier N'EST PAS utilisé par l'application**, c'est juste une documentation à distribuer.

---

## 🔧 Procédure de Configuration

### Étape 1 : Modifier `api.php`
```bash
1. Ouvrir api.php
2. Chercher "informations_rgpd" (ligne 445)
3. Remplacer [NOM DE VOTRE ENTREPRISE]
4. Remplacer [EMAIL DPO/CONTACT]
5. Sauvegarder
```

### Étape 2 : Modifier `export_rgpd_pdf.php` (si export PDF utilisé)
```bash
1. Ouvrir export_rgpd_pdf.php
2. Chercher "[NOM DE VOTRE ENTREPRISE]" (ligne 267)
3. Remplacer tous les placeholders
4. Sauvegarder
```

### Étape 3 : Personnaliser la documentation (optionnel)
```bash
1. Ouvrir Docs/rgpd_exemple.md
2. Remplacer tous les [PLACEHOLDERS]
3. Publier sur votre site ou distribuer aux utilisateurs
```

---

## 📊 Données Exportées

### Export JSON (api.php)
L'export inclut automatiquement :

**Informations personnelles :**
- ✅ ID, nom, prénom
- ✅ Permis de conduire
- ✅ Expérience (manuelle)
- ✅ **Date d'embauche** (nouveau)
- ✅ Statut d'entreprise
- ✅ Tournée titulaire
- ✅ **Tournées maîtrisées** (nouveau)

**Disponibilités :**
- ✅ Repos récurrents
- ✅ Congés planifiés
- ✅ Statut temporaire

**Historique :**
- ✅ Planning (6 derniers mois)
- ✅ Statistiques de performance (3 derniers mois)

**Informations RGPD :**
- ✅ Responsable du traitement
- ✅ Finalité du traitement
- ✅ Base légale
- ✅ Durée de conservation
- ✅ Destinataires
- ✅ **Liste des données collectées** (nouveau)
- ✅ Droits RGPD
- ✅ **Mesures de sécurité** (nouveau)
- ✅ Contact DPO
- ✅ CNIL

---

## 🔍 Vérification

### Tester l'export RGPD
1. Connectez-vous à l'application
2. Allez sur **Conducteurs**
3. Cliquez sur un conducteur
4. Cliquez **Exporter RGPD**
5. Vérifiez que vos informations personnalisées apparaissent dans le JSON

### Résultat attendu
```json
{
  "date_export": "2025-11-14 10:30:00",
  "finalite": "Export des données personnelles conformément au RGPD",
  "conducteur": {
    "id": 1,
    "nom": "Dupont",
    "prenom": "Jean",
    "experience": 5,
    "date_embauche": "2020-01-15",  // ← NOUVEAU
    "tournees_maitrisees": [1, 3, 5]  // ← NOUVEAU
  },
  "informations_rgpd": {
    "responsable_traitement": "VOTRE ENTREPRISE ICI",  // ← VOS INFOS
    "contact_dpo": "dpo@votre-entreprise.fr",          // ← VOS INFOS
    "donnees_collectees": [...]  // ← NOUVEAU
  }
}
```

---

## ⚡ Mise à Jour Récentes (Novembre 2025)

### Ajouts dans l'export RGPD

#### Dans `api.php` :
- ✅ Ajout `date_embauche` dans les données exportées
- ✅ Ajout `tournees_maitrisees` dans les données exportées
- ✅ Ajout section `donnees_collectees` (liste complète)
- ✅ Ajout section `securite` (mesures de protection)
- ✅ Finalité mise à jour (mention IA)
- ✅ Droits mis à jour (précisions sur les actions possibles)

#### Dans `export_rgpd_pdf.php` :
- ✅ Ajout `date_embauche` dans les informations personnelles
- ✅ Ajout section "Données collectées" complète
- ✅ Finalité mise à jour (mention IA)
- ✅ Droits précisés avec actions concrètes

---

## 🎯 Checklist de Configuration

Avant la mise en production, vérifiez :

- [ ] `api.php` : Responsable du traitement personnalisé
- [ ] `api.php` : Email DPO/contact personnalisé
- [ ] `export_rgpd_pdf.php` : Nom entreprise personnalisé
- [ ] `export_rgpd_pdf.php` : Adresse complète renseignée
- [ ] `export_rgpd_pdf.php` : Email de contact renseigné
- [ ] `Docs/rgpd_exemple.md` : Tous les [PLACEHOLDERS] remplacés (si utilisé)
- [ ] Test d'export RGPD effectué et validé
- [ ] Vérification que les informations sont correctes dans le JSON
- [ ] Vérification que les informations sont correctes dans le PDF (si utilisé)

---

## 📞 Support

En cas de doute sur la configuration RGPD, vérifiez :
1. Que vous modifiez bien les **fichiers PHP** (pas juste la documentation)
2. Que vous avez bien **sauvegardé** après modification
3. Que vous avez **rechargé la page** dans le navigateur

---

*Document généré le 14 novembre 2025*  
*Planning Conducteur Pro - Configuration RGPD*
