<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ExternalDatabase;

class CalendarWidgetService
{
    private static array $MONTHS_FR = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    private static array $DAYS_FR = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

    public static function getCalendarData(?string $yearMonth = null): array
    {
        if (!$yearMonth) {
            $yearMonth = date('Y-m');
        }

        [$year, $month] = explode('-', $yearMonth);
        $year = (int) $year;
        $month = (int) $month;

        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $lastDay = mktime(23, 59, 59, $month + 1, 0, $year);
        $daysInMonth = date('t', $firstDay);
        $firstWeekday = (int) date('w', $firstDay);

        $days = [];
        $dayCounter = 1;

        for ($week = 0; $week < 6; $week++) {
            for ($weekday = 0; $weekday < 7; $weekday++) {
                $dayNum = null;
                $date = null;

                if ($week === 0 && $weekday < $firstWeekday) {
                    $days[$week][$weekday] = null;
                } elseif ($dayCounter <= $daysInMonth) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $dayCounter);
                    $days[$week][$weekday] = [
                        'day' => $dayCounter,
                        'date' => $date,
                        'isToday' => $date === date('Y-m-d'),
                    ];
                    $dayCounter++;
                } else {
                    $days[$week][$weekday] = null;
                }
            }
            if ($dayCounter > $daysInMonth) {
                break;
            }
        }

        $events = self::getEventsForMonth($year, $month);
        $eventsByDate = self::groupEventsByDate($events);

        return [
            'year' => $year,
            'month' => $month,
            'monthName' => self::$MONTHS_FR[$month],
            'dayHeaders' => self::$DAYS_FR,
            'weeks' => $days,
            'eventsByDate' => $eventsByDate,
            'events' => $events,
            'today' => date('Y-m-d'),
        ];
    }

    private static function getEventsForMonth(int $year, int $month): array
    {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                return [];
            }

            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = sprintf('%04d-%02d-%d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));

            $stmt = $db->prepare(
                "SELECT id_manifestation, ManifestationTypée, DateEvent, HeureEvent
                 FROM Manifestation
                 WHERE DATE(DateEvent) >= ? AND DATE(DateEvent) <= ?
                 ORDER BY DateEvent ASC"
            );
            $stmt->execute([$startDate, $endDate]);

            $events = [];
            while ($row = $stmt->fetch()) {
                $events[] = [
                    'id' => $row['id_manifestation'],
                    'title' => self::extractTitle($row['ManifestationTypée']),
                    'date' => date('Y-m-d', strtotime($row['DateEvent'])),
                    'time' => $row['HeureEvent'] ? substr($row['HeureEvent'], 0, 5) : '',
                    'type' => self::extractType($row['ManifestationTypée']),
                ];
            }

            return $events;
        } catch (\Exception $e) {
            error_log('CalendarWidgetService::getEventsForMonth: ' . $e->getMessage());
            return [];
        }
    }

    private static function groupEventsByDate(array $events): array
    {
        $grouped = [];
        foreach ($events as $event) {
            $date = $event['date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $event;
        }
        return $grouped;
    }

    private static function extractTitle(string $manifestationTypée): string
    {
        $parts = explode(' - ', $manifestationTypée);
        return isset($parts[2]) ? trim($parts[2]) : $manifestationTypée;
    }

    private static function extractType(string $manifestationTypée): string
    {
        $parts = explode(' - ', $manifestationTypée);
        return isset($parts[1]) ? trim($parts[1]) : 'Événement';
    }
}
