<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Models\PageStatique;
use App\Models\Photo;

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
        View::render('pages/detail.twig', [
            'page'   => $page,
            'photos' => Photo::forEntity('page', $page['id']),
        ]);
    }

}
