<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\HomeBlock;
use App\Models\Photo;
use App\Services\UploadPathManager;

class HomeBlockController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/home-blocks/list.twig', [
            'blocks' => HomeBlock::all(),
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
        View::flash('success', 'Bloc créé avec succès.');
        header('Location: ' . BASE_URL . '/admin/home-blocks/' . $id . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $block = HomeBlock::find((int)$params['id']);
        if (!$block) { $this->notFound(); return; }
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
        if (!$block) { $this->notFound(); return; }
        $data = $this->formData();
        if ($data['titre'] === '') {
            View::render('admin/home-blocks/form.twig', [
                'block'  => array_merge($block, $data),
                'action' => BASE_URL . '/admin/home-blocks/' . $id . '/edit',
                'error'  => 'Le titre est obligatoire.',
            ]);
            return;
        }
        // Si l'admin a remplacé l'image et qu'une ancienne existait, supprimer le fichier
        if (!empty($block['image']) && $block['image'] !== $data['image']) {
            $oldPath = UploadPathManager::getFullPath($block['image']);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
        HomeBlock::update($id, $data);
        View::flash('success', 'Bloc mis à jour.');
        header('Location: ' . BASE_URL . '/admin/home-blocks/' . $id . '/edit');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        HomeBlock::delete((int)$params['id']);
        View::flash('success', 'Bloc supprimé.');
        header('Location: ' . BASE_URL . '/admin/home-blocks');
        exit;
    }

    public function moveUp(array $params): void
    {
        Auth::require();
        HomeBlock::moveUp((int)$params['id']);
        header('Location: ' . BASE_URL . '/admin/home-blocks');
        exit;
    }

    public function moveDown(array $params): void
    {
        Auth::require();
        HomeBlock::moveDown((int)$params['id']);
        header('Location: ' . BASE_URL . '/admin/home-blocks');
        exit;
    }

    /**
     * Endpoint Dropzone : upload une image, retourne `{ok, url, filename}`.
     * Le filename est ensuite mis dans le champ caché `image` du form.
     */
    public function uploadImage(array $params): void
    {
        Auth::require();
        try {
            $filename = Photo::uploadSingle($_FILES['file'] ?? null, 'home_block');
            header('Content-Type: application/json');
            echo json_encode([
                'ok'       => true,
                'filename' => $filename,
                'url'      => BASE_URL . '/assets/uploads/' . $filename,
            ]);
        } catch (\RuntimeException $e) {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    private function formData(): array
    {
        return [
            'titre'     => trim($_POST['titre'] ?? ''),
            'contenu'   => $_POST['contenu'] ?? '',
            'image'     => trim($_POST['image'] ?? '') ?: null,
            'cta_label' => trim($_POST['cta_label'] ?? '') ?: null,
            'cta_url'   => trim($_POST['cta_url'] ?? '') ?: null,
            'position'  => (int)($_POST['position'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
