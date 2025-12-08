<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    colisage/diagnostic_colisage_complet.php
 * \ingroup colisage
 * \brief   Script de diagnostic complet pour le module Colisage
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

// Access control
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à ce diagnostic');
}

global $db, $conf, $langs, $user;
$langs->load("admin");

// Initialize
$error = 0;
$action = GETPOST('action', 'alpha');

print '<!DOCTYPE html>
<html>
<head>
<title>Diagnostic Module Colisage</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
.button { padding: 10px 15px; margin: 5px; background: #0066cc; color: white; text-decoration: none; border-radius: 3px; border: none; cursor: pointer; }
.button:hover { background: #0052a3; }
.button.danger { background: #cc0000; }
.button.danger:hover { background: #a30000; }
.code { background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 3px solid #ccc; }
.table { border-collapse: collapse; width: 100%; margin: 10px 0; }
.table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.table th { background-color: #f2f2f2; }
</style>
</head>
<body>';

print '<h1>🔧 Diagnostic Module Colisage - Complet</h1>';
print '<p><strong>Version:</strong> 1.0 | <strong>Date:</strong> '.date('Y-m-d H:i:s').'</p>';

/**
 * Actions
 */
if ($action == 'repair_tables') {
    print '<div class="section">';
    print '<h2>🔧 Réparation des tables</h2>';
    
    $tables_sql = array(
        'llx_colisage_packages' => "
            CREATE TABLE IF NOT EXISTS llx_colisage_packages (
                rowid           integer AUTO_INCREMENT PRIMARY KEY,
                fk_commande     integer NOT NULL,
                multiplier      integer DEFAULT 1,
                is_free         tinyint DEFAULT 0,
                total_weight    decimal(10,3) DEFAULT 0,
                total_surface   decimal(10,3) DEFAULT 0,
                date_creation   datetime,
                date_modification datetime,
                fk_user_creat   integer,
                fk_user_modif   integer
            ) ENGINE=innodb;",
        'llx_colisage_items' => "
            CREATE TABLE IF NOT EXISTS llx_colisage_items (
                rowid           integer AUTO_INCREMENT PRIMARY KEY,
                fk_package      integer NOT NULL,
                fk_commandedet  integer NULL,
                quantity        integer NOT NULL DEFAULT 1,
                detail_id       varchar(50) NULL,
                custom_name     varchar(255) NULL,
                custom_longueur integer NULL,
                custom_largeur  integer NULL,
                custom_description varchar(255) NULL,
                custom_weight   decimal(10,3) NULL,
                longueur        integer NOT NULL,
                largeur         integer NOT NULL,
                description     varchar(255),
                weight_unit     decimal(10,3) DEFAULT 0,
                surface_unit    decimal(10,3) DEFAULT 0,
                date_creation   datetime,
                fk_user_creat   integer
            ) ENGINE=innodb;"
    );
    
    $indexes_sql = array(
        'idx_colisage_packages_commande' => "CREATE INDEX IF NOT EXISTS idx_colisage_packages_commande ON llx_colisage_packages (fk_commande);",
        'idx_colisage_items_package' => "CREATE INDEX IF NOT EXISTS idx_colisage_items_package ON llx_colisage_items (fk_package);",
        'idx_colisage_items_commandedet' => "CREATE INDEX IF NOT EXISTS idx_colisage_items_commandedet ON llx_colisage_items (fk_commandedet);"
    );
    
    // Créer les tables
    foreach ($tables_sql as $table => $sql) {
        $resql = $db->query($sql);
        if ($resql) {
            print '<p class="success">✅ Table '.$table.' créée/vérifiée avec succès</p>';
        } else {
            print '<p class="error">❌ Erreur lors de la création de la table '.$table.': '.$db->lasterror().'</p>';
            $error++;
        }
    }
    
    // Créer les index
    foreach ($indexes_sql as $index => $sql) {
        $resql = $db->query($sql);
        if ($resql) {
            print '<p class="success">✅ Index '.$index.' créé/vérifié avec succès</p>';
        } else {
            print '<p class="error">❌ Erreur lors de la création de l\'index '.$index.': '.$db->lasterror().'</p>';
            $error++;
        }
    }
    
    if (!$error) {
        print '<p class="success"><strong>🎉 Toutes les tables et index ont été créés avec succès !</strong></p>';
    }
    print '</div>';
}

if ($action == 'repair_extrafields') {
    print '<div class="section">';
    print '<h2>🔧 Réparation des champs supplémentaires</h2>';
    
    include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($db);
    
    // Extrafield 1: total_de_colis
    $result1 = $extrafields->addExtraField(
        'total_de_colis',
        'Nombre total de colis',
        'int',
        100,
        10,
        'commande',
        1,
        0,
        0,
        array(),
        1,
        '$user->hasRight("commande", "read")',
        1,
        1,
        '',
        '',
        '',
        'Nombre total de colis générés pour cette commande (calculé automatiquement par le module Colisage)',
        '',
        $conf->entity
    );
    
    if ($result1 > 0) {
        print '<p class="success">✅ Champ supplémentaire "total_de_colis" créé avec succès</p>';
    } elseif ($result1 == 0) {
        print '<p class="info">ℹ️ Champ supplémentaire "total_de_colis" existe déjà</p>';
    } else {
        print '<p class="error">❌ Erreur lors de la création du champ "total_de_colis": '.$extrafields->error.'</p>';
    }
    
    // Extrafield 2: listecolis_fp
    $result2 = $extrafields->addExtraField(
        'listecolis_fp',
        'Liste des colis (fiche production)',
        'html',
        101,
        65535,
        'commande',
        1,
        0,
        '',
        array(),
        1,
        '$user->hasRight("commande", "read")',
        0,
        1,
        '',
        '',
        '',
        'Liste détaillée des colis pour la fiche de production (générée automatiquement par le module Colisage)',
        '',
        $conf->entity
    );
    
    if ($result2 > 0) {
        print '<p class="success">✅ Champ supplémentaire "listecolis_fp" créé avec succès</p>';
    } elseif ($result2 == 0) {
        print '<p class="info">ℹ️ Champ supplémentaire "listecolis_fp" existe déjà</p>';
    } else {
        print '<p class="error">❌ Erreur lors de la création du champ "listecolis_fp": '.$extrafields->error.'</p>';
    }
    
    print '<p class="success"><strong>🎉 Vérification des champs supplémentaires terminée !</strong></p>';
    print '</div>';
}

if ($action == 'cleanup_module') {
    print '<div class="section">';
    print '<h2>🧹 Nettoyage du module</h2>';
    
    // Désactiver le module d'abord
    $sql = "UPDATE ".MAIN_DB_PREFIX."const SET value = '0' WHERE name = 'MAIN_MODULE_COLISAGE' AND entity = ".$conf->entity;
    $resql = $db->query($sql);
    if ($resql) {
        print '<p class="success">✅ Module désactivé pour nettoyage</p>';
    }
    
    // Supprimer les entrées du menu
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'colisage'";
    $resql = $db->query($sql);
    if ($resql) {
        print '<p class="success">✅ Entrées de menu supprimées</p>';
    }
    
    // Nettoyer les permissions orphelines
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."user_rights WHERE fk_id NOT IN (SELECT id FROM ".MAIN_DB_PREFIX."rights_def)";
    $resql = $db->query($sql);
    if ($resql) {
        print '<p class="success">✅ Permissions orphelines nettoyées</p>';
    }
    
    print '<p class="success"><strong>🎉 Nettoyage terminé ! Vous pouvez maintenant réactiver le module.</strong></p>';
    print '</div>';
}

/**
 * Diagnostics
 */

// 1. État du module
print '<div class="section">';
print '<h2>📊 État du Module</h2>';
$module_enabled = isModEnabled('colisage');
print '<p><strong>Module activé:</strong> '.($module_enabled ? '<span class="success">✅ OUI</span>' : '<span class="error">❌ NON</span>').'</p>';

if ($module_enabled) {
    print '<p class="info">Le module est activé. Si vous rencontrez des erreurs, utilisez le nettoyage puis réactivez-le.</p>';
} else {
    print '<p class="warning">Le module n\'est pas activé. Utilisez les outils de réparation ci-dessous avant de l\'activer.</p>';
}
print '</div>';

// 2. Vérification des fichiers essentiels
print '<div class="section">';
print '<h2>📁 Vérification des Fichiers Essentiels</h2>';

$essential_files = array(
    'core/modules/modColisage.class.php' => 'Descripteur du module',
    'class/colisagepackage.class.php' => 'Classe ColisagePackage',
    'class/colisageitem.class.php' => 'Classe ColisageItem',
    'langs/fr_FR/colisage.lang' => 'Traductions françaises',
    'langs/en_US/colisage.lang' => 'Traductions anglaises',
    'admin/setup.php' => 'Page de configuration',
    'lib/colisage.lib.php' => 'Bibliothèque du module',
    'colisage_tab.php' => 'Onglet principal',
    'ajax/save_colisage.php' => 'Script AJAX',
    'css/colisage.css' => 'Fichier CSS',
    'js/colisage.js' => 'Fichier JavaScript'
);

$missing_files = array();
foreach ($essential_files as $file => $description) {
    $full_path = DOL_DOCUMENT_ROOT.'/custom/colisage/'.$file;
    if (file_exists($full_path)) {
        print '<p class="success">✅ '.$description.' ('.$file.')</p>';
    } else {
        print '<p class="error">❌ '.$description.' ('.$file.') - MANQUANT</p>';
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    print '<p class="success"><strong>🎉 Tous les fichiers essentiels sont présents !</strong></p>';
} else {
    print '<p class="error"><strong>⚠️ '.count($missing_files).' fichier(s) manquant(s) détecté(s)</strong></p>';
}
print '</div>';

// 3. Vérification des tables de base de données
print '<div class="section">';
print '<h2>🗄️ Vérification des Tables</h2>';

$required_tables = array(
    'llx_colisage_packages' => array(
        'description' => 'Table des colis',
        'columns' => array('rowid', 'fk_commande', 'multiplier', 'is_free', 'total_weight', 'total_surface')
    ),
    'llx_colisage_items' => array(
        'description' => 'Table des articles',
        'columns' => array('rowid', 'fk_package', 'fk_commandedet', 'quantity', 'longueur', 'largeur')
    )
);

$missing_tables = array();
foreach ($required_tables as $table => $info) {
    $sql = "SHOW TABLES LIKE '".$table."'";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        print '<p class="success">✅ '.$info['description'].' ('.$table.')</p>';
        
        // Vérifier les colonnes importantes
        $sql = "SHOW COLUMNS FROM ".$table;
        $resql2 = $db->query($sql);
        $existing_columns = array();
        if ($resql2) {
            while ($obj = $db->fetch_object($resql2)) {
                $existing_columns[] = $obj->Field;
            }
        }
        
        $missing_columns = array_diff($info['columns'], $existing_columns);
        if (empty($missing_columns)) {
            print '<p class="info">&nbsp;&nbsp;→ Toutes les colonnes requises sont présentes</p>';
        } else {
            print '<p class="warning">&nbsp;&nbsp;→ Colonnes manquantes: '.implode(', ', $missing_columns).'</p>';
        }
    } else {
        print '<p class="error">❌ '.$info['description'].' ('.$table.') - TABLE MANQUANTE</p>';
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    print '<p class="success"><strong>🎉 Toutes les tables requises sont présentes !</strong></p>';
} else {
    print '<p class="error"><strong>⚠️ '.count($missing_tables).' table(s) manquante(s)</strong></p>';
}
print '</div>';

// 4. Vérification des champs supplémentaires
print '<div class="section">';
print '<h2>🔧 Vérification des Champs Supplémentaires</h2>';

$extrafields_to_check = array(
    'total_de_colis' => 'Nombre total de colis',
    'listecolis_fp' => 'Liste des colis (fiche production)'
);

include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label('commande');

$missing_extrafields = array();
foreach ($extrafields_to_check as $field => $description) {
    if (isset($extrafields->attributes['commande']['label'][$field])) {
        print '<p class="success">✅ '.$description.' ('.$field.')</p>';
    } else {
        print '<p class="error">❌ '.$description.' ('.$field.') - CHAMP MANQUANT</p>';
        $missing_extrafields[] = $field;
    }
}

if (empty($missing_extrafields)) {
    print '<p class="success"><strong>🎉 Tous les champs supplémentaires sont présents !</strong></p>';
} else {
    print '<p class="error"><strong>⚠️ '.count($missing_extrafields).' champ(s) supplémentaire(s) manquant(s)</strong></p>';
}
print '</div>';

// 5. Vérification des permissions et de la configuration
print '<div class="section">';
print '<h2>🔐 Vérification des Permissions et Configuration</h2>';

// Vérifier les droits sur les répertoires
$directories_to_check = array(
    DOL_DOCUMENT_ROOT.'/custom/colisage' => 'Répertoire principal du module',
    DOL_DATA_ROOT.'/colisage' => 'Répertoire de données (sera créé si nécessaire)'
);

foreach ($directories_to_check as $dir => $description) {
    if (is_dir($dir)) {
        $writable = is_writable($dir);
        print '<p class="'.($writable ? 'success' : 'warning').'">'.($writable ? '✅' : '⚠️').' '.$description.' ('.($writable ? 'lecture/écriture' : 'lecture seule').')</p>';
    } else {
        print '<p class="error">❌ '.$description.' - RÉPERTOIRE MANQUANT</p>';
    }
}

// Vérifier la configuration PHP
$php_requirements = array(
    'mysqli' => 'Extension MySQLi',
    'json' => 'Extension JSON',
    'mbstring' => 'Extension Multibyte String'
);

foreach ($php_requirements as $ext => $description) {
    if (extension_loaded($ext)) {
        print '<p class="success">✅ '.$description.' (activée)</p>';
    } else {
        print '<p class="error">❌ '.$description.' (manquante)</p>';
    }
}

print '</div>';

// 6. Outils de réparation
print '<div class="section">';
print '<h2>🛠️ Outils de Réparation</h2>';
print '<p>Utilisez ces outils pour corriger les problèmes détectés :</p>';

print '<a href="'.$_SERVER['PHP_SELF'].'?action=repair_tables" class="button">🔧 Réparer les Tables</a>';
print '<a href="'.$_SERVER['PHP_SELF'].'?action=repair_extrafields" class="button">🔧 Réparer les Champs Supplémentaires</a>';
print '<a href="'.$_SERVER['PHP_SELF'].'?action=cleanup_module" class="button danger">🧹 Nettoyer le Module</a>';

print '<div class="code">';
print '<h4>Instructions après réparation :</h4>';
print '<ol>';
print '<li>Utilisez "Réparer les Tables" si des tables sont manquantes</li>';
print '<li>Utilisez "Réparer les Champs Supplémentaires" si des extrafields sont manquants</li>';
print '<li>Si le module ne s\'active toujours pas, utilisez "Nettoyer le Module" puis réactivez-le via l\'interface Dolibarr</li>';
print '<li>Vérifiez les logs Apache/PHP en cas d\'erreur 500 persistante</li>';
print '</ol>';
print '</div>';
print '</div>';

// 7. Informations système
print '<div class="section">';
print '<h2>ℹ️ Informations Système</h2>';
print '<table class="table">';
print '<tr><th>Paramètre</th><th>Valeur</th></tr>';
print '<tr><td>Version Dolibarr</td><td>'.DOL_VERSION.'</td></tr>';
print '<tr><td>Version PHP</td><td>'.PHP_VERSION.'</td></tr>';
print '<tr><td>Répertoire Dolibarr</td><td>'.DOL_DOCUMENT_ROOT.'</td></tr>';
print '<tr><td>Répertoire du module</td><td>'.DOL_DOCUMENT_ROOT.'/custom/colisage</td></tr>';
print '<tr><td>Répertoire de données</td><td>'.DOL_DATA_ROOT.'</td></tr>';
print '<tr><td>Entité</td><td>'.$conf->entity.'</td></tr>';
print '<tr><td>Base de données</td><td>'.$db->database_name.' (sur '.$db->database_host.')</td></tr>';
print '</table>';
print '</div>';

print '<div class="section">';
print '<p><strong>Aide :</strong> En cas de problème persistant, consultez les logs dans <code>'.DOL_DATA_ROOT.'/dolibarr.log</code></p>';
print '<p><strong>Support :</strong> Contactez DIAMANT INDUSTRIE pour une assistance technique</p>';
print '</div>';

print '</body></html>';
?>