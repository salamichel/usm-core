<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\MotsClef;
use App\Services\Agenda\EventRepository;
use App\Services\Validator;

class ManifestationController extends BaseAdminController
{
    /**
     * Liste toutes les manifestations (paginées et filtrables).
     */
    public function index(array $params): void
    {
        Auth::require();

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $filters = [
            'type'   => $_GET['type'] ?? '',
            'lieu'   => $_GET['lieu'] ?? '',
            'statut' => $_GET['statut'] ?? '',
        ];

        $perPage = 30;
        $total = EventRepository::countAllEvents($filters);
        $pagesCount = (int)ceil($total / $perPage);

        if ($page > $pagesCount && $pagesCount > 0) {
            $page = $pagesCount;
        }

        $manifestations = EventRepository::allEventsPaginated($page, $perPage, $filters);

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
            'currentPage'    => $page,
            'pagesCount'     => $pagesCount,
            'total'          => $total,
        ]);
    }

    /**
     * Formulaire d'ajout d'une manifestation.
     */
    public function create(array $params): void
    {
        Auth::require();

        $types = MotsClef::getByCategory('ManifestationTypée');
        $locations = MotsClef::getByCategory('Lieu');
        $durations = MotsClef::getByCategory('Durée_créneau');
        $statuses = MotsClef::getByCategory('Statut');

        View::render('admin/manifestations/form.twig', [
            'manifestation' => null,
            'types'         => $types,
            'locations'     => $locations,
            'durations'     => $durations,
            'statuses'      => $statuses,
            'action'        => BASE_URL . '/admin/manifestations/create',
        ]);
    }

    /**
     * Enregistrement d'une manifestation.
     */
    public function store(array $params): void
    {
        Auth::require();

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
            View::render('admin/manifestations/form.twig', [
                'manifestation' => $formData,
                'types'         => MotsClef::getByCategory('ManifestationTypée'),
                'locations'     => MotsClef::getByCategory('Lieu'),
                'durations'     => MotsClef::getByCategory('Durée_créneau'),
                'statuses'      => $statuses,
                'action'        => BASE_URL . '/admin/manifestations/create',
                'error'         => $validator->firstError(),
            ]);
            return;
        }

        $dateStr = str_replace('T', ' ', $formData['date']);
        if (strlen($dateStr) === 16) {
            $dateStr .= ':00';
        }

        $formData['date'] = $dateStr;

        EventRepository::createEvent($formData);
        View::flash('success', 'Manifestation créée avec succès.');
        $this->redirect('/admin/manifestations');
    }

    /**
     * Formulaire d'édition d'une manifestation.
     */
    public function edit(array $params): void
    {
        Auth::require();
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

        $types = MotsClef::getByCategory('ManifestationTypée');
        $locations = MotsClef::getByCategory('Lieu');
        $durations = MotsClef::getByCategory('Durée_créneau');
        $statuses = MotsClef::getByCategory('Statut');

        View::render('admin/manifestations/form.twig', [
            'manifestation' => $manifestationData,
            'types'         => $types,
            'locations'     => $locations,
            'durations'     => $durations,
            'statuses'      => $statuses,
            'action'        => BASE_URL . '/admin/manifestations/' . $id . '/edit',
        ]);
    }

    /**
     * Mise à jour d'une manifestation.
     */
    public function update(array $params): void
    {
        Auth::require();
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
            View::render('admin/manifestations/form.twig', [
                'manifestation' => $formData,
                'types'         => MotsClef::getByCategory('ManifestationTypée'),
                'locations'     => MotsClef::getByCategory('Lieu'),
                'durations'     => MotsClef::getByCategory('Durée_créneau'),
                'statuses'      => $statuses,
                'action'        => BASE_URL . '/admin/manifestations/' . $id . '/edit',
                'error'         => $validator->firstError(),
            ]);
            return;
        }

        $dateStr = str_replace('T', ' ', $formData['date']);
        if (strlen($dateStr) === 16) {
            $dateStr .= ':00';
        }
        $formData['date'] = $dateStr;

        EventRepository::updateEvent($id, $formData);
        View::flash('success', 'Manifestation mise à jour avec succès.');
        $this->redirect('/admin/manifestations');
    }

    /**
     * Suppression d'une manifestation.
     */
    public function delete(array $params): void
    {
        Auth::require();
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
}
