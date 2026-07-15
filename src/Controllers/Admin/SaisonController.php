<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\JoueurSnapshot;
use App\Models\Saison;
use App\Models\EquipeConfig;

class SaisonController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'saison';
        $this->itemName = 'saison';
        $this->itemsName = 'saisons';
        $this->templates = [
            'list'   => 'admin/saisons/list.twig',
            'create' => 'admin/saisons/create.twig',
            'edit'   => 'admin/saisons/edit.twig',
        ];
    }

    protected function getModel(): string
    {
        return Saison::class;
    }

    protected function getEntity(int $id): ?array
    {
        return Saison::find($id);
    }

    protected function getAllEntities(): array
    {
        return Saison::all();
    }

    protected function createEntity(array $data): int
    {
        return Saison::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        Saison::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        Saison::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'libelle'    => trim($_POST['libelle'] ?? ''),
            'date_debut' => trim($_POST['date_debut'] ?? ''),
            'date_fin'   => trim($_POST['date_fin'] ?? ''),
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        if (empty($data['libelle'])) {
            return 'Le libellé est obligatoire.';
        }
        if (empty($data['date_debut'])) {
            return 'La date de début est obligatoire.';
        }
        if (empty($data['date_fin'])) {
            return 'La date de fin est obligatoire.';
        }
        return null;
    }

    protected function getIndexData(array $entities): array
    {
        foreach ($entities as &$s) {
            $s['snapshot_count'] = Saison::snapshotCount($s['id']);
        }
        return [
            'saisons' => $entities,
        ];
    }

    public function update(array $params): void
    {
        $id     = (int)$params['id'];
        $saison = Saison::find($id);
        if (!$saison) {
            $this->notFound();
            return;
        }

        $data = $this->getFormData();
        $error = $this->validateData($data, $saison);
        if ($error !== null) {
            View::render($this->getFormTemplate(true), [
                'saison' => array_merge($saison, $data),
                'error'  => $error,
            ]);
            return;
        }

        try {
            Saison::update($id, $data);
            View::flash('success', "Saison « {$data['libelle']} » mise à jour.");
            $this->redirect('/admin/saisons');
        } catch (\PDOException $e) {
            $error = $e->getCode() === '23000' || str_contains($e->getMessage(), '1062')
                ? "Le libellé « {$data['libelle']} » existe déjà pour une autre saison."
                : "Erreur lors de la mise à jour : " . $e->getMessage();

            View::render($this->getFormTemplate(true), [
                'saison' => array_merge($saison, $data),
                'error'  => $error,
            ]);
        }
    }

    public function joueurs(array $params): void
    {
        try {
            $joueurs = JoueurSnapshot::getExternalJoueurs();
            $error   = null;
        } catch (\Throwable $e) {
            $joueurs = [];
            $error   = 'Impossible de se connecter à la base externe : ' . $e->getMessage();
        }

        // Récupération des catégories d'équipes pour l'affichage
        $categories = EquipeConfig::getEquipesSlug();

        // Récupération des saisons pour le formulaire de flashage
        $seasons = Saison::all();
        $saisonActive = Saison::getActive();

        View::render('admin/saisons/joueurs.twig', [
            'joueurs'       => $joueurs,
            'error'         => $error,
            'categories'    => $categories,
            'seasons'       => $seasons,
            'saison_active' => $saisonActive,
        ]);
    }

    public function flashSelect(array $params): void
    {
        $saisonId = (int)($_POST['saison_id'] ?? 0);
        $s        = Saison::find($saisonId);
        if (!$s) {
            View::flash('error', 'Saison introuvable.');
            $this->redirect('/admin/saisons/joueurs');
            return;
        }
        try {
            $count = JoueurSnapshot::flashForSaison($saisonId);
            View::flash('success', "{$count} joueurs enregistrés pour la saison « {$s['libelle']} ».");
        } catch (\Throwable $e) {
            View::flash('error', 'Erreur lors du flash : ' . $e->getMessage());
        }
        $this->redirect('/admin/saisons/' . $saisonId . '/snapshots');
    }

    public function flash(array $params): void
    {
        $id = (int)$params['id'];
        $s  = Saison::find($id);
        if (!$s) {
            $this->notFound();
            return;
        }
        try {
            $count = JoueurSnapshot::flashForSaison($id);
            View::flash('success', "{$count} joueurs enregistrés pour la saison « {$s['libelle']} ».");
        } catch (\Throwable $e) {
            View::flash('error', 'Erreur lors du flash : ' . $e->getMessage());
        }
        $this->redirect('/admin/saisons/' . $id . '/snapshots');
    }

    public function snapshots(array $params): void
    {
        $id     = (int)$params['id'];
        $saison = Saison::find($id);
        if (!$saison) {
            $this->notFound();
            return;
        }
        $snapshots = JoueurSnapshot::findBySaison($id);

        // Récupération des catégories d'équipes pour l'affichage
        $categories = EquipeConfig::getEquipesSlug();

        View::render('admin/saisons/snapshots.twig', [
            'saison'     => $saison,
            'snapshots'  => $snapshots,
            'categories' => $categories,
        ]);
    }
}
