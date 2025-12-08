-- Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

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

-- Index pour optimiser les requêtes (compatible MySQL)
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
