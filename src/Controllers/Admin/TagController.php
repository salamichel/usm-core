<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Tag;

class TagController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'tag';
        $this->itemName = 'tag';
        $this->itemsName = 'tags';
        $this->templates = [
            'list' => 'admin/tags/list.twig',
            'form' => 'admin/tags/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return Tag::class;
    }

    protected function getEntity(int $id): ?array
    {
        return Tag::find($id);
    }

    protected function getAllEntities(): array
    {
        return Tag::all();
    }

    protected function createEntity(array $data): int
    {
        return Tag::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        Tag::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        Tag::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        if (empty($data['name'])) {
            return 'Le nom du tag est obligatoire.';
        }
        return null;
    }

    protected function getIndexData(array $entities): array
    {
        foreach ($entities as &$tag) {
            $tag['post_count'] = Tag::getPostCount($tag['id']);
        }
        return [
            'tags' => $entities,
        ];
    }

    protected function getEditData(array $entity): array
    {
        $entity['post_count'] = Tag::getPostCount($entity['id']);
        return [
            'tag'    => $entity,
            'action' => BASE_URL . '/admin/tags/' . $entity['id'] . '/edit',
        ];
    }

    protected function getCreateData(): array
    {
        return [
            'tag'    => null,
            'action' => BASE_URL . '/admin/tags/create',
        ];
    }
}
