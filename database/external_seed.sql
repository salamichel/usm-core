SET NAMES utf8mb4;

-- Données de test pour la base externe simulée

-- Joueurs (25 joueuses avec diverses combinaisons de flags)
-- Colonnes : id_joueur, Nom, Prénom, Mel, NLicence,
--            Adulte, Jeune, Compétition, Loisir, Débutant,
--            L1, L2, L3, L4, Open, DEP,
--            Heitz, Aico, CoupeLoisir,
--            UFOLEP_1, UFOLEP_2, UFOLEP_3, M18F, M15F, R2F
INSERT IGNORE INTO Joueurs
  (id_joueur, Nom, `Prénom`, Mel, NLicence,
   Adulte, Jeune, `Compétition`, Loisir, `Débutant`,
   L1, L2, L3, L4, Open, DEP,
   Heitz, Aico, CoupeLoisir,
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

-- Manifestations (15 événements)
-- Format réel : ManifestationTypée = "Disponibilités - {Type} - {Description}"
--               Manifestation = '' (toujours vide)
--               Creneau = '' (toujours vide, l'heure est dans Date)
INSERT IGNORE INTO Manifestation
  (id_manifestation, `ManifestationTypée`, Manifestation, `Date`, `Durée_créneau`, Lieu, Nombre_terrain, Creneau, Commentaire, Statut)
VALUES
(1,  'Disponibilités - Match - Match L1',                  '', DATE_ADD(NOW(), INTERVAL 11 DAY), '3h',   'Gymnase Mios',                    1, '', 'L2A007 (Pessac)',          'Confirmé'),
(2,  'Disponibilités - Match - Match L2',                  '', DATE_ADD(NOW(), INTERVAL 18 DAY), '3h30', 'Chantecler (Salle GINKO)',         0, '', NULL,                      'Confirmé'),
(3,  'Disponibilités - Match - Match L3',                  '', DATE_ADD(NOW(), INTERVAL 21 DAY), '3h',   'Gymnase Mios',                    1, '', NULL,                      'Confirmé'),
(4,  'Disponibilités - Match - Match L4',                  '', DATE_ADD(NOW(), INTERVAL 25 DAY), '4h',   'Salles',                          1, '', 'contre L2',               'Confirmé'),
(5,  'Disponibilités - Match - Match DEP',                 '', DATE_ADD(NOW(), INTERVAL 14 DAY), '3h',   'Gymnase Andernos',                0, '', 'Déplacement',             'Confirmé'),
(6,  'Disponibilités - Match - Plateau UFOLEP 2',          '', DATE_ADD(NOW(), INTERVAL 28 DAY), '3h30', 'Salles',                          1, '', 'UFOLEP 3 et 2',           'Confirmé'),
(7,  'Disponibilités - Entraînement - Entraînement L1/L2', '', DATE_ADD(NOW(), INTERVAL  1 DAY), '1h30', 'Gymnase Mios',                    2, '', NULL,                      'Confirmé'),
(8,  'Disponibilités - Entraînement - Entraînement L3/L4', '', DATE_ADD(NOW(), INTERVAL  1 DAY), '1h30', 'Gymnase Mios',                    1, '', NULL,                      'Confirmé'),
(9,  'Disponibilités - Entraînement - Entraînement UFOLEP','', DATE_ADD(NOW(), INTERVAL  3 DAY), '1h30', 'Gymnase Mios',                    1, '', NULL,                      'Confirmé'),
(10, 'Disponibilités - Entraînement - Entraînement jeunes','', DATE_ADD(NOW(), INTERVAL  2 DAY), '1h30', 'Gymnase Mios',                    2, '', NULL,                      'Confirmé'),
(11, 'Disponibilités - Entraînement - Entraînement loisir','', DATE_ADD(NOW(), INTERVAL  2 DAY), '1h30', 'Gymnase Mios',                    1, '', NULL,                      'Confirmé'),
(12, 'Disponibilités - Entraînement - Entraînement L1',    '', DATE_ADD(NOW(), INTERVAL  5 DAY), '1h30', 'Gymnase Mios',                    0, '', 'Gymnase indisponible',    'Annulé'),
(13, 'Disponibilités - Entraînement - Entraînement L3/L4', '', DATE_ADD(NOW(), INTERVAL  8 DAY), '1h30', 'Gymnase Mios',                    1, '', NULL,                      'Confirmé'),
(14, 'Disponibilités - Tournoi - Tournoi Open',            '', DATE_ADD(NOW(), INTERVAL 15 DAY), '8h',   'Gymnase Mios',                    4, '', 'Journée complète',        'Confirmé'),
(15, 'Disponibilités - Stage - Stage jeunes',              '', DATE_ADD(NOW(), INTERVAL 18 DAY), '8h',   'Gymnase Mios',                    2, '', 'Inscription obligatoire', 'Confirmé'),
(16, 'Présences - Beach - BEACH CAP33 Tournoi',              '', DATE_ADD(NOW(), INTERVAL 10 DAY), '4h',   'Plage CAP33',                     0, '', 'Tournoi beach en été',        'Confirmé'),
(17, 'Présences - Beach - BEACH CAP33 Famille',              '', DATE_ADD(NOW(), INTERVAL 12 DAY), '4h',   'Plage CAP33',                     0, '', 'Ouvert aux membres et familles','Confirmé'),
(18, 'Présences - Club - AG',                                '', DATE_ADD(NOW(), INTERVAL 7 DAY),  '2h',   'Salle CAP33',                     0, '', 'Assemblée générale du club',   'Confirmé'),
(19, 'Présences - Club - Forum ASSO',                        '', DATE_ADD(NOW(), INTERVAL 9 DAY),  '4h',   'Forum CAP33',                     0, '', 'Stand et rencontres associatives','Confirmé');
