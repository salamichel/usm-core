-- Remove "archives/" prefix from blog slugs
UPDATE posts SET slug = REPLACE(slug, 'archives/', '') WHERE slug LIKE 'archives/%';
