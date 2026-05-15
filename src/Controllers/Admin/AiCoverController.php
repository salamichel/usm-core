<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\AiImageContext;
use App\Models\Photo;
use App\Models\Post;
use App\Services\GeminiService;
use App\Services\ImageResizer;
use App\Services\Logger;
use App\Services\UploadPathManager;

class AiCoverController
{
    /**
     * POST /admin/posts/{id}/generate-cover
     */
    public function generateCover(array $params): void
    {
        Auth::require();
        header('Content-Type: application/json');

        try {
            if (GEMINI_API_KEY === '') {
                throw new \RuntimeException('La clé API Gemini n\'est pas configurée (GEMINI_API_KEY).');
            }

            $postId = (int) ($params['id'] ?? 0);
            $post = Post::find($postId);
            if (!$post) {
                throw new \RuntimeException('Article introuvable.');
            }

            $contextId = (int) ($_POST['context_id'] ?? 0);
            $context = $contextId ? AiImageContext::find($contextId) : AiImageContext::getDefault();
            if (!$context) {
                throw new \RuntimeException('Aucun contexte IA sélectionné ou configuré.');
            }

            $gemini = new GeminiService();
            $imagePrompt = $gemini->buildImagePrompt(
                $post['title'],
                $post['content'] ?? '',
                $context['style_prompt'],
                $context['gemini_model']
            );

            $imageData = $gemini->generateImage($imagePrompt, $context['imagen_model']);

            // Save file
            $dir = UploadPathManager::getUploadPath('post');
            $filename = 'ai-cover-' . time() . '-' . uniqid() . '.jpg';
            $fullPath = $dir . '/' . $filename;
            if (file_put_contents($fullPath, $imageData) === false) {
                throw new \RuntimeException('Impossible de sauvegarder l\'image générée.');
            }

            $relPath = UploadPathManager::getRelativeUploadPath('post', $filename);

            // Generate variants
            $hasVariants = false;
            try {
                $hasVariants = ImageResizer::generateVariants($fullPath);
            } catch (\Throwable $e) {
                Logger::errors()->warning('AiCoverController: variant generation failed', ['error' => $e->getMessage()]);
            }

            $photoId = Photo::create(
                'post',
                $postId,
                $relPath,
                'Couverture générée par IA',
                0,
                $hasVariants
            );

            echo json_encode([
                'success'  => true,
                'photo_id' => $photoId,
                'url'      => BASE_URL . '/assets/uploads/' . $relPath,
                'prompt'   => $imagePrompt,
                'context'  => $context['name'],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/ai-contexts
     */
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/ai-contexts/list.twig', ['contexts' => AiImageContext::all()]);
    }

    /**
     * GET /admin/ai-contexts/create
     */
    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/ai-contexts/form.twig', [
            'context' => null,
            'action'  => BASE_URL . '/admin/ai-contexts',
        ]);
    }

    /**
     * POST /admin/ai-contexts
     */
    public function store(array $params): void
    {
        Auth::require();
        if (empty(trim($_POST['name'] ?? ''))) {
            View::flash('error', 'Le nom est obligatoire.');
            View::render('admin/ai-contexts/form.twig', [
                'context' => $_POST,
                'action'  => BASE_URL . '/admin/ai-contexts',
            ]);
            return;
        }
        AiImageContext::create([
            'name'         => trim($_POST['name']),
            'style_prompt' => trim($_POST['style_prompt'] ?? ''),
            'gemini_model' => $_POST['gemini_model'] ?? 'gemini-2.0-flash',
            'imagen_model' => $_POST['imagen_model'] ?? 'imagen-3.0-generate-002',
            'is_default'   => !empty($_POST['is_default']) ? 1 : 0,
        ]);
        View::flash('success', 'Contexte créé.');
        header('Location: ' . BASE_URL . '/admin/ai-contexts');
    }

    /**
     * GET /admin/ai-contexts/{id}/edit
     */
    public function edit(array $params): void
    {
        Auth::require();
        $context = AiImageContext::find((int) $params['id']);
        if (!$context) {
            http_response_code(404);
            View::render('error.twig', ['error' => 'Contexte introuvable.']);
            return;
        }
        View::render('admin/ai-contexts/form.twig', [
            'context' => $context,
            'action'  => BASE_URL . '/admin/ai-contexts/' . $params['id'] . '/edit',
        ]);
    }

    /**
     * POST /admin/ai-contexts/{id}/edit
     */
    public function update(array $params): void
    {
        Auth::require();
        $context = AiImageContext::find((int) $params['id']);
        if (!$context) {
            http_response_code(404);
            View::render('error.twig', ['error' => 'Contexte introuvable.']);
            return;
        }
        if (empty(trim($_POST['name'] ?? ''))) {
            View::flash('error', 'Le nom est obligatoire.');
            View::render('admin/ai-contexts/form.twig', [
                'context' => array_merge($context, $_POST),
                'action'  => BASE_URL . '/admin/ai-contexts/' . $params['id'] . '/edit',
            ]);
            return;
        }
        AiImageContext::update((int) $params['id'], [
            'name'         => trim($_POST['name']),
            'style_prompt' => trim($_POST['style_prompt'] ?? ''),
            'gemini_model' => $_POST['gemini_model'] ?? 'gemini-2.0-flash',
            'imagen_model' => $_POST['imagen_model'] ?? 'imagen-3.0-generate-002',
            'is_default'   => !empty($_POST['is_default']) ? 1 : 0,
        ]);
        View::flash('success', 'Contexte mis à jour.');
        header('Location: ' . BASE_URL . '/admin/ai-contexts');
    }

    /**
     * POST /admin/ai-contexts/{id}/delete
     */
    public function delete(array $params): void
    {
        Auth::require();
        AiImageContext::delete((int) $params['id']);
        View::flash('success', 'Contexte supprimé.');
        header('Location: ' . BASE_URL . '/admin/ai-contexts');
    }

    /**
     * POST /admin/ai-contexts/{id}/default
     */
    public function setDefault(array $params): void
    {
        Auth::require();
        AiImageContext::setDefault((int) $params['id']);
        View::flash('success', 'Contexte défini par défaut.');
        header('Location: ' . BASE_URL . '/admin/ai-contexts');
    }
}
