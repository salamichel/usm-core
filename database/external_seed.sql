SET NAMES utf8mb4;

-- Données de test pour la base externe simulée

-- Joueurs (25 joueuses avec diverses combinaisons de flags)
-- Colonnes : id_joueur, Nom, Prénom, Mel, NLicence,
--            Adulte, Jeune, Compétition, Loisir, Débutant,
--            Eq_L1, Eq_L2, Eq_L3, Eq_L4, Eq_Open, DEP,
--            Eq_Heitz, Eq_Aico, CoupeLoisir,
--            UFOLEP_1, UFOLEP_2, UFOLEP_3, M18F, M15F, R2F
INSERT IGNORE INTO Joueurs
  (id_joueur, Nom, `Prénom`, Mel, NLicence,
   Adulte, Jeune, `Compétition`, Loisir, `Débutant`,
   Eq_L1, Eq_L2, Eq_L3, Eq_L4, Eq_Open, DEP,
   Eq_Heitz, Eq_Aico, CoupeLoisir,
   UFOLEP_1, UFOLEP_2, UFOLEP_3, M18F, M15F, R2F)
VALUES
(1,  'Martin',   'Sophie',    'sophie.martin@example.com',    12001,  1,0,1,0,0,  1,0,0,0,0,0, 1,0,0, 0,0,0,0,0,0),
(2,  'Dubois',   'Claire',    'claire.dubois@example.com',    12002,  1,0,1,0,0,  1,0,0,0,0,0, 1,0,0, 0,0,0,0,0,0),
(3,  'Bernard',  'Julie',     'julie.bernard@example.com',    12003,  1,0,1,0,0,  1,0,0,0,0,0, 0,0,0, 0,0,0,0,0,0),
(4,  'Thomas',   'Marie',     'marie.thomas@example.com',     12004,  1,0,1,0,0,  0,1,0,0,0,0, 1,0,0, 0,0,0,0,0,0),
(5,  'Robert',   'Laura',     'laura.robert@example.com',     12005,  1,0,1,0,0,  0,1,0,0,0,0, 0,0,0, 0,0,0,0,0,0),
(6,  'Petit',    'Nathalie',  'nathalie.petit@example.com',   12006,  1,0,1,0,0,  0,1,0,0,0,0, 0,0,0, 0,0,0,0,0,0),
(7,  'Richard',  'Caroline',  'caroline.richard@example.com', 12007,  1,0,1,0,0,  0,0,1,0,0,0, 0,1,0, 0,0,0,0,0,0),
(8,  'Durand',   'Isabelle',  'isabelle.durand@example.com',  12008,  1,0,1,0,0,  0,0,1,0,0,0, 0,0,0, 0,0,0,0,0,0),
(9,  'Lefevre',  'Anne',      'anne.lefevre@example.com',     12009,  1,0,1,0,0,  0,0,0,1,0,0, 0,0,0, 0,0,0,0,0,0),
(10, 'Moreau',   'Sylvie',    'sylvie.moreau@example.com',    12010,  1,0,1,0,0,  0,0,0,1,0,0, 0,0,0, 0,0,0,0,0,0),
(11, 'Simon',    'Lucie',     'lucie.simon@example.com',      12011,  1,0,1,0,0,  0,0,0,0,1,0, 0,0,0, 0,0,0,0,0,0),
(12, 'Laurent',  'Emma',      'emma.laurent@example.com',     12012,  1,0,1,0,0,  0,0,0,0,1,0, 0,0,0, 0,0,0,0,0,0),
(13, 'Michel',   'Céline',    'celine.michel@example.com',    12013,  1,0,1,0,0,  0,0,0,0,0,1, 0,0,0, 0,0,0,0,0,0),
(14, 'Lefebvre', 'Patricia',  'patricia.lefebvre@example.com',12014,  1,0,1,0,0,  0,0,0,0,0,0, 0,0,0, 1,0,0,0,0,0),
(15, 'Garcia',   'Véronique', 'veronique.garcia@example.com', 12015,  1,0,1,0,0,  0,0,0,0,0,0, 0,0,0, 1,0,0,0,0,0),
(16, 'Martinez', 'Christine', 'christine.martinez@example.com',12016, 1,0,1,0,0,  0,0,0,0,0,0, 0,0,0, 0,1,0,0,0,0),
(17, 'Roux',     'Sandrine',  'sandrine.roux@example.com',    12017,  1,0,1,0,0,  0,0,0,0,0,0, 0,0,0, 0,1,0,0,0,0),
(18, 'Fournier', 'Virginie',  'virginie.fournier@example.com',12018,  1,0,1,0,0,  0,0,0,0,0,0, 0,0,0, 0,0,1,0,0,0),
(19, 'Morel',    'Amandine',  NULL,                           12019,  0,1,0,0,0,  0,0,0,0,0,0, 0,0,0, 0,0,0,1,0,0),
(20, 'Girard',   'Manon',     NULL,                           12020,  0,1,0,0,0,  0,0,0,0,0,0, 0,0,0, 0,0,0,1,0,0),
(21, 'Andre',    'Léa',       NULL,                           12021,  0,1,0,0,0,  0,0,0,0,0,0, 0,0,0, 0,0,0,0,1,0),
(22, 'Leroy',    'Jade',      NULL,                           12022,  0,1,0,0,0,  0,0,0,0,0,0, 0,0,0, 0,0,0,0,1,0),
(23, 'Bonnet',   'Inès',      NULL,                           12023,  0,1,0,0,0,  0,0,0,0,0,0, 0,0,0, 0,0,0,0,0,1),
(24, 'Dupont',   'Françoise', 'francoise.dupont@example.com', 12024,  1,0,0,1,0,  0,0,0,0,0,0, 0,0,1, 0,0,0,0,0,0),
(25, 'Lambert',  'Martine',   'martine.lambert@example.com',  12025,  1,0,0,0,1,  0,0,0,0,0,0, 0,0,1, 0,0,0,0,0,0);

