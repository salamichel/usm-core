<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';
require ROOT . '/config/config.php';

use App\Core\Database;

try {
    $db = Database::get();
    $sql = file_get_contents(ROOT . '/database/migrations/033_create_email_logs.sql');
    $db->exec($sql);
    echo "Migration 033 applied successfully!";
} catch (\Throwable $e) {
    echo "Error applying migration: " . $e->getMessage();
}
