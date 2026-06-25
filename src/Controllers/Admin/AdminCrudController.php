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

    protected function getListTemplate(): string
    {
        return $this->templates['list'] ?? "admin/{$this->itemsName}/list.twig";
    }

    protected function getFormTemplate(): string
    {
        return $this->templates['form'] ?? "admin/{$this->itemsName}/form.twig";
    }

    public function index(array $params): void
    {
        Auth::require();
        View::render($this->getListTemplate(), [
            $this->itemsName => $this->getAllEntities(),
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render($this->getFormTemplate(), [
            $this->itemName => null,
            'photos'        => [],
            'action'        => BASE_URL . '/admin/' . $this->itemsName . '/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->getFormData();

        if (empty($data['title'])) {
            View::render($this->getFormTemplate(), [
                $this->itemName => $data,
                'photos'        => [],
                'action'        => BASE_URL . '/admin/' . $this->itemsName . '/create',
                'error'         => 'Le titre est obligatoire.',
            ]);
            return;
        }

        $id = $this->createEntity($data);
        $this->handlePhotoUploads($id);
        View::flash('success', ucfirst($this->itemName) . ' créé(e) avec succès.');
        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::require();
        $entity = $this->getEntity((int)$params['id']);
        if (!$entity) {
            $this->notFound();
            return;
        }

        View::render($this->getFormTemplate(), [
            $this->itemName => $entity,
            'photos'        => Photo::forEntity($this->entityType, $entity['id']),
            'action'        => BASE_URL . '/admin/' . $this->itemsName . '/' . $entity['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $entity = $this->getEntity($id);

        if (!$entity) {
            $this->notFound();
            return;
        }

        $data = $this->getFormData();

        if (empty($data['title'])) {
            View::render($this->getFormTemplate(), [
                $this->itemName => array_merge($entity, $data),
                'photos'        => Photo::forEntity($this->entityType, $id),
                'action'        => BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit',
                'error'         => 'Le titre est obligatoire.',
            ]);
            return;
        }

        $this->updateEntity($id, $data);
        $error = $this->handlePhotoUploads($id);

        if ($error) {
            View::flash('error', $error);
        } else {
            View::flash('success', ucfirst($this->itemName) . ' mis à jour.');
        }

        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        Photo::deleteAllForEntity($this->entityType, $id);
        $this->deleteEntity($id);
        View::flash('success', ucfirst($this->itemName) . ' supprimé(e).');
        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName);
        exit;
    }

    public function uploadPhoto(array $params): void
    {
        Auth::require();
        $id = (int)$params['id'];
        $entity = $this->getEntity($id);

        if (!$entity) {
            $this->jsonError(ucfirst($this->itemName) . ' introuvable.', 404);
            return;
        }

        try {
            $uploaded = Photo::uploadSingle($_FILES['file'] ?? null, $this->entityType);
            $pid = Photo::create($this->entityType, $id, $uploaded['path'], null, 0, $uploaded['has_variants']);
            $this->jsonSuccess($id, $pid, $uploaded['path']);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage());
        }
    }

    public function deletePhoto(array $params): void
    {
        Auth::require();
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
        Auth::require();
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

    protected function jsonSuccess(int $entityId, int $pid, string $filename): void
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
