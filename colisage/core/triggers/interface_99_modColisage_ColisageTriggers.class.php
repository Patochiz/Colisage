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

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceColisageTriggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "colisage";
		$this->description = "Colisage triggers: ajout automatique des echantillons sur commande";
		$this->version = '1.0';
		$this->picto = 'fa-file-o';
	}

	public function getName()
	{
		return $this->name;
	}

	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * @param string    $action Event action code
	 * @param Object    $object Object
	 * @param User      $user   Object user
	 * @param Translate $langs  Object langs
	 * @param Conf      $conf   Object conf
	 * @return int               0 = nothing done, >0 = OK, <0 = KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if ($action !== 'ORDER_CREATE') {
			return 0;
		}

		if (!getDolGlobalInt('COLISAGE_ECHANTILLONS_ENABLED')) {
			return 0;
		}

		$socid = $object->socid;
		if (empty($socid)) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$societe = new Societe($this->db);
		if ($societe->fetch($socid) <= 0) {
			return 0;
		}
		$societe->fetch_optionals();

		if (empty($societe->array_options['options_echantillons'])) {
			return 0;
		}

		$product1_id = getDolGlobalInt('COLISAGE_ECHANTILLONS_PRODUCT1', 625);
		$product2_id = getDolGlobalInt('COLISAGE_ECHANTILLONS_PRODUCT2', 626);

		$product_ids = array();
		if (!empty($product1_id)) {
			$product_ids[] = $product1_id;
		}
		if (!empty($product2_id)) {
			$product_ids[] = $product2_id;
		}

		if (empty($product_ids)) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

		// Vérifier que les produits ne sont pas déjà dans la commande
		$existing_product_ids = array();
		if (!empty($object->lines)) {
			foreach ($object->lines as $line) {
				if (!empty($line->fk_product)) {
					$existing_product_ids[] = (int) $line->fk_product;
				}
			}
		}

		foreach ($product_ids as $pid) {
			if (in_array((int) $pid, $existing_product_ids)) {
				dol_syslog("Colisage echantillons: product ".$pid." already in order ".$object->id.", skipping", LOG_INFO);
				continue;
			}
			$product = new Product($this->db);
			if ($product->fetch($pid) <= 0) {
				dol_syslog("Colisage echantillons: product ID ".$pid." not found", LOG_WARNING);
				continue;
			}

			$desc = $product->description;
			if (empty($desc)) {
				$desc = $product->label;
			}

			$result = $object->addline(
				$desc,
				0,
				1,
				$product->tva_tx,
				$product->localtax1_tx,
				$product->localtax2_tx,
				$pid,
				0,
				0,
				0,
				'HT',
				0,
				'',
				'',
				$product->type,
				-1,
				0,
				0,
				null,
				0,
				$product->label,
				array(),
				$product->fk_unit
			);

			if ($result > 0) {
				dol_syslog("Colisage echantillons: added product ".$pid." to order ".$object->id, LOG_INFO);
			} else {
				dol_syslog("Colisage echantillons: error adding product ".$pid." to order ".$object->id.": ".$object->error, LOG_ERR);
			}
		}

		return 0;
	}
}
