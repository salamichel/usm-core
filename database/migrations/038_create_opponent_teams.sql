-- Migration: Create Opponent Teams
-- Description: Référentiel des équipes adverses administrable avec validation par admin

CREATE TABLE IF NOT EXISTS opponent_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    club VARCHAR(150) NULL,
    city VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    needs_review TINYINT(1) NOT NULL DEFAULT 0,
    created_by_joueur_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_opponent_name (name),
    INDEX idx_needs_review (needs_review),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
