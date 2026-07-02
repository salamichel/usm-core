<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\SiteConfig;
use App\Services\GoogleAnalyticsService;
use App\Services\Logger;

class StatsController extends BaseAdminController
{
    private const CACHE_DIR = ROOT . '/cache/google_analytics';
    private const CACHE_TTL = 900; // 15 minutes en secondes

    private const PERIODS = [
        '7days'  => ['label' => '7 derniers jours', 'start' => '7daysAgo'],
        '30days' => ['label' => '30 derniers jours', 'start' => '30daysAgo'],
        '90days' => ['label' => '90 derniers jours', 'start' => '90daysAgo'],
        'year'   => ['label' => '365 derniers jours', 'start' => '365daysAgo'],
    ];

    public function index(array $params): void
    {
        Auth::require();

        $propertyId = SiteConfig::get('google_analytics_property_id');
        $serviceAccountJson = SiteConfig::get('google_analytics_service_account');

        // Cas non configuré
        if (empty($propertyId) || empty($serviceAccountJson)) {
            View::render('admin/stats.twig', [
                'not_configured' => true,
                'periods'        => self::PERIODS,
                'current_period' => '30days',
            ]);
            return;
        }

        // Validation du JSON du compte de service
        $serviceAccount = json_decode($serviceAccountJson, true);
        if (!$serviceAccount || !is_array($serviceAccount)) {
            View::render('admin/stats.twig', [
                'error'          => 'Le compte de service Google Analytics est mal configuré (format JSON invalide).',
                'periods'        => self::PERIODS,
                'current_period' => '30days',
            ]);
            return;
        }

        // Période sélectionnée
        $period = $_GET['period'] ?? '30days';
        if (!array_key_exists($period, self::PERIODS)) {
            $period = '30days';
        }

        $startDate = self::PERIODS[$period]['start'];
        $forceRefresh = !empty($_GET['force_refresh']);

        // Gestion du cache local
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }

        $cacheFile = self::CACHE_DIR . '/stats_' . $period . '.json';
        $data = null;

