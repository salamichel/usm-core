<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EquipeSaisonJoueur
{
    /**
     * Joueurs membres de l'équipe-saison, triés Nom ASC.
     */
    public static function findByEquipeSaison(int $equipeSaisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT js.*
             FROM equipe_saison_joueur esj
             JOIN joueur_snapshots js ON js.id = esj.snapshot_id
             WHERE esj.equipe_saison_id = ?
             ORDER BY js.nom ASC, js.prenom ASC"
        );
        $stmt->execute([$equipeSaisonId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }
        return $rows;
    }

    public static function countByEquipeSaison(int $equipeSaisonId): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM equipe_saison_joueur WHERE equipe_saison_id = ?"
        );
        $stmt->execute([$equipeSaisonId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Snapshots de la saison NON encore membres de cette équipe-saison.
     */
    public static function getAvailableSnapshots(int $equipeSaisonId, int $saisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT js.*
             FROM joueur_snapshots js
             WHERE js.saison_id = ?
               AND js.id NOT IN (
                 SELECT snapshot_id FROM equipe_saison_joueur WHERE equipe_saison_id = ?
               )
             ORDER BY js.nom ASC, js.prenom ASC"
        );
        $stmt->execute([$saisonId, $equipeSaisonId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }
        return $rows;
    }

    public static function add(int $equipeSaisonId, int $snapshotId): void
    {
        Database::get()->prepare(
            "INSERT IGNORE INTO equipe_saison_joueur (equipe_saison_id, snapshot_id) VALUES (?, ?)"
        )->execute([$equipeSaisonId, $snapshotId]);
    }

    public static function remove(int $equipeSaisonId, int $snapshotId): void
    {
        Database::get()->prepare(
            "DELETE FROM equipe_saison_joueur WHERE equipe_saison_id = ? AND snapshot_id = ?"
        )->execute([$equipeSaisonId, $snapshotId]);
    }
}
