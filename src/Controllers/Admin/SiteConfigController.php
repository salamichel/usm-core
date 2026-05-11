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
        'font_family', 'primary_color',
        'header_bg_color', 'header_text_color',
        'header_hover_bg_color', 'header_hover_text_color',
        'header_active_bg_color', 'header_active_text_color',
        'logo_bg_color', 'logo_text_color',
        // front003 — palette éditoriale
        'secondary_color', 'text_color', 'background_color', 'surface_color',
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
