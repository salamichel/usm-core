<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\PageStatique;
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
        View::render('admin/pages/form.twig', ['page' => null, 'action' => BASE_URL . '/admin/pages/create']);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/pages/form.twig', ['page' => $data, 'action' => BASE_URL . '/admin/pages/create', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        PageStatique::create($data);
        View::flash('success', 'Page créée avec succès.');
        header('Location: ' . BASE_URL . '/admin/pages');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $page = PageStatique::find((int)$params['id']);
        if (!$page) { $this->notFound(); return; }
        View::render('admin/pages/form.twig', ['page' => $page, 'action' => BASE_URL . '/admin/pages/' . $page['id'] . '/edit']);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id   = (int)$params['id'];
        $page = PageStatique::find($id);
        if (!$page) { $this->notFound(); return; }
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/pages/form.twig', ['page' => array_merge($page, $data), 'action' => BASE_URL . '/admin/pages/' . $id . '/edit', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        PageStatique::update($id, $data);
        View::flash('success', 'Page mise à jour.');
        header('Location: ' . BASE_URL . '/admin/pages');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        PageStatique::delete((int)$params['id']);
        View::flash('success', 'Page supprimée.');
        header('Location: ' . BASE_URL . '/admin/pages');
        exit;
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
