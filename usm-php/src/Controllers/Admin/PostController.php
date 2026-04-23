<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Photo;
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
        View::render('admin/posts/form.twig', [
            'post'   => null,
            'photos' => [],
            'action' => BASE_URL . '/admin/posts/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/posts/form.twig', ['post' => $data, 'photos' => [], 'action' => BASE_URL . '/admin/posts/create', 'error' => 'Le titre est obligatoire.']);
            return;
        }
        $id = Post::create($data);
        $this->handlePhotoUploads('post', $id);
        View::flash('success', 'Article créé avec succès.');
        header('Location: ' . BASE_URL . '/admin/posts/' . $id . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $post = Post::find((int)$params['id']);
        if (!$post) { $this->notFound(); return; }
        View::render('admin/posts/form.twig', [
            'post'   => $post,
            'photos' => Photo::forEntity('post', $post['id']),
            'action' => BASE_URL . '/admin/posts/' . $post['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id   = (int)$params['id'];
        $post = Post::find($id);
        if (!$post) { $this->notFound(); return; }
        $data = $this->formData();
        if (empty($data['title'])) {
            View::render('admin/posts/form.twig', [
                'post'   => array_merge($post, $data),
                'photos' => Photo::forEntity('post', $id),
                'action' => BASE_URL . '/admin/posts/' . $id . '/edit',
                'error'  => 'Le titre est obligatoire.',
            ]);
            return;
        }
        Post::update($id, $data);
        $error = $this->handlePhotoUploads('post', $id);
        if ($error) {
            View::flash('error', $error);
        } else {
            View::flash('success', 'Article mis à jour.');
        }
        header('Location: ' . BASE_URL . '/admin/posts/' . $id . '/edit');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        Photo::deleteAllForEntity('post', $id);
        Post::delete($id);
        View::flash('success', 'Article supprimé.');
        header('Location: ' . BASE_URL . '/admin/posts');
        exit;
    }

    public function deletePhoto(array $params): void
    {
        Auth::require();
        $id    = (int)$params['id'];
        $pid   = (int)$params['pid'];
        $photo = Photo::find($pid);
        if ($photo && $photo['entity_type'] === 'post' && (int)$photo['entity_id'] === $id) {
            Photo::delete($pid);
            View::flash('success', 'Photo supprimée.');
        }
        header('Location: ' . BASE_URL . '/admin/posts/' . $id . '/edit');
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
