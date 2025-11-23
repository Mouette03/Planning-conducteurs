# Application de Planning Conducteur - PHP/MariaDB

## 📋 Présentation

**Planning Conducteur Pro** est une application web complète de gestion et d'optimisation des plannings pour les conducteurs et leurs tournées. Elle utilise un système d'intelligence artificielle pour automatiser l'attribution des conducteurs aux tournées en fonction de multiples critères (compétences, disponibilité, expérience, repos, congés).

### 🎯 Points forts
- ✅ **IA intelligente** : Attribution automatique optimisée
- ✅ **Gestion complète** : Conducteurs, tournées, planning
- ✅ **Personnalisable** : Logos, types de tournées, critères IA
- ✅ **RGPD** : Export des données personnelles intégré
- ✅ **Moderne** : Interface responsive et intuitive

## Fonctionnalités principales

### 🚛 Gestion des Conducteurs
- **Profil complet** : nom, prénom, permis, contact
- **Expérience** : saisie manuelle OU calcul automatique depuis la date d'embauche
- **Statut d'entreprise** : CDI, CDD, intérimaire, sous-traitant
- **Tournées maîtrisées** : liste des tournées que le conducteur peut effectuer
- **Tournée titulaire** : tournée principale affectée au conducteur (priorité absolue)
- **Repos récurrents** : configuration des jours de repos hebdomadaires
- **Gestion des congés** : périodes de congés, maladie, formation
- **Statut temporaire** : disponible, congé, malade, formation, repos
- **Export RGPD** : export complet des données personnelles au format JSON

### 🗺️ Gestion des Tournées
- **Informations détaillées** : nom, description, zone géographique
- **Configuration** : type de véhicule requis, niveau de difficulté (1-5)
- **Durée flexible** : 
  - **Journée** (1 case) : camion part toute la journée
  - **Matin** uniquement
  - **Après-midi** uniquement
  - **Matin et après-midi** (2 cases) : camion rentre le midi
- **Logos personnalisés** : emoji OU image (JPG/PNG/BMP, 1MB max)
- **Tri automatique** : par type de tournée
- **Suivi des performances** par tournée

### 📅 Planning Intelligent
- **Interface calendrier** : visualisation hebdomadaire ou sur période personnalisée
- **Attribution manuelle** : sélection directe des conducteurs pour chaque tournée
- **Attribution automatique par IA** : remplissage intelligent du planning sur une période
- **Actualisation** : recalcul des scores et réattribution des titulaires
- **Calcul de score IA** : score de compatibilité (0-100) pour chaque attribution
- **Détection intelligente de conflits** : 
  - ⚠️ Titulaire sur mauvaise tournée
  - ⚠️ Conducteur déjà affecté sur tournée "journée"
  - ⚠️ Attribution à tournée "journée" avec autre attribution
  - ✅ Compatible : périodes différentes (matin/après-midi)
- **Gestion matin/après-midi** : planning séparé pour chaque demi-journée
- **Badges de statut** : CDI/CDD/INT/ST avec code couleur

### 🤖 Intelligence Artificielle
L'IA prend en compte plusieurs critères pour optimiser les attributions :

**Phase 1 : Titulaires en priorité**
- ⭐ Attribue TOUS les titulaires sur leur tournée d'abord
- 🔄 Retire automatiquement le titulaire s'il est mal placé
- ✅ Vérifie permis, disponibilité et compatibilité

**Phase 2 : Remplaçants**
- ✅ Maîtrise de la tournée par le conducteur
- ✅ Expérience du conducteur (manuelle ou auto-calculée)
- ✅ Disponibilité (repos, congés, statut temporaire)
- ✅ Vérification des permis requis (BLOQUANT)
- ✅ Niveau de difficulté de la tournée
- ✅ Statut d'entreprise (priorité aux CDI)
- ✅ Historique de performance

