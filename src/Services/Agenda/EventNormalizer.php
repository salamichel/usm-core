<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Helpers\ParticipationStatus;

/**
 * Transforme les lignes brutes de la table Manifestation en structures canoniques agenda.
 * N'effectue aucune requête BDD — classe purement fonctionnelle.
 */
class EventNormalizer
{
    private static array $MONTHS_FR = [
        'Jan' => 'Jan',
        'Feb' => 'Fév',
        'Mar' => 'Mar',
        'Apr' => 'Avr',
        'May' => 'Mai',
        'Jun' => 'Jun',
        'Jul' => 'Jul',
        'Aug' => 'Aoû',
        'Sep' => 'Sep',
        'Oct' => 'Oct',
        'Nov' => 'Nov',
        'Dec' => 'Déc',
    ];

    private static array $DAYS_FR = [
        'Mon' => 'Lun',
        'Tue' => 'Mar',
        'Wed' => 'Mer',
        'Thu' => 'Jeu',
        'Fri' => 'Ven',
        'Sat' => 'Sam',
        'Sun' => 'Dim',
    ];

    /**
     * Construit un objet événement minimal depuis une ligne brute de Manifestation.
     * Utilisé pour les listes légères (getUpcomingMatches, etc.)
     */
    public static function buildEvent(array $row): array
    {
        $today   = new \DateTimeImmutable('today');
        $dateStr = $row['Date'] ?? null;
        $date    = $dateStr
            ? (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', substr($dateStr, 0, 10)))
            : null;
        $isSoon      = $date && $date->diff($today)->days <= 3 && $date >= $today;
        $timeDisplay = ($date && $date->format('H:i') !== '00:00') ? $date->format('H:i') : '';

        return [
            'id'           => (int)($row['id_manifestation'] ?? 0),
            'titre'        => self::extractTitle($row['ManifestationTypée'] ?? ''),
            'date_display' => $date ? self::formatDateDisplay($date) : ($dateStr ?? ''),
            'time_display' => $timeDisplay,
            'lieu'         => $row['Lieu'] ?? null,
            'status'       => $row['Statut'] ?? null,
            'is_soon'      => $isSoon,
        ];
    }

    /**
     * Étend buildEvent avec l'ID et le type (utilisé par getEventById).
     */
    public static function buildEventWithId(array $row): array
    {
        $event              = self::buildEvent($row);
        $event['id']        = (int)($row['id_manifestation'] ?? 0);
        $event['type']      = self::extractType($row['ManifestationTypée'] ?? '');
        $event['duration']  = $row['Durée_créneau'] ?? null;
        $event['nb_courts'] = (int)($row['Nombre_terrain'] ?? 0);
        return $event;
    }

    /**
     * Construit le tableau canonique complet d'une manifestation (sans enrichissement BDD participation).
     * Tous les compteurs nb_* sont à zéro, les listes de joueurs sont vides.
     */
    public static function buildBaseFields(array $row, int $totalJoueurs = 0): array
    {
        $id    = (int)($row['id_manifestation'] ?? 0);
        $parts = explode(' - ', $row['ManifestationTypée'] ?? '', 3);
        $type  = $parts[1] ?? '';
        $titre = $parts[2] ?? ($row['ManifestationTypée'] ?? '');

        // Calcul de la plage horaire depuis Durée_créneau
        $timeRange = '';
        if (!empty($row['Durée_créneau'])) {
            $hm = explode('h', $row['Durée_créneau'], 2);
            $h  = (int)($hm[0] ?? 0);
            $m  = isset($hm[1]) && $hm[1] !== '' ? (int)$hm[1] : 0;
            $ts = strtotime($row['Date']);
            $timeRange = date('H\hi', $ts) . ' - ' . date('H\hi', strtotime("+{$h} hour +{$m} minute", $ts));
        }

        $dateStr     = $row['Date'] ?? null;
        $date        = $dateStr
            ? (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateStr)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', substr($dateStr, 0, 10)))
            : null;
        $timeDisplay = ($date && $date->format('H:i') !== '00:00') ? $date->format('H:i') : '';
        $statut      = $row['Statut'] ?? '';
        $today       = new \DateTimeImmutable('today');
        $isSoon      = $date && $date->diff($today)->days <= 3 && $date >= $today;

        return [
            'id'                          => $id,
            'ManifestationTypée'          => $row['ManifestationTypée'] ?? '',
            'type'                        => $type,
            'titre'                       => $titre,
            'Date'                        => $dateStr,
            'date'                        => $date ? $date->format('Y-m-d') : null,
            'date_display'                => $date ? self::formatDateDisplay($date) : ($dateStr ?? ''),
            'time_range'                  => $timeRange,
            'time_display'                => $timeDisplay,
            'duration'                    => $row['Durée_créneau'] ?? null,
            'nb_terrains'                 => (int)($row['Nombre_terrain'] ?? 0),
            'lieu'                        => $row['Lieu'] ?? null,
            'commentaire'                 => !empty($row['Commentaire']) ? $row['Commentaire'] : null,
            'status'                      => $statut,
            'statut'                      => $statut,
            'annule'                      => str_contains($statut, 'Annulé'),
            'provisoire'                  => str_contains($statut, 'Provisoire'),
            'is_soon'                     => $isSoon,
            'nb_present'                  => 0,
            'nb_absent'                   => 0,
            'nb_available'                => 0,
            'nb_available_if_needed'      => 0,
            'nb_unavailable'              => 0,
            'nb_selected'                 => 0,
            'nb_unknown'                  => 0,
            'nb_no_response'              => $totalJoueurs,
            'present'                     => [],
            'absent'                      => [],
            'available'                   => [],
            'available_if_needed'         => [],
            'unavailable'                 => [],
            'selected'                    => [],
            'unknown'                     => [],
            'no_response'                 => [],
            'is_match'                    => str_contains($type, 'Match'),
            'is_training'                 => str_contains($type, 'Entra'),
            'type_simple'                 => $type,
            'type_libelle'                => $type,
        ];
    }

    /**
     * Met à jour les compteurs et listes de joueurs d'une manifestation
     * en fonction du statut de participation d'un joueur.
     */
    public static function updateManifestationStats(
        array &$manifestationStats,
        ParticipationStatus $status,
        int $jid,
        string $nomJoueur,
        string $rawStatus
    ): void {
        $category   = $status->getCategory();
        $playerInfo = ['id' => $jid, 'nom' => $nomJoueur];

        // Initialisation défensive des tableaux
        if (!isset($manifestationStats['present'])) {
            foreach (
                [
                    'present',
                    'absent',
                    'available',
                    'available_if_needed',
                    'unavailable',
                    'selected',
                    'unknown',
                    'no_response'
                ] as $key
            ) {
                $manifestationStats[$key] = [];
            }
        }

        match ($category) {
            'selected'    => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['selected'][] = $playerInfo;
                $manifestationStats['nb_selected']++;
            })(),
            'available'   => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['available'][] = $playerInfo;
                $manifestationStats['nb_available']++;
            })(),
            'available_if_needed' => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['available_if_needed'][] = $playerInfo;
                $manifestationStats['nb_available_if_needed']++;
            })(),
            'unavailable' => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['unavailable'][] = $playerInfo;
                $manifestationStats['nb_unavailable']++;
            })(),
            'absent'      => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['absent'][] = $playerInfo;
                $manifestationStats['nb_absent']++;
            })(),
            'present'     => (function () use ($status, $playerInfo, &$manifestationStats) {
                $manifestationStats['present'][] = $playerInfo;
                $manifestationStats['nb_present']++;
                // Comptabiliser les accompagnants éventuels
                $manifestationStats['nb_present'] += $status->getCompanionCount();
            })(),
            'unknown'     => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['unknown'][] = $playerInfo;
                $manifestationStats['nb_unknown']++;
            })(),
            default       => null,
        };

        if ($category !== 'no_response') {
            $manifestationStats['nb_no_response']--;
        }
    }

    /**
     * Extrait le titre de l'événement depuis ManifestationTypée.
     * Format : "Disponibilités - Type - Titre" → "Titre"
     */
    public static function extractTitle(string $type): string
    {
        $parts = explode(' - ', $type, 3);
        return count($parts) === 3 ? trim($parts[2]) : trim($type);
    }

    /**
     * Extrait le type de l'événement depuis ManifestationTypée.
     * Format : "Disponibilités - Type - Titre" → "Type"
     */
    public static function extractType(string $typeStr): string
    {
        $parts = explode(' - ', $typeStr, 2);
        return count($parts) >= 2 ? trim($parts[1]) : '';
    }

    /**
     * Formate une date pour affichage en français.
     * Exemple : DateTimeImmutable('2025-01-06') → "Lun 6 Jan"
     */
    public static function formatDateDisplay(\DateTimeImmutable $date): string
    {
        $dayEn   = $date->format('D');
        $dayFr   = self::$DAYS_FR[$dayEn]    ?? $dayEn;
        $monthEn = $date->format('M');
        $monthFr = self::$MONTHS_FR[$monthEn] ?? $monthEn;
        return $dayFr . ' ' . $date->format('j') . ' ' . $monthFr;
    }
}
