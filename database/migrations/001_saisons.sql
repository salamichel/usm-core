CREATE TABLE IF NOT EXISTS saisons (
  id         INT NOT NULL AUTO_INCREMENT,
  libelle    VARCHAR(100) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_saisons_libelle (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS joueur_snapshots (
  id         INT NOT NULL AUTO_INCREMENT,
  saison_id  INT NOT NULL,
  id_joueur  INT NOT NULL,
  nom        VARCHAR(100) NOT NULL,
  prenom     VARCHAR(100) NOT NULL,
  data       JSON NOT NULL,
  snapped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_snapshot_saison_joueur (saison_id, id_joueur),
  CONSTRAINT fk_snapshot_saison FOREIGN KEY (saison_id)
    REFERENCES saisons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
