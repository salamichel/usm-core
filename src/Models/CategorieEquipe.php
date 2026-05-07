<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

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

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO categories_equipes (nom, description, ordre)
             VALUES (:nom, :description, :ordre)"
        );

        $description = $data['description'] ?? null;
        if ($description === '<p><br></p>') {
            $description = null;
        }

        $stmt->execute([
            ':nom'         => $data['nom'],
            ':description' => $description,
            ':ordre'       => (int)($data['ordre'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $description = $data['description'] ?? null;
        if ($description === '<p><br></p>') {
            $description = null;
        }

        Database::get()->prepare(
            "UPDATE categories_equipes
             SET nom = :nom, description = :description, ordre = :ordre
             WHERE id = :id"
        )->execute([
            ':nom'         => $data['nom'],
            ':description' => $description,
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
