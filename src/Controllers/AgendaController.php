<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Services\AgendaService;
use App\Core\View;
use App\Models\Participation;

class AgendaController
{
    use NotFoundHandler;

    /**
     * Display the agenda cross-table (players × events with participation).
     *
     * Supports filtering by team, type, manifestation, location, and special filters
     * (this_week, hide_empty_players). All filters are optional and applied server-side.
     *
     * Template variables:
     * - joueurs: Associative array (id_joueur => "Nom Prénom")
     * - manifestations: Associative array of events with stats
     * - cross: Cross-table (id_joueur => {id_manifestation => status_string})
     * - filters: Applied filters (subset of available filters)
     * - filterOptions: All available filter options (for dropdown rendering)
     */
    public function index(array $params): void
    {
        $filters = $this->extractFilters();
        $view = $_GET['view'] ?? 'cards'; // Default to 'cards' view if not specified

        // Obtenir les données de la table croisée qui sert de source unique de vérité
        $data = AgendaService::getCrossTable($filters);

        if ($view === 'cards') {
            // Enrich with user status if logged in
            $userStatuses = [];
            $userId = null;
            $currentUserName = '';
            if (isset($_SESSION['LogIn']) && $_SESSION['LogIn']) {
                $userId = (int) ($_SESSION['LogInId'] ?? 0);
                if ($userId) {
                    $upcoming = Participation::getUpcomingWithUserStatus($userId);
                    foreach ($upcoming as $u) {
                        $userStatuses[$u['id_manifestation']] = $u['user_status'];
                    }

                    // Récupérer le nom/prénom exact du joueur pour le JS
                    $db = \App\Core\ExternalDatabase::get();
                    if ($db) {
                        $stmt = $db->prepare("SELECT Nom, `Prénom` FROM Joueurs WHERE id_joueur = ?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch();
                        if ($row) {
                            $currentUserName = $row['Nom'] . ' ' . $row['Prénom'];
                        }
                    }
                }
            }

            foreach ($data['manifestations'] as $mid => &$m) {
                $statusStr = $userStatuses[$mid] ?? null;
                $m['user_status'] = $statusStr;
                $m['user_status_category'] = $statusStr ? \App\Helpers\ParticipationStatus::categorize($statusStr) : null;
            }
            unset($m);

            if ($userId) {
                \App\Services\AgendaService::flagOverlappingSelected($data['manifestations'], $userId);
            }

            View::render('agenda/cards.twig', [
                'manifestations' => $data['manifestations'],
                'filters'        => $filters,
                'filterOptions'  => AgendaService::getFilterOptions(),
                'currentUserId'  => $userId,
                'currentUserName' => $currentUserName,
            ]);
            return;
        }

        // Pre-process cross table for Twig
        foreach ($data['cross'] as $jid => &$row) {
            foreach ($data['manifestations'] as $mid => $m) {
                $statusStr = $row[$mid] ?? '';
                if (is_string($statusStr) && $statusStr !== '') {
                    $status = new \App\Helpers\ParticipationStatus($statusStr);
                    $row[$mid] = [
                        'text' => $statusStr,
                        'category' => $status->getCategory(),
                        'companion_count' => $status->getCompanionCount()
                    ];
                }
            }
        }
        unset($row);

        View::render('agenda/index.twig', [
            'joueurs'        => $data['joueurs'],
            'manifestations' => $data['manifestations'],
            'cross'          => $data['cross'],
            'filters'        => $filters,
            'filterOptions'  => AgendaService::getFilterOptions(),
        ]);
    }

    /**
     * Display detailed view of a single event.
     *
     * Shows full event details including participation breakdown and statistics.
     * If event not found, renders 404 page.
     *
     * Template variables:
     * - event: Full event object including participation stats
     */
    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $event = AgendaService::getEventById($id);

        if (!$event) {
            $this->notFound();
            return;
        }

        $participationStats = AgendaService::getParticipationStats($id);
        $event['participation_stats'] = $participationStats;

        // Vérifier si l'utilisateur connecté est le capitaine pour cette manifestation
        $isCaptain = false;
        if (isset($_SESSION['LogIn']) && $_SESSION['LogIn'] === true) {
            $userId = (int)$_SESSION['LogInId'];
            $saisonActive = \App\Models\Saison::getActive();
            $captainedTeams = $saisonActive ? \App\Models\EquipeSaisonJoueur::findCaptainedTeams($userId, $saisonActive['id']) : [];

            foreach ($captainedTeams as $team) {
                $filter = $team['manifestation_filter'] ?: $team['libelle'];
                if (
                    str_contains($event['titre'] ?? '', $filter) ||
                    str_contains($event['type'] ?? '', $filter) ||
                    (isset($event['ManifestationTypée']) && str_contains($event['ManifestationTypée'], $filter))
                ) {
                    $isCaptain = true;
                    break;
                }
            }
        }

        View::render('agenda/detail.twig', [
            'event' => $event,
            'is_captain' => $isCaptain,
        ]);
    }

    /**
     * Extract and validate filters from $_GET parameters.
     *
     * String filters (lieu, type, manifestation, team) are:
     * - Only included if non-empty and not equal to "Tous"
     * - Cast to string for safety
     *
     * Boolean filters (hide_empty_players, hide_absent_unavailable, this_week) are:
     * - Included as true if their key exists in $_GET
     *
     * Invalid or "Tous" values are silently dropped, so the returned
     * array only contains active filters.
     *
     * @return array Associative array of active filters
     */
    private function extractFilters(): array
    {
        $filters = [];

        // String filters: only include if non-empty and not "Tous"
        foreach (['lieu', 'type', 'manifestation', 'team'] as $key) {
            if (!empty($_GET[$key]) && $_GET[$key] !== 'Tous') {
                $filters[$key] = (string) $_GET[$key];
            }
        }

        // Boolean filters: include as true if key exists
        foreach (['hide_empty_players', 'hide_absent_unavailable', 'this_week', 'next_week'] as $key) {
            if (!empty($_GET[$key])) {
                $filters[$key] = true;
            }
        }

        return $filters;
    }
}
