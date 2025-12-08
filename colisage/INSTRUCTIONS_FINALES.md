# 🚀 INSTRUCTIONS FINALES - Édition des Titres

## ✅ Ce qui a été fait

J'ai créé/modifié les fichiers suivants dans votre module Colisage :

### Fichiers Créés ✨
1. **ajax/save_card_title.php** - Endpoint AJAX pour sauvegarder les titres
2. **js/colisage_edit_titles.js** - Code JavaScript pour l'édition
3. **EDITION_TITRES_CARTES.md** - Documentation complète
4. **INSTRUCTIONS_FINALES.md** - Ce fichier

### Fichiers Modifiés 📝
1. **colisage_tab.php** - Ajout récupération `ref_chantier` et `rowid`
2. **css/colisage.css** - Ajout styles pour édition

## ⚠️ ACTION REQUISE : Intégration JavaScript

Vous devez maintenant **modifier manuellement** votre fichier `js/colisage.js`.

### Option 1 : Modification Manuelle (Recommandée)

1. **Ouvrez** `js/colisage.js`

2. **Trouvez** la fonction `renderProductsGroup` (ligne ~428 environ) qui commence par :
```javascript
function renderProductsGroup(productIds, titre = null, description = null, sectionIndex = null) {
```

3. **Remplacez** toute la fonction par celle dans `js/colisage_edit_titles.js` (lignes 12-116)

4. **Ajoutez** à la fin du fichier (après les `window.xxx = xxx;`) les 3 nouvelles fonctions :
   - `window.startEditCardTitle` (lignes 118-158)
   - `window.saveCardTitle` (lignes 160-217)
   - `window.cancelEditCardTitle` (lignes 219-226)

### Option 2 : Inclusion Séparée (Plus Simple)

Modifiez `colisage_tab.php` et ajoutez APRÈS la ligne qui charge `colisage.js` :

```php
<?php
// Inclure le JavaScript principal (version corrigée)
$jsFile = dol_buildpath('/colisage/js/colisage.js', 1);
print '<script type="text/javascript" src="'.$jsFile.'?v='.time().'"></script>';

// AJOUTEZ CES 3 LIGNES :
$jsEditFile = dol_buildpath('/colisage/js/colisage_edit_titles.js', 1);
print '<script type="text/javascript" src="'.$jsEditFile.'?v='.time().'"></script>';
// FIN DE L'AJOUT
```

**⚠️ IMPORTANT avec Option 2 :** Vous devrez quand même remplacer la fonction `renderProductsGroup` dans `colisage.js` par la version du fichier `colisage_edit_titles.js`.

## 🧪 Test de Fonctionnement

1. **Accédez** à une commande avec des titres de section (product_type = 9)

2. **Allez** dans le module Colisage pour cette commande

3. **Vérifiez** dans la console (F12) :
```javascript
// Doit afficher les sections avec rowid, titre, ref_chantier
console.log(window.colisageData.sections);
```

4. **Survolez** un titre de carte (bloc violet 📋) → icône ✏️ doit apparaître

5. **Cliquez** sur ✏️ → le titre doit devenir éditable

6. **Modifiez** le texte et cliquez sur ✓

7. **Vérifiez** dans la base de données :
```sql
SELECT fk_object, ref_chantier 
FROM llx_commandedet_extrafields 
WHERE fk_object = [rowid de la ligne];
```

## 🔧 Dépannage Rapide

### L'icône ✏️ n'apparaît pas
- Vérifiez que `colisage_edit_titles.js` est bien chargé (onglet Network en F12)
- Vérifiez que la fonction `renderProductsGroup` a été remplacée
- Vérifiez la console pour des erreurs JavaScript

### Le titre ne se sauvegarde pas
- Vérifiez les permissions de l'utilisateur (`commande/creer`)
- Vérifiez dans l'onglet Network (F12) la requête à `save_card_title.php`
- Vérifiez que l'extrafield `ref_chantier` existe bien dans la table `llx_commandedet_extrafields`

### Erreur "rowid manquant"
- Vérifiez que `colisage_tab.php` a bien été remplacé
- Vérifiez dans la console : `console.log(window.colisageData.sections[0].rowid)`

## 📋 Checklist Finale

Avant de déployer en production :

- [ ] Fonction `renderProductsGroup` remplacée dans `colisage.js` OU
- [ ] Fichier `colisage_edit_titles.js` inclus dans `colisage_tab.php`
- [ ] Test : icône ✏️ visible au survol
- [ ] Test : édition d'un titre fonctionne
- [ ] Test : sauvegarde réussit et persiste
- [ ] Test : rechargement de la page affiche le nouveau titre
- [ ] Test : annulation fonctionne
- [ ] Vérification : extrafield `ref_chantier` existe
- [ ] Vérification : permissions utilisateur correctes

## 🎯 Résultat Attendu

Une fois l'installation terminée, vous devriez pouvoir :

1. ✅ Voir une icône ✏️ au survol de chaque titre de carte
2. ✅ Cliquer dessus pour éditer le titre
3. ✅ Sauvegarder avec ✓ ou annuler avec ✗
4. ✅ Le titre personnalisé persiste entre les sessions
5. ✅ L'extrafield `ref_chantier` contient le nouveau titre

## 📞 Support

Si vous rencontrez des problèmes :

1. Consultez `EDITION_TITRES_CARTES.md` pour la documentation complète
2. Vérifiez la console JavaScript (F12) pour les erreurs
3. Vérifiez les logs PHP (Dolibarr → Outils → Logs)
4. Activez le mode debug : `?id=XXX&debug=1`

## 🎉 C'est Tout !

Une fois l'intégration JavaScript effectuée, la fonctionnalité sera pleinement opérationnelle.

Bon colisage ! 📦

---

**Important** : N'oubliez pas de faire une **sauvegarde** de vos fichiers avant toute modification !
