-- Migration: Create Scheduled Job Logs
-- Description: Table locale pour enregistrer l'historique et les traces d'exécution de chaque tâche planifiée.

CREATE TABLE IF NOT EXISTS scheduled_job_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NULL,
    action VARCHAR(100) NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    executed_at DATETIME NOT NULL,
    duration_ms INT NOT NULL DEFAULT 0,
    details TEXT NULL,
    INDEX idx_job_id (job_id),
    INDEX idx_action (action),
    INDEX idx_executed_at (executed_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
