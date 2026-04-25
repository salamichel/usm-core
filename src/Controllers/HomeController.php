<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\Post;
use App\Services\AgendaService;

class HomeController
{
    public function index(array $params): void
    {
        $latestPosts = array_slice(Post::allPublished(), 0, 3);

        // Fetch agenda — silently returns [] on API failure
        $matches   = AgendaService::getUpcomingMatches(5);
        $trainings = AgendaService::getUpcomingTrainings(7);

        View::render('home.twig', [
            'latest_posts' => $latestPosts,
            'matches'      => $matches,
            'trainings'    => $trainings,
        ]);
    }
}
