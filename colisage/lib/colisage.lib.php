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
 * \file       colisage/lib/colisage.lib.php
 * \ingroup    colisage
 * \brief      Library files with common functions for Colisage
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function colisageAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("colisage@colisage");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/colisage/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    /*
    $head[$h][0] = dol_buildpath("/colisage/admin/myobject_extrafields.php", 1);
    $head[$h][1] = $langs->trans("ExtraFields");
    $head[$h][2] = 'myobject_extrafields';
    $h++;
    */

    $head[$h][0] = dol_buildpath("/colisage/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //  'entity:+tabname:Title:@colisage:/colisage/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //  'entity:-tabname:Title:@colisage:/colisage/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, null, $head, $h, 'colisage@colisage');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'colisage@colisage', 'remove');

    return $head;
}

/**
 * Prepare colisage pages header for objects
 *
 * @param object $object Object
 * @return array
 */
function colisagePrepareHead($object)
{
    global $db, $langs, $conf;

    $langs->load("colisage@colisage");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/colisage/colisage_tab.php", 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("Colisage");
    $head[$h][2] = 'colisage';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //  'entity:+tabname:Title:@colisage:/colisage/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //  'entity:-tabname:Title:@colisage:/colisage/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'colisage@colisage');

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'colisage@colisage', 'remove');

    return $head;
}

/**
 * Return array of tabs to used on pages for third parties objects
 *
 * @param 	Societe		$object		Object company shown
 * @return 	array				Array of tabs
 */
function colisage_prepare_head($object)
{
    return colisagePrepareHead($object);
}

/**
 * Get colisage statistics
 *
 * @param int $fk_commande ID de la commande
 * @return array Statistiques du colisage
 */
function getColisageStats($fk_commande)
{
    global $db;

    $stats = array(
        'total_packages' => 0,
        'total_items' => 0,
        'total_weight' => 0.0,
        'total_surface' => 0.0
    );

    if (empty($fk_commande)) {
        return $stats;
    }

    // Compter les colis
    $sql = "SELECT COUNT(*) as nb_packages, SUM(multiplier) as total_packages,";
    $sql .= " SUM(total_weight * multiplier) as total_weight,";
    $sql .= " SUM(total_surface * multiplier) as total_surface";
    $sql .= " FROM ".MAIN_DB_PREFIX."colisage_packages";
    $sql .= " WHERE fk_commande = ".((int) $fk_commande);

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            $stats['total_packages'] = (int) $obj->total_packages;
            $stats['total_weight'] = (float) $obj->total_weight;
            $stats['total_surface'] = (float) $obj->total_surface;
        }
        $db->free($resql);
    }

    // Compter les articles
    $sql = "SELECT SUM(ci.quantity) as total_items";
    $sql .= " FROM ".MAIN_DB_PREFIX."colisage_items ci";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."colisage_packages cp ON cp.rowid = ci.fk_package";
    $sql .= " WHERE cp.fk_commande = ".((int) $fk_commande);

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            $stats['total_items'] = (int) $obj->total_items;
        }
        $db->free($resql);
    }

    return $stats;
}

/**
 * Update extrafields for commande with colisage data
 *
 * @param int $fk_commande ID de la commande
 * @return int 1 si OK, <0 si erreur
 */
function updateCommandeExtrafields($fk_commande)
{
    global $db, $conf;

    if (empty($fk_commande)) {
        return -1;
    }

    // Récupérer les statistiques
    $stats = getColisageStats($fk_commande);

    // Générer la liste des colis pour la fiche de production
    $listeColis = generateListeColisForFP($fk_commande);

    // Mettre à jour les extrafields
    $sql = "UPDATE ".MAIN_DB_PREFIX."commande_extrafields SET";
    $sql .= " total_de_colis = ".((int) $stats['total_packages']).",";
    $sql .= " listecolis_fp = '".$db->escape($listeColis)."'";
    $sql .= " WHERE fk_object = ".((int) $fk_commande);

    $resql = $db->query($sql);
    if (!$resql) {
        // Essayer de créer l'enregistrement s'il n'existe pas
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."commande_extrafields";
        $sql .= " (fk_object, total_de_colis, listecolis_fp)";
        $sql .= " VALUES (".((int) $fk_commande).", ".((int) $stats['total_packages']).", '".$db->escape($listeColis)."')";
        
        $resql = $db->query($sql);
        if (!$resql) {
            return -1;
        }
    }

    return 1;
}

