SET NAMES utf8mb4;

-- Blocs éditoriaux affichés sur la page d'accueil (gérés depuis l'admin).
CREATE TABLE IF NOT EXISTS home_blocks (
  id         INT NOT NULL AUTO_INCREMENT,
  titre      VARCHAR(200) NOT NULL,
  contenu    TEXT NOT NULL,
  image      VARCHAR(255) DEFAULT NULL,
  cta_label  VARCHAR(100) DEFAULT NULL,
  cta_url    VARCHAR(500) DEFAULT NULL,
  position   INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_home_blocks_titre (titre),
  KEY idx_home_blocks_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed deux blocs d'exemple (idempotent : INSERT IGNORE sur le titre)
INSERT IGNORE INTO home_blocks (titre, contenu, cta_label, cta_url, position, is_active) VALUES
('Le club',
 '<p>Fondée à Mios, l''<strong>USM Volley-Ball</strong> rassemble plus de cent passionné·e·s de tous niveaux, du débutant au compétiteur. Notre club met l''accent sur la convivialité, la formation et le plaisir du jeu.</p><p>Que vous cherchiez à découvrir le volley, à progresser ou à jouer en compétition, vous trouverez chez nous une équipe à votre image.</p>',
 'Découvrir nos équipes', '/equipes', 10, 1),
('Nous rejoindre',
 '<p>Le club accueille de nouveaux licenciés tout au long de la saison. Les inscriptions se font après une ou deux séances d''essai gratuites — n''hésitez pas à venir tester !</p><p>Entraînements le soir en semaine au gymnase municipal. Encadrement assuré par des entraîneurs diplômés.</p>',
 'Toutes les actualités', '/blog', 20, 1);
