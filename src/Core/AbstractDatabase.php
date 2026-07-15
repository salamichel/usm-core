<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

abstract class AbstractDatabase
{
    private static array $instances = [];

    protected static function connect(string $dsn, string $user, string $pass, string $errorMsg): PDO
    {
        $key = static::class;
        if (!isset(self::$instances[$key])) {
            try {
                self::$instances[$key] = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw $e;
                }
                http_response_code(500);
                exit($errorMsg);
            }
        }
        return self::$instances[$key];
    }
}
