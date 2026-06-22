<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ExternalDatabase;
use App\Helpers\ParticipationStatus;

class AgendaService
{
    private static array $MONTHS_FR = [
        'Jan' => 'Jan', 'Feb' => 'Fév', 'Mar' => 'Mar', 'Apr' => 'Avr',
        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Aoû',
        'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Déc',
    ];

    private static array $DAYS_FR = [
        'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Jeu',
        'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim',
    ];

    /**
     * Get upcoming matches (events with "Match" type).
     *
     * Queries the external database for future matches, ordered by date ascending.
     * Returns normalized event data: title, date_display, time_display, location, etc.
     *
     * @param int $limit Maximum number of matches to return (default 5)
     * @return array List of match events
     */
    public static function getUpcomingMatches(int $limit = 5): array
    {
        return self::queryByPattern('% - Match - %', $limit);
    }

    /**
     * Get upcoming trainings (events with "Entraînement" type).
     *
     * @param int $limit Maximum number of trainings to return (default 5)
     * @return array List of training events
     */
    public static function getUpcomingTrainings(int $limit = 5): array
    {
        return self::queryByPattern('% - Entra%', $limit);
    }

    /**
     * Get upcoming events filtered by a type fragment found anywhere in
     * ManifestationTypée or Lieu (e.g. 'Beach', 'Tournoi').
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
        return array_map([self::class, 'buildEvent'], $rows);
    }

    /**
     * Get upcoming events filtered by several type fragments.
     *
     * Useful for grouping summer event categories like Beach, Club and Tournoi.
     */
    public static function getUpcomingByTypes(array $needles, int $limit = 5): array
    {
        if (empty($needles)) {
            return [];
        }

        $conditions = [];
        $bindings = [];
        foreach ($needles as $needle) {
            $conditions[] = "(`ManifestationTypée` LIKE ? OR `Lieu` LIKE ?)";
            $bindings[] = '%' . $needle . '%';
            $bindings[] = '%' . $needle . '%';
        }

        $sql = "SELECT * FROM Manifestation
                 WHERE (" . implode(' OR ', $conditions) . ")
                   AND `Date` >= CURDATE()
                 ORDER BY `Date` ASC
                 LIMIT " . (int) $limit;

        try {
            $stmt = ExternalDatabase::get()->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        return array_map([self::class, 'buildEvent'], $rows);
    }

    /**
     * Build a cross-table of players × events with participation data.
     *
     * Fetches all players, all upcoming manifestations, and their participation statuses,
     * then builds a crosstab structure for display in agenda grids/tables.
     *
     * Supported filters:
     * - team: Filter players by team (boolean column in Joueurs)
     * - location: Filter events by Lieu
     * - type: Filter events by ManifestationTypée (fuzzy match on segment 2)
     * - manifestation: Filter events by name (fuzzy match on segment 3)
     * - this_week: Boolean; if true, only show events within current week (Mon-Sun)
     * - hide_empty_players: Boolean; filter out players with no participation responses
     *
     * Returns structure:
     * [
     *   'joueurs' => { id_joueur => 'Nom Prénom', ... },
     *   'manifestations' => { id_manifestation => { id, type, titre, date_display, nb_present, ... }, ... },
     *   'cross' => { id_joueur => { id_manifestation => 'status_string', nb_participation, ... }, ... }
     * ]
     *
     * @param array $filters Optional filters (see above)
     * @return array Cross-table structure with joueurs, manifestations, and participation data
     */
    public static function getCrossTable(array $filters = []): array
    {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                error_log('getCrossTable: ExternalDatabase::get() returned null');
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }

            // 1. Joueurs triés par nom, filtrés par équipe si demandé
            $joueurs = [];
            if (!empty($filters['team'])) {
                $teamCol = self::teamColumn($filters['team']);
                $joueurStmt = $teamCol
                    ? $db->prepare("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 AND `$teamCol` = 1 ORDER BY Nom")
                    : $db->prepare("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 AND Equipe = ? ORDER BY Nom");
                if (!$teamCol) {
                    $joueurStmt->execute([$filters['team']]);
                } else {
                    $joueurStmt->execute();
                }
                $stmt = $joueurStmt;
            } else {
                $stmt = $db->query("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 ORDER BY Nom");
            }
            if (!$stmt) {
                error_log('getCrossTable: Failed to query Joueurs - ' . json_encode($db->errorInfo()));
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }
            while ($row = $stmt->fetch()) {
                $id = (int) $row['id_joueur'];
                $joueurs[$id] = $row['Nom'] . ' ' . $row['Prénom'];
            }

            // 2. Manifestations futures uniquement — avec filtres
            $manifestations = [];
            $sql = "SELECT id_manifestation, `ManifestationTypée`, `Date`,
                        DATE_FORMAT(`Date`, '%W %d %M') AS date_fr,
                        `Durée_créneau`, Nombre_terrain, Lieu, Commentaire, Statut
                 FROM Manifestation
                 WHERE id_manifestation > 0 AND `Date` >= CURDATE()";

            $bindings = [];

            // Appliquer les filtres
            if (!empty($filters['location'])) {
                $sql .= " AND Lieu = ?";
                $bindings[] = $filters['location'];
            }
            if (!empty($filters['type'])) {
                $sql .= " AND ManifestationTypée LIKE ?";
                $bindings[] = '%' . $filters['type'] . '%';
            }
            if (!empty($filters['manifestation'])) {
                $sql .= " AND ManifestationTypée LIKE ?";
                $bindings[] = '% - ' . $filters['manifestation'];
            }
            if (!empty($filters['this_week'])) {
                $weekStart = date('Y-m-d', strtotime('Monday this week'));
                $weekEnd = date('Y-m-d', strtotime('Sunday this week'));
                $sql .= " AND Date BETWEEN ? AND ?";
                $bindings[] = $weekStart;
                $bindings[] = $weekEnd . ' 23:59:59';
            }

            $sql .= " ORDER BY `Date` ASC";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                error_log('getCrossTable: Failed to prepare Manifestation query - ' . json_encode($db->errorInfo()));
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }
            if (!$stmt->execute($bindings)) {
                error_log('getCrossTable: Failed to execute Manifestation query - ' . json_encode($db->errorInfo()));
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }

