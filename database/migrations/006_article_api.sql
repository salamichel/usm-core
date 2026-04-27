-- Ajout de la colonne canalblog_id pour tracker les articles importés via API
ALTER TABLE posts
ADD COLUMN IF NOT EXISTS canalblog_id VARCHAR(255) DEFAULT NULL UNIQUE,
ADD KEY IF NOT EXISTS idx_posts_canalblog_id (canalblog_id);
