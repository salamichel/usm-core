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
use App\Services\BrevoService;
use App\Services\Logger;

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

        $filters = [
            'type'      => $_GET['type'] ?? '',
            'location'  => $_GET['location'] ?? '',
            'this_week' => !empty($_GET['this_week']),
            'next_week' => !empty($_GET['next_week']),
        ];

        $teamsData = [];
        foreach ($captainedTeams as $team) {
            $rosterPlayers = EquipeSaisonJoueur::findByEquipeSaison($team['equipe_saison_id']);
            $playerIds = array_map(fn($p) => (int)$p['id_joueur'], $rosterPlayers);

            // 1. Prochains matchs pour les cartes
            $matches = AgendaService::getUpcomingMatchesForTeam(
                $team['slug_colonne'],
                50,
                $team['manifestation_filter'] ?? null,
                $filters
            );

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
                    } elseif ($statusObj->getCategory() === 'available' || $statusObj->getCategory() === 'available_if_needed' || $statusObj->getCategory() === 'present') {
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

            // 2. Grille de présence et métriques de l'équipe
            $data = $this->buildTeamGridAndMetrics($team, $rosterPlayers, $playerIds, $filters);

            $teamsData[] = [
                'config' => $team,
                'matches' => $matches,
                'upcoming_events' => $data['upcoming_events'],
                'grid_players' => $data['grid_players'],
                'metrics' => $data['metrics']
            ];
        }

        View::render('member/captain/dashboard.twig', [
            'teams' => $teamsData,
            'saison' => $saisonActive,
            'filters' => $filters,
            'filterOptions' => AgendaService::getFilterOptions()
        ]);
    }

    /**
     * Formulaire de création d'un match.
     * Route: GET /member/captain/matches/create
     */
    public function createMatchForm(): void
    {
        [$userId, $saisonActive, $captainedTeams] = $this->checkAccess();

        $locations = \App\Services\Agenda\EventRepository::getKeywordsByCategory('Lieu');
        $durations = \App\Services\Agenda\EventRepository::getKeywordsByCategory('Durée_créneau');
        $statuses = \App\Services\Agenda\EventRepository::getKeywordsByCategory('Statut');

        View::render('member/captain/create_match.twig', [
            'teams'     => $captainedTeams,
            'locations' => $locations,
            'durations' => $durations,
            'statuses'  => $statuses
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
            ->required('duration', 'La durée est requise.')
            ->required('statut', 'Le statut est requis.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /member/captain/matches/create');
            exit;
        }

        $data = $v->getCleanData(['team_id', 'date', 'location', 'comment', 'duration', 'statut']);
        
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
                'duration' => $data['duration'],
                'location' => $data['location'],
                'comment' => $data['comment'] ?: null,
                'statut' => $data['statut']
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
     * Formulaire d'édition d'un match.
     * Route: GET /member/captain/matches/{id}/edit
     */
    public function editMatchForm(array $params): void
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
            View::flash('error', 'Vous n\'êtes pas autorisé à modifier cette rencontre.');
            header('Location: /member/captain');
            exit;
        }

        // Formater la date pour datetime-local (Y-m-d\TH:i)
        $dateValue = '';
        if (!empty($event['Date'])) {
            $dateValue = date('Y-m-d\TH:i', strtotime($event['Date']));
        }

        $locations = \App\Services\Agenda\EventRepository::getKeywordsByCategory('Lieu');
        $durations = \App\Services\Agenda\EventRepository::getKeywordsByCategory('Durée_créneau');
        $statuses = \App\Services\Agenda\EventRepository::getKeywordsByCategory('Statut');

        View::render('member/captain/edit_match.twig', [
            'event'     => $event,
            'date_value'=> $dateValue,
            'team'      => $matchedTeam,
            'locations' => $locations,
            'durations' => $durations,
            'statuses'  => $statuses
        ]);
    }

    /**
     * Enregistre les modifications d'un match.
     * Route: POST /member/captain/matches/{id}/edit
     */
    public function updateMatch(array $params): void
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
            View::flash('error', 'Vous n\'êtes pas autorisé à modifier cette rencontre.');
            header('Location: /member/captain');
            exit;
        }

        $v = Validator::make($_POST)
            ->required('date', 'La date et l\'heure sont requises.')
            ->required('location', 'Le lieu est requis.')
            ->required('duration', 'La durée est requise.')
            ->required('statut', 'Le statut est requis.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /member/captain/matches/' . $matchId . '/edit');
            exit;
        }

        $data = $v->getCleanData(['date', 'location', 'comment', 'duration', 'statut']);

        // Formater la date HTML datetime-local en SQL DATETIME
        $dateStr = str_replace('T', ' ', $data['date']);
        if (strlen($dateStr) === 16) {
            $dateStr .= ':00'; // ajouter les secondes
        }

        $wasCancelled = $event['annule'] ?? false;
        $selectedPlayers = $event['selectionnes'] ?? [];

        try {
            \App\Services\Agenda\EventRepository::updateMatch($matchId, [
                'date' => $dateStr,
                'duration' => $data['duration'],
                'location' => $data['location'],
                'comment' => $data['comment'] ?: null,
                'statut' => $data['statut']
            ]);

            // Notification d'annulation par email
            $isCancelledNow = str_contains($data['statut'], 'Annulé');
            if (!$wasCancelled && $isCancelledNow && !empty($selectedPlayers)) {
                $brevo = new BrevoService();
                foreach ($selectedPlayers as $selPlayer) {
                    try {
                        $playerDb = \App\Models\Joueur::findById((int)$selPlayer['id']);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $brevo->sendMatchCancellationNotification($playerDb, $event);
                        }
                    } catch (\Throwable $e) {
                        Logger::errors()->error('Failed to send match cancellation email', [
                            'player_id' => $selPlayer['id'],
                            'match_id' => $matchId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            View::flash('success', 'Match mis à jour avec succès.');
            header('Location: /member/captain');
            exit;
        } catch (\Exception $e) {
            View::flash('error', 'Une erreur est survenue lors de la mise à jour du match.');
            header('Location: /member/captain/matches/' . $matchId . '/edit');
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
                'present', 'available', 'available_if_needed' => 2,
                'unknown', 'no_response' => 1,
                'unavailable', 'absent' => 0,
                default => 1,
            };
            $scoreB = match ($b['status_category']) {
                'selected' => 3,
                'present', 'available', 'available_if_needed' => 2,
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

                    // Notification par email
                    try {
                        $playerDb = \App\Models\Joueur::findById($jid);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $brevo = new BrevoService();
                            $brevo->sendPlayerSelectionNotification($playerDb, $event);
                        }
                    } catch (\Throwable $e) {
                        Logger::errors()->error('Failed to send player selection email', [
                            'player_id' => $jid,
                            'match_id' => $matchId,
                            'error' => $e->getMessage()
                        ]);
                    }
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
        
        // 1. Trouver les événements candidats dans un intervalle de ±1 jour (non annulés)
        $stmt = $db->prepare(
            "SELECT id_manifestation, Date, Durée_créneau 
             FROM Manifestation 
             WHERE Date >= DATE_SUB(?, INTERVAL 1 DAY) AND Date <= DATE_ADD(?, INTERVAL 1 DAY)
               AND id_manifestation != ?
               AND (Statut IS NULL OR Statut NOT LIKE '%Annulé%')"
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

        // 2. Traiter les participations pour les événements en chevauchement
        if (!empty($overlappingIds)) {
            $inClause = implode(',', $overlappingIds);
            $stmt = $db->prepare(
                "SELECT id_manifestation, Participation 
                 FROM Participation 
                 WHERE id_joueur = ? AND id_manifestation IN ($inClause)"
            );
            $stmt->execute([$joueurId]);
            $currentParticipations = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

            foreach ($overlappingIds as $mid) {
                $currentStatus = isset($currentParticipations[$mid]) ? trim($currentParticipations[$mid]) : '';
                
                if ($currentStatus !== '') {
                    $statusObj = new \App\Helpers\ParticipationStatus($currentStatus);
                    if ($statusObj->isPresent() || $statusObj->isAvailable()) {
                        // Si le joueur avait mis une présence, on le marque absent
                        $newStatus = 'Absent';
                        if ($currentStatus === 'Oui') {
                            $newStatus = 'Non';
                        } elseif ($currentStatus === 'Disponible' || $currentStatus === 'Disponible si nécessaire') {
                            $newStatus = 'Indisponible';
                        } elseif ($currentStatus === 'Présent') {
                            $newStatus = 'Absent';
                        }
                        
                        $updateStmt = $db->prepare(
                            "UPDATE Participation 
                             SET Participation = ?, S_MAJ = NOW() 
                             WHERE id_joueur = ? AND id_manifestation = ?"
                        );
                        $updateStmt->execute([$newStatus, $joueurId, $mid]);
                    } else {
                        // Si le statut n'est pas une présence (ex: 'Ne sait pas' ou '.'), on le supprime
                        if ($statusObj->isUnknown() || $currentStatus === '.' || $currentStatus === '') {
                            $deleteStmt = $db->prepare(
                                "DELETE FROM Participation 
                                 WHERE id_joueur = ? AND id_manifestation = ?"
                            );
                            $deleteStmt->execute([$joueurId, $mid]);
                        }
                    }
                }
            }
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
     * Endpoint API pour la mise à jour directe de la participation d'un joueur par son capitaine.
     * Route: POST /api/captain/participation/update
     */
    public function apiUpdatePlayerParticipation(): void
    {
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Veuillez vous reconnecter.']);
            exit;
        }

        header('Content-Type: application/json');
        $userId = (int)$_SESSION['LogInId'];
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['joueur_id']) || !isset($input['manifestation_id']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Données de requête incorrectes.']);
            exit;
        }

        $joueurId = (int)$input['joueur_id'];
        $manifestationId = (int)$input['manifestation_id'];
        $status = trim((string)$input['status']);

        $saisonActive = Saison::getActive();
        $captainedTeams = $saisonActive ? EquipeSaisonJoueur::findCaptainedTeams($userId, $saisonActive['id']) : [];
        if (empty($captainedTeams)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Accès refusé. Vous n\'êtes pas capitaine.']);
            exit;
        }

        $event = AgendaService::getEventById($manifestationId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Événement introuvable.']);
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

        $isTraining = str_contains($event['type'] ?? '', 'Entra') || str_contains($event['ManifestationTypée'] ?? '', 'Entr');
        if (!$matchedTeam && $isTraining) {
            $matchedTeam = $captainedTeams[0];
        }

        if (!$matchedTeam) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Non autorisé pour cet événement.']);
            exit;
        }

        $rosterPlayers = EquipeSaisonJoueur::findByEquipeSaison($matchedTeam['equipe_saison_id']);
        $playerInRoster = false;
        foreach ($rosterPlayers as $rp) {
            if ((int)$rp['id_joueur'] === $joueurId) {
                $playerInRoster = true;
                break;
            }
        }

        if (!$playerInRoster) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Ce joueur ne fait pas partie de votre effectif.']);
            exit;
        }

        try {
            $db = ExternalDatabase::get();
            $currentStatus = '';
            $stmt = $db->prepare("SELECT Participation FROM Participation WHERE id_joueur = ? AND id_manifestation = ?");
            $stmt->execute([$joueurId, $manifestationId]);
            $currentStatus = $stmt->fetchColumn() ?: '';
            $statusObj = new \App\Helpers\ParticipationStatus($currentStatus);

            if ($status === 'selected') {
                if ($statusObj->getCategory() !== 'selected') {
                    $orig = $currentStatus !== '' ? $currentStatus : 'Sans réponse';
                    $newStatus = "Sélectionné(e) ($orig)";
                    Participation::upsert($joueurId, $manifestationId, $newStatus);
                    $this->removeConcurrentParticipations($joueurId, $event);

                    // Notification par email
                    try {
                        $playerDb = \App\Models\Joueur::findById($joueurId);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $brevo = new BrevoService();
                            $brevo->sendPlayerSelectionNotification($playerDb, $event);
                        }
                    } catch (\Throwable $e) {
                        Logger::errors()->error('Failed to send player selection email (API)', [
                            'player_id' => $joueurId,
                            'match_id' => $manifestationId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } elseif ($status === 'deselected') {
                if ($statusObj->getCategory() === 'selected') {
                    $orig = $statusObj->getOriginalStatus();
                    if ($orig === 'Sans réponse' || $orig === '') {
                        $db->prepare("DELETE FROM Participation WHERE id_joueur = ? AND id_manifestation = ?")->execute([$joueurId, $manifestationId]);
                    } else {
                        Participation::upsert($joueurId, $manifestationId, $orig);
                    }
                }
            } else {
                if ($status === '') {
                    $db->prepare("DELETE FROM Participation WHERE id_joueur = ? AND id_manifestation = ?")->execute([$joueurId, $manifestationId]);
                } else {
                    Participation::upsert($joueurId, $manifestationId, $status);
                }
            }

            // Recalculer la grille et metrics de l'équipe
            $playerIds = array_map(fn($p) => (int)$p['id_joueur'], $rosterPlayers);
            $data = $this->buildTeamGridAndMetrics($matchedTeam, $rosterPlayers, $playerIds);

            echo json_encode([
                'ok' => true,
                'message' => 'Participation mise à jour avec succès.',
                'team_id' => $matchedTeam['equipe_saison_id'],
                'grid_players' => $data['grid_players'],
                'metrics' => $data['metrics']
            ]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Construit et calcule la grille de présence et les métriques d'une équipe.
     */
    private function buildTeamGridAndMetrics(array $team, array $rosterPlayers, array $playerIds, array $filters = []): array
    {
        $limit = (!empty($filters['this_week']) || !empty($filters['next_week']) || !empty($filters['type']) || !empty($filters['location'])) ? 50 : 8;
        $upcomingEvents = AgendaService::getUpcomingEventsForTeam($team, $limit, $filters);
        $eventIds = array_column($upcomingEvents, 'id');

        $participationsMap = [];
        if (!empty($eventIds) && !empty($playerIds)) {
            $db = ExternalDatabase::get();
            $eventPlaceholders = implode(',', array_fill(0, count($eventIds), '?'));
            $playerPlaceholders = implode(',', array_fill(0, count($playerIds), '?'));
            
            $stmt = $db->prepare("
                SELECT id_joueur, id_manifestation, Participation 
                FROM Participation 
                WHERE id_manifestation IN ($eventPlaceholders) 
                  AND id_joueur IN ($playerPlaceholders)
            ");
            $params = array_merge($eventIds, $playerIds);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                $jid = (int)$row['id_joueur'];
                $mid = (int)$row['id_manifestation'];
                $participationsMap[$jid][$mid] = $row['Participation'];
            }
        }

        // Construire la grille croisée des joueurs
        $gridPlayers = [];
        foreach ($rosterPlayers as $player) {
            $jid = (int)$player['id_joueur'];
            $playerGrid = [
                'id_joueur' => $jid,
                'nom' => $player['nom'] ?? '',
                'prenom' => $player['prenom'] ?? '',
                'sexe' => $player['data']['Sexe'] ?? 'F',
                'is_captain' => (bool)($player['is_captain'] ?? false),
                'events' => []
            ];
            
            $nbSelected = 0;
            $nbAvailable = 0;
            $nbUnavailable = 0;
            $nbNoResponse = 0;
            
            foreach ($upcomingEvents as $event) {
                $mid = $event['id'];
                $rawStatus = $participationsMap[$jid][$mid] ?? '';
                $statusObj = new \App\Helpers\ParticipationStatus($rawStatus);
                $category = $statusObj->getCategory();
                
                if ($category === 'selected') {
                    $nbSelected++;
                } elseif ($category === 'available' || $category === 'available_if_needed' || $category === 'present') {
                    $nbAvailable++;
                } elseif ($category === 'unavailable' || $category === 'absent') {
                    $nbUnavailable++;
                } else {
                    $nbNoResponse++;
                }
                
                $playerGrid['events'][$mid] = [
                    'raw_status' => $rawStatus,
                    'category' => $category,
                    'label' => $statusObj->getLabel(),
                    'icon' => $statusObj->getIcon(),
                    'bg_class' => $statusObj->getBackgroundColor(),
                    'text_class' => $statusObj->getTextColor(),
                ];
            }
            
            $playerGrid['stats'] = [
                'selected' => $nbSelected,
                'available' => $nbAvailable,
                'unavailable' => $nbUnavailable,
                'no_response' => $nbNoResponse,
            ];
            
            $gridPlayers[] = $playerGrid;
        }

        // Calcul des metrics d'équipe
        $totalSlots = count($rosterPlayers) * count($upcomingEvents);
        $totalAnswered = 0;
        $understaffedMatches = 0;
        $eventAttendance = [];
        $trainingCount = 0;
        $trainingPresentCount = 0;
        $trainingSlotsCount = 0;

        foreach ($upcomingEvents as $event) {
            $mid = $event['id'];
            $availCount = 0;
            $selCount = 0;
            
            foreach ($rosterPlayers as $player) {
                $jid = (int)$player['id_joueur'];
                $rawStatus = $participationsMap[$jid][$mid] ?? '';
                $statusObj = new \App\Helpers\ParticipationStatus($rawStatus);
                $category = $statusObj->getCategory();
                
                if ($category !== 'no_response' && $rawStatus !== '') {
                    $totalAnswered++;
                }
                
                if (in_array($category, ['selected', 'present', 'available', 'available_if_needed'])) {
                    $availCount++;
                }
                if ($category === 'selected') {
                    $selCount++;
                }
                
                if ($event['is_training']) {
                    $trainingSlotsCount++;
                    if (in_array($category, ['present', 'available', 'available_if_needed', 'selected'])) {
                        $trainingPresentCount++;
                    }
                }
            }
            
            $eventAttendance[$mid] = $availCount;
            
            if ($event['is_match']) {
                $minRequired = 6;
                $typeLower = mb_strtolower($event['ManifestationTypée'] ?? '');
                if (
                    str_contains($typeLower, '4x4') ||
                    str_contains($typeLower, '4*4') ||
                    str_contains($typeLower, 'm13') ||
                    str_contains($typeLower, 'm15') ||
                    str_contains($typeLower, 'ufolep')
                ) {
                    $minRequired = 4;
                }
                if ($selCount < $minRequired) {
                    $understaffedMatches++;
                }
            }
            if ($event['is_training']) {
                $trainingCount++;
            }
        }

        $responseRate = $totalSlots > 0 ? (int)round(($totalAnswered / $totalSlots) * 100) : 0;
        $avgAvailable = count($upcomingEvents) > 0 ? round(array_sum($eventAttendance) / count($upcomingEvents), 1) : 0;
        $trainingAttendanceRate = $trainingSlotsCount > 0 ? (int)round(($trainingPresentCount / $trainingSlotsCount) * 100) : 0;

        $metrics = [
            'response_rate' => $responseRate,
            'avg_available' => $avgAvailable,
            'understaffed_matches' => $understaffedMatches,
            'training_attendance' => $trainingAttendanceRate,
            'has_trainings' => $trainingCount > 0
        ];

        return [
            'upcoming_events' => $upcomingEvents,
            'grid_players' => $gridPlayers,
            'metrics' => $metrics
        ];
    }

    /**
     * Vérifie si deux plages de temps se chevauchent.
     */
    private function checkOverlap(array $rangeA, array $rangeB): bool
    {
        return $rangeA[0] < $rangeB[1] && $rangeB[0] < $rangeA[1];
    }

    /**
     * Envoie un mail de relance par email à tous les joueurs n'ayant pas répondu à une rencontre.
     * Route: POST /member/captain/matches/{id}/remind
     */
    public function remindNoResponse(array $params): void
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
            View::flash('error', 'Vous n\'êtes pas autorisé à gérer cette rencontre.');
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

        $brevo = new BrevoService();
        $sentCount = 0;
        $failedCount = 0;

        foreach ($joueurs as $j) {
            $jid = (int)$j['id_joueur'];
            $rawStatus = $participations[$jid] ?? '';
            $statusObj = new \App\Helpers\ParticipationStatus($rawStatus);
            $cat = $statusObj->getCategory();

            // Sont considérés comme "sans réponse" les joueurs qui ne sont ni sélectionnés,
            // ni déclarés disponibles, ni déclarés indisponibles/absents (catégories no_response et unknown)
            $hasAnswered = in_array($cat, ['selected', 'available', 'available_if_needed', 'present', 'unavailable', 'absent']);

            if (!$hasAnswered) {
                // Charger les détails du joueur depuis la base externe pour obtenir son adresse email
                try {
                    $playerDb = \App\Models\Joueur::findById($jid);
                    if ($playerDb && !empty($playerDb['Mel'])) {
                        $success = $brevo->sendMatchReminderNotification($playerDb, $event, $matchedTeam['libelle']);
                        if ($success) {
                            $sentCount++;
                        } else {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                        Logger::errors()->warning('Reminder email skipped: player has no email address', ['player_id' => $jid]);
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    Logger::errors()->error('Failed to send reminder email to player', [
                        'player_id' => $jid,
                        'match_id' => $matchId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($sentCount > 0) {
            $msg = "$sentCount relance(s) envoyée(s) avec succès.";
            if ($failedCount > 0) {
                $msg .= " ($failedCount échec(s) ou e-mail(s) manquant(s)).";
            }
            View::flash('success', $msg);
        } else {
            if ($failedCount > 0) {
                View::flash('error', "Impossible d'envoyer les relances ($failedCount échec(s) ou e-mail(s) manquant(s)).");
            } else {
                View::flash('info', 'Aucun retardataire à relancer pour cette rencontre.');
            }
        }

        header('Location: /member/captain');
        exit;
    }
}
