<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\SlugManager;
use App\Services\AIContentService;

class PageStatique
{
    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM pages WHERE slug = ? AND is_published = 1 LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::get()->query("SELECT * FROM pages ORDER BY title ASC");
        return $stmt->fetchAll();
    }

    public static function allPublished(): array
    {
        $stmt = Database::get()->query("SELECT id, title, slug FROM pages WHERE is_published = 1 ORDER BY title ASC");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $slug = SlugManager::makeUnique($data['slug'] ?? SlugManager::generate($data['title']), 'pages');

        // Generate meta_description via AI if empty
        $metaDescription = $data['meta_description'] ?? null;
        if (empty($metaDescription) && !empty($data['content'])) {
            $generated = AIContentService::generateMetaDescription($data['title'], $data['content']);
            if ($generated) {
                $metaDescription = $generated;
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO pages (title, slug, content, meta_description, is_published)
             VALUES (:title, :slug, :content, :meta_description, :is_published)"
        );
        $stmt->execute([
            ':title'            => $data['title'],
            ':slug'             => $slug,
            ':content'          => $data['content'] ?? '',
            ':meta_description' => $metaDescription,
            ':is_published'     => (int)($data['is_published'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $slug = SlugManager::makeUnique($data['slug'] ?? SlugManager::generate($data['title']), 'pages', 'id', $id);

        // Generate meta_description via AI if empty
        $metaDescription = $data['meta_description'] ?? null;
        if (empty($metaDescription) && !empty($data['content'])) {
            $generated = AIContentService::generateMetaDescription($data['title'], $data['content']);
            if ($generated) {
                $metaDescription = $generated;
            }
        }

        Database::get()->prepare(
            "UPDATE pages SET title=:title, slug=:slug, content=:content,
             meta_description=:meta_description, is_published=:is_published, updated_at=NOW() WHERE id=:id"
        )->execute([
            ':title'            => $data['title'],
            ':slug'             => $slug,
            ':content'          => $data['content'] ?? '',
            ':meta_description' => $metaDescription,
            ':is_published'     => (int)($data['is_published'] ?? 0),
            ':id'               => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM pages WHERE id = ?")->execute([$id]);
    }

}
