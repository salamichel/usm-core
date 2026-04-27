<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\NotFoundHandler;
use App\Core\View;
use App\Core\Database;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Tag;

class BlogController
{
    use NotFoundHandler;

    public function list(array $params): void
    {
        $tagSlug = $params['tag'] ?? null;
        $posts = Post::allPublished();
        $selectedTag = null;

        if ($tagSlug) {
            $selectedTag = Tag::findBySlug($tagSlug);
            if ($selectedTag) {
                $posts = $this->filterPostsByTag($posts, $selectedTag['id']);
            }
        }

        $allTags = Tag::all();
        $tagCounts = [];
        foreach ($allTags as $tag) {
            $tagCounts[$tag['id']] = Tag::getPostCount($tag['id']);
        }

        View::render('blog/list.twig', [
            'posts'       => $posts,
            'all_tags'    => $allTags,
            'tag_counts'  => $tagCounts,
            'selected_tag' => $selectedTag,
        ]);
    }

    private function filterPostsByTag(array $posts, int $tagId): array
    {
        $postIds = [];
        $stmt = \App\Core\Database::get()->prepare(
            "SELECT post_id FROM post_tags WHERE tag_id = ?"
        );
        $stmt->execute([$tagId]);
        while ($row = $stmt->fetch()) {
            $postIds[] = $row['post_id'];
        }

        return array_filter($posts, fn($post) => in_array($post['id'], $postIds, true));
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
