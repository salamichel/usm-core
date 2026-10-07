-- Migration: Create Match Results
-- Description: Stockage local des résultats et scores des matchs avec équipe adverse

CREATE TABLE IF NOT EXISTS match_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manifestation_id INT NOT NULL,
    opponent_team_id INT NOT NULL,
    sets_for TINYINT UNSIGNED NULL,
    sets_against TINYINT UNSIGNED NULL,
    set_details JSON NULL,
    entered_by_joueur_id INT NULL,
    entered_by_admin TINYINT(1) NOT NULL DEFAULT 0,
    entered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_manifestation_id (manifestation_id),
    INDEX idx_opponent_team_id (opponent_team_id),
    INDEX idx_entered_at (entered_at),
    CONSTRAINT fk_match_results_opponent FOREIGN KEY (opponent_team_id) REFERENCES opponent_teams (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
