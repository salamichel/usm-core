<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Agenda\EventNormalizer;
use App\Services\Agenda\EventRepository;
use App\Services\Agenda\ParticipationStatsService;

/**
 * Façade de l'agenda — conserve la compatibilité ascendante complète.
 *
 * Toutes les méthodes publiques délèguent aux classes spécialisées :
 *
 * @see EventRepository           — requêtes BDD base externe (Manifestation, Joueurs, Participation)
 * @see EventNormalizer           — transformation row DB → objet agenda canonique (sans BDD)
 * @see ParticipationStatsService — calcul et agrégation des statistiques de participation
 *
 * Cette classe ne contient aucune logique propre.
 * Pour ajouter une nouvelle fonctionnalité, intervenez dans la classe spécialisée appropriée.
 */
class AgendaService
{
    // ── Requêtes de manifestations (EventRepository) ─────────────────────────

    /** @see EventRepository::getUpcomingMatches() */
    public static function getUpcomingMatches(int $limit = 5): array
    {
        return EventRepository::getUpcomingMatches($limit);
    }

    /** @see EventRepository::getUpcomingTrainings() */
    public static function getUpcomingTrainings(int $limit = 5): array
    {
        return EventRepository::getUpcomingTrainings($limit);
    }

    /** @see EventRepository::getUpcomingByType() */
    public static function getUpcomingByType(string $needle, int $limit = 5): array
    {
        return EventRepository::getUpcomingByType($needle, $limit);
    }

    /** @see EventRepository::getUpcomingByTypes() */
    public static function getUpcomingByTypes(array $needles, int $limit = 5): array
    {
        return EventRepository::getUpcomingByTypes($needles, $limit);
    }

    /** @see EventRepository::getCrossTable() */
    public static function getCrossTable(array $filters = []): array
    {
        return EventRepository::getCrossTable($filters);
    }

    /** @see EventRepository::getEventById() */
    public static function getEventById(int $id): ?array
    {
        return EventRepository::getEventById($id);
    }

    /** @see EventRepository::getAvailableFilters() */
    public static function getAvailableFilters(): array
    {
        return EventRepository::getAvailableFilters();
    }

    /** @see EventRepository::countManifestations() */
    public static function countManifestations(array $filters = []): int
    {
        return EventRepository::countManifestations($filters);
    }

    /** @see EventRepository::getFilterOptions() */
    public static function getFilterOptions(): array
    {
        return EventRepository::getFilterOptions();
    }

    /** @see EventRepository::getUpcomingMatchesForTeam() */
    public static function getUpcomingMatchesForTeam(
        string $teamCode,
        int $limit = 5,
        ?string $manifestationFilter = null
    ): array {
        return EventRepository::getUpcomingMatchesForTeam($teamCode, $limit, $manifestationFilter);
    }

    /** @see EventRepository::getUpcomingEventsForTeam() */
    public static function getUpcomingEventsForTeam(
        array $team,
        int $limit = 8
    ): array {
        return EventRepository::getUpcomingEventsForTeam($team, $limit);
    }

    /** @see EventRepository::normalizeManifestation() */
    public static function normalizeManifestation(array $row, int $totalJoueurs = 0): array
    {
        return EventRepository::normalizeManifestation($row, $totalJoueurs);
    }

    // ── Stats de participation (ParticipationStatsService) ───────────────────

    /** @see ParticipationStatsService::getParticipationStats() */
    public static function getParticipationStats(int $manifestationId): array
    {
        return ParticipationStatsService::getParticipationStats($manifestationId);
    }

    /** @see ParticipationStatsService::getParticipationStatsBatch() */
    public static function getParticipationStatsBatch(array $manifestationIds): array
    {
        return ParticipationStatsService::getParticipationStatsBatch($manifestationIds);
    }

    /** @see ParticipationStatsService::getNormalizedCounts() */
    public static function getNormalizedCounts(int $manifestationId): array
    {
        return ParticipationStatsService::getNormalizedCounts($manifestationId);
    }

