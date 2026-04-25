<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class ExternalDatabase
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                EXT_DB_HOST, EXT_DB_NAME
            );
            try {
                self::$instance = new PDO($dsn, EXT_DB_USER, EXT_DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw $e;
                }
                http_response_code(500);
                exit('External database connection error.');
            }
        }
        return self::$instance;
    }
}
