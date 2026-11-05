<?php
/* Copyright (C) 2026 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       colisage/ajax/lots.php
 * \ingroup    colisage
 * \brief      AJAX : lister les lots en stock d'une ligne de commande et enregistrer la sélection
 *
 * GET  action=list&commande_id=X&commandedet_id=Y
 * POST action=save&commande_id=X&commandedet_id=Y&token=...&lots=[{"fk_entrepot":1,"batch":"L1","qty":5}]
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1'); // Contrôle fait plus bas (token + même origine)

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
dol_include_once('/colisage/lib/colisagelots.lib.php');

/**
 * @param bool       $success Succès
 * @param mixed|null $data    Données
 * @param string     $error   Message d'erreur
 * @return void
 */
function colisageLotsJson($success, $data = null, $error = '')
{
	top_httphead('application/json');
	$out = array('success' => $success);
	if ($data !== null) $out['data'] = $data;
	if ($error !== '') $out['error'] = $error;
	echo json_encode($out);
	exit;
}

if (!isModEnabled('colisage')) colisageLotsJson(false, null, 'Module Colisage non activé');
if (!isModEnabled('productbatch')) colisageLotsJson(false, null, 'Module Lots/Séries non activé');
if (!$user->hasRight('commande', 'read')) colisageLotsJson(false, null, 'Accès refusé');

$action = GETPOST('action', 'aZ09');
$commande_id = GETPOSTINT('commande_id');
$commandedet_id = GETPOSTINT('commandedet_id');

$commande = new Commande($db);
if ($commande_id <= 0 || $commande->fetch($commande_id) <= 0) {
	colisageLotsJson(false, null, 'Commande introuvable');
}

// Retrouver la ligne de commande et vérifier qu'elle appartient bien à la commande
$line = null;
foreach ($commande->lines as $l) {
	if ((int) $l->id === $commandedet_id) {
		$line = $l;
		break;
	}
}
if (!$line || $line->fk_product <= 0) {
	colisageLotsJson(false, null, 'Ligne de commande introuvable');
}

// -------------------------------------------------------------------------
// LIST
// -------------------------------------------------------------------------
if ($action === 'list') {
	$available = colisage_lots_get_available($db, $line->fk_product);
	$all = colisage_lots_get_selections($db, $commande_id);
	$selected = isset($all[$commandedet_id]) ? $all[$commandedet_id] : array();
	// Quantités réservées par lot sur les autres commandes en cours (non encore expédiées)
	$reserved = colisage_lots_get_reserved_other($db, $line->fk_product, $commande_id);

	// Index des sélections par entrepôt|lot
	$selIndex = array();
	foreach ($selected as $s) {
		$selIndex[$s['fk_entrepot'].'|'.$s['batch']] = $s['qty'];
	}

	$lots = array();
	foreach ($available as $a) {
		$key = $a['fk_entrepot'].'|'.$a['batch'];
		$a['selected_qty'] = isset($selIndex[$key]) ? $selIndex[$key] : 0;
		$a['reserved_qty'] = isset($reserved[$key]) ? $reserved[$key]['qty'] : 0;
		$a['reserved_orders'] = isset($reserved[$key]) ? $reserved[$key]['orders'] : array();
		unset($selIndex[$key]);
		$lots[] = $a;
	}
	// Lots sélectionnés qui ne sont plus en stock : on les affiche quand même (stock 0)
	foreach ($selIndex as $key => $qty) {
		list($fk_entrepot, $batch) = explode('|', $key, 2);
		$entrepotRef = '';
		foreach ($selected as $s) {
			if ($s['fk_entrepot'] == $fk_entrepot && $s['batch'] === $batch) $entrepotRef = $s['entrepot'];
		}
		$lots[] = array(
			'fk_product_batch' => 0,
			'fk_entrepot'      => (int) $fk_entrepot,
			'entrepot'         => $entrepotRef,
			'batch'            => $batch,
			'qty'              => 0,
			'sellby'           => '',
			'eatby'            => '',
			'selected_qty'     => $qty,
			'reserved_qty'     => isset($reserved[$key]) ? $reserved[$key]['qty'] : 0,
			'reserved_orders'  => isset($reserved[$key]) ? $reserved[$key]['orders'] : array(),
			'missing'          => 1,
		);
	}

	colisageLotsJson(true, array(
		'commandedet_id' => $commandedet_id,
		'product_ref'    => $line->ref,
		'product_label'  => $line->product_label ? $line->product_label : $line->label,
		'qty_ordered'    => (float) $line->qty,
		'allow_negative' => getDolGlobalInt('STOCK_ALLOW_NEGATIVE_TRANSFER') ? 1 : 0,
		'can_edit'       => $user->hasRight('commande', 'creer') ? 1 : 0,
		'lots'           => $lots,
	));
}

