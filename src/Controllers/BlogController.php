<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Core\Database;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Tag;
use App\Models\SiteConfig;
use App\Services\Pagination;
use App\Services\SeoService;
use App\Services\StructuredDataService;
use App\ValueObjects\PageMetadata;

class BlogController
{
    use NotFoundHandler;

    public function list(array $params): void
    {
        // Tag via route param (/blog/tag/{tag}) ou query string (?tag=slug)
        $tagSlug     = $params['tag'] ?? ($_GET['tag'] ?? null);
        $selectedMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : null;
        $selectedTag = null;

        $filters = ['published_only' => true];

        if ($tagSlug) {
            $selectedTag = Tag::findBySlug($tagSlug);
            if ($selectedTag) {
                $filters['tag_id'] = $selectedTag['id'];
            }
        }

        if ($selectedMonth) {
            $filters['month'] = $selectedMonth;
        }

        // Pagination
        $perPage = (int)(SiteConfig::get('posts_per_page') ?? 10);
        $perPage = max(1, $perPage);
        $currentPage = max(1, (int)($_GET['page'] ?? 1));

        $totalPosts = Post::countFiltered($filters);
        $pagination = new Pagination($totalPosts, $perPage, $currentPage);

        $filters['limit'] = $pagination->limit;
        $filters['offset'] = $pagination->offset;

        $posts   = Post::filtered($filters);
        $allTags = Tag::all();

        // Add cover photo for each post
        foreach ($posts as &$post) {
            $post['cover'] = Photo::getEntityCover('post', $post['id']);
        }
        unset($post);

        $tagCounts = [];
        foreach ($allTags as $tag) {
            $tagCounts[$tag['id']] = Tag::getPostCount($tag['id']);
        }

        // SEO metadata
        $blogTitle = 'Actualités';
        if ($selectedTag) {
            $blogTitle = $selectedTag['name'] . ' — Actualités';
        }

        $meta = new PageMetadata(
            title: SeoService::title($blogTitle),
            description: SeoService::description(
                null,
                null,
                'Retrouvez les dernières actualités du club USM Volley-Ball : matchs, entraînements, événements et news.'
            ),
            ogType: 'website',
            jsonLd: [
                StructuredDataService::sportsClub(),
            ],
        );

        View::render('blog/list.twig', [
            'meta'           => $meta,
            'posts'          => $posts,
            'all_tags'       => $allTags,
            'tag_counts'     => $tagCounts,
            'selected_tag'   => $selectedTag,
            'selected_month' => $selectedMonth,
            'months'         => Post::getAvailableMonths(true),
            'pagination'     => $pagination,
        ]);
    }

    public function show(array $params): void
    {
        $post = Post::findBySlug($params['slug']);
        if (!$post) {
            $this->notFound();
            return;
        }
        $post['cover'] = Photo::getEntityCover('post', $post['id']);
        $tags = Tag::findByPost($post['id']);
        $allPhotos = Photo::forEntity('post', $post['id']);
        // Exclure la photo de couverture de la galerie
        $photos = array_values(array_filter($allPhotos, fn($p) => $post['cover'] === null || $p['id'] !== $post['cover']['id']));

        // SEO metadata
        $ogImage = SeoService::pickOgImage(null, $photos);
        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SeoService::absoluteUrl('/')],
            ['name' => 'Actualités', 'url' => SeoService::absoluteUrl('/blog')],
            ['name' => $post['title'], 'url' => SeoService::absoluteUrl('/blog/' . $post['slug'])],
        ];
        $jsonLd = [
            StructuredDataService::blogPosting($post, $ogImage),
            StructuredDataService::sportsClub(),
        ];
        $breadcrumbSchema = StructuredDataService::breadcrumbs($breadcrumbs);
        if ($breadcrumbSchema) {
            $jsonLd[] = $breadcrumbSchema;
        }

        $meta = new PageMetadata(
            title: SeoService::title($post['title']),
            description: SeoService::description($post['excerpt'], $post['content']),
            canonical: SeoService::absoluteUrl('/blog/' . $post['slug']),
            ogImage: $ogImage,
            ogType: 'article',
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
            articlePublishedAt: !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : null,
            articleModifiedAt: !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : null,
        );

        $neighbors = Post::getNeighbors($post['id']);

        View::render('blog/detail.twig', [
            'meta'      => $meta,
            'post'      => $post,
            'photos'    => $photos,
            'tags'      => $tags,
            'prev_post' => $neighbors['prev'],
            'next_post' => $neighbors['next'],
        ]);
    }
}
