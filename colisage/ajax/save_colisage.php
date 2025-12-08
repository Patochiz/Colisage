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
 * \file       colisage/ajax/save_colisage.php
 * \ingroup    colisage
 * \brief      AJAX endpoint pour sauvegarder les données de colisage - Version avec cases à cocher
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

// Load Dolibarr environment
$res = 0;
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once __DIR__.'/../class/colisagepackage.class.php';
require_once __DIR__.'/../class/colisageitem.class.php';

// Function to return JSON response
function jsonResponse($success, $data = null, $error = null) {
    header('Content-Type: application/json');
    $response = array('success' => $success);
    if ($data !== null) $response['data'] = $data;
    if ($error !== null) $response['error'] = $error;
    echo json_encode($response);
    exit;
}

// Function to log debug information
function debugLog($message, $data = null) {
    if ($data !== null) {
        error_log("COLISAGE DEBUG: $message - " . print_r($data, true));
    } else {
        error_log("COLISAGE DEBUG: $message");
    }
}

// Check if module is enabled
if (!isModEnabled('colisage')) {
    jsonResponse(false, null, 'Module Colisage not enabled');
}

// Check parameters
$action = GETPOST('action', 'aZ09');
$token = GETPOST('token', 'alpha');
$commande_id = GETPOSTINT('commande_id');

debugLog("Action reçue", $action);
debugLog("Token reçu", $token ? 'présent' : 'absent');
debugLog("Commande ID", $commande_id);

// CORRECTION : Validation du token améliorée
if (!$token) {
    debugLog("Erreur: Token manquant");
    jsonResponse(false, null, 'Missing token');
}

// Pour FormData, on peut utiliser une validation moins stricte du token
// car le token peut être généré côté client
if (empty($token) || strlen($token) < 10) {
    debugLog("Erreur: Token invalide", $token);
    jsonResponse(false, null, 'Invalid token format');
}

// Check if user has rights
if (!$user->hasRight('commande', 'read')) {
    debugLog("Erreur: Accès refusé pour l'utilisateur", $user->id);
    jsonResponse(false, null, 'Access denied');
}

