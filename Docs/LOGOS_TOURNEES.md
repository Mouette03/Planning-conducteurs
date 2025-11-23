# 📦 Logos et Icônes pour les Tournées

## Nouveautés

### 🎨 Ajout de logos/icônes pour les types de tournées
Vous pouvez maintenant associer un **emoji** ou une **image personnalisée** (JPG, PNG, GIF, BMP) à chaque type de tournée. Le logo s'affichera dans la colonne "Tournée" du planning, sous le nom de la tournée.

**Deux options disponibles :**
1. **Emojis** : Rapide, léger, gratuit (📦 📨 🚚 ✈️)
2. **Images personnalisées** : Logo de votre entreprise, design sur-mesure (JPG, PNG, GIF, BMP)

### 📅 Optimisation de l'affichage des tournées "Journée"
Les tournées avec durée "Journée" n'affichent plus qu'**une seule case** au lieu de deux (matin + après-midi séparés), ce qui permet un gain de place significatif.

**Pourquoi ?** 
- Un camion qui part pour la journée ne peut pas être utilisé par deux conducteurs différents (matin et après-midi)
- Affichage plus clair et plus compact
- Évite les erreurs d'attribution

---

## 🎯 Comment ajouter un logo à un type de tournée

### Option 1 : Emoji (rapide)

#### 1. Onglet Paramètres → Types de Tournée

Dans la section **Types de Tournée**, vous avez maintenant plusieurs champs :
- **Nom** : Le nom du type (ex: "Express", "Messagerie")
- **Emoji** : Un emoji ou caractère spécial (ex: 📦, 📨, 🚚)
- **📷 Bouton Image** : Pour uploader une image personnalisée
- **Ordre** : L'ordre d'affichage dans le planning

#### 2. Exemples d'emojis recommandés

| Type de tournée | Emoji suggéré | Code |
|----------------|---------------|------|
| Express | 📦 | Copier : 📦 |
| Messagerie | 📨 | Copier : 📨 |
| Transport lourd | 🚚 | Copier : 🚚 |
| Livraison internationale | ✈️ | Copier : ✈️ |
| Distribution locale | 🏘️ | Copier : 🏘️ |
| Palette | 📋 | Copier : 📋 |
| Fret | 🚛 | Copier : 🚛 |
| Coursier | 🏃 | Copier : 🏃 |

#### 3. Comment trouver des emojis

**Windows** : 
- Appuyez sur `Windows + .` (point) pour ouvrir le sélecteur d'emojis
- Cherchez par mot-clé (ex: "camion", "package", "avion")

**Mac** :
- Appuyez sur `Cmd + Ctrl + Espace`

