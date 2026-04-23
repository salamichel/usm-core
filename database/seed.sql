-- Données d'exemple USM Volley
-- Idempotent : INSERT IGNORE (re-runnable sans dupliquer les données)

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Menu racine
INSERT IGNORE INTO menu_items (id, label, link_type, target, parent_id, position) VALUES
(1, 'Club',      'none', NULL,         NULL, 0),
(2, 'Blog',      'url',  '/blog',      NULL, 1),
(3, 'Documents', 'url',  '/documents', NULL, 2);

-- Sous-menu du club (parent_id=1 fixé)
INSERT IGNORE INTO menu_items (id, label, link_type, target, parent_id, position) VALUES
(4, 'À propos', 'page', 'a-propos', 1, 0),
(5, 'Contact',  'page', 'contact',  1, 1);

-- Pages statiques
INSERT IGNORE INTO pages (id, title, slug, content, is_published) VALUES
(1, 'À propos', 'a-propos',
 '<h2>Le club</h2><p>USM Volley est un club de volley-ball fondé en 1975.</p>',
 1),
(2, 'Contact', 'contact',
 '<p>Pour nous contacter : <a href="mailto:contact@usm-volley.fr">contact@usm-volley.fr</a></p>',
 1);

-- Article de blog
INSERT IGNORE INTO posts (id, title, slug, excerpt, content, is_published, published_at) VALUES
(1, 'Bienvenue sur le nouveau site',
 'bienvenue-nouveau-site',
 'Découvrez notre tout nouveau site web.',
 '<p>Nous sommes ravis de vous présenter le nouveau site de <strong>USM Volley</strong>.</p><p>Retrouvez toutes nos actualités ici.</p>',
 1,
 '2026-01-01 10:00:00');
