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
}
