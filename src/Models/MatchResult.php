<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class MatchResult
{
    /**
     * Récupère le résultat consolidé (match simple ou plateau) pour une manifestation.
     */
    public static function findByManifestation(int $manifestationId): ?array
    {
        $res = self::findByManifestations([$manifestationId]);
        return $res[$manifestationId] ?? null;
    }

    /**
     * Récupère la liste brute de toutes les rencontres d'une manifestation.
     */
    public static function findAllByManifestation(int $manifestationId): array
    {
        $stmt = Database::get()->prepare("
            SELECT mr.*, ot.name AS opponent_name, ot.club AS opponent_club, ot.city AS opponent_city
            FROM match_results mr
            LEFT JOIN opponent_teams ot ON ot.id = mr.opponent_team_id
            WHERE mr.manifestation_id = ?
            ORDER BY mr.id ASC
        ");
        $stmt->execute([$manifestationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['set_details'])) {
                $row['set_details'] = json_decode($row['set_details'], true) ?: [];
            } else {
                $row['set_details'] = [];
            }
            if ($row['sets_for'] !== null && $row['sets_against'] !== null) {
                $row['has_score'] = true;
                $row['score_formatted'] = \App\Helpers\MatchResultLabel::formatScore((int)$row['sets_for'], (int)$row['sets_against']);
                $row['outcome'] = \App\Helpers\MatchResultLabel::getOutcome((int)$row['sets_for'], (int)$row['sets_against']);
                $row['outcome_label'] = \App\Helpers\MatchResultLabel::getOutcomeLabel((int)$row['sets_for'], (int)$row['sets_against']);
                $row['outcome_badge_classes'] = \App\Helpers\MatchResultLabel::getBadgeClasses((int)$row['sets_for'], (int)$row['sets_against']);
            } else {
                $row['has_score'] = false;
                $row['score_formatted'] = null;
                $row['outcome'] = null;
                $row['outcome_label'] = null;
                $row['outcome_badge_classes'] = null;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Récupération groupée pour enrichir l'agenda (par liste d'id_manifestation).
     * Gère aussi bien les matchs simples que les plateaux multi-équipes.
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
            ORDER BY mr.id ASC
        ");
        $stmt->execute(array_values($manifestationIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $mid = (int)$row['manifestation_id'];
            if (!empty($row['set_details'])) {
                $row['set_details'] = json_decode($row['set_details'], true) ?: [];
            } else {
                $row['set_details'] = [];
            }
            if ($row['sets_for'] !== null && $row['sets_against'] !== null) {
                $row['has_score'] = true;
                $row['score_formatted'] = \App\Helpers\MatchResultLabel::formatScore((int)$row['sets_for'], (int)$row['sets_against']);
                $row['outcome'] = \App\Helpers\MatchResultLabel::getOutcome((int)$row['sets_for'], (int)$row['sets_against']);
                $row['outcome_label'] = \App\Helpers\MatchResultLabel::getOutcomeLabel((int)$row['sets_for'], (int)$row['sets_against']);
                $row['outcome_badge_classes'] = \App\Helpers\MatchResultLabel::getBadgeClasses((int)$row['sets_for'], (int)$row['sets_against']);
            } else {
                $row['has_score'] = false;
                $row['score_formatted'] = null;
                $row['outcome'] = null;
                $row['outcome_label'] = null;
                $row['outcome_badge_classes'] = null;
            }
            $grouped[$mid][] = $row;
        }

        $results = [];
        foreach ($grouped as $mid => $encounters) {
            $opponentsNames = array_filter(array_map(fn($e) => $e['opponent_name'] ?? null, $encounters));
            $isPlateau = count($encounters) > 1;
            $allScoresEntered = true;
            $anyScoreEntered = false;

            foreach ($encounters as $enc) {
                if ($enc['has_score']) {
                    $anyScoreEntered = true;
                } else {
                    $allScoresEntered = false;
                }
            }

            if (!$isPlateau) {
                // Match simple
                $single = $encounters[0];
                $single['encounters'] = $encounters;
                $single['is_plateau'] = false;
                $single['opponents_names'] = array_values($opponentsNames);
                $single['all_scores_entered'] = $single['has_score'];
                $results[$mid] = $single;
            } else {
                // Plateau multi-équipes
                $oppLabel = implode(' & ', $opponentsNames);
                $summaryRecord = \App\Helpers\MatchResultLabel::formatSummaryRecord($encounters);
                $overallOutcome = \App\Helpers\MatchResultLabel::getOverallOutcome($encounters);
                $overallBadges = \App\Helpers\MatchResultLabel::getOverallBadgeClasses($encounters);

                $results[$mid] = [
                    'id'                    => $encounters[0]['id'],
                    'manifestation_id'      => $mid,
                    'is_plateau'            => true,
                    'encounters'            => $encounters,
                    'opponents_names'       => array_values($opponentsNames),
                    'opponent_name'         => $oppLabel,
                    'has_score'             => $anyScoreEntered,
                    'all_scores_entered'    => $allScoresEntered,
                    'summary_record'        => $summaryRecord,
                    'outcome'               => $overallOutcome,
                    'outcome_badge_classes' => $overallBadges,
                    'sets_for'              => null,
                    'sets_against'          => null,
                ];
            }
        }

        return $results;
    }

    /**
     * Synchronise la liste des adversaires d'une manifestation (pour un match ou un plateau).
     */
    public static function syncManifestationOpponents(int $manifestationId, array $opponentTeamIds, ?int $userId = null, bool $isAdmin = false): void
    {
        $opponentTeamIds = array_unique(array_filter(array_map('intval', $opponentTeamIds)));
        $currentEncounters = self::findAllByManifestation($manifestationId);
        $currentOpponentIds = array_map(fn($e) => (int)$e['opponent_team_id'], $currentEncounters);

        $db = Database::get();

        // 1. Supprimer les adversaires retirés
        foreach ($currentOpponentIds as $currId) {
            if (!in_array($currId, $opponentTeamIds, true)) {
                $stmtDel = $db->prepare("DELETE FROM match_results WHERE manifestation_id = ? AND opponent_team_id = ?");
                $stmtDel->execute([$manifestationId, $currId]);
            }
        }

        // 2. Ajouter les nouveaux adversaires
        foreach ($opponentTeamIds as $newId) {
            if (!in_array($newId, $currentOpponentIds, true)) {
                self::upsert([
                    'manifestation_id'     => $manifestationId,
                    'opponent_team_id'     => $newId,
                    'sets_for'             => null,
                    'sets_against'         => null,
                    'entered_by_joueur_id' => $userId,
                    'entered_by_admin'     => $isAdmin ? 1 : 0,
                ]);
            }
        }
    }

    /**
     * Enregistre ou met à jour le résultat d'un match (ou d'une rencontre de plateau).
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
     * Supprime tous les résultats associés à une manifestation.
     */
    public static function deleteByManifestation(int $manifestationId): void
    {
        $stmt = Database::get()->prepare("DELETE FROM match_results WHERE manifestation_id = ?");
        $stmt->execute([$manifestationId]);
    }
}
