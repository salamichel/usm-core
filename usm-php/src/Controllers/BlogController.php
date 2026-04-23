<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Post;

class BlogController
{
    public function list(array $params): void
    {
        View::render('blog/list.twig', ['posts' => Post::allPublished()]);
    }

    public function show(array $params): void
    {
        $post = Post::findBySlug($params['slug']);
        if (!$post) {
            http_response_code(404);
            View::render('404.twig');
            return;
        }
        View::render('blog/detail.twig', ['post' => $post]);
    }
}
