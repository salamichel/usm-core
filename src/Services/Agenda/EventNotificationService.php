<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Core\ExternalDatabase;
use App\Helpers\ParticipationStatus;
use App\Models\EquipeConfig;
use App\Models\EquipeSaison;
use App\Models\EquipeSaisonJoueur;
use App\Models\EventReminderSent;
use App\Models\Joueur;
use App\Models\JoueurSnapshot;
use App\Models\MemberEmailPreference;
use App\Models\MotsClef;
use App\Models\Saison;
use App\Services\BrevoService;
use App\Services\Logger;
use PDO;

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

    /**
     * Envoie les rappels d'événements (J-2 et J-1) aux joueurs concernés n'ayant pas renseigné leur disponibilité.
     *
     * @param int|null $specificEventId ID d'une manifestation spécifique ou null pour balayer J-2 et J-1
     * @return array{sent: int, j2: int, j1: int, skipped: int, events_count: int}
     */
    public static function sendEventReminders(?int $specificEventId = null): array
    {
        $stats = [
            'sent'         => 0,
            'j2'           => 0,
            'j1'           => 0,
            'skipped'      => 0,
            'events_count' => 0,
        ];

        $saison = Saison::getActive();
        if (!$saison) {
            return $stats;
        }
        $saisonId = (int)$saison['id'];

        $extDb = ExternalDatabase::get();
        if (!$extDb) {
            return $stats;
        }

        $todayStr = date('Y-m-d');
        $eventsToProcess = [];

        if ($specificEventId !== null && $specificEventId > 0) {
            $stmt = $extDb->prepare("SELECT * FROM Manifestation WHERE id_manifestation = ?");
            $stmt->execute([$specificEventId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (empty($row['Statut']) || stripos($row['Statut'], 'Annulé') === false)) {
                $manifType = (string)($row['ManifestationTypée'] ?? '');
                $isMatchOrPlateau = (stripos($manifType, 'match') !== false || stripos($manifType, 'plateau') !== false);
                if (!$isMatchOrPlateau) {
                    return $stats; // Uniquement Match ou Plateau (exclut les entraînements)
                }

                // Interdiction formelle de relancer sur des rencontres ou événements passés
                if (!empty($row['Date']) && strtotime((string)$row['Date']) <= time()) {
                    return $stats;
                }

                $eventDateStr = substr((string)$row['Date'], 0, 10);
                $diffDays = (int)round((strtotime($eventDateStr) - strtotime($todayStr)) / 86400);
                if ($diffDays <= 0) {
                    return $stats;
                }

                $reminderType = ($diffDays === 2) ? 'j-2' : (($diffDays === 1) ? 'j-1' : 'manual');
                $eventsToProcess[] = [
                    'row'           => $row,
                    'reminder_type' => $reminderType,
                    'diff_days'     => $diffDays,
                ];
            }
        } else {
            $j1Str = date('Y-m-d', strtotime('+1 day'));
            $j2Str = date('Y-m-d', strtotime('+2 days'));

            $stmt = $extDb->prepare("
                SELECT * FROM Manifestation
                WHERE (Statut IS NULL OR Statut NOT LIKE '%Annulé%')
                  AND (
                      ManifestationTypée LIKE '%Match%'
                      OR ManifestationTypée LIKE '%Plateau%'
                  )
                  AND (DATE(Date) = ? OR DATE(Date) = ?)
                  AND Date > NOW()
                ORDER BY Date ASC
            ");
            $stmt->execute([$j1Str, $j2Str]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                if (empty($row['Date']) || strtotime((string)$row['Date']) <= time()) {
                    continue; // Ignorer systématiquement les événements passés
                }

                $manifType = (string)($row['ManifestationTypée'] ?? '');
                if (stripos($manifType, 'match') === false && stripos($manifType, 'plateau') === false) {
                    continue; // Strictement Match ou Plateau
                }

                $eventDateStr = substr((string)$row['Date'], 0, 10);
                $diffDays = (int)round((strtotime($eventDateStr) - strtotime($todayStr)) / 86400);
                if ($diffDays === 2) {
                    $eventsToProcess[] = ['row' => $row, 'reminder_type' => 'j-2', 'diff_days' => 2];
                } elseif ($diffDays === 1) {
                    $eventsToProcess[] = ['row' => $row, 'reminder_type' => 'j-1', 'diff_days' => 1];
                }
            }
        }

        if (empty($eventsToProcess)) {
            return $stats;
        }

        $stats['events_count'] = count($eventsToProcess);
        $brevo = new BrevoService();

        // Pré-charger les équipes de la saison
        $activeTeams = EquipeConfig::allActive();

        foreach ($eventsToProcess as $item) {
            $row = $item['row'];
            $reminderType = $item['reminder_type'];
            $eventId = (int)$row['id_manifestation'];
            $normEvent = EventNormalizer::buildBaseFields($row);
            $manifType = (string)($row['ManifestationTypée'] ?? '');

            // 1. Récupérer les statuts de participations déjà enregistrés pour cette manifestation
            $stmtPart = $extDb->prepare("SELECT id_joueur, Participation FROM Participation WHERE id_manifestation = ?");
            $stmtPart->execute([$eventId]);
            $participations = $stmtPart->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            // 2. Déterminer les joueurs de l'équipe ciblée UNIQUEMENT si l'équipe est en sous-effectif
            $candidatePlayers = [];

            $matchingTeams = [];
            foreach ($activeTeams as $team) {
                $filter = $team['manifestation_filter'] ?: $team['libelle'];
                if ($filter && (str_contains($manifType, $filter) || str_contains($row['Lieu'] ?? '', $filter))) {
                    $matchingTeams[] = $team;
                }
            }

            foreach ($matchingTeams as $team) {
                $es = EquipeSaison::findBySaisonAndEquipe($saisonId, (int)$team['id']);
                if (!$es) {
                    continue;
                }
                $teamPlayers = EquipeSaisonJoueur::findByEquipeSaison((int)$es['id']);
                if (empty($teamPlayers)) {
                    continue;
                }

                $minRequired = (int)($team['min_players'] ?? 0);
                if ($minRequired <= 0) {
                    $minRequired = ParticipationStatsService::getMinPlayersRequired($manifType);
                }

                // Calculer le nombre de joueurs engagés (sélectionnés, disponibles ou présents)
                $committedCount = 0;
                foreach ($teamPlayers as $tp) {
                    $pid = (int)$tp['id_joueur'];
                    $rawStatus = (string)($participations[$pid] ?? '');
                    if ($rawStatus !== '') {
                        $statusObj = new ParticipationStatus($rawStatus);
                        $cat = $statusObj->getCategory();
                        if (in_array($cat, ['selected', 'available', 'available_if_needed', 'present'], true)) {
                            $committedCount++;
                        }
                    }
                }

                // RÈGLE MÉTIER : Envoi d'une relance UNIQUEMENT si l'équipe est en sous-effectif (< minRequired)
                if ($committedCount >= $minRequired) {
                    continue; // Effectif complet ou suffisant : aucune relance envoyée pour cette équipe
                }

                // L'équipe est en sous-effectif (< minRequired) : cibler ses joueurs
                foreach ($teamPlayers as $tp) {
                    $pid = (int)$tp['id_joueur'];
                    if ($pid > 0) {
                        $candidatePlayers[$pid] = [
                            'team_name' => $team['libelle'],
                            'pref_key'  => 'match',
                        ];
                    }
                }
            }

            if (empty($candidatePlayers)) {
                continue;
            }

            // 3. Récupérer les joueurs ayant déjà reçu ce rappel
            $alreadySent = EventReminderSent::getSentPlayerIds($eventId, $reminderType);

            // 4. Parcourir les candidats et envoyer si sans réponse
            foreach ($candidatePlayers as $playerId => $candInfo) {
                // Déjà reçu ce rappel (idempotence) ?
                if (in_array($playerId, $alreadySent, true)) {
                    $stats['skipped']++;
                    continue;
                }

                // Vérifier la préférence de notification
                if (!MemberEmailPreference::isSubscribed($playerId, $saisonId, $candInfo['pref_key'])) {
                    $stats['skipped']++;
                    continue;
                }

                // Vérifier si le joueur a déjà renseigné sa dispo
                $rawStatus = (string)($participations[$playerId] ?? '');
                $statusObj = new ParticipationStatus($rawStatus);
                $cat = $statusObj->getCategory();
                $hasAnswered = in_array($cat, ['selected', 'available', 'available_if_needed', 'present', 'unavailable', 'absent'], true);

                if ($hasAnswered) {
                    $stats['skipped']++;
                    continue;
                }

                // Récupérer les coordonnées du joueur
                $playerDb = Joueur::findById($playerId);
                if (!$playerDb || empty($playerDb['Mel'])) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $success = $brevo->sendMatchReminderNotification(
                        $playerDb,
                        $normEvent,
                        $candInfo['team_name'],
                        $reminderType
                    );

                    if ($success) {
                        EventReminderSent::markAsSent($eventId, $playerId, $reminderType);
                        $stats['sent']++;
                        if ($reminderType === 'j-2') {
                            $stats['j2']++;
                        } elseif ($reminderType === 'j-1') {
                            $stats['j1']++;
                        }
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['skipped']++;
                    Logger::errors()->error('Failed to send event reminder notification', [
                        'player_id'     => $playerId,
                        'event_id'      => $eventId,
                        'reminder_type' => $reminderType,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        }

        return $stats;
    }
}


