<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Models\PageStatique;
use App\Models\Photo;
use App\Services\SeoService;
use App\Services\StructuredDataService;
use App\ValueObjects\PageMetadata;

class PageController
{
    use NotFoundHandler;

    public function show(array $params): void
    {
        $page = PageStatique::findBySlug($params['slug']);
        if (!$page) {
            $this->notFound();
            return;
        }
        $photos = Photo::forEntity('page', $page['id']);

        // SEO metadata
        $ogImage = SeoService::pickOgImage(null, $photos);
        $meta = new PageMetadata(
            title: SeoService::title($page['titre']),
            description: SeoService::description(null, $page['contenu']),
            canonical: SeoService::absoluteUrl('/p/' . $page['slug']),
            ogImage: $ogImage,
            ogType: 'website',
            jsonLd: [
                StructuredDataService::sportsClub(),
            ],
            breadcrumbs: [
                ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
                ['name' => $page['titre'], 'url' => SeoService::absoluteUrl('/p/' . $page['slug'])],
            ],
        );

        View::render('pages/detail.twig', [
            'meta'   => $meta,
            'page'   => $page,
            'photos' => $photos,
        ]);
    }

}