    /**
     * Identifie et flague les événements en chevauchement horaire avec un match où le joueur est déjà sélectionné.
     */
    public static function flagOverlappingSelected(array &$events, int $userId): void
    {
        if (empty($events) || !$userId) {
            return;
        }

        // 1. Trouver les événements pour lesquels l'utilisateur est sélectionné
        $selectedEvents = [];
        foreach ($events as $e) {
            $status = $e['user_status'] ?? '';
            // Ne pas considérer les événements annulés comme bloquants
            $isCancelled = ($e['annule'] ?? false) || str_contains((string)($e['Statut'] ?? $e['statut'] ?? $e['status'] ?? ''), 'Annulé');
            if ($isCancelled) {
                continue;
            }
            if (\App\Helpers\ParticipationStatus::categorize((string)$status) === 'selected') {
                $selectedEvents[] = $e;
            }
        }

        if (empty($selectedEvents)) {
            return;
        }

        // Helper pour calculer la plage horaire
        $getRange = function ($e) {
            $dateStr = $e['Date'] ?? $e['date'] ?? null;
            if (!$dateStr) return [0, 0];
            $start = strtotime($dateStr);
            if ($start === false) return [0, 0];
            
            $durationStr = $e['Durée_créneau'] ?? $e['duration'] ?? '2h';
            $hours = 2;
            $minutes = 0;
            if (!empty($durationStr)) {
                if (str_contains($durationStr, 'h')) {
                    $parts = explode('h', $durationStr);
                    $hours = (int)($parts[0] ?? 2);
                    $minutes = isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : 0;
                } elseif (str_contains($durationStr, 'm')) {
                    $hours = 0;
                    $minutes = (int)str_replace('m', '', $durationStr);
                }
            }
            $end = strtotime("+{$hours} hour +{$minutes} minute", $start);
            return [$start, $end];
        };

        // 2. Calculer les plages horaires des événements sélectionnés
        $selectedRanges = [];
        foreach ($selectedEvents as $se) {
            $selectedRanges[] = $getRange($se);
        }

        // 3. Flaguer les événements en chevauchement
        foreach ($events as &$e) {
            $status = $e['user_status'] ?? '';
            if (\App\Helpers\ParticipationStatus::categorize((string)$status) === 'selected') {
                $e['is_selected_by_captain'] = true;
                continue;
            }

            // Si l'événement lui-même est annulé, on ne le marque pas en chevauchement
            $isCancelled = ($e['annule'] ?? false) || str_contains((string)($e['Statut'] ?? $e['statut'] ?? $e['status'] ?? ''), 'Annulé');
            if ($isCancelled) {
                $e['is_overlapping_selected'] = false;
                continue;
            }

            $eRange = $getRange($e);
            if ($eRange[0] === 0) continue;

            $e['is_overlapping_selected'] = false;
            foreach ($selectedRanges as $sr) {
                if ($sr[0] < $eRange[1] && $eRange[0] < $sr[1]) {
                    $e['is_overlapping_selected'] = true;
                    break;
                }
            }
        }
        unset($e);
    }

    /**
     * Vérifie si un événement chevauche un match pour lequel l'utilisateur est déjà sélectionné.
     */
    public static function isOverlappingSelected(int $userId, int $manifestationId): bool
    {
        if (!$userId || !$manifestationId) {
            return false;
        }

        // 1. Récupérer l'événement cible
        $targetEvent = self::getEventById($manifestationId);
        if (!$targetEvent) {
            return false;
        }

        // Si l'événement cible lui-même est annulé, pas de chevauchement à signaler
        $targetCancelled = ($targetEvent['annule'] ?? false) || str_contains((string)($targetEvent['Statut'] ?? $targetEvent['statut'] ?? $targetEvent['status'] ?? ''), 'Annulé');
        if ($targetCancelled) {
            return false;
        }

        // 2. Récupérer les catégories du joueur pour trouver ses événements à venir
        $categories = \App\Models\Joueur::getCategories($userId);
        if (empty($categories)) {
            return false;
        }

        // 3. Récupérer les événements futurs du joueur
        $upcoming = \App\Models\Participation::getUpcomingForMember($userId, $categories);

        // 4. Trouver les événements sélectionnés (autres que l'événement cible)
        $selectedEvents = [];
        foreach ($upcoming as $m) {
            $status = $m['user_status'] ?? '';
            $mid = (int)($m['id_manifestation'] ?? $m['id'] ?? 0);
            
            // Ne pas prendre en compte les événements annulés dans les sélections bloquantes
            $isCancelled = ($m['annule'] ?? false) || str_contains((string)($m['Statut'] ?? $m['statut'] ?? $m['status'] ?? ''), 'Annulé');
            if ($isCancelled) {
                continue;
            }

            if (\App\Helpers\ParticipationStatus::categorize((string)$status) === 'selected' && $mid !== $manifestationId) {
                $selectedEvents[] = $m;
            }
        }

        if (empty($selectedEvents)) {
            return false;
        }

        // Helper pour calculer la plage horaire
        $getRange = function ($e) {
            $dateStr = $e['Date'] ?? $e['date'] ?? null;
            if (!$dateStr) return [0, 0];
            $start = strtotime($dateStr);
            if ($start === false) return [0, 0];
            
            $durationStr = $e['Durée_créneau'] ?? $e['duration'] ?? '2h';
            $hours = 2;
            $minutes = 0;
            if (!empty($durationStr)) {
                if (str_contains($durationStr, 'h')) {
                    $parts = explode('h', $durationStr);
                    $hours = (int)($parts[0] ?? 2);
                    $minutes = isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : 0;
                } elseif (str_contains($durationStr, 'm')) {
                    $hours = 0;
                    $minutes = (int)str_replace('m', '', $durationStr);
                }
            }
            $end = strtotime("+{$hours} hour +{$minutes} minute", $start);
            return [$start, $end];
        };

        $targetRange = $getRange($targetEvent);
        if ($targetRange[0] === 0) {
            return false;
        }

        // 5. Comparer avec les plages horaires des événements sélectionnés
        foreach ($selectedEvents as $se) {
            $seRange = $getRange($se);
            if ($seRange[0] === 0) continue;

            if ($seRange[0] < $targetRange[1] && $targetRange[0] < $seRange[1]) {
                return true;
            }
        }

        return false;
    }
}
