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

    private static function extractTitle(string $type): string
    {
        // "Disponibilités - Match - Match L2" → "Match L2"
        $parts = explode(' - ', $type, 3);
        return count($parts) === 3 ? trim($parts[2]) : trim($type);
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
