-- Migration: Create Email Logs
-- Description: Table locale pour enregistrer et suivre l'envoi de tous les e-mails.

CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    email_type VARCHAR(100) NOT NULL,
    sent_at DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL,
    error_message TEXT NULL,
    message_id VARCHAR(255) NULL,
    INDEX idx_recipient_email (recipient_email),
    INDEX idx_email_type (email_type),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
