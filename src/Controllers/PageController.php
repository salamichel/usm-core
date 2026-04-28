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
        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
            ['name' => $page['title'], 'url' => SeoService::absoluteUrl('/p/' . $page['slug'])],
        ];
        $jsonLd = [
            StructuredDataService::sportsClub(),
        ];
        $breadcrumbSchema = StructuredDataService::breadcrumbs($breadcrumbs);
        if ($breadcrumbSchema) {
            $jsonLd[] = $breadcrumbSchema;
        }

        $meta = new PageMetadata(
            title: SeoService::title($page['title']),
            description: SeoService::description(null, $page['content']),
            canonical: SeoService::absoluteUrl('/p/' . $page['slug']),
            ogImage: $ogImage,
            ogType: 'website',
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
        );

        View::render('pages/detail.twig', [
            'meta'   => $meta,
            'page'   => $page,
            'photos' => $photos,
        ]);
    }

}