// -------------------------------------------------------------------------
// SAVE
// -------------------------------------------------------------------------
if ($action === 'save') {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') colisageLotsJson(false, null, 'Méthode non autorisée');
	if (!$user->hasRight('commande', 'creer')) colisageLotsJson(false, null, 'Droit de modification des commandes requis');

	// Anti-CSRF : token de la page + requête de même origine
	$token = GETPOST('token', 'alpha');
	if (empty($token) || strlen($token) < 10) colisageLotsJson(false, null, 'Token manquant');
	$origin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
	$originHost = $origin ? (string) parse_url($origin, PHP_URL_HOST) : '';
	$serverHost = (string) parse_url('//'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''), PHP_URL_HOST);
	if ($originHost === '' || strcasecmp($originHost, $serverHost) !== 0) {
		colisageLotsJson(false, null, 'Origine de la requête refusée');
	}

	$lotsJson = GETPOST('lots', 'none'); // JSON décodé puis chaque champ est typé/échappé
	$lotsIn = json_decode($lotsJson, true);
	if (!is_array($lotsIn)) colisageLotsJson(false, null, 'Données de lots invalides');

	// Stock réel par entrepôt|lot pour contrôle
	$stockIndex = array();
	foreach (colisage_lots_get_available($db, $line->fk_product) as $a) {
		$stockIndex[$a['fk_entrepot'].'|'.$a['batch']] = $a['qty'];
	}
	$allowNegative = getDolGlobalInt('STOCK_ALLOW_NEGATIVE_TRANSFER');

	$clean = array();
	$errors = array();
	foreach ($lotsIn as $l) {
		$qty = (float) price2num(isset($l['qty']) ? $l['qty'] : 0);
		if ($qty <= 0) continue;
		$fk_entrepot = (int) (isset($l['fk_entrepot']) ? $l['fk_entrepot'] : 0);
		$batch = trim((string) (isset($l['batch']) ? $l['batch'] : ''));
		if ($fk_entrepot <= 0 || $batch === '') continue;

		$key = $fk_entrepot.'|'.$batch;
		$stock = isset($stockIndex[$key]) ? $stockIndex[$key] : 0;
		if (!$allowNegative && $qty > $stock) {
			$errors[] = 'Lot '.$batch.' : '.$qty.' demandé(s) pour '.$stock.' en stock';
			continue;
		}
		if (isset($clean[$key])) {
			$clean[$key]['qty'] += $qty;
		} else {
			$clean[$key] = array('fk_entrepot' => $fk_entrepot, 'batch' => $batch, 'qty' => $qty);
		}
	}
	if (!empty($errors)) colisageLotsJson(false, null, implode("\n", $errors));

	$r = colisage_lots_save_line($db, $user, $commande_id, $commandedet_id, (int) $line->fk_product, array_values($clean));
	if ($r < 0) colisageLotsJson(false, null, 'Erreur SQL : '.$db->lasterror());

	$all = colisage_lots_get_selections($db, $commande_id);
	$lineSelections = isset($all[$commandedet_id]) ? $all[$commandedet_id] : array();

	// Recopie dans l'extrafield "matiere" de la ligne (fiche de production Einstein)
	$matiere = colisage_lots_update_matiere($db, $user, $line, $lineSelections);

	colisageLotsJson(true, array(
		'commandedet_id' => $commandedet_id,
		'selections'     => $lineSelections,
		'matiere'        => $matiere === false ? null : $matiere,
		'matiere_ok'     => $matiere !== false,
	));
}

colisageLotsJson(false, null, 'Action inconnue');
