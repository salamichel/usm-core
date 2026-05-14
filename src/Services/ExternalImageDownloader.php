<?php
declare(strict_types=1);

namespace App\Services;

class ExternalImageDownloader
{

    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE = 10 * 1024 * 1024; // 10 MB
    private const TIMEOUT = 10;

    public function processContent(string $html, int $entityId, string $entityType = 'post'): string
    {
        $changed = false;

        // Remplacer les URLs des attributs src
        $html = preg_replace_callback(
            '/\bsrc=["\']([^"\']+)["\']/',
            function ($matches) use (&$changed) {
                $url = $matches[1];
                if ($this->isExternalUrl($url)) {
                    $localPath = $this->downloadImage($url);
                    if ($localPath) {
                        $changed = true;
                        return 'src="' . BASE_URL . '/assets/uploads/' . $localPath . '"';
                    }
                }
                return $matches[0];
            },
            $html
        );

        // Remplacer aussi les URLs des attributs data-cke-saved-src (CKEditor)
        $html = preg_replace_callback(
            '/\bdata-cke-saved-src=["\']([^"\']+)["\']/',
            function ($matches) use (&$changed) {
                $url = $matches[1];
                if ($this->isExternalUrl($url)) {
                    $localPath = $this->downloadImage($url);
                    if ($localPath) {
                        $changed = true;
                        return 'data-cke-saved-src="' . BASE_URL . '/assets/uploads/' . $localPath . '"';
                    }
                }
                return $matches[0];
            },
            $html
        );

        return $html;
    }

    private function isExternalUrl(string $url): bool
    {
        if (empty($url) || $url[0] === '/') {
            return false;
        }

        if (strpos($url, 'data:') === 0) {
            return false;
        }

        $baseHost = parse_url(BASE_URL, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        return $urlHost && $urlHost !== $baseHost;
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT,
                    'user_agent' => 'USM-Volley/1.0',
                ]
            ]);

            $imageContent = @file_get_contents($url, false, $context);
            if (!$imageContent || strlen($imageContent) > self::MAX_SIZE) {
                return null;
            }

            $ext = $this->getImageExtension($url, $imageContent);
            if (!$ext) {
                return null;
            }

            $filename = 'external-' . time() . '-' . uniqid() . '.' . $ext;
            $uploadPath = UploadPathManager::getUploadPath('external');
            $path = $uploadPath . '/' . $filename;

            if (!file_put_contents($path, $imageContent)) {
                return null;
            }

            ImageResizer::generateVariants($path);

            return 'external/' . (new \DateTime('now', new \DateTimeZone('Europe/Paris')))->format('Y/m') . '/' . $filename;
        } catch (\Exception $e) {
            Logger::errors()->error('Failed to download external image', ['url' => $url, 'error' => $e->getMessage()]);
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