-- Manifestations (15 événements autour d'aujourd'hui)
-- ManifestationTypée = nom descriptif (80 chars max)
-- Manifestation      = type court : Match | Entraînement | Tournoi | Stage
INSERT IGNORE INTO Manifestation
  (id_manifestation, `ManifestationTypée`, Manifestation, `Date`, Creneau, Lieu, Commentaire, Statut)
VALUES
(1,  'USM Volley vs Bordeaux L1',   'Match',         DATE_ADD(NOW(), INTERVAL  3 DAY), '19:00', 'Gymnase Mios',     NULL,                       NULL),
(2,  'Entraînement L1/L2',          'Entraînement',  DATE_ADD(NOW(), INTERVAL  1 DAY), '20:30', 'Gymnase Mios',     NULL,                       NULL),
(3,  'USM Volley vs Arcachon',      'Match',         DATE_ADD(NOW(), INTERVAL  8 DAY), '18:00', 'Salle Arcachon',   'Déplacement co-voiturage', NULL),
(4,  'Entraînement général',        'Entraînement',  DATE_ADD(NOW(), INTERVAL  2 DAY), '20:30', 'Gymnase Mios',     NULL,                       NULL),
(5,  'USM Volley vs Pessac VB',     'Match',         DATE_ADD(NOW(), INTERVAL 12 DAY), '20:00', 'Gymnase Mios',     NULL,                       NULL),
(6,  'Entraînement UFOLEP',         'Entraînement',  DATE_ADD(NOW(), INTERVAL  4 DAY), '19:00', 'Gymnase Mios',     NULL,                       NULL),
(7,  'Match DEP vs Andernos',       'Match',         DATE_ADD(NOW(), INTERVAL  6 DAY), '17:00', 'Gymnase Andernos', 'Retour prévu 20h',         NULL),
(8,  'Entraînement jeunes',         'Entraînement',  DATE_ADD(NOW(), INTERVAL  3 DAY), '18:00', 'Gymnase Mios',     NULL,                       NULL),
(9,  'Tournoi Open',                'Tournoi',       DATE_ADD(NOW(), INTERVAL 15 DAY), '09:00', 'Gymnase Mios',     'Journée complète',         NULL),
(10, 'Entraînement L3/L4',          'Entraînement',  DATE_ADD(NOW(), INTERVAL  5 DAY), '21:00', 'Gymnase Mios',     NULL,                       NULL),
(11, 'USM Volley vs Mérignac VB',   'Match',         DATE_ADD(NOW(), INTERVAL 20 DAY), '20:30', 'Gymnase Mios',     NULL,                       NULL),
(12, 'Entraînement L1 — annulé',    'Entraînement',  DATE_ADD(NOW(), INTERVAL  7 DAY), '20:30', 'Gymnase Mios',     'Gymnase indisponible',     'Annulé'),
(13, 'Match UFOLEP 2 vs Lège',      'Match',         DATE_ADD(NOW(), INTERVAL 10 DAY), '15:00', 'Gymnase Lège',     NULL,                       NULL),
(14, 'Stage jeunes',                'Stage',         DATE_ADD(NOW(), INTERVAL 18 DAY), '09:00', 'Gymnase Mios',     'Inscription obligatoire',  NULL),
(15, 'Entraînement loisir',         'Entraînement',  DATE_ADD(NOW(), INTERVAL  2 DAY), '19:30', 'Gymnase Mios',     NULL,                       NULL);
