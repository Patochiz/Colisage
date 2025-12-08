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
 * \file       colisage/class/colisageitem.class.php
 * \ingroup    colisage
 * \brief      Classe pour gérer les articles des colis
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Classe pour gérer les articles des colis
 */
class ColisageItem extends CommonObject
{
    /**
     * @var string Module name
     */
    public $module = 'colisage';

    /**
     * @var string Element name
     */
    public $element = 'colisageitem';

    /**
     * @var string Table name
     */
    public $table_element = 'colisage_items';

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int ID du colis parent
     */
    public $fk_package;

    /**
     * @var int ID de la ligne de commande (NULL pour articles libres)
     */
    public $fk_commandedet;

    /**
     * @var int Quantité
     */
    public $quantity = 1;

    /**
     * @var string ID du détail spécifique dans detailjson
     */
    public $detail_id;

    // Pour les articles libres
    /**
     * @var string Nom personnalisé pour articles libres
     */
    public $custom_name;

    /**
     * @var int Longueur personnalisée
     */
    public $custom_longueur;

    /**
     * @var int Largeur personnalisée
     */
    public $custom_largeur;

    /**
     * @var string Description personnalisée
     */
    public $custom_description;

    /**
     * @var float Poids personnalisé
     */
    public $custom_weight;

    // Données calculées/dupliquées
    /**
     * @var int Longueur en mm
     */
    public $longueur;

    /**
     * @var int Largeur en mm
     */
    public $largeur;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var float Poids unitaire
     */
    public $weight_unit = 0.0;

    /**
     * @var float Surface unitaire en m²
     */
    public $surface_unit = 0.0;

    /**
     * @var string Date de création
     */
    public $date_creation;

    /**
     * @var int ID utilisateur créateur
     */
    public $fk_user_creat;

    /**
     * @var array Cache for weight unit scales
     */
    private static $scaleCache = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create item in database
     *
     * @param User $user User that creates
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, Id of created object if OK
     */
    public function create($user, $notrigger = 0)
    {
        global $conf;

        $error = 0;

        // Clean parameters
        $this->fk_package = (int) $this->fk_package;
        $this->fk_commandedet = $this->fk_commandedet ? (int) $this->fk_commandedet : null;
        $this->quantity = max(1, (int) $this->quantity);
        $this->longueur = (int) $this->longueur;
        $this->largeur = (int) $this->largeur;
        $this->weight_unit = (float) $this->weight_unit;
        $this->surface_unit = (float) $this->surface_unit;

        $now = dol_now();

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_package,";
        $sql .= "fk_commandedet,";
        $sql .= "quantity,";
        $sql .= "detail_id,";
        $sql .= "custom_name,";
        $sql .= "custom_longueur,";
        $sql .= "custom_largeur,";
        $sql .= "custom_description,";
        $sql .= "custom_weight,";
        $sql .= "longueur,";
        $sql .= "largeur,";
        $sql .= "description,";
        $sql .= "weight_unit,";
        $sql .= "surface_unit,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= " ".((int) $this->fk_package).",";
        $sql .= " ".($this->fk_commandedet ? (int) $this->fk_commandedet : 'NULL').",";
        $sql .= " ".((int) $this->quantity).",";
        $sql .= " ".($this->detail_id ? "'".$this->db->escape($this->detail_id)."'" : 'NULL').",";
        $sql .= " ".($this->custom_name ? "'".$this->db->escape($this->custom_name)."'" : 'NULL').",";
        $sql .= " ".($this->custom_longueur ? (int) $this->custom_longueur : 'NULL').",";
        $sql .= " ".($this->custom_largeur ? (int) $this->custom_largeur : 'NULL').",";
        $sql .= " ".($this->custom_description ? "'".$this->db->escape($this->custom_description)."'" : 'NULL').",";
        $sql .= " ".($this->custom_weight ? (float) $this->custom_weight : 'NULL').",";
        $sql .= " ".((int) $this->longueur).",";
        $sql .= " ".((int) $this->largeur).",";
        $sql .= " '".$this->db->escape($this->description)."',";
        $sql .= " ".((float) $this->weight_unit).",";
        $sql .= " ".((float) $this->surface_unit).",";
        $sql .= " '".$this->db->idate($now)."',";
        $sql .= " ".((int) $user->id);
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->date_creation = $now;
            $this->fk_user_creat = $user->id;
        }

