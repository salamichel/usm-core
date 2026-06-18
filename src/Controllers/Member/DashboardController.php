<?php

namespace App\Controllers\Member;

use App\Core\View;

class DashboardController
{
    /**
     * Affiche le tableau de bord de l'adhérent.
     * Route: GET /member/dashboard
     */
    public function index(): void
    {
        // Vérification d'accès spécifique aux adhérents (différent de l'admin CMS)
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            View::flash('error', 'Veuillez vous connecter pour accéder à l\'espace adh\u00e9rent.');
            header('Location: /member/login');
            exit;
        }

        View::render('member/dashboard.twig', [
            'user_name' => $_SESSION['user_name'] ?? 'Joueur',
            'user_email' => $_SESSION['user_email'] ?? '',
            'is_capitaine' => $_SESSION['Capitaine'] ?? false,
            'is_admin_web' => $_SESSION['AdminWeb'] ?? false,
        ]);
    }
}