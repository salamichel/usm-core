SET NAMES utf8mb4;

-- Extend ENUM to support equipe_saison photos
ALTER TABLE photos
  MODIFY COLUMN entity_type ENUM('post','page','equipe_saison') NOT NULL;

-- Équipes (config permanente, indépendante de la saison)
CREATE TABLE IF NOT EXISTS equipes_config (
  id           INT NOT NULL AUTO_INCREMENT,
  slug_colonne VARCHAR(50)  NOT NULL COMMENT 'Colonne dans Joueurs (ex: Eq_L1)',
  libelle      VARCHAR(100) NOT NULL,
  categorie    VARCHAR(100) NOT NULL,
  ordre        INT NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipes_colonne (slug_colonne)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liaison Équipe × Saison (photos + membres)
CREATE TABLE IF NOT EXISTS equipe_saison (
  id        INT NOT NULL AUTO_INCREMENT,
  equipe_id INT NOT NULL,
  saison_id INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipe_saison (equipe_id, saison_id),
  KEY fk_es_equipe (equipe_id),
  KEY fk_es_saison (saison_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membres d'une équipe pour une saison (ajustable manuellement post-flash)
CREATE TABLE IF NOT EXISTS equipe_saison_joueur (
  id               INT NOT NULL AUTO_INCREMENT,
  equipe_saison_id INT NOT NULL,
  snapshot_id      INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_esj (equipe_saison_id, snapshot_id),
  KEY fk_esj_es (equipe_saison_id),
  KEY fk_esj_snap (snapshot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrer slug_colonne 'Debutant' → 'Débutant' si déjà seedé sans accent
UPDATE equipes_config SET slug_colonne = 'Débutant' WHERE slug_colonne = 'Debutant';

-- Seed équipes_config
INSERT IGNORE INTO equipes_config (slug_colonne, libelle, categorie, ordre) VALUES
('Eq_L1',       'Loisir 1',      'Compétition', 1),
('Eq_L2',       'Loisir 2',      'Compétition', 2),
('Eq_L3',       'Loisir 3',      'Compétition', 3),
('Eq_L4',       'Loisir 4',      'Compétition', 4),
('Eq_Open',     'Open',          'Compétition', 5),
('DEP',         'Département',   'Compétition', 6),
('Eq_Heitz',    'Coupe Heitz',   'Coupes',      10),
('Eq_Aico',     'Coupe Aïco',    'Coupes',      11),
('CoupeLoisir', 'Coupe Loisir',  'Coupes',      12),
('UFOLEP_1',    'UFOLEP 1',      'UFOLEP',      20),
('UFOLEP_2',    'UFOLEP 2',      'UFOLEP',      21),
('UFOLEP_3',    'UFOLEP 3',      'UFOLEP',      22),
('M18F',        'M18 Féminin',   'Jeunes',      30),
('M15F',        'M15 Féminin',   'Jeunes',      31),
('R2F',         'R2 Féminin',    'Jeunes',      32),
('Loisir',      'Loisir',        'Loisir',      40),
('Débutant',    'Débutant',      'Loisir',      41);
