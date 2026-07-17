<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Models\Tag;
use App\Services\SlugManager;
use App\Services\ExternalImageDownloader;

class PostController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'post';
        $this->itemName = 'post';
        $this->itemsName = 'posts';
        $this->templates = [
            'list' => 'admin/posts/list.twig',
            'form' => 'admin/posts/form.twig',
        ];
    }

    public function index(array $params): void
    {
        $filters = [];

        if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
            $filters['month'] = $_GET['month'];
        }

        if (!empty($_GET['tag'])) {
            $tag = Tag::findBySlug($_GET['tag']);
            if ($tag) {
                $filters['tag_id'] = $tag['id'];
            }
        }

        if (isset($_GET['status']) && in_array($_GET['status'], ['published', 'draft'], true)) {
            $filters['status'] = $_GET['status'];
        }

        $posts   = $filters ? Post::filtered($filters) : Post::all();
        $allTags = Tag::all();
        $months  = Post::getAvailableMonths(false);

        $postCovers = [];
        foreach ($posts as $post) {
            $postCovers[$post['id']] = \App\Models\Photo::getEntityCover('post', $post['id']);
        }

        \App\Core\View::render($this->getListTemplate(), [
            'posts'          => $posts,
            'all_tags'       => $allTags,
            'months'         => $months,
            'post_covers'    => $postCovers,
            'selected_tag'   => $_GET['tag'] ?? '',
            'selected_month' => $_GET['month'] ?? '',
            'selected_status'=> $_GET['status'] ?? '',
        ]);
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

    protected function getCreateData(): array
    {
        return array_merge(parent::getCreateData(), [
            'all_tags'      => Tag::all(),
            'tags'          => [],
            'tag_ids'       => [],
            'default_date'  => (new \DateTime('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i'),
        ]);
    }

    protected function getEditData(array $entity): array
    {
        $tags = Tag::findByPost($entity['id']);
        return array_merge(parent::getEditData($entity), [
            'tags'          => $tags,
            'tag_ids'       => array_map(fn($tag) => $tag['id'], $tags),
            'all_tags'      => Tag::all(),
        ]);
    }

    protected function afterStore(int $id, array $data): void
    {
        $downloader = new ExternalImageDownloader();
        $processedContent = $downloader->processContent($data['content'], $id, $this->entityType);
        if ($processedContent !== $data['content']) {
            Post::updateContent($id, $processedContent);
        }
        $this->saveTags($id);
    }

    protected function afterUpdate(int $id, array $data): void
    {
        $downloader = new ExternalImageDownloader();
        $processedContent = $downloader->processContent($data['content'], $id, $this->entityType);
        if ($processedContent !== $data['content']) {
            Post::updateContent($id, $processedContent);
        }
        $this->saveTags($id);
    }

    protected function getFormData(): array
    {
        $title = trim($_POST['title'] ?? '');
        $customSlug = trim($_POST['slug'] ?? '');

        return [
            'title'        => $title,
            'slug'         => $customSlug ?: '',
            'excerpt'      => trim($_POST['excerpt'] ?? ''),
            'content'      => $_POST['content'] ?? '',
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'published_at' => trim($_POST['published_at'] ?? '') ?: null,
            'is_slider'    => isset($_POST['is_slider']) ? 1 : 0,
        ];
    }

    public function toggleSlider(array $params): void
    {
        $id = (int)$params['id'];
        $post = Post::find($id);
        if (!$post) {
            $this->notFound('error.twig', ['error' => 'Article introuvable.']);
            return;
        }
        Post::setSlider($id, !(bool)$post['is_slider']);
        header('Location: ' . BASE_URL . '/admin/posts');
        exit;
    }

    private function saveTags(int $postId): void
    {
        $tagIds = isset($_POST['tags']) && is_array($_POST['tags'])
            ? array_map('intval', $_POST['tags'])
            : [];
        Tag::setPostTags($postId, $tagIds);
    }

    public function shareFacebook(array $params): void
    {
        $id = (int)$params['id'];
        $post = Post::find($id);
        
        if (!$post) {
            \App\Core\View::flash('error', 'Article introuvable.');
            $this->redirect('/admin/posts');
            return;
        }

        if (!$post['is_published']) {
            \App\Core\View::flash('error', 'L\'article doit être publié avant d\'être partagé sur Facebook.');
            $this->redirect('/admin/posts');
            return;
        }

        if (!empty($post['fb_post_id'])) {
            \App\Core\View::flash('error', 'Cet article a déjà été partagé sur Facebook.');
            $this->redirect('/admin/posts');
            return;
        }

        $link = BASE_URL . '/blog/' . $post['slug'];
        
        // Nettoyer l'extrait pour retirer les balises HTML éventuelles
        $excerpt = strip_tags($post['excerpt'] ?? '');
        if (empty($excerpt)) {
            // Si pas d'extrait, on prend un bout du contenu
            $excerpt = mb_substr(strip_tags($post['content'] ?? ''), 0, 150) . '...';
        }
        
        $message = "Nouvel article : " . $post['title'] . "\n\n" . $excerpt;

        try {
            $fbPostId = \App\Services\FacebookService::publishPost($message, $link);
            if ($fbPostId) {
                Post::setFacebookPostId($id, $fbPostId);
                \App\Core\View::flash('success', 'L\'article a été partagé avec succès sur Facebook.');
            } else {
                \App\Core\View::flash('error', 'Erreur inconnue lors du partage sur Facebook.');
            }
        } catch (\Exception $e) {
            \App\Core\View::flash('error', 'Erreur Facebook : ' . $e->getMessage());
        }

        $this->redirect('/admin/posts');
    }

}
