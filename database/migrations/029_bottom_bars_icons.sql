-- Migration: 029_bottom_bars_icons
-- Description: Initialisation des icônes par défaut pour les bandeaux de navigation mobiles

INSERT IGNORE INTO `site_config` (`cle`, `valeur`) VALUES
('visitor_bottom_icon_1', 'home'),
('visitor_bottom_icon_2', 'users'),
('visitor_bottom_icon_3', 'lock'),
('visitor_bottom_icon_4', 'newspaper'),
('visitor_bottom_icon_5', 'mail'),
('member_bottom_icon_1', 'home'),
('member_bottom_icon_2', 'clipboard-check'),
('member_bottom_icon_3', 'calendar'),
('member_bottom_icon_4', 'calendar'),
('member_bottom_icon_5', 'user');
