-- 016_site_config_theme.sql
-- Ajoute la clé `theme` à site_config pour permettre la sélection du thème
-- depuis le back-office plutôt que via la variable d'environnement THEME.
-- INSERT IGNORE : valeur env actuelle ou 'front001' à l'install initiale.

INSERT IGNORE INTO site_config (cle, valeur) VALUES
  ('theme', 'front003');
