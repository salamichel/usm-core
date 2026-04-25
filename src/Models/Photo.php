<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

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

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $type, int $entityId, string $filename, ?string $caption = null, int $position = 0): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO photos (entity_type, entity_id, filename, caption, position)
             VALUES (:type, :entity_id, :filename, :caption, :position)"
        );
        $stmt->execute([
            ':type'      => $type,
            ':entity_id' => $entityId,
            ':filename'  => $filename,
            ':caption'   => $caption,
            ':position'  => $position,
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
        }
        Database::get()->prepare("DELETE FROM photos WHERE id = ?")->execute([$id]);
    }

    public static function deleteAllForEntity(string $type, int $id): void
    {
        $photos = self::forEntity($type, $id);
        foreach ($photos as $photo) {
            $path = UPLOAD_DIR . '/' . $photo['filename'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
        Database::get()->prepare(
            "DELETE FROM photos WHERE entity_type = ? AND entity_id = ?"
        )->execute([$type, $id]);
    }

    /**
     * Upload a single file (from Dropzone: $_FILES['file']).
     * Returns the saved filename.
     */
    public static function uploadSingle(?array $file): string
    {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Aucun fichier reçu.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur upload : code ' . $file['error']);
        }
        if ($file['size'] > self::MAX_SIZE) {
            throw new \RuntimeException('Fichier trop volumineux (max 10 Mo).');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            throw new \RuntimeException('Type non autorisé (' . $mime . ').');
        }
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'photo-' . time() . '-' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
            throw new \RuntimeException('Impossible de sauvegarder le fichier.');
        }
        return $filename;
    }

    /**
     * Process a multi-file upload ($_FILES['photos']).
     * Returns array of saved filenames.
     */
    public static function uploadFiles(array $filesInput): array
    {
        $saved = [];
        // Normalize the $_FILES multi-upload structure
        $files = self::normalizeFilesArray($filesInput);
        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Erreur upload : code ' . $file['error']);
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
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'photo-' . time() . '-' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
                throw new \RuntimeException("Impossible de sauvegarder « {$file['name']} ».");
            }
            $saved[] = $filename;
        }
        return $saved;
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
