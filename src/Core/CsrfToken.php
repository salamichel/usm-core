<?php

declare(strict_types=1);

namespace App\Core;

class CsrfToken
{
    private const SESSION_KEY = '_csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function generate(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !$token) {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    public static function validateFromPost(): bool
    {
        $token = $_POST['_csrf_token'] ?? null;
        return self::validate($token);
    }
}
