-- Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
--
-- Script SQL simple et compatible pour le module Colisage
-- Compatible avec toutes les versions de MySQL

-- Table pour stocker les colis de colisage
CREATE TABLE IF NOT EXISTS llx_colisage_packages
(
    rowid           integer AUTO_INCREMENT PRIMARY KEY,
    fk_commande     integer NOT NULL,
    multiplier      integer DEFAULT 1,
    is_free         tinyint DEFAULT 0,
    livraison_num   integer DEFAULT 1,
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
    fk_commandedet  integer NULL,
    quantity        integer NOT NULL DEFAULT 1,
    detail_id       varchar(50) NULL,
    custom_name     varchar(255) NULL,
    custom_longueur integer NULL,
    custom_largeur  integer NULL,
    custom_description varchar(255) NULL,
    custom_weight   decimal(10,3) NULL,
    longueur        integer NOT NULL,
    largeur         integer NOT NULL,
    description     varchar(255),
    weight_unit     decimal(10,3) DEFAULT 0,
    surface_unit    decimal(10,3) DEFAULT 0,
    date_creation   datetime,
    fk_user_creat   integer
) ENGINE=innodb;

-- Index pour optimiser les requêtes
-- Note: Les index seront créés même s'ils existent, mais MySQL ignorera l'erreur
-- lors de l'activation du module grâce à la gestion d'erreur dans le code

-- Index pour llx_colisage_packages
ALTER TABLE llx_colisage_packages ADD INDEX idx_colisage_packages_commande (fk_commande);

-- Index pour llx_colisage_items
ALTER TABLE llx_colisage_items ADD INDEX idx_colisage_items_package (fk_package);
ALTER TABLE llx_colisage_items ADD INDEX idx_colisage_items_commandedet (fk_commandedet);