            $manifestationCount = 0;
            while ($row = $stmt->fetch()) {
                $manifestationCount++;
                $id  = (int) $row['id_manifestation'];
                $manifestations[$id] = self::normalizeManifestation($row, count($joueurs));
            }
            error_log("getCrossTable: Fetched $manifestationCount Manifestation rows, stored " . count($manifestations) . " in array");

            if (empty($manifestations)) {
                error_log('getCrossTable: No manifestations found!');
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }

            // 3. Table croisée via LEFT JOIN
            $ids = implode(',', array_keys($manifestations));
            $cross = [];
            foreach ($joueurs as $jid => $nom) {
                $cross[$jid] = ['nb_participation' => 0, 'nb_non_absence' => 0, 'nb_ne_sait_pas' => 0, 'nb_ne_sait_pas_proche' => 0];
                foreach (array_keys($manifestations) as $mid) {
                    $cross[$jid][$mid] = '';
                }
            }

            $dateTropProche = time() + 3 * 24 * 3600;
            $stmt = $db->query(
                "SELECT j.id_joueur, m.id_manifestation,
                        COALESCE(p.Participation, '') AS participation,
                        DATE_FORMAT(m.`Date`, '%Y-%m-%d %H:%i') AS date2
                 FROM Joueurs j
                 CROSS JOIN Manifestation m
                 LEFT JOIN Participation p ON j.id_joueur = p.id_joueur AND m.id_manifestation = p.id_manifestation
                 WHERE m.id_manifestation IN ($ids)
                   AND j.id_joueur > 0
                 ORDER BY j.Nom, m.`Date`"
            );

            while ($row = $stmt->fetch()) {
                $jid  = (int) $row['id_joueur'];
                $mid  = (int) $row['id_manifestation'];
                $part = trim((string) ($row['participation'] ?? ''));
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
            return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
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
     * Fetch a single event by ID.
     *
     * @param int $id Event ID (id_manifestation)
     * @return array|null Normalized event object, or null if not found
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

        return $row ? self::buildEventWithId($row) : null;
    }

