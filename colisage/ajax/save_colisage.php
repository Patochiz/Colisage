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
 * Generate HTML list of packages for extrafield with hierarchical structure
 *
 * NEW HIERARCHY:
 * - Titre (Section)
 *   - Produit
 *     - Colis (with checkboxes)
 *
 * MULTI-REF HANDLING:
 * - If a package contains products from multiple sections, it goes to "Multi Ref" section
 */
function generateColisageHtmlList($commande_id, $db) {
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

    $package = new ColisagePackage($db);
    $packages = $package->fetchByCommande($commande_id);

    debugLog("generateColisageHtmlList - Nombre de colis chargés", count($packages));

    if (empty($packages)) {
        return '';
    }

    // Charger la commande pour récupérer les lignes
    $commande = new Commande($db);
    $commande->fetch($commande_id);
    $commande->fetch_lines();

    // 1. Construire la structure des sections (comme dans colisage_tab.php)
    $sections = array();
    $current_section = null;
    $produits_avant_premier_titre = array();

    foreach ($commande->lines as $line) {
        // Détecter les titres de section (Service ID=361)
        if ($line->fk_product == 361 && $line->product_type == 1) {
            // Sauvegarder la section précédente
            if ($current_section !== null) {
                $sections[] = $current_section;
            }

            // Récupérer le titre
            $ref_chantier = '';
            if (!empty($line->array_options['options_ref_chantier'])) {
                $ref_chantier = $line->array_options['options_ref_chantier'];
            }

            $titre_affiche = !empty($ref_chantier) ? $ref_chantier : (!empty($line->desc) ? $line->desc : (!empty($line->description) ? $line->description : ''));

            // Créer nouvelle section
            $current_section = array(
                'titre' => $titre_affiche,
                'produits' => array() // rowid => product_name
            );

            continue;
        }

        // Traiter les produits physiques
        if (empty($line->product_type) || $line->product_type == 0) {
            $product_name = $line->product_label ?: $line->label;

            if ($current_section !== null) {
                $current_section['produits'][$line->rowid] = $product_name;
            } else {
                $produits_avant_premier_titre[$line->rowid] = $product_name;
            }
        }
    }

    // Sauvegarder la dernière section
    if ($current_section !== null) {
        $sections[] = $current_section;
    }

    // 2. Mapper chaque commandedet_id vers sa section_index
    $commandedet_to_section = array(); // commandedet_id => section_index (-1 = avant premier titre, null = libre)

    foreach ($produits_avant_premier_titre as $rowid => $name) {
        $commandedet_to_section[$rowid] = -1;
    }

    foreach ($sections as $section_index => $section) {
        foreach ($section['produits'] as $rowid => $name) {
            $commandedet_to_section[$rowid] = $section_index;
        }
    }

    // 3. Classifier les colis par section (et détecter Multi-Ref)
    $packages_by_section = array();
    $packages_by_section[-1] = array(); // Produits avant le premier titre
    $packages_by_section['multi_ref'] = array(); // Colis multi-sections

    foreach ($sections as $index => $section) {
        $packages_by_section[$index] = array();
    }

    foreach ($packages as $pkg) {
        if (empty($pkg->items)) {
            continue;
        }

        // Déterminer toutes les sections de ce colis
        $sections_in_package = array();

        foreach ($pkg->items as $item) {
            if ($item->isFree()) {
                // Produit libre → pas de section
                $sections_in_package[] = null;
            } else {
                $section_index = isset($commandedet_to_section[$item->fk_commandedet])
                    ? $commandedet_to_section[$item->fk_commandedet]
                    : null;

                if ($section_index !== null && !in_array($section_index, $sections_in_package)) {
                    $sections_in_package[] = $section_index;
                }
            }
        }

        // Enlever les null
        $sections_in_package = array_filter($sections_in_package, function($s) { return $s !== null; });
        $sections_in_package = array_unique($sections_in_package);

        // Si plusieurs sections → Multi-Ref
        if (count($sections_in_package) > 1) {
            $packages_by_section['multi_ref'][] = $pkg;
        } elseif (count($sections_in_package) == 1) {
            $section_index = reset($sections_in_package);
            $packages_by_section[$section_index][] = $pkg;
        }
        // Si aucune section (produits libres) → ignorer pour l'instant
    }

    // 4. Générer le HTML avec la hiérarchie Titre > Produit > Colis
    $html = '';

    // Créer mapping product_names pour tous les produits
    $product_names = array();
    foreach ($commande->lines as $line) {
        $product_names[$line->rowid] = $line->product_label ?: $line->label;
    }

    // Afficher les sections dans l'ordre
    $displayed_sections = array();

    // Produits avant le premier titre (si présents)
    if (!empty($packages_by_section[-1])) {
        $displayed_sections[] = array('index' => -1, 'titre' => null, 'packages' => $packages_by_section[-1]);
    }

    // Sections avec titres
    foreach ($sections as $index => $section) {
        if (!empty($packages_by_section[$index])) {
            $displayed_sections[] = array('index' => $index, 'titre' => $section['titre'], 'packages' => $packages_by_section[$index]);
        }
    }

    // Multi-Ref (si présents)
    if (!empty($packages_by_section['multi_ref'])) {
        $displayed_sections[] = array('index' => 'multi_ref', 'titre' => 'Multi Ref', 'packages' => $packages_by_section['multi_ref']);
    }

    debugLog("generateColisageHtmlList - Nombre de sections affichées", count($displayed_sections));
    foreach ($displayed_sections as $idx => $sec) {
        debugLog("Section {$idx}", "Titre: " . ($sec['titre'] ?: 'NULL') . ", Colis: " . count($sec['packages']));
    }

    // Compteur de lignes pour la pagination
    $line_count = 0;
    $first_separator = 40;  // Premier séparateur après 40 lignes
    $next_separators = 60;   // Suivants tous les 60 lignes
    $next_separator_at = $first_separator;

    // Pour chaque section
    foreach ($displayed_sections as $section_data) {
        // Afficher le titre de section si présent
        if ($section_data['titre']) {
            // Vérifier si on doit insérer un séparateur AVANT le titre
            if ($line_count >= $next_separator_at) {
                $html .= '<hr /><br />';
                $next_separator_at += $next_separators;
            }
            $html .= '<strong style="font-size: 1.1em; color: #667eea;">-' . htmlspecialchars($section_data['titre']) . '</strong><br>';
            $line_count++;
        }

        // Regrouper les colis par produit dans cette section
        $packages_by_product = array();

        foreach ($section_data['packages'] as $pkg) {
            debugLog("Traitement du colis", "ID: {$pkg->id}, Items: " . count($pkg->items));

            // Pour chaque item dans le colis
            foreach ($pkg->items as $item) {
                if ($item->isFree()) {
                    $product_key = $item->custom_name;
                } else {
                    $product_key = isset($product_names[$item->fk_commandedet])
                        ? $product_names[$item->fk_commandedet]
                        : 'Produit ID:' . $item->fk_commandedet;
                }

                debugLog("  Item", "fk_commandedet: {$item->fk_commandedet}, Produit: {$product_key}");

                if (!isset($packages_by_product[$product_key])) {
                    $packages_by_product[$product_key] = array();
                }

                // Vérifier si ce colis n'est pas déjà dans la liste pour ce produit
                $already_added = false;
                foreach ($packages_by_product[$product_key] as $existing_pkg) {
                    if ($existing_pkg->id == $pkg->id) {
                        $already_added = true;
                        break;
                    }
                }

                if (!$already_added) {
                    $packages_by_product[$product_key][] = $pkg;
                    debugLog("  Colis ajouté au produit", $product_key);
                } else {
                    debugLog("  Colis déjà présent pour ce produit", $product_key);
                }
            }
        }

        // Afficher chaque groupe de produits
        foreach ($packages_by_product as $product_name => $pkgs) {
            debugLog("Produit: {$product_name}", "Nombre de colis: " . count($pkgs));

            // Vérifier si on doit insérer un séparateur AVANT le nom du produit
            if ($line_count >= $next_separator_at) {
                $html .= '<hr /><br />';
                $next_separator_at += $next_separators;
            }

            // Afficher le nom du produit
            $html .= '<span style="color: #48bb78;">--' . htmlspecialchars($product_name) . '</span><br>';
            $line_count += 2;  // Les noms de produits comptent pour 2 lignes (souvent longs)

            // Afficher tous les colis de ce produit
            foreach ($pkgs as $pkg) {
                // Filtrer les items pour ne garder que ceux qui correspondent au produit actuel
                $filtered_items = array();
                foreach ($pkg->items as $item) {
                    $item_product_name = '';
                    if ($item->isFree()) {
                        $item_product_name = $item->custom_name;
                    } else {
                        $item_product_name = isset($product_names[$item->fk_commandedet])
                            ? $product_names[$item->fk_commandedet]
                            : 'Produit ID:' . $item->fk_commandedet;
                    }

                    // Ne garder que les items qui correspondent au produit actuel
                    if ($item_product_name === $product_name) {
                        $filtered_items[] = $item;
                    }
                }

                // Si aucun item ne correspond, passer au colis suivant
                if (empty($filtered_items)) {
                    debugLog("Colis ignoré (aucun item filtré)", "Colis ID: {$pkg->id}");
                    continue;
                }

                debugLog("Colis affiché", "Colis ID: {$pkg->id}, Items: " . count($filtered_items));

                // Formater le multiplicateur
                if ($pkg->multiplier > 1) {
                    $multiplier_text = '<strong>' . $pkg->multiplier . ' colis de </strong>';
                } else {
                    $multiplier_text = '<strong>1 colis de </strong>';
                }

                // Afficher les items filtrés de ce colis
                foreach ($filtered_items as $item_index => $item) {
                    // Vérifier si on doit insérer un séparateur AVANT cette ligne
                    if ($line_count >= $next_separator_at) {
                        $html .= '<hr /><br />';
                        $next_separator_at += $next_separators;
                    }

                    if ($item_index == 0) {
                        $line_prefix = '---' . $multiplier_text;
                    } else {
                        // 18 espaces + "+" pour aligner les détails verticalement
                        $line_prefix = str_repeat('&nbsp;', 18) . '+ ';
                    }

                    // Formater les dimensions et surface
                    $dimensions = '';
                    $surface = '';

                    if ($item->largeur && $item->largeur > 0) {
                        $dimensions = $item->quantity . ' × ' . $item->longueur . '×' . $item->largeur;
                        $surface_value = ($item->quantity * $item->longueur * $item->largeur) / 1000000;
                        $surface = '<strong>' . number_format($surface_value, 2) . ' m²</strong>';
                    } elseif ($item->longueur && $item->longueur > 0) {
                        $dimensions = $item->quantity . ' × ' . $item->longueur;
                        $length_value = ($item->quantity * $item->longueur) / 1000;
                        $surface = '<strong>' . number_format($length_value, 2) . ' ml</strong>';
                    } else {
                        $dimensions = $item->quantity . ' unités';
                        $surface = '<strong>' . $item->quantity . ' u</strong>';
                    }

                    $description = $item->description ?: '';
                    $html .= $line_prefix . $dimensions . ' ' . $surface . ' ' . $description . '<br>';
                    $line_count++;
                }
            }
        }

        $html .= '<br>'; // Saut de ligne après chaque section
    }

    // Supprimer le dernier <br> s'il existe
    $html = rtrim($html, '<br>');

    return $html;
}
