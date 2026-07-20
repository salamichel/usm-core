<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Logger;

class ScheduledJobLog
{
    public static function log(
        ?int $jobId,
        string $action,
        string $status,
        ?string $details = null,
        int $durationMs = 0
    ): int {
        try {
            $stmt = Database::get()->prepare("
                INSERT INTO scheduled_job_logs (job_id, action, status, executed_at, duration_ms, details)
                VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$jobId, $action, $status, $durationMs, $details]);
            return (int)Database::get()->lastInsertId();
        } catch (\Throwable $e) {
            Logger::errors()->error('Failed to insert scheduled job log', [
                'error' => $e->getMessage(),
                'action' => $action
            ]);
            return 0;
        }
    }

    public static function getRecentLogs(int $limit = 50): array
    {
        $stmt = Database::get()->prepare("
            SELECT l.*, j.frequency
            FROM scheduled_job_logs l
            LEFT JOIN scheduled_jobs j ON l.job_id = j.id
            ORDER BY l.executed_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getByJobId(int $jobId, int $limit = 20): array
    {
        $stmt = Database::get()->prepare("
            SELECT * FROM scheduled_job_logs
            WHERE job_id = ?
            ORDER BY executed_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $jobId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function purgeOldLogs(int $days = 90): int
    {
        $stmt = Database::get()->prepare("
            DELETE FROM scheduled_job_logs 
            WHERE executed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bindValue(1, $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
