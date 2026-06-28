<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\HtmlHelper;
use App\Models\CategorieEquipe;
use App\Models\EquipeConfig;
use App\Models\EquipeSaison;
use App\Models\EquipeSaisonJoueur;
use App\Models\JoueurSnapshot;
use App\Models\Photo;
use App\Models\Saison;

class EquipeConfigController extends BaseAdminController
{
    // ── CRUD config ──────────────────────────────────────────────────────────

    public function index(array $params): void
    {
        Auth::require();
        $saison = \App\Models\Saison::getActive();
        View::render('admin/equipes-config/list.twig', [
            'equipes' => EquipeConfig::all(),
            'saison_active_id' => $saison ? $saison['id'] : null,
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/equipes-config/form.twig', [
            'equipe'      => null,
            'saisons'     => [],
            'categories'  => CategorieEquipe::all(),
            'action'      => BASE_URL . '/admin/equipes-config/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if ($data['slug_colonne'] === '' || $data['libelle'] === '') {
            View::render('admin/equipes-config/form.twig', [
                'equipe'      => $data,
                'saisons'     => [],
                'categories'  => CategorieEquipe::all(),
                'action'      => BASE_URL . '/admin/equipes-config/create',
                'error'       => 'Colonne et libellé sont obligatoires.',
            ]);
            return;
        }
        EquipeConfig::create($data);
        View::flash('success', "Équipe « {$data['libelle']} » créée.");
        $this->redirect('/admin/equipes-config');
    }

    public function edit(array $params): void
    {
        Auth::require();
        $equipe = EquipeConfig::find((int)$params['id']);
        if (!$equipe) {
            $this->notFound();
            return;
        }

        $saisons = Saison::all();
        foreach ($saisons as &$s) {
            $es = EquipeSaison::findBySaisonAndEquipe($s['id'], $equipe['id']);
            $s['photo_count']  = $es ? count(Photo::forEntity('equipe_saison', $es['id'])) : 0;
            $s['joueur_count'] = $es ? EquipeSaisonJoueur::countByEquipeSaison($es['id']) : 0;
            $s['es_id']        = $es ? $es['id'] : null;
        }
        View::render('admin/equipes-config/form.twig', [
            'equipe'      => $equipe,
            'saisons'     => $saisons,
            'categories'  => CategorieEquipe::all(),
            'action'      => BASE_URL . '/admin/equipes-config/' . $equipe['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id     = (int)$params['id'];
        $equipe = EquipeConfig::find($id);
        if (!$equipe) {
            $this->notFound();
            return;
        }
        $data = $this->formData();
        if ($data['slug_colonne'] === '' || $data['libelle'] === '') {
            $saisons = Saison::all();
            View::render('admin/equipes-config/form.twig', [
                'equipe'      => array_merge($equipe, $data),
                'saisons'     => $saisons,
                'categories'  => CategorieEquipe::all(),
                'action'      => BASE_URL . '/admin/equipes-config/' . $id . '/edit',
                'error'       => 'Colonne et libellé sont obligatoires.',
            ]);
            return;
        }
        EquipeConfig::update($id, $data);
        View::flash('success', "Équipe « {$data['libelle']} » mise à jour.");
        $this->redirect('/admin/equipes-config/' . $id . '/edit');
    }

    public function delete(array $params): void
    {
        Auth::require();
        EquipeConfig::delete((int)$params['id']);
        View::flash('success', 'Équipe supprimée.');
        $this->redirect('/admin/equipes-config');
    }

    // ── Photos saison ────────────────────────────────────────────────────────

    public function saisonPhotos(array $params): void
    {
        Auth::require();
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $photos     = Photo::forEntity('equipe_saison', $es['id']);
        $upload_url = BASE_URL . '/admin/equipes-config/' . $equipe['id']
                      . '/saisons/' . $saison['id'] . '/photos/upload';
        View::render('admin/equipes-config/saison_photos.twig', [
            'equipe'     => $equipe,
            'saison'     => $saison,
            'es'         => $es,
            'photos'     => $photos,
            'upload_url' => $upload_url,
        ]);
    }

    public function uploadSaisonPhoto(array $params): void
    {
        Auth::require();
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->jsonError('Équipe ou saison introuvable.', 404);
            return;
        }
        try {
            $uploaded  = Photo::uploadSingle($_FILES['file'] ?? null, 'equipe_saison');
            $pid       = Photo::create('equipe_saison', $es['id'], $uploaded['path'], null, 0, $uploaded['has_variants']);
            $deleteUrl = BASE_URL . '/admin/equipes-config/' . $equipe['id']
                         . '/saisons/' . $saison['id'] . '/photos/' . $pid . '/delete-xhr';
            header('Content-Type: application/json');
            echo json_encode([
                'ok'        => true,
                'id'        => $pid,
                'url'       => BASE_URL . '/assets/uploads/' . $uploaded['path'],
                'deleteUrl' => $deleteUrl,
            ]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
            return;
        }
        exit;
    }

    public function deleteSaisonPhotoXhr(array $params): void
    {
        Auth::require();
        $pid   = (int)$params['pid'];
        $photo = Photo::find($pid);
        if ($photo && $photo['entity_type'] === 'equipe_saison') {
            Photo::delete($pid);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } else {
            $this->jsonError('Photo introuvable.', 404);
            return;
        }
        exit;
    }

    // ── Joueurs saison ───────────────────────────────────────────────────────

    public function saisonJoueurs(array $params): void
    {
        Auth::require();
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $joueurs   = EquipeSaisonJoueur::findByEquipeSaison($es['id']);
        $available = EquipeSaisonJoueur::getAvailableSnapshots($es['id'], $saison['id']);
        View::render('admin/equipes-config/saison_joueurs.twig', [
            'equipe'    => $equipe,
            'saison'    => $saison,
            'es'        => $es,
            'joueurs'   => $joueurs,
            'available' => $available,
        ]);
    }

    public function addJoueur(array $params): void
    {
        Auth::require();
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $snapshotId = (int)($_POST['snapshot_id'] ?? 0);
        if ($snapshotId > 0) {
            EquipeSaisonJoueur::add($es['id'], $snapshotId);
        }
        $this->redirect('/admin/equipes-config/' . $equipe['id'] . '/saisons/' . $saison['id'] . '/joueurs');
    }

    public function removeJoueur(array $params): void
    {
        Auth::require();
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $snapshotId = (int)$params['jid'];
        EquipeSaisonJoueur::remove($es['id'], $snapshotId);
        $this->redirect('/admin/equipes-config/' . $equipe['id'] . '/saisons/' . $saison['id'] . '/joueurs');
    }

    public function toggleCaptain(array $params): void
    {
        Auth::require();
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $snapshotId = (int)$params['jid'];
        EquipeSaisonJoueur::toggleCaptain($es['id'], $snapshotId);
        $this->redirect('/admin/equipes-config/' . $equipe['id'] . '/saisons/' . $saison['id'] . '/joueurs');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveEquipeSaison(array $params): array
    {
        $equipe = EquipeConfig::find((int)$params['id']);
        $saison = Saison::find((int)$params['sid']);
        if (!$equipe || !$saison) {
            return [null, null, null];
        }
        $es = EquipeSaison::findOrCreate($equipe['id'], $saison['id']);
        return [$equipe, $saison, $es];
    }

    private function formData(): array
    {
        return [
            'slug_colonne'         => trim($_POST['slug_colonne'] ?? ''),
            'libelle'              => trim($_POST['libelle'] ?? ''),
            'categorie'            => trim($_POST['categorie'] ?? ''),
            'ordre'                => (int)($_POST['ordre'] ?? 0),
            'is_active'            => isset($_POST['is_active']) ? 1 : 0,
            'slug'                 => trim($_POST['slug'] ?? ''),
            'team_filter'          => !empty($_POST['team_filter']) ? trim($_POST['team_filter']) : null,
            'manifestation_filter' => !empty($_POST['manifestation_filter']) ? trim($_POST['manifestation_filter']) : null,
            'description'          => HtmlHelper::nullIfEmptyHtml($_POST['description'] ?? null),
            'description_courte'   => trim($_POST['description_courte'] ?? '') ?: null,
            'type'                 => trim($_POST['type'] ?? '') ?: null,
            'hauteur_filet'        => trim($_POST['hauteur_filet'] ?? '') ?: null,
            'ffvb_link'            => trim($_POST['ffvb_link'] ?? '') ?: null,
        ];
    }
}
