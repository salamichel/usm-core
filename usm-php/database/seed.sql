-- Données d'exemple USM Volley
-- Importer après schema.sql

-- Menu
INSERT INTO menu_items (label, link_type, target, parent_id, position) VALUES
('Club',      'none', NULL,     NULL, 0),
('Blog',      'url',  '/blog',  NULL, 1),
('Documents', 'url',  '/documents', NULL, 2);

-- Sous-menu du club
INSERT INTO menu_items (label, link_type, target, parent_id, position)
SELECT 'À propos', 'page', 'a-propos', id, 0 FROM menu_items WHERE label='Club' LIMIT 1;
INSERT INTO menu_items (label, link_type, target, parent_id, position)
SELECT 'Contact', 'page', 'contact', id, 1 FROM menu_items WHERE label='Club' LIMIT 1;

-- Pages statiques
INSERT INTO pages (title, slug, content, is_published) VALUES
('À propos', 'a-propos',
 '<h2>Le club</h2><p>USM Volley est un club de volley-ball fondé en 1975.</p>',
 1),
('Contact', 'contact',
 '<p>Pour nous contacter : <a href="mailto:contact@usm-volley.fr">contact@usm-volley.fr</a></p>',
 1);

-- Article de blog
INSERT INTO posts (title, slug, excerpt, content, is_published, published_at) VALUES
('Bienvenue sur le nouveau site',
 'bienvenue-nouveau-site',
 'Découvrez notre tout nouveau site web.',
 '<p>Nous sommes ravis de vous présenter le nouveau site de <strong>USM Volley</strong>.</p><p>Retrouvez toutes nos actualités ici.</p>',
 1,
 NOW());