    /**
     * Get available filter options for agenda views.
     *
     * Fetches distinct values from Manifestation and Mots_clef tables.
     * Used by filter dropdowns.
     *
     * @return array Keys: types (array), locations (array)
     */
    public static function getAvailableFilters(): array
    {
        try {
            $db = ExternalDatabase::get();

            $types = [];
            $stmt = $db->query(
                "SELECT DISTINCT ManifestationTypée FROM Manifestation WHERE ManifestationTypée LIKE '% - %' ORDER BY ManifestationTypée"
            );
            while ($row = $stmt->fetch()) {
                $parts = explode(' - ', $row['ManifestationTypée'], 2);
                if (count($parts) >= 2 && !in_array($parts[1], $types)) {
                    $types[] = $parts[1];
                }
            }

            $locations = [];
            $stmt = $db->query(
                "SELECT DISTINCT Lieu FROM Manifestation WHERE Lieu != '' ORDER BY Lieu"
            );
            while ($row = $stmt->fetch()) {
                $locations[] = $row['Lieu'];
            }

            return [
                'types' => $types,
                'locations' => $locations,
            ];
        } catch (\Throwable) {
            return ['types' => [], 'locations' => []];
        }
    }

    /**
     * Count manifestations matching the given filters.
     *
     * Used for pagination calculations.
     *
     * @param array $filters Optional filters: type, location, date_from, date_to
     * @return int Total count
     */
    public static function countManifestations(array $filters = []): int
    {
        try {
            $type = $filters['type'] ?? null;
            $location = $filters['location'] ?? null;
            $dateFrom = $filters['date_from'] ?? null;
            $dateTo   = $filters['date_to'] ?? null;

            $sql = "SELECT COUNT(*) as cnt FROM Manifestation WHERE 1=1";

            if ($type) {
                $sql .= " AND ManifestationTypée LIKE ?";
            }
            if ($location) {
                $sql .= " AND Lieu = ?";
            }
            if ($dateFrom) {
                $sql .= " AND Date >= ?";
            }
            if ($dateTo) {
                $sql .= " AND Date <= ?";
            }

            $stmt = ExternalDatabase::get()->prepare($sql);
            $bindings = [];

            if ($type) {
                $bindings[] = "% - $type - %";
            }
            if ($location) {
                $bindings[] = $location;
            }
            if ($dateFrom) {
                $bindings[] = $dateFrom;
            }
            if ($dateTo) {
                $bindings[] = $dateTo;
            }

            $stmt->execute($bindings);
            $result = $stmt->fetch();
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get participation statistics for a single event.
     *
     * Counts and categorizes all participation responses for the given manifestation.
     * Categories: present, available, unavailable, selected, absent, unknown, no_response.
     *
     * @param int $manifestationId Event ID
     * @return array Stats with counts per category and 'enough_players' flag (true if >= 6 available)
     */
    public static function getParticipationStats(int $manifestationId): array
    {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                return self::defaultStats();
            }
            $stmt = $db->prepare(
                "SELECT Participation FROM Participation WHERE id_manifestation = ?"
            );
            $stmt->execute([$manifestationId]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return self::defaultStats();
        }

        $stats = self::emptyStats();
        $stats['available_if_needed'] = 0;

        foreach ($rows as $row) {
            $statusStr = trim((string) ($row['Participation'] ?? ''));
            if ($statusStr === '') {
                continue;
            }
            $status = new ParticipationStatus($statusStr);
            $category = $status->getCategory();

            if (strpos($statusStr, 'Disponible si n') !== false) {
                $stats['available_if_needed']++;
            } else {
                if (isset($stats[$category])) {
                    $stats[$category]++;
                    if ($category === 'present') {
                        $stats[$category] += $status->getCompanionCount();
                    }
                }
            }
        }

        $total_responses = array_sum([
            $stats['present'], $stats['available'], $stats['unavailable'],
            $stats['selected'], $stats['absent'], $stats['unknown'], $stats['available_if_needed']
        ]);
        $stats['total_responses'] = $total_responses;
        $stats['enough_players'] = ($stats['present'] + $stats['available'] + $stats['selected'] + $stats['available_if_needed']) >= 6;

        return $stats;
    }

    /**
     * Batch-fetch participation stats for multiple events (avoids N+1 queries).
     *
     * @param array $manifestationIds List of event IDs to fetch stats for
     * @return array Associative array: manifestationId => stats_array
     */
    public static function getParticipationStatsBatch(array $manifestationIds): array
    {
        if (empty($manifestationIds)) {
            return [];
        }

        try {
            $db = ExternalDatabase::get();
            $placeholders = implode(',', array_fill(0, count($manifestationIds), '?'));
            $stmt = $db->prepare(
                "SELECT id_manifestation, Participation, COUNT(*) as cnt
                 FROM Participation
                 WHERE id_manifestation IN ($placeholders)
                 GROUP BY id_manifestation, Participation"
            );
            $stmt->execute($manifestationIds);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return array_fill_keys($manifestationIds, self::defaultStats());
        }

        $result = array_fill_keys($manifestationIds, self::emptyStats());

        foreach ($rows as $row) {
            $mid = (int) ($row['id_manifestation'] ?? 0);
            if (!isset($result[$mid])) {
                $result[$mid] = self::emptyStats();
            }

            $statusStr = $row['Participation'] ?? '';
            $count = (int) ($row['cnt'] ?? 0);
            $status = new ParticipationStatus($statusStr);
            $category = $status->getCategory();

            if (isset($result[$mid][$category])) {
                $result[$mid][$category] += $count;
            }
        }

        foreach ($result as &$stats) {
            $stats['total_responses'] = array_sum([
                $stats['present'], $stats['available'], $stats['unavailable'],
                $stats['selected'], $stats['absent'], $stats['unknown']
            ]);
            $stats['enough_players'] = ($stats['present'] + $stats['available'] + $stats['selected']) >= 6;
        }

        return $result;
    }

    /**
     * Query manifestations by ManifestationTypée pattern (SQL LIKE).
     *
     * Internal helper for getUpcomingMatches() and getUpcomingTrainings().
     *
     * @param string $pattern SQL LIKE pattern (e.g., '% - Match - %')
     * @param int $limit Max results
     * @return array List of normalized events
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

        return array_map([self::class, 'buildEvent'], $rows);
    }

    /**
     * Build a normalized event object from raw database row.
     *
     * Normalizes dates, extracts title and type, and computes derived fields (is_soon).
     *
     * @param array $row Raw database row from Manifestation
     * @return array Normalized event structure
     */
    private static function buildEvent(array $row): array
    {
        $today   = new \DateTimeImmutable('today');
        $dateStr = $row['Date'] ?? null;
        $date    = $dateStr
            ? (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr)
               ?: \DateTimeImmutable::createFromFormat('Y-m-d', substr($dateStr, 0, 10)))
            : null;
        $isSoon = $date && $date->diff($today)->days <= 3 && $date >= $today;

        // Heure extraite du datetime (Creneau est vide dans la vraie base)
        $timeDisplay = ($date && $date->format('H:i') !== '00:00') ? $date->format('H:i') : '';

        return [
            'title'        => self::extractTitle($row['ManifestationTypée'] ?? ''),
            'date_display' => $date ? self::formatDateDisplay($date) : ($dateStr ?? ''),
            'time_display' => $timeDisplay,
            'location'     => $row['Lieu'] ?? null,
            'comment'      => !empty($row['Commentaire']) ? $row['Commentaire'] : null,
            'status'       => $row['Statut'] ?? null,
            'is_soon'      => $isSoon,
        ];
    }

