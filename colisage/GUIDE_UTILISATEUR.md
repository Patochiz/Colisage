# 📦 Guide Utilisateur — Module Colisage

Ce guide s'adresse aux **utilisateurs finaux** (préparateurs, opérateurs de colisage, gestionnaires de commandes). Il explique comment utiliser le module Colisage au quotidien, depuis l'onglet d'une commande client Dolibarr.

> 💡 Pour l'installation et l'activation du module, voir [INSTALL.md](INSTALL.md). Pour une vue d'ensemble technique, voir [README.md](README.md).

---

## 🎯 À quoi sert ce module ?

Le module **Colisage** vous permet, à partir d'une **commande client**, de préparer et décrire les **colis physiques** qui vont être expédiés :

- Répartir les produits de la commande dans un ou plusieurs colis
- Créer autant de colis identiques que nécessaire (multiplicateur)
- Ajouter des **colis libres** pour des articles hors commande (emballage, calage, accessoires)
- Personnaliser les **titres de sections** qui regroupent visuellement les produits
- Calculer automatiquement le **poids** et la **surface** de chaque colis
- Enregistrer tout cela en base, et l'exporter au format HTML sur la fiche commande

Tout se passe dans **un seul onglet** accessible depuis la commande, sans quitter Dolibarr.

---

## 🚪 Accéder au module

1. Ouvrir une **commande client** dans Dolibarr
2. Cliquer sur l'onglet **« Colisage »** dans la barre d'onglets de la commande
3. La page de colisage s'affiche

Vous arrivez sur une page dédiée qui comporte, de haut en bas :

- Un bandeau de navigation avec un lien **« ← Retour à la commande »**
- Un bandeau d'aide bleu rappelant les nouvelles fonctionnalités (💡)
- L'en-tête avec la référence de la commande
- La zone principale en **deux colonnes**

---

## 🗺️ Vue d'ensemble de l'interface

### Colonne de gauche — Récapitulatif des Produits

Liste tous les produits de la commande, regroupés par **sections** (titres de cartes).
Pour chaque produit et chaque détail, un bouton **➕** vert permet de l'ajouter directement au colis en cours.

### Colonne de droite — Éditeur de colis et Colis Créés

- **Éditeur de colis** : affiche le colis actuellement sélectionné, ses articles, son multiplicateur, son poids et sa surface. Tant qu'aucun colis n'est sélectionné, un message « Aucun colis sélectionné » est visible.
- **Colis Créés** : la liste de tous les colis déjà ajoutés. Cliquer sur un colis dans cette liste permet de l'ouvrir dans l'éditeur.

### Boutons d'action (sous l'éditeur)

| Bouton | Rôle |
|---|---|
| **+ Colis standard** | Créer un nouveau colis basé sur les produits de la commande (désactivé lorsqu'il n'y a plus de produits disponibles à ajouter) |
| **+ Colis libre** | Créer un colis contenant des articles personnalisés, non liés à une ligne de commande |
| **Annuler** | Revenir à la page précédente |
| **💾 Sauvegarder** | Sauvegarde manuelle — rarement utile car l'**auto-sauvegarde** est activée |
| **📦 Générer fichiers EBS** | Télécharger les fichiers `.prj`/`.prv` pour l'imprimante d'étiquettes EBS |

### Lexique rapide

- **Colis standard** : un colis qui contient des articles provenant directement des lignes de la commande.
- **Colis libre** : un colis décrit librement (longueur, largeur, description, quantité) sans lien avec la commande.
- **Multiplicateur** : permet de dire « ce colis est en fait X colis identiques ». Les totaux affichés sont automatiquement multipliés.
- **Article** : une ligne à l'intérieur d'un colis (quantité, dimensions, description, unités de poids et de surface).
- **Section / Titre de carte** : un bandeau violet 📋 qui regroupe plusieurs produits. Il provient d'une ligne de commande de type **service** utilisée comme séparateur.

---

## ⚡ Workflow rapide — Créer un colis en 4 clics

