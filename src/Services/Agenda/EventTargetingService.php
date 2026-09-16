<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Core\Database;
use App\Core\ExternalDatabase;
use App\Models\EquipeConfig;
use App\Models\Joueur;
use App\Models\JoueurSnapshot;
use App\Models\MemberEmailPreference;
use App\Models\MotsClef;
use App\Models\Participation;
use App\Models\Saison;
use PDO;

class EventTargetingService
{
    /**
     * Récupère tous les événements éligibles pour un membre sur une plage de dates donnée,
     * avec son statut de participation actuel.
     *
     * @param int $userId ID du joueur
     * @param string|null $startDate Date de début (ex: '2026-08-30 00:00:00') ou null pour -1 jour
     * @param string|null $endDate Date de fin (ex: '2026-09-06 23:59:59') ou null pour illimité
     * @param bool $includeRecentCancelled Si vrai, inclut les événements annulés
     * @return array Liste des manifestations ciblées avec user_status
     */
    public static function getUpcomingForPlayer(
        int $userId,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $includeRecentCancelled = false
    ): array {
        $categories = Joueur::getCategories($userId);
        if (empty($categories)) {
            return [];
        }

        $queryData = Participation::getMemberEventConditions($userId, $categories, 'm');
        if (empty($queryData['conditions'])) {
            return [];
        }

        $db = ExternalDatabase::get();

        $sql = "SELECT 
                    m.id_manifestation, 
                    m.ManifestationTypée, 
                    m.Date, 
                    m.Durée_créneau,
                    m.Lieu, 
                    m.Nombre_terrain,
                    m.Creneau,
                    m.Commentaire,
                    m.Statut,
                    p.Participation as user_status
                FROM Manifestation m
                LEFT JOIN Participation p ON m.id_manifestation = p.id_manifestation AND p.id_joueur = ?
                WHERE (" . implode(' OR ', $queryData['conditions']) . ")";

        $bindings = array_merge([$userId], $queryData['bindings']);

        if ($startDate !== null) {
            $sql .= " AND m.Date >= ?";
            $bindings[] = $startDate;
        } else {
            $sql .= " AND m.Date >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        }

        if ($endDate !== null) {
            $sql .= " AND m.Date <= ?";
            $bindings[] = $endDate;
        }

        if (!$includeRecentCancelled) {
            $sql .= " AND (m.Statut IS NULL OR m.Statut NOT LIKE '%Annulé%')";
        }

        $sql .= " ORDER BY m.Date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Détermine si un joueur spécifique est éligible/concerné par un événement donné.
     *
     * @param int $userId ID du joueur
     * @param array|string $event Tableau de manifestation ou chaîne ManifestationTypée
     * @param array|null $playerCategories Catégories pré-chargées du joueur (optionnel)
     * @param array|null $playerEquipes Équipes pré-chargées du joueur (optionnel)
     * @return bool
     */
    public static function isPlayerConcernedByEvent(
        int $userId,
        array|string $event,
        ?array $playerCategories = null,
        ?array $playerEquipes = null
    ): bool {
        $manifType = is_array($event)
            ? ($event['ManifestationTypée'] ?? $event['manifestation_type'] ?? '')
            : (string)$event;

        if ($manifType === '') {
            return false;
        }

        // 1. Événements génériques (tournois, vie du club, beach...) -> Tous les membres
        foreach (Participation::getGenericEventPatterns() as $pattern) {
            $cleanPattern = trim($pattern, '%');
            if (stripos($manifType, $cleanPattern) !== false) {
                return true;
            }
        }

        $categories = $playerCategories ?? Joueur::getCategories($userId);
        if (empty($categories)) {
            return false;
        }

        // 2. Vérification directe sur les catégories du joueur (ex: 'Match L1' ou 'DEP')
        foreach ($categories as $cat) {
            if ($cat !== '' && (str_ends_with($manifType, $cat) || str_contains($manifType, ' ' . $cat))) {
                return true;
            }
        }

        // 3. Récupération des équipes de l'adhérent (via cache fourni ou equipes_config et equipe_saison_joueur)
        $equipes = $playerEquipes ?? self::getPlayerEquipesConfig($userId, $categories);

        // 4. Analyse selon le type (Match vs Entraînement vs Autre)
        $parts = explode(' - ', $manifType, 3);
        $type = $parts[1] ?? '';
        $isMatch = (mb_strtolower($type) === 'match');

        if ($isMatch) {
            foreach ($equipes as $eq) {
                $filter = $eq['manifestation_filter'] ?? '';
                if ($filter !== '' && str_contains($manifType, $filter)) {
                    return true;
                }
            }
            return false;
        }

        // 5. Entraînements configurés
        $categoriesWithConfiguredTrainings = [];
        foreach ($equipes as $eq) {
            if (!empty($eq['training_filter'])) {
                $associated = json_decode($eq['training_filter'], true) ?: [];
                if (!empty($associated)) {
                    $categoriesWithConfiguredTrainings[] = $eq['slug_colonne'];
                    foreach ($associated as $trainType) {
                        if ($manifType === $trainType) {
                            return true;
                        }
                        $cleanType = str_replace(['Disponibilités - ', 'Présences - '], '', $trainType);
                        if ($cleanType !== '' && str_contains($manifType, $cleanType)) {
                            return true;
                        }
                    }
                }
            }
        }

        // 6. Fallback pour catégories sans training_filter explicite
        $isTraining = (stripos($manifType, 'entra') !== false);
        if ($isTraining) {
            foreach ($categories as $cat) {
                if (!in_array($cat, $categoriesWithConfiguredTrainings, true)) {
                    if (stripos($manifType, $cat) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Récupère la liste des adhérents concernés par la création d'un événement ET abonnés aux notifications email.
     *
     * @param array $event Événement brut de la table Manifestation
     * @param int $saisonId ID de la saison active
     * @return array Liste d'éléments ['player_db' => array, 'target_label' => string]
     */
    public static function getEligibleAndSubscribedPlayersForCreation(array $event, int $saisonId): array
    {
        $manifType = $event['ManifestationTypée'] ?? $event['manifestation_type'] ?? '';
        $parts = explode(' - ', $manifType, 3);
        $type = $parts[1] ?? '';
        $title = $parts[2] ?? $manifType;
        $isMatch = (mb_strtolower($type) === 'match');

        $allPlayerSnaps = JoueurSnapshot::findBySaison($saisonId);
        if (empty($allPlayerSnaps)) {
            return [];
        }

        $eligibleRecipients = [];

        if ($isMatch) {
            $activeTeams = EquipeConfig::allActive();
            $matchingTeams = [];
            foreach ($activeTeams as $team) {
                $filter = $team['manifestation_filter'];
                if ($filter && str_contains($manifType, $filter)) {
                    $matchingTeams[] = $team;
                }
            }

            if (empty($matchingTeams)) {
                return [];
            }

            foreach ($matchingTeams as $team) {
                $es = \App\Models\EquipeSaison::findBySaisonAndEquipe($saisonId, $team['id']);
                if (!$es) {
                    continue;
                }

                $teamPlayers = \App\Models\EquipeSaisonJoueur::findByEquipeSaison($es['id']);
                foreach ($teamPlayers as $tp) {
                    $playerId = (int)$tp['id_joueur'];
                    if (MemberEmailPreference::isSubscribed($playerId, $saisonId, 'match')) {
                        $playerDb = Joueur::findById($playerId);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $eligibleRecipients[$playerId] = [
                                'player_db'    => $playerDb,
                                'target_label' => $team['libelle']
                            ];
                        }
                    }
                }
            }
        } else {
            $trainingTypes = MotsClef::getTrainingTypes();
            $isTraining = false;
            $matchedTrainingType = null;

            foreach ($trainingTypes as $tt) {
                if ($manifType === $tt) {
                    $isTraining = true;
                    $matchedTrainingType = $tt;
                    break;
                }
            }

            if ($isTraining && $matchedTrainingType !== null) {
                foreach ($allPlayerSnaps as $snap) {
                    $playerId = (int)$snap['id_joueur'];
                    if (self::isPlayerConcernedByEvent($playerId, $event)) {
                        if (MemberEmailPreference::isSubscribed($playerId, $saisonId, $matchedTrainingType)) {
                            $playerDb = Joueur::findById($playerId);
                            if ($playerDb && !empty($playerDb['Mel'])) {
                                $eligibleRecipients[$playerId] = [
                                    'player_db'    => $playerDb,
                                    'target_label' => $title
                                ];
                            }
                        }
                    }
                }
            } else {
                // Autres événements (Vie du club & Tournois) -> Tous les adhérents abonnés à club_life
                foreach ($allPlayerSnaps as $snap) {
                    $playerId = (int)$snap['id_joueur'];
                    if (MemberEmailPreference::isSubscribed($playerId, $saisonId, 'club_life')) {
                        $playerDb = Joueur::findById($playerId);
                        if ($playerDb && !empty($playerDb['Mel'])) {
                            $eligibleRecipients[$playerId] = [
                                'player_db'    => $playerDb,
                                'target_label' => 'Tous les adhérents'
                            ];
                        }
                    }
                }
            }
        }

        return array_values($eligibleRecipients);
    }

    /**
     * Récupère la configuration des équipes pour un joueur donné.
     *
     * @param int $userId
     * @param array $categories
     * @return array
     */
    public static function getPlayerEquipesConfig(int $userId, array $categories): array
    {
        $equipes = [];

        if (!empty($categories)) {
            try {
                $inClause = implode(',', array_fill(0, count($categories), '?'));
                $stmtEq = Database::get()->prepare(
                    "SELECT * FROM equipes_config WHERE is_active = 1 AND slug_colonne IN ($inClause)"
                );
                $stmtEq->execute($categories);
                $equipes = $stmtEq->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $equipes = [];
            }
        }

        $saison = Saison::getActive();
        if ($saison) {
            try {
                $stmtPlayerTeams = Database::get()->prepare(
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
            }
        }

        return $equipes;
    }

    /**
     * Pré-charge en mémoire les équipes actives de l'ensemble des joueurs en seulement 2 requêtes SQL locales.
     * Élimine les milliers de requêtes PDO lors de la génération de l'agenda cross-table.
     *
     * @param array<int, array> $playerCategories Mapping [id_joueur => [slug1, slug2, ...]]
     * @return array<int, array> Mapping [id_joueur => [equipe1, equipe2, ...]]
     */
    public static function preloadAllPlayersEquipes(array $playerCategories): array
    {
        $playersEquipes = [];
        try {
            $dbLoc = Database::get();
            $allEquipesConfig = $dbLoc->query("SELECT * FROM equipes_config WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
            $equipesBySlug = [];
            $equipesById = [];
            foreach ($allEquipesConfig as $eq) {
                if (!empty($eq['slug_colonne'])) {
                    $equipesBySlug[$eq['slug_colonne']] = $eq;
                }
                $equipesById[(int)$eq['id']] = $eq;
            }

            $seasonEquipesByPlayer = [];
            $saison = Saison::getActive();
            $saisonId = $saison ? (int)$saison['id'] : 0;
            if ($saisonId > 0) {
                $stmt = $dbLoc->prepare(
                    "SELECT js.id_joueur, es.equipe_id
                     FROM equipe_saison_joueur esj
                     JOIN joueur_snapshots js ON js.id = esj.snapshot_id
                     JOIN equipe_saison es ON es.id = esj.equipe_saison_id
                     WHERE es.saison_id = ?"
                );
                $stmt->execute([$saisonId]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $jId = (int)$row['id_joueur'];
                    $eqId = (int)$row['equipe_id'];
                    if (isset($equipesById[$eqId])) {
                        $seasonEquipesByPlayer[$jId][] = $equipesById[$eqId];
                    }
                }
            }

            foreach ($playerCategories as $jid => $cats) {
                $eqs = [];
                foreach ($cats as $cat) {
                    if (isset($equipesBySlug[$cat])) {
                        $eqs[$equipesBySlug[$cat]['id']] = $equipesBySlug[$cat];
                    }
                }
                if (isset($seasonEquipesByPlayer[$jid])) {
                    foreach ($seasonEquipesByPlayer[$jid] as $seq) {
                        $eqs[$seq['id']] = $seq;
                    }
                }
                $playersEquipes[$jid] = array_values($eqs);
            }
        } catch (\Throwable $e) {
            error_log('preloadAllPlayersEquipes failed: ' . $e->getMessage());
        }

        return $playersEquipes;
    }
}

