-- Déplacer type et hauteur_filet des catégories vers les équipes
ALTER TABLE equipes_config
  ADD COLUMN IF NOT EXISTS type VARCHAR(100) NULL COMMENT 'Ex: Filles - 6*6',
  ADD COLUMN IF NOT EXISTS hauteur_filet VARCHAR(50) NULL COMMENT 'Ex: 2m43';

-- Retirer des catégories
ALTER TABLE categories_equipes
  DROP COLUMN IF EXISTS type,
  DROP COLUMN IF EXISTS hauteur_filet;
