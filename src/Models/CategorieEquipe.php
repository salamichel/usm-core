<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\HtmlHelper;
use App\Services\SlugManager;

class CategorieEquipe
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM categories_equipes ORDER BY ordre ASC, nom ASC")
            ->fetchAll();
    }

    public static function allKeyedByNom(): array
    {
        $result = [];
        foreach (self::all() as $row) {
            $result[$row['nom']] = $row;
        }
        return $result;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM categories_equipes WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByNom(string $nom): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM categories_equipes WHERE nom = ? LIMIT 1"
        );
        $stmt->execute([$nom]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        foreach (self::all() as $row) {
            if (SlugManager::generate($row['nom']) === $slug) {
                return $row;
            }
        }

        return null;
    }

    public static function allKeyedBySlug(): array
    {
        $result = [];
        foreach (self::all() as $row) {
            $result[SlugManager::generate($row['nom'])] = $row;
        }
        return $result;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO categories_equipes (nom, description, ordre)
             VALUES (:nom, :description, :ordre)"
        );

        $stmt->execute([
            ':nom'         => $data['nom'],
            ':description' => HtmlHelper::nullIfEmptyHtml($data['description'] ?? null),
            ':ordre'       => (int)($data['ordre'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE categories_equipes
             SET nom = :nom, description = :description, ordre = :ordre
             WHERE id = :id"
        )->execute([
            ':nom'         => $data['nom'],
            ':description' => HtmlHelper::nullIfEmptyHtml($data['description'] ?? null),
            ':ordre'       => (int)($data['ordre'] ?? 0),
            ':id'          => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare(
            "DELETE FROM categories_equipes WHERE id = ?"
        )->execute([$id]);
    }
}
