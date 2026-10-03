-- Migration: Create Event Reminders Sent
-- Description: Table locale pour mémoriser les rappels d'événements envoyés aux adhérents (anti-spam / idempotence J-2, J-1).

CREATE TABLE IF NOT EXISTS event_reminders_sent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    player_id INT NOT NULL,
    reminder_type VARCHAR(10) NOT NULL,
    sent_at DATETIME NOT NULL,
    UNIQUE KEY uniq_event_player_reminder (event_id, player_id, reminder_type),
    INDEX idx_event_id (event_id),
    INDEX idx_player_id (player_id),
    INDEX idx_reminder_type (reminder_type),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialiser la tâche récurrente quotidienne si elle n'existe pas
INSERT INTO scheduled_jobs (action, payload, frequency, execute_at, status)
SELECT 'event_reminder', NULL, 'daily', CONCAT(CURDATE(), ' 08:00:00'), 'pending'
WHERE NOT EXISTS (
    SELECT 1 FROM scheduled_jobs WHERE action = 'event_reminder'
);
