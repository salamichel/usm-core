<?php

namespace App\Models;

use App\Core\ExternalDatabase;
use PDO;

class Participation
{
    /**
     * Récupère les événements à venir et le statut de participation actuel du joueur.
     * On limite aux événements futurs ou récents (depuis hier).
     *
     * @param int $userId ID du joueur
     * @return array
     */
    public static function getUpcomingWithUserStatus(int $userId): array
    {
        $db = ExternalDatabase::get();
        
        $sql = "
            SELECT 
                m.id_manifestation, 
                m.ManifestationTypée, 
                m.Date, 
                m.Lieu, 
                m.Statut,
                p.Participation as user_status
            FROM Manifestation m
            LEFT JOIN Participation p ON m.id_manifestation = p.id_manifestation AND p.id_joueur = ?
            WHERE m.Date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY m.Date ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insère, met à jour ou supprime la participation d'un joueur à un événement.
     *
     * @param int $userId ID du joueur
     * @param int $manifestationId ID de l'événement
     * @param string $status Le statut (ex: 'Disponible', 'Présent', '.')
     */
    public static function upsert(int $userId, int $manifestationId, string $status): void
    {
        $db = ExternalDatabase::get();

        // Si le statut est vide ou un point, on supprime l'entrée
        if ($status === '' || $status === '.') {
            $stmt = $db->prepare("DELETE FROM Participation WHERE id_joueur = ? AND id_manifestation = ?");
            $stmt->execute([$userId, $manifestationId]);
            return;
        }

        // Vérification de l'existence
        $stmt = $db->prepare("SELECT 1 FROM Participation WHERE id_joueur = ? AND id_manifestation = ? LIMIT 1");
        $stmt->execute([$userId, $manifestationId]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Mise à jour
            $update = $db->prepare("
                UPDATE Participation 
                SET Participation = ?, S_MAJ = NOW() 
                WHERE id_joueur = ? AND id_manifestation = ?
            ");
            $update->execute([$status, $userId, $manifestationId]);
        } else {
            // Insertion
            $insert = $db->prepare("
                INSERT INTO Participation (id_joueur, id_manifestation, Participation, S_MAJ) 
                VALUES (?, ?, ?, NOW())
            ");
            $insert->execute([$userId, $manifestationId, $status]);
        }
    }
}