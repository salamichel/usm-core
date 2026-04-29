-- Add slug column to equipes_config for SEO-friendly URLs
ALTER TABLE equipes_config
ADD COLUMN slug VARCHAR(100) DEFAULT '';

-- Populate existing slugs from libelle (auto-generate from team names)
UPDATE equipes_config
SET slug = LOWER(
  REGEXP_REPLACE(
    REGEXP_REPLACE(libelle, '[àâä]', 'a'),
    '[^a-z0-9]+',
    '-'
  )
);

-- Make slug NOT NULL and add UNIQUE constraint
ALTER TABLE equipes_config
MODIFY COLUMN slug VARCHAR(100) NOT NULL UNIQUE;

-- Add index for faster lookups
CREATE INDEX idx_equipes_slug ON equipes_config(slug);

