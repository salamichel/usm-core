<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class MenuItem
{
    /**
     * Returns root items with their children nested, ordered by position.
     */
    public static function getTree(): array
    {
        try {
            $stmt = Database::get()->query(
                "SELECT * FROM menu_items ORDER BY position ASC, id ASC"
            );
            $all = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        // Index all items, initialize empty children
        $indexed = [];
        foreach ($all as $row) {
            $row['children'] = [];
            $indexed[$row['id']] = $row;
        }

        // Nest children into their parent
        foreach ($indexed as $id => $row) {
            if ($row['parent_id'] !== null && isset($indexed[$row['parent_id']])) {
                $indexed[$row['parent_id']]['children'][] = $row;
            }
        }

        return array_values(array_filter($indexed, fn($r) => $r['parent_id'] === null));
    }

    public static function allFlat(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM menu_items ORDER BY ISNULL(parent_id) DESC, parent_id ASC, position ASC"
        );
        return $stmt->fetchAll();
    }

    public static function roots(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM menu_items WHERE parent_id IS NULL ORDER BY position ASC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM menu_items WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO menu_items (label, link_type, target, parent_id, position)
             VALUES (:label, :link_type, :target, :parent_id, :position)"
        );
        $stmt->execute([
            ':label'     => $data['label'],
            ':link_type' => $data['link_type'] ?? 'none',
            ':target'    => $data['target'] ?: null,
            ':parent_id' => $data['parent_id'] ?: null,
            ':position'  => (int)($data['position'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE menu_items SET label=:label, link_type=:link_type, target=:target,
             parent_id=:parent_id, position=:position WHERE id=:id"
        )->execute([
            ':label'     => $data['label'],
            ':link_type' => $data['link_type'] ?? 'none',
            ':target'    => $data['target'] ?: null,
            ':parent_id' => $data['parent_id'] ?: null,
            ':position'  => (int)($data['position'] ?? 0),
            ':id'        => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        // Detach children before deleting parent
        Database::get()->prepare("UPDATE menu_items SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        Database::get()->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
    }

    public static function getUrl(array $item): string
    {
        return match ($item['link_type']) {
            'page' => BASE_URL . '/p/' . ($item['target'] ?? ''),
            'url'  => $item['target'] ?? '#',
            default => '#',
        };
    }
}
