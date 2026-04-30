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
        $data = AgendaService::getCrossTable();

        View::render('agenda/index.twig', [
            'joueurs'        => $data['joueurs'],
            'manifestations' => $data['manifestations'],
            'cross'          => $data['cross'],
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

        if (!empty($_GET['location'])) {
            $filters['location'] = (string) $_GET['location'];
        }

        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = (string) $_GET['date_from'];
        }

        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = (string) $_GET['date_to'];
        }

        if (!empty($_GET['type'])) {
            $filters['type'] = (string) $_GET['type'];
        }

        return $filters;
    }
}
