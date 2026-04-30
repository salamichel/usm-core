-- Add slug and agenda filter mapping columns to equipes_config for SEO-friendly URLs
-- and dynamic agenda filtering

ALTER TABLE equipes_config
ADD COLUMN IF NOT EXISTS slug VARCHAR(100) DEFAULT '' COMMENT 'SEO-friendly URL slug',
ADD COLUMN IF NOT EXISTS team_filter VARCHAR(50) DEFAULT NULL COMMENT 'Team filter code for agenda (ex: Eq_L2)',
ADD COLUMN IF NOT EXISTS manifestation_filter VARCHAR(100) DEFAULT NULL COMMENT 'Manifestation filter for agenda (ex: Match L2)';

-- Populate existing slugs from libelle (auto-generate from team names)
UPDATE equipes_config
SET slug = LOWER(
  REGEXP_REPLACE(
    REGEXP_REPLACE(libelle, '[àâä]', 'a'),
    '[^a-z0-9]+',
    '-'
  )
)
WHERE slug = '' OR slug IS NULL;

-- Populate team_filter from slug_colonne (already set)
UPDATE equipes_config
SET team_filter = slug_colonne
WHERE team_filter IS NULL OR team_filter = '';

-- Make slug NOT NULL and add UNIQUE constraint
ALTER TABLE equipes_config
MODIFY COLUMN slug VARCHAR(100) NOT NULL UNIQUE;

-- No separate index needed; UNIQUE constraint already creates one
