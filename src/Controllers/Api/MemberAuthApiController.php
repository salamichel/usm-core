<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Joueur;
use App\Models\Saison;
use App\Models\EquipeSaisonJoueur;

class MemberAuthApiController
{
    /**
     * Tente de restaurer la session adhérent via le token persistant du localStorage.
     * Route: POST /api/member/auto-login
     */
    public function autoLogin(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];
        $token = trim($data['token'] ?? '');

        if (!$token) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Token manquant']);
            exit;
        }

        $user = Joueur::verifyAuthToken($token);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Token invalide ou expiré']);
            exit;
        }

        // Restauration de la session utilisateur
        $_SESSION['LogIn'] = true;
        $_SESSION['LogInId'] = $user['id_joueur'];
        $_SESSION['user_name'] = trim($user['Prénom'] . ' ' . $user['Nom']);
        $_SESSION['user_email'] = $user['Mel'];

        $caracteristiques = $user['Caracteristique'] ?? '';
        $_SESSION['Capitaine'] = str_contains($caracteristiques, 'Capitaine');
        $_SESSION['AdminWeb'] = str_contains($caracteristiques, 'Web');

        $saisonActive = Saison::getActive();
        $captainedTeams = $saisonActive ? EquipeSaisonJoueur::findCaptainedTeams((int)$user['id_joueur'], $saisonActive['id']) : [];
        $_SESSION['IsCaptainSaison'] = !empty($captainedTeams);

        // Renouvellement du token de session pour sécurité et fraîcheur
        $refreshedToken = Joueur::generateAuthToken($user);

        echo json_encode([
            'ok'        => true,
            'user_name' => $_SESSION['user_name'],
            'token'     => $refreshedToken
        ]);
        exit;
    }
}
