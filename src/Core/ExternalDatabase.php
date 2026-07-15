<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class ExternalDatabase extends AbstractDatabase
{
    public static function get(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            EXT_DB_HOST, EXT_DB_NAME
        );
        return self::connect($dsn, EXT_DB_USER, EXT_DB_PASS, 'External database connection error.');
    }
}