    /**
     * Build a normalized event object with ID and type (extends buildEvent).
     *
     * Used by getEventById().
     *
     * @param array $row Raw database row from Manifestation
     * @return array Normalized event structure including id, type, duration, nb_courts
     */
    private static function buildEventWithId(array $row): array
    {
        $event = self::buildEvent($row);
        $event['id'] = (int) ($row['id_manifestation'] ?? 0);
        $event['type'] = self::extractType($row['ManifestationTypée'] ?? '');
        $event['duration'] = $row['Durée_créneau'] ?? null;
        $event['nb_courts'] = (int) ($row['Nombre_terrain'] ?? 0);
        return $event;
    }

    /**
     * Extract the event title from ManifestationTypée field.
     *
     * Format: "Disponibilités - Type - Title"
     * Example: "Disponibilités - Match - Match L2" → "Match L2"
     *
     * @param string $type ManifestationTypée from database
     * @return string The title (segment 3), or entire string if format doesn't match
     */
    private static function extractTitle(string $type): string
    {
        $parts = explode(' - ', $type, 3);
        return count($parts) === 3 ? trim($parts[2]) : trim($type);
    }

    /**
     * Extract the event type from ManifestationTypée field.
     *
     * Format: "Disponibilités - Type - Title"
     * Example: "Disponibilités - Match - Match L2" → "Match"
     *
     * @param string $typeStr ManifestationTypée from database
     * @return string The type (segment 2), or empty string if format doesn't match
     */
    private static function extractType(string $typeStr): string
    {
        $parts = explode(' - ', $typeStr, 2);
        return count($parts) >= 2 ? trim($parts[1]) : '';
    }

