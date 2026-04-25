<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

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
        $slug = self::uniqueSlug($data['slug'] ?? self::slugify($data['title']));
        $stmt = $db->prepare(
            "INSERT INTO posts (title, slug, excerpt, content, is_published, published_at)
             VALUES (:title, :slug, :excerpt, :content, :is_published, :published_at)"
        );
        $stmt->execute([
            ':title'        => $data['title'],
            ':slug'         => $slug,
            ':excerpt'      => $data['excerpt'] ?? null,
            ':content'      => $data['content'] ?? '',
            ':is_published' => (int)($data['is_published'] ?? 0),
            ':published_at' => $data['published_at'] ?: null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $slug = self::uniqueSlug($data['slug'] ?? self::slugify($data['title']), $id);
        Database::get()->prepare(
            "UPDATE posts SET title=:title, slug=:slug, excerpt=:excerpt, content=:content,
             is_published=:is_published, published_at=:published_at, updated_at=NOW()
             WHERE id=:id"
        )->execute([
            ':title'        => $data['title'],
            ':slug'         => $slug,
            ':excerpt'      => $data['excerpt'] ?? null,
            ':content'      => $data['content'] ?? '',
            ':is_published' => (int)($data['is_published'] ?? 0),
            ':published_at' => $data['published_at'] ?: null,
            ':id'           => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    }

    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ô'=>'o','ù'=>'u','û'=>'u','ü'=>'u','î'=>'i','ï'=>'i','ç'=>'c']);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    private static function uniqueSlug(string $slug, int $excludeId = 0): string
    {
        $base  = $slug;
        $i     = 1;
        $db    = Database::get();
        while (true) {
            $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $excludeId]);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
