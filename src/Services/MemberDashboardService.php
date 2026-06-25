<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ExternalDatabase;
use App\Models\Joueur;
use App\Models\Participation;
use App\Helpers\ParticipationStatus;
use App\Models\Saison;
use PDO;

class MemberDashboardService
{
    /**
     * getKPIs($userId) : Calcule le nombre d'événements de la semaine, de la semaine pro,
     * et les "Actions requises" (où Participation est vide ou à "?").
     */
    public static function getKPIs(int $userId): array
    {
        $db = ExternalDatabase::get();
        $categories = Joueur::getCategories($userId);
        
        if (empty($categories)) {
            return [
                'this_week' => 0,
                'next_week' => 0,
                'action_required' => 0
            ];
        }

        // Récupérer toutes les manifestations futures pertinentes pour le membre
        $manifestations = Participation::getUpcomingForMember($userId, $categories);

        $thisWeekCount = 0;
        $nextWeekCount = 0;
        $actionRequiredCount = 0;

        // Calcul des dates limites pour cette semaine et la semaine prochaine
        // On considère une semaine du lundi au dimanche
        $today = new \DateTimeImmutable('today');
        
        // Cette semaine
        $startOfWeek = $today->modify('this week'); // Lundi de cette semaine
        $endOfWeek = $startOfWeek->modify('+6 days 23:59:59'); // Dimanche de cette semaine

        // Semaine prochaine
        $startOfNextWeek = $startOfWeek->modify('+7 days'); // Lundi de la semaine prochaine
        $endOfNextWeek = $startOfNextWeek->modify('+6 days 23:59:59'); // Dimanche de la semaine prochaine

        foreach ($manifestations as $m) {
            $eventDate = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $m['Date']);
            if (!$eventDate) {
                $eventDate = \DateTimeImmutable::createFromFormat('Y-m-d', substr($m['Date'], 0, 10));
            }

            if ($eventDate) {
                // Vérifier si c'est cette semaine
                if ($eventDate >= $startOfWeek && $eventDate <= $endOfWeek) {
                    $thisWeekCount++;
                }
                // Vérifier si c'est la semaine prochaine
                elseif ($eventDate >= $startOfNextWeek && $eventDate <= $endOfNextWeek) {
                    $nextWeekCount++;
                }
            }

            // Calcul des actions requises : Participation est absente, vide ou à "?" (ou "Ne sait pas encore", "?")
            $statusStr = $m['Participation'] ?? '';
            $status = new ParticipationStatus($statusStr);
            $category = $status->getCategory();

            if (empty($statusStr) || $statusStr === '.' || $category === 'unknown' || $category === 'no_response') {
                $actionRequiredCount++;
            }
        }