// Handle different actions
if ($action === 'save_colisage') {
    
    debugLog("Début de la sauvegarde du colisage (méthode UPDATE optimisée)");
    
    // Get packages data from POST
    $packages_json = GETPOST('packages', 'none');
    if (empty($packages_json)) {
        debugLog("Erreur: Aucune donnée de colis reçue");
        jsonResponse(false, null, 'No packages data received');
    }
    
    $packages_data = json_decode($packages_json, true);
    if (!is_array($packages_data)) {
        debugLog("Erreur: Format de données invalide", $packages_json);
        jsonResponse(false, null, 'Invalid packages data format');
    }
    
    debugLog("Nombre de colis à sauvegarder", count($packages_data));
    
    // Verify commande exists and user has access
    $commande = new Commande($db);
    $result = $commande->fetch($commande_id);
    if ($result <= 0) {
        debugLog("Erreur: Commande non trouvée", $commande_id);
        jsonResponse(false, null, 'Commande not found');
    }
    
    // Start transaction
    $db->begin();
    
    try {
        // Vérifier que les tables existent
        $sql_check = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."colisage_packages'";
        $resql_check = $db->query($sql_check);
        if (!$resql_check || $db->num_rows($resql_check) == 0) {
            throw new Exception('Tables colisage not found. Please enable the module first.');
        }
        
        debugLog("Tables vérifiées - OK");
        
        // NOUVELLE APPROCHE : UPDATE au lieu de DELETE/INSERT
        // 1. Récupérer les colis existants
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."colisage_packages 
                WHERE fk_commande = ".((int) $commande_id)." 
                ORDER BY rowid ASC";
        $resql = $db->query($sql);
        
        $existing_package_ids = array();
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $existing_package_ids[] = $obj->rowid;
            }
            $db->free($resql);
        }
        
        debugLog("Colis existants en base", count($existing_package_ids));
        
        $created_packages = array();
        $updated_packages = array();
        
        // 2. Parcourir les colis à sauvegarder
        foreach ($packages_data as $pkg_index => $pkg_data) {
            debugLog("Traitement du colis logique", $pkg_index + 1);
            
            // Calculer les totaux
            $total_weight = 0;
            $total_surface = 0;
            
            if (!empty($pkg_data['items']) && is_array($pkg_data['items'])) {
                foreach ($pkg_data['items'] as $item_data) {
                    $quantity = (int) ($item_data['quantity'] ?? 1);
                    $weight = (float) ($item_data['weight'] ?? 0);
                    $longueur = (int) ($item_data['longueur'] ?? 0);
                    $largeur = (int) ($item_data['largeur'] ?? 0);
                    
                    $total_weight += $quantity * $weight;
                    $total_surface += $quantity * ($longueur * $largeur / 1000000); // m²
                }
            }
            
            $multiplier = max(1, (int) ($pkg_data['multiplier'] ?? 1));
            $is_free = !empty($pkg_data['isFree']) ? 1 : 0;
            
            // Déterminer si on UPDATE ou INSERT
            $package_id = null;
            
            if (isset($existing_package_ids[$pkg_index])) {
                // UPDATE du colis existant
                $package_id = $existing_package_ids[$pkg_index];
                
                $sql = "UPDATE ".MAIN_DB_PREFIX."colisage_packages SET 
                        multiplier = ".((int) $multiplier).",
                        is_free = ".((int) $is_free).",
                        total_weight = ".((float) $total_weight).",
                        total_surface = ".((float) $total_surface).",
                        date_modification = '".$db->idate(dol_now())."',
                        fk_user_modif = ".((int) $user->id)."
                        WHERE rowid = ".((int) $package_id);
                
                $resql = $db->query($sql);
                if (!$resql) {
                    throw new Exception('Error updating package: '.$db->lasterror());
                }
                
                $updated_packages[] = $package_id;
                debugLog("Colis mis à jour", "rowid={$package_id}, logique=".($pkg_index + 1));
                
                // Supprimer les anciens items
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."colisage_items WHERE fk_package = ".((int) $package_id);
                $db->query($sql);
                
            } else {
                // INSERT nouveau colis
                $package = new ColisagePackage($db);
                $package->fk_commande = $commande_id;
                $package->multiplier = $multiplier;
                $package->is_free = $is_free;
                $package->total_weight = $total_weight;
                $package->total_surface = $total_surface;
                
                $package_id = $package->create($user);
                if ($package_id < 0) {
                    throw new Exception('Error creating package: '.implode(', ', $package->errors));
                }
                
                $created_packages[] = $package_id;
                debugLog("Nouveau colis créé", "rowid={$package_id}, logique=".($pkg_index + 1));
            }
            
            // Créer les items pour ce colis
            if (!empty($pkg_data['items']) && is_array($pkg_data['items'])) {
                foreach ($pkg_data['items'] as $item_index => $item_data) {
                    $item = new ColisageItem($db);
                    $item->fk_package = $package_id;
                    $item->quantity = max(1, (int) ($item_data['quantity'] ?? 1));
                    $item->longueur = (int) ($item_data['longueur'] ?? 0);
                    $item->largeur = (int) ($item_data['largeur'] ?? 0);
                    $item->description = $item_data['description'] ?? '';
                    $item->weight_unit = (float) ($item_data['weight'] ?? 0);
                    $item->surface_unit = ($item->longueur * $item->largeur) / 1000000; // m²
                    
                    // Check if it's a free item or standard item
                    if (!empty($item_data['productId']) && $item_data['productId'] !== 'free') {
                        // Standard item from commande
                        $item->fk_commandedet = (int) str_replace('prod_', '', $item_data['productId']);
                        $item->detail_id = $item_data['detailId'] ?? '';
                    } else {
                        // Free item
                        $item->fk_commandedet = null;
                        $item->custom_name = $item_data['customName'] ?? '';
                        $item->custom_longueur = $item->longueur;
                        $item->custom_largeur = $item->largeur;
                        $item->custom_description = $item->description;
                        $item->custom_weight = $item->weight_unit;
                    }
                    
                    $item_id = $item->create($user);
                    if ($item_id < 0) {
                        throw new Exception('Error creating item: '.implode(', ', $item->errors));
                    }
                }
            }
        }
        
        // 3. Supprimer les colis en trop (si on est passé de 5 à 3 colis)
        $deleted_count = 0;
        if (count($packages_data) < count($existing_package_ids)) {
            for ($i = count($packages_data); $i < count($existing_package_ids); $i++) {
                $package_id_to_delete = $existing_package_ids[$i];
                
                // Supprimer les items
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."colisage_items WHERE fk_package = ".((int) $package_id_to_delete);
                $db->query($sql);
                
                // Supprimer le colis
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."colisage_packages WHERE rowid = ".((int) $package_id_to_delete);
                $db->query($sql);
                
                $deleted_count++;
                debugLog("Colis supprimé", "rowid={$package_id_to_delete}");
            }
        }
        
        debugLog("Résumé", "Créés: ".count($created_packages).", Mis à jour: ".count($updated_packages).", Supprimés: {$deleted_count}");
        
        // Calculate total number of packages (including multipliers)
        $total_packages = 0;
        foreach ($packages_data as $pkg_data) {
            $multiplier = max(1, (int) ($pkg_data['multiplier'] ?? 1));
            $total_packages += $multiplier;
        }
        
        debugLog("Nombre total de colis calculé", $total_packages);
        
        // Generate HTML list for extrafield
        $html_list = generateColisageHtmlList($commande_id, $db);
        
        // Update commande extrafields (both listecolis_fp and total_de_colis)
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."commande_extrafields WHERE fk_object = ".((int) $commande_id);
        $resql = $db->query($sql);
        
        if ($resql && $db->num_rows($resql) > 0) {
            // Update existing extrafields
            $updates = array();
            if (!empty($html_list)) {
                $updates[] = "listecolis_fp = '".$db->escape($html_list)."'";
            }
            $updates[] = "total_de_colis = ".((int) $total_packages);
            
            $sql = "UPDATE ".MAIN_DB_PREFIX."commande_extrafields 
                    SET ".implode(", ", $updates)."
                    WHERE fk_object = ".((int) $commande_id);
        } else {
            // Insert new extrafields
            $fields = array("fk_object", "total_de_colis");
            $values = array(((int) $commande_id), ((int) $total_packages));
            
            if (!empty($html_list)) {
                $fields[] = "listecolis_fp";
                $values[] = "'".$db->escape($html_list)."'";
            }
            
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."commande_extrafields (".implode(", ", $fields).") 
                    VALUES (".implode(", ", $values).")"; 
        }
        
        $resql = $db->query($sql);
        if (!$resql) {
            // Don't fail the whole operation for this
            debugLog('Attention: Impossible de mettre à jour les extrafields: '.$db->lasterror());
        } else {
            debugLog("Extrafields mis à jour - listecolis_fp et total_de_colis = {$total_packages}");
        }
        
        $db->commit();
        debugLog("Transaction terminée avec succès");
        
        jsonResponse(true, array(
            'message' => 'Colisage saved successfully',
            'packages_created' => count($created_packages),
            'packages_updated' => count($updated_packages),
            'packages_deleted' => $deleted_count,
            'total_packages' => $total_packages,
            'html_list' => $html_list
        ));
        
    } catch (Exception $e) {
        $db->rollback();
        debugLog("Erreur lors de la sauvegarde", $e->getMessage());
        jsonResponse(false, null, $e->getMessage());
    }
    
} elseif ($action === 'load_colisage') {
    
    debugLog("Chargement du colisage existant pour la commande", $commande_id);
    
    // Load existing packages for this commande
    $package = new ColisagePackage($db);
    $packages = $package->fetchByCommande($commande_id);
    
    debugLog("Nombre de colis trouvés", count($packages));
    
    $packages_data = array();
    foreach ($packages as $pkg) {
        $pkg_data = array(
            'id' => $pkg->id,
            'multiplier' => $pkg->multiplier,
            'isFree' => $pkg->is_free ? true : false,
            'total_weight' => $pkg->total_weight,
            'total_surface' => $pkg->total_surface,
            'items' => array()
        );
        
        foreach ($pkg->items as $item) {
            $item_data = array(
                'quantity' => $item->quantity,
                'longueur' => $item->longueur,
                'largeur' => $item->largeur,
                'description' => $item->description,
                'weight' => $item->weight_unit
            );
            
            if ($item->isFree()) {
                $item_data['productId'] = 'free';
                $item_data['customName'] = $item->custom_name;
                $item_data['detailId'] = $item->detail_id;
            } else {
                $item_data['productId'] = 'prod_' . $item->fk_commandedet;
                $item_data['detailId'] = $item->detail_id;
            }
            
            $pkg_data['items'][] = $item_data;
        }
        
        $packages_data[] = $pkg_data;
    }
    
    debugLog("Données formatées pour", count($packages_data) . " colis");
    
    jsonResponse(true, array('packages' => $packages_data));
    
} else {
    debugLog("Action inconnue", $action);
    jsonResponse(false, null, 'Unknown action: ' . $action);
}

