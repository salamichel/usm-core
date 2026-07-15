<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database extends AbstractDatabase
{
    public static function get(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        return self::connect($dsn, DB_USER, DB_PASS, 'Database connection error.');
    }
}
