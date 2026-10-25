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
            'type'      => $_GET['type'] ?? '',
            'lieu'      => $_GET['lieu'] ?? '',
            'statut'    => $_GET['statut'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        $total = EventRepository::countAllEvents($filters);
        $pagination = new Pagination($total, 30, $page);

        $manifestations = EventRepository::allEventsPaginated($pagination->currentPage, $pagination->perPage, $filters);

        // Récupérer les options de filtres depuis MotsClef
        $types = MotsClef::getByCategory('ManifestationTypée');
        $locations = MotsClef::getByCategory('Lieu');
        $durations = MotsClef::getByCategory('Durée_créneau');
        $statuses = MotsClef::getByCategory('Statut');

        View::render('admin/manifestations/list.twig', [
            'manifestations' => $manifestations,
            'types'          => $types,
            'locations'      => $locations,
            'durations'      => $durations,
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

        View::render('admin/manifestations/form.twig', array_merge($this->getFormOptions(), [
            'manifestation' => $manifestationData,
            'action'        => BASE_URL . '/admin/manifestations/' . $id . '/edit',
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

        EventRepository::deleteEvent($id);
        View::flash('success', 'Manifestation supprimée avec succès.');
        $this->redirect('/admin/manifestations');
    }

    /**
     * Modification en masse de manifestations.
     */
    public function editBulk(array $params): void
    {
        $this->requirePost('/admin/manifestations');

        $rawIds = $_POST['ids'] ?? [];
        if (!is_array($rawIds) || empty($rawIds)) {
            View::flash('error', 'Aucune manifestation sélectionnée pour la modification.');
            $this->redirect('/admin/manifestations');
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $rawIds), fn(int $id) => $id > 0));
        if (empty($ids)) {
            View::flash('error', 'Identifiants de manifestations invalides.');
            $this->redirect('/admin/manifestations');
            return;
        }

        $updates = [];

        // Type
        $type = trim($_POST['manifestation_type'] ?? '');
        if ($type !== '' && $type !== '__NO_CHANGE__') {
            $updates['manifestation_type'] = $type;
        }

        // Lieu
        $location = trim($_POST['location'] ?? '');
        if ($location !== '' && $location !== '__NO_CHANGE__') {
            $updates['location'] = $location;
        }

        // Durée
        $duration = trim($_POST['duration'] ?? '');
        if ($duration !== '' && $duration !== '__NO_CHANGE__') {
            $updates['duration'] = $duration;
        }

        // Nombre de terrains
        $nombreTerrain = $_POST['nombre_terrain'] ?? '';
        if ($nombreTerrain !== '' && $nombreTerrain !== '__NO_CHANGE__') {
            $nb = (int)$nombreTerrain;
            if ($nb >= 0) {
                $updates['nombre_terrain'] = $nb;
            }
        }

        // Statut
        $statut = trim($_POST['statut'] ?? '');
        if ($statut !== '' && $statut !== '__NO_CHANGE__') {
            $statuses = MotsClef::getByCategory('Statut');
            if (in_array($statut, $statuses, true)) {
                $updates['statut'] = $statut;
            }
        }

        // Horaire / Date
        $timeAction = $_POST['time_action'] ?? 'none';
        if ($timeAction === 'set_time' && !empty($_POST['new_time'])) {
            $updates['time_action'] = 'set_time';
            $updates['time_value'] = trim($_POST['new_time']);
        } elseif ($timeAction === 'shift_days' && isset($_POST['shift_days']) && $_POST['shift_days'] !== '') {
            $days = (int)$_POST['shift_days'];
            if ($days !== 0) {
                $updates['time_action'] = 'shift_days';
                $updates['shift_days'] = $days;
            }
        } elseif ($timeAction === 'shift_hours' && isset($_POST['shift_hours']) && $_POST['shift_hours'] !== '') {
            $hours = (int)$_POST['shift_hours'];
            if ($hours !== 0) {
                $updates['time_action'] = 'shift_hours';
                $updates['shift_hours'] = $hours;
            }
        }

        // Commentaire
        $commentMode = $_POST['commentaire_mode'] ?? 'none';
        if (in_array($commentMode, ['replace', 'append', 'clear'], true)) {
            $updates['commentaire_mode'] = $commentMode;
            $updates['commentaire_text'] = trim($_POST['commentaire_text'] ?? '');
        }

        if (empty($updates)) {
            View::flash('error', "Aucun champ à modifier n'a été sélectionné.");
            $this->redirect('/admin/manifestations');
            return;
        }

        // Vérifier si un passage en statut Annulé nécessite des notifications
        $sendNotification = isset($_POST['send_cancellation_email']);
        $isCancelled = isset($updates['statut']) && str_contains($updates['statut'], 'Annulé');

        $notifiedCount = 0;
        if ($isCancelled && $sendNotification) {
            foreach ($ids as $id) {
                $raw = EventRepository::findEventRaw($id);
                if ($raw && !str_contains((string)($raw['Statut'] ?? ''), 'Annulé')) {
                    $fullEvent = EventRepository::getEventById($id);
                    if ($fullEvent) {
                        try {
                            \App\Services\Agenda\EventNotificationService::sendCancellationNotifications($fullEvent);
                            $notifiedCount++;
                        } catch (\Throwable $e) {
                            // Ignorer pour continuer les autres
                        }
                    }
                }
            }
        }

        $updatedCount = EventRepository::updateEventsBulk($ids, $updates);

        if ($updatedCount > 0) {
            $msg = sprintf('%d manifestation(s) mise(s) à jour avec succès.', $updatedCount);
            if ($notifiedCount > 0) {
                $msg .= sprintf(" (%d notification(s) d'annulation envoyée(s)).", $notifiedCount);
            }
            View::flash('success', $msg);
        } else {
            View::flash('error', "Aucune manifestation n'a pu être mise à jour.");
        }

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
            'types'     => MotsClef::getByCategory('ManifestationTypée'),
            'locations' => MotsClef::getByCategory('Lieu'),
            'durations' => MotsClef::getByCategory('Durée_créneau'),
            'statuses'  => MotsClef::getByCategory('Statut'),
        ];
    }
}
