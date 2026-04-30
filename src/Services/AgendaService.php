<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ExternalDatabase;

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

    public static function getUpcomingMatches(int $limit = 5): array
    {
        // "Disponibilités - Match - Match L2" → filtre sur le segment de type
        return self::queryByPattern('% - Match - %', $limit);
    }

    public static function getUpcomingTrainings(int $limit = 5): array
    {
        // "Disponibilités - Entraînement - …" → préfixe sans accent pour tolérance
        return self::queryByPattern('% - Entra%', $limit);
    }

    public static function getCrossTable(array $filters = []): array
    {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                error_log('getCrossTable: ExternalDatabase::get() returned null');
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }

            // 1. Joueurs triés par nom
            $joueurs = [];
            $stmt = $db->query("SELECT id_joueur, Nom, `Prénom` FROM Joueurs WHERE id_joueur > 0 ORDER BY Nom");
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

            // Appliquer les filtres
            if (!empty($filters['location'])) {
                $sql .= " AND Lieu = '" . $db->quote($filters['location']) . "'";
            }
            if (!empty($filters['type'])) {
                $sql .= " AND ManifestationTypée LIKE '%" . $db->quote($filters['type']) . "%'";
            }
            if (!empty($filters['this_week'])) {
                $weekStart = date('Y-m-d', strtotime('Monday this week'));
                $weekEnd = date('Y-m-d', strtotime('Sunday this week'));
                $sql .= " AND Date BETWEEN '" . $weekStart . "' AND '" . $weekEnd . " 23:59:59'";
            }

            $sql .= " ORDER BY `Date` ASC";
            $stmt = $db->query($sql);
            if (!$stmt) {
                error_log('getCrossTable: Failed to query Manifestation - ' . json_encode($db->errorInfo()));
                return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
            }

            $manifestationCount = 0;
            while ($row = $stmt->fetch()) {
                $manifestationCount++;
                $id  = (int) $row['id_manifestation'];
                $parts = explode(' - ', $row['ManifestationTypée'], 3);
                $type  = $parts[1] ?? '';
                $titre = $parts[2] ?? $row['ManifestationTypée'];

                // Calcul de la plage horaire
                $timeRange = '';
                if (!empty($row['Durée_créneau'])) {
                    $hm = explode('h', $row['Durée_créneau'], 2);
                    $h  = (int) ($hm[0] ?? 0);
                    $m  = isset($hm[1]) && $hm[1] !== '' ? (int) $hm[1] : 0;
                    $ts = strtotime($row['Date']);
                    $timeRange = date('H\hi', $ts) . ' - ' . date('H\hi', strtotime("+{$h} hour +{$m} minute", $ts));
                }

                $manifestations[$id] = [
                    'id'          => $id,
                    'type'        => $type,
                    'titre'       => $titre,
                    'date_display'=> self::formatDateDisplay(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['Date'])),
                    'time_range'  => $timeRange,
                    'nb_terrains' => (int) $row['Nombre_terrain'],
                    'lieu'        => $row['Lieu'],
                    'commentaire' => $row['Commentaire'] ?? '',
                    'statut'      => $row['Statut'] ?? '',
                    'annule'      => str_contains($row['Statut'] ?? '', 'Annulé'),
                    'provisoire'  => str_contains($row['Statut'] ?? '', 'Provisoire'),
                    'nb_present'  => 0, 'nb_absent' => 0, 'nb_disponible' => 0,
                    'nb_disponible_si_necessaire' => 0, 'nb_indisponible' => 0,
                    'nb_ne_sait_pas' => 0, 'nb_selection' => 0,
                    'nb_pas_de_reponse' => count($joueurs),
                ];
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
                        p.Participation AS participation,
                        DATE_FORMAT(m.`Date`, '%Y-%m-%d %H:%i') AS date2
                 FROM Joueurs j
                 LEFT JOIN Participation p USING (id_joueur)
                 LEFT JOIN Manifestation m USING (id_manifestation)
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

                // Skip if participation is empty
                if (!is_string($part) || $part === '') {
                    $cross[$jid]['nb_participation']++;

                    // Non-absence
                    if (strpos($part, 'Absent') === false && strpos($part, 'Non') === false && strpos($part, 'Indisponible') === false) {
                        $cross[$jid]['nb_non_absence']++;
                    }
                    // Ne sait pas
                    if (strpos($part, 'Ne sait pas encore') !== false) {
                        $cross[$jid]['nb_ne_sait_pas']++;
                        if (strtotime($row['date2']) < $dateTropProche) {
                            $cross[$jid]['nb_ne_sait_pas_proche']++;
                        }
                    }

                    // Stats manifestation — ordre strict pour éviter double comptage
                    if (strpos($part, 'Sélectionné(e)') !== false) {
                        $manifestations[$mid]['nb_selection']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                    } elseif (strpos($part, 'Disponible si n') !== false) {
                        $manifestations[$mid]['nb_disponible_si_necessaire']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                    } elseif (strpos($part, 'Disponible') !== false || strpos($part, 'Joker') !== false) {
                        $manifestations[$mid]['nb_disponible']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                    } elseif (strpos($part, 'Indisponible') !== false) {
                        $manifestations[$mid]['nb_indisponible']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                    } elseif (strpos($part, 'Absent') !== false || strpos($part, 'Non') !== false) {
                        $manifestations[$mid]['nb_absent']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                    } elseif (strpos($part, 'Oui') !== false || strpos($part, 'Présent') !== false) {
                        $manifestations[$mid]['nb_present']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                        // accompagnants
                        if (strpos($part, '5') !== false) {
                            $manifestations[$mid]['nb_present'] += 4;
                        } elseif (strpos($part, '4') !== false) {
                            $manifestations[$mid]['nb_present'] += 3;
                        } elseif (strpos($part, '3') !== false) {
                            $manifestations[$mid]['nb_present'] += 2;
                        } elseif (strpos($part, '2') !== false) {
                            $manifestations[$mid]['nb_present'] += 1;
                        }
                    } elseif (strpos($part, 'Ne sait pas encore') !== false || strpos($part, '?') !== false) {
                        $manifestations[$mid]['nb_ne_sait_pas']++;
                        $manifestations[$mid]['nb_pas_de_reponse']--;
                    }
                }
            }

        } catch (\Throwable $e) {
            error_log('getCrossTable: Exception - ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return ['joueurs' => [], 'manifestations' => [], 'cross' => []];
        }

        return ['joueurs' => $joueurs, 'manifestations' => $manifestations, 'cross' => $cross];
    }

    public static function getAllManifestations(array $filters = [], int $offset = 0, int $limit = 50): array
    {
        try {
            $type = $filters['type'] ?? null;
            $location = $filters['location'] ?? null;
            $dateFrom = $filters['date_from'] ?? null;
            $dateTo   = $filters['date_to'] ?? null;

            $sql = "SELECT * FROM Manifestation WHERE 1=1";

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

            $sql .= " ORDER BY Date ASC LIMIT ? OFFSET ?";

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
            $bindings[] = $limit;
            $bindings[] = $offset;

            $stmt->execute($bindings);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        return array_map([self::class, 'buildEventWithId'], $rows);
    }

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

    public static function getParticipationStats(int $manifestationId): array
    {
        try {
            $db = ExternalDatabase::get();
            $stmt = $db->prepare(
                "SELECT Participation, COUNT(*) as cnt
                 FROM Participation
                 WHERE id_manifestation = ?
                 GROUP BY Participation"
            );
            $stmt->execute([$manifestationId]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return ['present' => 0, 'available' => 0, 'unavailable' => 0, 'no_response' => 0, 'total_responses' => 0];
        }

        $stats = ['present' => 0, 'available' => 0, 'unavailable' => 0, 'selected' => 0, 'absent' => 0, 'unknown' => 0];

        foreach ($rows as $row) {
            $status = $row['Participation'] ?? '';
            $count = (int) ($row['cnt'] ?? 0);

            if (strpos($status, 'Sélectionné(e)') !== false) {
                $stats['selected'] += $count;
            } elseif (strpos($status, 'Disponible si n') !== false) {
                $stats['available'] += $count;
            } elseif (strpos($status, 'Disponible') !== false || strpos($status, 'Joker') !== false) {
                $stats['available'] += $count;
            } elseif (strpos($status, 'Indisponible') !== false) {
                $stats['unavailable'] += $count;
            } elseif (strpos($status, 'Absent') !== false || strpos($status, 'Non') !== false) {
                $stats['absent'] += $count;
            } elseif (strpos($status, 'Oui') !== false || strpos($status, 'Présent') !== false) {
                $stats['present'] += $count;
            } elseif (strpos($status, 'Ne sait pas') !== false || strpos($status, '?') !== false) {
                $stats['unknown'] += $count;
            }
        }

        $total_responses = array_sum($stats);
        $stats['total_responses'] = $total_responses;
        $stats['enough_players'] = ($stats['present'] + $stats['available'] + $stats['selected']) >= 6;

        return $stats;
    }

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
            return array_fill_keys($manifestationIds, ['present' => 0, 'available' => 0, 'unavailable' => 0, 'no_response' => 0, 'total_responses' => 0, 'enough_players' => false]);
        }

        $result = array_fill_keys($manifestationIds, [
            'present' => 0, 'available' => 0, 'unavailable' => 0, 'selected' => 0, 'absent' => 0, 'unknown' => 0,
            'total_responses' => 0, 'enough_players' => false
        ]);

        foreach ($rows as $row) {
            $mid = (int) ($row['id_manifestation'] ?? 0);
            if (!isset($result[$mid])) {
                $result[$mid] = ['present' => 0, 'available' => 0, 'unavailable' => 0, 'selected' => 0, 'absent' => 0, 'unknown' => 0];
            }

            $status = $row['Participation'] ?? '';
            $count = (int) ($row['cnt'] ?? 0);

            if (strpos($status, 'Sélectionné(e)') !== false) {
                $result[$mid]['selected'] += $count;
            } elseif (strpos($status, 'Disponible si n') !== false) {
                $result[$mid]['available'] += $count;
            } elseif (strpos($status, 'Disponible') !== false || strpos($status, 'Joker') !== false) {
                $result[$mid]['available'] += $count;
            } elseif (strpos($status, 'Indisponible') !== false) {
                $result[$mid]['unavailable'] += $count;
            } elseif (strpos($status, 'Absent') !== false || strpos($status, 'Non') !== false) {
                $result[$mid]['absent'] += $count;
            } elseif (strpos($status, 'Oui') !== false || strpos($status, 'Présent') !== false) {
                $result[$mid]['present'] += $count;
            } elseif (strpos($status, 'Ne sait pas') !== false || strpos($status, '?') !== false) {
                $result[$mid]['unknown'] += $count;
            }
        }

        foreach ($result as $mid => &$stats) {
            $stats['total_responses'] = array_sum([$stats['present'], $stats['available'], $stats['unavailable'], $stats['selected'], $stats['absent'], $stats['unknown']]);
            $stats['enough_players'] = ($stats['present'] + $stats['available'] + $stats['selected']) >= 6;
        }

        return $result;
    }

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

    private static function buildEventWithId(array $row): array
    {
        $event = self::buildEvent($row);
        $event['id'] = (int) ($row['id_manifestation'] ?? 0);
        $event['type'] = self::extractType($row['ManifestationTypée'] ?? '');
        $event['duration'] = $row['Durée_créneau'] ?? null;
        $event['nb_courts'] = (int) ($row['Nombre_terrain'] ?? 0);
        return $event;
    }

    private static function extractTitle(string $type): string
    {
        // "Disponibilités - Match - Match L2" → "Match L2"
        $parts = explode(' - ', $type, 3);
        return count($parts) === 3 ? trim($parts[2]) : trim($type);
    }

    private static function extractType(string $typeStr): string
    {
        // "Disponibilités - Match - Match L2" → "Match"
        $parts = explode(' - ', $typeStr, 2);
        return count($parts) >= 2 ? trim($parts[1]) : '';
    }

    private static function formatDateDisplay(\DateTimeImmutable $date): string
    {
        $dayEn   = $date->format('D'); // Mon, Tue, …
        $dayFr   = self::$DAYS_FR[$dayEn]  ?? $dayEn;
        $monthEn = $date->format('M'); // Jan, Feb, …
        $monthFr = self::$MONTHS_FR[$monthEn] ?? $monthEn;
        return $dayFr . ' ' . $date->format('j') . ' ' . $monthFr;
    }

    public static function getFilterOptions(): array
    {
        try {
            $db = ExternalDatabase::get();

            // Get unique event types
            $types = [];
            $stmt = $db->query(
                "SELECT DISTINCT SUBSTRING_INDEX(ManifestationTypée, ' - ', 2) AS type_part
                 FROM Manifestation
                 WHERE id_manifestation > 0 AND Date >= CURDATE()
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

            return [
                'types'     => $types,
                'locations' => $locations,
            ];
        } catch (\Throwable) {
            return ['types' => [], 'locations' => []];
        }
    }
}
