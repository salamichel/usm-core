<?php

namespace App\Controllers\Member;

use App\Core\View;
use App\Models\Participation;
use App\Models\Joueur;
use App\Models\Saison;

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

        // Récupérer les catégories du joueur pour filtrer les événements pertinents
        $categories = Joueur::getCategories($userId);
        
        // Récupérer les manifestations pertinentes pour ce joueur
        $manifestations = Participation::getUpcomingForMember($userId, $categories);

        // Enrichissement des données pour la vue
        foreach ($manifestations as &$m) {
            $typeLower = strtolower($m['ManifestationTypée']);
            // Détermine s'il s'agit d'un match pour adapter les choix du menu déroulant
            $m['is_match'] = str_contains($typeLower, 'match') || 
                             str_contains($typeLower, 'champ') || 
                             str_contains($typeLower, 'plateau');
            // Extraction du titre simplifié (segment 3 de "Disponibilités - Match - Match DEP")
            $parts = explode(' - ', $m['ManifestationTypée'], 3);
            $m['titre'] = $parts[2] ?? $m['ManifestationTypée'];
            $m['type_simple'] = $parts[1] ?? '';
        }

        View::render('member/participations_update.twig', [
            'manifestations' => $manifestations,
            'user_name' => $_SESSION['user_name'] ?? 'Joueur',
        ]);
    }

    /**
     * Endpoint API pour la sauvegarde AJAX des participations.
     * Route: POST /api/member/participations/upsert
     * Accepts: JSON {manifestation_id: int, status: string}
     * Returns: JSON {ok: bool, message: string}
     */
    public function apiUpsert(): void
    {
        // Vérification d'accès membre
        if (!isset($_SESSION['LogIn']) || $_SESSION['LogIn'] !== true) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Non authentifié']);
            exit;
        }

        header('Content-Type: application/json');

        $userId = (int) $_SESSION['LogInId'];
        
        // Récupérer le JSON du corps de la requête
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['manifestation_id']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Données invalides']);
            exit;
        }

        $manifestationId = (int) $input['manifestation_id'];
        $status = trim((string) $input['status']);

        try {
            // Mettre à jour la participation
            Participation::upsert($userId, $manifestationId, $status);
            echo json_encode(['ok' => true, 'message' => 'Participation mise à jour']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Erreur serveur']);
        }
        exit;
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
