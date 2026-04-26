<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EquipeSaison
{
    public static function findOrCreate(int $equipeId, int $saisonId): array
    {
        $existing = self::findBySaisonAndEquipe($saisonId, $equipeId);
        if ($existing) return $existing;

        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT IGNORE INTO equipe_saison (equipe_id, saison_id) VALUES (?, ?)"
        );
        $stmt->execute([$equipeId, $saisonId]);

        return self::findBySaisonAndEquipe($saisonId, $equipeId);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM equipe_saison WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySaisonAndEquipe(int $saisonId, int $equipeId): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM equipe_saison WHERE saison_id = ? AND equipe_id = ? LIMIT 1"
        );
        $stmt->execute([$saisonId, $equipeId]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySaison(int $saisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM equipe_saison WHERE saison_id = ?"
        );
        $stmt->execute([$saisonId]);
        return $stmt->fetchAll();
    }

    public static function countWithMembersForSaison(int $saisonId): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(DISTINCT es.id)
               FROM equipe_saison es
               JOIN equipe_saison_joueur esj ON esj.equipe_saison_id = es.id
              WHERE es.saison_id = ?"
        );
        $stmt->execute([$saisonId]);
        return (int)$stmt->fetchColumn();
    }
}
