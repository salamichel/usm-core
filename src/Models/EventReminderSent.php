<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class EventReminderSent
{
    /**
     * Vérifie si un rappel (ex: 'j-2' ou 'j-1') a déjà été envoyé à un joueur pour un événement donné.
     */
    public static function hasBeenSent(int $eventId, int $playerId, string $reminderType): bool
    {
        $stmt = Database::get()->prepare("
            SELECT 1 FROM event_reminders_sent
            WHERE event_id = ? AND player_id = ? AND reminder_type = ?
            LIMIT 1
        ");
        $stmt->execute([$eventId, $playerId, $reminderType]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Enregistre un rappel envoyé avec succès (idempotent grâce à INSERT IGNORE).
     */
    public static function markAsSent(int $eventId, int $playerId, string $reminderType): void
    {
        $stmt = Database::get()->prepare("
            INSERT IGNORE INTO event_reminders_sent (event_id, player_id, reminder_type, sent_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$eventId, $playerId, $reminderType]);
    }

    /**
     * Retourne la liste des IDs des joueurs ayant déjà reçu le rappel spécifié pour un événement.
     *
     * @return int[]
     */
    public static function getSentPlayerIds(int $eventId, string $reminderType): array
    {
        $stmt = Database::get()->prepare("
            SELECT player_id FROM event_reminders_sent
            WHERE event_id = ? AND reminder_type = ?
        ");
        $stmt->execute([$eventId, $reminderType]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows ?: []);
    }

    /**
     * Purge les anciens enregistrements au-delà de $days jours.
     */
    public static function cleanupOldLogs(int $days = 90): int
    {
        $stmt = Database::get()->prepare("
            DELETE FROM event_reminders_sent
            WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
