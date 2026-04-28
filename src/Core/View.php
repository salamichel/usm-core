<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\MenuItem;
use App\Models\SiteConfig;
use App\Models\ContactMessage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class View
{
    private static ?Environment $twig = null;

    private static function getInstance(): Environment
    {
        if (self::$twig === null) {
            $loader = new FilesystemLoader(ROOT . '/templates/' . THEME);
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
            $twig->addGlobal('unread_contact_messages', Auth::check() ? ContactMessage::countUnread() : 0);

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

            // url() function
            $twig->addFunction(new TwigFunction('url', function (string $path): string {
                return BASE_URL . '/' . ltrim($path, '/');
            }));

            // asset() function
            $twig->addFunction(new TwigFunction('asset', function (string $path): string {
                return BASE_URL . '/assets/' . ltrim($path, '/');
            }));

            // item_url(item) — resolves a MenuItem array to its URL
            $twig->addFunction(new TwigFunction('item_url', function (array $item): string {
                return MenuItem::getUrl($item);
            }));

            self::$twig = $twig;
        }
        return self::$twig;
    }

    public static function render(string $template, array $data = []): void
    {
        echo self::getInstance()->render($template, $data);
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
}
