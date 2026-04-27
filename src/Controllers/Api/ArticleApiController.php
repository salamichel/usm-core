<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Post;
use App\Models\Photo;
use App\Models\Tag;
use App\Services\Validator;
use App\Services\SlugManager;

class ArticleApiController
{
    public function create(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $validator = Validator::make($data)
            ->required('canalblog_id', 'canalblog_id is required')
            ->required('title', 'title is required')
            ->required('content', 'content is required')
            ->required('published_at', 'published_at is required');

        if ($validator->fails()) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'errors' => $validator->errors()]);
            return;
        }

        try {
            $canalblogId = trim((string)$data['canalblog_id']);
            $existingPost = Post::findByCanalblogId($canalblogId);

            if ($existingPost) {
                http_response_code(200);
                echo json_encode([
                    'message' => 'Article already exists',
                    'id'      => $existingPost['id'],
                ]);
                return;
            }

            $title = trim((string)$data['title']);
            $slugInput = trim((string)($data['slug'] ?? $title));
            $slug = SlugManager::generate($slugInput);
            $coverImage = $data['cover_image'] ?? null;

            $postData = [
                'title'        => $title,
                'slug'         => $slug,
                'excerpt'      => null,
                'content'      => $data['content'],
                'is_published' => 1,
                'published_at' => trim((string)$data['published_at']),
                'canalblog_id' => $canalblogId,
            ];

            $postId = Post::create($postData);

            if ($coverImage) {
                $filename = $this->downloadImage($coverImage, $postId);
                if ($filename) {
                    Photo::create('post', $postId, $filename);
                }
            }

            $tags = $data['tags'] ?? [];
            if (!empty($tags) && is_array($tags)) {
                foreach ($tags as $tagName) {
                    $tagName = trim((string)$tagName);
                    if (!empty($tagName)) {
                        $tagId = Tag::findOrCreateByName($tagName);
                        Tag::attachToPost($postId, $tagId);
                    }
                }
            }

            http_response_code(201);
            echo json_encode([
                'message' => 'Article created successfully',
                'id'      => $postId,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    private function downloadImage(string $url, int $postId): ?string
    {
        try {
            $imageContent = @file_get_contents($url);
            if (!$imageContent) {
                return null;
            }

            $ext = $this->getImageExtension($url, $imageContent);
            if (!$ext) {
                return null;
            }

            $filename = 'api-post-' . $postId . '-' . time() . '.' . $ext;
            $path = UPLOAD_DIR . '/' . $filename;

            if (!file_put_contents($path, $imageContent)) {
                return null;
            }

            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getImageExtension(string $url, string $content): ?string
    {
        $pathInfo = parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($pathInfo, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $ext;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => null,
        };
    }
}
