-- Ajout du lien FFVB pour les résultats de la saison sur les équipes
ALTER TABLE equipes_config ADD COLUMN IF NOT EXISTS ffvb_link TEXT DEFAULT NULL;

-- Initialisation du lien
UPDATE equipes_config
SET ffvb_link = 'Phase 1 | https://www.ffvbbeach.org/ffvbapp/resu/vbspo_home.php?saison=2025%2F2026&codent=PTAQ33&poule=L2A&division=&tour=&x=8&y=9
Phase 2 | https://www.ffvbbeach.org/ffvbapp/resu/vbspo_home.php?saison=2025%2F2026&codent=PTAQ33&poule=L2B&division=&tour=&x=8&y=9'
;
