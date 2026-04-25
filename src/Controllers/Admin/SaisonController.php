<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\JoueurSnapshot;
use App\Models\Saison;

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
        $libelle = trim($_POST['libelle'] ?? '');
        if ($libelle === '') {
            View::render('admin/saisons/create.twig', [
                'saison' => ['libelle' => $libelle],
                'error'  => 'Le libellé est obligatoire.',
            ]);
            return;
        }
        Saison::create(['libelle' => $libelle]);
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
        View::render('admin/saisons/joueurs.twig', ['joueurs' => $joueurs, 'error' => $error]);
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
        $snapshots = \App\Models\JoueurSnapshot::findBySaison($id);
        View::render('admin/saisons/snapshots.twig', [
            'saison'    => $saison,
            'snapshots' => $snapshots,
        ]);
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
