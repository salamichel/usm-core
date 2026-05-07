-- Ajouter champs spécifiques aux catégories d'équipes
ALTER TABLE categories_equipes
  ADD COLUMN IF NOT EXISTS hauteur_filet VARCHAR(50) NULL COMMENT 'Ex: 2m43',
  ADD COLUMN IF NOT EXISTS type VARCHAR(100) NULL COMMENT 'Ex: Filles - 6*6',
  ADD COLUMN IF NOT EXISTS description_courte TEXT NULL COMMENT 'Description courte pour les cartes';
