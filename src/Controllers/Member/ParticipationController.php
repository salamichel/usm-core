<?php

namespace App\Controllers\Member;

use App\Core\View;
use App\Models\Participation;
use App\Models\Joueur;
use App\Models\Saison;

class ParticipationController
{
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
            // 1. Vérifier si le joueur est actuellement sélectionné pour cet événement
            $db = \App\Core\ExternalDatabase::get();
            if ($db) {
                $stmt = $db->prepare("SELECT Participation FROM Participation WHERE id_joueur = ? AND id_manifestation = ?");
                $stmt->execute([$userId, $manifestationId]);
                $currentStatus = $stmt->fetchColumn() ?: '';
                if (\App\Helpers\ParticipationStatus::categorize((string)$currentStatus) === 'selected') {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'message' => 'Vous êtes convoqué pour ce match, impossible de modifier votre statut.']);
                    exit;
                }
            }

            // 2. Vérifier si le créneau de cet événement chevauche une autre sélection
            if (\App\Services\AgendaService::isOverlappingSelected($userId, $manifestationId)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Créneau concurrent à un match pour lequel vous êtes convoqué.']);
                exit;
            }

            // 3. Mettre à jour la participation (Votre code d'origine)
            Participation::upsert($userId, $manifestationId, $status);
    
            // ==========================================
            // 2. RÉCUPÉRER LES COMPTEURS EXACTS NORMALISÉS POUR LE JS
            // ==========================================
            $counts = \App\Services\AgendaService::getNormalizedCounts($manifestationId);
    
            // 3. Renvoyer la réponse enrichie
            echo json_encode([
                'ok' => true, 
                'message' => 'Participation mise à jour',
                'new_status' => $status,
                'counts' => $counts
            ]);
    
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Erreur serveur']);
        }
        exit;
    }
}
