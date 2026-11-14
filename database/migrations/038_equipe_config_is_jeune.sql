-- Ajout de l'indicateur is_jeune sur les équipes pour distinguer Match Jeune / Match Adulte
ALTER TABLE equipes_config
  ADD COLUMN IF NOT EXISTS is_jeune TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

-- Initialisation automatique pour les équipes jeunes existantes
UPDATE equipes_config
SET is_jeune = 1
WHERE categorie LIKE '%Jeune%'
   OR slug_colonne IN ('M13F', 'M15F', 'M15F6', 'M18F', 'Jeune');
