<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Document;
use App\Models\PageStatique;
use App\Models\Photo;

class PageController
{
    public function show(array $params): void
    {
        $page = PageStatique::findBySlug($params['slug']);
        if (!$page) {
            http_response_code(404);
            View::render('404.twig');
            return;
        }
        View::render('pages/detail.twig', [
            'page'   => $page,
            'photos' => Photo::forEntity('page', $page['id']),
        ]);
    }

    public function documents(array $params): void
    {
        View::render('documents/list.twig', ['documents' => Document::allPublic()]);
    }
}
