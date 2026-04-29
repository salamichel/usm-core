<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Services\AgendaService;
use App\Services\Pagination;
use App\Core\View;

class AgendaController
{
    use NotFoundHandler;

    private const ITEMS_PER_PAGE = 20;

    public function index(array $params): void
    {
        $filters = $this->extractFilters();
        $page = (int) ($_GET['page'] ?? 1);
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        $events = AgendaService::getAllManifestations($filters, $offset, self::ITEMS_PER_PAGE);
        $totalCount = AgendaService::countManifestations($filters);

        $pagination = new Pagination($totalCount, self::ITEMS_PER_PAGE, $page);
        $availableFilters = AgendaService::getAvailableFilters();

        View::render('agenda/index', [
            'events' => $events,
            'filters' => $filters,
            'availableFilters' => $availableFilters,
            'pagination' => $pagination,
            'currentPage' => $page,
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

        View::render('agenda/detail', [
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
