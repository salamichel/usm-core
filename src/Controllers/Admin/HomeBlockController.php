<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\HomeBlock;
use App\Models\Photo;

class HomeBlockController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        $blocks = HomeBlock::all();
        // Pour chaque bloc, récupérer sa photo de couverture
        foreach ($blocks as &$block) {
            $block['cover_photo'] = Photo::getEntityCover('home_block', (int)$block['id']);
        }
        View::render('admin/home-blocks/list.twig', [
            'blocks' => $blocks,
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/home-blocks/form.twig', [
            'block'  => null,
            'action' => BASE_URL . '/admin/home-blocks/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if ($data['titre'] === '') {
            View::render('admin/home-blocks/form.twig', [
                'block'  => $data,
                'action' => BASE_URL . '/admin/home-blocks/create',
                'error'  => 'Le titre est obligatoire.',
            ]);
            return;
        }
        $id = HomeBlock::create($data);
        // Si un photoId est présent dans les données, l'attacher au HomeBlock
        if (isset($data['photo_id']) && $data['photo_id'] > 0) {
            HomeBlock::attachPhoto((int)$id, (int)$data['photo_id']);
        }
        View::flash('success', 'Bloc créé avec succès.');
        $this->redirect('/admin/home-blocks/' . $id . '/edit');
    }

    public function edit(array $params): void
    {
        Auth::require();
        $block = HomeBlock::find((int)$params['id']);
        if (!$block) {
            $this->notFound();
            return;
        }
        // Récupérer les photos associées au bloc
        $block['photos']      = Photo::forEntity('home_block', (int)$params['id']);
        $block['cover_photo'] = Photo::getEntityCover('home_block', (int)$params['id']);

        View::render('admin/home-blocks/form.twig', [
            'block'  => $block,
            'action' => BASE_URL . '/admin/home-blocks/' . $block['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id    = (int)$params['id'];
        $block = HomeBlock::find($id);
        if (!$block) {
            $this->notFound();
            return;
        }
        $data = $this->formData();
        if ($data['titre'] === '') {
            View::render('admin/home-blocks/form.twig', [
                'block'  => array_merge($block, $data),
                'action' => BASE_URL . '/admin/home-blocks/' . $id . '/edit',
                'error'  => 'Le titre est obligatoire.',
            ]);
            return;
        }
        HomeBlock::update($id, $data);
        // Gérer l'attachement d'une nouvelle photo si fournie
        if (isset($data['photo_id']) && $data['photo_id'] > 0) {
            HomeBlock::attachPhoto((int)$id, (int)$data['photo_id']);
        }
        View::flash('success', 'Bloc mis à jour.');
        $this->redirect('/admin/home-blocks/' . $id . '/edit');
    }

    public function delete(array $params): void
    {
        Auth::require();
        HomeBlock::delete((int)$params['id']);
        View::flash('success', 'Bloc supprimé.');
        $this->redirect('/admin/home-blocks');
    }

    public function moveUp(array $params): void
    {
        Auth::require();
        HomeBlock::moveUp((int)$params['id']);
        $this->redirect('/admin/home-blocks');
    }

    public function moveDown(array $params): void
    {
        Auth::require();
        HomeBlock::moveDown((int)$params['id']);
        $this->redirect('/admin/home-blocks');
    }

    /**
     * Endpoint Dropzone : upload une image, retourne `{ok, url, filename}`.
     * Le photoId est ensuite mis dans le champ caché `photo_id` du form.
     */
    public function uploadImage(array $params): void
    {
        Auth::require();
        try {
            $uploaded = Photo::uploadSingle($_FILES['file'] ?? null, 'home_block');

            // Créer l'entrée dans la table photos (entity_id = 0 temporairement)
            $pid = Photo::create(
                'home_block',
                0,
                $uploaded['path'],
                null,
                0,
                $uploaded['has_variants']
            );

            header('Content-Type: application/json');
            echo json_encode([
                'ok'        => true,
                'photo_id'  => $pid,
                'filename'  => $uploaded['path'],
                'url'       => BASE_URL . '/assets/uploads/' . $uploaded['path'],
            ]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
            return; // jsonError() appelle exit(), return pour la lisibilité statique
        }
        exit;
    }

    private function formData(): array
    {
        return [
            'titre'     => trim($_POST['titre'] ?? ''),
            'contenu'   => $_POST['contenu'] ?? '',
            'cta_label' => trim($_POST['cta_label'] ?? '') ?: null,
            'cta_url'   => trim($_POST['cta_url'] ?? '') ?: null,
            'position'  => (int)($_POST['position'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'photo_id'  => (int)($_POST['photo_id'] ?? 0), // Pour l'image temporaire uploadée
        ];
    }
}
