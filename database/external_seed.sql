-- Données de test pour la base externe simulée

-- Joueurs (25 joueurs avec diverses combinaisons de flags)
INSERT IGNORE INTO Joueurs (id, Nom, Prenom, Email, Eq_L1, Eq_L2, Eq_L3, Eq_L4, Eq_Open, DEP, Eq_Heitz, Eq_Aico, CoupeLoisir, UFOLEP_1, UFOLEP_2, UFOLEP_3, M18F, M15F, R2F, Loisir, Debutant) VALUES
(1,  'Martin',    'Sophie',    'sophie.martin@example.com',    1,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0),
(2,  'Dubois',    'Claire',    'claire.dubois@example.com',    1,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0),
(3,  'Bernard',   'Julie',     'julie.bernard@example.com',    1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0),
(4,  'Thomas',    'Marie',     'marie.thomas@example.com',     0,1,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0),
(5,  'Robert',    'Laura',     'laura.robert@example.com',     0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0),
(6,  'Petit',     'Nathalie',  'nathalie.petit@example.com',   0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0),
(7,  'Richard',   'Caroline',  'caroline.richard@example.com', 0,0,1,0,0,0,0,1,0,0,0,0,0,0,0,0,0),
(8,  'Durand',    'Isabelle',  'isabelle.durand@example.com',  0,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0),
(9,  'Lefevre',   'Anne',      'anne.lefevre@example.com',     0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0),
(10, 'Moreau',    'Sylvie',    'sylvie.moreau@example.com',    0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0),
(11, 'Simon',     'Lucie',     'lucie.simon@example.com',      0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,0),
(12, 'Laurent',   'Emma',      'emma.laurent@example.com',     0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,0),
(13, 'Michel',    'Céline',    'celine.michel@example.com',    0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,0),
(14, 'Lefebvre',  'Patricia',  'patricia.lefebvre@example.com',0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0),
(15, 'Garcia',    'Véronique', 'veronique.garcia@example.com', 0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0),
(16, 'Martinez',  'Christine', 'christine.martinez@example.com',0,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0),
(17, 'Roux',      'Sandrine',  'sandrine.roux@example.com',    0,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0),
(18, 'Fournier',  'Virginie',  'virginie.fournier@example.com',0,0,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0),
(19, 'Morel',     'Amandine',  NULL,                           0,0,0,0,0,0,0,0,0,0,0,0,1,0,0,0,0),
(20, 'Girard',    'Manon',     NULL,                           0,0,0,0,0,0,0,0,0,0,0,0,1,0,0,0,0),
(21, 'Andre',     'Léa',       NULL,                           0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,0,0),
(22, 'Leroy',     'Jade',      NULL,                           0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,0,0),
(23, 'Bonnet',    'Inès',      NULL,                           0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0,0),
(24, 'Dupont',    'Françoise', 'francoise.dupont@example.com', 0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,1,0),
(25, 'Lambert',   'Martine',   'martine.lambert@example.com',  0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,1);

-- Manifestations (15 événements autour d'aujourd'hui)
INSERT IGNORE INTO Manifestation (id, LibelleManif, DateManif, HeureManif, Lieu, commentaire, type_manifestation, statut) VALUES
(1,  'USM Volley vs Club Bordeaux',        DATE_ADD(CURDATE(), INTERVAL 3  DAY), '19:00:00', 'Gymnase Mios',          NULL,                           'Match',         NULL),
(2,  'Entraînement L1/L2',                 DATE_ADD(CURDATE(), INTERVAL 1  DAY), '20:30:00', 'Gymnase Mios',          NULL,                           'Entraînement',  NULL),
(3,  'USM Volley vs Arcachon Volley',      DATE_ADD(CURDATE(), INTERVAL 8  DAY), '18:00:00', 'Salle Arcachon',        'Déplacement — co-voiturage',   'Match',         NULL),
(4,  'Entraînement général',               DATE_ADD(CURDATE(), INTERVAL 2  DAY), '20:30:00', 'Gymnase Mios',          NULL,                           'Entraînement',  NULL),
(5,  'USM Volley vs Pessac VB',            DATE_ADD(CURDATE(), INTERVAL 12 DAY), '20:00:00', 'Gymnase Mios',          NULL,                           'Match',         NULL),
(6,  'Entraînement UFOLEP',                DATE_ADD(CURDATE(), INTERVAL 4  DAY), '19:00:00', 'Gymnase Mios',          NULL,                           'Entraînement',  NULL),
(7,  'Match DEP féminin vs Andernos',      DATE_ADD(CURDATE(), INTERVAL 6  DAY), '17:00:00', 'Gymnase Andernos',      'Retour prévu 20h',             'Match',         NULL),
(8,  'Entraînement jeunes',                DATE_ADD(CURDATE(), INTERVAL 3  DAY), '18:00:00', 'Gymnase Mios',          NULL,                           'Entraînement',  NULL),
(9,  'Tournoi Open',                       DATE_ADD(CURDATE(), INTERVAL 15 DAY), '09:00:00', 'Gymnase Mios',          'Journée complète',             'Tournoi',       NULL),
(10, 'Entraînement L3/L4',                 DATE_ADD(CURDATE(), INTERVAL 5  DAY), '21:00:00', 'Gymnase Mios',          NULL,                           'Entraînement',  NULL),
(11, 'USM Volley vs Mérignac VB',          DATE_ADD(CURDATE(), INTERVAL 20 DAY), '20:30:00', 'Gymnase Mios',          NULL,                           'Match',         NULL),
(12, 'Entraînement L1 — annulé',           DATE_ADD(CURDATE(), INTERVAL 7  DAY), '20:30:00', 'Gymnase Mios',          'Gymnase indisponible',         'Entraînement',  'Annulé'),
(13, 'Match UFOLEP 2 vs Lège',             DATE_ADD(CURDATE(), INTERVAL 10 DAY), '15:00:00', 'Gymnase Lège',          NULL,                           'Match',         NULL),
(14, 'Stage jeunes',                       DATE_ADD(CURDATE(), INTERVAL 18 DAY), '09:00:00', 'Gymnase Mios',          'Inscription obligatoire',      'Stage',         NULL),
(15, 'Entraînement loisir',                DATE_ADD(CURDATE(), INTERVAL 2  DAY), '19:30:00', 'Gymnase Mios',          NULL,                           'Entraînement',  NULL);
