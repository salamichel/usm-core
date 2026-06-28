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
            'id_manifestation'            => $id,
            'id'                          => $id,
            'ManifestationTypée'          => $row['ManifestationTypée'] ?? '',
            'type'                        => $type,
            'titre'                       => $titre,
            'title'                       => $titre,
            'Date'                        => $dateStr,
            'date'                        => $date ? $date->format('Y-m-d') : null,
            'date_display'                => $date ? self::formatDateDisplay($date) : ($dateStr ?? ''),
            'time_range'                  => $timeRange,
            'time_display'                => $timeDisplay,
            'duration'                    => $row['Durée_créneau'] ?? null,
            'nb_courts'                   => (int)($row['Nombre_terrain'] ?? 0),
            'nb_terrains'                 => (int)($row['Nombre_terrain'] ?? 0),
            'location'                    => $row['Lieu'] ?? null,
            'lieu'                        => $row['Lieu'] ?? null,
            'comment'                     => !empty($row['Commentaire']) ? $row['Commentaire'] : null,
            'commentaire'                 => !empty($row['Commentaire']) ? $row['Commentaire'] : null,
            'status'                      => $statut,
            'statut'                      => $statut,
            'annule'                      => str_contains($statut, 'Annulé'),
            'provisoire'                  => str_contains($statut, 'Provisoire'),
            'is_soon'                     => $isSoon,
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
        if (!isset($manifestationStats['presents'])) {
            foreach (
                [
                    'presents',
                    'absents',
                    'disponibles',
                    'disponibles_si_necessaire',
                    'indisponibles',
                    'selectionnes',
                    'ne_sait_pas',
                    'pas_de_reponse'
                ] as $key
            ) {
                $manifestationStats[$key] = [];
            }
        }

        match ($category) {
            'selected'    => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['selectionnes'][] = $playerInfo;
                $manifestationStats['nb_selection']++;
            })(),
            'available'   => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['disponibles'][] = $playerInfo;
                $manifestationStats['nb_disponible']++;
            })(),
            'available_if_needed' => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['disponibles_si_necessaire'][] = $playerInfo;
                $manifestationStats['nb_disponible_si_necessaire']++;
            })(),
            'unavailable' => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['indisponibles'][] = $playerInfo;
                $manifestationStats['nb_indisponible']++;
            })(),
            'absent'      => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['absents'][] = $playerInfo;
                $manifestationStats['nb_absent']++;
            })(),
            'present'     => (function () use ($status, $playerInfo, &$manifestationStats) {
                $manifestationStats['presents'][] = $playerInfo;
                $manifestationStats['nb_present']++;
                // Comptabiliser les accompagnants éventuels
                $manifestationStats['nb_present'] += $status->getCompanionCount();
            })(),
            'unknown'     => (function () use ($playerInfo, &$manifestationStats) {
                $manifestationStats['ne_sait_pas'][] = $playerInfo;
                $manifestationStats['nb_ne_sait_pas']++;
            })(),
            default       => null,
        };

        if ($category !== 'no_response') {
            $manifestationStats['nb_pas_de_reponse']--;
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
