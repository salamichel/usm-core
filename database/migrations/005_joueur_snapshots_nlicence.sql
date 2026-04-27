ALTER TABLE joueur_snapshots
  ADD COLUMN IF NOT EXISTS nlicence VARCHAR(50) NULL
    AFTER prenom;
