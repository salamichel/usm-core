<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class ScoreReminderSent
{
    /**
     * Vérifie si un rappel (numéro 1, 2 ou 3) a déjà été envoyé pour cette manifestation.
     */
    public static function hasBeenSent(int $manifestationId, int $reminderNo): bool
    {
        $stmt = Database::get()->prepare("
            SELECT 1 FROM score_reminders_sent
            WHERE manifestation_id = ? AND reminder_no = ?
            LIMIT 1
        ");
        $stmt->execute([$manifestationId, $reminderNo]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Retourne le dernier numéro de rappel envoyé et sa date d'envoi.
     *
     * @return array{reminder_no: int, sent_at: string}|null
     */
    public static function getLastReminder(int $manifestationId): ?array
    {
        $stmt = Database::get()->prepare("
            SELECT reminder_no, sent_at
            FROM score_reminders_sent
            WHERE manifestation_id = ?
            ORDER BY reminder_no DESC
            LIMIT 1
        ");
        $stmt->execute([$manifestationId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Enregistre un rappel envoyé (idempotent grâce à INSERT IGNORE).
     */
    public static function markAsSent(int $manifestationId, int $reminderNo): void
    {
        $stmt = Database::get()->prepare("
            INSERT IGNORE INTO score_reminders_sent (manifestation_id, reminder_no, sent_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$manifestationId, $reminderNo]);
    }

    /**
     * Purge les anciens enregistrements au-delà de $days jours.
     */
    public static function cleanupOldLogs(int $days = 180): int
    {
        $stmt = Database::get()->prepare("
            DELETE FROM score_reminders_sent
            WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
