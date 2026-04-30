<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Services\AgendaService;
use App\Core\View;

class AgendaController
{
    use NotFoundHandler;

    private const ITEMS_PER_PAGE = 20;

    public function index(array $params): void
    {
        $filters = $this->extractFilters();
        $data = AgendaService::getCrossTable($filters);

        View::render('agenda/index.twig', [
            'joueurs'        => $data['joueurs'],
            'manifestations' => $data['manifestations'],
            'cross'          => $data['cross'],
            'filters'        => $filters,
            'filterOptions'  => AgendaService::getFilterOptions(),
        ]);
    }

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

    private function extractFilters(): array
    {
        $filters = [];

        if (!empty($_GET['location']) && $_GET['location'] !== 'Tous') {
            $filters['location'] = (string) $_GET['location'];
        }

        if (!empty($_GET['type']) && $_GET['type'] !== 'Tous') {
            $filters['type'] = (string) $_GET['type'];
        }

        if (!empty($_GET['manifestation']) && $_GET['manifestation'] !== 'Tous') {
            $filters['manifestation'] = (string) $_GET['manifestation'];
        }

        if (!empty($_GET['hide_empty_players'])) {
            $filters['hide_empty_players'] = true;
        }

        if (!empty($_GET['hide_absent_unavailable'])) {
            $filters['hide_absent_unavailable'] = true;
        }

        if (!empty($_GET['this_week'])) {
            $filters['this_week'] = true;
        }

        return $filters;
    }
}
