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

    /**
     * Envoie les notifications de création d'un événement aux adhérents concernés et abonnés.
     *
     * @param array $event L'événement brut de la table Manifestation
     */
    public static function sendCreationNotifications(array $event): void
    {
        $saison = \App\Models\Saison::getActive();
        if (!$saison) {
            return;
        }
        $saisonId = (int)$saison['id'];

        $recipients = EventTargetingService::getEligibleAndSubscribedPlayersForCreation($event, $saisonId);
        if (empty($recipients)) {
            return;
        }

        $brevo = new BrevoService();
        foreach ($recipients as $recipient) {
            $playerDb = $recipient['player_db'];
            $targetLabel = $recipient['target_label'];
            try {
                $brevo->sendEventCreationNotification($playerDb, $event, $targetLabel);
            } catch (\Throwable $e) {
                Logger::errors()->error('Failed to send event creation notification email', [
                    'player_id' => $playerDb['id_joueur'] ?? null,
                    'event_id'  => $event['id_manifestation'] ?? null,
                    'error'     => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Envoie le rappel hebdomadaire de présence aux joueurs concernés.
     *
     * @return int Nombre d'e-mails envoyés
     */
    public static function sendWeeklyNotifications(): int
    {
        $saison = \App\Models\Saison::getActive();
        if (!$saison) {
            return 0;
        }
        $saisonId = (int)$saison['id'];

        $start = date('Y-m-d') . ' 00:00:00';
        $end = date('Y-m-d', strtotime('+7 days')) . ' 23:59:59';

        $allPlayers = \App\Models\JoueurSnapshot::findBySaison($saisonId);
        if (empty($allPlayers)) {
            return 0;
        }

        $brevo = new BrevoService();
        $emailsSent = 0;

        foreach ($allPlayers as $playerSnap) {
            $playerId = (int)$playerSnap['id_joueur'];

            if (!\App\Models\MemberEmailPreference::isSubscribed($playerId, $saisonId, 'weekly_presence')) {
                continue;
            }

            // Récupérer exactement les événements ciblés pour ce joueur sur les 7 prochains jours
            $playerEvents = EventTargetingService::getUpcomingForPlayer($playerId, $start, $end, false);

            if (empty($playerEvents)) {
                continue;
            }

            // Normaliser le champ current_status pour le template d'email
            foreach ($playerEvents as &$pEvent) {
                $pEvent['current_status'] = $pEvent['user_status'] ?? null;
            }
            unset($pEvent);

            $playerDb = \App\Models\Joueur::findById($playerId);
            if ($playerDb && !empty($playerDb['Mel'])) {
                try {
                    $success = $brevo->sendWeeklyPresenceNotification($playerDb, $playerEvents, $saison);
                    if ($success) {
                        $emailsSent++;
                    }
                } catch (\Throwable $e) {
                    Logger::errors()->error('Failed to send weekly presence notification email', [
                        'player_id' => $playerId,
                        'error'     => $e->getMessage()
                    ]);
                }
            }
        }

        return $emailsSent;
    }
}

