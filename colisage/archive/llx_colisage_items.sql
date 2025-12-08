-- Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
--
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
ALTER TABLE llx_colisage_items ADD INDEX idx_colisage_items_package (fk_package);
ALTER TABLE llx_colisage_items ADD INDEX idx_colisage_items_commandedet (fk_commandedet);