/**
 * Generate HTML list of packages for production sheet
 *
 * @param int $fk_commande ID de la commande
 * @return string HTML content
 */
function generateListeColisForFP($fk_commande)
{
    global $db;

    if (empty($fk_commande)) {
        return '';
    }

    $html = '<div class="colisage-fp">';
    $html .= '<h4>Liste des Colis - Fiche de Production</h4>';

    // Récupérer tous les colis
    $sql = "SELECT rowid, multiplier, is_free, total_weight, total_surface";
    $sql .= " FROM ".MAIN_DB_PREFIX."colisage_packages";
    $sql .= " WHERE fk_commande = ".((int) $fk_commande);
    $sql .= " ORDER BY rowid ASC";

    $resql = $db->query($sql);
    if ($resql) {
        $package_num = 1;
        while ($obj = $db->fetch_object($resql)) {
            for ($i = 0; $i < $obj->multiplier; $i++) {
                $html .= '<div class="colis-item">';
                $html .= '<strong>Colis #'.$package_num.'</strong>';
                
                if ($obj->is_free) {
                    $html .= ' <em>(Colis libre)</em>';
                }
                
                $html .= '<br>';
                $html .= 'Poids: '.number_format($obj->total_weight, 3).' kg | ';
                $html .= 'Surface: '.number_format($obj->total_surface, 3).' m²';
                
                // Récupérer les articles du colis
                $html .= '<ul>';
                $sql2 = "SELECT quantity, description, longueur, largeur, custom_name";
                $sql2 .= " FROM ".MAIN_DB_PREFIX."colisage_items";
                $sql2 .= " WHERE fk_package = ".((int) $obj->rowid);
                $sql2 .= " ORDER BY rowid ASC";
                
                $resql2 = $db->query($sql2);
                if ($resql2) {
                    while ($item = $db->fetch_object($resql2)) {
                        $name = $item->custom_name ?: $item->description;
                        $dimensions = $item->longueur.'x'.$item->largeur.'mm';
                        $html .= '<li>'.$item->quantity.'x '.$name.' ('.$dimensions.')</li>';
                    }
                    $db->free($resql2);
                }
                $html .= '</ul>';
                $html .= '</div>';
                $package_num++;
            }
        }
        $db->free($resql);
    }

    $html .= '</div>';
    
    return $html;
}

/**
 * Get available products for colisage from commande
 *
 * @param int $fk_commande ID de la commande
 * @return array Array of available products with details
 */
