-- Migration : equipe_config_min_players
-- Généré le : 2026-07-03 10:16:45

-- Mettez vos requêtes SQL idempotentes ci-dessous (ex. CREATE TABLE IF NOT EXISTS, INSERT IGNORE)
ALTER TABLE `equipes_config` ADD COLUMN IF NOT EXISTS `min_players` INT NOT NULL DEFAULT 6;
