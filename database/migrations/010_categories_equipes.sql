-- Descriptions éditables pour les catégories d'équipes
CREATE TABLE IF NOT EXISTS categories_equipes (
  id          INT           NOT NULL AUTO_INCREMENT,
  nom         VARCHAR(100)  NOT NULL COMMENT 'Doit correspondre à equipes_config.categorie',
  description LONGTEXT      NULL,
  ordre       INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_equipes_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed des catégories existantes (sans description, sans écrasement)
INSERT IGNORE INTO categories_equipes (nom, ordre) VALUES
  ('Compétition', 1),
  ('Coupes',      2),
  ('UFOLEP',      3),
  ('Jeunes',      4),
  ('Loisir',      5);
