<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Post;

class HomeController
{
    public function index(array $params): void
    {
        $latestPosts = array_slice(Post::allPublished(), 0, 3);
        View::render('home.twig', ['latest_posts' => $latestPosts]);
    }
}
