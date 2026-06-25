<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Photo;
use App\Models\SiteConfig;

class SiteConfigController
{
    private const FIELDS = [
        'theme',
        'club_name', 'club_tagline', 'address', 'email', 'phone',
        'facebook_url', 'instagram_url', 'legal_text',
        'home_slider_posts_count', 'home_latest_posts_count',
        'font_family', 'primary_color',
        'google_analytics_id',

        'header_bg_color', 'header_text_color',
        'header_hover_bg_color', 'header_hover_text_color',
        'header_active_bg_color', 'header_active_text_color',
        'logo_bg_color', 'logo_text_color',
        'logo_url', 'logo_display_mode', 'logo_footer_display_mode', 'logo_height',
        'footer_bg_color', 'footer_text_color', 'footer_heading_color',
        // front003 — palette éditoriale
        'secondary_color', 'text_color', 'background_color', 'surface_color',
        'frame_dark_bg_color',
        'display_font_family',
        // front003 — contenu home
        'adherer_url', 'essai_url',
        'trust_badge_1_label', 'trust_badge_1_strong',
        'trust_badge_2_label', 'trust_badge_2_strong',
        'trust_badge_3_label',
        'hero_badge_trophy_label', 'hero_badge_trophy_sub', 'hero_motto',
        'marquee_tags',
        'cta_feature_1_label', 'cta_feature_1_sub',
        'cta_feature_2_label', 'cta_feature_2_sub',
        'cta_feature_3_label', 'cta_feature_3_sub',
    ];

    private const ALLOWED_DISPLAY_MODES = [
        'text_only', 'image_only', 'image_and_text', 'image_desktop_text_mobile',
    ];

    private const ALLOWED_FOOTER_DISPLAY_MODES = [
        'hidden', 'image_only', 'image_and_text',
    ];

    public function edit(array $params): void
    {
        Auth::require();
        View::render('admin/site-config/edit.twig', [
            'config'           => SiteConfig::all(),
            'available_themes' => self::availableThemes(),
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $themes = self::availableThemes();
        $existing = SiteConfig::all();

        // Handle logo upload (or removal) first
        $logoUrl = $existing['logo_url'] ?? '';
        if (!empty($_POST['delete_logo'])) {
            $logoUrl = '';
        }
        if (!empty($_FILES['logo_file']['name'])) {
            try {
                $result = Photo::uploadSingle($_FILES['logo_file'], 'site');
                $logoUrl = $result['path'];
            } catch (\Throwable $e) {
                View::flash('error', 'Logo : ' . $e->getMessage());
                header('Location: ' . BASE_URL . '/admin/site-config');
                exit;
            }
        }

        $data = [];
        foreach (self::FIELDS as $key) {
            if ($key === 'logo_url') {
                $data[$key] = $logoUrl;
                continue;
            }
            $raw = $_POST[$key] ?? '';
            $val = trim(is_array($raw) ? '' : (string)$raw);
            if (in_array($key, ['home_slider_posts_count', 'home_latest_posts_count'])) {
                $val = max(1, (int)$val);
            } elseif ($key === 'theme') {
                // Whitelist : la valeur doit correspondre à un dossier templates/<name>
                $val = in_array($val, $themes, true) ? $val : (SiteConfig::get('theme') ?? '');
            }
            if ($key === 'logo_display_mode' && !in_array($val, self::ALLOWED_DISPLAY_MODES, true)) {
                $val = 'text_only';
            }
            if ($key === 'logo_footer_display_mode' && !in_array($val, self::ALLOWED_FOOTER_DISPLAY_MODES, true)) {
                $val = 'hidden';
            }
            if ($key === 'logo_height') {
                $h = (int)$val;
                $val = ($h >= 16 && $h <= 200) ? (string)$h : '40';
            }
            $data[$key] = (string)$val;
        }
        SiteConfig::setMany($data);
        // Invalide le cache Twig pour que le nouveau thème prenne effet
        self::clearTwigCache();
        View::flash('success', 'Configuration enregistrée.');
        header('Location: ' . BASE_URL . '/admin/site-config');
        exit;
    }

    /**
     * Liste les thèmes disponibles (dossiers sous `templates/`).
     */
    private static function availableThemes(): array
    {
        $dir = ROOT . '/templates';
        if (!is_dir($dir)) {
            return [];
        }
        $themes = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($dir . '/' . $entry) && is_file($dir . '/' . $entry . '/base.twig')) {
                $themes[] = $entry;
            }
        }
        sort($themes);
        return $themes;
    }

    private static function clearTwigCache(): void
    {
        $cache = ROOT . '/cache/twig';
        if (!is_dir($cache)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cache, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
    }
}