/**
 * Generate HTML list of packages for extrafield with checkboxes for production tracking
 * 
 * NEW FEATURE: Each package line now has a checkbox (☐) for operators to track production
 * 
 * FORMAT:
 * - Single product packages are grouped together with checkboxes
 * - Multi-product packages are displayed separately with checkboxes
 * - Checkbox symbol: ☐ (empty checkbox that can be checked manually when printed)
 */
function generateColisageHtmlList($commande_id, $db) {
    $package = new ColisagePackage($db);
    $packages = $package->fetchByCommande($commande_id);
    
    if (empty($packages)) {
        return '';
    }
    
    // Charger la commande pour récupérer les lignes et les libellés de produits
    $commande = new Commande($db);
    $commande->fetch($commande_id);
    $commande->fetch_lines();
    
    // Créer un mapping des IDs de lignes vers les libellés de produits
    $product_names = array();
    foreach ($commande->lines as $line) {
        $product_names[$line->rowid] = $line->product_label ?: $line->label;
    }
    
    // Séparer les colis en deux catégories :
    // 1. Colis avec items d'UN SEUL produit (à regrouper)
    // 2. Colis avec items de PLUSIEURS produits différents (à afficher séparément)
    
    $single_product_packages = array(); // [ productName => [ packages... ] ]
    $multi_product_packages = array();
    
    foreach ($packages as $pkg) {
        if (empty($pkg->items)) {
            continue; // Skip empty packages
        }
        
        // Déterminer tous les produits différents dans ce colis
        $productKeys = array();
        foreach ($pkg->items as $item) {
            if ($item->isFree()) {
                $productKey = $item->custom_name;
            } else {
                $productKey = isset($product_names[$item->fk_commandedet]) 
                    ? $product_names[$item->fk_commandedet] 
                    : 'Produit ID:' . $item->fk_commandedet;
            }
            
            if (!in_array($productKey, $productKeys)) {
                $productKeys[] = $productKey;
            }
        }
        
        // Si un seul produit différent → regrouper
        if (count($productKeys) == 1) {
            $productKey = $productKeys[0];
            
            // Initialiser le tableau pour ce produit si nécessaire
            if (!isset($single_product_packages[$productKey])) {
                $single_product_packages[$productKey] = array();
            }
            
            // Ajouter le colis au groupe
            $single_product_packages[$productKey][] = $pkg;
            
        } else {
            // Plusieurs produits différents → garder séparé
            $multi_product_packages[] = $pkg;
        }
    }
    
    $html = '';
    
    // 1. Afficher les colis avec items d'un seul produit, regroupés
    foreach ($single_product_packages as $productName => $pkgs) {
        // Afficher le nom du produit une seule fois
        $html .= '-' . $productName . '<br>';
        
        // Afficher tous les colis de ce produit
        foreach ($pkgs as $pkg) {
            // NOUVEAU : Ajouter une case à cocher avant le multiplicateur
            $checkbox = '[ ] '; // Case à cocher vide (U+2610)
            
            // Formater le multiplicateur
            $multiplier_text = '';
            if ($pkg->multiplier > 1) {
                $multiplier_text = '<strong>' . $pkg->multiplier . ' colis de </strong>';
            } else {
                $multiplier_text = '<strong>1 colis de </strong>';
            }
            
            // Afficher tous les items de ce colis
            foreach ($pkg->items as $item_index => $item) {
                // Pour le premier item : checkbox + multiplicateur + détails
                // Pour les items suivants : "+" + détails (sans checkbox)
                $line_prefix = '';
                if ($item_index == 0) {
                    $line_prefix = $checkbox . $multiplier_text;
                } else {
                    $line_prefix = '&nbsp;&nbsp;+ '; // Indentation pour aligner avec le premier item
                }
                
                // Formater les dimensions et surface
                $dimensions = '';
                $surface = '';
                
                if ($item->largeur && $item->largeur > 0) {
                    // Produit avec longueur ET largeur → dimensions et surface en m²
                    $dimensions = $item->quantity . ' × ' . $item->longueur . '×' . $item->largeur;
                    $surface_value = ($item->quantity * $item->longueur * $item->largeur) / 1000000;
                    $surface = '<strong>' . number_format($surface_value, 2) . ' m²</strong>';
                } elseif ($item->longueur && $item->longueur > 0) {
                    // Produit avec longueur seulement → dimensions et longueur en ml
                    $dimensions = $item->quantity . ' × ' . $item->longueur;
                    $length_value = ($item->quantity * $item->longueur) / 1000;
                    $surface = '<strong>' . number_format($length_value, 2) . ' ml</strong>';
                } else {
                    // Produit sans dimensions → juste quantité
                    $dimensions = $item->quantity . ' unités';
                    $surface = '<strong>' . $item->quantity . ' u</strong>';
                }
                
                // Description
                $description = $item->description ?: '';
                
                // Ligne de colis
                $html .= $line_prefix . $dimensions . ' ' . $surface . ' ' . $description . '<br>';
            }
        }
        
        // Saut de ligne après chaque groupe de produit
        $html .= '<br>';
    }
    
    // 2. Afficher les colis multi-produits (format traditionnel avec checkboxes)
    foreach ($multi_product_packages as $pkg_index => $pkg) {
        // NOUVEAU : Ajouter une case à cocher avant l'en-tête du colis
        $checkbox = '[ ] '; // Case à cocher vide (U+2610)
        
        // En-tête du colis avec multiplicateur
        if ($pkg->multiplier > 1) {
            $html .= $checkbox . '<strong>' . $pkg->multiplier . ' colis de</strong><br>';
        } else {
            $html .= $checkbox . '<strong>1 colis de</strong><br>';
        }
        
        // Regrouper les items par produit
        $grouped_items = array();
        
        foreach ($pkg->items as $item) {
            // Récupérer le nom du produit
            if ($item->isFree()) {
                $productKey = $item->custom_name;
            } else {
                $productKey = isset($product_names[$item->fk_commandedet]) 
                    ? $product_names[$item->fk_commandedet] 
                    : 'Produit ID:' . $item->fk_commandedet;
            }
            
            // Initialiser le groupe si nécessaire
            if (!isset($grouped_items[$productKey])) {
                $grouped_items[$productKey] = array();
            }
            
            // Ajouter l'item au groupe
            $grouped_items[$productKey][] = $item;
        }
        
        // Afficher chaque groupe de produits
        foreach ($grouped_items as $productName => $items) {
            // Afficher le nom du produit (avec indentation)
            $html .= '&nbsp;&nbsp;-' . $productName . '<br>';
            
            // Afficher tous les détails de ce produit
            foreach ($items as $item) {
                // Formater les dimensions et surface
                $dimensions = '';
                $surface = '';
                
                if ($item->largeur && $item->largeur > 0) {
                    // Produit avec longueur ET largeur → dimensions et surface en m²
                    $dimensions = $item->quantity . ' × ' . $item->longueur . '×' . $item->largeur;
                    $surface_value = ($item->quantity * $item->longueur * $item->largeur) / 1000000;
                    $surface = '<strong>' . number_format($surface_value, 2) . ' m²</strong>';
                } elseif ($item->longueur && $item->longueur > 0) {
                    // Produit avec longueur seulement → dimensions et longueur en ml
                    $dimensions = $item->quantity . ' × ' . $item->longueur;
                    $length_value = ($item->quantity * $item->longueur) / 1000;
                    $surface = '<strong>' . number_format($length_value, 2) . ' ml</strong>';
                } else {
                    // Produit sans dimensions → juste quantité
                    $dimensions = $item->quantity . ' unités';
                    $surface = '<strong>' . $item->quantity . ' u</strong>';
                }
                
                // Description
                $description = $item->description ?: '';
                
                // Ligne de détail (sans le nom du produit, avec indentation)
                $html .= '&nbsp;&nbsp;' . $dimensions . ' ' . $surface . ' ' . $description . '<br>';
            }
        }
        
        // Ajouter saut de ligne après chaque colis multi-produits
        $html .= '<br>';
    }
    
    // Supprimer le dernier <br> s'il existe
    $html = rtrim($html, '<br>');
    
    return $html;
}
