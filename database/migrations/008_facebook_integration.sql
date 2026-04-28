-- Intégration Facebook Graph API
--
-- 1) facebook_post_id sur posts : tracking des articles partagés sur Facebook
--    (permet de ne pas re-partager 2 fois le même article)
ALTER TABLE posts
ADD COLUMN IF NOT EXISTS facebook_post_id VARCHAR(100) DEFAULT NULL,
ADD KEY IF NOT EXISTS idx_posts_facebook_post_id (facebook_post_id);

-- 2) Cache des posts récupérés depuis la page Facebook (TTL géré côté PHP)
--    Une seule ligne par cache_key (ex: 'page_feed:10') avec ON DUPLICATE KEY UPDATE.
CREATE TABLE IF NOT EXISTS facebook_cache (
  cache_key   VARCHAR(100) NOT NULL PRIMARY KEY,
  payload     LONGTEXT NOT NULL,
  fetched_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at  TIMESTAMP NOT NULL,
  KEY idx_fb_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
