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
        'club_name', 'club_tagline', 'address', 'email', 'phone',
        'facebook_url', 'instagram_url', 'legal_text',
        'home_slider_posts_count', 'home_latest_posts_count',
        'font_family', 'primary_color',
        'header_bg_color', 'header_text_color',
        'header_hover_bg_color', 'header_hover_text_color',
        'header_active_bg_color', 'header_active_text_color',
        'logo_bg_color', 'logo_text_color',
        'logo_url', 'logo_display_mode', 'logo_height',
    ];

    private const ALLOWED_DISPLAY_MODES = [
        'text_only', 'image_only', 'image_and_text', 'image_desktop_text_mobile',
    ];

    public function edit(array $params): void
    {
        Auth::require();
        View::render('admin/site-config/edit.twig', [
            'config' => SiteConfig::all(),
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $existing = SiteConfig::all();

        // Handle logo upload (or removal) first
        $logoUrl = $existing['logo_url'] ?? '';
        if (!empty($_POST['delete_logo'])) {
            $logoUrl = '';
        }
        if (!empty($_FILES['logo_file']['name'])) {
            try {
                $logoUrl = Photo::uploadSingle($_FILES['logo_file'], 'site');
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
            $val = trim($_POST[$key] ?? '');
            if (in_array($key, ['home_slider_posts_count', 'home_latest_posts_count'])) {
                $val = max(1, (int)$val);
            }
            if ($key === 'logo_display_mode' && !in_array($val, self::ALLOWED_DISPLAY_MODES, true)) {
                $val = 'text_only';
            }
            if ($key === 'logo_height') {
                $h = (int)$val;
                $val = ($h >= 16 && $h <= 200) ? (string)$h : '40';
            }
            $data[$key] = (string)$val;
        }
        SiteConfig::setMany($data);
        View::flash('success', 'Configuration enregistrée.');
        header('Location: ' . BASE_URL . '/admin/site-config');
        exit;
    }
}
