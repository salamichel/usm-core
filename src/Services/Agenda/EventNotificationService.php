<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Joueur;
use App\Services\BrevoService;
use App\Services\Logger;

class EventNotificationService
{
    /**
     * Envoie les notifications d'annulation aux joueurs concernés pour un événement donné.
     *
     * @param array $event Le tableau normalisé de l'événement (avec selected, present, etc.)
     */
    public static function sendCancellationNotifications(array $event): void
    {
        $type = $event['type'] ?? '';
        $isMatch = (mb_strtolower($type) === 'match');

        // Déterminer les joueurs à notifier
        // Pour les matchs : seuls les selected. Pour les autres événements : les presents.
        $playersToNotify = $isMatch ? ($event['selected'] ?? []) : ($event['present'] ?? []);

        if (empty($playersToNotify)) {
            return;
        }

        $brevo = new BrevoService();
        foreach ($playersToNotify as $playerInfo) {
            try {
                $playerId = (int)($playerInfo['id'] ?? 0);
                if ($playerId <= 0) {
                    continue;
                }

                $playerDb = Joueur::findById($playerId);
                if ($playerDb && !empty($playerDb['Mel'])) {
                    $brevo->sendMatchCancellationNotification($playerDb, $event);
                }
            } catch (\Throwable $e) {
                Logger::errors()->error('Failed to send event cancellation email', [
                    'player_id' => $playerInfo['id'] ?? null,
                    'event_id'  => $event['id'] ?? null,
                    'error'     => $e->getMessage()
                ]);
            }
        }
    }
}
