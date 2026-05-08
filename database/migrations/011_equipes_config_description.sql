-- Description éditable pour chaque équipe
ALTER TABLE equipes_config
  ADD COLUMN IF NOT EXISTS description LONGTEXT NULL AFTER manifestation_filter;
