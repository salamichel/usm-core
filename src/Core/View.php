<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\MenuItem;
use App\Models\SiteConfig;
use App\Models\ContactMessage;
use App\Services\ContentRenderer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class View
{
    private static ?Environment $twig = null;

    public static function getInstance(): Environment
    {
        if (self::$twig === null) {
            $theme = self::resolveTheme();
            $loader = new FilesystemLoader(ROOT . '/templates/' . $theme);
            $twig   = new Environment($loader, [
                'cache'       => APP_DEBUG ? false : ROOT . '/cache/twig',
                'auto_reload' => true,
            ]);

            // Global variables available in every template
            $twig->addGlobal('menu_items', MenuItem::getTree());
            $twig->addGlobal('base_url',   BASE_URL);
            $twig->addGlobal('admin_logged_in', Auth::check());
            $twig->addGlobal('flash', self::getFlash());
            $twig->addGlobal('site_config', SiteConfig::all());
            $twig->addGlobal('csrf_token', CsrfToken::generate());
            $twig->addGlobal('current_path', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
            $twig->addGlobal('_POST', $_POST);
            $twig->addGlobal('theme', $theme);

            // Contact stats for admin menu badge
            if (Auth::check()) {
                try {
                    $twig->addGlobal('contact_stats', [
                        'new' => \App\Models\Contact::countByStatus('new'),
                    ]);
                    $twig->addGlobal('unread_contact_messages', ContactMessage::countUnread());
                } catch (\Throwable) {
                    // Table might not exist yet during migration
                    $twig->addGlobal('unread_contact_messages', 0);
                }
            } else {
                $twig->addGlobal('unread_contact_messages', 0);
            }

            // |date_fr filter
            $twig->addFilter(new TwigFilter('date_fr', function (?string $date, string $format = 'd/m/Y'): string {
                if (!$date) return '';
                return date($format, strtotime($date));
            }));

            // |month_fr filter — 'YYYY-MM' → 'Janvier 2026'
            $twig->addFilter(new TwigFilter('month_fr', function (string $ym): string {
                $months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                           'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
                [$y, $m] = explode('-', $ym);
                return ($months[(int)$m - 1] ?? $ym) . ' ' . $y;
            }));

            // |truncate filter — truncate text at word boundary
            $twig->addFilter(new TwigFilter('truncate', function (string $text, int $maxLength = 160): string {
                return \App\Services\SeoService::truncate($text, $maxLength);
            }));

            // |render_with_config filter — render content with site_config Twig variables
            $twig->addFilter(new TwigFilter('render_with_config', function (string $content): string {
                return ContentRenderer::renderWithConfig($content);
            }, ['is_safe' => ['html']]));

            // json_encode_pretty() function — JSON with pretty printing + unicode (marked safe)
            $twig->addFunction(new TwigFunction('json_encode_pretty', function ($data): string {
                return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            }, ['is_safe' => ['html']]));

            // url() function
            $twig->addFunction(new TwigFunction('url', function (string $path): string {
                return BASE_URL . '/' . ltrim($path, '/');
            }));

            // asset() function
            $twig->addFunction(new TwigFunction('asset', function (string $path): string {
                return BASE_URL . '/assets/' . ltrim($path, '/');
            }));

            // image_variant(photo, size) — serves optimized variant or falls back to original
            $twig->addFunction(new TwigFunction('image_variant', function (array $photo, string $size): string {
                return \App\Services\ImageVariant::url($photo, $size);
            }));

            // item_url(item) — resolves a MenuItem array to its URL
            $twig->addFunction(new TwigFunction('item_url', function (array $item): string {
                return MenuItem::getUrl($item);
            }));

            // is_active(url) — true when the URL's path matches the current request path
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            $twig->addFunction(new TwigFunction('is_active', function (string $url) use ($currentPath): bool {
                $urlPath = parse_url($url, PHP_URL_PATH) ?? '/';
                return $urlPath !== '/' && $urlPath === $currentPath;
            }));

            self::$twig = $twig;
        }
        return self::$twig;
    }

    public static function render(string $template, array $data = []): void
    {
        $twig = self::getInstance();

        // If no 'meta' provided in $data, add a default empty one
        if (!isset($data['meta'])) {
            $data['meta'] = null;
        }

        echo $twig->render($template, $data);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private static function getFlash(): ?array
    {
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    /**
     * Resolve the active theme. Priority:
     *   1. `theme` key in site_config (set via /admin/site-config)
     *   2. THEME constant (env fallback, defaults to 'front001')
     * Falls back silently if the DB is unavailable (early boot, migrations).
     */
    private static function resolveTheme(): string
    {
        $fallback = defined('THEME') ? THEME : 'front001';
        try {
            $theme = SiteConfig::get('theme');
        } catch (\Throwable) {
            return $fallback;
        }
        if (!$theme) {
            return $fallback;
        }
        $theme = trim($theme);
        // Sécurité : empêcher path traversal vers un dossier hors templates/
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            return $fallback;
        }
        return is_dir(ROOT . '/templates/' . $theme) ? $theme : $fallback;
    }
}
