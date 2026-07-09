SET NAMES utf8mb4;

-- Seed de la configuration de confidentialité de l'agenda
INSERT IGNORE INTO site_config (cle, valeur) VALUES
('agenda_privacy', 'hybrid');
