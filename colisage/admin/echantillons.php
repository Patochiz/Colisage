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
 * \file    colisage/admin/echantillons.php
 * \ingroup colisage
 * \brief   Configuration de l'ajout automatique d'echantillons
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user, $conf, $db;

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once '../lib/colisage.lib.php';

$langs->loadLangs(array("admin", "colisage@colisage", "products"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');


/*
 * Actions
 */

if ($action == 'update') {
	$error = 0;

	$enabled = GETPOSTINT('COLISAGE_ECHANTILLONS_ENABLED');
	$product1 = GETPOSTINT('COLISAGE_ECHANTILLONS_PRODUCT1');
	$product2 = GETPOSTINT('COLISAGE_ECHANTILLONS_PRODUCT2');

	$res = dolibarr_set_const($db, 'COLISAGE_ECHANTILLONS_ENABLED', $enabled, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}
	$res = dolibarr_set_const($db, 'COLISAGE_ECHANTILLONS_PRODUCT1', $product1, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}
	$res = dolibarr_set_const($db, 'COLISAGE_ECHANTILLONS_PRODUCT2', $product2, 'chaine', 0, '', $conf->entity);
	if (!($res > 0)) {
		$error++;
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}


/*
 * View
 */

$form = new Form($db);

$title = "ColisageSetup";

llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-colisage page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = colisageAdminPrepareHead();
print dol_get_fiche_head($head, 'echantillons', $langs->trans($title), -1, "colisage@colisage");

print '<span class="opacitymedium">'.$langs->trans("EchantillonsSetupDesc").'</span><br><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Activation
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EchantillonsEnabled").'</td>';
print '<td>';
print ajax_constantonoff('COLISAGE_ECHANTILLONS_ENABLED', array(), null, 0, 0, 0, 0, 0, 0, '', 'colisage');
print '</td>';
print '</tr>';

// Produit 1
$product1_id = getDolGlobalInt('COLISAGE_ECHANTILLONS_PRODUCT1', 625);
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EchantillonsProduct1").'</td>';
print '<td>';
$form->select_produits($product1_id, 'COLISAGE_ECHANTILLONS_PRODUCT1', '', 0, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth400');
print '</td>';
print '</tr>';

// Produit 2
$product2_id = getDolGlobalInt('COLISAGE_ECHANTILLONS_PRODUCT2', 626);
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EchantillonsProduct2").'</td>';
print '<td>';
$form->select_produits($product2_id, 'COLISAGE_ECHANTILLONS_PRODUCT2', '', 0, 0, -1, 2, '', 0, array(), 0, '1', 0, 'maxwidth400');
print '</td>';
print '</tr>';

print '</table>';

print '<br>';
print '<div class="center">';
print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
