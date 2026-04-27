<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\SlugManager;

class Tag
{
    public static function all(): array
    {
        $stmt = Database::get()->query("SELECT * FROM tags ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM tags WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM tags WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function findOrCreateByName(string $name): int
    {
        $existing = Database::get()->prepare("SELECT id FROM tags WHERE name = ? LIMIT 1");
        $existing->execute([$name]);
        $result = $existing->fetch();

        if ($result) {
            return (int)$result['id'];
        }

        $slug = SlugManager::generate($name);
        $stmt = Database::get()->prepare(
            "INSERT INTO tags (name, slug) VALUES (:name, :slug)"
        );
        $stmt->execute([':name' => $name, ':slug' => $slug]);
        return (int)Database::get()->lastInsertId();
    }

    public static function create(array $data): int
    {
        $name = trim((string)$data['name']);
        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = SlugManager::generate($slugInput ?: $name);

        $stmt = Database::get()->prepare(
            "INSERT INTO tags (name, slug) VALUES (:name, :slug)"
        );
        $stmt->execute([':name' => $name, ':slug' => $slug]);
        return (int)Database::get()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $name = trim((string)$data['name']);
        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = SlugManager::generate($slugInput ?: $name);

        Database::get()->prepare(
            "UPDATE tags SET name = :name, slug = :slug WHERE id = :id"
        )->execute([':name' => $name, ':slug' => $slug, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
    }

    public static function findByPost(int $postId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT t.* FROM tags t
             INNER JOIN post_tags pt ON t.id = pt.tag_id
             WHERE pt.post_id = ?
             ORDER BY t.name ASC"
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }

    public static function attachToPost(int $postId, int $tagId): void
    {
        $stmt = Database::get()->prepare(
            "INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)"
        );
        $stmt->execute([':post_id' => $postId, ':tag_id' => $tagId]);
    }

    public static function detachFromPost(int $postId, int $tagId): void
    {
        Database::get()->prepare(
            "DELETE FROM post_tags WHERE post_id = ? AND tag_id = ?"
        )->execute([$postId, $tagId]);
    }

    public static function setPostTags(int $postId, array $tagIds): void
    {
        Database::get()->prepare("DELETE FROM post_tags WHERE post_id = ?")->execute([$postId]);

        foreach ($tagIds as $tagId) {
            self::attachToPost($postId, (int)$tagId);
        }
    }

    public static function getPostCount(int $tagId): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) as cnt FROM post_tags WHERE tag_id = ?"
        );
        $stmt->execute([$tagId]);
        $result = $stmt->fetch();
        return (int)$result['cnt'];
    }
}
