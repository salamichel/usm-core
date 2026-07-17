<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class MemberEmailPreference
{
    /**
     * Retourne un tableau associatif de toutes les clés d'abonnements et leur état (1 par défaut).
     *
     * @param int $idJoueur
     * @param int $saisonId
     * @return array
     */
    public static function getPreferences(int $idJoueur, int $saisonId): array
    {
        $stmt = Database::get()->prepare("
            SELECT pref_key, is_subscribed 
            FROM member_email_preferences 
            WHERE id_joueur = ? AND saison_id = ?
        ");
        $stmt->execute([$idJoueur, $saisonId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['pref_key']] = (int)$row['is_subscribed'];
        }

        return $prefs;
    }

    /**
     * Enregistre ou met à jour une préférence.
     *
     * @param int $idJoueur
     * @param int $saisonId
     * @param string $key
     * @param bool $value
     */
    public static function setPreference(int $idJoueur, int $saisonId, string $key, bool $value): void
    {
        $intValue = $value ? 1 : 0;
        $db = Database::get();
        $stmt = $db->prepare("
            INSERT INTO member_email_preferences (id_joueur, saison_id, pref_key, is_subscribed)
            VALUES (:id_joueur, :saison_id, :pref_key, :is_subscribed)
            ON DUPLICATE KEY UPDATE is_subscribed = :is_subscribed_update
        ");
        $stmt->execute([
            'id_joueur'            => $idJoueur,
            'saison_id'            => $saisonId,
            'pref_key'             => $key,
            'is_subscribed'        => $intValue,
            'is_subscribed_update' => $intValue,
        ]);
    }

    /**
     * Vérifie si le joueur est abonné à une clé spécifique (retourne true par défaut si non spécifié).
     *
     * @param int $idJoueur
     * @param int $saisonId
     * @param string $key
     * @return bool
     */
    public static function isSubscribed(int $idJoueur, int $saisonId, string $key): bool
    {
        $stmt = Database::get()->prepare("
            SELECT is_subscribed 
            FROM member_email_preferences 
            WHERE id_joueur = ? AND saison_id = ? AND pref_key = ?
            LIMIT 1
        ");
        $stmt->execute([$idJoueur, $saisonId, $key]);
        $val = $stmt->fetchColumn();
        
        // Si aucune ligne n'existe, on retourne true (abonné par défaut)
        return $val === false ? true : ((int)$val === 1);
    }
}
