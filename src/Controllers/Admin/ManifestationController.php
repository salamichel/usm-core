<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\MotsClef;
use App\Services\Agenda\EventRepository;
use App\Services\Validator;
use App\Services\Pagination;

class ManifestationController extends BaseAdminController
{
    /**
     * Liste toutes les manifestations (paginées et filtrables).
     */
    public function index(array $params): void
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $filters = [
            'type'   => $_GET['type'] ?? '',
            'lieu'   => $_GET['lieu'] ?? '',
            'statut' => $_GET['statut'] ?? '',
        ];

        $total = EventRepository::countAllEvents($filters);
        $pagination = new Pagination($total, 30, $page);

        $manifestations = EventRepository::allEventsPaginated($pagination->currentPage, $pagination->perPage, $filters);

        // Récupérer les options de filtres depuis MotsClef
        $types = MotsClef::getByCategory('ManifestationTypée');
        $locations = MotsClef::getByCategory('Lieu');
        $statuses = MotsClef::getByCategory('Statut');

        View::render('admin/manifestations/list.twig', [
            'manifestations' => $manifestations,
            'types'          => $types,
            'locations'      => $locations,
            'statuses'       => $statuses,
            'filters'        => $filters,
            'currentPage'    => $pagination->currentPage,
            'pagesCount'     => $pagination->totalPages,
            'total'          => $total,
        ]);
    }

    /**
     * Formulaire d'ajout d'une manifestation.
     */
    public function create(array $params): void
    {
        View::render('admin/manifestations/form.twig', array_merge($this->getFormOptions(), [
            'manifestation' => null,
            'action'        => BASE_URL . '/admin/manifestations/create',
        ]));
    }

    /**
     * Enregistrement d'une manifestation.
     */
    public function store(array $params): void
    {
        $formData = [
            'manifestation_type'      => trim($_POST['manifestation_type'] ?? ''),
            'date'                    => trim($_POST['date'] ?? ''),
            'duration'                => trim($_POST['duration'] ?? ''),
            'location'                => trim($_POST['location'] ?? ''),
            'nombre_terrain'          => $_POST['nombre_terrain'] !== '' ? (int)$_POST['nombre_terrain'] : null,
            'statut'                  => trim($_POST['statut'] ?? ''),
            'commentaire'             => trim($_POST['commentaire'] ?? ''),
            'send_notification_email' => isset($_POST['send_notification_email']),
        ];

        $statuses = MotsClef::getByCategory('Statut');

        $validator = Validator::make($formData)
            ->required('manifestation_type', 'Le type de manifestation est obligatoire.')
            ->required('date', 'La date et l\'heure sont obligatoires.')
            ->required('duration', 'La durée est obligatoire.')
            ->required('location', 'Le lieu est obligatoire.')
            ->required('nombre_terrain', 'Le nombre de terrains est obligatoire.')
            ->required('statut', 'Le statut est obligatoire.')
            ->in('statut', $statuses, 'Le statut sélectionné est invalide.');

        if ($formData['nombre_terrain'] !== null && $formData['nombre_terrain'] < 0) {
            $validator->custom('nombre_terrain', fn() => false, 'Le nombre de terrains doit être supérieur ou égal à 0.');
        }

        if ($validator->fails()) {
            View::render('admin/manifestations/form.twig', array_merge($this->getFormOptions(), [
                'manifestation' => $formData,
                'action'        => BASE_URL . '/admin/manifestations/create',
                'error'         => $validator->firstError(),
            ]));
            return;
        }

        $dateStr = str_replace('T', ' ', $formData['date']);
        if (strlen($dateStr) === 16) {
            $dateStr .= ':00';
        }

        $formData['date'] = $dateStr;

        $id = EventRepository::createEvent($formData);

        if (!empty($_POST['opponent_team_id'])) {
            $oppId = (int)$_POST['opponent_team_id'];
            $setsFor = isset($_POST['sets_for']) && $_POST['sets_for'] !== '' ? (int)$_POST['sets_for'] : null;
            $setsAgainst = isset($_POST['sets_against']) && $_POST['sets_against'] !== '' ? (int)$_POST['sets_against'] : null;
            $hasScore = ($setsFor !== null && $setsAgainst !== null);
            \App\Models\MatchResult::upsert([
                'manifestation_id'     => $id,
                'opponent_team_id'     => $oppId,
                'sets_for'             => $setsFor,
                'sets_against'         => $setsAgainst,
                'entered_by_admin'     => 1,
                'entered_at'           => $hasScore ? date('Y-m-d H:i:s') : null,
            ]);
        }

        $event = EventRepository::findEventRaw($id);
        if ($event && $formData['send_notification_email']) {
            \App\Services\Agenda\EventNotificationService::sendCreationNotifications($event);
        }
        View::flash('success', 'Manifestation créée avec succès.');
        $this->redirect('/admin/manifestations');
    }

    /**
     * Formulaire d'édition d'une manifestation.
     */
    public function edit(array $params): void
    {
        $id = (int)$params['id'];
        $event = EventRepository::findEventRaw($id);

        if (!$event) {
            $this->notFound('error.twig', ['error' => 'Manifestation non trouvée.']);
            return;
        }

        // Formater la date pour datetime-local (Y-m-d\TH:i)
        $dateValue = '';
        if (!empty($event['Date'])) {
            $dateValue = date('Y-m-d\TH:i', strtotime($event['Date']));
        }

        $manifestationData = [
            'id'                 => $event['id_manifestation'],
            'manifestation_type' => $event['ManifestationTypée'],
            'date'               => $dateValue,
            'duration'           => $event['Durée_créneau'],
            'location'           => $event['Lieu'],
            'nombre_terrain'     => $event['Nombre_terrain'],
            'statut'             => $event['Statut'],
            'commentaire'        => $event['Commentaire'],
        ];

        $matchResult = \App\Models\MatchResult::findByManifestation($id);
        $currentEncounters = \App\Models\MatchResult::findAllByManifestation($id);

        View::render('admin/manifestations/form.twig', array_merge($this->getFormOptions(), [
            'manifestation'      => $manifestationData,
            'match_result'       => $matchResult,
            'current_encounters' => $currentEncounters,
            'action'             => BASE_URL . '/admin/manifestations/' . $id . '/edit',
        ]));
    }

    /**
     * Mise à jour d'une manifestation.
     */
    public function update(array $params): void
    {
        $id = (int)$params['id'];
        $event = EventRepository::findEventRaw($id);

        if (!$event) {
            $this->notFound('error.twig', ['error' => 'Manifestation non trouvée.']);
            return;
        }

        $formData = [
            'manifestation_type' => trim($_POST['manifestation_type'] ?? ''),
            'date'               => trim($_POST['date'] ?? ''),
            'duration'           => trim($_POST['duration'] ?? ''),
            'location'           => trim($_POST['location'] ?? ''),
            'nombre_terrain'     => $_POST['nombre_terrain'] !== '' ? (int)$_POST['nombre_terrain'] : null,
            'statut'             => trim($_POST['statut'] ?? ''),
            'commentaire'        => trim($_POST['commentaire'] ?? ''),
        ];

        $statuses = MotsClef::getByCategory('Statut');

        $validator = Validator::make($formData)
            ->required('manifestation_type', 'Le type de manifestation est obligatoire.')
            ->required('date', 'La date et l\'heure sont obligatoires.')
            ->required('duration', 'La durée est obligatoire.')
            ->required('location', 'Le lieu est obligatoire.')
            ->required('nombre_terrain', 'Le nombre de terrains est obligatoire.')
            ->required('statut', 'Le statut est obligatoire.')
            ->in('statut', $statuses, 'Le statut sélectionné est invalide.');

        if ($formData['nombre_terrain'] !== null && $formData['nombre_terrain'] < 0) {
            $validator->custom('nombre_terrain', fn() => false, 'Le nombre de terrains doit être supérieur ou égal à 0.');
        }

        if ($validator->fails()) {
            $formData['id'] = $id;
            View::render('admin/manifestations/form.twig', array_merge($this->getFormOptions(), [
                'manifestation' => $formData,
                'action'        => BASE_URL . '/admin/manifestations/' . $id . '/edit',
                'error'         => $validator->firstError(),
            ]));
            return;
        }

        $dateStr = str_replace('T', ' ', $formData['date']);
        if (strlen($dateStr) === 16) {
            $dateStr .= ':00';
        }
        $formData['date'] = $dateStr;

        $wasCancelled = str_contains((string)($event['Statut'] ?? ''), 'Annulé');
        $isCancelledNow = str_contains($formData['statut'], 'Annulé');

        EventRepository::updateEvent($id, $formData);

        // Mise à jour des adversaires et scores
        $opponentTeamIds = [];
        if (!empty($_POST['opponent_team_ids']) && is_array($_POST['opponent_team_ids'])) {
            $opponentTeamIds = array_map('intval', $_POST['opponent_team_ids']);
        } elseif (!empty($_POST['opponent_team_id'])) {
            $opponentTeamIds[] = (int)$_POST['opponent_team_id'];
        }

        if (!empty($opponentTeamIds)) {
            \App\Models\MatchResult::syncManifestationOpponents($id, $opponentTeamIds, null, true);

            if (!empty($_POST['scores']) && is_array($_POST['scores'])) {
                foreach ($_POST['scores'] as $oppId => $scoreData) {
                    $oppId = (int)$oppId;
                    if ($oppId <= 0) continue;
                    $setsFor = isset($scoreData['sets_for']) && $scoreData['sets_for'] !== '' ? (int)$scoreData['sets_for'] : null;
                    $setsAgainst = isset($scoreData['sets_against']) && $scoreData['sets_against'] !== '' ? (int)$scoreData['sets_against'] : null;
                    if ($setsFor !== null && $setsAgainst !== null) {
                        \App\Models\MatchResult::upsert([
                            'manifestation_id'     => $id,
                            'opponent_team_id'     => $oppId,
                            'sets_for'             => $setsFor,
                            'sets_against'         => $setsAgainst,
                            'entered_by_admin'     => 1,
                            'entered_at'           => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            } elseif (count($opponentTeamIds) === 1 && isset($_POST['sets_for'])) {
                $oppId = $opponentTeamIds[0];
                $setsFor = $_POST['sets_for'] !== '' ? (int)$_POST['sets_for'] : null;
                $setsAgainst = $_POST['sets_against'] !== '' ? (int)$_POST['sets_against'] : null;
                $existingResult = \App\Models\MatchResult::findByManifestation($id);
                $hasScore = ($setsFor !== null && $setsAgainst !== null);

                \App\Models\MatchResult::upsert([
                    'manifestation_id'     => $id,
                    'opponent_team_id'     => $oppId,
                    'sets_for'             => $setsFor,
                    'sets_against'         => $setsAgainst,
                    'set_details'          => $existingResult['set_details'] ?? null,
                    'entered_by_joueur_id' => $existingResult['entered_by_joueur_id'] ?? null,
                    'entered_by_admin'     => 1,
                    'entered_at'           => $hasScore ? ($existingResult['entered_at'] ?? date('Y-m-d H:i:s')) : null,
                ]);
            }
        } elseif (isset($_POST['opponent_team_id']) && $_POST['opponent_team_id'] === '') {
            \App\Models\MatchResult::deleteByManifestation($id);
        }

        if (!$wasCancelled && $isCancelledNow) {
            $fullEvent = EventRepository::getEventById($id);
            if ($fullEvent) {
                \App\Services\Agenda\EventNotificationService::sendCancellationNotifications($fullEvent);
            }
        }

        View::flash('success', 'Manifestation mise à jour avec succès.');
        $this->redirect('/admin/manifestations');
    }

    /**
     * Suppression d'une manifestation.
     */
    public function delete(array $params): void
    {
        $this->requirePost('/admin/manifestations');

        $id = (int)$params['id'];
        $event = EventRepository::findEventRaw($id);

        if (!$event) {
            $this->notFound('error.twig', ['error' => 'Manifestation non trouvée.']);
            return;
        }

        \App\Models\MatchResult::deleteByManifestation($id);
        EventRepository::deleteEvent($id);
        View::flash('success', 'Manifestation supprimée avec succès.');
        $this->redirect('/admin/manifestations');
    }

    /**
     * Suppression en masse de manifestations.
     */
    public function deleteBulk(array $params): void
    {
        $this->requirePost('/admin/manifestations');

        $rawIds = $_POST['ids'] ?? [];
        if (!is_array($rawIds) || empty($rawIds)) {
            View::flash('error', 'Aucune manifestation sélectionnée pour la suppression.');
            $this->redirect('/admin/manifestations');
            return;
        }

        $ids = array_map('intval', $rawIds);
        foreach ($ids as $delId) {
            \App\Models\MatchResult::deleteByManifestation($delId);
        }
        $deletedCount = EventRepository::deleteEventsBulk($ids);

        if ($deletedCount > 0) {
            View::flash('success', sprintf('%d manifestation(s) supprimée(s) avec succès.', $deletedCount));
        } else {
            View::flash('error', 'Aucune manifestation n\'a pu être supprimée.');
        }

        $this->redirect('/admin/manifestations');
    }

    /**
     * Retourne les options de formulaires récupérées depuis MotsClef.
     */
    private function getFormOptions(): array
    {
        return [
            'types'          => MotsClef::getByCategory('ManifestationTypée'),
            'locations'      => MotsClef::getByCategory('Lieu'),
            'durations'      => MotsClef::getByCategory('Durée_créneau'),
            'statuses'       => MotsClef::getByCategory('Statut'),
            'opponent_teams' => \App\Models\OpponentTeam::allActive(),
        ];
    }
}
