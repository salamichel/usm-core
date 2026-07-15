<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Services\ImageResizer;
use App\Services\UploadPathManager;

class MediaUploadController extends BaseAdminController
{
    private const ALLOWED_IMAGES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_FILES  = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    private const MAX_SIZE = 10 * 1024 * 1024;

    public function upload(array $params): void
    {
        // Jodit sends files as files[0]; fallback to file for direct calls
        $file = $this->resolveFile();

        if (!$file) {
            $this->error('Aucun fichier reçu.');
            return;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->error($this->uploadErrorMessage($file['error']));
            return;
        }
        if ($file['size'] > self::MAX_SIZE) {
            $this->error('Fichier trop volumineux (max 10 Mo).');
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $isImage = in_array($mime, self::ALLOWED_IMAGES, true);
        $isFile  = in_array($mime, self::ALLOWED_FILES, true);

        if (!$isImage && !$isFile) {
            $this->error('Type non autorisé (' . $mime . '). Formats acceptés : JPG, PNG, WebP, GIF, PDF, DOC(X), XLS(X).');
            return;
        }

        $entityType = $isImage ? 'editor_image' : 'editor_file';
        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename   = 'media-' . time() . '-' . uniqid() . '.' . $ext;
        $uploadPath = UploadPathManager::getUploadPath($entityType);

        $destPath = $uploadPath . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->error('Impossible de sauvegarder le fichier.');
            return;
        }

        if ($isImage) {
            ImageResizer::generateVariants($destPath);
        }

        $relative = UploadPathManager::getRelativeUploadPath($entityType, $filename);
        $url      = BASE_URL . '/assets/uploads/' . $relative;

        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => true,
            'url'     => $url,
            'name'    => $file['name'],
            'isImage' => $isImage,
        ]);
        exit;
    }

    private function resolveFile(): ?array
    {
        // Jodit: files[0]
        if (isset($_FILES['files']) && isset($_FILES['files']['name'][0])) {
            return [
                'name'     => $_FILES['files']['name'][0],
                'type'     => $_FILES['files']['type'][0],
                'tmp_name' => $_FILES['files']['tmp_name'][0],
                'error'    => $_FILES['files']['error'][0],
                'size'     => $_FILES['files']['size'][0],
            ];
        }
        // Generic fallback
        if (isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return $_FILES['file'];
        }
        return null;
    }

    private function error(string $msg): void
    {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur).',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire).',
            UPLOAD_ERR_PARTIAL    => 'Fichier reçu partiellement, réessayez.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire serveur introuvable.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le serveur.',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqué par une extension PHP.',
            default               => 'Erreur d\'upload (code ' . $code . ').',
        };
    }
}
