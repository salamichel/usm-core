<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function login(string $email, string $password): bool
    {
        if ($email === ADMIN_EMAIL && password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email']     = $email;
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['admin_logged_in']);
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/admin/login');
            exit;
        }
    }
}
