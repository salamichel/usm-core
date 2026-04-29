-- Add column to store old slugs for redirect mapping
ALTER TABLE posts
ADD COLUMN old_slug VARCHAR(255) DEFAULT NULL;

-- Save current slugs as old_slugs (for creating redirects later)
UPDATE posts
SET old_slug = slug
WHERE slug LIKE 'archives/%' OR slug REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}/' OR slug LIKE '%.html';

-- Remove "archives/" prefix from blog post slugs
-- Convert from: archives/2019/10/13/37708524.html → 37708524
UPDATE posts
SET slug = TRIM(
  BOTH '/' FROM LOWER(
    REGEXP_REPLACE(slug, '^archives/[0-9]{4}/[0-9]{2}/[0-9]{2}/', '')
  )
)
WHERE slug LIKE 'archives/%';

-- For posts with date format but no 'archives' prefix, also clean up
UPDATE posts
SET slug = TRIM(
  BOTH '/' FROM LOWER(
    REGEXP_REPLACE(slug, '^[0-9]{4}/[0-9]{2}/[0-9]{2}/', '')
  )
)
WHERE slug REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}/' AND slug NOT LIKE 'archives/%';

-- Remove .html extension if present
UPDATE posts
SET slug = REGEXP_REPLACE(slug, '\.html$', '')
WHERE slug LIKE '%.html';

-- Create redirect mapping table for old URLs → new slugs
CREATE TABLE IF NOT EXISTS url_redirects (
  id INT NOT NULL AUTO_INCREMENT,
  old_path VARCHAR(500) NOT NULL,
  new_slug VARCHAR(255) NOT NULL,
  post_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirects_old_path (old_path),
  KEY fk_redirects_post (post_id),
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Populate redirect table from old_slug data
INSERT INTO url_redirects (old_path, new_slug, post_id)
SELECT CONCAT('/blog/', old_slug), slug, id
FROM posts
WHERE old_slug IS NOT NULL;
