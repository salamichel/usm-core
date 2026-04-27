-- Ajout de la colonne canalblog_id pour tracker les articles importés via API
ALTER TABLE posts
ADD COLUMN canalblog_id VARCHAR(255) DEFAULT NULL UNIQUE,
ADD KEY idx_posts_canalblog_id (canalblog_id);