        return [
            'this_week' => $thisWeekCount,
            'next_week' => $nextWeekCount,
            'action_required' => $actionRequiredCount
        ];
    }

    /**
     * getImminentEvents($userId, $limit = 3) : Ramène les 3 prochains événements avec le statut actuel du joueur.
     */
    public static function getImminentEvents(int $userId, int $limit = 3): array
    {
        $categories = Joueur::getCategories($userId);
        if (empty($categories)) {
            return [];
        }

        $manifestations = Participation::getUpcomingForMember($userId, $categories);
        // Trier par date croissante au cas où ce n'est pas trié, et limiter à $limit
        usort($manifestations, function ($a, $b) {
            return strcmp($a['Date'], $b['Date']);
        });

        $imminent = array_slice($manifestations, 0, $limit);
        $result = [];

        foreach ($imminent as $m) {
            $normalized = AgendaService::normalizeManifestation($m);
            
            // On calcule l'heure d'affichage
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $m['Date']);
            $timeDisplay = ($dateObj && $dateObj->format('H:i') !== '00:00') ? $dateObj->format('H:i') : '';

            // Participation du joueur
            $statusStr = $m['user_status'] ?? '';
            $status = new ParticipationStatus($statusStr);
            $category = $status->getCategory();

            // Règle métier : Équipe OK (seulement pour les matchs)
            $enoughPlayers = false;
            if ($normalized['is_match']) {
                $stats = AgendaService::getParticipationStats((int)$m['id_manifestation']);
                $enoughPlayers = $stats['enough_players'] ?? false;
            }

            // Enrichir avec les informations de l'adhérent connecté pour _event_card.twig
            $normalized['user_status'] = $statusStr;
            $normalized['user_status_category'] = $category;
            $normalized['user_status_icon'] = $status->getIcon();
            $normalized['user_status_color'] = $status->getBackgroundColor();
            $normalized['enough_players'] = $enoughPlayers;

            $result[] = $normalized;
        }

        return $result;
    }

    /**
     * getSeasonStats($userId) : Calcule le % de présence (Matchs vs Entraînements) et groupe par type et par lieu.
     */
    public static function getSeasonStats(int $userId): array
    {
        $db = ExternalDatabase::get();

        // Récupérer la saison active
        $saison = Saison::getActive();

        // 1. Calcul du taux de présence aux Matchs et Entraînements sur la saison courante (passée ou future)
        // Pour cela, on récupère les participations du joueur
        $sql = "
            SELECT m.ManifestationTypée, p.Participation
            FROM Participation p
            JOIN Manifestation m ON p.id_manifestation = m.id_manifestation
            WHERE p.id_joueur = ?
            and m.Date >= ? 
            and m.Date <= ?
            and Statut in ('Confirmé', 'Provisoire')
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $saison['date_debut'], $saison['date_fin'] ?: '9999-12-31']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalMatches = 0;
        $presentMatches = 0;
        $totalTrainings = 0;
        $presentTrainings = 0;
        $totalTournaments = 0;
        $presentTournaments = 0;

        $events_by_type = [
            'match' => 0,
            'training' => 0,
            'tournois' => 0,
            'others' => 0
        ];

        foreach ($rows as $row) {
            $typeStr = $row['ManifestationTypée'] ?? '';
            $isMatch = stripos($typeStr, 'match') !== false;
            $isTraining = stripos($typeStr, 'entra') !== false;
            $isTournament = stripos($typeStr, 'tournoi') !== false;

            $status = new ParticipationStatus($row['Participation'] ?? '');

            if ($isMatch) {
                $totalMatches++;
                if ($status->isAvailable() || $status->isSelected() || $status->isPresent()) {
                    $presentMatches++;
                }
                $events_by_type['match']++;
            } elseif ($isTraining) {
                $totalTrainings++;
                if ($status->isPresent()) {
                    $presentTrainings++;
                }
                $events_by_type['training']++;
            } elseif ($isTournament) {
                $totalTournaments++;
                if ($status->isPresent() || $status->isAvailable()) {
                    $presentTournaments++;
                }
                $events_by_type['tournois']++;            
            } else {
                $events_by_type['others']++;
            }
        }

        $presenceMatch = $totalMatches > 0 ? (int) round(($presentMatches / $totalMatches) * 100) : 0;
        $presenceTraining = $totalTrainings > 0 ? (int) round(($presentTrainings / $totalTrainings) * 100) : 0;
        $presenceTournament = $totalTournaments > 0 ? (int) round(($presentTournaments / $totalTournaments) * 100) : 0;

        // 2. Classement des lieux (top 3)
        $categories = Joueur::getCategories($userId);
        $topLieux = [];

        if (!empty($categories)) {
            // Récupérer les manifestations pertinentes à venir pour connaître les lieux futurs fréquents
            $upcoming = Participation::getUpcomingForMember($userId, $categories);
            $lieuxCounts = [];
            foreach ($upcoming as $m) {
                $lieu = trim($m['Lieu'] ?? '');
                if ($lieu !== '') {
                    $lieuxCounts[$lieu] = ($lieuxCounts[$lieu] ?? 0) + 1;
                }
            }

            arsort($lieuxCounts);
            $limit = 3;
            foreach ($lieuxCounts as $lieu => $count) {
                if ($limit-- <= 0) break;
                $topLieux[] = [
                    'nom' => $lieu,
                    'count' => $count
                ];
            }
        }

        return [
            'presence_match' => $presenceMatch,
            'presence_training' => $presenceTraining,
            'presence_tournament' => $presenceTournament,
            'events_by_type' => $events_by_type,
            'top_lieux' => $topLieux
        ];
    }
}