#!/usr/bin/env php
<?php
/**
 * Retroactively generate optimized image variants for all existing photos.
 * Run: php scripts/generate_variants.php
 *
 * Processes photos in batches to avoid memory/time limits.
 * Can be run multiple times safely (idempotent via has_variants flag).
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';
require ROOT . '/config/config.php';

use App\Services\ImageResizer;

$batchSize = (int)($argv[1] ?? 50);

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$total = (int)$pdo->query("SELECT COUNT(*) FROM photos WHERE has_variants = 0")->fetchColumn();

echo "Photos à traiter : {$total}\n";

if ($total === 0) {
    echo "Rien à faire.\n";
    exit(0);
}

$processed = 0;
$success   = 0;
$skipped   = 0;

$stmt   = $pdo->prepare("SELECT id, filename FROM photos WHERE has_variants = 0 LIMIT :limit");
$update = $pdo->prepare("UPDATE photos SET has_variants = 1 WHERE id = ?");

do {
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        break;
    }

    foreach ($rows as $photo) {
        $absolutePath = UPLOAD_DIR . '/' . $photo['filename'];

        if (!file_exists($absolutePath)) {
            echo "  [SKIP] Fichier introuvable : {$photo['filename']}\n";
            $update->execute([$photo['id']]);
            $skipped++;
            $processed++;
            continue;
        }

        $ok = ImageResizer::generateVariants($absolutePath);

        if ($ok) {
            $update->execute([$photo['id']]);
            echo "  [OK]   {$photo['filename']}\n";
            $success++;
        } else {
            echo "  [SKIP] Non-image ou image trop petite : {$photo['filename']}\n";
            $update->execute([$photo['id']]);
            $skipped++;
        }
        $processed++;
    }

    $remaining = $total - $processed;
    echo "Progression : {$processed}/{$total} (restant : {$remaining})\n";

} while (count($rows) === $batchSize);

echo "\nTerminé. {$success} variantes générées, {$skipped} ignorées.\n";
