SET NAMES utf8mb4;

-- Extend ENUM to support equipe_saison photos
ALTER TABLE photos
  MODIFY COLUMN entity_type ENUM('post','page','equipe_saison') NOT NULL;

-- Équipes (config permanente, indépendante de la saison)
CREATE TABLE IF NOT EXISTS equipes_config (
  `id` int(11) NOT NULL,
  `slug_colonne` varchar(50) NOT NULL COMMENT 'Colonne dans Joueurs (ex: Eq_L1)',
  `libelle` varchar(100) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(100) NOT NULL,
  `team_filter` varchar(50) DEFAULT NULL COMMENT 'Team filter code for agenda (ex: Eq_L2)',
  `manifestation_filter` varchar(100) DEFAULT NULL COMMENT 'Manifestation filter for agenda (ex: Match L2)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `equipes_config`
  ADD PRIMARY KEY IF NOT EXISTS (`id`),
  ADD UNIQUE INDEX IF NOT EXISTS `uq_equipes_colonne` (`slug_colonne`),
  ADD UNIQUE INDEX IF NOT EXISTS `slug` (`slug`);


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

-- Seed équipes_config
INSERT IGNORE INTO `equipes_config` (`id`, `slug_colonne`, `libelle`, `categorie`, `ordre`, `is_active`, `slug`, `team_filter`, `manifestation_filter`) VALUES
(1, 'Eq_L1', 'Loisir 1', 'Compétition', 1, 1, 'loisir-1', 'Eq_L1', 'Match L1'),
(2, 'Eq_L2', 'Loisir 2', 'Compétition', 2, 1, 'loisir-2', 'Eq_L2', 'Match L2'),
(3, 'Eq_L3', 'Loisir 3', 'Compétition', 3, 1, 'loisir-3', 'Eq_L3', 'Match L3'),
(4, 'Eq_L4', 'Loisir 4', 'Compétition', 4, 1, 'loisir-4', 'Eq_L4', 'Match L4'),
(5, 'Eq_Open', 'Open', 'Compétition', 5, 1, 'open', 'Eq_Open', 'Match Open'),
(6, 'DEP', 'Département', 'Compétition', 6, 1, 'd-partement', 'DEP', 'Match DEP'),
(7, 'Eq_Heitz', 'Coupe Heitz', 'Coupes', 10, 1, 'coupe-heitz', 'Eq_Heitz', 'Match Heitz'),
(8, 'Eq_Aico', 'Coupe Aïco', 'Coupes', 11, 1, 'coupe-a-co', 'Eq_Aico', 'Match Aico'),
(9, 'CoupeLoisir', 'Coupe Loisir', 'Coupes', 12, 1, 'coupe-loisir', 'CoupeLoisir', 'Match CoupeLoisir'),
(10, 'UFOLEP_1', 'UFOLEP 1', 'UFOLEP', 20, 1, 'ufolep-1', 'UFOLEP_1', 'Plateau UFOLEP 1'),
(11, 'UFOLEP_2', 'UFOLEP 2', 'UFOLEP', 21, 1, 'ufolep-2', 'UFOLEP_2', 'Plateau UFOLEP 2'),
(12, 'UFOLEP_3', 'UFOLEP 3', 'UFOLEP', 22, 1, 'ufolep-3', 'UFOLEP_3', 'Plateau UFOLEP 3'),
(13, 'M18F', 'M18 Féminin', 'Jeunes', 30, 1, 'm18-f-minin', 'M18F', 'Match M18F'),
(14, 'M15F', 'M15 Féminin', 'Jeunes', 31, 1, 'm15-f-minin', 'M15F', 'Match M15F'),
(15, 'R2F', 'R2 Féminin', 'Jeunes', 32, 1, 'r2-f-minin', 'R2F', 'Match R2F'),
(16, 'Loisir', 'Loisir', 'Loisir', 40, 1, 'loisir', 'Loisir', 'Match Loisir'),
(17, 'Débutant', 'Débutant', 'Loisir', 41, 1, 'd-butant', 'Débutant', 'Match Debutant');
