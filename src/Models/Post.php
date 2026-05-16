<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\SlugManager;

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

    public static function forSlider(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM posts WHERE is_slider = 1 AND is_published = 1 AND (published_at IS NULL OR published_at <= NOW())
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

    public static function getNeighbors(int $id): array
    {
        $db   = Database::get();
        $self = $db->prepare("SELECT published_at FROM posts WHERE id = ? LIMIT 1");
        $self->execute([$id]);
        $row  = $self->fetch();
        if (!$row) {
            return ['prev' => null, 'next' => null];
        }
        $date = $row['published_at'];

        $prev = $db->prepare(
            "SELECT id, title, slug FROM posts
             WHERE is_published = 1
               AND (published_at < ? OR (published_at = ? AND id < ?))
             ORDER BY published_at DESC, id DESC LIMIT 1"
        );
        $prev->execute([$date, $date, $id]);

        $next = $db->prepare(
            "SELECT id, title, slug FROM posts
             WHERE is_published = 1
               AND (published_at > ? OR (published_at = ? AND id > ?))
             ORDER BY published_at ASC, id ASC LIMIT 1"
        );
        $next->execute([$date, $date, $id]);

        return ['prev' => $prev->fetch() ?: null, 'next' => $next->fetch() ?: null];
    }

    public static function findByCanalblogId(string $canalblogId): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM posts WHERE canalblog_id = ? LIMIT 1");
        $stmt->execute([$canalblogId]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM posts ORDER BY published_at DESC, created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Filtered query for both admin and front.
     * $filters keys: tag_id, month (YYYY-MM), status ('published'|'draft'), published_only (bool),
     *                limit, offset
     */
    public static function filtered(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if ($filters['published_only'] ?? false) {
            $where[] = 'is_published = 1 AND (published_at IS NULL OR published_at <= NOW())';
        }

        if (isset($filters['status'])) {
            $where[] = 'is_published = :status';
            $params[':status'] = $filters['status'] === 'published' ? 1 : 0;
        }

        if (!empty($filters['month'])) {
            $where[] = "DATE_FORMAT(published_at, '%Y-%m') = :month";
            $params[':month'] = $filters['month'];
        }

        if (!empty($filters['tag_id'])) {
            $where[] = 'id IN (SELECT post_id FROM post_tags WHERE tag_id = :tag_id)';
            $params[':tag_id'] = (int)$filters['tag_id'];
        }

        $sql = 'SELECT * FROM posts';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY published_at DESC, created_at DESC';

        if (isset($filters['limit']) && isset($filters['offset'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'] . ' OFFSET ' . (int)$filters['offset'];
        }

        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Count filtered posts (without limit/offset). */
    public static function countFiltered(array $filters = []): int
    {
        $where  = [];
        $params = [];

        if ($filters['published_only'] ?? false) {
            $where[] = 'is_published = 1 AND (published_at IS NULL OR published_at <= NOW())';
        }

        if (isset($filters['status'])) {
            $where[] = 'is_published = :status';
            $params[':status'] = $filters['status'] === 'published' ? 1 : 0;
        }

        if (!empty($filters['month'])) {
            $where[] = "DATE_FORMAT(published_at, '%Y-%m') = :month";
            $params[':month'] = $filters['month'];
        }

        if (!empty($filters['tag_id'])) {
            $where[] = 'id IN (SELECT post_id FROM post_tags WHERE tag_id = :tag_id)';
            $params[':tag_id'] = (int)$filters['tag_id'];
        }

        $sql = 'SELECT COUNT(*) as cnt FROM posts';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = Database::get()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['cnt'] ?? 0);
    }

    /** Returns distinct months (YYYY-MM) that have articles. */
    public static function getAvailableMonths(bool $publishedOnly = true): array
    {
        $cond = $publishedOnly
            ? "WHERE is_published = 1 AND published_at IS NOT NULL AND published_at <= NOW()"
            : "WHERE published_at IS NOT NULL";
        $stmt = Database::get()->query(
            "SELECT DISTINCT DATE_FORMAT(published_at, '%Y-%m') AS month
             FROM posts {$cond}
             ORDER BY month DESC"
        );
        return array_column($stmt->fetchAll(), 'month');
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

        $publishedAt = null;
        if (!empty($data['published_at'])) {
            try {
                $publishedAt = new \DateTime($data['published_at']);
            } catch (\Exception $e) {
                // Invalid date, leave as null
            }
        }

        $customSlug = !empty($data['slug']) ? trim($data['slug']) : '';
        if ($customSlug) {
            $baseSlug = SlugManager::generate($customSlug);
        } else {
            $baseSlug = SlugManager::generateWithDate($data['title'], $publishedAt);
        }
        $slug = SlugManager::makeUnique($baseSlug, 'posts');

        $stmt = $db->prepare(
            "INSERT INTO posts (title, slug, excerpt, content, is_published, published_at, canalblog_id, is_slider)
             VALUES (:title, :slug, :excerpt, :content, :is_published, :published_at, :canalblog_id, :is_slider)"
        );
        $stmt->execute([
            ':title'         => $data['title'],
            ':slug'          => $slug,
            ':excerpt'       => $data['excerpt'] ?? null,
            ':content'       => $data['content'] ?? '',
            ':is_published'  => (int)($data['is_published'] ?? 0),
            ':published_at'  => $data['published_at'] ?: null,
            ':canalblog_id'  => $data['canalblog_id'] ?? null,
            ':is_slider'     => (int)($data['is_slider'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $publishedAt = null;
        if (!empty($data['published_at'])) {
            try {
                $publishedAt = new \DateTime($data['published_at']);
            } catch (\Exception $e) {
                // Invalid date, leave as null
            }
        }

        $customSlug = !empty($data['slug']) ? trim($data['slug']) : '';
        if ($customSlug) {
            $baseSlug = SlugManager::generate($customSlug);
        } else {
            $baseSlug = SlugManager::generateWithDate($data['title'], $publishedAt);
        }
        $slug = SlugManager::makeUnique($baseSlug, 'posts', 'id', $id);

        Database::get()->prepare(
            "UPDATE posts SET title=:title, slug=:slug, excerpt=:excerpt, content=:content,
             is_published=:is_published, published_at=:published_at, is_slider=:is_slider, updated_at=NOW()
             WHERE id=:id"
        )->execute([
            ':title'        => $data['title'],
            ':slug'         => $slug,
            ':excerpt'      => $data['excerpt'] ?? null,
            ':content'      => $data['content'] ?? '',
            ':is_published' => (int)($data['is_published'] ?? 0),
            ':published_at' => $data['published_at'] ?: null,
            ':is_slider'    => (int)($data['is_slider'] ?? 0),
            ':id'           => $id,
        ]);
    }

    public static function updateContent(int $id, string $content): void
    {
        Database::get()->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$content, $id]);
    }

    public static function setSlider(int $id, bool $value): void
    {
        Database::get()->prepare("UPDATE posts SET is_slider = ?, updated_at = NOW() WHERE id = ?")
            ->execute([(int)$value, $id]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    }

}
