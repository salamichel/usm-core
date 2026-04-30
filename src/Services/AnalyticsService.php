<?php
declare(strict_types=1);

namespace App\Services;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;

class AnalyticsService
{
    private ?BetaAnalyticsDataClient $client = null;
    private string $propertyId;

    public function __construct(string $propertyId)
    {
        $this->propertyId = $propertyId;
    }

    private function getClient(): ?BetaAnalyticsDataClient
    {
        if (!$this->propertyId) {
            return null;
        }

        if ($this->client === null) {
            try {
                $credentialsPath = getenv('GA_CREDENTIALS_PATH');
                if ($credentialsPath && file_exists($credentialsPath)) {
                    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);
                }
                $this->client = new BetaAnalyticsDataClient();
            } catch (\Throwable $e) {
                Logger::errors()->error('GA client initialization failed', ['error' => $e->getMessage()]);
                return null;
            }
        }

        return $this->client;
    }

    /**
     * Get total pageviews for a date range
     */
    public function getPageViews(string $startDate = '7daysAgo', string $endDate = 'today'): int
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return 0;
            }

            $response = $client->runReport(
                new RunReportRequest([
                    'property' => 'properties/' . $this->propertyId,
                    'date_ranges' => [new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])],
                    'metrics' => [new Metric(['name' => 'screenPageViews'])],
                ])
            );

            $rows = $response->getRows();
            if (count($rows) === 0) {
                return 0;
            }

            return (int) $rows[0]->getMetricValues()[0]->getValue();
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to get pageviews', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get unique users for a date range
     */
    public function getActiveUsers(string $startDate = '7daysAgo', string $endDate = 'today'): int
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return 0;
            }

            $response = $client->runReport(
                new RunReportRequest([
                    'property' => 'properties/' . $this->propertyId,
                    'date_ranges' => [new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])],
                    'metrics' => [new Metric(['name' => 'activeUsers'])],
                ])
            );

            $rows = $response->getRows();
            if (count($rows) === 0) {
                return 0;
            }

            return (int) $rows[0]->getMetricValues()[0]->getValue();
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to get active users', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get top pages by pageviews
     */
    public function getTopPages(int $limit = 5, string $startDate = '7daysAgo', string $endDate = 'today'): array
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return [];
            }

            $response = $client->runReport(
                new RunReportRequest([
                    'property' => 'properties/' . $this->propertyId,
                    'date_ranges' => [new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])],
                    'dimensions' => [new Dimension(['name' => 'pagePath'])],
                    'metrics' => [new Metric(['name' => 'screenPageViews'])],
                    'order_bys' => [new OrderBy([
                        'desc' => true,
                        'dimension' => new DimensionOrderBy(['dimension_name' => 'screenPageViews']),
                    ])],
                    'limit' => $limit,
                ])
            );

            $pages = [];
            foreach ($response->getRows() as $row) {
                $pages[] = [
                    'path' => $row->getDimensionValues()[0]->getValue(),
                    'views' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return $pages;
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to get top pages', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get top referrers/sources
     */
    public function getTopSources(int $limit = 5, string $startDate = '7daysAgo', string $endDate = 'today'): array
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return [];
            }

            $response = $client->runReport(
                new RunReportRequest([
                    'property' => 'properties/' . $this->propertyId,
                    'date_ranges' => [new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])],
                    'dimensions' => [new Dimension(['name' => 'sessionSource'])],
                    'metrics' => [new Metric(['name' => 'activeUsers'])],
                    'order_bys' => [new OrderBy([
                        'desc' => true,
                        'dimension' => new DimensionOrderBy(['dimension_name' => 'activeUsers']),
                    ])],
                    'limit' => $limit,
                ])
            );

            $sources = [];
            foreach ($response->getRows() as $row) {
                $sources[] = [
                    'source' => $row->getDimensionValues()[0]->getValue(),
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return $sources;
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to get top sources', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get events count (form submissions, downloads, etc.)
     */
    public function getEventCount(string $eventName, string $startDate = '7daysAgo', string $endDate = 'today'): int
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return 0;
            }

            $response = $client->runReport(
                new RunReportRequest([
                    'property' => 'properties/' . $this->propertyId,
                    'date_ranges' => [new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])],
                    'dimensions' => [new Dimension(['name' => 'eventName'])],
                    'metrics' => [new Metric(['name' => 'eventCount'])],
                    'dimension_filter' => null, // Could be enhanced with filters
                ])
            );

            foreach ($response->getRows() as $row) {
                if ($row->getDimensionValues()[0]->getValue() === $eventName) {
                    return (int) $row->getMetricValues()[0]->getValue();
                }
            }

            return 0;
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to get event count', ['error' => $e->getMessage(), 'event' => $eventName]);
            return 0;
        }
    }

    /**
     * Get bounce rate (average session duration > 0)
     */
    public function getBounceRate(string $startDate = '7daysAgo', string $endDate = 'today'): string
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return '0%';
            }

            $response = $client->runReport(
                new RunReportRequest([
                    'property' => 'properties/' . $this->propertyId,
                    'date_ranges' => [new DateRange([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])],
                    'metrics' => [new Metric(['name' => 'bounceRate'])],
                ])
            );

            $rows = $response->getRows();
            if (count($rows) === 0) {
                return '0%';
            }

            $rate = (float) $rows[0]->getMetricValues()[0]->getValue();
            return number_format($rate, 1) . '%';
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to get bounce rate', ['error' => $e->getMessage()]);
            return '0%';
        }
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->propertyId) && !empty(getenv('GA_CREDENTIALS_PATH'));
    }

    /**
     * Get configuration status message
     */
    public function getConfigStatus(): string
    {
        if (empty($this->propertyId)) {
            return 'GA_PROPERTY_ID non configuré';
        }
        if (empty(getenv('GA_CREDENTIALS_PATH'))) {
            return 'GA_CREDENTIALS_PATH non configuré';
        }
        if (!file_exists((string)getenv('GA_CREDENTIALS_PATH'))) {
            return 'Fichier credentials.json introuvable';
        }
        return 'Configuré et prêt';
    }
}
