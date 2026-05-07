<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\SiteConfig;

class SiteConfigController
{
    private const FIELDS = [
        'club_name', 'club_tagline', 'address', 'email', 'phone',
        'facebook_url', 'instagram_url', 'legal_text',
        'home_slider_posts_count', 'home_latest_posts_count',
        'font_family', 'primary_color', 'header_text_color',
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
        $data = [];
        foreach (self::FIELDS as $key) {
            $val = trim($_POST[$key] ?? '');
            // Validate numeric fields
            if (in_array($key, ['home_slider_posts_count', 'home_latest_posts_count'])) {
                $val = max(1, (int)$val);
            }
            $data[$key] = (string)$val;
        }
        SiteConfig::setMany($data);
        View::flash('success', 'Configuration enregistrée.');
        header('Location: ' . BASE_URL . '/admin/site-config');
        exit;
    }
}
