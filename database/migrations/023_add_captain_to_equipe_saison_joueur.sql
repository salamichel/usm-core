SET NAMES utf8mb4;

-- Ajout de la notion de capitaine pour un joueur dans une équipe-saison
ALTER TABLE equipe_saison_joueur ADD COLUMN is_captain TINYINT(1) NOT NULL DEFAULT 0;
