# 📋 Fonctionnalité : Édition des Titres de Cartes

## 🎯 Objectif

Permettre aux utilisateurs de personnaliser les titres des sections (cartes violettes 📋) qui proviennent des lignes de commande avec le **service ID=361** (product_type=1).

## 📝 Changement Important

**ANCIEN COMPORTEMENT** : Les titres étaient identifiés par `product_type = 9`  
**NOUVEAU COMPORTEMENT** : Les titres sont identifiés par `fk_product = 361` (service ID=361, product_type=1)

Cette modification permet d'utiliser un service standard Dolibarr pour marquer les titres de sections plutôt qu'un type de produit spécial.

## ✨ Fonctionnalités

### 1. **Affichage Intelligent**
- Si l'extrafield `ref_chantier` est rempli, il est affiché en priorité
- Sinon, le `label` de la ligne (product_label) est utilisé
- Pas d'indication visuelle spéciale (comportement transparent)

### 2. **Interface d'Édition**
- Icône crayon ✏️ visible au survol de chaque titre
- Clic sur ✏️ → le titre devient éditable
- Champ de saisie avec focus automatique
- Boutons de validation explicites :
  - ✓ pour Sauvegarder
  - ✗ pour Annuler

### 3. **Sauvegarde**
- Validation manuelle via le bouton ✓
- Sauvegarde immédiate dans l'extrafield `ref_chantier`
- Feedback visuel avec notification
- Gestion des erreurs avec messages explicites

### 4. **Raccourcis Clavier**
- `Enter` : Sauvegarder
- `Escape` : Annuler

## 📁 Fichiers Modifiés/Créés

### 1. **colisage_tab.php** ✅
**Modifications :**
```php
// ANCIEN : Détection des titres avec product_type = 9
// if ($line->product_type == 9)

// NOUVEAU : Détection des titres avec le service ID=361
if ($line->fk_product == 361 && $line->product_type == 1) {
    // C'est un titre de section - créer une nouvelle section
    
    // Récupération de l'extrafield ref_chantier
    $ref_chantier = '';
    if (!empty($line->array_options['options_ref_chantier'])) {
        $ref_chantier = $line->array_options['options_ref_chantier'];
    }
    
    // Utiliser ref_chantier en priorité, sinon label de la ligne
    $titre_affiche = !empty($ref_chantier) ? $ref_chantier : $line->label;
    
    // Créer une nouvelle section avec toutes les infos nécessaires
    $currentSection = array(
        'titre' => $titre_affiche,          // Titre à afficher
        'titre_original' => $line->label,    // Titre original
        'ref_chantier' => $ref_chantier,     // Extrafield
        'rowid' => $line->rowid,             // ID pour mise à jour
        'rang' => $line->rang,
        'produits' => array()
    );
}
```

**Remplacement :** Le fichier complet a été remplacé avec les modifications intégrées.

### 2. **ajax/save_card_title.php** ✅ (Aucune modification nécessaire)
**Endpoint AJAX pour la sauvegarde**

Ce fichier n'a pas besoin de modification car il ne vérifie pas le type de produit, il sauvegarde simplement le `ref_chantier` pour n'importe quelle ligne de commande.

**Paramètres attendus :**
- `action` : "save_card_title"
- `token` : Token CSRF Dolibarr
- `rowid` : ID de la ligne de commande (commandedet.rowid)
- `new_title` : Nouveau titre à sauvegarder

**Fonctionnement :**
1. Vérification des permissions (droit `commande/creer`)
2. Validation des paramètres
3. Mise à jour de l'extrafield `ref_chantier` dans `commandedet_extrafields`
4. Si l'extrafield n'existe pas encore, création automatique
5. Retour JSON avec succès/erreur

**Sécurité :**
- Vérification du token CSRF
- Vérification des droits utilisateur
- Échappement des données SQL
- Gestion des erreurs complète

### 3. **css/colisage.css** ✅ (Aucune modification nécessaire)
**Styles pour l'édition des titres**

Les styles CSS restent identiques car l'interface utilisateur ne change pas.

