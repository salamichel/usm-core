<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Document
{
    public static function allPublic(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM documents WHERE is_public = 1 ORDER BY doc_type ASC, title ASC"
        );
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        $stmt = Database::get()->query(
            "SELECT * FROM documents ORDER BY created_at DESC"
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO documents (title, filename, doc_type, is_public)
             VALUES (:title, :filename, :doc_type, :is_public)"
        );
        $stmt->execute([
            ':title'    => $data['title'],
            ':filename' => $data['filename'],
            ':doc_type' => $data['doc_type'] ?: null,
            ':is_public'=> (int)($data['is_public'] ?? 1),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE documents SET title=:title, doc_type=:doc_type, is_public=:is_public
             WHERE id=:id"
        )->execute([
            ':title'    => $data['title'],
            ':doc_type' => $data['doc_type'] ?: null,
            ':is_public'=> (int)($data['is_public'] ?? 1),
            ':id'       => $id,
        ]);
    }

    public static function updateFilename(int $id, string $filename): void
    {
        Database::get()->prepare("UPDATE documents SET filename=? WHERE id=?")->execute([$filename, $id]);
    }

    public static function delete(int $id): void
    {
        $doc = self::find($id);
        if ($doc) {
            $path = UPLOAD_DIR . '/' . $doc['filename'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
        Database::get()->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
    }

    public static function uploadFile(array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur upload fichier.');
        }
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            throw new \RuntimeException('Fichier trop volumineux (max 10 Mo).');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, UPLOAD_ALLOWED_TYPES, true)) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = Post::slugify(pathinfo($file['name'], PATHINFO_FILENAME)) . '-' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
            throw new \RuntimeException('Impossible de déplacer le fichier.');
        }
        return $filename;
    }
}
