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

    /**
     * Génère un token sécurisé pour la réponse rapide par email.
     */
    public static function generateEmailToken(int $playerId, int $eventId, string $status): string
    {
        $salt = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : 'usm_volley_fallback_salt_2026';
        return hash_hmac('sha256', $playerId . '-' . $eventId . '-' . $status, $salt);
    }

    /**
     * Retourne les motifs génériques d'événements à toujours inclure pour tous les membres.
     * (Exclut les entraînements qui sont filtrés par équipe/catégorie).
     *
     * @return array
     */
    public static function getGenericEventPatterns(): array
    {
        return [
            '%Tournoi%',
            '%Club%',
        ];
    }

    /**
     * Construit les clauses SQL WHERE et les bindings associés pour les événements
     * éligibles d'un membre (matchs, événements génériques et entraînements de ses équipes).
     *
     * @param int $userId ID du joueur
     * @param array $categories Liste des catégories du joueur (ex: ['DEP', 'L1'])
     * @param string $tableAlias Alias de la table Manifestation (ex: 'm' ou '')
     * @return array ['conditions' => array, 'bindings' => array]
     */
    public static function getMemberEventConditions(int $userId, array $categories, string $tableAlias = ''): array
    {
        if (empty($categories)) {
            return ['conditions' => [], 'bindings' => []];
        }

        $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';

        $genericEventPatterns = self::getGenericEventPatterns();

        $conditions = [];
        $bindings = [];

        // 1. Événements génériques (tournois, vie du club, beach...)
        foreach ($genericEventPatterns as $pattern) {
            $conditions[] = "{$prefix}`ManifestationTypée` LIKE ?";
            $bindings[] = $pattern;
        }

        // 2. Conditions basées sur les catégories du joueur (ex: Match L1)
        foreach ($categories as $cat) {
            $conditions[] = "{$prefix}`ManifestationTypée` LIKE ?";
            $bindings[] = '%' . $cat;
        }

        // 3. Récupération des équipes du joueur dans equipes_config pour filtrer ses entraînements
        $equipes = [];

        try {
            $inClause = implode(',', array_fill(0, count($categories), '?'));
            $stmtEq = \App\Core\Database::get()->prepare(
                "SELECT * FROM equipes_config WHERE is_active = 1 AND slug_colonne IN ($inClause)"
            );
            $stmtEq->execute($categories);
            $equipes = $stmtEq->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $equipes = [];
        }

        $saison = \App\Models\Saison::getActive();
        if ($saison) {
            try {
                $stmtPlayerTeams = \App\Core\Database::get()->prepare(
                    "SELECT ec.* FROM equipe_saison_joueur esj
                     JOIN joueur_snapshots js ON js.id = esj.snapshot_id
                     JOIN equipe_saison es ON es.id = esj.equipe_saison_id
                     JOIN equipes_config ec ON ec.id = es.equipe_id
                     WHERE js.id_joueur = ? AND es.saison_id = ? AND ec.is_active = 1"
                );
                $stmtPlayerTeams->execute([$userId, (int)$saison['id']]);
                $seasonEquipes = $stmtPlayerTeams->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $existingIds = array_column($equipes, 'id');
                foreach ($seasonEquipes as $seq) {
                    if (!in_array($seq['id'], $existingIds, true)) {
                        $equipes[] = $seq;
                    }
                }
            } catch (\Throwable) {
                // Ignore fallback issues
            }
        }

        $configuredTrainings = [];
        $categoriesWithConfiguredTrainings = [];

        foreach ($equipes as $eq) {
            if (!empty($eq['manifestation_filter'])) {
                $conditions[] = "{$prefix}`ManifestationTypée` LIKE ?";
                $bindings[] = '%' . $eq['manifestation_filter'] . '%';
            }

            if (!empty($eq['training_filter'])) {
                $associated = json_decode($eq['training_filter'], true) ?: [];
                if (!empty($associated)) {
                    $categoriesWithConfiguredTrainings[] = $eq['slug_colonne'];
                    foreach ($associated as $trainType) {
                        $configuredTrainings[] = $trainType;
                    }
                }
            }
        }

        // Entraînements configurés explicitement
        $configuredTrainings = array_unique($configuredTrainings);
        foreach ($configuredTrainings as $trainType) {
            $conditions[] = "{$prefix}`ManifestationTypée` = ?";
            $bindings[] = $trainType;

            $cleanType = str_replace(['Disponibilités - ', 'Présences - '], '', $trainType);
            $conditions[] = "{$prefix}`ManifestationTypée` LIKE ?";
            $bindings[] = '%' . $cleanType . '%';
        }

        // Fallback pour catégories n'ayant pas de training_filter spécifique
        foreach ($categories as $cat) {
            if (!in_array($cat, $categoriesWithConfiguredTrainings, true)) {
                $conditions[] = "({$prefix}`ManifestationTypée` LIKE '%Entra%' AND {$prefix}`ManifestationTypée` LIKE ?)";
                $bindings[] = '%' . $cat . '%';
            }
        }

        return [
            'conditions' => $conditions,
            'bindings'   => $bindings,
        ];
    }

    /**
     * Récupère les événements à venir filtrés par catégories et équipes du joueur.
     * N'affiche que les créneaux et entraînements pertinents et éligibles.
     *
     * @param int $userId ID du joueur
     * @param array $categories Liste des catégories du joueur (ex: ['DEP', 'L1', 'Adulte'])
     * @return array Liste des manifestations avec statut de participation
     */
    public static function getUpcomingForMember(int $userId, array $categories): array
    {
        if (empty($categories)) {
            return [];
        }

        $db = ExternalDatabase::get();

        $queryData = self::getMemberEventConditions($userId, $categories, 'm');

        if (empty($queryData['conditions'])) {
            return [];
        }

        $sql = "SELECT 
                    m.id_manifestation, 
                    m.ManifestationTypée, 
                    m.Date, 
                    m.Lieu, 
                    m.Statut,
                    m.Durée_créneau,
                    m.Nombre_terrain,
                    m.Commentaire,
                    p.Participation as user_status
                FROM Manifestation m
                LEFT JOIN Participation p ON m.id_manifestation = p.id_manifestation AND p.id_joueur = ?
                WHERE (" . implode(' OR ', $queryData['conditions']) . ")
                  AND m.Date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ORDER BY m.Date ASC";

        $bindings = array_merge([$userId], $queryData['bindings']);

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
