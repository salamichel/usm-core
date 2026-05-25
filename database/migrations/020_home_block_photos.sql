SET NAMES utf8mb4;

-- Extend ENUM to support home_block photos
ALTER TABLE photos
  MODIFY COLUMN entity_type ENUM('post','page','equipe_saison','home_block') NOT NULL;