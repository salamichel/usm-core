<?php
declare(strict_types=1);

namespace App\Services\Agenda;

use App\Core\ExternalDatabase;
use App\Helpers\ParticipationStatus;
use App\Models\EquipeConfig;

/**
 * Accès en lecture seule à la base externe (tables Manifestation, Joueurs, Participation).
 *
 * Cette classe est responsable uniquement des requêtes SQL.
 * La transformation des données est déléguée à EventNormalizer.
 * Les stats de participation sont déléguées à ParticipationStatsService.
 */
class EventRepository
{
    /** @var array<int, string>|null Cache joueurs (id_joueur → "Nom Prénom") */
    private static ?array $allPlayersCache = null;

    // ── Requêtes principales ──────────────────────────────────────────────────

    /**
     * Prochains matchs (ManifestationTypée LIKE '% - Match - %').
     */
    public static function getUpcomingMatches(int $limit = 5): array
    {
        return self::queryByPattern('% - Match - %', $limit);
    }

    /**
     * Prochains entraînements (ManifestationTypée LIKE '% - Entra%').
     */
    public static function getUpcomingTrainings(int $limit = 5): array
    {
        return self::queryByPattern('% - Entra%', $limit);
    }

    /**
     * Prochains événements filtrés par une chaîne présente dans ManifestationTypée ou Lieu.
     */
    public static function getUpcomingByType(string $needle, int $limit = 5): array
    {
        try {
            $stmt = ExternalDatabase::get()->prepare(
                "SELECT * FROM Manifestation
                 WHERE (`ManifestationTypée` LIKE :pat OR `Lieu` LIKE :pat)
                   AND `Date` >= CURDATE()
                 ORDER BY `Date` ASC
                 LIMIT :limit"
            );
            $stmt->bindValue(':pat',   '%' . $needle . '%');
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        return array_map([EventNormalizer::class, 'buildEvent'], $rows);
    }

    /**
     * Prochains événements filtrés par plusieurs chaînes (OR entre les fragments).
     */
    public static function getUpcomingByTypes(array $needles, int $limit = 5): array
    {
        if (empty($needles)) {
            return [];
        }

        $conditions = [];
        $bindings   = [];
        foreach ($needles as $needle) {
            $conditions[] = "(`ManifestationTypée` LIKE ? OR `Lieu` LIKE ?)";
            $bindings[]   = '%' . $needle . '%';
            $bindings[]   = '%' . $needle . '%';
        }

        $sql = "SELECT * FROM Manifestation
                WHERE (" . implode(' OR ', $conditions) . ")
                  AND `Date` >= CURDATE()
                ORDER BY `Date` ASC
                LIMIT " . (int)$limit;

        try {
            $stmt = ExternalDatabase::get()->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        return array_map([EventNormalizer::class, 'buildEvent'], $rows);
    }

    /**
     * Récupère et normalise un événement par son ID.
     */
    public static function getEventById(int $id): ?array
    {
        try {
            $stmt = ExternalDatabase::get()->prepare(
                "SELECT * FROM Manifestation WHERE id_manifestation = ?"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
        } catch (\Throwable) {
            return null;
        }

        return $row ? self::normalizeManifestation($row) : null;
    }

    /**
     * Options de filtres disponibles (types simples + lieux) — pour les dropdowns.
     */
    public static function getAvailableFilters(): array
    {
        try {
            $db = ExternalDatabase::get();

            $types = [];
            $stmt  = $db->query(
                "SELECT DISTINCT ManifestationTypée FROM Manifestation
                 WHERE ManifestationTypée LIKE '% - %'
                 ORDER BY ManifestationTypée"
            );
            while ($row = $stmt->fetch()) {
                $parts = explode(' - ', $row['ManifestationTypée'], 2);
                if (count($parts) >= 2 && !in_array($parts[1], $types)) {
                    $types[] = $parts[1];
                }
            }

            $locations = [];
            $stmt      = $db->query(
                "SELECT DISTINCT Lieu FROM Manifestation WHERE Lieu != '' ORDER BY Lieu"
            );
            while ($row = $stmt->fetch()) {
                $locations[] = $row['Lieu'];
            }

            return ['types' => $types, 'locations' => $locations];
        } catch (\Throwable) {
            return ['types' => [], 'locations' => []];
        }
    }

    /**
     * Compte les manifestations correspondant aux filtres (pour la pagination).
     */
    public static function countManifestations(array $filters = []): int
    {
        try {
            $type     = $filters['type']      ?? null;
            $location = $filters['lieu']  ?? null;
            $dateFrom = $filters['date_from'] ?? null;
            $dateTo   = $filters['date_to']   ?? null;

            $sql      = "SELECT COUNT(*) as cnt FROM Manifestation WHERE 1=1";
            $bindings = [];

            if ($type)     { $sql .= " AND ManifestationTypée LIKE ?"; $bindings[] = "% - $type - %"; }
            if ($location) { $sql .= " AND Lieu = ?";                  $bindings[] = $location; }
            if ($dateFrom) { $sql .= " AND Date >= ?";                 $bindings[] = $dateFrom; }
            if ($dateTo)   { $sql .= " AND Date <= ?";                 $bindings[] = $dateTo; }

            $stmt = ExternalDatabase::get()->prepare($sql);
            $stmt->execute($bindings);
            return (int)(($stmt->fetch())['cnt'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Options complètes de filtres (types, lieux, noms de manifestation, équipes).
     * Utilisé par les formulaires de filtrage de l'agenda admin.
     */
    public static function getFilterOptions(): array
    {
        try {
            $db = ExternalDatabase::get();

            $types = [];
            $mots = \App\Models\MotsClef::getByCategory('ManifestationTypée');
            foreach ($mots as $mot) {
                $parts = explode(' - ', $mot);
                $typePart = count($parts) >= 2 ? $parts[0] . ' - ' . $parts[1] : $mot;
                $type = trim(str_replace('Disponibilités - ', '', $typePart));
                if (!empty($type) && !in_array($type, $types, true)) {
                    $types[] = $type;
                }
            }
            sort($types);

            $lieux = [];
            $stmt      = $db->query(
                "SELECT DISTINCT Lieu FROM Manifestation
                 WHERE id_manifestation > 0 AND Date >= CURDATE() AND Lieu IS NOT NULL
                 ORDER BY Lieu"
            );
            while ($row = $stmt->fetch()) {
                if (!empty($row['Lieu'])) {
                    $lieux[] = $row['Lieu'];
                }
            }

            $manifestationNames = [];
            $stmt               = $db->query(
                "SELECT DISTINCT TRIM(SUBSTRING_INDEX(ManifestationTypée, ' - ', -1)) AS manif_name
                 FROM Manifestation
                 WHERE id_manifestation > 0 AND Date >= CURDATE()
                   AND ManifestationTypée LIKE '% - % - %'
                 ORDER BY manif_name"
            );
            while ($row = $stmt->fetch()) {
                if (!empty($row['manif_name'])) {
                    $manifestationNames[] = $row['manif_name'];
                }
            }

            $teams = [];
            foreach (\App\Models\MotsClef::getByCategory('EquipeParEquipe') as $mot) {
                if (!empty($mot)) {
                    $teams[] = $mot;
                }
            }

            return [
                'types'              => $types,
                'lieux'              => $lieux,
                'manifestationNames' => $manifestationNames,
                'teams'              => $teams,
            ];
        } catch (\Throwable) {
            return ['types' => [], 'lieux' => []];
        }
    }

    /**
     * Tableau croisé joueurs × manifestations avec données de participation.
     *
     * Filtres supportés : team, lieu, type, manifestation, this_week, hide_empty_players.
     *
     * @return array{joueurs: array, manifestations: array, cross: array}
     */
    public static function getCrossTable(array $filters = []): array
    {
        $empty = ['joueurs' => [], 'manifestations' => [], 'cross' => []];

        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                error_log('getCrossTable: ExternalDatabase::get() returned null');
                return $empty;
            }

            // 1. Joueurs (filtrés par équipe si demandé)
            $joueurs = [];
            if (!empty($filters['team'])) {
                $teamCol    = self::teamColumn($filters['team']);
                $joueurStmt = $teamCol
                    ? $db->prepare("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 AND `$teamCol` = 1 ORDER BY Nom")
                    : $db->prepare("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 AND Equipe = ? ORDER BY Nom");
                $teamCol ? $joueurStmt->execute() : $joueurStmt->execute([$filters['team']]);
                $stmt = $joueurStmt;
            } else {
                $stmt = $db->query("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 ORDER BY Nom");
            }

            if (!$stmt) {
                error_log('getCrossTable: Failed to query Joueurs - ' . json_encode($db->errorInfo()));
                return $empty;
            }
            while ($row = $stmt->fetch()) {
                $joueurs[(int)$row['id_joueur']] = $row['Nom'] . ' ' . $row['Prénom'];
            }

            // 2. Manifestations futures avec filtres
            $sql      = "SELECT id_manifestation, `ManifestationTypée`, `Date`,
                                DATE_FORMAT(`Date`, '%W %d %M') AS date_fr,
                                `Durée_créneau`, Nombre_terrain, Lieu, Commentaire, Statut
                         FROM Manifestation
                         WHERE id_manifestation > 0 AND `Date` >= CURDATE()";
            $bindings = [];

            if (!empty($filters['lieu']))     { $sql .= " AND Lieu = ?";                    $bindings[] = $filters['lieu']; }
            if (!empty($filters['type']))         { $sql .= " AND ManifestationTypée LIKE ?";   $bindings[] = '%' . $filters['type'] . '%'; }
            if (!empty($filters['manifestation'])) { $sql .= " AND ManifestationTypée LIKE ?"; $bindings[] = '% - ' . $filters['manifestation']; }
            if (!empty($filters['this_week'])) {
                $sql      .= " AND Date BETWEEN ? AND ?";
                $bindings[] = date('Y-m-d', strtotime('Monday this week'));
                $bindings[] = date('Y-m-d', strtotime('Sunday this week')) . ' 23:59:59';
            }
            $sql .= " ORDER BY `Date` ASC";

            $stmt = $db->prepare($sql);
            if (!$stmt || !$stmt->execute($bindings)) {
                error_log('getCrossTable: Failed to query Manifestation - ' . json_encode($db->errorInfo()));
                return $empty;
            }

            $manifestations = [];
            while ($row = $stmt->fetch()) {
                $id                = (int)$row['id_manifestation'];
                $manifestations[$id] = self::normalizeManifestation($row, count($joueurs));
            }
            error_log('getCrossTable: Fetched ' . count($manifestations) . ' manifestations');

            if (empty($manifestations)) {
                return $empty;
            }

            // 3. Table croisée via CROSS JOIN + LEFT JOIN
            $ids   = implode(',', array_keys($manifestations));
            $cross = [];
            foreach ($joueurs as $jid => $nom) {
                $cross[$jid] = [
                    'nb_participation'       => 0,
                    'nb_non_absence'         => 0,
                    'nb_ne_sait_pas'         => 0,
                    'nb_ne_sait_pas_proche'  => 0,
                ];
                foreach (array_keys($manifestations) as $mid) {
                    $cross[$jid][$mid] = '';
                }
            }

            $dateTropProche = time() + 3 * 24 * 3600;
            $stmt           = $db->query(
                "SELECT j.id_joueur, m.id_manifestation,
                        COALESCE(p.Participation, '') AS Participation,
                        DATE_FORMAT(m.`Date`, '%Y-%m-%d %H:%i') AS date2
                 FROM Joueurs j
                 CROSS JOIN Manifestation m
                 LEFT JOIN Participation p ON j.id_joueur = p.id_joueur AND m.id_manifestation = p.id_manifestation
                 WHERE m.id_manifestation IN ($ids)
                   AND j.id_joueur > 0
                 ORDER BY j.Nom, m.`Date`"
            );

            while ($row = $stmt->fetch()) {
                $jid  = (int)$row['id_joueur'];
                $mid  = (int)$row['id_manifestation'];
                $part = trim((string)($row['Participation'] ?? ''));

                if (!isset($cross[$jid]) || !isset($manifestations[$mid])) {
                    continue;
                }

                $cross[$jid][$mid] = $part;

                if ($part !== '') {
                    $status = new ParticipationStatus($part);
                    $cross[$jid]['nb_participation']++;

                    if ($status->isNonAbsence()) {
                        $cross[$jid]['nb_non_absence']++;
                    }
                    if ($status->isUnknown()) {
                        $cross[$jid]['nb_ne_sait_pas']++;
                        if (strtotime($row['date2']) < $dateTropProche) {
                            $cross[$jid]['nb_ne_sait_pas_proche']++;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('getCrossTable: Exception - ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $empty;
        }

        if (!empty($filters['hide_empty_players'])) {
            foreach (array_keys($joueurs) as $jid) {
                if ($cross[$jid]['nb_participation'] === 0) {
                    unset($joueurs[$jid], $cross[$jid]);
                }
            }
        }

        return ['joueurs' => $joueurs, 'manifestations' => $manifestations, 'cross' => $cross];
    }

    /**
     * Prochains matchs d'une équipe avec leurs stats de participation agrégées.
     *
     * @param string      $teamCode            Identifiant équipe (slug_colonne)
     * @param int         $limit               Nombre max de matchs
     * @param string|null $manifestationFilter Filtre optionnel sur le nom de la manifestation
     */
    public static function getUpcomingMatchesForTeam(
        string $teamCode,
        int $limit = 5,
        ?string $manifestationFilter = null
    ): array {
        try {
            $db = ExternalDatabase::get();
            if (!$db || !self::teamColumn($teamCode)) {
                return [];
            }

            $manifestationClause = "m.ManifestationTypée LIKE '% - Match - %'";
            $bindings            = [];

            if (!empty($manifestationFilter)) {
                $manifestationClause .= " AND m.ManifestationTypée LIKE ?";
                $bindings[]          = '%' . $manifestationFilter;
            }
            $bindings[] = $limit;

            $stmt = $db->prepare(
                "SELECT m.id_manifestation, m.ManifestationTypée, m.Date,
                        m.Durée_créneau, m.Nombre_terrain, m.Lieu, m.Commentaire, m.Statut
                 FROM Manifestation m
                 WHERE m.id_manifestation > 0 AND $manifestationClause
                   AND m.Date >= CURDATE()
                 ORDER BY m.Date ASC
                 LIMIT ?"
            );

            if (!$stmt->execute($bindings)) {
                return [];
            }

            $events          = [];
            $manifestationIds = [];

            while ($row = $stmt->fetch()) {
                $id = (int)$row['id_manifestation'];
                $manifestationIds[] = $id;

                $parts = explode(' - ', $row['ManifestationTypée'], 3);
                $type  = $parts[1] ?? '';
                $titre = $parts[2] ?? $row['ManifestationTypée'];

                // Calcul de la plage horaire
                $timeRange = '';
                if (!empty($row['Durée_créneau'])) {
                    $hm = explode('h', $row['Durée_créneau'], 2);
                    $h  = (int)($hm[0] ?? 0);
                    $m  = isset($hm[1]) && $hm[1] !== '' ? (int)$hm[1] : 0;
                    $ts = strtotime($row['Date']);
                    $timeRange = date('H\hi', $ts) . ' - ' . date('H\hi', strtotime("+{$h} hour +{$m} minute", $ts));
                }

                $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['Date']);

                $events[$id] = [
                    'id'                => $id,
                    'titre'             => $titre,
                    'type'              => $type,
                    'date_display'      => $dateObj ? EventNormalizer::formatDateDisplay($dateObj) : $row['Date'],
                    'time_range'        => $timeRange,
                    'lieu'              => $row['Lieu'],
                    'commentaire'       => $row['Commentaire'] ?? '',
                    'statut'            => $row['Statut'] ?? '',
                    'nb_present'        => 0,
                    'nb_disponible'     => 0,
                    'nb_indisponible'   => 0,
                    'nb_ne_sait_pas'    => 0,
                    'nb_pas_de_reponse' => 0,
                ];
            }

            if (empty($events)) {
                return [];
            }

            // Enrichissement avec stats de participation (batch, pas de N+1)
            $stats = ParticipationStatsService::getParticipationStatsBatch($manifestationIds);
            foreach ($events as &$event) {
                if (isset($stats[$event['id']])) {
                    $s = $stats[$event['id']];
                    $event['nb_present']      = $s['present']     ?? 0;
                    $event['nb_disponible']   = $s['available']   ?? 0;
                    $event['nb_indisponible'] = $s['unavailable'] ?? 0;
                    $event['nb_ne_sait_pas']  = $s['unknown']     ?? 0;
                }
            }
            unset($event);

            return array_values($events);
        } catch (\Throwable $e) {
            error_log('getUpcomingMatchesForTeam: Exception - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Prochains événements (matchs et entraînements) d'une équipe.
     */
    public static function getUpcomingEventsForTeam(
        array $team,
        int $limit = 8
    ): array {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                return [];
            }

            $teamCode = $team['slug_colonne'];
            $manifestationFilter = $team['manifestation_filter'] ?? '';
            $category = $team['categorie'] ?? '';
            $libelle = $team['libelle'] ?? '';

            // Filtrage match : par défaut 'Match ' + libellé
            $matchFilter = '%' . ($manifestationFilter ?: 'Match ' . $libelle) . '%';
            $teamCodeFilter = '%' . $teamCode . '%';

            // Conditions d'entraînements flexibles basées sur le type d'événement contenant 'Entr'
            // et optionnellement le code d'équipe ou des mots clés généraux si non typé par équipe
            $trainingExtraClauses = ["m.ManifestationTypée LIKE '%Entr. Compétition%'"];
            
            if (stripos($category, 'ufolep') !== false || stripos($libelle, 'ufolep') !== false) {
                $trainingExtraClauses[] = "m.ManifestationTypée LIKE '%UFOLEP%'";
            }
            if (stripos($category, 'jeunes') !== false || stripos($category, 'jeune') !== false || stripos($libelle, 'jeune') !== false) {
                $trainingExtraClauses[] = "m.ManifestationTypée LIKE '%jeunes%'";
            }
            if (stripos($category, 'loisir') !== false || stripos($libelle, 'loisir') !== false || str_starts_with($teamCode, 'L')) {
                $trainingExtraClauses[] = "m.ManifestationTypée LIKE '%CompetLib%'";
            }

            $trainingExtraSql = "";
            if (!empty($trainingExtraClauses)) {
                $trainingExtraSql = " OR " . implode(" OR ", $trainingExtraClauses);
            }

            $sql = "SELECT m.id_manifestation, m.ManifestationTypée, m.Date,
                            m.Durée_créneau, m.Nombre_terrain, m.Lieu, m.Commentaire, m.Statut
                     FROM Manifestation m
                     WHERE m.id_manifestation > 0
                       AND m.Date >= CURDATE()
                       AND (
                         (m.ManifestationTypée LIKE '% - Match - %' AND m.ManifestationTypée LIKE :match_filter)
                         OR
                         ((m.ManifestationTypée LIKE '%Entr%' OR m.ManifestationTypée LIKE '%Entrainement%') AND (m.ManifestationTypée LIKE :team_code_filter $trainingExtraSql))
                       )
                     ORDER BY m.Date ASC
                     LIMIT :limit";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':match_filter', $matchFilter, \PDO::PARAM_STR);
            $stmt->bindValue(':team_code_filter', $teamCodeFilter, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

            if (!$stmt->execute()) {
                return [];
            }

            $events = [];
            while ($row = $stmt->fetch()) {
                $id = (int)$row['id_manifestation'];
                
                // Normalisation via EventNormalizer pour obtenir un objet quasi complet et standardisé
                $event = EventNormalizer::buildBaseFields($row);
                
                $event['is_match'] = (stripos($row['ManifestationTypée'], 'match') !== false);
                $event['is_training'] = (stripos($row['ManifestationTypée'], 'entra') !== false || stripos($row['ManifestationTypée'], 'entr') !== false);
                
                // Harmonisation type entraînement
                if ($event['is_training']) {
                    $event['type'] = 'Entraînement';
                }

                $events[$id] = $event;
            }

            return array_values($events);
        } catch (\Throwable $e) {
            error_log('getUpcomingEventsForTeam: Exception - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Normalise une ligne brute de Manifestation en objet agenda canonique,
     * enrichi des données de participation via la BDD.
     *
     * Délègue le formatage à EventNormalizer et charge les participations en BDD.
     */
    public static function normalizeManifestation(array $row, int $totalJoueurs = 0): array
    {
        $id            = (int)($row['id_manifestation'] ?? 0);
        $manifestation = EventNormalizer::buildBaseFields($row, $totalJoueurs);

        // Enrichissement participation depuis la BDD externe
        try {
            $db = ExternalDatabase::get();
            if ($db && $id > 0) {
                $stmt = $db->prepare(
                    "SELECT p.Participation, j.id_joueur, j.Nom, j.Prénom
                     FROM Participation p
                     JOIN Joueurs j ON p.id_joueur = j.id_joueur
                     WHERE p.id_manifestation = ? AND j.id_joueur > 0"
                );
                $stmt->execute([$id]);
                $rows = $stmt->fetchAll();

                $respondedJoueurIds = [];
                foreach ($rows as $pRow) {
                    $rawStatus = trim((string)($pRow['Participation'] ?? ''));
                    if ($rawStatus === '') {
                        continue;
                    }
                    $status    = new ParticipationStatus($rawStatus);
                    $jid       = (int)$pRow['id_joueur'];
                    $nomJoueur = $pRow['Nom'] . ' ' . $pRow['Prénom'];

                    EventNormalizer::updateManifestationStats($manifestation, $status, $jid, $nomJoueur, $rawStatus);
                    $respondedJoueurIds[] = $jid;
                }

                // Compléter avec les joueurs sans réponse
                foreach (self::getAllPlayers() as $jid => $nom) {
                    if (!in_array($jid, $respondedJoueurIds, true)) {
                        $manifestation['pas_de_reponse'][] = ['id' => $jid, 'nom' => $nom];
                    }
                }
                $manifestation['nb_pas_de_reponse'] = count($manifestation['pas_de_reponse']);
            }
        } catch (\Throwable $e) {
            error_log('normalizeManifestation participation loading failed: ' . $e->getMessage());
        }

        return $manifestation;
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * Cache en mémoire de tous les joueurs (id_joueur → "Nom Prénom").
     * Évite les requêtes répétées lors du calcul des "pas_de_reponse".
     */
    private static function getAllPlayers(): array
    {
        if (self::$allPlayersCache === null) {
            self::$allPlayersCache = [];
            try {
                $db = ExternalDatabase::get();
                if ($db) {
                    $stmt = $db->query(
                        "SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 ORDER BY Nom"
                    );
                    if ($stmt) {
                        while ($row = $stmt->fetch()) {
                            self::$allPlayersCache[(int)$row['id_joueur']] = $row['Nom'] . ' ' . $row['Prénom'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('getAllPlayers failed: ' . $e->getMessage());
            }
        }
        return self::$allPlayersCache;
    }

    /**
     * Requête par pattern LIKE sur ManifestationTypée (helper interne).
     */
    private static function queryByPattern(string $pattern, int $limit): array
    {
        try {
            $stmt = ExternalDatabase::get()->prepare(
                "SELECT * FROM Manifestation
                 WHERE `ManifestationTypée` LIKE :pat
                   AND `Date` >= CURDATE()
                 ORDER BY `Date` ASC
                 LIMIT :limit"
            );
            $stmt->bindValue(':pat',   $pattern);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        return array_map([EventNormalizer::class, 'buildEvent'], $rows);
    }

    /**
     * Vérifie que le code équipe fait partie de la whitelist des colonnes autorisées.
     * Prévient toute injection SQL via le paramètre team.
     */
    private static function teamColumn(string $team): ?string
    {
        $categories = EquipeConfig::getEquipesSlug();
        return in_array($team, $categories, true) ? $team : null;
    }



    /**
     * Crée un nouveau match dans la base externe.
     */
    public static function createMatch(array $data): int
    {
        $db = ExternalDatabase::get();

        // Récupérer le prochain ID (id_manifestation) de façon sûre (pas d'auto-incrément dans le schéma externe)
        $stmt = $db->query("SELECT COALESCE(MAX(id_manifestation), 0) + 1 FROM Manifestation");
        $nextId = (int)$stmt->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO Manifestation (id_manifestation, `ManifestationTypée`, Manifestation, `Date`, `Durée_créneau`, Lieu, Nombre_terrain, Creneau, Commentaire, Statut)
             VALUES (?, ?, '', ?, ?, ?, 1, '', ?, ?)"
        );

        $stmt->execute([
            $nextId,
            $data['manifestation_type'],
            $data['date'],
            $data['duration'] ?? '2h',
            $data['location'],
            $data['commentaire'],
            $data['statut'] ?? 'Confirmé'
        ]);

        return $nextId;
    }

    /**
     * Met à jour un match existant dans la base externe.
     */
    public static function updateMatch(int $id, array $data): void
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare(
            "UPDATE Manifestation 
             SET `Date` = ?, `Durée_créneau` = ?, Lieu = ?, Commentaire = ?, Statut = ?
             WHERE id_manifestation = ?"
        );
        $stmt->execute([
            $data['date'],
            $data['duration'],
            $data['location'],
            $data['commentaire'],
            $data['statut'],
            $id
        ]);
    }
}