function getAvailableProductsForColisage($fk_commande)
{
    global $db;

    $products = array();
    
    if (empty($fk_commande)) {
        return $products;
    }

    // Récupérer les lignes de commande avec les détails JSON
    $sql = "SELECT cd.rowid, cd.fk_product, cd.qty, cd.weight, cd.weight_units,";
    $sql .= " cd.description, cd.product_type, cd.detailjson,";
    $sql .= " p.label as product_label, p.weight as product_weight";
    $sql .= " FROM ".MAIN_DB_PREFIX."commandedet cd";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = cd.fk_product";
    $sql .= " WHERE cd.fk_commande = ".((int) $fk_commande);
    $sql .= " AND cd.product_type = 0"; // Seulement les produits physiques
    $sql .= " ORDER BY cd.rang, cd.rowid";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $detailjson = json_decode($obj->detailjson, true);
            
            if (!empty($detailjson) && is_array($detailjson)) {
                // Traiter chaque détail du JSON
                foreach ($detailjson as $detail) {
                    if (isset($detail['longueur']) && isset($detail['largeur']) && isset($detail['quantity'])) {
                        $detail_id = $detail['detail_id'] ?? uniqid();
                        
                        // Calculer les quantités déjà colisées
                        $sql_used = "SELECT SUM(quantity) as used_qty";
                        $sql_used .= " FROM ".MAIN_DB_PREFIX."colisage_items ci";
                        $sql_used .= " INNER JOIN ".MAIN_DB_PREFIX."colisage_packages cp ON cp.rowid = ci.fk_package";
                        $sql_used .= " WHERE ci.fk_commandedet = ".((int) $obj->rowid);
                        $sql_used .= " AND ci.detail_id = '".$db->escape($detail_id)."'";
                        $sql_used .= " AND cp.fk_commande = ".((int) $fk_commande);
                        
                        $resql_used = $db->query($sql_used);
                        $used_qty = 0;
                        if ($resql_used) {
                            $used_obj = $db->fetch_object($resql_used);
                            $used_qty = (int) $used_obj->used_qty;
                            $db->free($resql_used);
                        }
                        
                        $remaining_qty = (int) $detail['quantity'] - $used_qty;
                        
                        if ($remaining_qty > 0) {
                            $products[] = array(
                                'commandedet_id' => $obj->rowid,
                                'detail_id' => $detail_id,
                                'product_label' => $obj->product_label ?: $obj->description,
                                'description' => $detail['description'] ?? '',
                                'longueur' => (int) $detail['longueur'],
                                'largeur' => (int) $detail['largeur'],
                                'total_quantity' => (int) $detail['quantity'],
                                'used_quantity' => $used_qty,
                                'remaining_quantity' => $remaining_qty,
                                'weight' => (float) $obj->weight,
                                'weight_units' => (int) $obj->weight_units
                            );
                        }
                    }
                }
            } else {
                // Produit simple sans détails JSON
                $sql_used = "SELECT SUM(quantity) as used_qty";
                $sql_used .= " FROM ".MAIN_DB_PREFIX."colisage_items ci";
                $sql_used .= " INNER JOIN ".MAIN_DB_PREFIX."colisage_packages cp ON cp.rowid = ci.fk_package";
                $sql_used .= " WHERE ci.fk_commandedet = ".((int) $obj->rowid);
                $sql_used .= " AND cp.fk_commande = ".((int) $fk_commande);
                
                $resql_used = $db->query($sql_used);
                $used_qty = 0;
                if ($resql_used) {
                    $used_obj = $db->fetch_object($resql_used);
                    $used_qty = (int) $used_obj->used_qty;
                    $db->free($resql_used);
                }
                
                $remaining_qty = (int) $obj->qty - $used_qty;
                
                if ($remaining_qty > 0) {
                    $products[] = array(
                        'commandedet_id' => $obj->rowid,
                        'detail_id' => '',
                        'product_label' => $obj->product_label ?: $obj->description,
                        'description' => $obj->description,
                        'longueur' => 0, // À définir
                        'largeur' => 0,  // À définir
                        'total_quantity' => (int) $obj->qty,
                        'used_quantity' => $used_qty,
                        'remaining_quantity' => $remaining_qty,
                        'weight' => (float) $obj->weight,
                        'weight_units' => (int) $obj->weight_units
                    );
                }
            }
        }
        $db->free($resql);
    }

    return $products;
}

/**
 * Format weight with unit
 *
 * @param float $weight Weight value
 * @param int $unit_id Unit ID from c_units table
 * @return string Formatted weight with unit
 */
function formatWeightWithUnit($weight, $unit_id = null)
{
    global $db;
    
    if (empty($weight)) {
        return '0 kg';
    }
    
    $unit_label = 'kg';
    
    if (!empty($unit_id)) {
        $sql = "SELECT code, label FROM ".MAIN_DB_PREFIX."c_units";
        $sql .= " WHERE rowid = ".((int) $unit_id);
        $sql .= " AND type_duration = 'weight'";
        $sql .= " AND active = 1";
        
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            $unit_label = $obj->code ?: $obj->label;
            $db->free($resql);
        }
    }
    
    return number_format($weight, 3, ',', ' ').' '.$unit_label;
}

/**
 * Generate EBS printer files (.prj XML + .prv PNG) for all packages of a commande
 *
 * Text 1 : Contact SHIPPING / VILLE (CP)
 * Text 2 : Section title (ref_chantier)
 * Text 3 : Item details, format {qty}x{longueur} concatenated with +
 * LineDivider : positioned at max(width_text1, width_text2)
 * Text 3 x   : LineDivider x + 5 (divider width) + 5 (margin)
 *
 * @param int $fk_commande  ID de la commande
 * @return array|false      Associative array [filename => content_string] or false on error
 */
