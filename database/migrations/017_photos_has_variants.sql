-- 017_photos_has_variants.sql
-- Track whether resize variants (thumb/medium/large WebP) have been generated for a photo.
-- Allows image_variant() Twig function to fall back to original for legacy photos.
ALTER TABLE photos
  ADD COLUMN IF NOT EXISTS has_variants TINYINT(1) NOT NULL DEFAULT 0;
