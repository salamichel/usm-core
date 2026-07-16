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
        $trainingTypes = [];
        $preferences = [];

        if ($saisonActive) {
            $equipes = EquipeSaisonJoueur::findEquipesByJoueur($userId, $saisonActive['id']);
            $trainingTypes = \App\Models\MotsClef::getTrainingTypes();
            $dbPrefs = \App\Models\MemberEmailPreference::getPreferences($userId, $saisonActive['id']);
            $knownKeys = array_merge(['match', 'weekly_presence'], $trainingTypes);
            foreach ($knownKeys as $key) {
                $preferences[$key] = $dbPrefs[$key] ?? 1;
            }
        }

        View::render('member/profile.twig', [
            'joueur' => $joueur,
            'equipes' => $equipes,
            'saison' => $saisonActive,
            'training_types' => $trainingTypes,
            'preferences' => $preferences,
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
            ->email('Mel', 'L\'email n\'est pas valide.')
            ->maxLength('Adresse', 200, 'L\'adresse ne peut pas dépasser 200 caractères.')
            ->maxLength('Commune', 50, 'La commune ne peut pas dépasser 50 caractères.')
            ->maxLength('Téléphone', 50, 'Le téléphone ne peut pas dépasser 50 caractères.')
            ->custom('CodePostal', fn($value) => $value === '' || preg_match('/^\d{5}$/', $value), 'Le code postal doit contenir 5 chiffres.')
            ->custom('DateNaissance', function ($value) {
                if ($value === '' || $value === null) {
                    return true;
                }
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
            }, 'La date de naissance est invalide.');

        if ($v->fails()) {
            View::flash('error', $v->firstError());
            header('Location: /member/profile');
            exit;
        }

        $data = $v->getCleanData(['Prenom', 'Nom', 'Mel', 'Adresse', 'Commune', 'CodePostal', 'Téléphone', 'DateNaissance']);

        // Mise à jour du profil (si la méthode existe dans le modèle)
        try {
            Joueur::update($userId, [
                'Prénom' => $data['Prenom'],
                'Nom' => $data['Nom'],
                'Mel' => $data['Mel'],
                'Adresse' => $data['Adresse'] ?: null,
                'Commune' => $data['Commune'] ?: null,
                'CodePostal' => $data['CodePostal'] ?: null,
                'Téléphone' => $data['Téléphone'],
                'DateNaissance' => $data['DateNaissance'] ?: null,
            ]);

            // Mise à jour de la session avec le nouvel email/nom
            $_SESSION['user_name'] = trim($data['Prenom'] . ' ' . $data['Nom']);
            $_SESSION['user_email'] = $data['Mel'];

            // Sauvegarde des préférences d'emails
            $saisonActive = Saison::getActive();
            if ($saisonActive) {
                $saisonId = (int)$saisonActive['id'];
                $trainingTypes = \App\Models\MotsClef::getTrainingTypes();

                // 1. Préférence matchs
                $prefMatch = isset($_POST['pref_match']) && $_POST['pref_match'] === '1';
                \App\Models\MemberEmailPreference::setPreference($userId, $saisonId, 'match', $prefMatch);

                // 2. Préférence rappel hebdomadaire
                $prefWeekly = isset($_POST['pref_weekly_presence']) && $_POST['pref_weekly_presence'] === '1';
                \App\Models\MemberEmailPreference::setPreference($userId, $saisonId, 'weekly_presence', $prefWeekly);

                // 3. Préférences entraînements
                $prefTrainings = $_POST['pref_trainings'] ?? [];
                foreach ($trainingTypes as $type) {
                    $isSubscribed = isset($prefTrainings[$type]) && $prefTrainings[$type] === '1';
                    \App\Models\MemberEmailPreference::setPreference($userId, $saisonId, $type, $isSubscribed);
                }
            }

            View::flash('success', 'Votre profil a été mis à jour avec succès.');
        } catch (\Exception $e) {
            View::flash('error', 'Erreur lors de la mise à jour du profil.');
        }

        header('Location: /member/profile');
        exit;
    }
}
