<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\EquipeSaison;
use App\Models\HomeBlock;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Saison;
use App\Models\SiteConfig;
use App\Services\AgendaService;

class HomeController
{
    public function index(array $params): void
    {
        $sliderCount = (int)SiteConfig::get('home_slider_posts_count', '3');
        $latestPostsCount = (int)SiteConfig::get('home_latest_posts_count', '3');

        $allPublished = Post::allPublished();
        $sliderPosts = array_slice($allPublished, 0, $sliderCount);
        $latestPosts = array_slice($allPublished, 0, $latestPostsCount);

        // Fetch agenda — silently returns [] on API failure
        $matches   = AgendaService::getUpcomingMatches(5);
        $trainings = AgendaService::getUpcomingTrainings(7);

        // Slides hero — construits à partir des posts du slider avec leur 1ère photo
        $slides = [];
        foreach ($sliderPosts as $post) {
            $photos    = Photo::forEntity('post', (int)$post['id']);
            $slides[]  = [
                'title'      => $post['title'],
                'excerpt'    => $post['excerpt'] ?? '',
                'url'        => BASE_URL . '/blog/' . $post['slug'],
                'image'      => !empty($photos) ? $photos[0]['filename'] : null,
            ];
        }

        // Stats club (saison active)
        $saisonActive = Saison::getActive();
        $stats = [
            'licencies'      => 0,
            'equipes'        => 0,
            'saison'         => $saisonActive['libelle'] ?? '—',
            'matchs_a_venir' => count(AgendaService::getUpcomingMatches(50)),
        ];
        if ($saisonActive) {
            $stats['licencies'] = Saison::snapshotCount((int)$saisonActive['id']);
            $stats['equipes']   = EquipeSaison::countWithMembersForSaison((int)$saisonActive['id']);
        }

        // Load cover photos for latest posts
        $postCovers = [];
        foreach ($latestPosts as $post) {
            $postCovers[$post['id']] = Photo::getEntityCover('post', (int)$post['id']);
        }

        View::render('home.twig', [
            'slides'       => $slides,
            'stats'        => $stats,
            'home_blocks'  => HomeBlock::allActive(),
            'latest_posts' => $latestPosts,
            'post_covers'  => $postCovers,
            'matches'      => $matches,
            'trainings'    => $trainings,
        ]);
    }
}
