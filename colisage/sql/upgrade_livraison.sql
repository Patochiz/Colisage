-- Migration : Ajout du champ livraison_num pour la gestion des livraisons partielles
-- Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
--
-- A exécuter sur les installations existantes du module Colisage
-- Les colis existants auront livraison_num = 1 (valeur par défaut)

ALTER TABLE llx_colisage_packages ADD COLUMN livraison_num integer DEFAULT 1;
