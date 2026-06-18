<?php

namespace App\Controllers\Member;

use App\Core\View;
use App\Models\Joueur;
use App\Models\Saison;
use App\Models\EquipeSaisonJoueur;
use App\Services\Validator;

class ProfileController
{
    /**
     * Affiche le profil du membre connecté.
     * Route: GET /member/profile
     */
    public function show(): void
    {
        // Vérification d'accès adhérent
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            View::flash('error', 'Veuillez vous connecter pour accéder à cette page.');
            header('Location: /member/login');
            exit;
        }

        $userId = (int) $_SESSION['LogInId'];
        $joueur = Joueur::findById($userId);

        if (!$joueur) {
            View::flash('error', 'Profil non trouvé.');
            header('Location: /member/dashboard');
            exit;
        }

        // Récupérer la saison active et les équipes du joueur
        $saisonActive = Saison::getActive();
        $equipes = [];
        
        if ($saisonActive) {
            $equipes = EquipeSaisonJoueur::findEquipesByJoueur($userId, $saisonActive['id']);
        }

        View::render('member/profile.twig', [
            'joueur' => $joueur,
            'user_name' => $_SESSION['user_name'] ?? 'Joueur',
            'user_email' => $_SESSION['user_email'] ?? '',
            'saison_active' => $saisonActive,
            'equipes' => $equipes,
        ]);
    }

    /**
     * Traite la mise à jour du profil.
     * Route: POST /member/profile
     */
    public function update(): void
    {
        // Vérification d'accès adhérent
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            header('Location: /member/login');
            exit;
        }

        $userId = (int) $_SESSION['LogInId'];

        // Validation des champs
        $v = Validator::make($_POST)
            ->required('Prenom', 'Le prénom est requis.')
            ->required('Nom', 'Le nom est requis.')
            ->required('Mel', 'L\'email est requis.')
            ->email('Mel', 'L\'email n\'est pas valide.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /member/profile');
            exit;
        }

        $data = $v->getCleanData(['Prenom', 'Nom', 'Mel']);

        // Mise à jour du profil (si la méthode existe dans le modèle)
        try {
            Joueur::update($userId, [
                'Prénom' => $data['Prenom'],
                'Nom' => $data['Nom'],
                'Mel' => $data['Mel'],
            ]);

            // Mise à jour de la session avec le nouvel email/nom
            $_SESSION['user_name'] = trim($data['Prenom'] . ' ' . $data['Nom']);
            $_SESSION['user_email'] = $data['Mel'];

            View::flash('success', 'Votre profil a été mis à jour avec succès.');
        } catch (\Exception $e) {
            View::flash('error', 'Erreur lors de la mise à jour du profil.');
        }

        header('Location: /member/profile');
        exit;
    }
}