        if ($error) {
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return $this->id;
        }
    }

    /**
     * Load object in memory from the database
     *
     * @param int $id Id object
     * @return int <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT rowid, fk_package, fk_commandedet, quantity, detail_id,";
        $sql .= " custom_name, custom_longueur, custom_largeur, custom_description, custom_weight,";
        $sql .= " longueur, largeur, description, weight_unit, surface_unit,";
        $sql .= " date_creation, fk_user_creat";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".((int) $id);

        $resql = $this->db->query($sql);
        if ($resql) {
            $numrows = $this->db->num_rows($resql);
            if ($numrows) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_package = $obj->fk_package;
                $this->fk_commandedet = $obj->fk_commandedet;
                $this->quantity = $obj->quantity;
                $this->detail_id = $obj->detail_id;
                $this->custom_name = $obj->custom_name;
                $this->custom_longueur = $obj->custom_longueur;
                $this->custom_largeur = $obj->custom_largeur;
                $this->custom_description = $obj->custom_description;
                $this->custom_weight = $obj->custom_weight;
                $this->longueur = $obj->longueur;
                $this->largeur = $obj->largeur;
                $this->description = $obj->description;
                $this->weight_unit = $obj->weight_unit;
                $this->surface_unit = $obj->surface_unit;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->fk_user_creat = $obj->fk_user_creat;

                $this->db->free($resql);
                return 1;
            } else {
                $this->db->free($resql);
                return 0;
            }
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Delete item from database
     *
     * @param User $user User that deletes
     * @param int $notrigger 0=launch triggers after, 1=disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function delete($user, $notrigger = 0)
    {
        $error = 0;

        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".((int) $this->id);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if ($error) {
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * Prepare data from commande detail and detailjson
     *
     * @param object $commandedet Ligne de commande
     * @param array $detail Détail spécifique du detailjson
     * @return void
     */
    public function prepareFromCommandeDetail($commandedet, $detail)
    {
        $this->fk_commandedet = $commandedet->rowid;
        $this->detail_id = $detail['detail_id'] ?? '';
        $this->longueur = (int) $detail['longueur'];
        $this->largeur = (int) $detail['largeur'];
        $this->description = $detail['description'] ?? '';

        // CORRECTION FINALE : Calcul du poids selon la logique finale
        // Formule : quantity × surface_unit × poids_produit_kg
        // Exemple : 8 × 1.074 × 5.76 = 49.49 kg
        
        $product_weight = (float) $commandedet->weight;
        $product_weight_units = (int) $commandedet->weight_units;
        
        // Conversion vers kg avec la nouvelle fonction corrigée
        $product_weight_kg = $this->convertWeightToKg($product_weight, $product_weight_units);
        
        // CORRECTION TEMPORAIRE : Détecter si le poids semble déjà être en kg/m²
        if ($product_weight_units === 3 && $product_weight > 1) {
            error_log("COLISAGE: Détection PHP - Poids $product_weight avec unité 'grammes' mais semble déjà être en kg/m²");
            $product_weight_kg = $product_weight; // Pas de conversion
        }
        
        // Calculer la surface d'une unité (comme dans la BDD surface_unit)
        $surface_unit = ($detail['longueur'] * $detail['largeur']) / 1000000.0; // mm² vers m²
        
        // CALCUL : Poids d'une unité = surface_unit × poids_produit_kg
        $this->weight_unit = $product_weight_kg * $surface_unit;

        // Calculer la surface unitaire : L * l en m²
        $this->surface_unit = ($this->longueur * $this->largeur) / 1000000.0;
    }

    /**
     * Prepare data for free item
     *
     * @param string $name Nom de l'article
     * @param int $longueur Longueur en mm
     * @param int $largeur Largeur en mm
     * @param string $description Description
     * @param float $weight Poids unitaire
     * @return void
     */
    public function prepareAsFreeItem($name, $longueur, $largeur, $description = '', $weight = 0.0)
    {
        $this->fk_commandedet = null;
        $this->custom_name = $name;
        $this->custom_longueur = (int) $longueur;
        $this->custom_largeur = (int) $largeur;
        $this->custom_description = $description;
        $this->custom_weight = (float) $weight;

        $this->longueur = (int) $longueur;
        $this->largeur = (int) $largeur;
        $this->description = $description;
        $this->weight_unit = (float) $weight;
        $this->surface_unit = ($longueur * $largeur) / 1000000.0;
    }

    /**
     * CORRECTION : Récupérer le scale depuis le dictionnaire des unités Dolibarr
     * 
     * @param int $weightUnits Code de l'unité (rowid dans c_units)
     * @return int|null Scale de l'unité ou null si non trouvé
     */
    private function getUnitScale($weightUnits)
    {
        // Cache pour éviter les requêtes multiples
        if (isset(self::$scaleCache[$weightUnits])) {
            return self::$scaleCache[$weightUnits];
        }

        $sql = "SELECT scale FROM ".MAIN_DB_PREFIX."c_units";
        $sql .= " WHERE rowid = ".((int) $weightUnits);
        $sql .= " AND type_duration = 'weight'";
        $sql .= " AND active = 1";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            $scale = (int) $obj->scale;
            self::$scaleCache[$weightUnits] = $scale;
            $this->db->free($resql);
            return $scale;
        }

        // Pas trouvé, mettre null dans le cache
        self::$scaleCache[$weightUnits] = null;
        return null;
    }

    /**
     * MÉTHODE CORRIGÉE : Conversion robuste des poids vers kg utilisant le système scale de Dolibarr
     * 
     * @param float $weight Poids à convertir
     * @param int $weightUnits Code de l'unité (rowid dans c_units)
     * @return float Poids en kg
     */
    private function convertWeightToKg($weight, $weightUnits)
    {
        $numWeight = (float) $weight;
        
        // Si poids nul ou unité invalide
        if ($numWeight == 0 || empty($weightUnits)) {
            return 0.0;
        }

        // Récupérer le scale depuis le dictionnaire
        $scale = $this->getUnitScale($weightUnits);
        
        if ($scale !== null) {
            // Utiliser la formule Dolibarr : poids_en_kg = poids × 10^(scale-3)
            // Car scale-3 nous donne la puissance de 10 pour convertir vers kg
            // Exemples avec votre config:
            // - Tonne (scale=6): 6-3=3, donc 10^3=1000, 1 tonne = 1000 kg ✓
            // - Kg (scale=3): 3-3=0, donc 10^0=1, 1 kg = 1 kg ✓  
            // - Gramme (scale=0): 0-3=-3, donc 10^-3=0.001, 1 g = 0.001 kg ✓
            // - Milligramme (scale=-3): -3-3=-6, donc 10^-6=0.000001, 1 mg = 0.000001 kg ✓
            
            $conversion_factor = pow(10, $scale - 3);
            $result = $numWeight * $conversion_factor;
            
            // Log pour debug si nécessaire
            if (defined('DOL_VERSION') && getDolGlobalInt('COLISAGE_DEBUG_WEIGHT_CONVERSION')) {
                error_log("COLISAGE: Conversion poids - {$numWeight} (unité {$weightUnits}, scale {$scale}) = {$result} kg");
            }
            
            return $result;
        }

        // FALLBACK : Si le scale n'est pas trouvé, utiliser l'ancienne méthode avec valeurs hardcodées
        error_log("COLISAGE: Scale non trouvé pour unité {$weightUnits}, utilisation fallback");
        return $this->convertWeightToKgFallback($numWeight, $weightUnits);
    }

    /**
     * FALLBACK : Ancienne méthode de conversion avec valeurs hardcodées
     * 
     * @param float $weight Poids à convertir
     * @param int $weightUnits Unité de poids
     * @return float Poids en kg
     */
    private function convertWeightToKgFallback($weight, $weightUnits)
    {
        $numWeight = (float) $weight;
        $units = (int) $weightUnits;
        
        // Anciennes valeurs hardcodées (à adapter selon vos besoins)
        switch ($units) {
            case 1: // T (tonne) - selon votre config
                return $numWeight * 1000;
            case 2: // KG
                return $numWeight;
            case 3: // G (gramme)
                return $numWeight / 1000;
            case 4: // MG (milligramme)
                return $numWeight / 1000000;
            default:
                // Par défaut considérer comme kg
                error_log("COLISAGE: Unité de poids non reconnue en fallback: {$units} - Considéré comme kg");
                return $numWeight;
        }
    }
    
    /**
     * MÉTHODE AMÉLIORÉE : Obtenir le nom de l'unité de poids depuis le dictionnaire
     * 
     * @param int $weightUnits Code de l'unité
     * @return string Nom de l'unité
     */
    public function getWeightUnitName($weightUnits)
    {
        if (empty($weightUnits)) {
            return 'kg';
        }

        // Cache pour les noms d'unités
        static $nameCache = array();
        
        if (isset($nameCache[$weightUnits])) {
            return $nameCache[$weightUnits];
        }

        $sql = "SELECT code, label FROM ".MAIN_DB_PREFIX."c_units";
        $sql .= " WHERE rowid = ".((int) $weightUnits);
        $sql .= " AND type_duration = 'weight'";
        $sql .= " AND active = 1";

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $obj = $this->db->fetch_object($resql);
            $name = $obj->code ?: $obj->label; // Préférer le code court
            $nameCache[$weightUnits] = $name;
            $this->db->free($resql);
            return $name;
        }

        // Fallback
        $nameCache[$weightUnits] = 'kg (?)';
        return 'kg (?)';
    }

    /**
     * NOUVELLE MÉTHODE : Clear le cache des unités (utile pour les tests)
     * 
     * @return void
     */
    public static function clearUnitCache()
    {
        self::$scaleCache = array();
    }

    /**
     * Check if this is a free item
     *
     * @return bool
     */
    public function isFree()
    {
        return empty($this->fk_commandedet);
    }

    /**
     * Get display name for this item
     *
     * @return string
     */
    public function getDisplayName()
    {
        if ($this->isFree()) {
            return $this->custom_name ?: 'Article libre';
        } else {
            // Il faudrait récupérer le nom du produit depuis la commande
            return 'Produit ID:'.$this->fk_commandedet;
        }
    }
}
