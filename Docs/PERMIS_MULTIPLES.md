# 🚗 Gestion des Permis Multiples

## Nouvelles Fonctionnalités Implémentées

### ✅ Ce qui a changé

#### 1. **Conducteurs** - Permis multiples
- Un conducteur peut maintenant avoir **plusieurs permis** (B, C, C+E, D, EC, etc.)
- Interface avec **cases à cocher** au lieu d'un menu déroulant
- Stockage en JSON dans la base de données

#### 2. **Tournées** - Permis requis
- Chaque tournée peut définir **un ou plusieurs permis acceptés**
- Interface avec **cases à cocher** pour sélectionner les permis requis
- Une tournée peut accepter plusieurs types de permis (ex: C ou C+E)

#### 3. **Algorithme IA** - Vérification automatique
- ✅ Vérifie **automatiquement** la compatibilité des permis
- ❌ **Bloque** l'attribution si le conducteur n'a aucun des permis requis
- 🎯 Priorise les conducteurs avec les bons permis

## 📋 Migration Nécessaire

### Pour les bases de données existantes

**Étape 1** : Exécutez le fichier de migration
```
http://localhost/votre-dossier/migration_permis_multiple.php
```

Ce script va :
- Convertir les permis uniques des conducteurs en tableaux JSON
- Ajouter la colonne `permis_requis` aux tournées
- Préserver toutes les données existantes

**Étape 2** : Supprimez le fichier après migration
```
migration_permis_multiple.php
```

### Pour les nouvelles installations
✅ Aucune action requise - tout est configuré automatiquement

## 🎯 Utilisation

### Ajouter/Modifier un Conducteur
1. Ouvrir le modal conducteur
2. Section "Permis détenus"
3. **Cocher tous les permis** que le conducteur possède
4. Au moins un permis doit être sélectionné

**Exemple :**
```
☑ B
☑ C
☐ C+E
☑ D
☐ EC
```
Ce conducteur a les permis B, C et D.

### Ajouter/Modifier une Tournée
1. Ouvrir le modal tournée
2. Section "Permis requis"
3. **Cocher les permis acceptés** pour cette tournée
4. Au moins un permis doit être sélectionné

**Exemple :**
```
☐ B
☑ C
☑ C+E
☐ D
☐ EC
```
Cette tournée accepte les conducteurs ayant le permis C **OU** C+E.

## 🤖 Comportement de l'IA

### Scénario 1 : Conducteur avec permis compatible
```
Conducteur : Jean (Permis: B, C)
Tournée : Livraison Centre-ville (Permis requis: B, C)

Résultat : ✅ Éligible (a le permis C)
Score calculé normalement
```

### Scénario 2 : Conducteur sans permis compatible
```
Conducteur : Marie (Permis: B)
Tournée : Transport 19T (Permis requis: C, C+E)

Résultat : ❌ Non éligible
Message : "Permis requis : C, C+E"
Score : 0 - Non disponible
```

### Scénario 3 : Génération automatique IA
L'IA va automatiquement :
1. ✅ Vérifier les permis pour chaque combinaison conducteur/tournée
2. ❌ Exclure les conducteurs sans permis compatible
3. 🎯 Sélectionner le meilleur conducteur **parmi ceux qui ont le bon permis**

## 💡 Avantages

✅ **Flexibilité** : Les conducteurs peuvent avoir plusieurs certifications  
✅ **Sécurité** : Impossible d'attribuer un conducteur sans le bon permis  
✅ **Automatisation** : L'IA gère automatiquement la vérification  
✅ **Clarté** : Messages explicites en cas d'incompatibilité  
✅ **Évolutif** : Facile d'ajouter de nouveaux types de permis

## 🔧 Ordre de Vérification de l'IA

L'algorithme vérifie dans cet ordre :

1. **Permis** ❌ Bloquant → Si pas de permis compatible = score 0
2. **Disponibilité** ❌ Bloquant → Congés, repos, maladie = score 0
3. **Titulaire** ✅ +80 points (configurable dans Paramètres IA)
4. **Connaissance** ✅ +80 points (configurable dans Paramètres IA)
5. **Expérience** ✅ +points selon années (multiplicateur configurable)
6. **Statut** ✅/❌ CDI +10, Intérimaire -50

## ⚠️ Points d'Attention

- **Au moins un permis** doit être sélectionné pour chaque conducteur
- **Au moins un permis** doit être défini pour chaque tournée
- Les permis sont **vérifiés en premier** avant toute autre règle
- La migration **préserve toutes les données** existantes
