<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file       colisage/install_extrafields.php
 * \ingroup    colisage
 * \brief      Script pour installer les extrafields nécessaires au module Colisage
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

// Vérification des droits d'administration
if (!$user->admin) {
    accessforbidden('Admin rights required');
}

// Début de l'affichage
print '<!DOCTYPE html>';
print '<html><head><title>Installation des extrafields du module Colisage</title></head><body>';
print '<h1>Installation des extrafields du module Colisage</h1>';

$extrafields = new ExtraFields($db);

$errors = array();
$success = array();

// Configuration des extrafields à créer
$extrafields_config = array(
    'total_de_colis' => array(
        'table' => 'commande',
        'label' => 'Nombre total de colis',
        'type' => 'int',
        'size' => 10,
        'pos' => 100,
        'enabled' => 1,
        'perms' => '$user->hasRight("commande", "read")',
        'langfile' => 'colisage@colisage',
        'list' => 1,
        'printable' => 1,
        'default' => 0,
        'css' => '',
        'cssview' => '',
        'csslist' => '',
        'help' => 'Nombre total de colis générés pour cette commande (calculé automatiquement par le module Colisage)',
        'computed' => '',
        'entity' => $conf->entity
    ),
    'listecolis_fp' => array(
        'table' => 'commande',
        'label' => 'Liste des colis (fiche production)',
        'type' => 'html',
        'size' => 65535,
        'pos' => 101,
        'enabled' => 1,
        'perms' => '$user->hasRight("commande", "read")',
        'langfile' => 'colisage@colisage',
        'list' => 0,
        'printable' => 1,
        'default' => '',
        'css' => '',
        'cssview' => '',
        'csslist' => '',
        'help' => 'Liste détaillée des colis pour la fiche de production (générée automatiquement par le module Colisage)',
        'computed' => '',
        'entity' => $conf->entity
    )
);

// Création des extrafields
foreach ($extrafields_config as $key => $config) {
    print "<h2>Création de l'extrafield '{$key}' pour la table '{$config['table']}'</h2>";
    
    // Vérifier si l'extrafield existe déjà
    $existing = $extrafields->fetch_name_optionals_label($config['table']);
    
    if (array_key_exists($key, $existing)) {
        print "<p style='color: orange;'>L'extrafield '{$key}' existe déjà pour la table '{$config['table']}'.</p>";
        
        // Vérifier si la configuration est à jour
        $current_config = $extrafields->attributes[$config['table']];
        $need_update = false;
        
        if (isset($current_config['type'][$key]) && $current_config['type'][$key] != $config['type']) {
            $need_update = true;
        }
        
        if ($need_update) {
            print "<p style='color: blue;'>Mise à jour de la configuration de l'extrafield '{$key}'...</p>";
            
            $result = $extrafields->update(
                $key,
                $config['label'],
                $config['type'],
                $config['size'],
                $config['table'],
                1, // unique
                0, // required
                $config['pos'],
                array(), // param
                1, // alwayseditable
                $config['perms'],
                $config['list'],
                $config['printable'],
                $config['default'],
                $config['css'],
                $config['cssview'],
                $config['csslist'],
                $config['help'],
                $config['computed'],
                $config['entity']
            );
            
            if ($result > 0) {
                print "<p style='color: green;'>Configuration de l'extrafield '{$key}' mise à jour avec succès.</p>";
                $success[] = "Mise à jour de l'extrafield '{$key}' pour la table '{$config['table']}'";
            } else {
                print "<p style='color: red;'>Erreur lors de la mise à jour de l'extrafield '{$key}': " . implode(', ', $extrafields->errors) . "</p>";
                $errors[] = "Erreur lors de la mise à jour de l'extrafield '{$key}': " . implode(', ', $extrafields->errors);
            }
        } else {
            print "<p style='color: green;'>L'extrafield '{$key}' est déjà configuré correctement.</p>";
            $success[] = "L'extrafield '{$key}' existe déjà et est correctement configuré";
        }
    } else {
        print "<p style='color: blue;'>Création de l'extrafield '{$key}'...</p>";
        
        $result = $extrafields->addExtraField(
            $key,
            $config['label'],
            $config['type'],
            $config['pos'],
            $config['size'],
            $config['table'],
            1, // unique
            0, // required
            $config['default'],
            array(), // param
            1, // alwayseditable
            $config['perms'],
            $config['list'],
            $config['printable'],
            $config['css'],
            $config['cssview'],
            $config['csslist'],
            $config['help'],
            $config['computed'],
            $config['entity']
        );
        
        if ($result > 0) {
            print "<p style='color: green;'>Extrafield '{$key}' créé avec succès.</p>";
            $success[] = "Création de l'extrafield '{$key}' pour la table '{$config['table']}'";
        } else {
            print "<p style='color: red;'>Erreur lors de la création de l'extrafield '{$key}': " . implode(', ', $extrafields->errors) . "</p>";
            $errors[] = "Erreur lors de la création de l'extrafield '{$key}': " . implode(', ', $extrafields->errors);
        }
    }
}

// Résumé final
print '<h2>Résumé de l\'installation</h2>';

if (!empty($success)) {
    print '<h3 style="color: green;">Opérations réussies:</h3>';
    print '<ul>';
    foreach ($success as $message) {
        print '<li style="color: green;">' . $message . '</li>';
    }
    print '</ul>';
}

if (!empty($errors)) {
    print '<h3 style="color: red;">Erreurs rencontrées:</h3>';
    print '<ul>';
    foreach ($errors as $error) {
        print '<li style="color: red;">' . $error . '</li>';
    }
    print '</ul>';
} else {
    print '<p style="color: green; font-weight: bold;">Tous les extrafields ont été installés avec succès !</p>';
}

print '<h3>Instructions d\'utilisation:</h3>';
print '<ul>';
print '<li>Le champ <strong>total_de_colis</strong> sera automatiquement mis à jour chaque fois que vous sauvegardez un colisage.</li>';
print '<li>Le champ <strong>listecolis_fp</strong> contiendra la liste détaillée des colis pour l\'affichage dans les fiches de production.</li>';
print '<li>Ces champs sont visibles dans l\'onglet "Autres informations" de vos commandes.</li>';
print '<li>Vous pouvez modifier la visibilité et la position de ces champs dans la configuration des extrafields (Menu Admin → Configuration → Dictionnaires → Champs supplémentaires).</li>';
print '</ul>';

print '<p><a href="admin/setup.php">← Retour à la configuration du module Colisage</a></p>';

print '</body></html>';
