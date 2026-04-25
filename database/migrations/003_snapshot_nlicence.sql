SET NAMES utf8mb4;

-- Ajout de la colonne nlicence dans joueur_snapshots (idempotent)
ALTER TABLE joueur_snapshots
  ADD COLUMN IF NOT EXISTS nlicence VARCHAR(50) DEFAULT NULL AFTER prenom;
