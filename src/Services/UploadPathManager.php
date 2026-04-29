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
            @mkdir($path, 0755, true);
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
