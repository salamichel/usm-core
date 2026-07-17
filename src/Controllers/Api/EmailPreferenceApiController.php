<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\MemberEmailPreference;
use App\Models\MotsClef;
use App\Models\Saison;
use App\Models\EquipeSaisonJoueur;
use App\Core\Auth;
use App\Services\Logger;

class EmailPreferenceApiController
{
    /**
     * Récupère les préférences de courriels pour un joueur donné.
     * Route: GET /api/member-email-preferences/get?player_id=X
     */
    public function get(): void
    {
        header('Content-Type: application/json');

        $playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;
        if ($playerId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ID joueur manquant ou invalide.']);
            exit;
        }

        // Contrôle des accès
        if (!$this->isAuthorized($playerId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Accès interdit.']);
            exit;
        }

        $saison = Saison::getActive();
        if (!$saison) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Saison active introuvable.']);
            exit;
        }
        $saisonId = (int)$saison['id'];

        $trainingTypes = MotsClef::getTrainingTypes();
        $dbPrefs = MemberEmailPreference::getPreferences($playerId, $saisonId);

        // Préparer la liste complète des préférences
        $preferences = [
            'match' => $dbPrefs['match'] ?? 1,
            'weekly_presence' => $dbPrefs['weekly_presence'] ?? 1,
            'club_life' => $dbPrefs['club_life'] ?? 1,
        ];

        foreach ($trainingTypes as $type) {
            $preferences[$type] = $dbPrefs[$type] ?? 1;
        }

        echo json_encode([
            'ok' => true,
            'preferences' => $preferences,
            'training_types' => $trainingTypes
        ]);
        exit;
    }

    /**
     * Met à jour une préférence.
     * Route: POST /api/member-email-preferences/update
     */
    public function update(): void
    {
        header('Content-Type: application/json');

        // Récupérer le corps de la requête (supporte JSON ou POST standard)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $playerId = isset($input['player_id']) ? (int)$input['player_id'] : 0;
        $key = isset($input['pref_key']) ? trim((string)$input['pref_key']) : '';
        $value = isset($input['is_subscribed']) ? (bool)$input['is_subscribed'] : true;

        if ($playerId <= 0 || empty($key)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Données manquantes ou invalides.']);
            exit;
        }

        // Contrôle des accès
        if (!$this->isAuthorized($playerId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Accès interdit.']);
            exit;
        }

        $saison = Saison::getActive();
        if (!$saison) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Saison active introuvable.']);
            exit;
        }
        $saisonId = (int)$saison['id'];

        // Enregistrer la préférence
        MemberEmailPreference::setPreference($playerId, $saisonId, $key, $value);

        echo json_encode([
            'ok' => true,
            'message' => 'Préférence mise à jour avec succès.',
            'player_id' => $playerId,
            'pref_key' => $key,
            'is_subscribed' => $value
        ]);
        exit;
    }

    /**
     * Vérifie si l'utilisateur actuel est autorisé à voir/modifier les préférences du joueur.
     */
    private function isAuthorized(int $playerId): bool
    {
        // 1. Autoriser si Admin
        if (Auth::check()) {
            return true;
        }

        // 2. Autoriser si le joueur lui-même
        $currentUserId = isset($_SESSION['LogInId']) ? (int)$_SESSION['LogInId'] : 0;
        if ($currentUserId > 0 && $currentUserId === $playerId) {
            return true;
        }

        // 3. Autoriser si Capitaine du joueur dans la saison en cours
        if ($currentUserId > 0) {
            $saison = Saison::getActive();
            if ($saison) {
                $saisonId = (int)$saison['id'];
                $captainedTeams = EquipeSaisonJoueur::findCaptainedTeams($currentUserId, $saisonId);
                
                if (!empty($captainedTeams)) {
                    $playerTeams = EquipeSaisonJoueur::findEquipesByJoueur($playerId, $saisonId);
                    
                    $captainedIds = array_column($captainedTeams, 'id');
                    $playerIds = array_column($playerTeams, 'id');
                    
                    if (!empty(array_intersect($captainedIds, $playerIds))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
