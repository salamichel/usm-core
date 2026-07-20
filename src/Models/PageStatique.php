<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\SlugManager;

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
        $stmt = Database::get()->query("SELECT * FROM pages ORDER BY id ASC");
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
        $stmt = $db->prepare(
            "INSERT INTO pages (title, slug, content, is_published)
             VALUES (:title, :slug, :content, :is_published)"
        );
        $stmt->execute([
            ':title'        => $data['title'],
            ':slug'         => $slug,
            ':content'      => $data['content'] ?? '',
            ':is_published' => (int)($data['is_published'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $slug = SlugManager::makeUnique($data['slug'] ?? SlugManager::generate($data['title']), 'pages', 'id', $id);
        Database::get()->prepare(
            "UPDATE pages SET title=:title, slug=:slug, content=:content, category=:category,
             is_published=:is_published, updated_at=NOW() WHERE id=:id"
        )->execute([
            ':title'        => $data['title'],
            ':slug'         => $slug,
            ':category'     => $data['category'] ?? null,
            ':content'      => $data['content'] ?? '',
            ':is_published' => (int)($data['is_published'] ?? 0),
            ':id'           => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM pages WHERE id = ?")->execute([$id]);
    }

    public static function deleteBulk(array $ids): int
    {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if (empty($cleanIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = Database::get()->prepare("DELETE FROM pages WHERE id IN ($placeholders)");
        $stmt->execute($cleanIds);
        return $stmt->rowCount();
    }
}
