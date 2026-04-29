<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\PageStatique;
use App\Models\EquipeConfig;
use App\Models\Saison;
use App\Models\EquipeSaison;
use App\Models\EquipeSaisonJoueur;

/**
 * XML Sitemap generator for search engines.
 *
 * Discovers all public URLs: home, blog posts, pages, teams.
 * Returns array of entries, each with 'loc' and optional 'lastmod', 'changefreq', 'priority'.
 */
class SitemapService
{
    /**
     * Generate all sitemap entries.
     *
     * @return array<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    public static function entries(): array
    {
        $entries = [];

        $entries[] = [
            'loc'        => SeoService::absoluteUrl('/'),
            'changefreq' => 'daily',
            'priority'   => '1.0',
        ];

        $entries[] = [
            'loc'        => SeoService::absoluteUrl('/blog'),
            'changefreq' => 'daily',
            'priority'   => '0.9',
        ];

        $entries[] = [
            'loc'        => SeoService::absoluteUrl('/equipes'),
            'changefreq' => 'weekly',
            'priority'   => '0.9',
        ];

        $entries = array_merge($entries, self::blogEntries());
        $entries = array_merge($entries, self::pageEntries());
        $entries = array_merge($entries, self::teamEntries());

        return $entries;
    }

    private static function blogEntries(): array
    {
        $entries = [];
        $posts = Post::allPublished();

        foreach ($posts as $post) {
            $entries[] = [
                'loc'        => SeoService::absoluteUrl('/blog/' . $post['slug']),
                'lastmod'    => isset($post['updated_at']) ? self::formatDate($post['updated_at']) : self::formatDate($post['published_at'] ?? $post['created_at']),
                'changefreq' => 'monthly',
                'priority'   => '0.7',
            ];
        }

        return $entries;
    }

    private static function pageEntries(): array
    {
        $entries = [];
        $pages = PageStatique::all();

        foreach ($pages as $page) {
            $entries[] = [
                'loc'        => SeoService::absoluteUrl('/p/' . $page['slug']),
                'lastmod'    => isset($page['updated_at']) ? self::formatDate($page['updated_at']) : self::formatDate($page['created_at']),
                'changefreq' => 'monthly',
                'priority'   => '0.7',
            ];
        }

        return $entries;
    }

    private static function teamEntries(): array
    {
        $entries = [];
        $saison = Saison::getActive();
        if (!$saison) {
            return $entries;
        }

        $equipes = EquipeConfig::all();
        foreach ($equipes as $eq) {
            if (!$eq['is_active']) continue;
            $es = EquipeSaison::findBySaisonAndEquipe($saison['id'], $eq['id']);
            if (!$es) continue;
            $memberCount = EquipeSaisonJoueur::countByEquipeSaison($es['id']);
            if ($memberCount === 0) continue;

            $entries[] = [
                'loc'        => SeoService::absoluteUrl('/equipes/' . $eq['slug']),
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ];
        }

        return $entries;
    }

    private static function formatDate(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) return date('Y-m-d');
        return date('Y-m-d', $ts);
    }

    /**
     * Render XML sitemap from entries array.
     */
    public static function renderXml(array $entries): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= "    <lastmod>" . htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
            }
            if (!empty($entry['changefreq'])) {
                $xml .= "    <changefreq>" . htmlspecialchars($entry['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
            }
            if (!empty($entry['priority'])) {
                $xml .= "    <priority>" . htmlspecialchars($entry['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>";
        return $xml;
    }
}