C'est la méthode **recommandée** pour la plupart des situations.

1. **Clic 1** — Cliquer sur **« + Colis standard »**
2. **Clic 2** — Dans le récapitulatif de gauche, cliquer sur le bouton **➕** en face du produit (ou du détail) à inclure
3. **Clic 3** — Choisir la quantité avec les boutons rapides **×4**, **×6**, **×8** ou **Max**
4. **Clic 4** — Cliquer sur **« + »** pour ajouter l'article au colis

✅ **C'est tout.** L'**auto-sauvegarde** enregistre la modification en arrière-plan (environ une demi-seconde après votre dernière action) : aucun clic sur « Sauvegarder » n'est nécessaire.

> Exemple : pour un colis contenant 8 pièces d'un produit, cliquez simplement « + Colis standard » → ➕ → ×8 → « + ». Terminé.

---

## 🧰 Workflow classique — Saisie manuelle

Disponible en permanence si vous avez besoin d'une quantité précise ou d'un produit qui n'apparaît pas dans le récapitulatif rapide.

1. Cliquer sur **« + Colis standard »**
2. Dans l'éditeur, sélectionner le **produit** dans le premier menu déroulant
3. Sélectionner le **détail** (dimension/variante) dans le second menu
4. Saisir la **quantité** au clavier (ou utiliser ×4/×6/×8/Max)
5. Cliquer sur **« + »** pour ajouter l'article
6. Répéter pour chaque article à inclure dans ce colis

---

## 🔢 Créer plusieurs colis identiques (multiplicateur)

Si vous préparez par exemple **10 colis identiques** contenant chacun 4 pièces du même produit :

