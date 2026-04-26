<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Models\Photo;
use App\Models\Post;

class BlogController
{
    use NotFoundHandler;

    public function list(array $params): void
    {
        View::render('blog/list.twig', ['posts' => Post::allPublished()]);
    }

    public function show(array $params): void
    {
        $post = Post::findBySlug($params['slug']);
        if (!$post) {
            $this->notFound();
            return;
        }
        View::render('blog/detail.twig', [
            'post'   => $post,
            'photos' => Photo::forEntity('post', $post['id']),
        ]);
    }
}
