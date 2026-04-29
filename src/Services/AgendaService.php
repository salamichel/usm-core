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
}