    /**
     * Format a date for display in French.
     *
     * Converts English day/month abbreviations to French.
     * Example: "Mon 24 Jan" → "Lun 24 Jan"
     *
     * @param \DateTimeImmutable $date
     * @return string Formatted date (e.g., "Lun 24 Jan")
     */
    private static function formatDateDisplay(\DateTimeImmutable $date): string
    {
        $dayEn   = $date->format('D');
        $dayFr   = self::$DAYS_FR[$dayEn]  ?? $dayEn;
        $monthEn = $date->format('M');
        $monthFr = self::$MONTHS_FR[$monthEn] ?? $monthEn;
        return $dayFr . ' ' . $date->format('j') . ' ' . $monthFr;
    }

    /**
     * Map team name to the corresponding boolean column in Joueurs table.
     *
     * Uses a whitelist to prevent SQL injection via team filters.
     * Valid values come from Mots_clef where Catégorie='EquipeParEquipe'.
     *
     * @param string $team Team name/column name
     * @return string|null Sanitized column name, or null if not in whitelist
     */
    private static function teamColumn(string $team): ?string
    {
        $allowed = [
            'L1', 'L2', 'L3', 'L4', 'Open',
            'CoupeLoisir', 'Heitz', 'Aico',
            'UFOLEP_1', 'UFOLEP_2', 'UFOLEP_3',
            'DEP', 'Adulte', 'Jeune', 'M18F', 'M13F', 'M15F', 'M15F6', 'R2F',
            'Compétition', 'Loisir', 'Débutant',
        ];
        return in_array($team, $allowed, true) ? $team : null;
    }

    /**
     * Return an empty participation stats array with all categories set to 0.
     *
     * @return array Empty stats structure
     */
    private static function emptyStats(): array
    {
        return [
            'present' => 0,
            'available' => 0,
            'unavailable' => 0,
            'selected' => 0,
            'absent' => 0,
            'unknown' => 0,
        ];
    }

    /**
     * Return default stats for error cases.
     *
     * @return array Default empty stats with computed fields
     */
    private static function defaultStats(): array
    {
        $stats = self::emptyStats();
        $stats['total_responses'] = 0;
        $stats['no_response'] = 0;
        $stats['enough_players'] = false;
        return $stats;
    }

    /**
     * Get normalized participation counts for an event, mapped to JS status names.
     *
     * @param int $manifestationId Event ID
     * @return array Key-value counts matching JS expected statuses
     */
    public static function getNormalizedCounts(int $manifestationId): array
    {
        $stats = self::getParticipationStats($manifestationId);

        return [
            'Disponible'               => $stats['available'] ?? 0,
            'Disponible si nécessaire' => $stats['available_if_needed'] ?? 0,
            'Indisponible'             => $stats['unavailable'] ?? 0,
            'Présent'                  => $stats['present'] ?? 0,
            'Absent'                   => $stats['absent'] ?? 0,
            'Ne sait pas encore'       => $stats['unknown'] ?? 0,
        ];
    }

    /**
     * Normalise une ligne de Manifestation en un objet de manifestation agenda canonique.
     */
    public static function normalizeManifestation(array $row, int $totalJoueurs = 0): array
    {
        $id = (int) ($row['id_manifestation'] ?? 0);
        $parts = explode(' - ', $row['ManifestationTypée'] ?? '', 3);
        $type = $parts[1] ?? '';
        $titre = $parts[2] ?? ($row['ManifestationTypée'] ?? '');

        // Calcul de la plage horaire
        $timeRange = '';
        if (!empty($row['Durée_créneau'])) {
            $hm = explode('h', $row['Durée_créneau'], 2);
            $h  = (int) ($hm[0] ?? 0);
            $m  = isset($hm[1]) && $hm[1] !== '' ? (int) $hm[1] : 0;
            $ts = strtotime($row['Date']);
            $timeRange = date('H\hi', $ts) . ' - ' . date('H\hi', strtotime("+{$h} hour +{$m} minute", $ts));
        }

        $dateStr = $row['Date'] ?? null;
        $date = $dateStr
            ? (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr)
               ?: \DateTimeImmutable::createFromFormat('Y-m-d', substr($dateStr, 0, 10)))
            : null;

