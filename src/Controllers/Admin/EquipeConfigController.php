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
use App\Services\Validator;

class EquipeConfigController extends BaseAdminController
{
    // ── CRUD config ──────────────────────────────────────────────────────────

    public function index(array $params): void
    {
        $saison = \App\Models\Saison::getActive();
        $saisonId = $saison ? (int)$saison['id'] : null;
        $equipes = EquipeConfig::all();
        
        // Calculer dynamiquement le nombre de joueurs affectés à chaque équipe pour la saison active
        foreach ($equipes as &$eq) {
            $eq['player_count'] = 0;
            if ($saisonId) {
                $es = \App\Models\EquipeSaison::findBySaisonAndEquipe($saisonId, (int)$eq['id']);
                if ($es) {
                    $eq['player_count'] = \App\Models\EquipeSaisonJoueur::countByEquipeSaison((int)$es['id']);
                }
            }
        }
        unset($eq);

        View::render('admin/equipes-config/list.twig', [
            'equipes' => $equipes,
            'saison_active_id' => $saisonId,
        ]);
    }

    public function create(array $params): void
    {
        View::render('admin/equipes-config/form.twig', [
            'equipe'      => null,
            'saisons'     => [],
            'categories'  => CategorieEquipe::all(),
            'training_types' => \App\Models\MotsClef::getTrainingTypes(),
            'db_columns'  => \App\Models\MotsClef::getByCategory('EquipeParEquipe'),
            'action'      => BASE_URL . '/admin/equipes-config/create',
        ]);
    }

    public function store(array $params): void
    {
        $data = $this->formData();
        $v = Validator::make($data)
            ->required('slug_colonne', 'La colonne est obligatoire.')
            ->required('libelle', 'Le libellé est obligatoire.');

        if ($v->fails()) {
            $associatedTrainings = json_decode($data['training_filter'] ?? '[]', true) ?: [];
            $data['associated_trainings'] = $associatedTrainings;
            View::render('admin/equipes-config/form.twig', [
                'equipe'      => $data,
                'saisons'     => [],
                'categories'  => CategorieEquipe::all(),
                'training_types' => \App\Models\MotsClef::getTrainingTypes(),
                'db_columns'  => \App\Models\MotsClef::getByCategory('EquipeParEquipe'),
                'action'      => BASE_URL . '/admin/equipes-config/create',
                'error'       => $v->firstError(),
            ]);
            return;
        }
        EquipeConfig::create($data);
        View::flash('success', "Équipe « {$data['libelle']} » créée.");
        $this->redirect('/admin/equipes-config');
    }

    public function edit(array $params): void
    {
        $equipe = $this->findOr404(EquipeConfig::class, (int)$params['id']);

        $associatedTrainings = json_decode($equipe['training_filter'] ?? '[]', true) ?: [];
        $equipe['associated_trainings'] = $associatedTrainings;

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
            'training_types' => \App\Models\MotsClef::getTrainingTypes(),
            'db_columns'  => \App\Models\MotsClef::getByCategory('EquipeParEquipe'),
            'action'      => BASE_URL . '/admin/equipes-config/' . $equipe['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        $id     = (int)$params['id'];
        $equipe = $this->findOr404(EquipeConfig::class, $id);
        $data = $this->formData();
        $v = Validator::make($data)
            ->required('slug_colonne', 'La colonne est obligatoire.')
            ->required('libelle', 'Le libellé est obligatoire.');

        if ($v->fails()) {
            $saisons = Saison::all();
            $associatedTrainings = json_decode($data['training_filter'] ?? '[]', true) ?: [];
            $data['associated_trainings'] = $associatedTrainings;
            View::render('admin/equipes-config/form.twig', [
                'equipe'      => array_merge($equipe, $data),
                'saisons'     => $saisons,
                'categories'  => CategorieEquipe::all(),
                'training_types' => \App\Models\MotsClef::getTrainingTypes(),
                'db_columns'  => \App\Models\MotsClef::getByCategory('EquipeParEquipe'),
                'action'      => BASE_URL . '/admin/equipes-config/' . $id . '/edit',
                'error'       => $v->firstError(),
            ]);
            return;
        }
        EquipeConfig::update($id, $data);
        View::flash('success', "Équipe « {$data['libelle']} » mise à jour.");
        $this->redirect('/admin/equipes-config/' . $id . '/edit');
    }

    public function delete(array $params): void
    {
        EquipeConfig::delete((int)$params['id']);
        View::flash('success', 'Équipe supprimée.');
        $this->redirect('/admin/equipes-config');
    }

    // ── Photos saison ────────────────────────────────────────────────────────

    public function saisonPhotos(array $params): void
    {
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $photos     = Photo::forEntity('equipe_saison', $es['id']);
        $upload_url = BASE_URL . '/admin/equipes-config/' . $equipe['id']
                      . '/saisons/' . $saison['id'] . '/photos/upload';
        $saisons    = Saison::all();
        View::render('admin/equipes-config/saison_photos.twig', [
            'equipe'     => $equipe,
            'saison'     => $saison,
            'saisons'    => $saisons,
            'es'         => $es,
            'photos'     => $photos,
            'upload_url' => $upload_url,
        ]);
    }

    public function uploadSaisonPhoto(array $params): void
    {
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
        [$equipe, $saison, $es] = $this->resolveEquipeSaison($params);
        if (!$equipe || !$saison) {
            $this->notFound();
            return;
        }

        $joueurs   = EquipeSaisonJoueur::findByEquipeSaison($es['id']);
        $available = EquipeSaisonJoueur::getAvailableSnapshots($es['id'], $saison['id']);
        $saisons   = Saison::all();
        View::render('admin/equipes-config/saison_joueurs.twig', [
            'equipe'    => $equipe,
            'saison'    => $saison,
            'saisons'   => $saisons,
            'es'        => $es,
            'joueurs'   => $joueurs,
            'available' => $available,
        ]);
    }

    public function addJoueur(array $params): void
    {
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
            'training_filter'      => isset($_POST['associated_trainings']) && is_array($_POST['associated_trainings']) ? json_encode($_POST['associated_trainings']) : null,
            'description'          => HtmlHelper::nullIfEmptyHtml($_POST['description'] ?? null),
            'description_courte'   => trim($_POST['description_courte'] ?? '') ?: null,
            'type'                 => trim($_POST['type'] ?? '') ?: null,
            'hauteur_filet'        => trim($_POST['hauteur_filet'] ?? '') ?: null,
            'ffvb_link'            => trim($_POST['ffvb_link'] ?? '') ?: null,
            'min_players'          => isset($_POST['min_players']) ? (int)$_POST['min_players'] : 6,
        ];
    }
}
