<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * AJAX endpoint pour sauvegarder les titres personnalisés des cartes/sections
 * dans l'extrafield ref_chantier
 */

// Chargement de l'environnement Dolibarr
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../../../main.inc.php")) $res = @include "../../../../../main.inc.php";
if (!$res) {
    die(json_encode(array('success' => false, 'error' => 'Impossible de charger main.inc.php')));
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

// En-têtes HTTP pour JSON
header('Content-Type: application/json; charset=utf-8');

// Désactiver la sortie bufferisée
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) {
    ob_end_flush();
}
ob_implicit_flush(1);

/**
 * Fonction pour envoyer une réponse JSON et terminer le script
 */
function sendJsonResponse($success, $data = null, $error = null) {
    $response = array('success' => $success);
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($error !== null) {
        $response['error'] = $error;
    }
    echo json_encode($response);
    exit;
}

// Log de démarrage
dol_syslog("AJAX save_card_title.php called", LOG_DEBUG);

// Vérifier les permissions
if (!$user->hasRight('commande', 'creer')) {
    sendJsonResponse(false, null, 'Permission refusée. Vous devez avoir les droits de création sur les commandes.');
}

// Récupérer les paramètres
$action = GETPOST('action', 'aZ09');

if ($action !== 'save_card_title') {
    sendJsonResponse(false, null, 'Action non reconnue');
}

// Récupérer les paramètres spécifiques
$rowid = GETPOSTINT('rowid');
$new_title = GETPOST('new_title', 'alphanohtml');

// Validation des paramètres
if (empty($rowid)) {
    sendJsonResponse(false, null, 'ID de ligne manquant');
}

if ($new_title === '') {
    sendJsonResponse(false, null, 'Le nouveau titre ne peut pas être vide');
}

try {
    // Charger la ligne de commande
    $sql = "SELECT fk_commande FROM " . MAIN_DB_PREFIX . "commandedet WHERE rowid = " . ((int) $rowid);
    $resql = $db->query($sql);
    
    if (!$resql) {
        sendJsonResponse(false, null, 'Erreur lors de la récupération de la ligne: ' . $db->lasterror());
    }
    
    $obj = $db->fetch_object($resql);
    if (!$obj) {
        sendJsonResponse(false, null, 'Ligne de commande introuvable');
    }
    
    $fk_commande = $obj->fk_commande;
    
    // Vérifier les permissions sur cette commande
    $commande = new Commande($db);
    $result = $commande->fetch($fk_commande);
    
    if ($result < 0) {
        sendJsonResponse(false, null, 'Erreur lors du chargement de la commande: ' . $commande->error);
    }
    
    // Vérifier que l'utilisateur a le droit de modifier cette commande
    if (!$user->hasRight('commande', 'creer')) {
        sendJsonResponse(false, null, 'Vous n\'avez pas le droit de modifier cette commande');
    }
    
    // CORRECTION : Vérifier si l'extrafield existe déjà
    $sql = "SELECT COUNT(*) as nb FROM " . MAIN_DB_PREFIX . "commandedet_extrafields 
            WHERE fk_object = " . ((int) $rowid);
    $resql = $db->query($sql);
    
    if (!$resql) {
        sendJsonResponse(false, null, 'Erreur lors de la vérification de l\'extrafield: ' . $db->lasterror());
    }
    
    $obj_count = $db->fetch_object($resql);
    $extrafield_exists = ($obj_count->nb > 0);
    
    dol_syslog("CORRECTION: rowid=$rowid, extrafield_exists=" . ($extrafield_exists ? 'OUI' : 'NON') . ", new_title=$new_title", LOG_INFO);
    
    if ($extrafield_exists) {
        // UPDATE si l'extrafield existe
        $sql = "UPDATE " . MAIN_DB_PREFIX . "commandedet_extrafields 
                SET ref_chantier = '" . $db->escape($new_title) . "'
                WHERE fk_object = " . ((int) $rowid);
        
        $resql = $db->query($sql);
        
        if (!$resql) {
            sendJsonResponse(false, null, 'Erreur lors de la mise à jour: ' . $db->lasterror());
        }
        
        dol_syslog("UPDATE effectué pour rowid=$rowid avec succès", LOG_INFO);
    } else {
        // INSERT si l'extrafield n'existe pas
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "commandedet_extrafields (fk_object, ref_chantier)
                VALUES (" . ((int) $rowid) . ", '" . $db->escape($new_title) . "')";
        
        $resql = $db->query($sql);
        
        if (!$resql) {
            sendJsonResponse(false, null, 'Erreur lors de la création de l\'extrafield: ' . $db->lasterror());
        }
        
        dol_syslog("INSERT effectué pour rowid=$rowid avec succès", LOG_INFO);
    }
    
    // Vérification finale : lire la valeur sauvegardée
    $sql = "SELECT ref_chantier FROM " . MAIN_DB_PREFIX . "commandedet_extrafields 
            WHERE fk_object = " . ((int) $rowid);
    $resql = $db->query($sql);
    
    if ($resql) {
        $obj_verify = $db->fetch_object($resql);
        if ($obj_verify) {
            dol_syslog("VERIFICATION: Valeur en base pour rowid=$rowid : " . $obj_verify->ref_chantier, LOG_INFO);
        }
    }
    
    // Log de succès
    dol_syslog("Titre de carte sauvegardé avec succès pour rowid=$rowid: $new_title", LOG_INFO);
    
    // Réponse de succès
    sendJsonResponse(true, array(
        'rowid' => $rowid,
        'new_title' => $new_title,
        'extrafield_existed' => $extrafield_exists,
        'operation' => $extrafield_exists ? 'UPDATE' : 'INSERT',
        'message' => 'Titre sauvegardé avec succès'
    ));
    
} catch (Exception $e) {
    sendJsonResponse(false, null, 'Erreur: ' . $e->getMessage());
}
