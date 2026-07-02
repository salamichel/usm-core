<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SitemapService;
use App\Services\SeoService;

/**
 * Public SEO routes: sitemap.xml and robots.txt.
 */
class SitemapController
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $sitemapUrl = SeoService::absoluteUrl('/sitemap.xml');

        echo "# robots.txt — " . (defined('BASE_URL') ? BASE_URL : 'USM Volley') . "\n";
        echo "# https://www.robotstxt.org/\n\n";
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /agenda/\n";
        echo "Disallow: /assets/uploads/*.pdf$\n";
        echo "Crawl-delay: 1\n\n";

        // Block aggressive AI crawlers (optional — can be customized)
        $blockedAgents = [
            'GPTBot',
            'ChatGPT-User',
            'Google-Extended',
            'PerplexityBot',
            'ClaudeBot',
            'anthropic-ai',
            'CCBot',
        ];
        foreach ($blockedAgents as $agent) {
            echo "User-agent: " . $agent . "\n";
            echo "Disallow: /\n\n";
        }

        echo "Sitemap: " . $sitemapUrl . "\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');

        $entries = SitemapService::entries();
        echo SitemapService::renderXml($entries);
    }
}
