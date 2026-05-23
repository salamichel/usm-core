SET NAMES utf8mb4;

-- Base externe simulée (dev) — tables Joueurs et Manifestation
-- En production : base InfinityFree IF0 du club (MyISAM, utf8mb3).
-- Simulation en dev avec InnoDB + utf8mb4 (structure de colonnes identique).

CREATE TABLE IF NOT EXISTS Joueurs (
  id_joueur       INT(4)        NOT NULL,
  Nom             VARCHAR(50)   NOT NULL,
  `Prénom`        VARCHAR(50)   NOT NULL,
  Sexe            CHAR(1)       NOT NULL DEFAULT 'F',
  Adresse         CHAR(200)     DEFAULT NULL,
  CodePostal      INT(5)        DEFAULT 33380,
  Commune         CHAR(15)      DEFAULT NULL,
  Caracteristique CHAR(15)      DEFAULT NULL,
  NLicence        MEDIUMINT(9)  DEFAULT NULL,
  Mel             CHAR(50)      DEFAULT NULL,
  `Téléphone`     CHAR(50)      NOT NULL DEFAULT '',
  DateNaissance   DATE          DEFAULT NULL,
  Equipe          CHAR(20)      DEFAULT NULL,
  Equipes         VARCHAR(100)  NOT NULL DEFAULT '',
  L1              TINYINT(1)    NOT NULL DEFAULT 0,
  L2              TINYINT(1)    NOT NULL DEFAULT 0,
  L3              TINYINT(1)    NOT NULL DEFAULT 0,
  L4              TINYINT(1)    NOT NULL DEFAULT 0,
  Open            TINYINT(1)    NOT NULL DEFAULT 0,
  CoupeLoisir     TINYINT(4)    NOT NULL DEFAULT 0,
  Heitz           TINYINT(4)    NOT NULL DEFAULT 0,
  Aico            TINYINT(4)    NOT NULL DEFAULT 0,
  UFOLEP_1        TINYINT(4)    NOT NULL DEFAULT 0,
  UFOLEP_2        TINYINT(4)    NOT NULL DEFAULT 0,
  UFOLEP_3        TINYINT(4)    NOT NULL DEFAULT 0,
  DEP             TINYINT(4)    NOT NULL DEFAULT 0,
  Adulte          TINYINT(1)    NOT NULL DEFAULT 0,
  Jeune           TINYINT(1)    NOT NULL DEFAULT 0,
  M18F            TINYINT(4)    NOT NULL DEFAULT 0,
  M13F            TINYINT(4)    NOT NULL DEFAULT 0,
  M15F6           TINYINT(4)    NOT NULL DEFAULT 0,
  M15F            TINYINT(4)    NOT NULL DEFAULT 0,
  R2F             TINYINT(4)    NOT NULL DEFAULT 0,
  `Compétition`   TINYINT(1)    NOT NULL DEFAULT 0,
  Loisir          TINYINT(1)    NOT NULL DEFAULT 0,
  `Débutant`      TINYINT(1)    NOT NULL DEFAULT 0,
  mdp             TINYTEXT      NOT NULL,
  PRIMARY KEY (id_joueur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Manifestation (
  id_manifestation     INT(11)      NOT NULL,
  `ManifestationTypée` VARCHAR(80)  NOT NULL DEFAULT '',
  Manifestation        VARCHAR(20)  NOT NULL DEFAULT '',
  `Date`               DATETIME     DEFAULT NULL,
  `Durée_créneau`      TINYTEXT     DEFAULT NULL,
  Lieu                 VARCHAR(80)  NOT NULL DEFAULT '',
  Nombre_terrain       TINYINT(4)   NOT NULL DEFAULT 1,
  Creneau              VARCHAR(20)  NOT NULL DEFAULT '',
  Commentaire          LONGTEXT     DEFAULT NULL,
  Statut               VARCHAR(20)  DEFAULT NULL,
  PRIMARY KEY (id_manifestation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
