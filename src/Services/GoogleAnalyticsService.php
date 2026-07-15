<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Logger;

class GoogleAnalyticsService
{
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Échange la clé JSON du compte de service contre un jeton d'accès OAuth2.
     */
    public static function getAccessToken(array $serviceAccount): ?string
    {
        try {
            $privateKey = $serviceAccount['private_key'] ?? null;
            $clientEmail = $serviceAccount['client_email'] ?? null;
            $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            if (!$privateKey || !$clientEmail) {
                Logger::errors()->error('GA4: Clé privée ou client_email manquant dans la config du compte de service.');
                return null;
            }

            // JWT Header
            $header = self::base64UrlEncode((string)json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT'
            ]));

            // JWT Payload (expire dans 1 heure)
            $now = time();
            $payload = self::base64UrlEncode((string)json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'aud' => $tokenUri,
                'exp' => $now + 3600,
                'iat' => $now
            ]));

            // Signature du JWT
            $signatureInput = $header . '.' . $payload;
            $signature = '';
            
            // Initialisation de la clé privée OpenSSL
            $pkeyId = openssl_pkey_get_private($privateKey);
            if (!$pkeyId) {
                Logger::errors()->error('GA4: Clé privée invalide (impossible de la lire via OpenSSL).');
                return null;
            }

            if (!openssl_sign($signatureInput, $signature, $pkeyId, OPENSSL_ALGO_SHA256)) {
                Logger::errors()->error('GA4: Échec de signature openssl_sign.');
                return null;
            }
            
            // Libération de la ressource clé sous PHP < 8.0, sans effet sous PHP 8.0+
            if (PHP_VERSION_ID < 80000) {
                openssl_free_key($pkeyId);
            }

            $signatureEncoded = self::base64UrlEncode($signature);
            $jwt = $signatureInput . '.' . $signatureEncoded;

            // Envoi de la requête POST pour obtenir le Token d'accès
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tokenUri);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                Logger::errors()->error("GA4: Échec d'échange de token ({$httpCode}). Erreur curl: {$curlError}. Réponse: {$response}");
                return null;
            }

            $data = json_decode((string)$response, true);
            return $data['access_token'] ?? null;

        } catch (\Throwable $e) {
            Logger::errors()->error('GA4: Exception lors de la génération du token d\'accès : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère un rapport groupé (batch) depuis GA4.
     */
    public static function fetchBatchReports(string $propertyId, array $serviceAccount, array $requestsPayload): ?array
    {
        $accessToken = self::getAccessToken($serviceAccount);
        if (!$accessToken) {
            return null;
        }

        try {
            $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:batchRunReports";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)json_encode(['requests' => $requestsPayload]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                Logger::errors()->error("GA4: Erreur batchRunReports ({$httpCode}). Erreur curl: {$curlError}. Réponse: {$response}");
                return null;
            }

            return json_decode((string)$response, true);
        } catch (\Throwable $e) {
            Logger::errors()->error('GA4: Exception lors du batchRunReports : ' . $e->getMessage());
            return null;
        }
    }

    public const PERIODS = [
        '7days'  => ['label' => '7 derniers jours', 'start' => '7daysAgo'],
        '30days' => ['label' => '30 derniers jours', 'start' => '30daysAgo'],
        '90days' => ['label' => '90 derniers jours', 'start' => '90daysAgo'],
        'year'   => ['label' => '365 derniers jours', 'start' => '365daysAgo'],
    ];

    private const CACHE_DIR = ROOT . '/cache/google_analytics';
    private const CACHE_TTL = 900; // 15 minutes en secondes

    /**
     * Récupère les statistiques de visites pour une période donnée.
     * Gère la configuration, le cache local et la connexion API GA4.
     */
    public static function getStatsForPeriod(string $period, bool $forceRefresh = false): array
    {
        $propertyId = \App\Models\SiteConfig::get('google_analytics_property_id');
        $serviceAccountJson = \App\Models\SiteConfig::get('google_analytics_service_account');

        // Cas non configuré
        if (empty($propertyId) || empty($serviceAccountJson)) {
            return [
                'success'        => false,
                'not_configured' => true,
                'error'          => null,
                'data'           => null,
                'last_updated'   => null,
            ];
        }

        // Validation du JSON du compte de service
        $serviceAccount = json_decode($serviceAccountJson, true);
        if (!$serviceAccount || !is_array($serviceAccount)) {
            return [
                'success'        => false,
                'not_configured' => false,
                'error'          => 'Le compte de service Google Analytics est mal configuré (format JSON invalide).',
                'data'           => null,
                'last_updated'   => null,
            ];
        }

        if (!array_key_exists($period, self::PERIODS)) {
            $period = '30days';
        }

        $startDate = self::PERIODS[$period]['start'];

        // Gestion du cache local
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0755, true);
        }

        $cacheFile = self::CACHE_DIR . '/stats_' . $period . '.json';
        $data = null;
        $lastUpdated = null;

        if (!$forceRefresh && is_file($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            $cachedContent = file_get_contents($cacheFile);
            if ($cachedContent) {
                $data = json_decode($cachedContent, true);
                $lastUpdated = filemtime($cacheFile);
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

            $apiResult = self::fetchBatchReports($propertyId, $serviceAccount, $requestsPayload);

            if ($apiResult && isset($apiResult['reports'])) {
                $data = self::parseReports($apiResult['reports']);
                if ($data && is_dir(self::CACHE_DIR) && is_writable(self::CACHE_DIR)) {
                    @file_put_contents($cacheFile, json_encode($data));
                    $lastUpdated = time();
                }
            } else {
                // Tenter d'utiliser un cache expiré s'il existe pour éviter un écran blanc
                if (is_file($cacheFile)) {
                    $cachedContent = file_get_contents($cacheFile);
                    if ($cachedContent) {
                        $data = json_decode($cachedContent, true);
                        $lastUpdated = filemtime($cacheFile);
                        return [
                            'success'        => true,
                            'not_configured' => false,
                            'error'          => 'Impossible de récupérer les statistiques en temps réel. Affichage des anciennes données en cache.',
                            'data'           => $data,
                            'last_updated'   => $lastUpdated,
                        ];
                    }
                }
            }
        }

        if (!$data) {
            return [
                'success'        => false,
                'not_configured' => false,
                'error'          => "Échec de connexion à l'API Google Analytics. Veuillez vérifier la validité de votre compte de service et de l'ID de propriété. Consultez les logs d'erreurs pour plus de détails.",
                'data'           => null,
                'last_updated'   => null,
            ];
        }

        return [
            'success'        => true,
            'not_configured' => false,
            'error'          => null,
            'data'           => $data,
            'last_updated'   => $lastUpdated ?? time(),
        ];
    }

    /**
     * Analyse et formate les rapports GA4 bruts.
     */
    private static function parseReports(array $reports): ?array
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
            $durationSec = ((int)$avgSecs) % 60;
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
