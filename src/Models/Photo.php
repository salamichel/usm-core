<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ImageResizer;
use App\Services\UploadPathManager;

class Photo
{
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE      = 10 * 1024 * 1024; // 10 Mo

    public static function forEntity(string $type, int $id): array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM photos WHERE entity_type = ? AND entity_id = ? ORDER BY position ASC, id ASC"
        );
        $stmt->execute([$type, $id]);
        return $stmt->fetchAll();
    }

    public static function getEntityCover(string $type, int $id): ?array
    {
        $photos = self::forEntity($type, $id);
        return $photos[0] ?? null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $type, int $entityId, string $filename, ?string $caption = null, int $position = 0, bool $hasVariants = false): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO photos (entity_type, entity_id, filename, caption, position, has_variants)
             VALUES (:type, :entity_id, :filename, :caption, :position, :has_variants)"
        );
        $stmt->execute([
            ':type'         => $type,
            ':entity_id'    => $entityId,
            ':filename'     => $filename,
            ':caption'      => $caption,
            ':position'     => $position,
            ':has_variants' => $hasVariants ? 1 : 0,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $photo = self::find($id);
        if ($photo) {
            $path = UPLOAD_DIR . '/' . $photo['filename'];
            if (file_exists($path)) {
                unlink($path);
            }
            ImageResizer::deleteVariants($path);
        }
        Database::get()->prepare("DELETE FROM photos WHERE id = ?")->execute([$id]);
    }

    public static function reorder(array $ids): void
    {
        $stmt = Database::get()->prepare("UPDATE photos SET position = ? WHERE id = ?");
        foreach (array_values($ids) as $pos => $id) {
            $stmt->execute([$pos, (int)$id]);
        }
    }

    public static function deleteAllForEntity(string $type, int $id): void
    {
        $photos = self::forEntity($type, $id);
        foreach ($photos as $photo) {
            $path = UPLOAD_DIR . '/' . $photo['filename'];
            if (file_exists($path)) {
                unlink($path);
            }
            ImageResizer::deleteVariants($path);
        }
        Database::get()->prepare(
            "DELETE FROM photos WHERE entity_type = ? AND entity_id = ?"
        )->execute([$type, $id]);
    }

    /**
     * Upload a single file (from Dropzone: $_FILES['file']).
     * Returns array with 'path' (relative) and 'has_variants' (bool).
     */
    public static function uploadSingle(?array $file, string $entityType = 'post'): array
    {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Aucun fichier reçu.');
        }
        return self::validateAndSaveFile($file, $entityType);
    }

    /**
     * Process a multi-file upload ($_FILES['photos']).
     * Returns array of ['path' => string, 'has_variants' => bool] per file.
     */
    public static function uploadFiles(array $filesInput, string $entityType = 'post'): array
    {
        $saved = [];
        foreach (self::normalizeFilesArray($filesInput) as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $saved[] = self::validateAndSaveFile($file, $entityType);
        }
        return $saved;
    }

    /**
     * Valide et sauvegarde un fichier uploadé.
     * Lève une RuntimeException si la validation échoue.
     *
     * @return array{path: string, has_variants: bool}
     */
    private static function validateAndSaveFile(array $file, string $entityType): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage($file['error']) . ' (' . $file['name'] . ')');
        }
        if ($file['size'] > self::MAX_SIZE) {
            throw new \RuntimeException("Fichier « {$file['name']} » trop volumineux (max 10 Mo).");
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            throw new \RuntimeException("Type non autorisé pour « {$file['name']} » ({$mime}).");
        }
        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename   = 'photo-' . time() . '-' . uniqid() . '.' . $ext;
        $uploadPath = UploadPathManager::getUploadPath($entityType);
        $destPath   = $uploadPath . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException("Impossible de sauvegarder « {$file['name']} ».");
        }
        $hasVariants = ImageResizer::generateVariants($destPath);
        return [
            'path'         => UploadPathManager::getRelativeUploadPath($entityType, $filename),
            'has_variants' => $hasVariants,
        ];
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux pour la configuration PHP du serveur (limite upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite du formulaire HTML).',
            UPLOAD_ERR_PARTIAL    => 'Fichier reçu partiellement, réessayez.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire serveur introuvable.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le serveur.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqué par une extension PHP.',
            default               => 'Erreur d\'upload (code ' . $code . ').',
        };
    }

    private static function normalizeFilesArray(array $files): array
    {
        $normalized = [];
        if (is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                $normalized[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
        } else {
            $normalized[] = $files;
        }
        return $normalized;
    }
}
