<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Services\GoogleAnalyticsService;

class StatsController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();

        // Période sélectionnée
        $period = $_GET['period'] ?? '30days';
        if (!array_key_exists($period, GoogleAnalyticsService::PERIODS)) {
            $period = '30days';
        }

        $forceRefresh = !empty($_GET['force_refresh']);

        $result = GoogleAnalyticsService::getStatsForPeriod($period, $forceRefresh);

        if ($result['not_configured']) {
            View::render('admin/stats.twig', [
                'not_configured' => true,
                'periods'        => GoogleAnalyticsService::PERIODS,
                'current_period' => $period,
            ]);
            return;
        }

        if (!$result['success']) {
            View::render('admin/stats.twig', [
                'error'          => $result['error'],
                'periods'        => GoogleAnalyticsService::PERIODS,
                'current_period' => $period,
            ]);
            return;
        }

        // Si on a des données de cache obsolètes mais récupérées suite à une erreur d'API
        if ($result['error']) {
            View::flash('warning', $result['error']);
        }

        View::render('admin/stats.twig', [
            'stats'          => $result['data'],
            'periods'        => GoogleAnalyticsService::PERIODS,
            'current_period' => $period,
            'last_updated'   => $result['last_updated'],
        ]);
    }
}
