<?php
/* Copyright (C) 2026 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       colisage/class/actions_colisage.class.php
 * \ingroup    colisage
 * \brief      Hooks du module Colisage
 *
 * expeditioncard / formObjectOptions (action=create, origin=commande) :
 * pré-remplit les quantités par lot du formulaire natif de création d'expédition
 * avec les lots choisis dans l'onglet Colisage de la commande.
 */

/**
 * Class ActionsColisage
 */
class ActionsColisage
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var array */
	public $errors = array();
	/** @var array */
	public $results = array();
	/** @var string */
	public $resprints = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook formObjectOptions
	 *
	 * @param array        $parameters  Hook parameters
	 * @param CommonObject $object      Object (Expedition en création)
	 * @param string       $action      Current action
	 * @param HookManager  $hookmanager Hook manager
	 * @return int                      0
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', (string) $parameters['context']);
		if (!in_array('expeditioncard', $contexts) || $action !== 'create') {
			return 0;
		}
		if (!isModEnabled('colisage') || !isModEnabled('productbatch')) {
			return 0;
		}
		if (GETPOST('origin', 'alpha') !== 'commande') {
			return 0;
		}

		$origin_id = GETPOSTINT('origin_id');
		if (empty($origin_id)) $origin_id = GETPOSTINT('object_id');
		if (empty($origin_id)) $origin_id = GETPOSTINT('id');
		if ($origin_id <= 0) {
			return 0;
		}

		// Formulaire ré-affiché après une erreur de saisie : on ne touche pas aux valeurs de l'utilisateur
		if (GETPOSTISSET('idl0')) {
			return 0;
		}

		dol_include_once('/colisage/lib/colisagelots.lib.php');

		$selections = colisage_lots_get_selections($this->db, $origin_id);
		if (empty($selections)) {
			return 0;
		}
		$shipped = colisage_lots_get_shipped($this->db, $origin_id);

		// Correspondance sélection (entrepôt + lot) -> id llx_product_batch courant
		$availableCache = array();
		$map = array();     // [fk_commandedet][fk_product_batch] = qté à pré-remplir
		$expected = array(); // [fk_commandedet] = liste lisible pour les avertissements
		foreach ($selections as $lineId => $sels) {
			foreach ($sels as $s) {
				$fk_product = (int) $s['fk_product'];
				if (!isset($availableCache[$fk_product])) {
					$availableCache[$fk_product] = colisage_lots_get_available($this->db, $fk_product);
				}
				// On retire ce qui a déjà été mis sur une expédition pour ce lot
				$already = isset($shipped[$lineId][$s['batch']]) ? $shipped[$lineId][$s['batch']] : 0;
				$todo = max(0, $s['qty'] - $already);
				// Les quantités déjà consommées sont imputées une seule fois
				if (isset($shipped[$lineId][$s['batch']])) {
					$shipped[$lineId][$s['batch']] = max(0, $already - $s['qty']);
				}

				$pbId = 0;
				foreach ($availableCache[$fk_product] as $a) {
					if ((int) $a['fk_entrepot'] === (int) $s['fk_entrepot'] && $a['batch'] === $s['batch']) {
						$pbId = (int) $a['fk_product_batch'];
						break;
					}
				}
				if ($pbId > 0) {
					if (!isset($map[$lineId][$pbId])) $map[$lineId][$pbId] = 0;
					$map[$lineId][$pbId] += $todo;
				}
				$expected[$lineId][] = array(
					'pb'       => $pbId,
					'batch'    => $s['batch'],
					'entrepot' => $s['entrepot'],
					'qty'      => $todo,
				);
			}
		}

		$json = json_encode(array('map' => (object) $map, 'expected' => (object) $expected));

		$this->resprints = '<!-- Colisage : pré-remplissage des lots -->'."\n";
		$this->resprints .= '<script type="text/javascript">'."\n";
		$this->resprints .= 'jQuery(function ($) {'."\n";
		$this->resprints .= '  var cfg = '.$json.';'."\n";
		$this->resprints .= <<<'JS'
  var prefilled = [];   // {input, value, label}
  var warnings = [];
  var linesDone = 0;

  $('input[type="hidden"][name^="idl"]').each(function () {
    var m = this.name.match(/^idl(\d+)$/);
    if (!m) return;
    var i = m[1];
    var lineId = String(this.value);
    var lineMap = cfg.map[lineId];
    var lineExpected = cfg.expected[lineId];
    if (!lineExpected) return;
    linesDone++;
    var found = {};
    $('input[type="hidden"][name^="batchl' + i + '_"]').each(function () {
      var j = this.name.substring(('batchl' + i + '_').length);
      var pb = String(this.value);
      var $q = $('input[name="qtyl' + i + '_' + j + '"]');
      if (!$q.length || $q.prop('disabled')) return;
      var v = (lineMap && lineMap[pb] !== undefined) ? Number(lineMap[pb]) : 0;
      v = Math.round(v * 1000) / 1000;
      $q.val(v);
      $q.addClass(v > 0 ? 'colisage-lot-prefilled' : 'colisage-lot-zeroed');
      if (v > 0) $q.attr('title', 'Lot choisi dans le Colisage');
      found[pb] = true;
      prefilled.push({ input: $q, value: v });
    });
    $.each(lineExpected, function (_, e) {
      if (e.qty <= 0) return;
      if (!e.pb) {
        warnings.push('Lot ' + e.batch + ' (' + e.entrepot + ') : plus en stock');
      } else if (!found[String(e.pb)]) {
        warnings.push('Lot ' + e.batch + ' : non proposé ici (entrepôt ' + e.entrepot + ' non sélectionné ?)');
      }
    });
  });

  if (!linesDone) return;

  var $firstQty = $('input.qtyl').first();
  var $table = $firstQty.closest('table');
  var html = '<div class="info colisage-lot-notice">📦 <strong>Colisage</strong> : les quantités par lot ont été pré-remplies '
    + 'avec les lots choisis dans l\'onglet Colisage (cases vertes). Les autres lots de ces lignes sont mis à 0.</div>';
  if (warnings.length) {
    html += '<div class="warning colisage-lot-notice"><strong>Lots Colisage non appliqués :</strong><br>'
      + $('<div>').text(warnings.join('\n')).html().replace(/\n/g, '<br>') + '</div>';
  }
  if ($table.length) $table.before(html);

  // Contrôle à la validation du formulaire : alerte si l'utilisateur a modifié les lots
  var $form = $firstQty.closest('form');
  $form.on('submit', function (ev) {
    var submitter = ev.originalEvent && ev.originalEvent.submitter;
    if (submitter && submitter.name === 'cancel') return true;
    var changed = 0;
    $.each(prefilled, function (_, p) {
      var cur = parseFloat(String(p.input.val()).replace(',', '.')) || 0;
      if (Math.abs(cur - p.value) > 0.0001) changed++;
    });
    if (changed > 0 && !window.confirm(changed + ' quantité(s) par lot diffèrent de la sélection faite dans le Colisage.\n\nCréer l\'expédition quand même ?')) {
      ev.preventDefault();
      return false;
    }
  });
});
JS;
		$this->resprints .= "\n".'</script>'."\n";

		return 0;
	}
}
