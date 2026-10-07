-- Migration: Support Multiple Match Results per Manifestation (Plateaux)
-- Description: Permet d'enregistrer plusieurs équipes adverses et résultats pour un même plateau

SET @exist := (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'match_results' 
      AND INDEX_NAME = 'uniq_manifestation_id'
);

SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE match_results DROP INDEX uniq_manifestation_id', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_idx := (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'match_results' 
      AND INDEX_NAME = 'idx_manifestation_id'
);

SET @sqlstmt_idx := IF(@exist_idx = 0, 'ALTER TABLE match_results ADD INDEX idx_manifestation_id (manifestation_id)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist_uniq := (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'match_results' 
      AND INDEX_NAME = 'uniq_manif_opponent'
);

SET @sqlstmt_uniq := IF(@exist_uniq = 0, 'ALTER TABLE match_results ADD UNIQUE KEY uniq_manif_opponent (manifestation_id, opponent_team_id)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt_uniq;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