```css
/* Section titre - actions d'édition */
.section-titre-actions {
    display: flex;
    gap: 5px;
    align-items: center;
}

.section-titre-edit-btn {
    background: #007bff;
    color: white;
    /* ... styles de l'icône crayon */
    opacity: 0.7; /* Visible au survol */
}

.section-titre-edit-input {
    /* Styles pour le champ d'édition */
}

.section-titre-save-btn {
    background: #28a745; /* Vert pour valider */
}

.section-titre-cancel-btn {
    background: #6c757d; /* Gris pour annuler */
}
```

### 4. **js/colisage.js** ✅
**Modification des commentaires**

```javascript
// ANCIEN commentaire :
// - Édition des titres de cartes (product_type = 9)

// NOUVEAU commentaire :
// - Édition des titres de cartes (Service ID=361)
```

Le code JavaScript reste identique car il ne vérifie pas le type de produit - il travaille avec la structure de données fournie par PHP.

## 🔧 Installation

### Étape 1 : Remplacer les fichiers
Tous les fichiers ont été créés/modifiés et sont prêts. Il suffit de :

1. **colisage_tab.php** : ✅ Remplacé avec détection du service ID=361
2. **ajax/save_card_title.php** : ✅ Aucune modification nécessaire
3. **css/colisage.css** : ✅ Aucune modification nécessaire
4. **js/colisage.js** : ✅ Commentaires mis à jour

### Étape 2 : Configurer le service ID=361

**IMPORTANT :** Vous devez avoir un service avec l'ID=361 dans votre base Dolibarr :

1. Vérifiez dans votre base de données :
```sql
SELECT rowid, ref, label, product_type 
FROM llx_product 
WHERE rowid = 361;
```

2. Ce service doit avoir `product_type = 1` (service)

3. Si le service n'existe pas, créez-le dans Dolibarr :
   - Menu Produits/Services
   - Nouveau service
   - Utilisez-le comme marqueur de titre dans vos commandes

### Étape 3 : Vérifier l'extrafield

L'extrafield `ref_chantier` doit exister dans `commandedet_extrafields` avec :
- **Type** : varchar(255) - "1 ligne de texte"
- **Champ** : `ref_chantier`

## 🎮 Utilisation

### Préparation des Commandes

1. **Créer une commande**
2. **Ajouter le service ID=361** où vous voulez un titre de section
3. Le `label` de la ligne servira de titre par défaut
4. Vous pouvez ensuite personnaliser ce titre via l'interface

### Édition des Titres

1. **Accéder au module Colisage** pour une commande
2. **Localiser une carte** (bloc violet 📋)
3. **Survoler le titre** → l'icône ✏️ apparaît
4. **Cliquer sur ✏️** → le titre devient éditable
5. **Modifier le texte**
6. **Cliquer sur ✓** pour sauvegarder ou **✗** pour annuler

### Exemple Visuel

```
COMMANDE AVEC SERVICE ID=361 :
┌─────────────────────────────────────┐
│ Service ID=361 : Chantier Nord      │ ← Ligne de commande
└─────────────────────────────────────┘

AFFICHAGE DANS COLISAGE :
┌─────────────────────────────────────┐
│ 📋  Chantier Nord            [✏️]   │ ← Carte titre éditable
└─────────────────────────────────────┘

APRÈS ÉDITION :
┌─────────────────────────────────────┐
│ 📋  Chantier Nord - Phase 1  [✏️]   │ ← Titre personnalisé
└─────────────────────────────────────┘
```

## 🔍 Comportement

### Priorité d'Affichage
1. **Si `ref_chantier` existe et n'est pas vide** → Affichage de `ref_chantier`
2. **Sinon** → Affichage du `label` de la ligne

### Persistance
- Le titre personnalisé est sauvegardé dans la base de données
- Il est conservé entre les sessions
- Il est réutilisé à chaque chargement du module

### Permissions
- Nécessite le droit `commande/creer` pour modifier
- Les utilisateurs sans permission ne voient pas l'icône ✏️

## 🐛 Debug

Si les titres ne s'affichent pas :

1. **Vérifier que le service ID=361 existe** :
```sql
SELECT * FROM llx_product WHERE rowid = 361;
```

