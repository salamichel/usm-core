<?php

namespace App\Controllers\Member;

use App\Core\View;
use App\Models\Participation;

class ParticipationController
{
    /**
     * Affiche le formulaire de mise à jour des participations de l'adhérent.
     * Route: GET /member/participations/update
     */
    public function updateForm(): void
    {
        // Sécurisation de l'espace adhérent
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            View::flash('error', 'Veuillez vous connecter pour accéder à cette page.');
            header('Location: /member/login');
            exit;
        }

        $userId = (int) $_SESSION['LogInId'];
        $manifestations = Participation::getUpcomingWithUserStatus($userId);

        // Enrichissement des données pour la vue
        foreach ($manifestations as &$m) {
            $typeLower = strtolower($m['ManifestationTypée']);
            // Détermine s'il s'agit d'un match pour adapter les choix du menu déroulant
            $m['is_match'] = str_contains($typeLower, 'match') || 
                             str_contains($typeLower, 'champ') || 
                             str_contains($typeLower, 'plateau');
        }

        View::render('member/participations_update.twig', [
            'manifestations' => $manifestations,
            'is_capitaine' => $_SESSION['Capitaine'] ?? false
        ]);
    }

    /**
     * Traite l'enregistrement des modifications.
     * Route: POST /member/participations/update
     */
    public function store(): void
    {
        // Sécurisation
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            header('Location: /member/login');
            exit;
        }

        $userId = (int) $_SESSION['LogInId'];
        $participations = $_POST['participations'] ?? [];

        if (!is_array($participations)) {
            View::flash('error', 'Données invalides.');
            header('Location: /member/participations/update');
            exit;
        }

        // Sauvegarde de chaque participation modifiée
        foreach ($participations as $manifestationId => $status) {
            $manifestationId = (int) $manifestationId;
            $status = trim((string) $status);
            
            // On ignore si l'ID est invalide
            if ($manifestationId > 0) {
                Participation::upsert($userId, $manifestationId, $status);
            }
        }

        View::flash('success', 'Vos participations ont été enregistrées avec succès.');
        header('Location: /member/participations/update');
        exit;
    }
}