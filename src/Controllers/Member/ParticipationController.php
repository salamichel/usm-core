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

    /**
     * Action publique pour traiter la réponse rapide par email.
     * Route: GET /public/participation/update
     */
    public function publicUpdate(): void
    {
        $playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;
        $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
        $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
        $token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

        if (!$playerId || !$eventId || !$status || !$token) {
            View::render('agenda/participation_confirmed.twig', [
                'success' => false,
                'message' => 'Lien incomplet ou invalide.'
            ]);
            exit;
        }

        // Vérifier le token
        $expectedToken = \App\Models\Participation::generateEmailToken($playerId, $eventId, $status);
        if (!hash_equals($expectedToken, $token)) {
            View::render('agenda/participation_confirmed.twig', [
                'success' => false,
                'message' => 'Ce lien de réponse rapide est invalide ou a expiré.'
            ]);
            exit;
        }

        // Valider et normaliser le statut
        $allowedStatuses = [
            'Disponible' => 'Disponible',
            'Disponible si nécessaire' => 'Disponible si nécessaire',
            'Indisponible' => 'Indisponible',
            'Présent(e)' => 'Présent(e)',
            'Absent(e)' => 'Absent(e)',
            'Présent' => 'Présent(e)',
            'Absent' => 'Absent(e)'
        ];
        if (!isset($allowedStatuses[$status])) {
            View::render('agenda/participation_confirmed.twig', [
                'success' => false,
                'message' => 'Statut de participation inconnu.'
            ]);
            exit;
        }
        $status = $allowedStatuses[$status];

        try {
            $event = \App\Services\AgendaService::getEventById($eventId);
            if (!$event) {
                View::render('agenda/participation_confirmed.twig', [
                    'success' => false,
                    'message' => 'Événement introuvable.'
                ]);
                exit;
            }

            // 1. Vérifier si le joueur est sélectionné
            $db = \App\Core\ExternalDatabase::get();
            $stmt = $db->prepare("SELECT Participation FROM Participation WHERE id_joueur = ? AND id_manifestation = ?");
            $stmt->execute([$playerId, $eventId]);
            $currentStatus = $stmt->fetchColumn() ?: '';
            if (str_contains($currentStatus, 'Sélectionné')) {
                View::render('agenda/participation_confirmed.twig', [
                    'success' => false,
                    'message' => 'Vous êtes convoqué(e) pour ce match, votre statut ne peut pas être modifié.',
                    'event' => $event,
                    'status' => $currentStatus
                ]);
                exit;
            }

            // 2. Vérifier le chevauchement
            if (\App\Services\AgendaService::isOverlappingSelected($playerId, $eventId)) {
                View::render('agenda/participation_confirmed.twig', [
                    'success' => false,
                    'message' => 'Vous avez un créneau concurrent avec un autre match pour lequel vous êtes déjà sélectionné(e).',
                    'event' => $event,
                    'status' => $status
                ]);
                exit;
            }

            // 3. Mettre à jour la participation
            \App\Models\Participation::upsert($playerId, $eventId, $status);

            // Log de l'action
            \App\Services\Logger::audit()->info('Public participation update via email link', [
                'player_id' => $playerId,
                'event_id' => $eventId,
                'status' => $status
            ]);

            View::render('agenda/participation_confirmed.twig', [
                'success' => true,
                'message' => 'Votre réponse a bien été enregistrée.',
                'event' => $event,
                'status' => $status
            ]);
        } catch (\Throwable $e) {
            \App\Services\Logger::errors()->error('Error in publicUpdate', [
                'exception' => $e->getMessage(),
                'player_id' => $playerId,
                'event_id' => $eventId
            ]);
            View::render('agenda/participation_confirmed.twig', [
                'success' => false,
                'message' => 'Une erreur interne est survenue. Veuillez vous connecter pour mettre à jour votre disponibilité.'
            ]);
        }
        exit;
    }
}
