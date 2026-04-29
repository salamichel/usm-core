<?php
declare(strict_types=1);

namespace App\Services;

class UploadPathManager
{
    public static function getUploadPath(string $entityType): string
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $subdir = $now->format('Y/m');
        $path = UPLOAD_DIR . '/' . $entityType . '/' . $subdir;

        if (!is_dir($path)) {
            if (!@mkdir($path, 0777, true)) {
                // Fallback: try to create with different permissions
                @mkdir($path, 0755, true);
            }
        }

        // Ensure directory is writable
        if (!is_writable($path)) {
            @chmod($path, 0777);
        }

        return $path;
    }

    public static function getRelativeUploadPath(string $entityType, string $filename): string
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        return $entityType . '/' . $now->format('Y/m') . '/' . $filename;
    }

    public static function ensureDirectoryExists(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }
        return @mkdir($path, 0755, true) !== false;
    }

    public static function getFullPath(string $relativePath): string
    {
        return UPLOAD_DIR . '/' . $relativePath;
    }
}
