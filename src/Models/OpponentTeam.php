<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class OpponentTeam
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM opponent_teams ORDER BY name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function allActive(): array
    {
        return Database::get()
            ->query("SELECT * FROM opponent_teams WHERE is_active = 1 ORDER BY name ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function pendingReview(): array
    {
        return Database::get()
            ->query("SELECT * FROM opponent_teams WHERE needs_review = 1 ORDER BY created_at DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM opponent_teams WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM opponent_teams WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::get();
        $stmt = $db->prepare("
            INSERT INTO opponent_teams (name, club, city, is_active, needs_review, created_by_joueur_id)
            VALUES (:name, :club, :city, :is_active, :needs_review, :created_by_joueur_id)
        ");
        $stmt->execute([
            ':name'                 => trim($data['name']),
            ':club'                 => !empty($data['club']) ? trim($data['club']) : null,
            ':city'                 => !empty($data['city']) ? trim($data['city']) : null,
            ':is_active'            => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ':needs_review'         => isset($data['needs_review']) ? (int)$data['needs_review'] : 0,
            ':created_by_joueur_id' => !empty($data['created_by_joueur_id']) ? (int)$data['created_by_joueur_id'] : null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::get()->prepare("
            UPDATE opponent_teams
            SET name = :name,
                club = :club,
                city = :city,
                is_active = :is_active,
                needs_review = :needs_review
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'         => trim($data['name']),
            ':club'         => !empty($data['club']) ? trim($data['club']) : null,
            ':city'         => !empty($data['city']) ? trim($data['city']) : null,
            ':is_active'    => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ':needs_review' => isset($data['needs_review']) ? (int)$data['needs_review'] : 0,
            ':id'           => $id,
        ]);
    }

    public static function validate(int $id): void
    {
        $stmt = Database::get()->prepare("UPDATE opponent_teams SET needs_review = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::get()->prepare("DELETE FROM opponent_teams WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Fusionne l'équipe $fromId dans $toId.
     * Réeffectue les résultats de match, puis supprime ou archive la source.
     */
    public static function merge(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            return;
        }

        $db = Database::get();
        // Réaffecter tous les résultats rattachés à fromId vers toId
        $stmt = $db->prepare("UPDATE match_results SET opponent_team_id = ? WHERE opponent_team_id = ?");
        $stmt->execute([$toId, $fromId]);

        // Désactiver ou supprimer l'équipe source
        $stmtDel = $db->prepare("DELETE FROM opponent_teams WHERE id = ?");
        $stmtDel->execute([$fromId]);
    }
}
