<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Saison
{
    public static function all(): array
    {
        $rows = Database::get()
            ->query("SELECT * FROM saisons ORDER BY created_at DESC")
            ->fetchAll();

        $active = self::getActive();
        $activeId = $active ? (int)$active['id'] : null;

        foreach ($rows as &$row) {
            $row['is_active'] = ($activeId !== null && (int)$row['id'] === $activeId) ? 1 : 0;
        }
        unset($row);

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM saisons WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: null;

        if ($row) {
            $active = self::getActive();
            $activeId = $active ? (int)$active['id'] : null;
            $row['is_active'] = ($activeId !== null && (int)$row['id'] === $activeId) ? 1 : 0;
        }

        return $row;
    }

    public static function getActive(): ?array
    {
        $db = Database::get();
        
        // 1. Chercher la saison dont les bornes de dates incluent le jour actuel (inclusif)
        $stmt = $db->prepare("
            SELECT * FROM saisons 
            WHERE date_debut IS NOT NULL 
              AND date_fin IS NOT NULL 
              AND date_debut <= CURRENT_DATE() 
              AND date_fin >= CURRENT_DATE() 
            ORDER BY date_debut DESC, id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $saison = $stmt->fetch() ?: null;
        if ($saison) {
            $saison['is_active'] = 1;
            return $saison;
        }

        // 2. Repli : chercher la dernière saison par date de début
        $stmt = $db->prepare("
            SELECT * FROM saisons 
            ORDER BY date_debut DESC, id DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $saison = $stmt->fetch() ?: null;
        if ($saison) {
            $saison['is_active'] = 1;
        }
        return $saison;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO saisons (libelle, date_debut, date_fin) VALUES (:libelle, :date_debut, :date_fin)"
        );
        $stmt->execute([
            ':libelle'    => $data['libelle'],
            ':date_debut' => $data['date_debut'],
            ':date_fin'   => $data['date_fin'],
        ]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM saisons WHERE id = ?")->execute([$id]);
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE saisons
             SET libelle = :libelle,
                 date_debut = :date_debut,
                 date_fin = :date_fin
             WHERE id = :id"
        )->execute([
            ':libelle'    => $data['libelle'],
            ':date_debut' => $data['date_debut'],
            ':date_fin'   => $data['date_fin'],
            ':id'         => $id,
        ]);
    }

    public static function snapshotCount(int $id): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM joueur_snapshots WHERE saison_id = ?"
        );
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Number of licenciés in the given season that did not exist (by nlicence)
     * in any previous season. Falls back to snapshotCount if no previous season.
     */
    public static function newLicenciesCount(int $id): int
    {
        $db = Database::get();
        $prev = $db->prepare("SELECT id FROM saisons WHERE id < ? ORDER BY id DESC LIMIT 1");
        $prev->execute([$id]);
        $prevId = $prev->fetchColumn();
        if (!$prevId) {
            return self::snapshotCount($id);
        }
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM joueur_snapshots s
             WHERE s.saison_id = ?
               AND s.nlicence IS NOT NULL
               AND s.nlicence <> ''
               AND NOT EXISTS (
                 SELECT 1 FROM joueur_snapshots p
                 WHERE p.saison_id = ? AND p.nlicence = s.nlicence
               )"
        );
        $stmt->execute([$id, $prevId]);
        return (int)$stmt->fetchColumn();
    }
}
