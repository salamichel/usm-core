-- USM Volley — Schéma MySQL
-- Compatible InfinityFree / MySQL 5.7+

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ─────────────────────────────────────────────────────────────────────────────
-- Menu items (2 niveaux max)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS menu_items (
  id         INT          NOT NULL AUTO_INCREMENT,
  label      VARCHAR(100) NOT NULL,
  link_type  ENUM('page','url','none') NOT NULL DEFAULT 'none',
  target     VARCHAR(255)             DEFAULT NULL,
  parent_id  INT                      DEFAULT NULL,
  position   INT          NOT NULL    DEFAULT 0,
  created_at TIMESTAMP    NOT NULL    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_parent (parent_id),
  CONSTRAINT fk_menu_parent FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Pages statiques
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pages (
  id           INT          NOT NULL AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(255) NOT NULL,
  content      LONGTEXT,
  is_published TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Articles blog
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
  id           INT          NOT NULL AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(255) NOT NULL,
  excerpt      TEXT                  DEFAULT NULL,
  content      LONGTEXT,
  is_published TINYINT(1)   NOT NULL DEFAULT 0,
  published_at DATETIME              DEFAULT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_published (is_published, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Photos de galerie (liées à un post ou une page)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS photos (
  id          INT          NOT NULL AUTO_INCREMENT,
  entity_type ENUM('post','page') NOT NULL,
  entity_id   INT          NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  caption     VARCHAR(255)          DEFAULT NULL,
  position    INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_photos_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
