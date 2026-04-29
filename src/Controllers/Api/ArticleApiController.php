<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Post;
use App\Models\Photo;
use App\Models\Tag;
use App\Services\Validator;
use App\Services\SlugManager;
use App\Services\ExternalImageDownloader;
use App\Services\UploadPathManager;
use App\Services\Logger;

class ArticleApiController
{
    public function create(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }

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
            $customSlug = trim((string)($data['slug'] ?? ''));
            $coverImage = $data['cover_image'] ?? null;

            // Debug: check if cover_image is received
            if ($coverImage) {
                error_log("DEBUG: Cover image URL received: " . substr($coverImage, 0, 100));
            } else {
                error_log("DEBUG: No cover_image in payload");
            }

            // Convert ISO 8601 date to MySQL format
            $publishedAtForStorage = null;
            if (!empty($data['published_at'])) {
                try {
                    $dt = new \DateTime($data['published_at']);
                    $publishedAtForStorage = $dt->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    error_log('Date parsing error: ' . $e->getMessage());
                    $publishedAtForStorage = null;
                }
            }

            $postData = [
                'title'        => $title,
                'excerpt'      => null,
                'content'      => $data['content'],
                'is_published' => 1,
                'published_at' => $publishedAtForStorage,
                'canalblog_id' => $canalblogId,
            ];

            if ($customSlug) {
                $postData['slug'] = $customSlug;
            }

            $postId = Post::create($postData);

            $coverImageStatus = null;
            $coverImageError = null;

            // Download cover image if provided
            if ($coverImage) {
                $filename = $this->downloadImage($coverImage, $postId);
                if ($filename) {
                    Photo::create('post', $postId, $filename);
                    $coverImageStatus = 'downloaded';
                } else {
                    $coverImageError = 'Failed to download or process cover image';
                }
            } else {
                $coverImageStatus = 'not_provided';
            }

            // Download external images in content and update URLs
            $downloader = new ExternalImageDownloader();
            $processedContent = $downloader->processContent($postData['content'], $postId, 'post');
            if ($processedContent !== $postData['content']) {
                Post::updateContent($postId, $processedContent);
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
            $response = [
                'message' => 'Article created successfully',
                'id'      => $postId,
                'cover_image' => [
                    'status' => $coverImageStatus,
                    'error'  => $coverImageError,
                ]
            ];
            echo json_encode($response);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    private function downloadImage(string $url, int $postId): ?string
    {
        try {
            error_log("DEBUG: Downloading cover image from: " . substr($url, 0, 100));

            // Try with context first
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'USM-Volley/1.0',
                ]
            ]);

            $imageContent = @file_get_contents($url, false, $context);

            // Fallback: try without context if first attempt failed
            if (!$imageContent) {
                error_log("DEBUG: Context download failed, retrying without context");
                $imageContent = @file_get_contents($url);
            }

            if (!$imageContent) {
                error_log("DEBUG: Failed to download - empty content");
                return null;
            }

            error_log("DEBUG: Downloaded " . strlen($imageContent) . " bytes");

            if (strlen($imageContent) > 10 * 1024 * 1024) {
                error_log("DEBUG: Image too large: " . strlen($imageContent) . " bytes");
                return null;
            }

            $ext = $this->getImageExtension($url, $imageContent);
            if (!$ext) {
                error_log("DEBUG: Invalid image extension");
                return null;
            }

            error_log("DEBUG: Image extension detected: " . $ext);

            $filename = 'api-post-' . $postId . '-' . time() . '.' . $ext;
            $uploadPath = UploadPathManager::getUploadPath('post');

            error_log("DEBUG: Upload path: " . $uploadPath);
            error_log("DEBUG: Is dir: " . (is_dir($uploadPath) ? 'yes' : 'no'));
            error_log("DEBUG: Is writable: " . (is_writable($uploadPath) ? 'yes' : 'no'));

            $path = $uploadPath . '/' . $filename;

            error_log("DEBUG: Saving to path: " . $path);

            if (!file_put_contents($path, $imageContent)) {
                error_log("DEBUG: Failed to write file to disk - trying with chmod");
                @chmod($uploadPath, 0777);
                if (!file_put_contents($path, $imageContent)) {
                    error_log("DEBUG: Still failed after chmod");
                    return null;
                }
                error_log("DEBUG: Succeeded after chmod");
            }

            $relativePath = UploadPathManager::getRelativeUploadPath('post', $filename);
            error_log("DEBUG: Cover image saved successfully: " . $relativePath);
            return $relativePath;
        } catch (\Exception $e) {
            error_log("DEBUG: Exception: " . $e->getMessage());
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
