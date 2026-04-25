<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\PageStatique;
use App\Models\Photo;
use App\Models\Post;

class PageAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/pages/list.twig', ['pages' => PageStatique::all()]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/pages/form.twig', [
            'page'   => null,
            'photos' => [],
            'action' => BASE_URL . '/admin/pages/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/pages/form.twig', ['page' => $data, 'photos' => [], 'action' => BASE_URL . '/admin/pages/create', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        $id = PageStatique::create($data);
        $this->handlePhotoUploads('page', $id);
        View::flash('success', 'Page créée avec succès.');
        header('Location: ' . BASE_URL . '/admin/pages/' . $id . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $page = PageStatique::find((int)$params['id']);
        if (!$page) { $this->notFound(); return; }
        View::render('admin/pages/form.twig', [
            'page'   => $page,
            'photos' => Photo::forEntity('page', $page['id']),
            'action' => BASE_URL . '/admin/pages/' . $page['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id   = (int)$params['id'];
        $page = PageStatique::find($id);
        if (!$page) { $this->notFound(); return; }
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/pages/form.twig', [
                'page'   => array_merge($page, $data),
                'photos' => Photo::forEntity('page', $id),
                'action' => BASE_URL . '/admin/pages/' . $id . '/edit',
                'error'  => 'Le titre est obligatoire.',
            ]);
            return;
        }
        PageStatique::update($id, $data);
        $error = $this->handlePhotoUploads('page', $id);
        if ($error) {
            View::flash('error', $error);
        } else {
            View::flash('success', 'Page mise à jour.');
        }
        header('Location: ' . BASE_URL . '/admin/pages/' . $id . '/edit');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        Photo::deleteAllForEntity('page', $id);
        PageStatique::delete($id);
        View::flash('success', 'Page supprimée.');
        header('Location: ' . BASE_URL . '/admin/pages');
        exit;
    }

    // ── Upload via Dropzone (réponse JSON) ───────────────────────────────────

    public function uploadPhoto(array $params): void
    {
        Auth::require();
        $id   = (int)$params['id'];
        $page = PageStatique::find($id);
        if (!$page) { $this->jsonError('Page introuvable.', 404); return; }

        try {
            $filename = Photo::uploadSingle($_FILES['file'] ?? null);
            $pid      = Photo::create('page', $id, $filename);
            $this->jsonSuccess($id, $pid, $filename);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function deletePhoto(array $params): void
    {
        Auth::require();
        $id    = (int)$params['id'];
        $pid   = (int)$params['pid'];
        $photo = Photo::find($pid);
        if ($photo && $photo['entity_type'] === 'page' && (int)$photo['entity_id'] === $id) {
            Photo::delete($pid);
            View::flash('success', 'Photo supprimée.');
        }
        header('Location: ' . BASE_URL . '/admin/pages/' . $id . '/edit');
        exit;
    }

    public function deletePhotoXhr(array $params): void
    {
        Auth::require();
        $id    = (int)$params['id'];
        $pid   = (int)$params['pid'];
        $photo = Photo::find($pid);
        if ($photo && $photo['entity_type'] === 'page' && (int)$photo['entity_id'] === $id) {
            Photo::delete($pid);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } else {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Photo introuvable.']);
        }
        exit;
    }

    private function jsonSuccess(int $entityId, int $pid, string $filename): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'       => true,
            'id'       => $pid,
            'url'      => BASE_URL . '/assets/uploads/' . $filename,
            'deleteUrl'=> BASE_URL . '/admin/pages/' . $entityId . '/photos/' . $pid . '/delete-xhr',
        ]);
        exit;
    }

    private function jsonError(string $message, int $code = 422): void
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    private function handlePhotoUploads(string $type, int $id): ?string
    {
        if (empty($_FILES['photos']['name'][0]) && empty($_FILES['photos']['name'])) {
            return null;
        }
        try {
            $filenames = Photo::uploadFiles($_FILES['photos']);
            foreach ($filenames as $i => $filename) {
                Photo::create($type, $id, $filename, null, $i);
            }
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
        return null;
    }

    private function formData(): array
    {
        $title = trim($_POST['title'] ?? '');
        return [
            'title'        => $title,
            'slug'         => Post::slugify(trim($_POST['slug'] ?? '') ?: $title),
            'content'      => $_POST['content'] ?? '',
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
