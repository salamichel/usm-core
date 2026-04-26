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
            $data[$key] = trim($_POST[$key] ?? '');
        }
        SiteConfig::setMany($data);
        View::flash('success', 'Configuration enregistrée.');
        header('Location: ' . BASE_URL . '/admin/site-config');
        exit;
    }
}
