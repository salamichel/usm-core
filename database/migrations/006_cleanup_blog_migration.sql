-- Cleanup: remove tables and columns created by incomplete migration 005
DROP TABLE IF EXISTS url_redirects;
ALTER TABLE posts DROP COLUMN IF EXISTS old_slug;
