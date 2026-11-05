<?php
/* Copyright (C) 2026 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       colisage/lib/colisagelots.lib.php
 * \ingroup    colisage
 * \brief      Fonctions de gestion des lots présélectionnés dans le Colisage
 *
 * Une sélection = (ligne de commande, entrepôt, numéro de lot, quantité).
 * On stocke le numéro de lot + l'entrepôt (et non l'id llx_product_batch) car Dolibarr
 * supprime / recrée les lignes llx_product_batch quand le stock d'un lot passe à 0.
 */

/**
 * Crée la table des lots sélectionnés si elle n'existe pas (filet de sécurité
 * si le module n'a pas été réactivé après la mise à jour).
 *
 * @param  DoliDB $db Database handler
 * @return void
 */
function colisage_lots_ensure_table($db)
{
	static $done = false;
	if ($done) {
		return;
	}
	$done = true;

	$res = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX."colisage_lots")."'");
	if ($res && $db->num_rows($res) > 0) {
		return;
	}

	$sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."colisage_lots (";
	$sql .= " rowid integer AUTO_INCREMENT PRIMARY KEY,";
	$sql .= " fk_commande integer NOT NULL,";
	$sql .= " fk_commandedet integer NOT NULL,";
	$sql .= " fk_product integer NOT NULL,";
	$sql .= " fk_entrepot integer NOT NULL,";
	$sql .= " batch varchar(128) NOT NULL,";
	$sql .= " qty double NOT NULL DEFAULT 0,";
	$sql .= " date_creation datetime,";
	$sql .= " fk_user_creat integer,";
	$sql .= " INDEX idx_colisage_lots_commande (fk_commande),";
	$sql .= " INDEX idx_colisage_lots_commandedet (fk_commandedet)";
	$sql .= ") ENGINE=innodb";
	if (!$db->query($sql)) {
		dol_syslog("colisage_lots_ensure_table: ".$db->lasterror(), LOG_ERR);
	}
}

/**
 * Sélections de lots enregistrées pour une commande.
 *
 * @param  DoliDB $db          Database handler
 * @param  int    $fk_commande Id commande
 * @return array               [fk_commandedet => [ ['fk_entrepot'=>, 'entrepot'=>, 'batch'=>, 'qty'=>], ... ]]
 */
function colisage_lots_get_selections($db, $fk_commande)
{
	colisage_lots_ensure_table($db);

	$out = array();
	$sql = "SELECT cl.fk_commandedet, cl.fk_product, cl.fk_entrepot, cl.batch, cl.qty, e.ref as entrepot_ref";
	$sql .= " FROM ".MAIN_DB_PREFIX."colisage_lots as cl";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = cl.fk_entrepot";
	$sql .= " WHERE cl.fk_commande = ".((int) $fk_commande);
	$sql .= " ORDER BY cl.fk_commandedet, cl.rowid";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$out[(int) $obj->fk_commandedet][] = array(
				'fk_product'  => (int) $obj->fk_product,
				'fk_entrepot' => (int) $obj->fk_entrepot,
				'entrepot'    => (string) $obj->entrepot_ref,
				'batch'       => (string) $obj->batch,
				'qty'         => (float) $obj->qty,
			);
		}
	}
	return $out;
}

/**
 * Lots en stock pour un produit (tous entrepôts ouverts), avec l'id llx_product_batch courant.
 *
 * @param  DoliDB $db         Database handler
 * @param  int    $fk_product Id produit
 * @return array              Liste de ['fk_product_batch','fk_entrepot','entrepot','batch','qty','sellby','eatby']
 */
function colisage_lots_get_available($db, $fk_product)
{
	$out = array();
	$sql = "SELECT pb.rowid as fk_product_batch, pb.batch, pb.qty, pb.sellby, pb.eatby,";
	$sql .= " ps.fk_entrepot, e.ref as entrepot_ref";
	$sql .= " FROM ".MAIN_DB_PREFIX."product_batch as pb";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product_stock as ps ON ps.rowid = pb.fk_product_stock";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = ps.fk_entrepot";
	$sql .= " WHERE ps.fk_product = ".((int) $fk_product);
	$sql .= " AND e.entity IN (".getEntity('stock').")";
	$sql .= " AND e.statut > 0"; // entrepôts ouverts
	$sql .= " AND pb.qty <> 0";
	$sql .= " ORDER BY e.ref, pb.batch";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$out[] = array(
				'fk_product_batch' => (int) $obj->fk_product_batch,
				'fk_entrepot'      => (int) $obj->fk_entrepot,
				'entrepot'         => (string) $obj->entrepot_ref,
				'batch'            => (string) $obj->batch,
				'qty'              => (float) $obj->qty,
				'sellby'           => $obj->sellby ? dol_print_date($db->jdate($obj->sellby), 'day') : '',
				'eatby'            => $obj->eatby ? dol_print_date($db->jdate($obj->eatby), 'day') : '',
			);
		}
	} else {
		dol_syslog("colisage_lots_get_available: ".$db->lasterror(), LOG_ERR);
	}
	return $out;
}

