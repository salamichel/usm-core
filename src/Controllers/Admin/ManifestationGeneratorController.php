<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\MotsClef;
use App\Services\Agenda\EventRepository;
use App\Services\Validator;

class ManifestationGeneratorController extends BaseAdminController
{
    /**
     * Affiche le formulaire de génération d'entraînements.
     */
    public function showForm(array $params): void
    {
        // Récupérer toutes les manifestations typées et filtrer celles liées aux entraînements
        $allTypes = MotsClef::getByCategory('ManifestationTypée');
        $trainingTypes = array_filter($allTypes, function(string $type) {
            $normalized = mb_strtolower($type, 'UTF-8');
            return str_contains($normalized, 'entrainement') || str_contains($normalized, 'entraînement');
        });
        // Réordonner les index
        $trainingTypes = array_values($trainingTypes);

        $locations = MotsClef::getByCategory('Lieu');
        $durations = MotsClef::getByCategory('Durée_créneau');
        $statuses = MotsClef::getByCategory('Statut');

        View::render('admin/manifestations/generator.twig', [
            'trainingTypes' => $trainingTypes,
            'locations'     => $locations,
            'durations'     => $durations,
            'statuses'      => $statuses,
            'data'          => [
                'nombre_terrain' => 1,
                'statut'         => 'Confirmé',
            ]
        ]);
    }

    /**
     * Génère les manifestations hebdomadaires.
     */
    public function generate(array $params): void
    {
        $formData = [
            'manifestation_type' => trim($_POST['manifestation_type'] ?? ''),
            'date_debut'         => trim($_POST['date_debut'] ?? ''),
            'date_fin'           => trim($_POST['date_fin'] ?? ''),
            'duration'           => trim($_POST['duration'] ?? ''),
            'location'           => trim($_POST['location'] ?? ''),
            'nombre_terrain'     => $_POST['nombre_terrain'] !== '' ? (int)$_POST['nombre_terrain'] : null,
            'statut'             => trim($_POST['statut'] ?? ''),
            'commentaire'        => trim($_POST['commentaire'] ?? ''),
        ];

        // Charger les données pour le formulaire en cas d'erreur
        $allTypes = MotsClef::getByCategory('ManifestationTypée');
        $trainingTypes = array_values(array_filter($allTypes, function(string $type) {
            $normalized = mb_strtolower($type, 'UTF-8');
            return str_contains($normalized, 'entrainement') || str_contains($normalized, 'entraînement');
        }));
        $locations = MotsClef::getByCategory('Lieu');
        $durations = MotsClef::getByCategory('Durée_créneau');
        $statuses = MotsClef::getByCategory('Statut');

        $validator = Validator::make($formData)
            ->required('manifestation_type', 'Le type de manifestation est obligatoire.')
            ->required('date_debut', 'La date de début est obligatoire.')
            ->required('date_fin', 'La date de fin est obligatoire.')
            ->required('duration', 'La durée est obligatoire.')
            ->required('location', 'Le lieu est obligatoire.')
            ->required('nombre_terrain', 'Le nombre de terrains est obligatoire.')
            ->required('statut', 'Le statut est obligatoire.')
            ->in('statut', $statuses, 'Le statut sélectionné est invalide.');

        // Validation supplémentaire pour le nombre de terrains
        if ($formData['nombre_terrain'] !== null && $formData['nombre_terrain'] < 0) {
            $validator->custom('nombre_terrain', fn() => false, 'Le nombre de terrains doit être supérieur ou égal à 0.');
        }

        // Valider la cohérence des dates
        if (!empty($formData['date_debut']) && !empty($formData['date_fin'])) {
            try {
                $start = new \DateTime(str_replace('T', ' ', $formData['date_debut']));
                $end = new \DateTime($formData['date_fin'] . ' 23:59:59');
                if ($start > $end) {
                    $validator->custom('date_fin', fn() => false, 'La date de fin doit être postérieure à la date de début.');
                }
            } catch (\Throwable) {
                $validator->custom('date_debut', fn() => false, 'Les dates saisies sont invalides.');
            }
        }

        if ($validator->fails()) {
            View::render('admin/manifestations/generator.twig', [
                'trainingTypes' => $trainingTypes,
                'locations'     => $locations,
                'durations'     => $durations,
                'statuses'      => $statuses,
                'data'          => $formData,
                'error'         => $validator->firstError(),
            ]);
            return;
        }

        try {
            $startDateStr = str_replace('T', ' ', $formData['date_debut']);
            if (strlen($startDateStr) === 16) {
                $startDateStr .= ':00';
            }

            $currentDate = new \DateTime($startDateStr);
            $endDate = new \DateTime($formData['date_fin'] . ' 23:59:59');

            $count = 0;
            while ($currentDate <= $endDate) {
                EventRepository::createEvent([
                    'manifestation_type' => $formData['manifestation_type'],
                    'date'               => $currentDate->format('Y-m-d H:i:s'),
                    'duration'           => $formData['duration'],
                    'location'           => $formData['location'],
                    'nombre_terrain'     => $formData['nombre_terrain'],
                    'commentaire'        => $formData['commentaire'] !== '' ? $formData['commentaire'] : null,
                    'statut'             => $formData['statut'],
                ]);
                $currentDate->modify('+7 days');
                $count++;
            }

            if ($count === 0) {
                View::flash('error', 'Aucune manifestation n\'a pu être générée dans cet intervalle.');
                $this->redirect('/admin/manifestations/generator');
                return;
            }

            View::flash('success', sprintf('%d manifestations ont été générées avec succès.', $count));
            $this->redirect('/admin/manifestations');
        } catch (\Throwable $e) {
            View::render('admin/manifestations/generator.twig', [
                'trainingTypes' => $trainingTypes,
                'locations'     => $locations,
                'durations'     => $durations,
                'statuses'      => $statuses,
                'data'          => $formData,
                'error'         => 'Une erreur technique est survenue : ' . $e->getMessage(),
            ]);
        }
    }
}
