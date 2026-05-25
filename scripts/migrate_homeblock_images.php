<?php
declare(strict_types=1);

// Définir ROOT en utilisant le répertoire de travail actuel, car le script est exécuté via CLI
if (!defined('ROOT')) {
    define('ROOT', $_SERVER['PWD'] ?? dirname(__DIR__));
}

// Charge les configurations de l'application
require_once ROOT . '/config/config.php';

// Autoloader de Composer pour charger les classes
require_once ROOT . '/vendor/autoload.php';

use App\Core\Database;
use App\Models\HomeBlock;
use App\Models\Photo;
use App\Services\UploadPathManager;

echo "Démarrage de la migration des images HomeBlock...\n";

// Le singleton Database utilisera les constantes DB_* définies dans config.php
$db = Database::get();

// 1. Récupérer les HomeBlocks avec des images existantes
$stmt = $db->query("SELECT id, image FROM home_blocks WHERE image IS NOT NULL AND image != ''");
$homeBlocksWithImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$migratedCount = 0;
foreach ($homeBlocksWithImages as $block) {
    $homeBlockId = (int)$block['id'];
    $imagePath   = $block['image'];

    // Vérifier si l'image existe réellement sur le système de fichiers
    $fullPath = UploadPathManager::getFullPath($imagePath);
    if (!file_exists($fullPath)) {
        echo "AVERTISSEMENT: L'image '{$imagePath}' pour HomeBlock #{$homeBlockId} n'existe pas sur le disque. Sautée.\n";
        continue;
    }

    // Vérifier si une photo existe déjà pour ce HomeBlock avec ce fichier
    $existingPhotoStmt = $db->prepare(
        "SELECT id FROM photos WHERE entity_type = 'home_block' AND entity_id = ? AND filename = ?"
    );
    $existingPhotoStmt->execute([$homeBlockId, $imagePath]);
    if ($existingPhotoStmt->fetch()) {
        echo "INFO: Une photo existe déjà pour HomeBlock #{$homeBlockId} avec le fichier '{$imagePath}'. Sautée.\n";
        continue;
    }

    try {
        // 2. Créer une nouvelle entrée dans la table photos
        // Le chemin de l'image est déjà relatif au répertoire d'upload, donc on peut l'utiliser directement comme filename
        Photo::create(
            'home_block',
            $homeBlockId,
            $imagePath,
            null, // caption
            0,    // position (sera la première photo, donc la couverture)
            false // has_variants, car l'ancienne image n'avait pas forcément de variantes
        );
        echo "Image '{$imagePath}' migrée pour HomeBlock #{$homeBlockId}.\n";
        $migratedCount++;
    } catch (Throwable $e) {
        echo "ERREUR: Échec de la migration de l'image '{$imagePath}' pour HomeBlock #{$homeBlockId}: " . $e->getMessage() . "\n";
    }
}

// 3. Supprimer la colonne 'image' de la table home_blocks
try {
    // Vérifier si la colonne existe avant de tenter de la supprimer
    $columnExists = $db->query("SHOW COLUMNS FROM home_blocks LIKE 'image'")->fetch();
    if ($columnExists) {
        $db->exec("ALTER TABLE home_blocks DROP COLUMN image");
        echo "Colonne 'image' supprimée de la table home_blocks.\n";
    } else {
        echo "INFO: La colonne 'image' n'existe pas dans la table home_blocks. Aucune suppression nécessaire.\n";
    }
} catch (Throwable $e) {
    echo "ERREUR: Échec de la suppression de la colonne 'image' de home_blocks: " . $e->getMessage() . "\n";
}

echo "Migration terminée. {$migratedCount} images HomeBlock migrées.\n";