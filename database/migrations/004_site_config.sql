SET NAMES utf8mb4;

-- Configuration globale du site (clés/valeurs).
-- Édité depuis /admin/site-config et exposé en globale Twig (`site_config`).
CREATE TABLE IF NOT EXISTS site_config (
  cle        VARCHAR(100) NOT NULL,
  valeur     TEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed des clés par défaut (idempotent : INSERT IGNORE sur la PK).
INSERT IGNORE INTO site_config (cle, valeur) VALUES
('club_name',              'USM Volley-Ball'),
('club_tagline',           'Club de volley-ball de Mios — convivialité, formation et compétition.'),
('address',                "Gymnase municipal\nAvenue de Bordeaux\n33380 Mios"),
('email',                  'contact@usm-volley.fr'),
('phone',                  ''),
('facebook_url',           ''),
('instagram_url',          ''),
('legal_text',             'Site géré par l''USM Volley-Ball. Photographies © leurs auteurs.'),
('home_slider_posts_count', '3'),
('home_latest_posts_count', '3');
