-- Migration: Equipe Config Training Filter
-- Description: Ajoute une colonne pour associer les types d'entraînements dans la configuration des équipes.

ALTER TABLE equipes_config
ADD COLUMN IF NOT EXISTS training_filter VARCHAR(255) NULL AFTER manifestation_filter;