        if (!$forceRefresh && is_file($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            $cachedContent = file_get_contents($cacheFile);
            if ($cachedContent) {
                $data = json_decode($cachedContent, true);
            }
        }

        // Si pas de cache ou refresh forcé
        if (!$data) {
            $requestsPayload = [
                // 0. Rapport journalier (Tendance)
                [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => 'today']],
                    'dimensions' => [['name' => 'date']],
                    'metrics'    => [
                        ['name' => 'activeUsers'],
                        ['name' => 'sessions'],
                        ['name' => 'screenPageViews'],
                        ['name' => 'bounceRate'],
                        ['name' => 'averageSessionDuration']
                    ],
                    'metricAggregations' => ['TOTAL'],
                    'orderBys'   => [[
                        'dimension' => ['dimensionName' => 'date'],
                        'desc'      => false
                    ]]
                ],
                // 1. Rapport des pages les plus vues
                [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => 'today']],
                    'dimensions' => [
                        ['name' => 'pagePath'],
                        ['name' => 'pageTitle']
                    ],
                    'metrics'    => [
                        ['name' => 'screenPageViews'],
                        ['name' => 'activeUsers']
                    ],
                    'orderBys'   => [[
                        'metric' => ['metricName' => 'screenPageViews'],
                        'desc'   => true
                    ]],
                    'limit'      => 15
                ],
                // 2. Rapport des sources de trafic
                [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => 'today']],
                    'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                    'metrics'    => [['name' => 'sessions']],
                    'orderBys'   => [[
                        'metric' => ['metricName' => 'sessions'],
                        'desc'   => true
                    ]]
                ],
                // 3. Rapport des appareils
                [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => 'today']],
                    'dimensions' => [['name' => 'deviceCategory']],
                    'metrics'    => [['name' => 'activeUsers']],
                    'orderBys'   => [[
                        'metric' => ['metricName' => 'activeUsers'],
                        'desc'   => true
                    ]]
                ]
            ];

            $apiResult = GoogleAnalyticsService::fetchBatchReports($propertyId, $serviceAccount, $requestsPayload);

            if ($apiResult && isset($apiResult['reports'])) {
                $data = $this->parseReports($apiResult['reports']);
                if ($data) {
                    file_put_contents($cacheFile, json_encode($data));
                }
            } else {
                // Tenter d'utiliser un cache expiré s'il existe pour éviter un écran blanc
                if (is_file($cacheFile)) {
                    $cachedContent = file_get_contents($cacheFile);
                    if ($cachedContent) {
                        $data = json_decode($cachedContent, true);
                        View::flash('warning', 'Impossible de récupérer les statistiques en temps réel. Affichage des anciennes données en cache.');
                    }
                }
            }
        }

        if (!$data) {
            View::render('admin/stats.twig', [
                'error'          => "Échec de connexion à l'API Google Analytics. Veuillez vérifier la validité de votre compte de service et de l'ID de propriété. Consultez les logs d'erreurs pour plus de détails.",
                'periods'        => self::PERIODS,
                'current_period' => $period,
            ]);
            return;
        }

        View::render('admin/stats.twig', [
            'stats'          => $data,
            'periods'        => self::PERIODS,
            'current_period' => $period,
            'last_updated'   => is_file($cacheFile) ? filemtime($cacheFile) : time(),
        ]);
    }

    private function parseReports(array $reports): ?array
    {
        try {
            $trendReport = $reports[0] ?? [];
            $pagesReport = $reports[1] ?? [];
            $sourcesReport = $reports[2] ?? [];
            $devicesReport = $reports[3] ?? [];

            // 1. Tendance Journalière
            $trendLabels = [];
            $trendViews = [];
            $trendUsers = [];
            $trendSessions = [];

            $rows = $trendReport['rows'] ?? [];
            foreach ($rows as $row) {
                $rawDate = $row['dimensionValues'][0]['value'] ?? '';
                $dateTime = \DateTime::createFromFormat('Ymd', $rawDate);
                $dateFormatted = $dateTime ? $dateTime->format('d/m') : $rawDate;

                $trendLabels[] = $dateFormatted;
                $trendUsers[] = (int)($row['metricValues'][0]['value'] ?? 0);
                $trendSessions[] = (int)($row['metricValues'][1]['value'] ?? 0);
                $trendViews[] = (int)($row['metricValues'][2]['value'] ?? 0);
            }

            $totalsData = $trendReport['totals'][0]['metricValues'] ?? [];
            $totalUsers = (int)($totalsData[0]['value'] ?? 0);
            $totalSessions = (int)($totalsData[1]['value'] ?? 0);
            $totalViews = (int)($totalsData[2]['value'] ?? 0);
            
            $rawBounce = (float)($totalsData[3]['value'] ?? 0.0);
            $bounceRate = $rawBounce > 1.0 ? $rawBounce : $rawBounce * 100;

            $avgSecs = (float)($totalsData[4]['value'] ?? 0.0);
            $durationMin = (int)floor($avgSecs / 60);
            $durationSec = (int)($avgSecs % 60);
            $formattedDuration = sprintf("%02d:%02d", $durationMin, $durationSec);

            // 2. Top Pages
            $pages = [];
            $pagesRows = $pagesReport['rows'] ?? [];
            foreach ($pagesRows as $row) {
                $pages[] = [
                    'path'  => $row['dimensionValues'][0]['value'] ?? '',
                    'title' => $row['dimensionValues'][1]['value'] ?? '',
                    'views' => (int)($row['metricValues'][0]['value'] ?? 0),
                    'users' => (int)($row['metricValues'][1]['value'] ?? 0),
                ];
            }

            // 3. Traffic Sources
            $sources = [];
            $sourcesRows = $sourcesReport['rows'] ?? [];
            foreach ($sourcesRows as $row) {
                $sources[] = [
                    'name'     => $row['dimensionValues'][0]['value'] ?? 'Direct / Autre',
                    'sessions' => (int)($row['metricValues'][0]['value'] ?? 0),
                ];
            }
            // Calcul des pourcentages des sources
            foreach ($sources as &$src) {
                $src['pct'] = $totalSessions > 0 ? round(($src['sessions'] / $totalSessions) * 100, 1) : 0;
            }
            unset($src);

            // 4. Appareils
            $devices = [];
            $devicesRows = $devicesReport['rows'] ?? [];
            foreach ($devicesRows as $row) {
                $devices[] = [
                    'name'  => $row['dimensionValues'][0]['value'] ?? 'Inconnu',
                    'users' => (int)($row['metricValues'][0]['value'] ?? 0),
                ];
            }
            // Calcul des pourcentages des appareils
            foreach ($devices as &$dev) {
                $dev['pct'] = $totalUsers > 0 ? round(($dev['users'] / $totalUsers) * 100, 1) : 0;
                $dev['name'] = match(strtolower($dev['name'])) {
                    'desktop' => 'Ordinateur',
                    'mobile'  => 'Mobile',
                    'tablet'  => 'Tablette',
                    'smarttv' => 'Téléviseur',
                    default   => ucfirst($dev['name'])
                };
            }
            unset($dev);

            return [
                'trend' => [
                    'labels'   => $trendLabels,
                    'views'    => $trendViews,
                    'users'    => $trendUsers,
                    'sessions' => $trendSessions,
                ],
                'totals' => [
                    'users'       => $totalUsers,
                    'sessions'    => $totalSessions,
                    'views'       => $totalViews,
                    'bounce_rate' => round($bounceRate, 1),
                    'duration'    => $formattedDuration,
                ],
                'pages'   => $pages,
                'sources' => $sources,
                'devices' => $devices,
            ];
        } catch (\Throwable $e) {
            Logger::errors()->error('GA4: Exception lors du parsing du rapport : ' . $e->getMessage());
            return null;
        }
    }
}
