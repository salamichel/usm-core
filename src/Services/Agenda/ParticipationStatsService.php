<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Core\ExternalDatabase;
use App\Helpers\ParticipationStatus;

/**
 * Calcule et agrège les statistiques de participation aux manifestations.
 * Toutes les requêtes ciblent la table Participation de la base externe.
 */
class ParticipationStatsService
{
    /**
     * Statistiques de participation pour une manifestation (requête unitaire).
     *
     * Catégories retournées : present, available, unavailable, selected, absent, unknown,
     * available_if_needed, total_responses, enough_players.
     *
     * @param int $manifestationId Identifiant de la manifestation
     * @return array Stats avec compteurs par catégorie
     */
    public static function getParticipationStats(int $manifestationId): array
    {
        try {
            $db = ExternalDatabase::get();
            if (!$db) {
                return self::defaultStats();
            }
            $stmt = $db->prepare(
                "SELECT p.Participation, m.ManifestationTypée 
                 FROM Participation p
                 JOIN Manifestation m ON p.id_manifestation = m.id_manifestation
                 WHERE p.id_manifestation = ?"
            );
            $stmt->execute([$manifestationId]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return self::defaultStats();
        }

        $stats = self::emptyStats();
        $type = '';

        foreach ($rows as $row) {
            $statusStr = trim((string)($row['Participation'] ?? ''));
            if (isset($row['ManifestationTypée'])) {
                $type = $row['ManifestationTypée'];
            }
            if ($statusStr === '') {
                continue;
            }
            $status   = new ParticipationStatus($statusStr);
            $category = $status->getCategory();

            if (isset($stats[$category])) {
                $stats[$category]++;
                // Comptabiliser les accompagnants des présents
                if ($category === 'present') {
                    $stats[$category] += $status->getCompanionCount();
                }
            }
        }

        $stats['total_responses'] = array_sum([
            $stats['present'],
            $stats['available'],
            $stats['available_if_needed'],
            $stats['unavailable'],
            $stats['selected'],
            $stats['absent'],
            $stats['unknown'],
        ]);

        $minRequired = self::getMinPlayersRequired($type);
        $stats['min_required'] = $minRequired;
        $stats['enough_players'] = (
            $stats['present'] + $stats['available'] + $stats['selected'] + $stats['available_if_needed']
        ) >= $minRequired;

        return $stats;
    }

    /**
     * Stats de participation pour plusieurs manifestations en une seule requête (évite le N+1).
     *
     * @param int[] $manifestationIds
     * @return array<int, array> manifestationId => stats_array
     */
    public static function getParticipationStatsBatch(array $manifestationIds): array
    {
        if (empty($manifestationIds)) {
            return [];
        }

        try {
            $db           = ExternalDatabase::get();
            $placeholders = implode(',', array_fill(0, count($manifestationIds), '?'));
            $stmt         = $db->prepare(
                "SELECT p.id_manifestation, p.Participation, m.ManifestationTypée, COUNT(*) as cnt
                 FROM Participation p
                 JOIN Manifestation m ON p.id_manifestation = m.id_manifestation
                 WHERE p.id_manifestation IN ($placeholders)
                 GROUP BY p.id_manifestation, m.ManifestationTypée, p.Participation"
            );
            $stmt->execute($manifestationIds);
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return array_fill_keys($manifestationIds, self::defaultStats());
        }

        $result = array_fill_keys($manifestationIds, self::emptyStats());
        $typesMap = [];

        foreach ($rows as $row) {
            $mid      = (int)($row['id_manifestation'] ?? 0);
            $count    = (int)($row['cnt'] ?? 0);
            $status   = new ParticipationStatus($row['Participation'] ?? '');
            $category = $status->getCategory();

            if (isset($row['ManifestationTypée'])) {
                $typesMap[$mid] = $row['ManifestationTypée'];
            }

            if (!isset($result[$mid])) {
                $result[$mid] = self::emptyStats();
            }
            if (isset($result[$mid][$category])) {
                $result[$mid][$category] += $count;
            }
        }

        foreach ($result as $mid => &$stats) {
            $stats['total_responses'] = array_sum([
                $stats['present'],
                $stats['available'],
                $stats['available_if_needed'],
                $stats['unavailable'],
                $stats['selected'],
                $stats['absent'],
                $stats['unknown'],
            ]);

            $type = $typesMap[$mid] ?? '';
            $minRequired = self::getMinPlayersRequired($type);
            $stats['min_required'] = $minRequired;
            $stats['enough_players'] = (
                $stats['present'] + $stats['available'] + $stats['selected'] + $stats['available_if_needed']
            ) >= $minRequired;
        }
        unset($stats);

        return $result;
    }

    /**
     * Retourne le nombre minimum de joueurs requis pour un événement.
     */
    public static function getMinPlayersRequired(string $manifestationType): int
    {
        try {
            $db = \App\Core\Database::get();
            if ($db) {
                $teams = $db->query("SELECT libelle, team_filter, manifestation_filter, min_players FROM equipes_config WHERE is_active = 1")->fetchAll();
                foreach ($teams as $team) {
                    $filter = $team['manifestation_filter'] ?: $team['libelle'];
                    if ($filter !== '' && str_contains($manifestationType, $filter)) {
                        return (int)$team['min_players'];
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback
        }

        $typeLower = mb_strtolower($manifestationType);
        if (
            str_contains($typeLower, '4x4') ||
            str_contains($typeLower, '4*4') ||
            str_contains($typeLower, 'm13') ||
            str_contains($typeLower, 'm15') ||
            str_contains($typeLower, 'ufolep')
        ) {
            return 4;
        }
        return 6;
    }

    /**
     * Counts formatés pour les rendus JS (clés = libellés de statut en français).
     */
    public static function getNormalizedCounts(int $manifestationId): array
    {
        $stats = self::getParticipationStats($manifestationId);

        return [
            'available'           => $stats['available']           ?? 0,
            'available_if_needed' => $stats['available_if_needed'] ?? 0,
            'unavailable'         => $stats['unavailable']         ?? 0,
            'present'             => $stats['present']             ?? 0,
            'absent'              => $stats['absent']              ?? 0,
            'unknown'             => $stats['unknown']             ?? 0,
            'selected'            => $stats['selected']            ?? 0,
        ];
    }

    /**
     * Tableau de stats vide avec toutes les catégories à zéro.
     */
    public static function emptyStats(): array
    {
        return [
            'present'             => 0,
            'available'           => 0,
            'available_if_needed' => 0,
            'unavailable'         => 0,
            'selected'            => 0,
            'absent'              => 0,
            'unknown'             => 0,
        ];
    }

    /**
     * Stats par défaut pour les cas d'erreur BDD (avec champs calculés à zéro/false).
     */
    public static function defaultStats(): array
    {
        $stats = self::emptyStats();
        $stats['total_responses'] = 0;
        $stats['no_response']     = 0;
        $stats['min_required']    = 6;
        $stats['enough_players']  = false;
        return $stats;
    }
}
