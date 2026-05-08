-- Description courte pour les équipes (affichée sur les cartes)
ALTER TABLE equipes_config
  ADD COLUMN IF NOT EXISTS description_courte TEXT NULL COMMENT 'Description courte pour les cartes';

-- Retirer description_courte de categories_equipes (elle n\'y était que temporairement)
ALTER TABLE categories_equipes
  DROP COLUMN IF EXISTS description_courte;
