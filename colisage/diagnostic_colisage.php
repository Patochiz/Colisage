<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * Script de diagnostic et réparation pour le module Colisage
 */

// Try main.inc.php using relative path
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Check permissions
if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');

print '<!DOCTYPE html>';
print '<html>';
print '<head>';
print '<title>Diagnostic Module Colisage</title>';
print '<meta charset="utf-8">';
print '<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; margin: 10px 0; }
.error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; margin: 10px 0; }
.warning { color: orange; background: #fffaf0; padding: 10px; border: 1px solid orange; margin: 10px 0; }
.info { color: blue; background: #f0f0ff; padding: 10px; border: 1px solid blue; margin: 10px 0; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.btn { padding: 10px 15px; margin: 5px; background: #0066cc; color: white; text-decoration: none; border-radius: 3px; }
.btn:hover { background: #0052a3; }
.btn-danger { background: #cc0000; }
.btn-danger:hover { background: #a30000; }
</style>';
print '</head>';
print '<body>';

print '<h1>Diagnostic Module Colisage</h1>';

// Diagnostic des tables
print '<h2>1. Vérification des tables</h2>';

$tables_required = [
    'llx_colisage_packages' => [
        'rowid' => 'int(11) NOT NULL AUTO_INCREMENT',
        'fk_commande' => 'int(11) NOT NULL',
        'multiplier' => 'int(11) DEFAULT 1',
        'is_free' => 'tinyint(1) DEFAULT 0',
        'total_weight' => 'decimal(10,3) DEFAULT 0.000',
        'total_surface' => 'decimal(10,3) DEFAULT 0.000',
        'date_creation' => 'datetime DEFAULT NULL',
        'date_modification' => 'datetime DEFAULT NULL',
        'fk_user_creat' => 'int(11) DEFAULT NULL',
        'fk_user_modif' => 'int(11) DEFAULT NULL'
    ],
    'llx_colisage_items' => [
        'rowid' => 'int(11) NOT NULL AUTO_INCREMENT',
        'fk_package' => 'int(11) NOT NULL',
        'fk_commandedet' => 'int(11) DEFAULT NULL',
        'quantity' => 'int(11) NOT NULL DEFAULT 1',
        'detail_id' => 'varchar(50) DEFAULT NULL',
        'custom_name' => 'varchar(255) DEFAULT NULL',
        'custom_longueur' => 'int(11) DEFAULT NULL',
        'custom_largeur' => 'int(11) DEFAULT NULL',
        'custom_description' => 'varchar(255) DEFAULT NULL',
        'custom_weight' => 'decimal(10,3) DEFAULT NULL',
        'longueur' => 'int(11) NOT NULL',
        'largeur' => 'int(11) NOT NULL',
        'description' => 'varchar(255) DEFAULT NULL',
        'weight_unit' => 'decimal(10,3) DEFAULT 0.000',
        'surface_unit' => 'decimal(10,3) DEFAULT 0.000',
        'date_creation' => 'datetime DEFAULT NULL',
        'fk_user_creat' => 'int(11) DEFAULT NULL'
    ]
];

$all_tables_ok = true;

foreach ($tables_required as $table_name => $columns) {
    print "<h3>Table: $table_name</h3>";
    
    // Vérifier si la table existe
    $sql = "SHOW TABLES LIKE '".$table_name."'";
    $resql = $db->query($sql);
    
    if ($resql && $db->num_rows($resql) > 0) {
        print "<div class='success'>✓ Table $table_name existe</div>";
        
        // Vérifier la structure
        $sql = "DESCRIBE $table_name";
        $resql2 = $db->query($sql);
        
        if ($resql2) {
            $existing_columns = [];
            while ($obj = $db->fetch_object($resql2)) {
                $existing_columns[$obj->Field] = $obj->Type;
            }
            
            print "<table>";
            print "<tr><th>Colonne</th><th>Status</th><th>Type actuel</th><th>Type attendu</th></tr>";
            
            foreach ($columns as $col_name => $col_type) {
                if (isset($existing_columns[$col_name])) {
                    print "<tr><td>$col_name</td><td class='success'>✓ Existe</td><td>".$existing_columns[$col_name]."</td><td>$col_type</td></tr>";
                } else {
                    print "<tr><td>$col_name</td><td class='error'>✗ Manquante</td><td>-</td><td>$col_type</td></tr>";
                    $all_tables_ok = false;
                }
            }
            print "</table>";
        }
    } else {
        print "<div class='error'>✗ Table $table_name n'existe pas</div>";
        $all_tables_ok = false;
    }
}

// Diagnostic des extrafields
print '<h2>2. Vérification des extrafields</h2>';

$extrafields_required = [
    'total_de_colis' => 'commande',
    'listecolis_fp' => 'commande'
];

foreach ($extrafields_required as $extrafield => $table) {
    $sql = "SELECT name FROM ".MAIN_DB_PREFIX."extrafields WHERE name = '".$extrafield."' AND elementtype = '".$table."'";
    $resql = $db->query($sql);
    
    if ($resql && $db->num_rows($resql) > 0) {
        print "<div class='success'>✓ Extrafield $extrafield pour $table existe</div>";
    } else {
        print "<div class='error'>✗ Extrafield $extrafield pour $table n'existe pas</div>";
    }
}

// Diagnostic du module
print '<h2>3. Statut du module</h2>';

$sql = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_COLISAGE'";
$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    if ($obj->value == '1') {
        print "<div class='success'>✓ Module Colisage activé</div>";
    } else {
        print "<div class='warning'>⚠ Module Colisage désactivé</div>";
    }
} else {
    print "<div class='error'>✗ Statut du module inconnu</div>";
}

// Actions de réparation
print '<h2>4. Actions de réparation</h2>';

if ($action == 'repair_tables') {
    print "<h3>Réparation des tables en cours...</h3>";
    
    foreach ($tables_required as $table_name => $columns) {
        // Créer la table si elle n'existe pas
        $sql_create = "";
        if ($table_name == 'llx_colisage_packages') {
            $sql_create = "CREATE TABLE IF NOT EXISTS $table_name (
                rowid int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                fk_commande int(11) NOT NULL,
                multiplier int(11) DEFAULT 1,
                is_free tinyint(1) DEFAULT 0,
                total_weight decimal(10,3) DEFAULT 0.000,
                total_surface decimal(10,3) DEFAULT 0.000,
                date_creation datetime DEFAULT NULL,
                date_modification datetime DEFAULT NULL,
                fk_user_creat int(11) DEFAULT NULL,
                fk_user_modif int(11) DEFAULT NULL
            ) ENGINE=InnoDB";
        } elseif ($table_name == 'llx_colisage_items') {
            $sql_create = "CREATE TABLE IF NOT EXISTS $table_name (
                rowid int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                fk_package int(11) NOT NULL,
                fk_commandedet int(11) DEFAULT NULL,
                quantity int(11) NOT NULL DEFAULT 1,
                detail_id varchar(50) DEFAULT NULL,
                custom_name varchar(255) DEFAULT NULL,
                custom_longueur int(11) DEFAULT NULL,
                custom_largeur int(11) DEFAULT NULL,
                custom_description varchar(255) DEFAULT NULL,
                custom_weight decimal(10,3) DEFAULT NULL,
                longueur int(11) NOT NULL,
                largeur int(11) NOT NULL,
                description varchar(255) DEFAULT NULL,
                weight_unit decimal(10,3) DEFAULT 0.000,
                surface_unit decimal(10,3) DEFAULT 0.000,
                date_creation datetime DEFAULT NULL,
                fk_user_creat int(11) DEFAULT NULL
            ) ENGINE=InnoDB";
        }
        
        if ($sql_create) {
            $resql = $db->query($sql_create);
            if ($resql) {
                print "<div class='success'>✓ Table $table_name créée/vérifiée</div>";
            } else {
                print "<div class='error'>✗ Erreur création table $table_name: ".$db->error()."</div>";
            }
        }
    }
    
    // Créer les index
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_colisage_packages_commande ON llx_colisage_packages (fk_commande)",
        "CREATE INDEX IF NOT EXISTS idx_colisage_items_package ON llx_colisage_items (fk_package)",
        "CREATE INDEX IF NOT EXISTS idx_colisage_items_commandedet ON llx_colisage_items (fk_commandedet)"
    ];
    
    foreach ($indexes as $sql_index) {
        $resql = $db->query($sql_index);
        if ($resql) {
            print "<div class='success'>✓ Index créé</div>";
        } else {
            print "<div class='warning'>⚠ Index non créé (peut déjà exister): ".$db->error()."</div>";
        }
    }
}

if ($action == 'repair_extrafields') {
    print "<h3>Réparation des extrafields en cours...</h3>";
    
    include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($db);
    
    // Create total_de_colis extrafield
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
        print "<div class='success'>✓ Extrafield total_de_colis créé</div>";
    } else {
        print "<div class='warning'>⚠ Extrafield total_de_colis non créé (peut déjà exister)</div>";
    }
    
    // Create listecolis_fp extrafield
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
        print "<div class='success'>✓ Extrafield listecolis_fp créé</div>";
    } else {
        print "<div class='warning'>⚠ Extrafield listecolis_fp non créé (peut déjà exister)</div>";
    }
}

print '<div style="margin-top: 30px;">';
print '<a href="?action=repair_tables" class="btn">Réparer les tables</a>';
print '<a href="?action=repair_extrafields" class="btn">Réparer les extrafields</a>';
print '<a href="?" class="btn">Actualiser le diagnostic</a>';
print '</div>';

print '<h2>5. Instructions</h2>';
print '<div class="info">';
print '<p><strong>Si vous avez une erreur 500 lors de l\'activation du module :</strong></p>';
print '<ol>';
print '<li>Désactivez le module via l\'interface Dolibarr</li>';
print '<li>Exécutez "Réparer les tables" ci-dessus</li>';
print '<li>Exécutez "Réparer les extrafields" ci-dessus</li>';
print '<li>Réactivez le module via l\'interface Dolibarr</li>';
print '</ol>';
print '<p><strong>Note :</strong> Les fichiers SQL ont été corrigés pour utiliser "CREATE TABLE IF NOT EXISTS" afin d\'éviter les conflits lors de la réactivation.</p>';
print '</div>';

print '</body>';
print '</html>';
