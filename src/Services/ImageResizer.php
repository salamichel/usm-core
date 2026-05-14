<?php
declare(strict_types=1);

namespace App\Services;

class ImageResizer
{
    public const SIZES = [
        'thumb'  => [300, 200],
        'medium' => [800, 600],
        'large'  => [1200, 800],
    ];

    private const WEBP_QUALITY = 82;
    private const JPEG_QUALITY = 85;

    /**
     * Generate all size variants for the given absolute file path.
     * Returns true if at least one variant was written.
     * Silently skips non-images or when GD is unavailable.
     */
    public static function generateVariants(string $absolutePath): bool
    {
        if (!extension_loaded('gd') || !file_exists($absolutePath)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $absolutePath);
        finfo_close($finfo);

        $isGif = ($mime === 'image/gif');

        $src = self::loadImage($absolutePath, $mime);
        if ($src === null) {
            return false;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $generated = false;

        foreach (self::SIZES as $key => [$dstW, $dstH]) {
            // Never upscale
            if ($srcW <= $dstW && $srcH <= $dstH) {
                continue;
            }

            $resized = self::resizeAndCrop($src, $srcW, $srcH, $dstW, $dstH);
            $varPath = self::variantPath($absolutePath, $key, $isGif);
            $format  = $isGif ? 'jpeg' : 'webp';

            if (self::saveImage($resized, $varPath, $format)) {
                $generated = true;
            }

            imagedestroy($resized);
        }

        imagedestroy($src);

        return $generated;
    }

    /**
     * Delete all size variants for the given absolute original path.
     */
    public static function deleteVariants(string $absolutePath): void
    {
        foreach (array_keys(self::SIZES) as $key) {
            foreach (['webp', 'jpg'] as $ext) {
                $candidate = self::variantPathWithExt($absolutePath, $key, $ext);
                if (file_exists($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    /**
     * Return the absolute path of a variant file.
     * Uses WebP extension by default, JPEG for GIF sources.
     */
    public static function variantPath(string $originalPath, string $size, bool $isGif = false): string
    {
        $ext = $isGif ? 'jpg' : 'webp';
        return self::variantPathWithExt($originalPath, $size, $ext);
    }

    /**
     * Return the relative upload path of a variant, given the original relative path.
     * e.g. "post/2026/05/photo-xxx.jpg" + "thumb" → "post/2026/05/photo-xxx_thumb.webp"
     */
    public static function variantRelativePath(string $relativeFilename, string $size, bool $isGif = false): string
    {
        $ext      = $isGif ? 'jpg' : 'webp';
        $noExt    = preg_replace('/\.[^.]+$/', '', $relativeFilename);
        return $noExt . '_' . $size . '.' . $ext;
    }

    private static function variantPathWithExt(string $absolutePath, string $size, string $ext): string
    {
        $noExt = preg_replace('/\.[^.]+$/', '', $absolutePath);
        return $noExt . '_' . $size . '.' . $ext;
    }

    private static function loadImage(string $path, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png'  => @imagecreatefrompng($path) ?: null,
            'image/webp' => @imagecreatefromwebp($path) ?: null,
            'image/gif'  => @imagecreatefromgif($path) ?: null,
            default      => null,
        };
    }

    private static function resizeAndCrop(\GdImage $src, int $srcW, int $srcH, int $dstW, int $dstH): \GdImage
    {
        // Scale to cover the target box while preserving aspect ratio
        $scale = max($dstW / $srcW, $dstH / $srcH);

        // Never upscale
        if ($scale > 1.0) {
            $scale = 1.0;
        }

        $scaledW = (int)round($srcW * $scale);
        $scaledH = (int)round($srcH * $scale);

        // Center-crop offset
        $offsetX = (int)(($scaledW - $dstW) / 2);
        $offsetY = (int)(($scaledH - $dstH) / 2);

        $actualW = min($dstW, $scaledW);
        $actualH = min($dstH, $scaledH);

        $dst = imagecreatetruecolor($actualW, $actualH);

        // Preserve transparency for PNG/WebP
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $actualW, $actualH, $transparent);
        imagealphablending($dst, true);

        imagecopyresampled(
            $dst, $src,
            0, 0,
            (int)($offsetX / $scale), (int)($offsetY / $scale),
            $actualW, $actualH,
            (int)($actualW / $scale), (int)($actualH / $scale)
        );

        return $dst;
    }

    private static function saveImage(\GdImage $img, string $targetPath, string $format): bool
    {
        return match ($format) {
            'webp'  => function_exists('imagewebp')
                           ? imagewebp($img, $targetPath, self::WEBP_QUALITY)
                           : imagejpeg($img, preg_replace('/\.webp$/', '.jpg', $targetPath), self::JPEG_QUALITY),
            'jpeg'  => imagejpeg($img, $targetPath, self::JPEG_QUALITY),
            default => false,
        };
    }
}
