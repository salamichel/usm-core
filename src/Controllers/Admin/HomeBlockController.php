<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\HomeBlock;
use App\Models\Photo;

class HomeBlockController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'home_block';
        $this->itemName = 'block';
        $this->itemsName = 'home-blocks';
        $this->templates = [
            'list' => 'admin/home-blocks/list.twig',
            'form' => 'admin/home-blocks/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return HomeBlock::class;
    }

    protected function getEntity(int $id): ?array
    {
        return HomeBlock::find($id);
    }

    protected function getAllEntities(): array
    {
        return HomeBlock::all();
    }

    protected function createEntity(array $data): int
    {
        return HomeBlock::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        HomeBlock::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        HomeBlock::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'titre'     => trim($_POST['titre'] ?? ''),
            'contenu'   => $_POST['contenu'] ?? '',
            'cta_label' => trim($_POST['cta_label'] ?? '') ?: null,
            'cta_url'   => trim($_POST['cta_url'] ?? '') ?: null,
            'position'  => (int)($_POST['position'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'photo_id'  => (int)($_POST['photo_id'] ?? 0),
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        if (empty($data['titre'])) {
            return 'Le titre est obligatoire.';
        }
        return null;
    }

    protected function getIndexData(array $entities): array
    {
        foreach ($entities as &$block) {
            $block['cover_photo'] = Photo::getEntityCover('home_block', (int)$block['id']);
        }
        return [
            'blocks' => $entities,
        ];
    }

    protected function getEditData(array $entity): array
    {
        $entity['photos']      = Photo::forEntity('home_block', $entity['id']);
        $entity['cover_photo'] = Photo::getEntityCover('home_block', $entity['id']);
        return [
            'block'  => $entity,
            'action' => BASE_URL . '/admin/home-blocks/' . $entity['id'] . '/edit',
        ];
    }

    protected function getCreateData(): array
    {
        return [
            'block'  => null,
            'action' => BASE_URL . '/admin/home-blocks/create',
        ];
    }

    protected function afterStore(int $id, array $data): void
    {
        if (isset($data['photo_id']) && $data['photo_id'] > 0) {
            HomeBlock::attachPhoto($id, $data['photo_id']);
        }
    }

    protected function afterUpdate(int $id, array $data): void
    {
        if (isset($data['photo_id']) && $data['photo_id'] > 0) {
            HomeBlock::attachPhoto($id, $data['photo_id']);
        }
    }

    public function moveUp(array $params): void
    {
        HomeBlock::moveUp((int)$params['id']);
        $this->redirect('/admin/home-blocks');
    }

    public function moveDown(array $params): void
    {
        HomeBlock::moveDown((int)$params['id']);
        $this->redirect('/admin/home-blocks');
    }

    /**
     * Endpoint Dropzone : upload une image, retourne `{ok, url, filename}`.
     * Le photoId est ensuite mis dans le champ caché `photo_id` du form.
     */
    public function uploadImage(array $params): void
    {
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
            return;
        }
        exit;
    }
}