### 📊 Statistiques et Performance
- **Tableau de bord** : vue d'ensemble des conducteurs, tournées et attributions
- **Score de performance global** : évaluation de la qualité du planning
- **Statistiques par conducteur** : score moyen et nombre d'attributions
- **Bonus qualité** : valorisation des conducteurs CDI sur tournées difficiles

### ⚙️ Configuration
- **Types de permis personnalisables** : ajout/suppression de catégories de permis (B, C, C+E, D, EC...)
- **Types de véhicules** : gestion des différents types de véhicules (3.5T, 7.5T, 12T, 19T, 40T...)
- **Types de tournées** : organisation et tri personnalisés
- **Critères IA ajustables** : poids de chaque critère dans le calcul du score
- **Logo entreprise** : upload d'image (JPG/PNG, max 2MB)
- **Logos tournées** : emoji ou image par tournée
- **Gestion multi-utilisateurs** : comptes admin et utilisateurs standards

### 🔐 Authentification et Sécurité
- Système de connexion sécurisé (bcrypt)
- Gestion des rôles (administrateur / utilisateur)
- Sessions utilisateur protégées
- Protection upload de fichiers (validation type/taille)
- .htaccess sécurisé sur dossier uploads

### 📊 RGPD & Conformité
- Export complet des données personnelles (JSON)
- Historique de planning (6 derniers mois)
- Statistiques de performance (3 derniers mois)
- Informations complètes sur le traitement des données
- Documentation RGPD fournie

## 💾 Installation

### Prérequis
- Apache + PHP 7.4+ ou 8.x
- MariaDB 10.3+ ou MySQL 5.7+
- Extensions PHP: pdo, pdo_mysql, json, mbstring, gd (pour images)

### Installation rapide
1. Décompresser l'archive dans votre dossier web (www/ ou htdocs/)
2. Créer une base de données MySQL/MariaDB
3. Copier `config.php.example` en `config.php`
4. Modifier les identifiants de base de données dans `config.php`
5. Accéder à `http://localhost/planning-conducteur/install.php`
6. Suivre l'assistant d'installation
7. Supprimer `install.php` après installation

### Première connexion
- Utilisateur : celui créé lors de l'installation
- Rôle : Administrateur

## 📱 Interface

## 📱 Interface

- Interface responsive Bootstrap 5
- Compatible desktop, tablette et mobile
- Design moderne avec dégradés et animations
- Notifications toast pour les actions utilisateur
- Modales pour édition rapide
- Badges de statut avec code couleur
- Emojis et icônes pour meilleure lisibilité

## 🛠️ Technologies utilisées

- **Backend** : PHP 7.4+ avec architecture MVC
- **Base de données** : MariaDB/MySQL avec support JSON
- **Frontend** : Bootstrap 5 + JavaScript vanilla
- **Icons** : Bootstrap Icons + Emojis
- **API REST** : Communication asynchrone avec le backend
- **Upload** : Gestion sécurisée des fichiers images

## 📚 Documentation

- `README.md` : Ce fichier (vue d'ensemble)
- `PRESENTATION.md` : Document de présentation détaillé
- `Docs/rgpd_exemple.md` : Politique RGPD
- `Docs/LOGOS_TOURNEES.md` : Guide logos de tournées
- Voir les commentaires dans le code pour plus de détails

## 🔄 Mises à jour récentes

### Novembre 2025
- ✅ Ajout calcul automatique expérience (date d'embauche)
- ✅ Logos personnalisés pour tournées (emoji + images)
- ✅ Nouvelle durée "Matin et après-midi" (2 cases séparées)
- ✅ Détection intelligente de conflits
- ✅ Actualisation améliorée (réattribution titulaires)
- ✅ Export RGPD intégré
- ✅ Orthographe avec accents (journée, après-midi)

## 📄 Licence

Ce projet est sous licence propriétaire. Tous droits réservés.

## 👤 Auteur

Développé pour la gestion optimisée des plannings de conducteurs.

---

**Pour toute question ou support, consultez la documentation dans le dossier `Docs/`**