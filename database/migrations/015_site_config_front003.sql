-- 015_site_config_front003.sql
-- Seeds des clés site_config utilisées par le thème front003.
-- Valeurs par défaut issues de la maquette zip (palette ink/flame/ember/bone, Archivo italic).
-- INSERT IGNORE : aucune clé existante n'est écrasée.

INSERT IGNORE INTO site_config (cle, valeur) VALUES
  -- Palette
  ('primary_color',            '#E34226'),
  ('secondary_color',          '#A6221C'),
  ('text_color',               '#1A1A1A'),
  ('background_color',         '#F7F4EE'),
  ('surface_color',            '#FFFFFF'),
  -- Typographie
  ('font_family',              'Manrope'),
  ('display_font_family',      'Archivo'),
  -- Liens d'action principaux
  ('adherer_url',              '/contact'),
  ('essai_url',                '/contact'),
  -- Trust strip (Hero)
  ('trust_badge_1_label',      'Affilié'),
  ('trust_badge_1_strong',     'FFVB'),
  ('trust_badge_2_label',      'Label'),
  ('trust_badge_2_strong',     'Formation Jeunes'),
  ('trust_badge_3_label',      'nouveaux licenciés cette saison'),
  -- Badges flottants (Hero card)
  ('hero_badge_trophy_label',  '3e place'),
  ('hero_badge_trophy_sub',    'Région · Poule B'),
  ('hero_motto',               'Esprit · Famille · Volley'),
  -- Marquee
  ('marquee_tags',             'Volley loisir,Compétition,Beach,Baby-Volley,M11 → Séniors,Esprit club'),
  -- CTA Adhérer (features colonne droite)
  ('cta_feature_1_label',      'Cotisation à partir de 145€'),
  ('cta_feature_1_sub',        'Aides ANCV & Pass''Sport acceptés'),
  ('cta_feature_2_label',      'Encadrants diplômés FFVB'),
  ('cta_feature_2_sub',        'Niveau Régional et National'),
  ('cta_feature_3_label',      '4 créneaux par semaine'),
  ('cta_feature_3_sub',        'Du lundi au samedi · Mios');
