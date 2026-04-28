<?php
declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Page metadata for SEO (title, description, OG, Twitter, canonical, JSON-LD).
 * Pass an instance to View::render() under the 'meta' key.
 */
final class PageMetadata
{
    /**
     * @param string      $title         <title> tag content (also used for og:title / twitter:title).
     * @param string      $description   <meta name="description"> (also og/twitter description). Truncated at 160 chars in template.
     * @param string|null $canonical     Absolute canonical URL. If null, base.twig falls back to current URL.
     * @param string|null $ogImage       Absolute URL to OG/Twitter image (1200x630 ideal).
     * @param string      $ogType        Open Graph type: 'website', 'article', etc.
     * @param string      $robots        meta robots content (e.g. 'index, follow', 'noindex, nofollow').
     * @param array       $jsonLd        Array of JSON-LD schemas (each is an associative array). Rendered in base.twig.
     * @param array       $breadcrumbs   List of [label, url] tuples for BreadcrumbList schema.
     * @param string|null $articlePublishedAt ISO 8601 publish date (for og:article:published_time).
     * @param string|null $articleModifiedAt  ISO 8601 modified date.
     */
    public function __construct(
        public readonly string  $title,
        public readonly string  $description,
        public readonly ?string $canonical = null,
        public readonly ?string $ogImage = null,
        public readonly string  $ogType = 'website',
        public readonly string  $robots = 'index, follow',
        public readonly array   $jsonLd = [],
        public readonly array   $breadcrumbs = [],
        public readonly ?string $articlePublishedAt = null,
        public readonly ?string $articleModifiedAt = null,
    ) {}
}
