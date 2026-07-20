<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Logger;

class ScheduledJob
{
    public const ACTIONS_MAP = [
        'weekly_presence' => 'Rappel hebdomadaire des présences',
        'event_reminder' => 'Rappel d\'événement / Match',
        'captain_reminder' => 'Relance saisie des scores (Capitaines)',
        'cleanup_logs' => 'Nettoyage des anciens logs',
        'custom' => 'Action personnalisée / API',
    ];

    public const FREQUENCIES_MAP = [
        'once' => 'Une seule fois (Ponctuel)',
        'hourly' => 'Toutes les heures',
        'daily' => 'Tous les jours',
        'weekly' => 'Toutes les semaines',
        'monthly' => 'Tous les mois',
    ];

    public static function create(
        string $action,
        ?array $payload,
        string $executeAt,
        string $frequency = 'once',
        ?string $endAt = null
    ): int {
        $payloadJson = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        if (!array_key_exists($frequency, self::FREQUENCIES_MAP)) {
            $frequency = 'once';
        }
        
        $stmt = Database::get()->prepare("
            INSERT INTO scheduled_jobs (action, payload, frequency, execute_at, end_at, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$action, $payloadJson, $frequency, $executeAt, $endAt ?: null]);
        return (int)Database::get()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM scheduled_jobs WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getPendingJobsToExecute(int $limit = 5): array
    {
        $stmt = Database::get()->prepare("
            SELECT * FROM scheduled_jobs
            WHERE status = 'pending' 
              AND execute_at <= NOW()
              AND (end_at IS NULL OR end_at >= NOW())
            ORDER BY execute_at ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getAll(int $limit = 50): array
    {
        $stmt = Database::get()->prepare("
            SELECT * FROM scheduled_jobs
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getCountsByStatus(): array
    {
        $rows = Database::get()
            ->query("SELECT status, COUNT(*) as count FROM scheduled_jobs GROUP BY status")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $counts = [
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        ];

        foreach ($rows as $row) {
            $st = $row['status'];
            $cnt = (int)$row['count'];
            if (isset($counts[$st])) {
                $counts[$st] = $cnt;
            }
            $counts['total'] += $cnt;
        }

        return $counts;
    }

    public static function updateStatus(int $id, string $status, ?string $errorMessage = null): void
    {
        $stmt = Database::get()->prepare("
            UPDATE scheduled_jobs
            SET status = ?, error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $errorMessage, $id]);
    }

    /**
     * Termine l'exécution d'un job : s'il est ponctuel, le marque comme completed.
     * S'il est récurrent, calcule la prochaine date et le remet en pending.
     */
    public static function completeOrAdvanceJob(int $id, string $frequency, ?string $executeAt, ?string $endAt): void
    {
        $now = date('Y-m-d H:i:s');

        if ($frequency === 'once' || empty($frequency)) {
            $stmt = Database::get()->prepare("
                UPDATE scheduled_jobs
                SET status = 'completed', last_run_at = ?, error_message = NULL
                WHERE id = ?
            ");
            $stmt->execute([$now, $id]);
            return;
        }

        // Calcul de la prochaine date d'exécution
        $baseTime = !empty($executeAt) ? strtotime($executeAt) : time();
        // S'assurer qu'on part de maintenant si la date passée est déjà ancienne
        if ($baseTime < time()) {
            $baseTime = time();
        }

        switch ($frequency) {
            case 'hourly':
                $nextTime = strtotime('+1 hour', $baseTime);
                break;
            case 'daily':
                $nextTime = strtotime('+1 day', $baseTime);
                break;
            case 'weekly':
                $nextTime = strtotime('+1 week', $baseTime);
                break;
            case 'monthly':
                $nextTime = strtotime('+1 month', $baseTime);
                break;
            default:
                $nextTime = strtotime('+1 day', $baseTime);
                break;
        }

        $nextExecuteAt = date('Y-m-d H:i:s', $nextTime);

        // Si une date de fin est définie et qu'elle est dépassée
        if (!empty($endAt) && strtotime($nextExecuteAt) > strtotime($endAt)) {
            $stmt = Database::get()->prepare("
                UPDATE scheduled_jobs
                SET status = 'completed', last_run_at = ?, error_message = NULL
                WHERE id = ?
            ");
            $stmt->execute([$now, $id]);
            return;
        }

        // Sinon, reprogrammer pour le prochain passage
        $stmt = Database::get()->prepare("
            UPDATE scheduled_jobs
            SET status = 'pending', execute_at = ?, last_run_at = ?, error_message = NULL
            WHERE id = ?
        ");
        $stmt->execute([$nextExecuteAt, $now, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::get()->prepare("DELETE FROM scheduled_jobs WHERE id = ?");
        $stmt->execute([$id]);
    }
}