        // Heure extraite du datetime (Creneau est vide dans la vraie base)
        $timeDisplay = ($date && $date->format('H:i') !== '00:00') ? $date->format('H:i') : '';

        $statut = $row['Statut'] ?? '';

        $manifestation = [
            'id_manifestation'            => $id,
            'id'                          => $id,
            'type'                        => $type,
            'titre'                       => $titre,
            'date_display'                => $date ? self::formatDateDisplay($date) : ($dateStr ?? ''),
            'time_range'                  => $timeRange,
            'time_display'                => $timeDisplay,
            'location'                    => $row['Lieu'] ?? null,
            'lieu'                        => $row['Lieu'] ?? null,
            'comment'                     => !empty($row['Commentaire']) ? $row['Commentaire'] : null,
            'commentaire'                 => !empty($row['Commentaire']) ? $row['Commentaire'] : null,
            'status'                      => $statut,
            'statut'                      => $statut,
            'annule'                      => str_contains($statut, 'Annulé'),
            'provisoire'                  => str_contains($statut, 'Provisoire'),
            'nb_terrains'                 => (int) ($row['Nombre_terrain'] ?? 0),
            'nb_present'                  => 0,
            'nb_absent'                   => 0,
            'nb_disponible'               => 0,
            'nb_disponible_si_necessaire' => 0,
            'nb_indisponible'             => 0,
            'nb_selection'                => 0,
            'nb_ne_sait_pas'              => 0,
            'nb_pas_de_reponse'           => $totalJoueurs,
            'presents'                    => [],
            'absents'                     => [],
            'disponibles'                 => [],
            'disponibles_si_necessaire'   => [],
            'indisponibles'               => [],
            'selectionnes'                => [],
            'ne_sait_pas'                 => [],
            'pas_de_reponse'              => [],
            'is_match'                    => (bool) (strpos($type, 'Match') !== false),
            'is_training'                 => (bool) (strpos($type, 'Entra') !== false),
            'type_simple'                 => $type,
            'type_libelle'                => $type,
        ];

        // Charger dynamiquement les listes de participations des joueurs pour cette manifestation
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

