<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EquipeConfig
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM equipes_config ORDER BY categorie ASC, ordre ASC")
            ->fetchAll();
    }

    public static function allActive(): array
    {
        return Database::get()
            ->query("SELECT * FROM equipes_config WHERE is_active = 1 ORDER BY categorie ASC, ordre ASC")
            ->fetchAll();
    }

    public static function groupedByCategorie(): array
    {
        $rows   = self::allActive();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['categorie']][] = $row;
        }
        return $result;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM equipes_config WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO equipes_config (slug_colonne, libelle, categorie, ordre, is_active)
             VALUES (:slug_colonne, :libelle, :categorie, :ordre, :is_active)"
        );
        $stmt->execute([
            ':slug_colonne' => $data['slug_colonne'],
            ':libelle'      => $data['libelle'],
            ':categorie'    => $data['categorie'],
            ':ordre'        => (int)($data['ordre'] ?? 0),
            ':is_active'    => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE equipes_config
             SET slug_colonne = :slug_colonne,
                 libelle      = :libelle,
                 categorie    = :categorie,
                 ordre        = :ordre,
                 is_active    = :is_active
             WHERE id = :id"
        )->execute([
            ':slug_colonne' => $data['slug_colonne'],
            ':libelle'      => $data['libelle'],
            ':categorie'    => $data['categorie'],
            ':ordre'        => (int)($data['ordre'] ?? 0),
            ':is_active'    => (int)($data['is_active'] ?? 1),
            ':id'           => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM equipes_config WHERE id = ?")->execute([$id]);
    }
}
