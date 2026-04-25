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
        return self::queryByType('Match', $limit);
    }

    public static function getUpcomingTrainings(int $limit = 5): array
    {
        return self::queryByType('Entraînement', $limit);
    }

    private static function queryByType(string $type, int $limit): array
    {
        try {
            $stmt = ExternalDatabase::get()->prepare(
                "SELECT * FROM Manifestation
                 WHERE type_manifestation = :type
                   AND DateManif >= CURDATE()
                 ORDER BY DateManif ASC, HeureManif ASC
                 LIMIT :limit"
            );
            $stmt->bindValue(':type',  $type);
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
        $today = new \DateTimeImmutable('today');
        $date  = \DateTimeImmutable::createFromFormat('Y-m-d', $row['DateManif']);
        $isSoon = $date && $date->diff($today)->days <= 3 && $date >= $today;

        $timeDisplay = '';
        if (!empty($row['HeureManif'])) {
            $timeDisplay = substr($row['HeureManif'], 0, 5); // HH:MM
        }

        return [
            'title'        => $row['LibelleManif'],
            'date_display' => $date ? self::formatDateDisplay($date) : ($row['DateManif'] ?? ''),
            'time_display' => $timeDisplay,
            'location'     => $row['Lieu'] ?? null,
            'comment'      => !empty($row['commentaire']) ? $row['commentaire'] : null,
            'status'       => $row['statut'] ?? null,
            'is_soon'      => $isSoon,
        ];
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
