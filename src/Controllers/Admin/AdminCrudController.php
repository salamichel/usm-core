<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Photo;

abstract class AdminCrudController extends BaseAdminController
{
    protected string $entityType;
    protected string $itemName;
    protected string $itemsName;
    protected array $templates;

    abstract protected function getModel(): string;
    abstract protected function getEntity(int $id): ?array;
    abstract protected function getAllEntities(): array;
    abstract protected function createEntity(array $data): int;
    abstract protected function updateEntity(int $id, array $data): void;
    abstract protected function deleteEntity(int $id): void;
    abstract protected function getFormData(): array;

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        if (empty($data['title'])) {
            return 'Le titre est obligatoire.';
        }
        return null;
    }

    protected function getIndexData(array $entities): array
    {
        return [
            $this->itemsName => $entities,
        ];
    }

    protected function getCreateData(): array
    {
        return [
            $this->itemName => null,
            'photos'        => [],
            'action'        => BASE_URL . '/admin/' . $this->itemsName . '/create',
        ];
    }

    protected function getEditData(array $entity): array
    {
        return [
            $this->itemName => $entity,
            'photos'        => Photo::forEntity($this->entityType, $entity['id']),
            'action'        => BASE_URL . '/admin/' . $this->itemsName . '/' . $entity['id'] . '/edit',
        ];
    }

    protected function afterStore(int $id, array $data): void {}
    protected function afterUpdate(int $id, array $data): void {}

    protected function getRedirectUrl(int $id, bool $isEdit = true): string
    {
        if ($isEdit) {
            return BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit';
        }
        return BASE_URL . '/admin/' . $this->itemsName;
    }

    protected function getListTemplate(): string
    {
        return $this->templates['list'] ?? "admin/{$this->itemsName}/list.twig";
    }

    protected function getFormTemplate(bool $isEdit = false): string
    {
        if ($isEdit) {
            return $this->templates['edit'] ?? $this->templates['form'] ?? "admin/{$this->itemsName}/form.twig";
        }
        return $this->templates['create'] ?? $this->templates['form'] ?? "admin/{$this->itemsName}/form.twig";
    }

    public function index(array $params): void
    {
        View::render($this->getListTemplate(), $this->getIndexData($this->getAllEntities()));
    }

    public function create(array $params): void
    {
        View::render($this->getFormTemplate(false), $this->getCreateData());
    }

    public function store(array $params): void
    {
        $data = $this->getFormData();

        $error = $this->validateData($data);
        if ($error !== null) {
            View::render($this->getFormTemplate(false), array_merge($this->getCreateData(), [
                $this->itemName => $data,
                'error'         => $error,
            ]));
            return;
        }

        $id = $this->createEntity($data);
        $this->handlePhotoUploads($id);
        $this->afterStore($id, $data);
        View::flash('success', ucfirst($this->itemName) . ' créé(e) avec succès.');
        header('Location: ' . $this->getRedirectUrl($id, false));
        exit;
    }

    public function edit(array $params): void
    {
        $entity = $this->getEntity((int)$params['id']);
        if (!$entity) {
            $this->notFound();
            return;
        }

        View::render($this->getFormTemplate(true), $this->getEditData($entity));
    }

    public function update(array $params): void
    {
        $id = (int)$params['id'];
        $entity = $this->getEntity($id);

        if (!$entity) {
            $this->notFound();
            return;
        }

        $data = $this->getFormData();

        $error = $this->validateData($data, $entity);
        if ($error !== null) {
            View::render($this->getFormTemplate(true), array_merge($this->getEditData($entity), [
                $this->itemName => array_merge($entity, $data),
                'error'         => $error,
            ]));
            return;
        }

        $this->updateEntity($id, $data);
        $photoError = $this->handlePhotoUploads($id);
        $this->afterUpdate($id, $data);

        if ($photoError) {
            View::flash('error', $photoError);
        } else {
            View::flash('success', ucfirst($this->itemName) . ' mis à jour.');
        }

        header('Location: ' . $this->getRedirectUrl($id, true));
        exit;
    }

    public function delete(array $params): void
    {
        $id = (int)$params['id'];
        Photo::deleteAllForEntity($this->entityType, $id);
        $this->deleteEntity($id);
        View::flash('success', ucfirst($this->itemName) . ' supprimé(e).');
        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName);
        exit;
    }

    public function uploadPhoto(array $params): void
    {
        $id = (int)$params['id'];
        $entity = $this->getEntity($id);

        if (!$entity) {
            $this->jsonError(ucfirst($this->itemName) . ' introuvable.', 404);
            return;
        }

        try {
            $uploaded = Photo::uploadSingle($_FILES['file'] ?? null, $this->entityType);
            $pid = Photo::create($this->entityType, $id, $uploaded['path'], null, 0, $uploaded['has_variants']);
            $this->jsonPhotoUploadSuccess($id, $pid, $uploaded['path']);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function deletePhoto(array $params): void
    {
        $id = (int)$params['id'];
        $pid = (int)$params['pid'];
        $photo = Photo::find($pid);

        if ($photo && $photo['entity_type'] === $this->entityType && (int)$photo['entity_id'] === $id) {
            Photo::delete($pid);
            View::flash('success', 'Photo supprimée.');
        }

        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit');
        exit;
    }

    public function deletePhotoXhr(array $params): void
    {
        $id = (int)$params['id'];
        $pid = (int)$params['pid'];
        $photo = Photo::find($pid);

        if ($photo && $photo['entity_type'] === $this->entityType && (int)$photo['entity_id'] === $id) {
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

    protected function handlePhotoUploads(int $id): ?string
    {
        if (empty($_FILES['photos']['name'][0]) && empty($_FILES['photos']['name'])) {
            return null;
        }

        try {
            $uploaded = Photo::uploadFiles($_FILES['photos'], $this->entityType);
            foreach ($uploaded as $i => $item) {
                Photo::create($this->entityType, $id, $item['path'], null, $i, $item['has_variants']);
            }
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    protected function jsonPhotoUploadSuccess(int $entityId, int $pid, string $filename): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'        => true,
            'id'        => $pid,
            'url'       => BASE_URL . '/assets/uploads/' . $filename,
            'deleteUrl' => BASE_URL . '/admin/' . $this->itemsName . '/' . $entityId . '/photos/' . $pid . '/delete-xhr',
        ]);
        exit;
    }
}
