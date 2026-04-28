<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\EquipeSaison;
use App\Models\HomeBlock;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Saison;
use App\Services\AgendaService;
use App\Services\FacebookService;

class HomeController
{
    public function index(array $params): void
    {
        $latestPosts = array_slice(Post::allPublished(), 0, 3);

        // Fetch agenda — silently returns [] on API failure
        $matches   = AgendaService::getUpcomingMatches(5);
        $trainings = AgendaService::getUpcomingTrainings(7);

        // Slides hero = 3 dernières actus avec leur 1ère photo (si dispo)
        $slides = [];
        foreach ($latestPosts as $post) {
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

        View::render('home.twig', [
            'slides'         => $slides,
            'stats'          => $stats,
            'home_blocks'    => HomeBlock::allActive(),
            'latest_posts'   => $latestPosts,
            'matches'        => $matches,
            'trainings'      => $trainings,
            'facebook_posts' => FacebookService::getPagePosts(4),
        ]);
    }
}
