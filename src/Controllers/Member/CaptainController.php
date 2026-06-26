<?php
declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\View;
use App\Models\Saison;
use App\Models\EquipeSaisonJoueur;
use App\Models\Participation;
use App\Services\AgendaService;
use App\Services\Agenda\EventRepository;
use App\Services\Validator;
use App\Core\ExternalDatabase;

class CaptainController
{
    /**
     * Vérifie que l'utilisateur est connecté et capitaine pour la saison active.
     */
    private function checkAccess(): array
    {
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            View::flash('error', 'Veuillez vous connecter pour accéder à l\'espace capitaine.');
            header('Location: /member/login');
            exit;
        }

        $userId = (int) $_SESSION['LogInId'];
        $saisonActive = Saison::getActive();
        $captainedTeams = $saisonActive ? EquipeSaisonJoueur::findCaptainedTeams($userId, $saisonActive['id']) : [];

        if (empty($captainedTeams)) {
            View::flash('error', 'Accès interdit. Vous n\'êtes pas capitaine pour cette saison.');
            header('Location: /member/dashboard');
            exit;
        }

        return [$userId, $saisonActive, $captainedTeams];
    }

    /**
     * Dashboard du capitaine (liste des équipes et matchs).
     * Route: GET /member/captain
     */
    public function index(): void
    {
        [$userId, $saisonActive, $captainedTeams] = $this->checkAccess();

        $teamsData = [];
        foreach ($captainedTeams as $team) {
            $matches = AgendaService::getUpcomingMatchesForTeam(
                $team['slug_colonne'],
                50,
                $team['manifestation_filter'] ?? null
            );

            $rosterPlayers = EquipeSaisonJoueur::findByEquipeSaison($team['equipe_saison_id']);

            // Charger les détails de sélection et dispo pour chaque match
            foreach ($matches as &$match) {
                $db = ExternalDatabase::get();
                $stmt = $db->prepare("SELECT id_joueur, Participation FROM Participation WHERE id_manifestation = ?");
                $stmt->execute([$match['id']]);
                $participations = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

                $nbSelection = 0;
                $nbDisponible = 0;
                $nbIndisponible = 0;
                $nbSansReponse = 0;

                foreach ($rosterPlayers as $rp) {
                    $jid = (int)$rp['id_joueur'];
                    $status = $participations[$jid] ?? '';
                    $statusObj = new \App\Helpers\ParticipationStatus($status);

                    if ($statusObj->getCategory() === 'selected') {
                        $nbSelection++;
                    } elseif ($statusObj->getCategory() === 'available' || $statusObj->getCategory() === 'present') {
                        $nbDisponible++;
                    } elseif ($statusObj->getCategory() === 'unavailable' || $statusObj->getCategory() === 'absent') {
                        $nbIndisponible++;
                    } else {
                        $nbSansReponse++;
                    }
                }

                $match['nb_selection'] = $nbSelection;
                $match['nb_disponible'] = $nbDisponible;
                $match['nb_indisponible'] = $nbIndisponible;
                $match['nb_sans_reponse'] = $nbSansReponse;
                $match['total_roster'] = count($rosterPlayers);
            }
            unset($match);

            $teamsData[] = [
                'config' => $team,
                'matches' => $matches
            ];
        }

        View::render('member/captain/dashboard.twig', [
            'teams' => $teamsData,
            'saison' => $saisonActive
        ]);
    }

    /**
     * Formulaire de création d'un match.
     * Route: GET /member/captain/matches/create
     */
    public function createMatchForm(): void
    {
        [$userId, $saisonActive, $captainedTeams] = $this->checkAccess();

        View::render('member/captain/create_match.twig', [
            'teams' => $captainedTeams
        ]);
    }

    /**
     * Enregistre un match.
     * Route: POST /member/captain/matches/create
     */
    public function storeMatch(): void
    {
        [$userId, $saisonActive, $captainedTeams] = $this->checkAccess();

        $v = Validator::make($_POST)
            ->required('team_id', 'L\'équipe est requise.')
            ->required('date', 'La date et l\'heure sont requises.')
            ->required('location', 'Le lieu est requis.')
            ->maxLength('location', 80, 'Le lieu ne peut pas dépasser 80 caractères.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /member/captain/matches/create');
            exit;
        }

        $data = $v->getCleanData(['team_id', 'date', 'location', 'comment', 'duration']);
        
        $teamId = (int)$data['team_id'];
        $selectedTeam = null;
        foreach ($captainedTeams as $team) {
            if ((int)$team['id'] === $teamId) {
                $selectedTeam = $team;
                break;
            }
        }

        if (!$selectedTeam) {
            View::flash('error', 'Équipe sélectionnée invalide.');
            header('Location: /member/captain/matches/create');
            exit;
        }

        // Formater la date HTML datetime-local en SQL DATETIME
        $dateStr = str_replace('T', ' ', $data['date']);
        if (strlen($dateStr) === 16) {
            $dateStr .= ':00'; // ajouter les secondes
        }

        // Construire ManifestationTypée
        $filter = $selectedTeam['manifestation_filter'] ?: 'Match ' . $selectedTeam['libelle'];
        $manifestationType = "Disponibilités - Match - " . $filter;

        try {
            EventRepository::createMatch([
                'manifestation_type' => $manifestationType,
                'date' => $dateStr,
                'duration' => $data['duration'] ?: '2h',
                'location' => $data['location'],
                'comment' => $data['comment'] ?: null,
            ]);

            View::flash('success', 'Match créé avec succès et publié dans l\'agenda.');
            header('Location: /member/captain');
            exit;
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue lors de la création du match.');
            header('Location: /member/captain/matches/create');
            exit;
        }
    }

    /**
     * Formulaire de sélection/convocation des joueurs.
     * Route: GET /member/captain/matches/{id}/select-players
     */
    public function selectPlayersForm(array $params): void
    {
        [$userId, $saisonActive, $captainedTeams] = $this->checkAccess();
        $matchId = (int)$params['id'];

        $event = AgendaService::getEventById($matchId);
        if (!$event) {
            View::flash('error', 'Rencontre introuvable.');
            header('Location: /member/captain');
            exit;
        }

        // Vérifier que le match appartient à une équipe gérée par ce capitaine
        $matchedTeam = null;
        foreach ($captainedTeams as $team) {
            $filter = $team['manifestation_filter'] ?: $team['libelle'];
            if (str_contains($event['titre'] ?? '', $filter) || str_contains($event['ManifestationTypée'] ?? '', $filter)) {
                $matchedTeam = $team;
                break;
            }
        }

        if (!$matchedTeam) {
            View::flash('error', 'Vous n\'êtes pas autorisé à gérer les joueurs pour cette rencontre.');
            header('Location: /member/captain');
            exit;
        }

        // Récupérer les joueurs de l'équipe pour la saison
        $joueurs = EquipeSaisonJoueur::findByEquipeSaison($matchedTeam['equipe_saison_id']);

        // Récupérer les participations actuelles pour ce match
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("SELECT id_joueur, Participation FROM Participation WHERE id_manifestation = ?");
        $stmt->execute([$matchId]);
        $participations = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        // Enrichir la liste des joueurs
        foreach ($joueurs as &$j) {
            $jid = (int)$j['id_joueur'];
            $rawStatus = $participations[$jid] ?? '';
            $statusObj = new \App\Helpers\ParticipationStatus($rawStatus);
            
            $j['raw_status'] = $rawStatus;
            $j['status_category'] = $statusObj->getCategory();
            $j['status_label'] = $statusObj->getLabel();
            $j['is_selected'] = ($j['status_category'] === 'selected');
            $j['status_bg'] = $statusObj->getBackgroundColor();
            $j['status_text'] = $statusObj->getTextColor();
        }
        unset($j);

        // Séparer et trier par nom pour chaque genre (data.Sexe)
        $femmes = [];
        $hommes = [];
        foreach ($joueurs as $j) {
            $sexe = $j['data']['Sexe'] ?? 'F';
            if ($sexe === 'M') {
                $hommes[] = $j;
            } else {
                $femmes[] = $j;
            }
        }

        $sortByAvailabilityAndName = function ($a, $b) {
            $scoreA = match ($a['status_category']) {
                'selected' => 3,
                'present', 'available' => 2,
                'unknown', 'no_response' => 1,
                'unavailable', 'absent' => 0,
                default => 1,
            };
            $scoreB = match ($b['status_category']) {
                'selected' => 3,
                'present', 'available' => 2,
                'unknown', 'no_response' => 1,
                'unavailable', 'absent' => 0,
                default => 1,
            };
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA; // Plus grand score en premier
            }
            $nomA = trim(($a['nom'] ?? '') . ' ' . ($a['prenom'] ?? ''));
            $nomB = trim(($b['nom'] ?? '') . ' ' . ($b['prenom'] ?? ''));
            return strcasecmp($nomA, $nomB);
        };
        usort($femmes, $sortByAvailabilityAndName);
        usort($hommes, $sortByAvailabilityAndName);

        View::render('member/captain/select_players.twig', [
            'event' => $event,
            'team' => $matchedTeam,
            'joueurs' => $joueurs,
            'femmes' => $femmes,
            'hommes' => $hommes
        ]);
    }

    /**
     * Enregistre la sélection/convocation des joueurs.
     * Route: POST /member/captain/matches/{id}/select-players
     */
    public function updateSelectedPlayers(array $params): void
    {
        [$userId, $saisonActive, $captainedTeams] = $this->checkAccess();
        $matchId = (int)$params['id'];

        $event = AgendaService::getEventById($matchId);
        if (!$event) {
            View::flash('error', 'Rencontre introuvable.');
            header('Location: /member/captain');
            exit;
        }

        $matchedTeam = null;
        foreach ($captainedTeams as $team) {
            $filter = $team['manifestation_filter'] ?: $team['libelle'];
            if (str_contains($event['titre'] ?? '', $filter) || str_contains($event['ManifestationTypée'] ?? '', $filter)) {
                $matchedTeam = $team;
                break;
            }
        }

        if (!$matchedTeam) {
            View::flash('error', 'Action non autorisée.');
            header('Location: /member/captain');
            exit;
        }

        // Récupérer les joueurs du roster de l'équipe
        $joueurs = EquipeSaisonJoueur::findByEquipeSaison($matchedTeam['equipe_saison_id']);

        // Récupérer les participations actuelles pour ce match
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("SELECT id_joueur, Participation FROM Participation WHERE id_manifestation = ?");
        $stmt->execute([$matchId]);
        $participations = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        $selectedIds = array_map('intval', $_POST['selected_players'] ?? []);

        foreach ($joueurs as $j) {
            $jid = (int)$j['id_joueur'];
            $isSelected = in_array($jid, $selectedIds, true);
            $currentStatus = $participations[$jid] ?? '';
            $statusObj = new \App\Helpers\ParticipationStatus($currentStatus);

            if ($isSelected) {
                // Si pas déjà sélectionné, on le sélectionne et on mémorise sa dispo d'origine
                if ($statusObj->getCategory() !== 'selected') {
                    $orig = $currentStatus !== '' ? $currentStatus : 'Sans réponse';
                    $newStatus = "Sélectionné(e) ($orig)";
                    Participation::upsert($jid, $matchId, $newStatus);
                    $this->removeConcurrentParticipations($jid, $event);
                }
            } else {
                // Si dé-sélectionné (et qu'il était sélectionné), on restaure sa dispo d'origine
                if ($statusObj->getCategory() === 'selected') {
                    $orig = $statusObj->getOriginalStatus();
                    if ($orig === 'Sans réponse' || $orig === '') {
                        Participation::upsert($jid, $matchId, '');
                    } else {
                        Participation::upsert($jid, $matchId, $orig);
                    }
                }
            }
        }

        View::flash('success', 'Sélection des joueurs mise à jour avec succès.');
        header('Location: /member/captain');
        exit;
    }

    /**
     * Supprime la participation d'un joueur à tout événement concurrent (chevauchement horaire).
     */
    private function removeConcurrentParticipations(int $joueurId, array $event): void
    {
        $db = ExternalDatabase::get();
        
        $dateStr = $event['Date'] ?? $event['date'] ?? null;
        $idManifestation = $event['id_manifestation'] ?? $event['id'] ?? 0;
        
        // 1. Trouver les événements candidats dans un intervalle de ±1 jour
        $stmt = $db->prepare(
            "SELECT id_manifestation, Date, Durée_créneau 
             FROM Manifestation 
             WHERE Date >= DATE_SUB(?, INTERVAL 1 DAY) AND Date <= DATE_ADD(?, INTERVAL 1 DAY)
               AND id_manifestation != ?"
        );
        $stmt->execute([$dateStr, $dateStr, $idManifestation]);
        $candidates = $stmt->fetchAll();

        $eventRange = $this->getEventRange($event);
        $overlappingIds = [];

        foreach ($candidates as $cand) {
            $candRange = $this->getEventRange($cand);
            if ($this->checkOverlap($eventRange, $candRange)) {
                $overlappingIds[] = (int)$cand['id_manifestation'];
            }
        }

        // 2. Supprimer les participations pour les événements en chevauchement
        if (!empty($overlappingIds)) {
            $inClause = implode(',', $overlappingIds);
            $stmt = $db->prepare(
                "DELETE FROM Participation 
                 WHERE id_joueur = ? AND id_manifestation IN ($inClause)"
            );
            $stmt->execute([$joueurId]);
        }
    }

    private function getEventRange(array $event): array
    {
        $dateStr = $event['Date'] ?? $event['date'] ?? null;
        if (!$dateStr) {
            return [0, 0];
        }
        $start = strtotime($dateStr);
        if ($start === false) {
            return [0, 0];
        }
        $durationStr = $event['Durée_créneau'] ?? $event['duration'] ?? '2h';
        
        $hours = 2;
        $minutes = 0;
        
        if (!empty($durationStr)) {
            if (str_contains($durationStr, 'h')) {
                $parts = explode('h', $durationStr);
                $hours = (int)($parts[0] ?? 2);
                $minutes = isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : 0;
            } elseif (str_contains($durationStr, 'm')) {
                $hours = 0;
                $minutes = (int)str_replace('m', '', $durationStr);
            }
        }
        
        $end = strtotime("+{$hours} hour +{$minutes} minute", $start);
        return [$start, $end];
    }

    /**
     * Vérifie si deux plages de temps se chevauchent.
     */
    private function checkOverlap(array $rangeA, array $rangeB): bool
    {
        return $rangeA[0] < $rangeB[1] && $rangeB[0] < $rangeA[1];
    }
}