2. **Vérifier les lignes de commande** :
```sql
SELECT cd.rowid, cd.fk_product, cd.product_type, cd.label, cde.ref_chantier
FROM llx_commandedet cd
LEFT JOIN llx_commandedet_extrafields cde ON cd.rowid = cde.fk_object
WHERE cd.fk_commande = [ID_COMMANDE];
```

3. **Activer le mode debug** : Ajouter `?debug=1` à l'URL

4. **Vérifier la console JavaScript** (F12)

### Tests Rapides

```javascript
// Dans la console (F12)
console.log(window.colisageData.sections);
// Doit afficher les sections avec rowid, titre, ref_chantier

// Tester la fonction d'édition
startEditCardTitle(0); // Éditer la première section
```

## 📝 Notes Techniques

### Différences avec l'Ancien Système

**ANCIEN (product_type=9)** :
- Utilisait un type de produit spécial (type 9)
- Nécessitait un module complémentaire
- Type 9 n'est pas standard dans Dolibarr

**NOUVEAU (Service ID=361)** :
- Utilise un service standard (type 1)
- Aucun module complémentaire nécessaire
- Plus conforme à l'architecture Dolibarr

### Structure des Données
```javascript
window.colisageData.sections = [
    {
        titre: "Chantier ABC 2025",           // Titre affiché
        titre_original: "Service Titre",      // Titre original
        ref_chantier: "Chantier ABC 2025",    // Extrafield
        rowid: 12345,                         // ID ligne commande
        rang: 1,
        produits: ["prod_67890", ...]
    },
    // ...
]
```

### Requête SQL pour Identifier les Titres
```php
// Dans colisage_tab.php
if ($line->fk_product == 361 && $line->product_type == 1) {
    // C'est un titre de section
}
```

### Appel AJAX (inchangé)
```javascript
POST /colisage/ajax/save_card_title.php
Body:
  action=save_card_title
  token=xyz
  rowid=12345
  new_title=Mon Nouveau Titre

Response:
{
  "success": true,
  "data": {
    "rowid": 12345,
    "new_title": "Mon Nouveau Titre",
    "message": "Titre sauvegardé avec succès"
  }
}
```

## ✅ Checklist de Déploiement

- [x] `colisage_tab.php` modifié pour détecter `fk_product=361`
- [x] `js/colisage.js` commentaires mis à jour
- [ ] Service ID=361 créé dans Dolibarr (product_type=1)
- [ ] Extrafield `ref_chantier` configuré pour commandedet
- [ ] Test : Ajouter le service ID=361 dans une commande
- [ ] Test : Vérifier l'affichage dans le module Colisage
- [ ] Test : Éditer un titre et vérifier la sauvegarde
- [ ] Vérification des permissions utilisateur
- [ ] Test avec différentes commandes

## 🚀 Migration depuis l'Ancien Système

Si vous aviez des commandes utilisant `product_type=9` :

1. **Identifier les lignes concernées** :
```sql
SELECT cd.rowid, cd.fk_commande, cd.label, cd.product_type
FROM llx_commandedet cd
WHERE cd.product_type = 9;
```

2. **Options de migration** :
   - **Option A** : Créer le service ID=361 et ajouter de nouvelles lignes
   - **Option B** : Conserver l'ancien comportement en parallèle (modifier le code pour accepter les deux conditions)

3. **Code pour accepter les deux systèmes** (si nécessaire) :
```php
// Dans colisage_tab.php, ligne 154
if (($line->fk_product == 361 && $line->product_type == 1) || $line->product_type == 9) {
    // Accepter à la fois l'ancien et le nouveau système
}
```

## 🔄 Prochaines Évolutions Possibles

1. **Historique des modifications** : Conserver un log des changements de titres
2. **Suggestion automatique** : Proposer des noms basés sur l'historique
3. **Édition en masse** : Modifier plusieurs titres d'un coup
4. **Templates** : Créer des modèles de noms réutilisables
5. **Synchronisation** : Propager le changement à d'autres modules

---

**Version** : 2.0 (Service ID=361)  
**Date** : 15/11/2025  
**Auteur** : Patrice GOURMELEN  
**Module** : Colisage pour Dolibarr  
**Changement majeur** : Migration de product_type=9 vers Service ID=361
