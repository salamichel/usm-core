<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Services\AnalyticsService;

class AnalyticsController
{
    public function dashboard(array $params): void
    {
        Auth::require();

        $propertyId = getenv('GA_PROPERTY_ID');
        $analytics = new AnalyticsService((string)$propertyId);

        // Determine date range from query parameter
        $range = $_GET['range'] ?? '7days';
        $ranges = [
            '7days' => ['7daysAgo', 'today', '7 derniers jours'],
            '30days' => ['30daysAgo', 'today', '30 derniers jours'],
            '90days' => ['90daysAgo', 'today', '90 derniers jours'],
            'month' => ['firstDayOfMonth', 'today', 'Ce mois-ci'],
        ];

        [$startDate, $endDate, $rangeLabel] = $ranges[$range] ?? $ranges['7days'];

        // Fetch analytics data
        $data = [
            'analytics_configured' => $analytics->isConfigured(),
            'config_status' => $analytics->getConfigStatus(),
            'selected_range' => $range,
            'range_label' => $rangeLabel,
            'ranges' => $ranges,

            'pageviews' => $analytics->getPageViews($startDate, $endDate),
            'active_users' => $analytics->getActiveUsers($startDate, $endDate),
            'bounce_rate' => $analytics->getBounceRate($startDate, $endDate),

            'top_pages' => $analytics->getTopPages(5, $startDate, $endDate),
            'top_sources' => $analytics->getTopSources(5, $startDate, $endDate),

            'form_submissions' => $analytics->getEventCount('form_submit', $startDate, $endDate),
            'contact_form_submissions' => $analytics->getEventCount('contact_form_submit', $startDate, $endDate),
            'downloads' => $analytics->getEventCount('file_download', $startDate, $endDate),
            'external_links' => $analytics->getEventCount('click_external_link', $startDate, $endDate),
        ];

        View::render('admin/analytics.twig', $data);
    }
}
