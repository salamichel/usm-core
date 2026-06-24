<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\JoueurSnapshot;
use App\Models\Saison;
use App\Models\EquipeConfig;

class SaisonController
{
    public function index(array $params): void
    {
        Auth::require();
        $saisons = Saison::all();
        foreach ($saisons as &$s) {
            $s['snapshot_count'] = Saison::snapshotCount($s['id']);
        }
        View::render('admin/saisons/list.twig', ['saisons' => $saisons]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/saisons/create.twig', ['saison' => null]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $libelle   = trim($_POST['libelle'] ?? '');
        $dateDebut = trim($_POST['date_debut'] ?? '');
        $dateFin   = trim($_POST['date_fin'] ?? '');

        if ($libelle === '') {
            View::render('admin/saisons/create.twig', [
                'saison' => ['libelle' => $libelle, 'date_debut' => $dateDebut, 'date_fin' => $dateFin],
                'error'  => 'Le libellé est obligatoire.',
            ]);
            return;
        }
        if ($dateDebut === '') {
            View::render('admin/saisons/create.twig', [
                'saison' => ['libelle' => $libelle, 'date_debut' => $dateDebut, 'date_fin' => $dateFin],
                'error'  => 'La date de début est obligatoire.',
            ]);
            return;
        }
        if ($dateFin === '') {
            View::render('admin/saisons/create.twig', [
                'saison' => ['libelle' => $libelle, 'date_debut' => $dateDebut, 'date_fin' => $dateFin],
                'error'  => 'La date de fin est obligatoire.',
            ]);
            return;
        }

        Saison::create([
            'libelle'    => $libelle,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
        ]);
        View::flash('success', "Saison « {$libelle} » créée.");
        header('Location: ' . BASE_URL . '/admin/saisons');
        exit;
    }

    public function activate(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $s  = Saison::find($id);
        if (!$s) { $this->notFound(); return; }
        Saison::activate($id);
        View::flash('success', "Saison « {$s['libelle']} » activée.");
        header('Location: ' . BASE_URL . '/admin/saisons');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        Saison::delete((int)$params['id']);
        View::flash('success', 'Saison supprimée.');
        header('Location: ' . BASE_URL . '/admin/saisons');
        exit;
    }

    public function joueurs(array $params): void
    {
        Auth::require();
        try {
            $joueurs = JoueurSnapshot::getExternalJoueurs();
            $error   = null;
        } catch (\Throwable $e) {
            $joueurs = [];
            $error   = 'Impossible de se connecter à la base externe : ' . $e->getMessage();
        }

        // Récupération des catégories d'équipes pour l'affichage
        $categories = EquipeConfig::getEquipesSlug();

        View::render('admin/saisons/joueurs.twig', ['joueurs' => $joueurs, 'error' => $error, 'categories' => $categories]);
    }

    public function flash(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $s  = Saison::find($id);
        if (!$s) { $this->notFound(); return; }
        try {
            $count = JoueurSnapshot::flashForSaison($id);
            View::flash('success', "{$count} joueurs enregistrés pour la saison « {$s['libelle']} ».");
        } catch (\Throwable $e) {
            View::flash('error', 'Erreur lors du flash : ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/admin/saisons/' . $id . '/snapshots');
        exit;
    }

    public function snapshots(array $params): void
    {
        Auth::require();
        $id     = (int)$params['id'];
        $saison = Saison::find($id);
        if (!$saison) { $this->notFound(); return; }
        $snapshots = JoueurSnapshot::findBySaison($id);

        // Récupération des catégories d'équipes pour l'affichage
        $categories = EquipeConfig::getEquipesSlug();

        View::render('admin/saisons/snapshots.twig', [
            'saison'    => $saison,
            'snapshots' => $snapshots,
            'categories' => $categories,
        ]);
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
