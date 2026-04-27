<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\SlugManager;
use App\Services\AIContentService;

class Post
{
    public static function allPublished(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM posts WHERE is_published = 1 AND (published_at IS NULL OR published_at <= NOW())
             ORDER BY published_at DESC, created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM posts WHERE slug = ? AND is_published = 1 LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM posts ORDER BY created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM posts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $slug = SlugManager::makeUnique($data['slug'] ?? SlugManager::generate($data['title']), 'posts');

        // Generate excerpt via AI if empty
        $excerpt = $data['excerpt'] ?? null;
        if (empty($excerpt) && !empty($data['content'])) {
            $generated = AIContentService::generateExcerpt($data['content']);
            if ($generated) {
                $excerpt = $generated;
            }
        }

        // Generate meta_description via AI if empty
        $metaDescription = $data['meta_description'] ?? null;
        if (empty($metaDescription) && !empty($data['content'])) {
            $generated = AIContentService::generateMetaDescription($data['title'], $data['content']);
            if ($generated) {
                $metaDescription = $generated;
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO posts (title, slug, excerpt, content, meta_description, is_published, published_at)
             VALUES (:title, :slug, :excerpt, :content, :meta_description, :is_published, :published_at)"
        );
        $stmt->execute([
            ':title'            => $data['title'],
            ':slug'             => $slug,
            ':excerpt'          => $excerpt,
            ':content'          => $data['content'] ?? '',
            ':meta_description' => $metaDescription,
            ':is_published'     => (int)($data['is_published'] ?? 0),
            ':published_at'     => $data['published_at'] ?: null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $slug = SlugManager::makeUnique($data['slug'] ?? SlugManager::generate($data['title']), 'posts', 'id', $id);

        // Generate excerpt via AI if empty
        $excerpt = $data['excerpt'] ?? null;
        if (empty($excerpt) && !empty($data['content'])) {
            $generated = AIContentService::generateExcerpt($data['content']);
            if ($generated) {
                $excerpt = $generated;
            }
        }

        // Generate meta_description via AI if empty
        $metaDescription = $data['meta_description'] ?? null;
        if (empty($metaDescription) && !empty($data['content'])) {
            $generated = AIContentService::generateMetaDescription($data['title'], $data['content']);
            if ($generated) {
                $metaDescription = $generated;
            }
        }

        Database::get()->prepare(
            "UPDATE posts SET title=:title, slug=:slug, excerpt=:excerpt, content=:content,
             meta_description=:meta_description, is_published=:is_published, published_at=:published_at, updated_at=NOW()
             WHERE id=:id"
        )->execute([
            ':title'            => $data['title'],
            ':slug'             => $slug,
            ':excerpt'          => $excerpt,
            ':content'          => $data['content'] ?? '',
            ':meta_description' => $metaDescription,
            ':is_published'     => (int)($data['is_published'] ?? 0),
            ':published_at'     => $data['published_at'] ?: null,
            ':id'               => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    }

}
