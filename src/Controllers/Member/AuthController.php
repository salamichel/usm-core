<?php

namespace App\Controllers\Member;

use App\Core\View;
use App\Models\Joueur;
use App\Models\Saison;
use App\Models\EquipeSaisonJoueur;
use App\Services\Validator;

class AuthController
{
    /**
     * Affiche le formulaire de connexion de l'espace adhérent.
     * Route: GET /member/login
     */
    public function loginForm(): void
    {
        // Redirection si l'adhérent est déjà connecté
        if (isset($_SESSION['LogIn']) && $_SESSION['LogIn'] === true) {
            header('Location: /member/dashboard');
            exit;
        }

        $redirect = $_GET['redirect'] ?? '/member/dashboard';

        // Utilise une vue front-end
        View::render('auth/login.twig', [
            'redirect' => $redirect,
        ]);
    }

    /**
     * Traite la connexion.
     * Route: POST /member/login
     */
    public function login(): void
    {
        $redirectUrl = $_POST['redirect'] ?? '/member/dashboard';
        if (!str_starts_with($redirectUrl, '/') || str_starts_with($redirectUrl, '//')) {
            $redirectUrl = '/member/dashboard';
        }

        $v = Validator::make($_POST)
            ->required('Id', 'L\'adresse email est requise.')
            ->required('IdPassword', 'Le mot de passe est requis.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /member/login?redirect=' . urlencode($redirectUrl));
            exit;
        }

        $data = $v->getCleanData(['Id', 'IdPassword']);
        $user = Joueur::authenticate($data['Id'], $data['IdPassword']);

        if ($user) {
            $_SESSION['LogIn'] = true;
            $_SESSION['LogInId'] = $user['id_joueur'];
            $_SESSION['user_name'] = trim($user['Prénom'] . ' ' . $user['Nom']);
            $_SESSION['user_email'] = $user['Mel'];

            $caracteristiques = $user['Caracteristique'] ?? '';
            $_SESSION['Capitaine'] = str_contains($caracteristiques, 'Capitaine');
            $_SESSION['AdminWeb'] = str_contains($caracteristiques, 'Web');

            // Évaluer le statut de capitaine pour la saison en cours
            $saisonActive = Saison::getActive();
            $captainedTeams = $saisonActive ? EquipeSaisonJoueur::findCaptainedTeams((int)$user['id_joueur'], $saisonActive['id']) : [];
            $_SESSION['IsCaptainSaison'] = !empty($captainedTeams);

            View::flash('success', 'Bienvenue ' . $user['Prénom'] . ' !');
            header('Location: ' . $redirectUrl);
            exit;
        }

        View::flash('error', 'Mot de passe ou email incorrect.');
        header('Location: /member/login?redirect=' . urlencode($redirectUrl));
        exit;
    }

    /**
     * Déconnecte l'adhérent.
     * Route: POST /member/logout
     */
    public function logout(): void
    {
        $_SESSION['LogIn'] = false;
        $_SESSION['Capitaine'] = false;
        $_SESSION['AdminWeb'] = false;
        $_SESSION['IsCaptainSaison'] = false;
        
        unset($_SESSION['LogInId'], $_SESSION['user_name'], $_SESSION['user_email']);
        
        header('Location: /member/login');
        exit;
    }
}