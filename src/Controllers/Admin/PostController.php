<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Services\SlugManager;

class PostController extends AdminCrudController
{
    public function __construct()
    {
        $this->entityType = 'post';
        $this->itemName = 'post';
        $this->itemsName = 'posts';
        $this->templates = [
            'list' => 'admin/posts/list.twig',
            'form' => 'admin/posts/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return Post::class;
    }

    protected function getEntity(int $id): ?array
    {
        return Post::find($id);
    }

    protected function getAllEntities(): array
    {
        return Post::all();
    }

    protected function createEntity(array $data): int
    {
        return Post::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        Post::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        Post::delete($id);
    }

    protected function getFormData(): array
    {
        $title = trim($_POST['title'] ?? '');
        return [
            'title'               => $title,
            'slug'                => SlugManager::generate(trim($_POST['slug'] ?? '') ?: $title),
            'excerpt'             => trim($_POST['excerpt'] ?? ''),
            'meta_description'    => trim($_POST['meta_description'] ?? ''),
            'content'             => $_POST['content'] ?? '',
            'is_published'        => isset($_POST['is_published']) ? 1 : 0,
            'published_at'        => trim($_POST['published_at'] ?? '') ?: null,
        ];
    }
}