function generateColisageEBSFiles($fk_commande)
{
    global $db;

    if (empty($fk_commande)) {
        return false;
    }

    // ---------------------------------------------------------------
    // 1. Load commande
    // ---------------------------------------------------------------
    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
    $commande = new Commande($db);
    if ($commande->fetch((int) $fk_commande) <= 0) {
        return false;
    }

    // ---------------------------------------------------------------
    // 2. Build Text 1 from SHIPPING contact
    //    Format : "CONTACT / VILLE (DEPT)"
    // ---------------------------------------------------------------
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
    // 3. Build section map : commandedet_rowid => section_title
    //    Iterate order lines ordered by rang; each section header
    //    (fk_product=361, product_type=1) updates the current section.
    // ---------------------------------------------------------------
    $sectionMap = array(); // commandedet_rowid => section title string

    $sql  = "SELECT cd.rowid, cd.rang, cd.product_type, cd.fk_product, cd.description,";
    $sql .= " cde.ref_chantier";
    $sql .= " FROM ".MAIN_DB_PREFIX."commandedet cd";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet_extrafields cde ON cde.fk_object = cd.rowid";
    $sql .= " WHERE cd.fk_commande = ".((int) $fk_commande);
    $sql .= " ORDER BY cd.rang ASC, cd.rowid ASC";

    $resql = $db->query($sql);
    if ($resql) {
        $currentSection = '';
        while ($obj = $db->fetch_object($resql)) {
            // Section header detection (Service ID=361)
            if ((int) $obj->fk_product === 361 && (int) $obj->product_type === 1) {
                $currentSection = !empty($obj->ref_chantier) ? $obj->ref_chantier : $obj->description;
            }
            $sectionMap[(int) $obj->rowid] = $currentSection;
        }
        $db->free($resql);
    }

    // ---------------------------------------------------------------
    // 4. Load packages and generate one file per package entry
    // ---------------------------------------------------------------
    require_once __DIR__.'/../class/colisagepackage.class.php';

    $sql  = "SELECT rowid, multiplier FROM ".MAIN_DB_PREFIX."colisage_packages";
    $sql .= " WHERE fk_commande = ".((int) $fk_commande);
    $sql .= " ORDER BY rowid ASC";

    $resql = $db->query($sql);
    if (!$resql) {
        return false;
    }

    $files    = array();
    $colisNum = 1;

    while ($pkgRow = $db->fetch_object($resql)) {
        $package = new ColisagePackage($db);
        if ($package->fetch((int) $pkgRow->rowid) <= 0) {
            $colisNum++;
            continue;
        }

        // --- Determine section title (Text 2) ---
        $text2 = '';
        foreach ($package->items as $item) {
            if (!empty($item->fk_commandedet) && isset($sectionMap[(int) $item->fk_commandedet])) {
                $text2 = $sectionMap[(int) $item->fk_commandedet];
                break;
            }
        }

        // --- Build Text 3 : {qty}x{longueur} for each item, joined by + ---
        $text3Parts = array();
        foreach ($package->items as $item) {
            $longueur = (int) ($item->longueur ?: $item->custom_longueur);
            $qty      = (int) $item->quantity;
            if ($longueur > 0 && $qty > 0) {
                $text3Parts[] = $qty.'x'.$longueur;
            }
        }
        $text3 = implode('+', $text3Parts);

        // --- Calculate positions ---
        // Font_16x10 → 10 px/char  (Text 1, Text 3)
        // Font_12x7  →  7 px/char  (Text 2)
        $charWidthT1  = 10;
        $charWidthT2  = 7;
        $text1Width   = mb_strlen($text1, 'UTF-8') * $charWidthT1;
        $text2Width   = mb_strlen($text2, 'UTF-8') * $charWidthT2;
        $lineDividerX = max($text1Width, $text2Width);
        $text3X       = $lineDividerX + 5 + 5; // divider_width=5 + margin=5

        // --- Build filename ---
        $multiplier = max(1, (int) ($package->multiplier ?? 1));
        $filename   = 'colis'.$colisNum;
        if ($multiplier > 1) {
            $filename .= '_x'.$multiplier;
        }

        // --- Generate .prj XML ---
        $files[$filename.'.prj'] = _generateEBSPrjXml($text1, $text2, $text3, $lineDividerX, $text3X);

        // --- Generate .prv PNG ---
        $prvData = _generateEBSPrvPng($text1, $text2, $text3, $lineDividerX, $text3X);
        if ($prvData !== false) {
            $files[$filename.'.prv'] = $prvData;
        }

        $colisNum++;
    }
    $db->free($resql);

    return $files;
}

