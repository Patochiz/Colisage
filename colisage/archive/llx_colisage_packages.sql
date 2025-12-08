-- Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
--
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

-- Index pour optimiser les requêtes
ALTER TABLE llx_colisage_packages ADD INDEX idx_colisage_packages_commande (fk_commande);
