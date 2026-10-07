<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class MatchResult
{
    public static function findByManifestation(int $manifestationId): ?array
    {
        $stmt = Database::get()->prepare("
            SELECT mr.*, ot.name AS opponent_name, ot.club AS opponent_club, ot.city AS opponent_city
            FROM match_results mr
            LEFT JOIN opponent_teams ot ON ot.id = mr.opponent_team_id
            WHERE mr.manifestation_id = ?
            LIMIT 1
        ");
        $stmt->execute([$manifestationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (!empty($row['set_details'])) {
            $row['set_details'] = json_decode($row['set_details'], true) ?: [];
        } else {
            $row['set_details'] = [];
        }

        return $row;
    }

    /**
     * Récupération groupée pour enrichir l'agenda (par liste d'id_manifestation).
     *
     * @param int[] $manifestationIds
     * @return array<int, array> Indexé par manifestation_id
     */
    public static function findByManifestations(array $manifestationIds): array
    {
        if (empty($manifestationIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($manifestationIds), '?'));
        $stmt = Database::get()->prepare("
            SELECT mr.*, ot.name AS opponent_name, ot.club AS opponent_club, ot.city AS opponent_city
            FROM match_results mr
            LEFT JOIN opponent_teams ot ON ot.id = mr.opponent_team_id
            WHERE mr.manifestation_id IN ($placeholders)
        ");
        $stmt->execute(array_values($manifestationIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            if (!empty($row['set_details'])) {
                $row['set_details'] = json_decode($row['set_details'], true) ?: [];
            } else {
                $row['set_details'] = [];
            }
            $results[(int)$row['manifestation_id']] = $row;
        }

        return $results;
    }

    /**
     * Enregistre ou met à jour le résultat d'un match.
     */
    public static function upsert(array $data): void
    {
        $db = Database::get();
        $setDetailsJson = !empty($data['set_details']) ? json_encode($data['set_details'], JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare("
            INSERT INTO match_results (
                manifestation_id, opponent_team_id, sets_for, sets_against,
                set_details, entered_by_joueur_id, entered_by_admin, entered_at
            )
            VALUES (
                :manifestation_id, :opponent_team_id, :sets_for, :sets_against,
                :set_details, :entered_by_joueur_id, :entered_by_admin, :entered_at
            )
            ON DUPLICATE KEY UPDATE
                opponent_team_id = VALUES(opponent_team_id),
                sets_for = VALUES(sets_for),
                sets_against = VALUES(sets_against),
                set_details = VALUES(set_details),
                entered_by_joueur_id = COALESCE(VALUES(entered_by_joueur_id), entered_by_joueur_id),
                entered_by_admin = VALUES(entered_by_admin),
                entered_at = COALESCE(VALUES(entered_at), entered_at),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':manifestation_id'     => (int)$data['manifestation_id'],
            ':opponent_team_id'     => (int)$data['opponent_team_id'],
            ':sets_for'             => isset($data['sets_for']) && $data['sets_for'] !== '' ? (int)$data['sets_for'] : null,
            ':sets_against'         => isset($data['sets_against']) && $data['sets_against'] !== '' ? (int)$data['sets_against'] : null,
            ':set_details'          => $setDetailsJson,
            ':entered_by_joueur_id' => !empty($data['entered_by_joueur_id']) ? (int)$data['entered_by_joueur_id'] : null,
            ':entered_by_admin'     => !empty($data['entered_by_admin']) ? 1 : 0,
            ':entered_at'           => !empty($data['entered_at']) ? $data['entered_at'] : (!empty($data['has_score']) ? date('Y-m-d H:i:s') : null),
        ]);
    }

    /**
     * Supprime le résultat associé à une manifestation.
     */
    public static function deleteByManifestation(int $manifestationId): void
    {
        $stmt = Database::get()->prepare("DELETE FROM match_results WHERE manifestation_id = ?");
        $stmt->execute([$manifestationId]);
    }
}