/**
 * Quantités déjà expédiées (toutes expéditions de la commande, brouillons compris) par ligne et par lot.
 *
 * @param  DoliDB $db          Database handler
 * @param  int    $fk_commande Id commande
 * @return array               [fk_commandedet => [batch => qty]]
 */
function colisage_lots_get_shipped($db, $fk_commande)
{
	$out = array();
	$sql = "SELECT ed.fk_elementdet as fk_commandedet, edb.batch, SUM(edb.qty) as qty";
	$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch as edb";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON ed.rowid = edb.fk_expeditiondet";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.fk_elementdet";
	$sql .= " WHERE cd.fk_commande = ".((int) $fk_commande);
	$sql .= " GROUP BY ed.fk_elementdet, edb.batch";
	$res = $db->query($sql);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$out[(int) $obj->fk_commandedet][(string) $obj->batch] = (float) $obj->qty;
		}
	} else {
		dol_syslog("colisage_lots_get_shipped: ".$db->lasterror(), LOG_WARNING);
	}
	return $out;
}

/**
 * Recopie les lots choisis dans l'extrafield de ligne "matiere" (texte long),
 * affiché sur la fiche de production (PDF Einstein).
 *
 * Seule la ligne commençant par "Lots :" est gérée par le Colisage : elle est remplacée
 * (ou supprimée si aucun lot), le reste du texte saisi à la main est conservé.
 *
 * @param  DoliDB    $db         Database handler
 * @param  User      $user       Utilisateur
 * @param  OrderLine $line       Ligne de commande
 * @param  array     $selections Sélections de la ligne (batch, qty)
 * @return string|false          Nouvelle valeur de matiere, false si l'extrafield n'existe pas ou erreur
 */
function colisage_lots_update_matiere($db, $user, $line, $selections)
{
	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label('commandedet');
	if (empty($extrafields->attributes['commandedet']['type']['matiere'])) {
		return false;
	}

	$line->fetch_optionals();
	$current = isset($line->array_options['options_matiere']) ? (string) $line->array_options['options_matiere'] : '';

	// Retirer l'ancienne ligne "Lots :" en gardant le reste
	$kept = array();
	foreach (preg_split('/\r\n|\r|\n/', $current) as $l) {
		if (preg_match('/^\s*Lots\s*:/i', $l)) {
			continue;
		}
		$kept[] = $l;
	}
	while (!empty($kept) && trim(end($kept)) === '') {
		array_pop($kept);
	}

	if (!empty($selections)) {
		$parts = array();
		foreach ($selections as $s) {
			$parts[] = $s['batch'].' ('.str_replace('.', ',', (string) price2num($s['qty'], 'MS')).')';
		}
		$kept[] = 'Lots : '.implode(' - ', $parts);
	}
	$new = implode("\n", $kept);

	if ($new === $current) {
		return $new;
	}

	$line->array_options['options_matiere'] = $new;
	if ($line->updateExtraField('matiere', null, $user) < 0) {
		dol_syslog("colisage_lots_update_matiere: ".$line->error, LOG_ERR);
		return false;
	}
	return $new;
}

/**
 * Remplace la sélection de lots d'une ligne de commande.
 *
 * @param  DoliDB $db             Database handler
 * @param  User   $user           Utilisateur
 * @param  int    $fk_commande    Id commande
 * @param  int    $fk_commandedet Id ligne de commande
 * @param  int    $fk_product     Id produit
 * @param  array  $selections     Liste de ['fk_entrepot'=>int, 'batch'=>string, 'qty'=>float] (qty > 0)
 * @return int                    >0 si OK, <0 si erreur
 */
function colisage_lots_save_line($db, $user, $fk_commande, $fk_commandedet, $fk_product, $selections)
{
	colisage_lots_ensure_table($db);

	$db->begin();

	$sql = "DELETE FROM ".MAIN_DB_PREFIX."colisage_lots";
	$sql .= " WHERE fk_commande = ".((int) $fk_commande)." AND fk_commandedet = ".((int) $fk_commandedet);
	if (!$db->query($sql)) {
		$db->rollback();
		return -1;
	}

	foreach ($selections as $sel) {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."colisage_lots";
		$sql .= " (fk_commande, fk_commandedet, fk_product, fk_entrepot, batch, qty, date_creation, fk_user_creat)";
		$sql .= " VALUES (".((int) $fk_commande).", ".((int) $fk_commandedet).", ".((int) $fk_product).",";
		$sql .= " ".((int) $sel['fk_entrepot']).", '".$db->escape($sel['batch'])."', ".((float) $sel['qty']).",";
		$sql .= " '".$db->idate(dol_now())."', ".((int) $user->id).")";
		if (!$db->query($sql)) {
			$db->rollback();
			return -2;
		}
	}

	$db->commit();
	return 1;
}
