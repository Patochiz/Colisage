-- Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

-- Script de création des tables pour le module Colisage

-- Table pour stocker les colis de colisage
CREATE TABLE IF NOT EXISTS llx_colisage_packages
(
    rowid           integer AUTO_INCREMENT PRIMARY KEY,
    fk_commande     integer NOT NULL,
    multiplier      integer DEFAULT 1,
    is_free         tinyint DEFAULT 0,
    total_weight    decimal(10,3) DEFAULT 0,
    total_surface   decimal(10,3) DEFAULT 0,
    date_creation   datetime,
    date_modification datetime,
    fk_user_creat   integer,
    fk_user_modif   integer
) ENGINE=innodb;

-- Table pour stocker les articles dans chaque colis
CREATE TABLE IF NOT EXISTS llx_colisage_items
(
    rowid           integer AUTO_INCREMENT PRIMARY KEY,
    fk_package      integer NOT NULL,
    fk_commandedet  integer NULL,           -- NULL pour les articles libres
    quantity        integer NOT NULL DEFAULT 1,
    detail_id       varchar(50) NULL,       -- ID du détail spécifique dans detailjson
    -- Pour les articles libres
    custom_name     varchar(255) NULL,
    custom_longueur integer NULL,
    custom_largeur  integer NULL,
    custom_description varchar(255) NULL,
    custom_weight   decimal(10,3) NULL,
    -- Données calculées/dupliquées pour performance
    longueur        integer NOT NULL,
    largeur         integer NOT NULL,
    description     varchar(255),
    weight_unit     decimal(10,3) DEFAULT 0,
    surface_unit    decimal(10,3) DEFAULT 0,
    date_creation   datetime,
    fk_user_creat   integer
) ENGINE=innodb;

-- Index pour optimiser les requêtes (compatible MySQL toutes versions)
-- Vérification et création des index seulement s'ils n'existent pas

-- Index pour llx_colisage_packages
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'llx_colisage_packages' 
     AND index_name = 'idx_colisage_packages_commande') > 0,
    'SELECT 1',
    'CREATE INDEX idx_colisage_packages_commande ON llx_colisage_packages (fk_commande)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index pour llx_colisage_items - fk_package
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'llx_colisage_items' 
     AND index_name = 'idx_colisage_items_package') > 0,
    'SELECT 1',
    'CREATE INDEX idx_colisage_items_package ON llx_colisage_items (fk_package)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index pour llx_colisage_items - fk_commandedet
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'llx_colisage_items' 
     AND index_name = 'idx_colisage_items_commandedet') > 0,
    'SELECT 1',
    'CREATE INDEX idx_colisage_items_commandedet ON llx_colisage_items (fk_commandedet)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
