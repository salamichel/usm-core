<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\UploadPathManager;

class HomeBlock
{
    public static function allActive(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM home_blocks WHERE is_active = 1 ORDER BY position ASC, id ASC"
        );
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM home_blocks ORDER BY position ASC, id ASC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM home_blocks WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO home_blocks (titre, contenu, image, cta_label, cta_url, position, is_active)
             VALUES (:titre, :contenu, :image, :cta_label, :cta_url, :position, :is_active)"
        );
        $stmt->execute([
            ':titre'     => $data['titre'],
            ':contenu'   => $data['contenu'] ?? '',
            ':image'     => $data['image'] ?? null,
            ':cta_label' => $data['cta_label'] ?? null,
            ':cta_url'   => $data['cta_url'] ?? null,
            ':position'  => (int)($data['position'] ?? self::nextPosition()),
            ':is_active' => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE home_blocks
             SET titre=:titre, contenu=:contenu, image=:image,
                 cta_label=:cta_label, cta_url=:cta_url,
                 position=:position, is_active=:is_active
             WHERE id=:id"
        )->execute([
            ':titre'     => $data['titre'],
            ':contenu'   => $data['contenu'] ?? '',
            ':image'     => $data['image'] ?? null,
            ':cta_label' => $data['cta_label'] ?? null,
            ':cta_url'   => $data['cta_url'] ?? null,
            ':position'  => (int)($data['position'] ?? 0),
            ':is_active' => (int)($data['is_active'] ?? 0),
            ':id'        => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $block = self::find($id);
        if ($block && !empty($block['image'])) {
            $path = UploadPathManager::getFullPath($block['image']);
            if (file_exists($path)) {
                unlink($path);
            }
        }
        Database::get()->prepare("DELETE FROM home_blocks WHERE id = ?")->execute([$id]);
    }

    public static function nextPosition(): int
    {
        $max = (int)Database::get()
            ->query("SELECT COALESCE(MAX(position), 0) FROM home_blocks")
            ->fetchColumn();
        return $max + 10;
    }

    /**
     * Échange la position avec le bloc précédent (le plus proche dans l'ordre).
     */
    public static function moveUp(int $id): void
    {
        self::swapWithNeighbour($id, 'up');
    }

    /**
     * Échange la position avec le bloc suivant (le plus proche dans l'ordre).
     */
    public static function moveDown(int $id): void
    {
        self::swapWithNeighbour($id, 'down');
    }

    private static function swapWithNeighbour(int $id, string $direction): void
    {
        $db      = Database::get();
        $current = self::find($id);
        if (!$current) return;

        if ($direction === 'up') {
            $stmt = $db->prepare(
                "SELECT * FROM home_blocks
                 WHERE position < ? OR (position = ? AND id < ?)
                 ORDER BY position DESC, id DESC
                 LIMIT 1"
            );
        } else {
            $stmt = $db->prepare(
                "SELECT * FROM home_blocks
                 WHERE position > ? OR (position = ? AND id > ?)
                 ORDER BY position ASC, id ASC
                 LIMIT 1"
            );
        }

        $stmt->execute([(int)$current['position'], (int)$current['position'], $id]);
        $other = $stmt->fetch();
        if (!$other) return;

        $db->beginTransaction();
        try {
            $upd = $db->prepare("UPDATE home_blocks SET position = ? WHERE id = ?");
            $upd->execute([(int)$other['position'],   (int)$current['id']]);
            $upd->execute([(int)$current['position'], (int)$other['id']]);
            // Si les positions étaient identiques, on en force un cran d'écart
            if ((int)$current['position'] === (int)$other['position']) {
                $bump = $direction === 'up' ? -1 : 1;
                $db->prepare("UPDATE home_blocks SET position = position + ? WHERE id = ?")
                   ->execute([$bump, (int)$current['id']]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
