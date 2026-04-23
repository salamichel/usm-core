<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;

class AuthController
{
    public function showLogin(array $params): void
    {
        if (Auth::check()) {
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }
        View::render('admin/login.twig');
    }

    public function handleLogin(array $params): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::login($email, $password)) {
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        View::render('admin/login.twig', ['error' => 'Email ou mot de passe incorrect.']);
    }

    public function logout(array $params): void
    {
        Auth::logout();
        header('Location: ' . BASE_URL . '/admin/login');
        exit;
    }
}
