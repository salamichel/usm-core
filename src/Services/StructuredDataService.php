<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SiteConfig;

/**
 * JSON-LD structured data generators (schema.org).
 *
 * Each method returns an associative array ready to be JSON-encoded
 * inside <script type="application/ld+json"> in templates.
 */
class StructuredDataService
{
    /**
     * SportsClub / Organization — for the whole site (rendered on every page).
     */
    public static function sportsClub(): array
    {
        $name        = SiteConfig::get('club_name') ?? 'USM Volley';
        $tagline     = SiteConfig::get('club_tagline') ?? '';
        $address     = SiteConfig::get('address') ?? '';
        $email       = SiteConfig::get('email') ?? '';
        $phone       = SiteConfig::get('phone') ?? '';
        $facebook    = SiteConfig::get('facebook_url') ?? '';
        $instagram   = SiteConfig::get('instagram_url') ?? '';

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'SportsClub',
            'name'     => $name,
            'url'      => SeoService::absoluteUrl('/'),
            'sport'    => 'Volleyball',
        ];

        if ($tagline !== '') {
            $data['description'] = $tagline;
        }

        if ($address !== '') {
            $data['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
                'addressCountry'  => 'FR',
            ];
        }

        $contactPoint = ['@type' => 'ContactPoint', 'contactType' => 'customer support'];
        if ($email !== '') $contactPoint['email']     = $email;
        if ($phone !== '') $contactPoint['telephone'] = $phone;
        if (count($contactPoint) > 2) {
            $data['contactPoint'] = $contactPoint;
        }

        $sameAs = array_values(array_filter([$facebook, $instagram]));
        if ($sameAs) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * BlogPosting / Article — for blog post detail pages.
     *
     * @param array       $post   Post row (title, excerpt, content, slug, published_at, updated_at).
     * @param string|null $imgUrl Absolute image URL (cover photo).
     */
    public static function blogPosting(array $post, ?string $imgUrl = null): array
    {
        $clubName    = SiteConfig::get('club_name') ?? 'USM Volley';
        $description = SeoService::description(
            $post['excerpt'] ?? null,
            $post['content'] ?? null,
        );

        $data = [
            '@context'      => 'https://schema.org',
            '@type'         => 'BlogPosting',
            'headline'      => $post['title'] ?? '',
            'description'   => $description,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => SeoService::absoluteUrl('/blog/' . ($post['slug'] ?? '')),
            ],
            'author' => [
                '@type' => 'Organization',
                'name'  => $clubName,
                'url'   => SeoService::absoluteUrl('/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => $clubName,
                'url'   => SeoService::absoluteUrl('/'),
            ],
        ];

        if (!empty($post['published_at'])) {
            $data['datePublished'] = self::toIso8601($post['published_at']);
        }
        if (!empty($post['updated_at'])) {
            $data['dateModified'] = self::toIso8601($post['updated_at']);
        }
        if ($imgUrl) {
            $data['image'] = $imgUrl;
        }

        return $data;
    }

    /**
     * BreadcrumbList — list of [name, url] tuples.
     * Returns null if items is empty (safe for Twig {% if %}).
     *
     * @param array $items Each item: ['name' => string, 'url' => string]
     */
    public static function breadcrumbs(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        $listItems = [];
        $position = 1;
        foreach ($items as $item) {
            if (!isset($item['name'], $item['url'])) continue;
            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }

        if (empty($listItems)) {
            return null;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }

    /**
     * SportsTeam — for team detail pages.
     *
     * @param array       $equipe   EquipeConfig row (libelle, etc.).
     * @param string|null $imgUrl   Cover photo URL.
     * @param string      $url      Canonical URL of the team page.
     */
    public static function sportsTeam(array $equipe, ?string $imgUrl, string $url): array
    {
        $clubName = SiteConfig::get('club_name') ?? 'USM Volley';

        $data = [
            '@context'      => 'https://schema.org',
            '@type'         => 'SportsTeam',
            'name'          => ($equipe['libelle'] ?? '') . ' — ' . $clubName,
            'sport'         => 'Volleyball',
            'url'           => $url,
            'parentOrganization' => [
                '@type' => 'SportsClub',
                'name'  => $clubName,
                'url'   => SeoService::absoluteUrl('/'),
            ],
        ];

        if ($imgUrl) {
            $data['image'] = $imgUrl;
            $data['logo']  = $imgUrl;
        }

        return $data;
    }

    /**
     * WebSite — adds SearchAction (homepage only).
     */
    public static function website(): array
    {
        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'WebSite',
            'name'          => SiteConfig::get('club_name') ?? 'USM Volley',
            'url'           => SeoService::absoluteUrl('/'),
            'inLanguage'    => 'fr-FR',
        ];
    }

    private static function toIso8601(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        return date('c', $ts);
    }
}
