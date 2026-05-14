<?php
declare(strict_types=1);

namespace App\Services;

class ImageVariant
{
    /**
     * Return the public URL for a photo variant.
     * Falls back to the original if has_variants is false or GD was unavailable.
     *
     * @param array  $photo  Row from the photos table (needs 'filename' and 'has_variants')
     * @param string $size   'thumb' | 'medium' | 'large'
     */
    public static function url(array $photo, string $size): string
    {
        return self::urlFromFilename(
            $photo['filename'],
            (bool)($photo['has_variants'] ?? false),
            $size
        );
    }

    /**
     * Same but takes a plain filename and has_variants flag.
     * Used for images not stored in the photos table (home blocks, site config, etc.).
     */
    public static function urlFromFilename(string $filename, bool $hasVariants, string $size): string
    {
        if (!$hasVariants || !isset(ImageResizer::SIZES[$size])) {
            return BASE_URL . '/assets/uploads/' . ltrim($filename, '/');
        }

        // Determine if the original was a GIF (variants would be JPEG)
        $ext   = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isGif = ($ext === 'gif');

        $variantRelative = ImageResizer::variantRelativePath($filename, $size, $isGif);
        $absoluteVariant = UPLOAD_DIR . '/' . $variantRelative;

        // If variant file does not exist on disk, fall back to original
        if (!file_exists($absoluteVariant)) {
            return BASE_URL . '/assets/uploads/' . ltrim($filename, '/');
        }

        return BASE_URL . '/assets/uploads/' . $variantRelative;
    }
}
