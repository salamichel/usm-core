-- Migration: Create Score Reminders Sent
-- Description: Table locale pour mémoriser les rappels de saisie de score envoyés aux capitaines (anti-spam et relances progressives)

CREATE TABLE IF NOT EXISTS score_reminders_sent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manifestation_id INT NOT NULL,
    reminder_no INT NOT NULL DEFAULT 1,
    sent_at DATETIME NOT NULL,
    UNIQUE KEY uniq_manif_reminder (manifestation_id, reminder_no),
    INDEX idx_manifestation_id (manifestation_id),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialiser la tâche récurrente quotidienne pour les scores si elle n'existe pas
INSERT INTO scheduled_jobs (action, payload, frequency, execute_at, status)
SELECT 'score_reminder', NULL, 'daily', CONCAT(CURDATE(), ' 09:00:00'), 'pending'
WHERE NOT EXISTS (
    SELECT 1 FROM scheduled_jobs WHERE action = 'score_reminder'
);
