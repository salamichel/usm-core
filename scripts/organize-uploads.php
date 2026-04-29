<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require_once ROOT . '/config/config.php';
require_once ROOT . '/src/Core/Database.php';
require_once ROOT . '/src/Services/UploadPathManager.php';

use App\Core\Database;
use App\Services\UploadPathManager;

class UploadOrganizer
{
    private int $movedFiles = 0;
    private int $updatedRecords = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "=== Réorganisation des uploads ===\n\n";

        $this->organizePhotos();
        $this->organizeHomeBlocks();

        echo "\n=== Résumé ===\n";
        echo "✓ Fichiers déplacés: {$this->movedFiles}\n";
        echo "✓ Enregistrements mis à jour: {$this->updatedRecords}\n";

        if (!empty($this->errors)) {
            echo "\n⚠ Erreurs:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        } else {
            echo "✓ Aucune erreur!\n";
        }
    }

    private function organizePhotos(): void
    {
        echo "Réorganisation des photos...\n";
        try {
            $db = Database::get();
            $stmt = $db->query("SELECT * FROM photos ORDER BY id ASC");
            $photos = $stmt->fetchAll();
        } catch (Exception $e) {
            $this->errors[] = "Erreur BD photos: " . $e->getMessage();
            return;
        }

        foreach ($photos as $photo) {
            $filename = $photo['filename'];

            // Ignorer si déjà organisé (contient un /)
            if (strpos($filename, '/') !== false) {
                echo "  ✓ {$filename} (déjà organisé)\n";
                continue;
            }

            $oldPath = UPLOAD_DIR . '/' . $filename;

            if (!file_exists($oldPath)) {
                $this->errors[] = "Fichier introuvable: {$oldPath}";
                continue;
            }

            // Déterminer la date de création (utiliser la date du fichier)
            $fileTime = filemtime($oldPath);
            $date = new DateTime('@' . $fileTime, new DateTimeZone('Europe/Paris'));
            $subdir = $date->format('Y/m');

            // Créer le dossier de destination
            $newDir = UPLOAD_DIR . '/' . $photo['entity_type'] . '/' . $subdir;
            if (!is_dir($newDir)) {
                @mkdir($newDir, 0755, true);
            }

            $newPath = $newDir . '/' . $filename;
            $newFilename = $photo['entity_type'] . '/' . $subdir . '/' . $filename;

            // Déplacer le fichier
            if (rename($oldPath, $newPath)) {
                // Mettre à jour la BD
                Database::get()->prepare(
                    "UPDATE photos SET filename = ? WHERE id = ?"
                )->execute([$newFilename, $photo['id']]);

                echo "  ✓ {$filename} → {$newFilename}\n";
                $this->movedFiles++;
                $this->updatedRecords++;
            } else {
                $this->errors[] = "Impossible de déplacer: {$oldPath}";
            }
        }
    }

    private function organizeHomeBlocks(): void
    {
        echo "\nRéorganisation des images HomeBlock...\n";
        try {
            $db = Database::get();
            $stmt = $db->query("SELECT * FROM home_blocks WHERE image IS NOT NULL AND image != ''");
            $blocks = $stmt->fetchAll();
        } catch (Exception $e) {
            $this->errors[] = "Erreur BD home_blocks: " . $e->getMessage();
            return;
        }

        foreach ($blocks as $block) {
            $filename = $block['image'];

            // Ignorer si déjà organisé (contient un /)
            if (strpos($filename, '/') !== false) {
                echo "  ✓ {$filename} (déjà organisé)\n";
                continue;
            }

            $oldPath = UPLOAD_DIR . '/' . $filename;

            if (!file_exists($oldPath)) {
                $this->errors[] = "Fichier HomeBlock introuvable: {$oldPath}";
                continue;
            }

            // Déterminer la date de création (utiliser la date du fichier)
            $fileTime = filemtime($oldPath);
            $date = new DateTime('@' . $fileTime, new DateTimeZone('Europe/Paris'));
            $subdir = $date->format('Y/m');

            // Créer le dossier de destination
            $newDir = UPLOAD_DIR . '/home_block/' . $subdir;
            if (!is_dir($newDir)) {
                @mkdir($newDir, 0755, true);
            }

            $newPath = $newDir . '/' . $filename;
            $newFilename = 'home_block/' . $subdir . '/' . $filename;

            // Déplacer le fichier
            if (rename($oldPath, $newPath)) {
                // Mettre à jour la BD
                Database::get()->prepare(
                    "UPDATE home_blocks SET image = ? WHERE id = ?"
                )->execute([$newFilename, $block['id']]);

                echo "  ✓ {$filename} → {$newFilename}\n";
                $this->movedFiles++;
                $this->updatedRecords++;
            } else {
                $this->errors[] = "Impossible de déplacer HomeBlock: {$oldPath}";
            }
        }
    }
}

try {
    $organizer = new UploadOrganizer();
    $organizer->run();
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
