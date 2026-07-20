-- Migration: Add frequency, end_at and last_run_at to scheduled_jobs
-- Description: Permet de gérer la récurrence des tâches planifiées (ponctuelles ou récurrentes).

ALTER TABLE scheduled_jobs
ADD COLUMN IF NOT EXISTS frequency ENUM('once', 'hourly', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'once' AFTER payload,
ADD COLUMN IF NOT EXISTS end_at DATETIME NULL DEFAULT NULL AFTER execute_at,
ADD COLUMN IF NOT EXISTS last_run_at DATETIME NULL DEFAULT NULL AFTER end_at;
