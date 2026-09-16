<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       colisage/ajax/get_ebs_chantiers.php
 * \ingroup    colisage
 * \brief      Renvoie le texte 1 (contact) et la liste des chantiers uniques
 *             pour les popups de confirmation EBS.
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');

$res = 0;
if (!$res && file_exists("../../main.inc.php"))      $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php"))    $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

header('Content-Type: application/json; charset=UTF-8');

if (!isModEnabled('colisage')) {
    http_response_code(403);
    echo json_encode(array('error' => 'Module Colisage non activé'));
    exit;
}
if (!$user->hasRight('commande', 'read')) {
    http_response_code(403);
    echo json_encode(array('error' => 'Accès refusé'));
    exit;
}

$fk_commande = GETPOSTINT('id');
if (empty($fk_commande)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Paramètre id manquant'));
    exit;
}

// ---------------------------------------------------------------
// 1. Build Text 1 from SHIPPING contact (same logic as generateColisageEBSFiles)
// ---------------------------------------------------------------
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$commande = new Commande($db);
if ($commande->fetch((int) $fk_commande) <= 0) {
    http_response_code(500);
    echo json_encode(array('error' => 'Commande introuvable'));
    exit;
}

$text1 = '';
$contactIds = $commande->getIdContact('external', 'SHIPPING');
if (!empty($contactIds)) {
    require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
    $contact = new Contact($db);
    if ($contact->fetch((int) $contactIds[0]) > 0) {
        $contactName = strtoupper(trim($contact->lastname));
        $city        = strtoupper(trim($contact->town));
        $zip         = trim($contact->zip);
        $dept        = substr($zip, 0, 2);
        $text1       = $contactName.' / '.$city.' ('.$dept.')';
    }
}

// ---------------------------------------------------------------
// 2. Build section map : commandedet_rowid => section_title
// ---------------------------------------------------------------
$sectionMap = array();
$sql  = "SELECT cd.rowid, cd.rang, cd.product_type, cd.fk_product, cd.description,";
$sql .= " cde.ref_commande";
$sql .= " FROM ".MAIN_DB_PREFIX."commandedet cd";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet_extrafields cde ON cde.fk_object = cd.rowid";
$sql .= " WHERE cd.fk_commande = ".((int) $fk_commande);
$sql .= " ORDER BY cd.rang ASC, cd.rowid ASC";

$resql = $db->query($sql);
if ($resql) {
    $currentSection = '';
    while ($obj = $db->fetch_object($resql)) {
        if ((int) $obj->fk_product === 361 && (int) $obj->product_type === 1) {
            $currentSection = !empty($obj->ref_commande) ? $obj->ref_commande : $obj->description;
        }
        $sectionMap[(int) $obj->rowid] = $currentSection;
    }
    $db->free($resql);
}

// ---------------------------------------------------------------
// 3. Iterate packages to collect unique chantiers (preserving order)
// ---------------------------------------------------------------
require_once __DIR__.'/../class/colisagepackage.class.php';

$sql  = "SELECT rowid FROM ".MAIN_DB_PREFIX."colisage_packages";
$sql .= " WHERE fk_commande = ".((int) $fk_commande);
$sql .= " ORDER BY rowid ASC";

$resql = $db->query($sql);
if (!$resql) {
    http_response_code(500);
    echo json_encode(array('error' => 'Erreur base de données'));
    exit;
}

$chantierCounts = array();
$chantierOrder  = array();

while ($pkgRow = $db->fetch_object($resql)) {
    $package = new ColisagePackage($db);
    if ($package->fetch((int) $pkgRow->rowid) <= 0) {
        continue;
    }

    $text2 = '';
    foreach ($package->items as $item) {
        if (!empty($item->fk_commandedet) && isset($sectionMap[(int) $item->fk_commandedet])) {
            $text2 = $sectionMap[(int) $item->fk_commandedet];
            break;
        }
    }

    if (!isset($chantierCounts[$text2])) {
        $chantierCounts[$text2] = 0;
        $chantierOrder[] = $text2;
    }
    $chantierCounts[$text2]++;
}
$db->free($resql);

$chantiers = array();
foreach ($chantierOrder as $text2) {
    $chantiers[] = array(
        'text2' => $text2,
        'count' => $chantierCounts[$text2],
    );
}

echo json_encode(array(
    'text1'     => $text1,
    'chantiers' => $chantiers,
));
