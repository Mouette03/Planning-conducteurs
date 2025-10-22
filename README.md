# Application de Planning Conducteur - PHP/MariaDB

## But de l'application

**Planning Conducteur Pro** est une application web complète de gestion et d'optimisation des plannings pour les conducteurs et leurs tournées. Elle utilise un système d'intelligence artificielle pour automatiser l'attribution des conducteurs aux tournées en fonction de multiples critères (compétences, disponibilité, expérience, repos, congés).

## Fonctionnalités principales

### 🚛 Gestion des Conducteurs
- **Profil complet** : nom, prénom, permis, contact, années d'expérience
- **Statut d'entreprise** : CDI, CDD, intérimaire, sous-traitant
- **Tournées maîtrisées** : liste des tournées que le conducteur peut effectuer
- **Tournée titulaire** : tournée principale affectée au conducteur
- **Repos récurrents** : configuration des jours de repos hebdomadaires
- **Gestion des congés** : périodes de congés, maladie, formation
- **Statut temporaire** : disponible, congé, malade, formation, repos

### 🗺️ Gestion des Tournées
- **Informations détaillées** : nom, description, zone géographique
- **Configuration** : type de véhicule requis, niveau de difficulté (1-5)
- **Durée flexible** : journée complète, matin uniquement, ou après-midi uniquement
- **Suivi des performances** par tournée

### 📅 Planning Intelligent
- **Interface calendrier** : visualisation hebdomadaire ou sur période personnalisée
- **Attribution manuelle** : sélection directe des conducteurs pour chaque tournée
- **Attribution automatique par IA** : remplissage intelligent du planning sur une période
- **Calcul de score IA** : score de compatibilité (0-100) pour chaque attribution
- **Détection de conflits** : alertes en cas de double affectation
- **Gestion matin/après-midi** : planning séparé pour chaque demi-journée

### 🤖 Intelligence Artificielle
L'IA prend en compte plusieurs critères pour optimiser les attributions :
- ✅ Maîtrise de la tournée par le conducteur
- ✅ Statut de titulaire sur la tournée
- ✅ Expérience du conducteur
- ✅ Disponibilité (repos, congés, statut temporaire)
- ✅ Niveau de difficulté de la tournée
- ✅ Statut d'entreprise (priorité aux CDI)
- ✅ Historique de performance

### 📊 Statistiques et Performance
- **Tableau de bord** : vue d'ensemble des conducteurs, tournées et attributions
- **Score de performance global** : évaluation de la qualité du planning
- **Statistiques par conducteur** : score moyen et nombre d'attributions
- **Bonus qualité** : valorisation des conducteurs CDI sur tournées difficiles

### ⚙️ Configuration
- **Types de permis personnalisables** : ajout/suppression de catégories de permis
- **Types de véhicules** : gestion des différents types de véhicules
- **Critères IA ajustables** : poids de chaque critère dans le calcul du score
- **Logo personnalisable** : ajout du logo de l'entreprise
- **Gestion multi-utilisateurs** : comptes admin et utilisateurs standards

### 🔐 Authentification et Sécurité
- Système de connexion sécurisé
- Gestion des rôles (administrateur / utilisateur)
- Sessions utilisateur protégées

## Installation

1. Décompresser l'archive dans votre dossier web (www/ ou htdocs/)
2. Accéder à http://localhost/planning-conducteur/
3. Suivre l'assistant d'installation
4. Configuration automatique de la base de données

## Prérequis

- Apache + PHP 7.4+
- MariaDB ou MySQL
- Extensions PHP: pdo, pdo_mysql, json, mbstring

## Interface

- Interface responsive Bootstrap 5
- Compatible desktop, tablette et mobile
- Design moderne avec dégradés et animations
- Notifications toast pour les actions utilisateur

## Technologies utilisées

- **Backend** : PHP 7.4+ avec architecture MVC
- **Base de données** : MariaDB/MySQL avec support JSON
- **Frontend** : Bootstrap 5 + JavaScript vanilla
- **Icons** : Bootstrap Icons
- **API REST** : Communication asynchrone avec le backend

Voir INSTALL-GUIDE.md pour la documentation complète.