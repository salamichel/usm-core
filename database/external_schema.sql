SET NAMES utf8mb4;

-- Base externe simulée (dev) — tables Joueurs et Manifestation
-- En production, ces tables existent dans la base IF0 du club.

CREATE TABLE IF NOT EXISTS Joueurs (
  id          INT NOT NULL AUTO_INCREMENT,
  Nom         VARCHAR(100) NOT NULL,
  Prenom      VARCHAR(100) NOT NULL,
  Email       VARCHAR(255) DEFAULT NULL,
  Telephone   VARCHAR(20)  DEFAULT NULL,
  -- Équipes compétition
  Eq_L1       TINYINT(1) NOT NULL DEFAULT 0,
  Eq_L2       TINYINT(1) NOT NULL DEFAULT 0,
  Eq_L3       TINYINT(1) NOT NULL DEFAULT 0,
  Eq_L4       TINYINT(1) NOT NULL DEFAULT 0,
  Eq_Open     TINYINT(1) NOT NULL DEFAULT 0,
  DEP         TINYINT(1) NOT NULL DEFAULT 0,
  -- Coupes
  Eq_Heitz    TINYINT(1) NOT NULL DEFAULT 0,
  Eq_Aico     TINYINT(1) NOT NULL DEFAULT 0,
  CoupeLoisir TINYINT(1) NOT NULL DEFAULT 0,
  -- UFOLEP
  UFOLEP_1    TINYINT(1) NOT NULL DEFAULT 0,
  UFOLEP_2    TINYINT(1) NOT NULL DEFAULT 0,
  UFOLEP_3    TINYINT(1) NOT NULL DEFAULT 0,
  -- Jeunes filles
  M18F        TINYINT(1) NOT NULL DEFAULT 0,
  M15F        TINYINT(1) NOT NULL DEFAULT 0,
  R2F         TINYINT(1) NOT NULL DEFAULT 0,
  -- Loisir
  Loisir      TINYINT(1) NOT NULL DEFAULT 0,
  Debutant    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Manifestation (
  id                 INT NOT NULL AUTO_INCREMENT,
  LibelleManif       VARCHAR(255) NOT NULL,
  DateManif          DATE NOT NULL,
  HeureManif         TIME DEFAULT NULL,
  Lieu               VARCHAR(255) DEFAULT NULL,
  commentaire        TEXT DEFAULT NULL,
  type_manifestation ENUM('Match','Entraînement','Tournoi','Stage') NOT NULL DEFAULT 'Match',
  statut             VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
