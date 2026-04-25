<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Saison
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM saisons ORDER BY created_at DESC")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM saisons WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getActive(): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM saisons WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO saisons (libelle, is_active) VALUES (:libelle, :is_active)"
        );
        $stmt->execute([
            ':libelle'   => $data['libelle'],
            ':is_active' => (int)($data['is_active'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function activate(int $id): void
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE saisons SET is_active = 0")->execute();
            $db->prepare("UPDATE saisons SET is_active = 1 WHERE id = ?")->execute([$id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM saisons WHERE id = ?")->execute([$id]);
    }

    public static function snapshotCount(int $id): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM joueur_snapshots WHERE saison_id = ?"
        );
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
}
