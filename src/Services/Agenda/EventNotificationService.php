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

        $manifType = $event['ManifestationTypée'] ?? $event['manifestation_type'] ?? '';
        $parts = explode(' - ', $manifType, 3);
        $type = $parts[1] ?? ''; // Match / Entraînement / Vie du club ...
        $title = $parts[2] ?? $manifType;

        $isMatch = (mb_strtolower($type) === 'match');

        // Récupérer tous les snapshots de joueurs pour la saison active
        $allPlayers = \App\Models\JoueurSnapshot::findBySaison($saisonId);
        if (empty($allPlayers)) {
            return;
        }

        $brevo = new BrevoService();

        if ($isMatch) {
            // Déterminer l'équipe correspondante
            $activeTeams = \App\Models\EquipeConfig::allActive();
            $matchingTeams = [];
            foreach ($activeTeams as $team) {
                $filter = $team['manifestation_filter'];
                if ($filter && str_contains($manifType, $filter)) {
                    $matchingTeams[] = $team;
                }
            }

            if (empty($matchingTeams)) {
                return;
            }

            foreach ($matchingTeams as $team) {
                $es = \App\Models\EquipeSaison::findBySaisonAndEquipe($saisonId, $team['id']);
                if (!$es) {
                    continue;
                }

                $teamPlayers = \App\Models\EquipeSaisonJoueur::findByEquipeSaison($es['id']);
                foreach ($teamPlayers as $tp) {
                    $playerId = (int)$tp['id_joueur'];

                    if (\App\Models\MemberEmailPreference::isSubscribed($playerId, $saisonId, 'match')) {
                        $playerDb = \App\Models\Joueur::findById($playerId);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $brevo->sendEventCreationNotification($playerDb, $event, $team['libelle']);
                        }
                    }
                }
            }
        } else {
            // C'est un entraînement ou autre
            $trainingTypes = \App\Models\MotsClef::getTrainingTypes();
            $isTraining = false;
            $matchedTrainingType = null;

            foreach ($trainingTypes as $tt) {
                if ($manifType === $tt) {
                    $isTraining = true;
                    $matchedTrainingType = $tt;
                    break;
                }
            }

            if ($isTraining && $matchedTrainingType !== null) {
                foreach ($allPlayers as $playerSnap) {
                    $playerId = (int)$playerSnap['id_joueur'];
                    if (\App\Models\MemberEmailPreference::isSubscribed($playerId, $saisonId, $matchedTrainingType)) {
                        $playerDb = \App\Models\Joueur::findById($playerId);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $brevo->sendEventCreationNotification($playerDb, $event, $title);
                        }
                    }
                }
            } else {
                // Autres événements
                foreach ($allPlayers as $playerSnap) {
                    $playerId = (int)$playerSnap['id_joueur'];
                    $playerDb = \App\Models\Joueur::findById($playerId);
                    if ($playerDb && !empty($playerDb['Mel'])) {
                        $brevo->sendEventCreationNotification($playerDb, $event, 'Tous les adhérents');
                    }
                }
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

        $db = \App\Core\ExternalDatabase::get();
        $stmt = $db->prepare("
            SELECT * FROM Manifestation 
            WHERE Date >= ? AND Date <= ? AND (Statut IS NULL OR Statut NOT LIKE '%Annulé%')
            ORDER BY Date ASC
        ");
        $stmt->execute([$start, $end]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($events)) {
            return 0;
        }

        $allPlayers = \App\Models\JoueurSnapshot::findBySaison($saisonId);
        if (empty($allPlayers)) {
            return 0;
        }

        $activeTeams = [];
        foreach (\App\Models\EquipeConfig::allActive() as $tc) {
            $activeTeams[$tc['id']] = $tc;
        }

        $trainingTypes = \App\Models\MotsClef::getTrainingTypes();
        $brevo = new BrevoService();
        $emailsSent = 0;

        foreach ($allPlayers as $playerSnap) {
            $playerId = (int)$playerSnap['id_joueur'];

            if (!\App\Models\MemberEmailPreference::isSubscribed($playerId, $saisonId, 'weekly_presence')) {
                continue;
            }

            $playerTeams = \App\Models\EquipeSaisonJoueur::findEquipesByJoueur($playerId, $saisonId);
            $enrichedPlayerTeams = [];

            foreach ($playerTeams as $pt) {
                $teamId = (int)$pt['id'];
                if (isset($activeTeams[$teamId])) {
                    $enrichedPlayerTeams[] = $activeTeams[$teamId];
                }
            }

            $playerEvents = [];

            foreach ($events as $event) {
                $manifType = $event['ManifestationTypée'] ?? '';
                $parts = explode(' - ', $manifType, 3);
                $type = $parts[1] ?? '';
                $isMatch = (mb_strtolower($type) === 'match');

                $isConcerned = false;

                if ($isMatch) {
                    foreach ($enrichedPlayerTeams as $team) {
                        $filter = $team['manifestation_filter'];
                        if ($filter && str_contains($manifType, $filter)) {
                            $isConcerned = true;
                            break;
                        }
                    }
                } else {
                    $isTraining = false;
                    $matchedTrainingType = null;

                    foreach ($trainingTypes as $tt) {
                        if ($manifType === $tt) {
                            $isTraining = true;
                            $matchedTrainingType = $tt;
                            break;
                        }
                    }

                    if ($isTraining && $matchedTrainingType !== null) {
                        if (\App\Models\MemberEmailPreference::isSubscribed($playerId, $saisonId, $matchedTrainingType)) {
                            $isConcerned = true;
                        }
                    } else {
                        $isConcerned = true;
                    }
                }

                if ($isConcerned) {
                    $stmtStatus = $db->prepare("
                        SELECT Participation FROM Participation 
                        WHERE id_joueur = ? AND id_manifestation = ? 
                        LIMIT 1
                    ");
                    $stmtStatus->execute([$playerId, (int)$event['id_manifestation']]);
                    $currentStatus = $stmtStatus->fetchColumn();

                    $event['current_status'] = $currentStatus ?: null;
                    $playerEvents[] = $event;
                }
            }

            if (!empty($playerEvents)) {
                $playerDb = \App\Models\Joueur::findById($playerId);
                if ($playerDb && !empty($playerDb['Mel'])) {
                    $success = $brevo->sendWeeklyPresenceNotification($playerDb, $playerEvents, $saison);
                    if ($success) {
                        $emailsSent++;
                    }
                }
            }
        }

        return $emailsSent;
    }
}
