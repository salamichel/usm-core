<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Post;

class PostController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/posts/list.twig', ['posts' => Post::all()]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/posts/form.twig', ['post' => null, 'action' => BASE_URL . '/admin/posts/create']);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/posts/form.twig', ['post' => $data, 'action' => BASE_URL . '/admin/posts/create', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        Post::create($data);
        View::flash('success', 'Article créé avec succès.');
        header('Location: ' . BASE_URL . '/admin/posts');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $post = Post::find((int)$params['id']);
        if (!$post) { $this->notFound(); return; }
        View::render('admin/posts/form.twig', ['post' => $post, 'action' => BASE_URL . '/admin/posts/' . $post['id'] . '/edit']);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id   = (int)$params['id'];
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/posts/form.twig', ['post' => array_merge($post, $data), 'action' => BASE_URL . '/admin/posts/' . $id . '/edit', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        Post::update($id, $data);
        View::flash('success', 'Article mis à jour.');
        header('Location: ' . BASE_URL . '/admin/posts');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        Post::delete((int)$params['id']);
        View::flash('success', 'Article supprimé.');
        header('Location: ' . BASE_URL . '/admin/posts');
        exit;
    }

    private function formData(): array
    {
        return [
            'title'        => trim($_POST['title'] ?? ''),
            'slug'         => Post::slugify(trim($_POST['slug'] ?? '')),
            'excerpt'      => trim($_POST['excerpt'] ?? ''),
            'content'      => $_POST['content'] ?? '',
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'published_at' => trim($_POST['published_at'] ?? '') ?: null,
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
