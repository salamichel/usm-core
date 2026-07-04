<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class EquipeSaisonJoueur
{
    /**
     * Joueurs membres de l'équipe-saison, triés Nom ASC.
     */
    public static function findByEquipeSaison(int $equipeSaisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT js.*, esj.is_captain
             FROM equipe_saison_joueur esj
             JOIN joueur_snapshots js ON js.id = esj.snapshot_id
             WHERE esj.equipe_saison_id = ?
             ORDER BY js.nom ASC, js.prenom ASC"
        );
        $stmt->execute([$equipeSaisonId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }
        return $rows;
    }

    public static function countByEquipeSaison(int $equipeSaisonId): int
    {
        $stmt = Database::get()->prepare(
            "SELECT COUNT(*) FROM equipe_saison_joueur WHERE equipe_saison_id = ?"
        );
        $stmt->execute([$equipeSaisonId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Snapshots de la saison NON encore membres de cette équipe-saison.
     */
    public static function getAvailableSnapshots(int $equipeSaisonId, int $saisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT js.*
             FROM joueur_snapshots js
             WHERE js.saison_id = ?
               AND js.id NOT IN (
                 SELECT snapshot_id FROM equipe_saison_joueur WHERE equipe_saison_id = ?
               )
             ORDER BY js.nom ASC, js.prenom ASC"
        );
        $stmt->execute([$saisonId, $equipeSaisonId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }
        return $rows;
    }

    public static function add(int $equipeSaisonId, int $snapshotId): void
    {
        Database::get()->prepare(
            "INSERT IGNORE INTO equipe_saison_joueur (equipe_saison_id, snapshot_id) VALUES (?, ?)"
        )->execute([$equipeSaisonId, $snapshotId]);
    }

    public static function remove(int $equipeSaisonId, int $snapshotId): void
    {
        Database::get()->prepare(
            "DELETE FROM equipe_saison_joueur WHERE equipe_saison_id = ? AND snapshot_id = ?"
        )->execute([$equipeSaisonId, $snapshotId]);
    }

    /**
     * Retrouve les équipes d'un joueur pour une saison donnée.
     * Retourne les détails de chaque équipe-saison.
     *
     * @param int $joueurId L'ID du joueur (id_joueur de la base externe)
     * @param int $saisonId L'ID de la saison
     * @return array Liste des équipes avec détails (equipes_config + equipe_saison)
     */
    public static function findEquipesByJoueur(int $joueurId, int $saisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT ec.id, ec.libelle, ec.description_courte, ec.description, ec.categorie,
                    es.id as equipe_saison_id, es.saison_id
             FROM equipe_saison_joueur esj
             JOIN joueur_snapshots js ON js.id = esj.snapshot_id
             JOIN equipe_saison es ON es.id = esj.equipe_saison_id
             JOIN equipes_config ec ON ec.id = es.equipe_id
             WHERE js.id_joueur = ? AND es.saison_id = ?
             ORDER BY ec.categorie ASC, ec.libelle ASC"
        );
        $stmt->execute([$joueurId, $saisonId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère uniquement les capitaines d'une équipe pour une saison.
     */
    public static function findCaptainsByEquipeSaison(int $equipeSaisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT js.*
             FROM equipe_saison_joueur esj
             JOIN joueur_snapshots js ON js.id = esj.snapshot_id
             WHERE esj.equipe_saison_id = ? AND esj.is_captain = 1
             ORDER BY js.nom ASC, js.prenom ASC"
        );
        $stmt->execute([$equipeSaisonId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }
        return $rows;
    }

    /**
     * Bascule le statut de capitaine d'un joueur.
     */
    public static function toggleCaptain(int $equipeSaisonId, int $snapshotId): void
    {
        $stmt = Database::get()->prepare(
            "UPDATE equipe_saison_joueur 
             SET is_captain = 1 - is_captain 
             WHERE equipe_saison_id = ? AND snapshot_id = ?"
        );
        $stmt->execute([$equipeSaisonId, $snapshotId]);
    }

    /**
     * Récupère les équipes pour lesquelles un joueur est capitaine pour une saison donnée.
     */
    public static function findCaptainedTeams(int $joueurId, int $saisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT ec.id, ec.libelle, ec.slug_colonne, ec.manifestation_filter, ec.categorie, ec.min_players, es.id as equipe_saison_id
             FROM equipe_saison_joueur esj
             JOIN joueur_snapshots js ON js.id = esj.snapshot_id
             JOIN equipe_saison es ON es.id = esj.equipe_saison_id
             JOIN equipes_config ec ON ec.id = es.equipe_id
             WHERE js.id_joueur = ? AND es.saison_id = ? AND esj.is_captain = 1"
        );
        $stmt->execute([$joueurId, $saisonId]);
        return $stmt->fetchAll();
    }
}