**En ligne** :
- [Emojipedia](https://emojipedia.org/)
- [Get Emoji](https://getemoji.com/)

---

### Option 2 : Image personnalisée (professionnel)

#### 1. Préparer votre logo

**Formats acceptés :** JPG, PNG, GIF, BMP  
**Taille maximale :** 1 MB  
**Dimensions recommandées :** 64x64 pixels (carré)  
**Fond :** Transparent (PNG) pour meilleur rendu

#### 2. Upload du logo

1. Onglet **Paramètres** → Section **Types de Tournée**
2. Cliquez sur le bouton **📷** (icône image) à côté du type de tournée
3. Sélectionnez votre fichier image
4. Cliquez sur **Uploader**
5. Le logo s'affiche immédiatement !

#### 3. Remplacer un logo

Pour remplacer un logo par un autre :
- **Par un emoji** : Saisissez l'emoji dans le champ texte (efface l'image)
- **Par une autre image** : Cliquez à nouveau sur 📷 et uploadez la nouvelle image

#### 4. Exemples d'utilisation

**Logo d'entreprise :**
- Créez un logo pour chaque service (Express, Standard, Premium)
- Utilisez les couleurs de votre charte graphique
- Format PNG avec fond transparent

**Icônes métier :**
- Camion pour transport longue distance
- Fourgonnette pour livraison locale
- Avion pour international

---

## 🔧 Détails techniques

### Stockage des images

Les logos uploadés sont stockés dans :
```
uploads/logos_tournees/
```

Nom de fichier généré automatiquement :
```
tournee_[NomType]_[Timestamp].jpg
```

### Affichage

- **Emojis** : Taille 1.5rem (environ 24px)
- **Images** : Redimensionnées à 32x32px automatiquement
- **Position** : Sous le nom de la tournée dans la colonne planning

### Performance

- Images compressées automatiquement
- Cache navigateur utilisé
- Pas d'impact sur la vitesse de chargement

---

## 💡 Conseils d'utilisation

### Cohérence visuelle
- **Emojis** : Utilisez des emojis cohérents (tous les types de transport en 🚚 🚛 🚐)
- **Images** : Gardez le même style graphique pour tous vos logos
- **Combinaison** : Vous pouvez mélanger emojis et images selon vos besoins

### Performance
- Privilégiez les **emojis** pour rapidité et légèreté
- Utilisez les **images** pour branding professionnel
- Optimisez vos images avant upload (64x64px max)

### Accessibilité
- Les logos sont **en complément** du nom de tournée
- Ils ne remplacent pas le texte (toujours accessible aux lecteurs d'écran)
- Utilisez des images avec bon contraste

---

## 🐛 Dépannage

### Le logo ne s'affiche pas
- Vérifiez que vous avez bien **sauvegardé** le type de tournée
- Rechargez la page avec `Ctrl + F5`
- Vérifiez que la tournée a bien un **type de tournée** assigné
- Vérifiez les **permissions** du dossier `uploads/logos_tournees/`

### L'image ne s'upload pas
- Vérifiez la **taille** : max 1MB
- Vérifiez le **format** : JPG, PNG, GIF, BMP uniquement
- Vérifiez les **droits d'écriture** sur le serveur
- Consultez les logs d'erreur PHP

### Le logo s'affiche en carré � (emoji)
- Votre navigateur ou police ne supporte pas cet emoji
- Essayez un emoji plus commun (📦 📨 🚚)
- Ou utilisez une image à la place

### L'image est floue
- Uploadez une image en **meilleure résolution** (64x64px minimum)
- Utilisez le format **PNG** pour meilleure qualité
- Vérifiez que l'image source n'est pas déjà de mauvaise qualité

---

## 📚 Exemples de configuration

### Configuration Express/Standard (Emojis)
```
Type: Express        Logo: 📦  Ordre: 1
Type: Standard       Logo: 📨  Ordre: 2
Type: Messagerie     Logo: ✉️  Ordre: 3
```

### Configuration par tonnage (Emojis)
```
Type: 3.5T          Logo: 🚐  Ordre: 1
Type: 7.5T          Logo: 🚚  Ordre: 2
Type: 19T           Logo: 🚛  Ordre: 3
Type: Semi-remorque Logo: 🚜  Ordre: 4
```

### Configuration professionnelle (Images)
```
Type: Express        Logo: [logo_express.png]     Ordre: 1
Type: Premium        Logo: [logo_premium.png]     Ordre: 2
Type: Eco            Logo: [logo_eco.png]         Ordre: 3
Type: International  Logo: [logo_international.png] Ordre: 4
```

### Configuration mixte (Emojis + Images)
```
Type: Express        Logo: [logo_entreprise.png]  Ordre: 1
Type: Standard       Logo: 📦                      Ordre: 2
Type: International  Logo: ✈️                      Ordre: 3
```

---

## 📊 Affichage dans le Planning

### Avant (avec séparation matin/après-midi)
```
Tournée : Express
Durée : journee

+----------+----------+
|  Matin   |  Matin   |
| 🌅 Matin | 🌅 Matin |
| [Select] | [Select] |
+----------+----------+
|Après-midi|Après-midi|
|🌆 A-midi |🌆 A-midi |
| [Select] | [Select] |
+----------+----------+
```

### Après (une seule case pour journée)
```
Tournée : Express
          📦 (ou logo image)
Durée : journee

+----------+----------+
| Journée  | Journée  |
| 📅 Jour  | 📅 Jour  |
| [Select] | [Select] |
+----------+----------+
```

**Gain de place : 50% !**

---

✅ **Mise à jour appliquée** - Profitez d'un planning plus compact et visuellement organisé avec vos propres logos !

| Type de tournée | Emoji suggéré | Code |
|----------------|---------------|------|
| Express | 📦 | Copier : 📦 |
| Messagerie | 📨 | Copier : 📨 |
| Transport lourd | 🚚 | Copier : 🚚 |
| Livraison internationale | ✈️ | Copier : ✈️ |
| Distribution locale | 🏘️ | Copier : 🏘️ |
| Palette | 📋 | Copier : 📋 |
| Fret | 🚛 | Copier : 🚛 |
| Coursier | 🏃 | Copier : 🏃 |

### 3. Comment trouver des emojis

**Windows** : 
- Appuyez sur `Windows + .` (point) pour ouvrir le sélecteur d'emojis
- Cherchez par mot-clé (ex: "camion", "package", "avion")

**Mac** :
- Appuyez sur `Cmd + Ctrl + Espace`

**En ligne** :
- [Emojipedia](https://emojipedia.org/)
- [Get Emoji](https://getemoji.com/)

### 4. Modifier le logo d'un type existant

Dans la liste des types de tournées, vous verrez un champ texte à côté de chaque type. Modifiez simplement l'emoji et le changement sera automatique.

---

## 📊 Affichage dans le Planning

### Avant (avec séparation matin/après-midi)
```
Tournée : Express
Durée : journee

+----------+----------+
|  Matin   |  Matin   |
| 🌅 Matin | 🌅 Matin |
| [Select] | [Select] |
+----------+----------+
|Après-midi|Après-midi|
|🌆 A-midi |🌆 A-midi |
| [Select] | [Select] |
+----------+----------+
```

### Après (une seule case pour journée)
```
Tournée : Express
          📦
Durée : journee

+----------+----------+
| Journée  | Journée  |
| 📅 Jour  | 📅 Jour  |
| [Select] | [Select] |
+----------+----------+
```

**Gain de place : 50% !**

---

## 🔧 Détails techniques

### Comportement selon la durée

| Durée | Affichage | Icône |
|-------|-----------|-------|
| `matin` | Une case "Matin" | 🌅 |
| `apres-midi` | Une case "Après-midi" | 🌆 |
| `journee` | **Une seule case "Journée"** | 📅 |

### Attribution des conducteurs

Pour les tournées "Journée" :
- La sélection se fait sur une seule case
- Le conducteur est attribué pour **toute la journée**
- En base de données, l'attribution est enregistrée en période `matin` (pour compatibilité)
- Impossible d'attribuer deux conducteurs différents

### Migration automatique

✅ **Aucune action requise** : Les anciennes tournées "journée" avec deux attributions (matin + après-midi) continuent de fonctionner. Le système affiche automatiquement le conducteur du matin.

⚠️ **Nettoyage recommandé** : Si vous aviez des attributions différentes matin/après-midi sur des tournées "journée", l'après-midi sera ignoré. Utilisez le bouton "Actualiser" pour recalculer le planning.

---

## 💡 Conseils d'utilisation

### Cohérence visuelle
- Utilisez des emojis **cohérents** (tous les types de transport en 🚚 🚛 🚐)
- Ou créez des **catégories visuelles** (📦 pour colis, 📨 pour courrier, 🚚 pour véhicules lourds)

### Performance
- Les emojis sont **légers** et n'impactent pas les performances
- Ils s'affichent sur **tous les navigateurs modernes**

### Accessibilité
- Les logos sont **en complément** du nom de tournée
- Ils ne remplacent pas le texte (toujours accessible aux lecteurs d'écran)

---

## 🐛 Dépannage

### Le logo ne s'affiche pas
- Vérifiez que vous avez bien **sauvegardé** le type de tournée
- Rechargez la page avec `Ctrl + F5`
- Vérifiez que la tournée a bien un **type de tournée** assigné

### Le logo s'affiche en carré �
- Votre navigateur ou police ne supporte pas cet emoji
- Essayez un emoji plus commun (📦 📨 🚚)
- Mettez à jour votre navigateur

### Les tournées "journée" affichent encore deux cases
- Rechargez la page complètement (`Ctrl + F5`)
- Vérifiez que `script.js` a bien été mis à jour
- Videz le cache du navigateur

---

## 📚 Exemples de configuration

### Configuration Express/Standard
```
Type: Express        Logo: 📦  Ordre: 1
Type: Standard       Logo: 📨  Ordre: 2
Type: Messagerie     Logo: ✉️  Ordre: 3
```

### Configuration par tonnage
```
Type: 3.5T          Logo: 🚐  Ordre: 1
Type: 7.5T          Logo: 🚚  Ordre: 2
Type: 19T           Logo: 🚛  Ordre: 3
Type: Semi-remorque Logo: 🚜  Ordre: 4
```

### Configuration par service
```
Type: Livraison    Logo: 📦  Ordre: 1
Type: Collecte     Logo: 🔄  Ordre: 2
Type: Distribution Logo: 🏘️  Ordre: 3
Type: International Logo: 🌍  Ordre: 4
```

---

✅ **Mise à jour appliquée** - Profitez d'un planning plus compact et visuellement organisé !