                foreach ($rows as $pRow) {
                    $rawStatus = trim((string)($pRow['Participation'] ?? ''));
                    if ($rawStatus === '') {
                        continue;
                    }

                    $status = new ParticipationStatus($rawStatus);
                    $jid = (int) $pRow['id_joueur'];
                    $nomJoueur = $pRow['Nom'] . ' ' . $pRow['Prénom'];

                    self::updateManifestationStats($manifestation, $status, $jid, $nomJoueur, $rawStatus);
                }
            }
        } catch (\Throwable $e) {
            error_log('normalizeManifestation participation loading failed: ' . $e->getMessage());
        }

        return $manifestation;
    }

    /**
     * Update manifestation stats based on a single player's participation status.
     *
     * Increments the appropriate counter (present, available, etc.) and decrements no_response.
     *
     * @param array $manifestationStats Reference to the manifestation's stats array
     * @param ParticipationStatus $status Parsed participation status
     */
    private static function updateManifestationStats(array &$manifestationStats, ParticipationStatus $status, int $jid, string $nomJoueur, string $rawStatus): void
    {
        $category = $status->getCategory();
        $playerInfo = ['id' => $jid, 'nom' => $nomJoueur];

        // S'assurer que les tableaux de joueurs existent
        if (!isset($manifestationStats['presents'])) {
            $manifestationStats['presents'] = [];
            $manifestationStats['absents'] = [];
            $manifestationStats['disponibles'] = [];
            $manifestationStats['disponibles_si_necessaire'] = [];
            $manifestationStats['indisponibles'] = [];
            $manifestationStats['selectionnes'] = [];
            $manifestationStats['ne_sait_pas'] = [];
            $manifestationStats['pas_de_reponse'] = [];
        }

        // ma participation
        $manifestationStats['ma_participation'] = 'pas_de_reponse';
        if(isset($_SESSION['LogInId']) && $_SESSION['LogInId'] === $jid) {
            $manifestationStats['ma_participation'] = $category;
        }

        // "Disponible si nécessaire"
        $isDisponibleSiNecessaire = (strpos($rawStatus, 'Disponible si n') !== false);

        if ($isDisponibleSiNecessaire) {
            $manifestationStats['disponibles_si_necessaire'][] = $playerInfo;
            $manifestationStats['nb_disponible_si_necessaire']++;
        } else {
            match ($category) {
                'selected' => (function () use ($playerInfo, &$manifestationStats) {
                    $manifestationStats['selectionnes'][] = $playerInfo;
                    $manifestationStats['nb_selection']++;
                })(),
                'available' => (function () use ($playerInfo, &$manifestationStats) {
                    $manifestationStats['disponibles'][] = $playerInfo;
                    $manifestationStats['nb_disponible']++;
                })(),
                'unavailable' => (function () use ($playerInfo, &$manifestationStats) {
                    $manifestationStats['indisponibles'][] = $playerInfo;
                    $manifestationStats['nb_indisponible']++;
                })(),
                'absent' => (function () use ($playerInfo, &$manifestationStats) {
                    $manifestationStats['absents'][] = $playerInfo;
                    $manifestationStats['nb_absent']++;
                })(),
                'present' => (function () use ($status, $playerInfo, &$manifestationStats) {
                    $manifestationStats['presents'][] = $playerInfo;
                    $manifestationStats['nb_present']++;
                    // Compagnons
                    $manifestationStats['nb_present'] += $status->getCompanionCount();
                })(),
                'unknown' => (function () use ($playerInfo, &$manifestationStats) {
                    $manifestationStats['ne_sait_pas'][] = $playerInfo;
                    $manifestationStats['nb_ne_sait_pas']++;
                })(),
                default => null,
            };
        }

        if ($category !== 'no_response') {
            $manifestationStats['nb_pas_de_reponse']--;
        }
    }

    /**
     * Get all filter options for the agenda filter form.
     *
     * Fetches distinct values for all four filter dropdowns:
     * - types: From ManifestationTypée (segment 2 after first " - ")
     * - locations: From Lieu
     * - manifestationNames: From ManifestationTypée (segment 3, last part)
     * - teams: From Mots_clef where Catégorie='EquipeParEquipe'
     *
     * Only returns events from current date onwards.
     *
     * @return array Keys: types, locations, manifestationNames, teams (each an array of strings)
     */
    public static function getFilterOptions(): array
    {
        try {
            $db = ExternalDatabase::get();

            // Get unique event types
            $types = [];
            $stmt = $db->query(
                "SELECT DISTINCT SUBSTRING_INDEX(Mot, ' - ', 2) AS type_part
                 FROM Mots_clef
                 WHERE Catégorie = 'ManifestationTypée'
                 ORDER BY type_part"
            );
            while ($row = $stmt->fetch()) {
                $type = trim(str_replace('Disponibilités - ', '', $row['type_part'] ?? ''));
                if (!empty($type)) {
                    $types[] = $type;
                }
            }

            // Get unique locations
            $locations = [];
            $stmt = $db->query(
                "SELECT DISTINCT Lieu FROM Manifestation
                 WHERE id_manifestation > 0 AND Date >= CURDATE() AND Lieu IS NOT NULL
                 ORDER BY Lieu"
            );
            while ($row = $stmt->fetch()) {
                if (!empty($row['Lieu'])) {
                    $locations[] = $row['Lieu'];
                }
            }

            // Get unique manifestation names (3rd segment after 2nd " - ")
            $manifestationNames = [];
            $stmt = $db->query(
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

            // Get teams from Mots_clef (values are the boolean column names in Joueurs)
            $teams = [];
            $stmt = $db->query(
                "SELECT Mot FROM Mots_clef WHERE `Catégorie` = 'EquipeParEquipe' ORDER BY Mot"
            );
            while ($row = $stmt->fetch()) {
                if (!empty($row['Mot'])) {
                    $teams[] = $row['Mot'];
                }
            }

            return [
                'types'              => $types,
                'locations'          => $locations,
                'manifestationNames' => $manifestationNames,
                'teams'              => $teams,
            ];
        } catch (\Throwable) {
            return ['types' => [], 'locations' => []];
        }
    }

    /**
     * Get upcoming matches for a specific team with participation stats.
     *
     * Fetches upcoming matches filtered by team's slug_colonne.
     * Optionally filters by manifestation name (e.g., "Match L2").
     * Returns matches with participation stats (may be zero if no records exist yet).
     *
     * @param string $teamCode Team identifier (e.g., 'L1')
     * @param int $limit Maximum number of matches to return
     * @param string|null $manifestationFilter Optional filter on ManifestationTypée (e.g., "Match L2")
     * @return array List of match events with participation stats
     */
    public static function getUpcomingMatchesForTeam(string $teamCode, int $limit = 5, ?string $manifestationFilter = null): array
    {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                error_log('getUpcomingMatchesForTeam: ExternalDatabase::get() returned null');
                return [];
            }

            // Validate team code
            if (!self::teamColumn($teamCode)) {
                error_log("getUpcomingMatchesForTeam: Invalid team code '$teamCode'");
                return [];
            }

            // Build manifestation filter clause
            $manifestationClause = "m.ManifestationTypée LIKE '% - Match - %'";
            $bindings = [];

            if (!empty($manifestationFilter)) {
                $manifestationClause .= " AND m.ManifestationTypée LIKE ?";
                $bindings[] = '%' . $manifestationFilter;
            }

            // Get ALL upcoming matches that match filters (no participation requirement)
            $stmt = $db->prepare(
                "SELECT m.id_manifestation, m.ManifestationTypée, m.Date,
                        m.Durée_créneau, m.Nombre_terrain, m.Lieu, m.Commentaire, m.Statut
                 FROM Manifestation m
                 WHERE m.id_manifestation > 0 AND $manifestationClause
                   AND m.Date >= CURDATE()
                 ORDER BY m.Date ASC
                 LIMIT ?"
            );
            $bindings[] = $limit;

            error_log("getUpcomingMatchesForTeam: Query for team '$teamCode' with manifestation filter: " . (!empty($manifestationFilter) ? $manifestationFilter : 'none'));

            if (!$stmt->execute($bindings)) {
                error_log('getUpcomingMatchesForTeam: Failed to query matches - ' . json_encode($db->errorInfo()));
                return [];
            }

            $events = [];
            $manifestationIds = [];
            $rowCount = 0;
            while ($row = $stmt->fetch()) {
                $rowCount++;
                $id = (int) $row['id_manifestation'];
                $manifestationIds[] = $id;

                $parts = explode(' - ', $row['ManifestationTypée'], 3);
                $type = $parts[1] ?? '';
                $titre = $parts[2] ?? $row['ManifestationTypée'];

                // Calculate time range
                $timeRange = '';
                if (!empty($row['Durée_créneau'])) {
                    $hm = explode('h', $row['Durée_créneau'], 2);
                    $h = (int) ($hm[0] ?? 0);
                    $m = isset($hm[1]) && $hm[1] !== '' ? (int) $hm[1] : 0;
                    $ts = strtotime($row['Date']);
                    $timeRange = date('H\hi', $ts) . ' - ' . date('H\hi', strtotime("+{$h} hour +{$m} minute", $ts));
                }

                $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['Date']);
                $events[$id] = [
                    'id'           => $id,
                    'titre'        => $titre,
                    'type'         => $type,
                    'date_display' => $dateObj ? self::formatDateDisplay($dateObj) : $row['Date'],
                    'time_range'   => $timeRange,
                    'lieu'         => $row['Lieu'],
                    'commentaire'  => $row['Commentaire'] ?? '',
                    'statut'       => $row['Statut'] ?? '',
                    'nb_present'   => 0,
                    'nb_disponible' => 0,
                    'nb_indisponible' => 0,
                    'nb_ne_sait_pas' => 0,
                    'nb_pas_de_reponse' => 0,
                ];
            }

            error_log("getUpcomingMatchesForTeam: Found $rowCount matching events for team '$teamCode'");

            if (empty($events)) {
                error_log("getUpcomingMatchesForTeam: No events found matching filters");
                return [];
            }

            // Fetch participation stats for these events (may be empty if no participation records)
            $stats = self::getParticipationStatsBatch($manifestationIds);
            foreach ($events as &$event) {
                if (isset($stats[$event['id']])) {
                    $eventStats = $stats[$event['id']];
                    $event['nb_present'] = $eventStats['present'] ?? 0;
                    $event['nb_disponible'] = $eventStats['available'] ?? 0;
                    $event['nb_indisponible'] = $eventStats['unavailable'] ?? 0;
                    $event['nb_ne_sait_pas'] = $eventStats['unknown'] ?? 0;
                }
            }

            return array_values($events);
        } catch (\Throwable $e) {
            error_log('getUpcomingMatchesForTeam: Exception - ' . $e->getMessage());
            return [];
        }
    }
}
