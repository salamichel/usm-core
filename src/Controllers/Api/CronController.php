<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\ScheduledJob;
use App\Models\ScheduledJobLog;
use App\Models\SiteConfig;
use App\Services\Agenda\EventNotificationService;
use App\Services\Logger;
use App\Core\Database;

class CronController
{
    /**
     * Tâche planifiée hebdomadaire de rappel de présence.
     * Route: GET /api/cron/weekly-presence
     */
    public function weeklyPresence(): void
    {
        header('Content-Type: application/json');

        // Récupérer le token de sécurité
        $configuredToken = defined('CRON_SECURITY_TOKEN') ? CRON_SECURITY_TOKEN : '';
        $providedToken = $_GET['token'] ?? '';

        if (empty($configuredToken) || $providedToken !== $configuredToken) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Accès interdit. Token invalide.']);
            exit;
        }

        $startTime = microtime(true);
        try {
            $sentCount = EventNotificationService::sendWeeklyNotifications();
            $durationMs = (int)round((microtime(true) - $startTime) * 1000);
            
            ScheduledJobLog::log(null, 'weekly_presence', 'success', "Rappel hebdomadaire direct : $sentCount e-mail(s) envoyé(s)", $durationMs);

            Logger::app()->info('Weekly presence cron executed successfully', [
                'emails_sent' => $sentCount
            ]);

            echo json_encode([
                'ok' => true,
                'emails_sent' => $sentCount
            ]);
        } catch (\Throwable $e) {
            $durationMs = (int)round((microtime(true) - $startTime) * 1000);
            ScheduledJobLog::log(null, 'weekly_presence', 'failed', $e->getMessage(), $durationMs);

            Logger::errors()->error('Failed to run weekly presence cron', [
                'error' => $e->getMessage()
            ]);

            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Erreur interne lors de l\'envoi des notifications.'
            ]);
        }
        exit;
    }

    /**
     * Déclenchement Lazy Cron asynchrone depuis le navigateur d'un visiteur réel.
     * Route: POST /api/cron/lazy-trigger
     */
    public function lazyTrigger(): void
    {
        header('Content-Type: application/json');

        $now = time();
        $lastExecution = (int)(SiteConfig::get('last_cron_execution', '0'));
        $minIntervalSeconds = 180; // 3 minutes d'intervalle minimum

        if (($now - $lastExecution) < $minIntervalSeconds) {
            echo json_encode([
                'ok' => true,
                'skipped' => true,
                'message' => 'Tâche ignorée (délai de 3 minutes non écoulé).',
                'last_execution' => date('Y-m-d H:i:s', $lastExecution)
            ]);
            exit;
        }

        // Mettre à jour l'horodatage de la dernière exécution
        SiteConfig::set('last_cron_execution', (string)$now);

        $processedJobsCount = $this->runPendingJobs();

        echo json_encode([
            'ok' => true,
            'skipped' => false,
            'processed_jobs' => $processedJobsCount,
            'last_execution' => date('Y-m-d H:i:s', $now)
        ]);
        exit;
    }

    /**
     * Traite les tâches planifiées en attente (scheduled_jobs).
     */
    public function runPendingJobs(int $limit = 5): int
    {
        $jobs = ScheduledJob::getPendingJobsToExecute($limit);
        $executed = 0;

        foreach ($jobs as $job) {
            $startTime = microtime(true);
            ScheduledJob::updateStatus((int)$job['id'], 'running');

            try {
                $payload = json_decode($job['payload'] ?? '{}', true) ?: [];
                $details = "Tâche exécutée avec succès.";

                switch ($job['action']) {
                    case 'weekly_presence':
                        $sentCount = EventNotificationService::sendWeeklyNotifications();
                        $details = "Rappel hebdomadaire : $sentCount e-mail(s) envoyé(s).";
                        break;

                    case 'cleanup_logs':
                        $stmt = Database::get()->prepare("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                        $stmt->execute();
                        $cleanedEmails = $stmt->rowCount();
                        
                        $stmtLogs = Database::get()->prepare("DELETE FROM scheduled_job_logs WHERE executed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                        $stmtLogs->execute();
                        $cleanedLogs = $stmtLogs->rowCount();

                        $details = "Nettoyage : $cleanedEmails e-mail log(s) et $cleanedLogs trace(s) purgé(s).";
                        break;

                    case 'event_reminder':
                        if (!empty($payload['event_id'])) {
                            $details = "Rappel d'événement ID {$payload['event_id']} traité.";
                        }
                        break;

                    default:
                        $details = "Action personnalisée '{$job['action']}' exécutée.";
                        break;
                }

                $durationMs = (int)round((microtime(true) - $startTime) * 1000);

                // Enregistrer le résultat dans le journal des traces
                ScheduledJobLog::log((int)$job['id'], $job['action'], 'success', $details, $durationMs);

                // Mettre à jour ou faire avancer la tâche récurrente
                ScheduledJob::completeOrAdvanceJob(
                    (int)$job['id'],
                    $job['frequency'] ?? 'once',
                    $job['execute_at'],
                    $job['end_at'] ?? null
                );
                
                $executed++;

                Logger::app()->info("Scheduled job {$job['id']} executed successfully", [
                    'action' => $job['action'],
                    'frequency' => $job['frequency'] ?? 'once',
                    'duration_ms' => $durationMs
                ]);
            } catch (\Throwable $e) {
                $durationMs = (int)round((microtime(true) - $startTime) * 1000);

                // Enregistrer l'échec dans le journal des traces
                ScheduledJobLog::log((int)$job['id'], $job['action'], 'failed', $e->getMessage(), $durationMs);

                ScheduledJob::updateStatus((int)$job['id'], 'failed', $e->getMessage());

                Logger::errors()->error("Scheduled job {$job['id']} failed", [
                    'action' => $job['action'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $executed;
    }
}
