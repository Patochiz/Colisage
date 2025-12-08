# 💿 Guide d'Installation - Module Colisage Optimisé

**Version Optimisée - Octobre 2025**

---

## 📋 Table des Matières

1. [Prérequis](#prérequis)
2. [Installation Nouvelle](#installation-nouvelle)
3. [Mise à Jour depuis Version Classique](#mise-à-jour-depuis-version-classique)
4. [Vérification](#vérification)
5. [Configuration](#configuration)
6. [Dépannage](#dépannage)

---

## ✅ Prérequis

### Système Requis

```
Dolibarr : Version 19.0+
PHP      : Version 7.1+
MySQL    : Version 5.6+ ou MariaDB 10.0+
```

### Modules Dolibarr Requis

- ✅ **Commandes clients** : OBLIGATOIRE
- ✅ **Produits/Services** : OBLIGATOIRE
- 🔲 **Module détailproduit** : optionnel (recommandé)

### Permissions Requises

```
Lecture/écriture sur :
- /custom/colisage/
- Base de données Dolibarr
```

### Navigateurs Supportés

```
✅ Chrome/Edge 90+
✅ Firefox 88+
✅ Safari 14+
✅ Mobile : Chrome Mobile, Safari iOS
```

---

## 🆕 Installation Nouvelle

### Étape 1 : Copier les Fichiers

#### Méthode 1 : Manuel

```bash
# Aller dans le dossier custom de Dolibarr
cd /var/www/dolibarr/htdocs/custom/

# Copier le dossier colisage
cp -r /path/to/source/colisage ./

# Vérifier les permissions
chown -R www-data:www-data colisage/
chmod -R 755 colisage/
```

#### Méthode 2 : Via FTP

```
1. Se connecter en FTP au serveur
2. Naviguer vers htdocs/custom/
3. Uploader le dossier colisage/
4. Vérifier que la structure est correcte
```

### Étape 2 : Vérifier la Structure

```
custom/colisage/
├── admin/
│   ├── about.php
│   └── setup.php
├── ajax/
│   └── save_colisage.php
├── class/
│   ├── colisageitem.class.php
│   └── colisagepackage.class.php
├── core/
│   └── modules/
│       └── modColisage.class.php
├── css/
│   └── colisage.css
├── img/
├── js/
│   └── colisage.js
├── langs/
│   ├── en_US/
│   └── fr_FR/
├── lib/
│   └── colisage.lib.php
├── sql/
│   └── dolibarr_allversions.sql
├── colisage_tab.php
└── README.md
```

### Étape 3 : Activer le Module

```
1. Connexion à Dolibarr en tant qu'administrateur
2. Aller dans Accueil → Configuration → Modules/Applications
3. Chercher "Colisage" dans la liste
4. Cliquer sur "Activer"
5. Attendre le message de confirmation
```

**Que se passe-t-il lors de l'activation ?**

```
✅ Création des tables SQL (llx_colisage_packages, llx_colisage_items)
✅ Création des index pour optimisation
✅ Création des extrafields (total_de_colis, listecolis_fp)
✅ Ajout de l'onglet Colisage dans les commandes
```

### Étape 4 : Vérifier l'Installation

```
1. Ouvrir une commande client existante
2. Vérifier la présence de l'onglet "Colisage"
3. Cliquer sur l'onglet
4. L'interface doit s'afficher avec :
   - Bandeau bleu informatif en haut
   - Récapitulatif des produits à gauche
   - Zone d'édition et liste des colis à droite
```

### Étape 5 : Vider le Cache

```bash
# Cache navigateur
Ctrl+F5 dans le navigateur

# Cache serveur (si activé)
# Apache
service apache2 reload

# Nginx
service nginx reload
```

---

## 🔄 Mise à Jour depuis Version Classique

### Avant de Commencer

**⚠️ IMPORTANT : Faire un backup !**

```bash
# Backup des fichiers
cd /var/www/dolibarr/htdocs/custom/
tar -czf colisage_backup_$(date +%Y%m%d).tar.gz colisage/

# Backup de la base de données
mysqldump -u root -p dolibarr_db llx_colisage_packages llx_colisage_items > colisage_db_backup.sql
```

### Étape 1 : Identifier les Fichiers à Remplacer

**Fichiers modifiés dans la version optimisée :**

```
✏️ js/colisage.js           (MODIFIÉ - nouvelles fonctions)
✏️ css/colisage.css         (MODIFIÉ - nouveaux styles)
✏️ colisage_tab.php         (MODIFIÉ - interface améliorée)
```

**Nouveaux fichiers de documentation :**

```
📄 README_AMELIORATIONS.md
📄 GUIDE_DEMARRAGE_RAPIDE.md
📄 COMPARAISON_AVANT_APRES.md
📄 DOCUMENTATION_TECHNIQUE.md
📄 ChangeLog.md
📄 RESUME_MODIFICATIONS.md
📄 INSTALL.md (ce fichier)
```

### Étape 2 : Remplacer les Fichiers

```bash
cd /var/www/dolibarr/htdocs/custom/colisage/

# Backup des fichiers actuels
cp js/colisage.js js/colisage.js.backup_$(date +%Y%m%d)
cp css/colisage.css css/colisage.css.backup_$(date +%Y%m%d)
cp colisage_tab.php colisage_tab.php.backup_$(date +%Y%m%d)

# Copier les nouveaux fichiers
cp /path/to/new/colisage.js js/
cp /path/to/new/colisage.css css/
cp /path/to/new/colisage_tab.php ./

# Copier la documentation
cp /path/to/new/*.md ./

# Vérifier les permissions
chmod 644 js/colisage.js
chmod 644 css/colisage.css
chmod 644 colisage_tab.php
chmod 644 *.md
```

### Étape 3 : Vérifier la Compatibilité

**Aucune modification de base de données nécessaire !**

```
✅ Les tables SQL restent identiques
✅ Les données existantes sont 100% compatibles
✅ Les colis créés avec l'ancienne version fonctionnent
```

### Étape 4 : Tester

```
1. Vider le cache navigateur (Ctrl+F5)
2. Ouvrir une commande avec des colis existants
3. Vérifier que les colis s'affichent correctement
4. Tester la création d'un nouveau colis
5. Vérifier les nouvelles fonctionnalités :
   - Boutons ➕ dans le récapitulatif
   - Boutons ×4, ×6, ×8, Max
   - Auto-sauvegarde
```

### Étape 5 : Rollback si Problème

```bash
# Si un problème survient, restaurer les fichiers
cd /var/www/dolibarr/htdocs/custom/colisage/

cp js/colisage.js.backup_$(date +%Y%m%d) js/colisage.js
cp css/colisage.css.backup_$(date +%Y%m%d) css/colisage.css
cp colisage_tab.php.backup_$(date +%Y%m%d) colisage_tab.php

# Vider le cache
# → Demander aux utilisateurs de faire Ctrl+F5
```

---

## 🔍 Vérification

### Checklist Post-Installation

```
Interface
  [ ] Bandeau bleu informatif visible
  [ ] Bouton ❓ Aide présent
  [ ] Récapitulatif des produits à gauche
  [ ] Éditeur de colis à droite
  [ ] Liste des colis en bas à droite

Boutons de Sélection Rapide
  [ ] Boutons ➕ verts dans le récapitulatif
  [ ] Boutons visibles seulement si stock disponible
  [ ] Clic sur ➕ sélectionne le produit

Boutons de Quantité Rapide
  [ ] Boutons cachés avant sélection
  [ ] Boutons ×4, ×6, ×8, Max visibles après sélection
  [ ] Clic définit la bonne quantité

Auto-sauvegarde
  [ ] Message "💾" apparaît après modification
  [ ] Pas besoin de clic sur "Enregistrer"
  [ ] Données sauvées dans la base

Fonctionnalités Existantes
  [ ] Création de colis standard
  [ ] Création de colis libre
  [ ] Modification du multiplicateur
  [ ] Suppression d'articles
  [ ] Suppression de colis
  [ ] Calculs de poids et surface corrects
```

### Test Complet

**Scénario de test :**

```
1. Créer une nouvelle commande client
2. Ajouter 2-3 produits avec détails
3. Aller sur l'onglet Colisage
4. Créer un colis standard
5. Utiliser le bouton ➕ pour sélectionner
6. Utiliser un bouton de quantité (×4, ×6, ×8)
7. Ajouter l'article
8. Vérifier l'auto-sauvegarde (message 💾)
9. Recharger la page (F5)
10. Vérifier que le colis est bien sauvé

✅ Si tout fonctionne, installation OK !
```

### Vérification Technique

```bash
# Vérifier les tables SQL
mysql -u root -p dolibarr_db
mysql> SHOW TABLES LIKE '%colisage%';

# Résultat attendu :
# llx_colisage_packages
# llx_colisage_items

# Vérifier les fichiers
ls -la /var/www/dolibarr/htdocs/custom/colisage/js/
ls -la /var/www/dolibarr/htdocs/custom/colisage/css/

# Vérifier les permissions
stat -c '%a %n' /var/www/dolibarr/htdocs/custom/colisage/
# Doit afficher : 755
```

---

## ⚙️ Configuration

### Configuration Dolibarr

**Aucune configuration spécifique requise !**

Le module fonctionne avec les paramètres par défaut.

### Configuration Optionnelle

#### Extrafields (Recommandé)

```
1. Aller dans Configuration → Attributs supplémentaires
2. Sélectionner "Commandes"
3. Ajouter/vérifier :
   - total_de_colis (type: int, position: 100)
   - listecolis_fp (type: html, position: 101)
4. Sélectionner "Lignes de commandes"
5. Ajouter/vérifier :
   - detailjson (type: text) [si module détailproduit]
```

#### Permissions Utilisateurs

```
1. Aller dans Configuration → Utilisateurs & Groupes
2. Éditer un utilisateur ou groupe
3. Vérifier les permissions :
   - ✅ Lecture sur Commandes clients
   - ✅ (Optionnel) Écriture sur Commandes clients
```

### Configuration Avancée

#### Mode Debug

Pour activer le mode debug permanent (admins seulement) :

```php
// Dans htdocs/conf/conf.php (NE PAS FAIRE EN PRODUCTION)
$conf->global->COLISAGE_DEBUG_MODE = 1;
```

#### Personnalisation CSS

Pour personnaliser les couleurs/styles :

```css
/* Dans custom/colisage/css/colisage_custom.css */

/* Boutons de sélection rapide */
.quick-select-btn {
    background: #your-color !important;
}

/* Boutons de quantité */
.quick-qty-btn {
    background: #your-color !important;
}
```

Puis inclure dans `colisage_tab.php` :

```php
print '<link rel="stylesheet" href="'.dol_buildpath('/colisage/css/colisage_custom.css', 1).'">';
```

---

## 🐛 Dépannage

### Problème : Module non visible dans la liste

**Solution :**

```bash
# Vérifier les permissions
ls -la /var/www/dolibarr/htdocs/custom/colisage/core/modules/

# Le fichier modColisage.class.php doit exister et être lisible
chmod 644 /var/www/dolibarr/htdocs/custom/colisage/core/modules/modColisage.class.php
```

### Problème : Erreur lors de l'activation

**Cause possible :** Tables SQL déjà existantes

**Solution :**

```sql
-- Vérifier si les tables existent
SHOW TABLES LIKE '%colisage%';

-- Si elles existent et sont vides, les supprimer
DROP TABLE IF EXISTS llx_colisage_items;
DROP TABLE IF EXISTS llx_colisage_packages;

-- Réactiver le module
```

### Problème : Onglet Colisage non visible

**Vérifications :**

```
1. Module Colisage activé ?
   → Configuration → Modules → Chercher "Colisage"

2. Commande client ouverte ?
   → L'onglet n'apparaît que dans les commandes

3. Permissions utilisateur ?
   → L'utilisateur doit avoir accès aux commandes
```

### Problème : Boutons ➕ non visibles

**Causes possibles :**

```
1. Cache navigateur
   → Faire Ctrl+F5

2. Fichier CSS non chargé
   → F12 → Onglet Network → Vérifier colisage.css

3. Aucun colis sélectionné
   → Créer ou sélectionner un colis d'abord

4. Colis libre sélectionné
   → Les boutons ➕ ne fonctionnent que pour les colis standard
```

### Problème : Auto-sauvegarde ne fonctionne pas

**Vérifications :**

```
1. Console JavaScript (F12)
   → Regarder les erreurs

2. Fichier JS chargé ?
   → F12 → Sources → Vérifier colisage.js

3. Token valide ?
   → Recharger la page

4. Connexion réseau ?
   → F12 → Network → Voir les requêtes AJAX
```

### Problème : Styles CSS non appliqués

**Solutions :**

```bash
# 1. Vider le cache
Ctrl+F5

# 2. Vérifier le fichier CSS
ls -la /var/www/dolibarr/htdocs/custom/colisage/css/colisage.css

# 3. Vérifier que le CSS est bien chargé
# F12 → Sources → colisage.css doit être présent

# 4. Vérifier les permissions
chmod 644 /var/www/dolibarr/htdocs/custom/colisage/css/colisage.css
```

### Problème : Erreur 500

**Diagnostic :**

```bash
# Logs Apache
tail -f /var/log/apache2/error.log

# Logs Nginx
tail -f /var/log/nginx/error.log

# Logs PHP
tail -f /var/log/php7.x-fpm.log
```

**Causes fréquentes :**

```
- Erreur PHP dans le code
- Permissions incorrectes
- Fichier manquant
- Conflit avec un autre module
```

---

## 📞 Support

### Obtenir de l'Aide

**Avant de contacter le support :**

1. Consulter cette documentation
2. Vérifier les logs (F12 → Console)
3. Tester en mode debug (`&debug=1`)
4. Vérifier que tout est à jour (Ctrl+F5)

**Pour contacter le support :**

```
Email : pgourmelen@diamant-industrie.com
Sujet : [Colisage] Votre problème

Informations à fournir :
- Version Dolibarr
- Version du module
- Navigateur et version
- Description du problème
- Étapes pour reproduire
- Logs de la console (F12)
- Captures d'écran si pertinent
```

---

## ✅ Checklist Finale

```
Installation
  [ ] Module copié dans custom/colisage/
  [ ] Permissions correctes (755 dossiers, 644 fichiers)
  [ ] Module activé dans Dolibarr
  [ ] Tables SQL créées
  [ ] Extrafields créés

Vérification
  [ ] Onglet Colisage visible dans commandes
  [ ] Interface s'affiche correctement
  [ ] Boutons ➕ visibles dans récapitulatif
  [ ] Boutons ×4, ×6, ×8, Max présents
  [ ] Auto-sauvegarde fonctionne
  [ ] Test complet réussi

Documentation
  [ ] README.md lu
  [ ] GUIDE_DEMARRAGE_RAPIDE.md consulté
  [ ] Utilisateurs informés des nouvelles fonctionnalités

Configuration
  [ ] Extrafields configurés (optionnel)
  [ ] Permissions utilisateurs vérifiées
  [ ] Backup effectué (pour mise à jour)

✅ TOUT EST OK - Profitez du module optimisé ! 🚀
```

---

**Fin du guide d'installation**

Pour toute question : pgourmelen@diamant-industrie.com
