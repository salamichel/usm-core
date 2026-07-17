-- Description courte pour les équipes (affichée sur les cartes)
ALTER TABLE equipes_config
  ADD COLUMN IF NOT EXISTS description_courte TEXT NULL COMMENT 'Description courte pour les cartes';

