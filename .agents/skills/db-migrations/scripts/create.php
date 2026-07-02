<?php
declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: php create.php description_de_migration\n";
    echo "Exemple : php create.php ajouter_table_sponsors\n";
    exit(1);
}

$description = trim($argv[1]);
// Nettoyer la description pour le nom de fichier
$description = preg_replace('/[^a-zA-Z0-5_]/', '_', $description);
$description = strtolower(trim($description, '_'));

$workspaceRoot = dirname(__DIR__, 4);
$migrationsDir = "$workspaceRoot/database/migrations";

if (!is_dir($migrationsDir)) {
    echo "Erreur : Le dossier $migrationsDir n'existe pas.\n";
    exit(1);
}

// Recherche du numéro le plus élevé
$highestNum = 0;
$files = scandir($migrationsDir);
foreach ($files as $file) {
    if (preg_match('/^(\d{3})_.*\.sql$/', $file, $matches)) {
        $num = (int)$matches[1];
        if ($num > $highestNum) {
            $highestNum = $num;
        }
    }
}

$nextNum = $highestNum + 1;
$prefix = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
$filename = "{$prefix}_{$description}.sql";
$targetPath = "$migrationsDir/$filename";

$sqlTemplate = <<<SQL
-- Migration : $description
-- Généré le : %DATE%

-- Mettez vos requêtes SQL idempotentes ci-dessous (ex. CREATE TABLE IF NOT EXISTS, INSERT IGNORE)

SQL;

$sqlContent = str_replace('%DATE%', date('Y-m-d H:i:s'), $sqlTemplate);

if (file_put_contents($targetPath, $sqlContent) !== false) {
    echo "Migration créée avec succès : database/migrations/$filename\n";
} else {
    echo "Erreur lors de la création du fichier.\n";
    exit(1);
}
