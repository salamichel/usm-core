-- Migration : ajout de la table photos
-- À exécuter via phpMyAdmin sur les installations existantes

SET NAMES utf8mb4;

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
