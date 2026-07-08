-- Migration: 028_bottom_bars_config
-- Description: Initialisation des configurations pour les bandeaux de navigation mobiles du bas (Visiteur et Membre)

INSERT IGNORE INTO `site_config` (`cle`, `valeur`) VALUES
('visitor_bottom_url_1', ''),
('visitor_bottom_label_1', 'Accueil'),
('visitor_bottom_url_2', 'equipes'),
('visitor_bottom_label_2', 'Équipes'),
('visitor_bottom_url_3', 'member/login'),
('visitor_bottom_label_3', 'Connexion'),
('visitor_bottom_url_4', 'blog'),
('visitor_bottom_label_4', 'Actus'),
('visitor_bottom_url_5', 'contact'),
('visitor_bottom_label_5', 'Contact'),
('member_bottom_url_1', 'member/dashboard'),
('member_bottom_label_1', 'Accueil'),
('member_bottom_url_2', 'member/dashboard?filter=this-week&scroll=1'),
('member_bottom_label_2', 'Ma Sem.'),
('member_bottom_url_3', 'agenda?view=cards#filters'),
('member_bottom_label_3', 'Agenda'),
('member_bottom_url_4', 'agenda?this_week=1&view=cards&scroll=1'),
('member_bottom_label_4', 'Sem. Club'),
('member_bottom_url_5', 'member/profile'),
('member_bottom_label_5', 'Profil');
