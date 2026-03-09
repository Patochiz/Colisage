<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       colisage/ajax/generate_ebs_files.php
 * \ingroup    colisage
 * \brief      Génère les fichiers EBS (.prj + .prv) pour tous les colis d'une commande
 *             et les renvoie dans un fichier ZIP.
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php"))     $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php"))   $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once __DIR__.'/../lib/colisage.lib.php';
require_once __DIR__.'/../class/colisagepackage.class.php';

// ---------------------------------------------------------------
// Security checks
// ---------------------------------------------------------------
if (!isModEnabled('colisage')) {
    http_response_code(403);
    die('Module Colisage non activé');
}

if (!$user->hasRight('commande', 'read')) {
    http_response_code(403);
    die('Accès refusé');
}

// Validate CSRF token
$token = GETPOST('token', 'alpha');
if (!newToken() && !checkToken($token)) {
    http_response_code(403);
    die('Token invalide');
}

$fk_commande = GETPOSTINT('id');
if (empty($fk_commande)) {
    http_response_code(400);
    die('Paramètre id manquant');
}

// ---------------------------------------------------------------
// Generate files
// ---------------------------------------------------------------
$files = generateColisageEBSFiles($fk_commande);

if ($files === false || empty($files)) {
    http_response_code(500);
    die('Erreur lors de la génération des fichiers EBS');
}

// ---------------------------------------------------------------
// Build ZIP archive
// ---------------------------------------------------------------
if (!class_exists('ZipArchive')) {
    // Fallback: send files as JSON (base64 encoded) for client-side ZIP
    header('Content-Type: application/json');
    $output = array();
    foreach ($files as $filename => $content) {
        $output[] = array(
            'name'    => $filename,
            'content' => base64_encode($content),
        );
    }
    echo json_encode($output);
    exit;
}

// Get commande ref for the ZIP filename
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
$commande = new Commande($db);
$commande->fetch($fk_commande);
$commandeRef = preg_replace('/[^A-Za-z0-9_\-]/', '_', $commande->ref);

$tmpFile = tempnam(sys_get_temp_dir(), 'colisage_ebs_').'.zip';

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Impossible de créer le fichier ZIP');
}

foreach ($files as $filename => $content) {
    $zip->addFromString($filename, $content);
}
$zip->close();

// ---------------------------------------------------------------
// Send ZIP to browser
// ---------------------------------------------------------------
$zipFilename = 'EBS_'.$commandeRef.'.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zipFilename.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmpFile);
unlink($tmpFile);
exit;
