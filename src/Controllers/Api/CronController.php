<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Agenda\EventNotificationService;
use App\Services\Logger;

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

        try {
            $sentCount = EventNotificationService::sendWeeklyNotifications();
            
            Logger::app()->info('Weekly presence cron executed successfully', [
                'emails_sent' => $sentCount
            ]);

            echo json_encode([
                'ok' => true,
                'emails_sent' => $sentCount
            ]);
        } catch (\Throwable $e) {
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
}
