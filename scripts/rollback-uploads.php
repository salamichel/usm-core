<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require_once ROOT . '/config/config.php';
require_once ROOT . '/src/Core/Database.php';

use App\Core\Database;

class UploadRollback
{
    private int $movedFiles = 0;
    private int $updatedRecords = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "=== Rollback de la réorganisation des uploads ===\n\n";
        echo "⚠️  ATTENTION: Ce script déplace les fichiers vers le dossier racine /uploads\n\n";

        if (!$this->confirm("Continuer?")) {
            echo "Annulé.\n";
            return;
        }

        $this->rollbackPhotos();
        $this->rollbackHomeBlocks();

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

    private function rollbackPhotos(): void
    {
        echo "Rollback des photos...\n";
        $db = Database::get();

        $stmt = $db->query("SELECT * FROM photos WHERE filename LIKE '%/%' ORDER BY id ASC");
        $photos = $stmt->fetchAll();

        foreach ($photos as $photo) {
            $filename = $photo['filename'];
            $oldPath = UPLOAD_DIR . '/' . $filename;

            if (!file_exists($oldPath)) {
                $this->errors[] = "Fichier introuvable: {$oldPath}";
                continue;
            }

            // Extraire juste le nom du fichier
            $basename = basename($filename);
            $newPath = UPLOAD_DIR . '/' . $basename;

            // Gérer les doublons
            if (file_exists($newPath)) {
                $counter = 1;
                $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
                $ext = pathinfo($basename, PATHINFO_EXTENSION);
                while (file_exists(UPLOAD_DIR . '/' . $nameWithoutExt . '-' . $counter . '.' . $ext)) {
                    $counter++;
                }
                $basename = $nameWithoutExt . '-' . $counter . '.' . $ext;
                $newPath = UPLOAD_DIR . '/' . $basename;
            }

            // Déplacer le fichier
            if (rename($oldPath, $newPath)) {
                // Mettre à jour la BD
                Database::get()->prepare(
                    "UPDATE photos SET filename = ? WHERE id = ?"
                )->execute([$basename, $photo['id']]);

                echo "  ✓ {$filename} → {$basename}\n";
                $this->movedFiles++;
                $this->updatedRecords++;
            } else {
                $this->errors[] = "Impossible de déplacer: {$oldPath}";
            }
        }
    }

    private function rollbackHomeBlocks(): void
    {
        echo "\nRollback des images HomeBlock...\n";
        $db = Database::get();

        $stmt = $db->query("SELECT * FROM home_blocks WHERE image LIKE '%/%'");
        $blocks = $stmt->fetchAll();

        foreach ($blocks as $block) {
            $filename = $block['image'];
            $oldPath = UPLOAD_DIR . '/' . $filename;

            if (!file_exists($oldPath)) {
                $this->errors[] = "Fichier HomeBlock introuvable: {$oldPath}";
                continue;
            }

            // Extraire juste le nom du fichier
            $basename = basename($filename);
            $newPath = UPLOAD_DIR . '/' . $basename;

            // Gérer les doublons
            if (file_exists($newPath)) {
                $counter = 1;
                $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
                $ext = pathinfo($basename, PATHINFO_EXTENSION);
                while (file_exists(UPLOAD_DIR . '/' . $nameWithoutExt . '-' . $counter . '.' . $ext)) {
                    $counter++;
                }
                $basename = $nameWithoutExt . '-' . $counter . '.' . $ext;
                $newPath = UPLOAD_DIR . '/' . $basename;
            }

            // Déplacer le fichier
            if (rename($oldPath, $newPath)) {
                // Mettre à jour la BD
                Database::get()->prepare(
                    "UPDATE home_blocks SET image = ? WHERE id = ?"
                )->execute([$basename, $block['id']]);

                echo "  ✓ {$filename} → {$basename}\n";
                $this->movedFiles++;
                $this->updatedRecords++;
            } else {
                $this->errors[] = "Impossible de déplacer HomeBlock: {$oldPath}";
            }
        }
    }

    private function confirm(string $prompt): bool
    {
        echo "{$prompt} (y/N) ";
        $response = trim(fgets(STDIN));
        return strtolower($response) === 'y' || strtolower($response) === 'yes';
    }
}

$rollback = new UploadRollback();
$rollback->run();