/**
 * Generate EBS printer project XML content (.prj)
 *
 * @param string $text1        Text 1 : Contact / VILLE (dept)
 * @param string $text2        Text 2 : Section title
 * @param string $text3        Text 3 : Item details
 * @param int    $lineDividerX X position of the line divider
 * @param int    $text3X       X position of Text 3 (and Text 4)
 * @return string              XML string
 */
function _generateEBSPrjXml($text1, $text2, $text3, $lineDividerX, $text3X)
{
    $t1 = htmlspecialchars($text1, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $t2 = htmlspecialchars($text2, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $t3 = htmlspecialchars($text3, ENT_QUOTES | ENT_XML1, 'UTF-8');

    $editorData = '<EditorData Field0="" Field1="" Field2="" Field3="" Field4="" Field5="" Field6="" Field7="" Field8="" Field9="" Field10="" Field11="" Field12="" Field13="" Field14="%s"/>';
    $printingParams = '<PrintingParams ExternParamsFile="" UseExternParamsFile="0" ImpulseGeneratorSource="1" TriggerType="0" TriggerSignalMode="0" PhotocellSource="0" Resolution="550" PrintDistance="0" TxtRepetitions="1" RepetitionDistance="0" RowMultiply="0" UpsideDownPrint="0" ReversePrint="0" ShaftDirection="0" TextHeight="0" CleaningRows="0" Pressure="35" DotSize="3"/>';

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<EBS_PrinterProject>'."\n";
    $xml .= '  <ProjectSettings DataFormat="10" PrintHeadName="EBS-Electromagnetic-32" FriendlyName="Undefined" w="1000" h="32" ObjectsCount="5" ManagerInfo="1" min_w="660"/>'."\n";
    $xml .= '  '.$printingParams."\n";

    // Text 1 — Contact / VILLE (dept) — top row, Font_16x10
    $xml .= '  <Object ObjectType="TextObject" ObjectName="Text 1" x="0" y="0" w="100" h="16" AutoSize="1" Transparent="1"';
    $xml .= ' Text="'.$t1.'"';
    $xml .= ' FontName="fonts/Default/Font_16x10.xml" FontSize="25" FontSizeY="25"';
    $xml .= ' FontBold="0" FontItalic="0" FontRotate="0" LineSpacing="1" LetterSpacing="1" RowMultiply="1"';
    $xml .= ' ObjectRotate="0" IsLinked="0" LinkedToObject="" MustEdit="0" Printable="1">'."\n";
    $xml .= '    '.sprintf($editorData, '1')."\n";
    $xml .= '  </Object>'."\n";

    // Text 2 — Section title — bottom row, Font_12x7
    $xml .= '  <Object ObjectType="TextObject" ObjectName="Text 2" x="0" y="17" w="40" h="12" AutoSize="1" Transparent="1"';
    $xml .= ' Text="'.$t2.'"';
    $xml .= ' FontName="fonts/Default/Font_12x7.xml" FontSize="7" FontSizeY="7"';
    $xml .= ' FontBold="0" FontItalic="0" FontRotate="0" LineSpacing="1" LetterSpacing="1" RowMultiply="1"';
    $xml .= ' ObjectRotate="0" IsLinked="0" LinkedToObject="" MustEdit="0" Printable="1">'."\n";
    $xml .= '    '.sprintf($editorData, '1')."\n";
    $xml .= '  </Object>'."\n";

    // Text 3 — Item details — top row after divider, Font_16x10
    $xml .= '  <Object ObjectType="TextObject" ObjectName="Text 3" x="'.$text3X.'" y="0" w="110" h="16" AutoSize="1" Transparent="1"';
    $xml .= ' Text="'.$t3.'"';
    $xml .= ' FontName="fonts/Default/Font_16x10.xml" FontSize="25" FontSizeY="25"';
    $xml .= ' FontBold="0" FontItalic="0" FontRotate="0" LineSpacing="1" LetterSpacing="1" RowMultiply="1"';
    $xml .= ' ObjectRotate="0" IsLinked="0" LinkedToObject="" MustEdit="0" Printable="1">'."\n";
    $xml .= '    '.sprintf($editorData, '1')."\n";
    $xml .= '  </Object>'."\n";

    // Text 4 — Empty, bottom row after divider, Font_16x10
    $xml .= '  <Object ObjectType="TextObject" ObjectName="Text 4" x="'.$text3X.'" y="17" w="60" h="7" AutoSize="1" Transparent="1"';
    $xml .= ' Text=""';
    $xml .= ' FontName="fonts/Default/Font_16x10.xml" FontSize="25" FontSizeY="25"';
    $xml .= ' FontBold="0" FontItalic="0" FontRotate="0" LineSpacing="1" LetterSpacing="1" RowMultiply="1"';
    $xml .= ' ObjectRotate="0" IsLinked="0" LinkedToObject="" MustEdit="0" Printable="1">'."\n";
    $xml .= '    '.sprintf($editorData, '1')."\n";
    $xml .= '  </Object>'."\n";

    // LineDivider — vertical separator
    $xml .= '  <Object ObjectType="SpecialObject" Type="0" ObjectName="LineDivider 1" x="'.$lineDividerX.'" y="0" w="5" h="32">'."\n";
    $xml .= '    '.sprintf($editorData, '')."\n";
    $xml .= '  </Object>'."\n";

    $xml .= '</EBS_PrinterProject>'."\n";

    return $xml;
}

/**
 * Generate EBS printer preview PNG image (.prv)
 *
 * Canvas : 1000 x 32 pixels (matches EBS project canvas)
 * White background, black text and divider.
 *
 * @param string $text1        Text 1
 * @param string $text2        Text 2
 * @param string $text3        Text 3
 * @param int    $lineDividerX X position of the vertical divider
 * @param int    $text3X       X position of Text 3
 * @return string|false        PNG binary data or false if GD unavailable
 */
function _generateEBSPrvPng($text1, $text2, $text3, $lineDividerX, $text3X)
{
    if (!function_exists('imagecreate')) {
        return false;
    }

    // GD built-in fonts don't handle multi-byte UTF-8; convert to ISO-8859-1
    $t1 = mb_convert_encoding($text1, 'ISO-8859-1', 'UTF-8');
    $t2 = mb_convert_encoding($text2, 'ISO-8859-1', 'UTF-8');
    $t3 = mb_convert_encoding($text3, 'ISO-8859-1', 'UTF-8');

    $img   = imagecreate(1000, 32);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);

    // White background (auto-filled as first allocated color)
    // Unused variable silenced:
    unset($white);
    imagefilledrectangle($img, 0, 0, 999, 31, imagecolorallocate($img, 255, 255, 255));

    // Font 5 : ~9px wide, 15px tall — closest match to Font_16x10
    // Font 3 : ~7px wide, 13px tall — closest match to Font_12x7
    imagestring($img, 5, 0, 0,  $t1, $black); // Text 1 — top row
    imagestring($img, 3, 0, 17, $t2, $black); // Text 2 — bottom row
    imagestring($img, 5, $text3X, 0, $t3, $black); // Text 3 — top row after divider

    // Vertical divider (5px wide)
    imagefilledrectangle($img, $lineDividerX, 0, $lineDividerX + 4, 31, $black);

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);

    return $data;
}

/**
 * Check if module is properly configured
 *
 * @return array Array with status and messages
 */
function checkColisageConfiguration()
{
    global $db, $conf;
    
    $status = array(
        'valid' => true,
        'messages' => array()
    );
    
    // Vérifier les tables
    $required_tables = array('colisage_packages', 'colisage_items');
    foreach ($required_tables as $table) {
        $sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX.$table."'";
        $resql = $db->query($sql);
        if (!$resql || $db->num_rows($resql) == 0) {
            $status['valid'] = false;
            $status['messages'][] = 'Table '.$table.' manquante';
        }
    }
    
    // Vérifier les extrafields
    include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($db);
    $extrafields->fetch_name_optionals_label('commande');
    
    $required_extrafields = array('total_de_colis', 'listecolis_fp');
    foreach ($required_extrafields as $field) {
        if (!isset($extrafields->attributes['commande']['label'][$field])) {
            $status['valid'] = false;
            $status['messages'][] = 'Champ supplémentaire '.$field.' manquant';
        }
    }
    
    return $status;
}
