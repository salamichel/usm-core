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
        $view = $_GET['view'] ?? 'table';

        if ($view === 'cards') {
            $manifestations = AgendaService::getAllManifestations($filters);
            $ids = array_map(fn($m) => $m['id'], $manifestations);
            
            // Enrich with stats
            $stats = AgendaService::getParticipationStatsBatch($ids);
            
            // Enrich with user status if logged in
            $userStatuses = [];
            if (isset($_SESSION['LogIn']) && $_SESSION['LogIn']) {
                $userId = $_SESSION['Member']['id_joueur'] ?? null;
                if ($userId) {
                    $upcoming = Participation::getUpcomingWithUserStatus((int)$userId);
                    foreach ($upcoming as $u) {
                        $userStatuses[$u['id_manifestation']] = $u['user_status'];
                    }
                }
            }

            foreach ($manifestations as &$m) {
                $id = $m['id'];
                
                // Normalise la structure pour correspondre à celle attendue par le partial _event_card.twig
                $m['id_manifestation'] = $m['id'];
                $m['is_match'] = (bool)(strpos($m['type'] ?? '', 'Match') !== false);
                $m['type_simple'] = $m['type'];
                // Dans getAllManifestations, date_display contient déjà la date formatée. 
                // Mais le partial utilise m.Date|date('d/m/Y') si c'est un DateTime, ou le formate directement.
                // Passons la valeur brute ou simulons. En PHP, on peut laisser passer la chaîne ou adapter :
                $m['Date'] = $m['date_display']; 
                $m['Durée_créneau'] = $m['duration'];
                $m['Lieu'] = $m['location'];
                $m['titre'] = $m['title'];
                
                if (isset($stats[$id])) {
                    $m['nb_present'] = $stats[$id]['present'] ?? 0;
                    $m['nb_absent'] = $stats[$id]['absent'] ?? 0;
                    $m['nb_disponible'] = $stats[$id]['available'] ?? 0;
                    $m['nb_si_besoin'] = $stats[$id]['available_if_needed'] ?? $stats[$id]['unknown'] ?? 0; // fallback stats
                    $m['nb_indisponible'] = $stats[$id]['unavailable'] ?? 0;
                }
                $m['user_status'] = $userStatuses[$id] ?? null;
            }

            View::render('agenda/cards.twig', [
                'manifestations' => $manifestations,
                'filters'        => $filters,
            ]);
            return;
        }

        $data = AgendaService::getCrossTable($filters);

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
        $event['participation'] = $participationStats;

        View::render('agenda/detail.twig', [
            'event' => $event,
        ]);
    }

    /**
     * Extract and validate filters from $_GET parameters.
     *
     * String filters (location, type, manifestation, team) are:
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
        foreach (['location', 'type', 'manifestation', 'team'] as $key) {
            if (!empty($_GET[$key]) && $_GET[$key] !== 'Tous') {
                $filters[$key] = (string) $_GET[$key];
            }
        }

        // Boolean filters: include as true if key exists
        foreach (['hide_empty_players', 'hide_absent_unavailable', 'this_week'] as $key) {
            if (!empty($_GET[$key])) {
                $filters[$key] = true;
            }
        }

        return $filters;
    }
}