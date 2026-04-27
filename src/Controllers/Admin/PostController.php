<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Models\Tag;
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

    public function edit(array $params): void
    {
        \App\Core\Auth::require();
        $entity = $this->getEntity((int)$params['id']);
        if (!$entity) {
            $this->notFound();
            return;
        }

        $tags = Tag::findByPost($entity['id']);
        $tagIds = array_map(fn($tag) => $tag['id'], $tags);
        $allTags = Tag::all();

        \App\Core\View::render($this->getFormTemplate(), [
            $this->itemName => $entity,
            'photos'        => \App\Models\Photo::forEntity($this->entityType, $entity['id']),
            'action'        => BASE_URL . '/admin/' . $this->itemsName . '/' . $entity['id'] . '/edit',
            'tags'          => $tags,
            'tag_ids'       => $tagIds,
            'all_tags'      => $allTags,
        ]);
    }

    public function create(array $params): void
    {
        \App\Core\Auth::require();
        $allTags = Tag::all();
        \App\Core\View::render($this->getFormTemplate(), [
            $this->itemName => null,
            'photos'        => [],
            'action'        => BASE_URL . '/admin/' . $this->itemsName . '/create',
            'tags'          => [],
            'tag_ids'       => [],
            'all_tags'      => $allTags,
            'default_date'  => (new \DateTime('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(array $params): void
    {
        \App\Core\Auth::require();
        $data = $this->getFormData();

        if (empty($data['title'])) {
            $allTags = Tag::all();
            \App\Core\View::render($this->getFormTemplate(), [
                $this->itemName => $data,
                'photos'        => [],
                'action'        => BASE_URL . '/admin/' . $this->itemsName . '/create',
                'error'         => 'Le titre est obligatoire.',
                'tags'          => [],
                'tag_ids'       => [],
                'all_tags'      => $allTags,
            ]);
            return;
        }

        $id = $this->createEntity($data);
        $this->handlePhotoUploads($id);
        $this->saveTags($id);

        \App\Core\View::flash('success', ucfirst($this->itemName) . ' créé(e) avec succès.');
        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit');
        exit;
    }

    public function update(array $params): void
    {
        \App\Core\Auth::require();
        $id = (int)$params['id'];
        $entity = $this->getEntity($id);

        if (!$entity) {
            $this->notFound();
            return;
        }

        $data = $this->getFormData();

        if (empty($data['title'])) {
            $tags = Tag::findByPost($id);
            $tagIds = array_map(fn($tag) => $tag['id'], $tags);
            $allTags = Tag::all();
            \App\Core\View::render($this->getFormTemplate(), [
                $this->itemName => array_merge($entity, $data),
                'photos'        => \App\Models\Photo::forEntity($this->entityType, $id),
                'action'        => BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit',
                'error'         => 'Le titre est obligatoire.',
                'tags'          => $tags,
                'tag_ids'       => $tagIds,
                'all_tags'      => $allTags,
            ]);
            return;
        }

        $this->updateEntity($id, $data);
        $error = $this->handlePhotoUploads($id);
        $this->saveTags($id);

        if ($error) {
            \App\Core\View::flash('error', $error);
        } else {
            \App\Core\View::flash('success', ucfirst($this->itemName) . ' mis à jour.');
        }

        header('Location: ' . BASE_URL . '/admin/' . $this->itemsName . '/' . $id . '/edit');
        exit;
    }

    protected function getFormData(): array
    {
        $title = trim($_POST['title'] ?? '');
        return [
            'title'        => $title,
            'slug'         => SlugManager::generate(trim($_POST['slug'] ?? '') ?: $title),
            'excerpt'      => trim($_POST['excerpt'] ?? ''),
            'content'      => $_POST['content'] ?? '',
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'published_at' => trim($_POST['published_at'] ?? '') ?: null,
        ];
    }

    private function saveTags(int $postId): void
    {
        $tagIds = isset($_POST['tags']) && is_array($_POST['tags'])
            ? array_map('intval', $_POST['tags'])
            : [];
        Tag::setPostTags($postId, $tagIds);
    }

    protected function notFound(): void
    {
        http_response_code(404);
        \App\Core\View::render('error.twig', ['error' => 'Article non trouvé']);
    }
}
