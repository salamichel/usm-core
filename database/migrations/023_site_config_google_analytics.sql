SET NAMES utf8mb4;

-- Seed de la clé google_analytics_id (vide par défaut)
INSERT IGNORE INTO site_config (cle, valeur) VALUES
('google_analytics_id', '');
