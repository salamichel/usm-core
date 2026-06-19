<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\EquipeSaison;
use App\Models\HomeBlock;
use App\Models\Location;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Saison;
use App\Models\SiteConfig;
use App\Services\AgendaService;
use App\Services\SeoService;
use App\Services\StructuredDataService;
use App\ValueObjects\PageMetadata;

class HomeController
{
    public function index(array $params): void
    {
        $sliderCount = (int)SiteConfig::get('home_slider_posts_count', '3');
        $latestPostsCount = (int)SiteConfig::get('home_latest_posts_count', '3');

        $allPublished = Post::allPublished();
        $sliderPosts = Post::forSlider();
        if (empty($sliderPosts)) {
            $sliderPosts = array_slice($allPublished, 0, $sliderCount);
        }
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
                'photo'      => !empty($photos) ? $photos[0] : null,
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
        $newLicencies = 0;
        if ($saisonActive) {
            $stats['licencies'] = Saison::snapshotCount((int)$saisonActive['id']);
            $stats['equipes']   = EquipeSaison::countWithMembersForSaison((int)$saisonActive['id']);
            $newLicencies       = Saison::newLicenciesCount((int)$saisonActive['id']);
        }

        $locations   = Location::all();
        $summerEvents = AgendaService::getUpcomingByTypes(['Beach', 'Club', 'Tournoi'], 7);
        $nextMatch    = $matches[0] ?? null;
        $matchPhotos  = [];
        // Cover du prochain match : on tente d'utiliser la photo de couverture
        // de la 1ère équipe-saison ayant des membres (fallback visuel).
        if ($saisonActive) {
            $equipes = EquipeSaison::findBySaison((int)$saisonActive['id']);
            foreach ($equipes as $es) {
                $cover = Photo::getEntityCover('equipe_saison', (int)$es['id']);
                if ($cover) { $matchPhotos[] = $cover; break; }
            }
        }

        // Load cover photos for latest posts
        $postCovers = [];
        foreach ($latestPosts as $post) {
            $postCovers[$post['id']] = Photo::getEntityCover('post', (int)$post['id']);
        }

        // Home block

        $blocks = HomeBlock::allActive();
        // Pour chaque bloc, récupérer sa photo de couverture
        foreach ($blocks as &$block) {
            $block['cover_photo'] = Photo::getEntityCover('home_block', (int)$block['id']);
        }

        // SEO metadata
        $ogImage = null;
        if (!empty($slides) && !empty($slides[0]['image'])) {
            $ogImage = SeoService::uploadUrl($slides[0]['image']);
        }

        $meta = new PageMetadata(
            title: SiteConfig::get('club_name') ?? 'USM Volley',
            description: SeoService::description(
                SiteConfig::get('club_tagline'),
                null,
                'Union Sportive Miosienne Volley-Ball — actualités, agenda et équipes.'
            ),
            ogImage: $ogImage,
            ogType: 'website',
            jsonLd: [
                StructuredDataService::website(),
                StructuredDataService::sportsClub(),
            ],
        );

        View::render('home.twig', [
            'meta'          => $meta,
            'slides'        => $slides,
            'stats'         => $stats,
            'home_blocks'   => $blocks,
            'latest_posts'  => $latestPosts,
            'post_covers'   => $postCovers,
            'matches'       => $matches,
            'trainings'     => $trainings,
            'summer_events' => $summerEvents,
            'next_match'    => $nextMatch,
            'match_cover'   => $matchPhotos[0] ?? null,
            'new_licencies' => $newLicencies,
            'locations'     => $locations
        ]);
    }
}
