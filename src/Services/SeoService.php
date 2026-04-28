<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SiteConfig;

/**
 * SEO helper service: meta description / image / canonical URL generation.
 *
 * All methods are static. Consumed by controllers to populate PageMetadata DTO.
 */
class SeoService
{
    public const DESCRIPTION_MAX_LENGTH = 160;
    public const ALT_TEXT_MAX_LENGTH    = 125;

    /**
     * Build a meta description from explicit text (excerpt) or fallback HTML content.
     * Strips HTML, normalizes whitespace, truncates at word boundary.
     */
    public static function description(?string $primary, ?string $fallbackHtml = null, ?string $defaultText = null): string
    {
        $text = trim((string)($primary ?? ''));

        if ($text === '' && $fallbackHtml !== null) {
            $text = trim(strip_tags($fallbackHtml));
        }

        if ($text === '') {
            $text = trim((string)($defaultText ?? SiteConfig::get('club_tagline') ?? 'Union Sportive Miosienne Volley-Ball'));
        }

        // Normalize whitespace (newlines, tabs, multiple spaces → single space)
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return self::truncate($text, self::DESCRIPTION_MAX_LENGTH);
    }

    /**
     * Truncate at the last word boundary before $maxLength, append ellipsis.
     */
    public static function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        $cut = mb_substr($text, 0, $maxLength);
        // Step back to last space to avoid cutting words
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.5) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }
        return rtrim($cut, " \t\n\r\0\x0B,.;:") . '…';
    }

    /**
     * Build an absolute URL from a path. Uses BASE_URL from config.
     */
    public static function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Resolve absolute image URL for a photo filename (in assets/uploads/).
     */
    public static function uploadUrl(string $filename): string
    {
        return self::absoluteUrl('/assets/uploads/' . ltrim($filename, '/'));
    }

    /**
     * Pick an OG image: provided photo, first photo of a list, or null.
     *
     * @param array|null $primary  Photo array with 'filename' key, or null.
     * @param array      $fallback List of photo arrays (uses first one).
     */
    public static function pickOgImage(?array $primary, array $fallback = []): ?string
    {
        if ($primary && !empty($primary['filename'])) {
            return self::uploadUrl((string)$primary['filename']);
        }
        if (!empty($fallback) && !empty($fallback[0]['filename'])) {
            return self::uploadUrl((string)$fallback[0]['filename']);
        }
        return null;
    }

    /**
     * Sanitize alt text: strip HTML, collapse whitespace, truncate.
     */
    public static function altText(?string $text, ?string $fallback = null): string
    {
        $value = trim((string)($text ?? ''));
        if ($value === '') {
            $value = trim((string)($fallback ?? ''));
        }
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return self::truncate($value, self::ALT_TEXT_MAX_LENGTH);
    }

    /**
     * Get the canonical URL for the current request (without query string).
     */
    public static function currentCanonical(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        return self::absoluteUrl($path);
    }

    /**
     * Build a page title with the site name suffix.
     */
    public static function title(string $primary, ?string $siteName = null): string
    {
        $site = trim((string)($siteName ?? SiteConfig::get('club_name') ?? 'USM Volley'));
        $primary = trim($primary);
        if ($primary === '' || $primary === $site) {
            return $site;
        }
        return $primary . ' — ' . $site;
    }
}
