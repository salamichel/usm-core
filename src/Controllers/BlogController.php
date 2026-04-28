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

        View::render('blog/list.twig', [
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
        $tags = Tag::findByPost($post['id']);
        View::render('blog/detail.twig', [
            'post'   => $post,
            'photos' => Photo::forEntity('post', $post['id']),
            'tags'   => $tags,
        ]);
    }
}
