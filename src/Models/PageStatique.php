<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

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
        $slug = self::uniqueSlug($data['slug'] ?? Post::slugify($data['title']));
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
        $slug = self::uniqueSlug($data['slug'] ?? Post::slugify($data['title']), $id);
        Database::get()->prepare(
            "UPDATE pages SET title=:title, slug=:slug, content=:content,
             is_published=:is_published, updated_at=NOW() WHERE id=:id"
        )->execute([
            ':title'        => $data['title'],
            ':slug'         => $slug,
            ':content'      => $data['content'] ?? '',
            ':is_published' => (int)($data['is_published'] ?? 0),
            ':id'           => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM pages WHERE id = ?")->execute([$id]);
    }

    private static function uniqueSlug(string $slug, int $excludeId = 0): string
    {
        $base = $slug;
        $i    = 1;
        $db   = Database::get();
        while (true) {
            $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $excludeId]);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
