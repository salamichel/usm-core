<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\PageStatique;
use App\Services\SlugManager;

class PageAdminController extends AdminCrudController
{
    public function __construct()
    {
        $this->entityType = 'page';
        $this->itemName = 'page';
        $this->itemsName = 'pages';
        $this->templates = [
            'list' => 'admin/pages/list.twig',
            'form' => 'admin/pages/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return PageStatique::class;
    }

    protected function getEntity(int $id): ?array
    {
        return PageStatique::find($id);
    }

    protected function getAllEntities(): array
    {
        return PageStatique::all();
    }

    protected function createEntity(array $data): int
    {
        return PageStatique::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        PageStatique::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        PageStatique::delete($id);
    }

    protected function getFormData(): array
    {
        $title = trim($_POST['title'] ?? '');
        return [
            'title'        => $title,
            'slug'         => SlugManager::generate(trim($_POST['slug'] ?? '') ?: $title),
            'content'      => $_POST['content'] ?? '',
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
        ];
    }
}