1. Créer **un seul** colis avec son contenu (les 4 pièces)
2. En haut de l'éditeur, modifier la valeur du **multiplicateur** (passer de `1` à `10`)
3. Les totaux (poids, surface, nombre d'articles expédiés) se recalculent automatiquement
4. L'auto-sauvegarde enregistre la modification

> C'est beaucoup plus rapide que de dupliquer un colis 10 fois à la main, et plus lisible dans le récapitulatif.

---

## 🆓 Créer un colis libre

Un **colis libre** sert à ajouter tout ce qui **n'est pas une ligne de commande** : matériel d'emballage, calage, documentation, pièces de rechange jointes, etc.

1. Cliquer sur **« + Colis libre »**
2. Dans l'éditeur, renseigner pour chaque article :
   - **Description** — texte libre (ex. « Carton de calage »)
   - **Quantité**
   - **Longueur** et **Largeur** si elles sont pertinentes
   - **Unité de poids** (T, KG, G, MG) et **unité de surface** si applicables
3. Cliquer sur **« + »** pour ajouter l'article
4. Vous pouvez aussi régler un multiplicateur si ce colis libre est à créer en plusieurs exemplaires

---

## ✏️ Modifier ou supprimer

### Modifier un colis existant

- Cliquer sur le colis dans la liste **« Colis Créés »** → il s'ouvre dans l'éditeur
- Ajouter / enlever des articles, changer le multiplicateur → auto-sauvegardé

### Supprimer un article d'un colis

- Cliquer sur la **×** rouge en bout de ligne de l'article

### Supprimer un colis entier

- Cliquer sur la **×** rouge en haut à droite du colis dans l'éditeur

> ⚠️ Les suppressions sont appliquées immédiatement. Vérifiez avant de cliquer.

---

## 📋 Personnaliser les titres de sections (cartes violettes)

Certaines lignes de votre commande servent de **titres de sections** : elles s'affichent sous forme de cartes violettes 📋 dans le récapitulatif. Ce sont des lignes de type **service** utilisées comme séparateurs visuels.

Vous pouvez renommer ces titres à la volée, **sans quitter** l'interface Colisage.

### Comment éditer un titre

1. Passer la souris sur le titre → une icône **✏️** apparaît
2. Cliquer sur **✏️** → le titre devient un champ de saisie
3. Taper le nouveau texte
4. Valider avec **✓** ou annuler avec **✗**

### Raccourcis clavier

- **Entrée** : valider la modification
- **Échap** : annuler et revenir au titre précédent

### Où est stocké le nouveau titre ?

Il est enregistré dans l'extrafield `ref_chantier` de la ligne de commande. Si ce champ est rempli, c'est lui qui s'affiche en priorité ; sinon, c'est la description d'origine de la ligne.

> Pour plus de détails sur cette fonctionnalité, voir [EDITION_TITRES_CARTES.md](EDITION_TITRES_CARTES.md).

---

## 📤 Après le colisage — ce qui se passe côté commande

Une fois vos colis enregistrés, le module met à jour automatiquement la fiche commande :

- Le **nombre total de colis** est calculé et disponible sur la commande
- Un **récapitulatif HTML** (liste des colis et de leur contenu) est généré et consultable sur la fiche commande
- Les fichiers imprimante **EBS** peuvent être téléchargés à tout moment via le bouton **📦 Générer fichiers EBS**

Ces éléments sont mis à jour à chaque auto-sauvegarde : vous n'avez rien à faire manuellement.

---

## 💡 Astuces et raccourcis

- **Auto-sauvegarde** : vous n'avez (normalement) jamais besoin de cliquer sur **« 💾 Sauvegarder »**. Le bouton reste disponible comme filet de sécurité.
- **Quantités courantes** : les boutons **×4 / ×6 / ×8 / Max** couvrent la majorité des besoins et évitent la saisie clavier.
- **Enter / Échap** pour éditer un titre de section rapidement, sans toucher la souris.
- **Multiplicateur plutôt que duplication** : créez un colis type, puis montez le multiplicateur.
- **Bouton ❓ Aide** : disponible dans l'interface en cas de doute.

---

## 🆘 Dépannage

### Je ne vois pas l'onglet « Colisage » sur ma commande

Le module n'est probablement pas activé pour votre Dolibarr. Contactez votre administrateur pour qu'il active le module (voir [INSTALL.md](INSTALL.md)).

### Le bouton « + Colis standard » est grisé

Cela signifie qu'il n'y a **plus de produits disponibles** dans le récapitulatif : tous les articles de la commande sont déjà répartis dans des colis existants. Vous pouvez soit modifier un colis existant, soit créer un **colis libre**.

### Mes modifications ne sont pas enregistrées

L'auto-sauvegarde s'effectue environ une demi-seconde après votre dernière action. Si vous avez un doute :

1. Attendre une seconde que l'indicateur « Sauvegarde… » disparaisse
2. Cliquer manuellement sur **« 💾 Sauvegarder »** pour forcer l'enregistrement
3. Rafraîchir la page avec **Ctrl+F5** et vérifier que les colis sont bien présents

Si le problème persiste, prévenez votre administrateur Dolibarr.

### Le bouton ➕ n'apparaît pas à côté d'un produit

Le produit n'a pas de **détail** exploitable (pas de dimensions renseignées, pas de `detailjson`). Utilisez le workflow classique : sélection manuelle du produit, saisie de la quantité, puis ajout.

### Un titre de section refuse de se sauvegarder

Vérifiez que vous disposez du droit de modification sur la commande. Si le problème persiste, notez la référence de la commande et contactez votre administrateur.

### Pour tout autre problème

Contactez votre **administrateur Dolibarr**. Le mode debug technique est réservé aux administrateurs et n'est pas destiné à un usage quotidien.

---

## 📚 Pour aller plus loin

- [README.md](README.md) — vue d'ensemble du module, historique des améliorations
- [INSTALL.md](INSTALL.md) — guide d'installation (administrateurs)
- [EDITION_TITRES_CARTES.md](EDITION_TITRES_CARTES.md) — détail de la fonctionnalité d'édition des titres
- [ChangeLog.md](ChangeLog.md) — historique des versions

---

**Bon colisage ! 📦✨**
