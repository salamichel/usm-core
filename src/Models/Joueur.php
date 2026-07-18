<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\ExternalDatabase;
use App\Models\EquipeConfig;
use PDO;

class Joueur
{
    /**
     * Authentifie un joueur depuis la base de données externe.
     * * @param string $email L'email (login) du joueur
     * @param string $password Le mot de passe
     * @return array|null Les données du joueur ou null si échec
     */
    public static function authenticate(string $email, string $password): ?array
    {
        $db = ExternalDatabase::get();

        // Utilisation stricte de requêtes préparées pour la sécurité
        $stmt = $db->prepare("SELECT * FROM Joueurs WHERE Mel LIKE ? AND mdp = ? LIMIT 1");

        // On reproduit le comportement '%$Id%' de l'ancien code de façon sécurisée
        $stmt->execute(['%' . $email . '%', $password]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Récupère la liste complète des joueurs pour l'annuaire de l'espace adhérent.
     * Remplace la logique de l'ancien fichier liste_joueurs_consulte.php.
     * * @return array Liste des joueurs
     */
    public static function getAll(): array
    {
        $db = ExternalDatabase::get();

        // On récupère les joueurs triés alphabétiquement depuis la base externe
        $stmt = $db->query("SELECT * FROM Joueurs ORDER BY Nom ASC, Prénom ASC");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un nouveau joueur avec un mot de passe aléatoire.
     * Remplace la requête d'ajout de l'ancien fichier.
     *
     * @param string $nom
     * @param string $prenom
     * @param array $extraData
     * @return int
     */
    public static function create(string $nom, string $prenom, array $extraData = []): int
    {
        $db = ExternalDatabase::get();
        $mdp = (string)rand(1000, 9999); // Génération d'un MDP aléatoire à 4 chiffres (comme l'ancien Round(RAND()*1000))

        // Valeurs par défaut obligatoires pour éviter les erreurs de mode strict (SQL 1364)
        $defaultData = [
            'Sexe' => 'M',
            'Adresse' => '',
            'CodePostal' => 33380,
            'Commune' => '',
            'Caracteristique' => '',
            'NLicence' => null,
            'Mel' => null,
            'Téléphone' => '',
            'DateNaissance' => null,
            'Equipe' => '',
            'Equipes' => '',
        ];

        // Charger dynamiquement les flags d'équipes et les initialiser à 0
        $categorySlugs = \App\Models\MotsClef::getByCategory('EquipeParEquipe');
        foreach ($categorySlugs as $slug) {
            $defaultData[$slug] = 0;
        }

        // Fusionner avec les données fournies
        $data = array_merge($defaultData, $extraData);

        $columns = ['Nom', 'Prénom', 'mdp'];
        $values = [strtoupper($nom), $prenom, $mdp];

        foreach ($data as $column => $val) {
            $columns[] = "`$column`";
            $values[] = $val;
        }

        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO Joueurs (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        return (int)$db->lastInsertId();
    }

    /**
     * Supprime un joueur.
     * * @param int $id
     */
    public static function delete(int $id): void
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("DELETE FROM Joueurs WHERE id_joueur = ?");
        $stmt->execute([$id]);
    }

    /**
     * Récupère un joueur par son ID.
     *
     * @param int $id L'ID du joueur
     * @return array|null Les données du joueur ou null si non trouvé
     */
    public static function findById(int $id): ?array
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("SELECT * FROM Joueurs WHERE id_joueur = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère un joueur par son email exact.
     *
     * @param string $email L'email du joueur
     * @return array|null Les données du joueur ou null si non trouvé
     */
    public static function findByEmail(string $email): ?array
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("SELECT * FROM Joueurs WHERE Mel = ? LIMIT 1");
        $stmt->execute([trim($email)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour un joueur.
     *
     * @param int $id L'ID du joueur
     * @param array $data Les données à mettre à jour (Prénom, Nom, Mel, etc.)
     */
    public static function update(int $id, array $data): void
    {
        $db = ExternalDatabase::get();

        $setClause = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setClause[] = "$column = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $sql = "UPDATE Joueurs SET " . implode(', ', $setClause) . " WHERE id_joueur = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Récupère les catégories/équipes actives d'un joueur (drapeaux = 1).
     * Utilisé pour filtrer les événements pertinents pour le joueur.
     *
     * @param int $id L'ID du joueur
     * @return array Liste des catégories (ex: ['DEP', 'L1', 'Adulte'])
     */
    public static function getCategories(int $id): array
    {
        $joueur = self::findById($id);
        if (!$joueur) {
            return [];
        }

        // Récupération des colonnes de catégories depuis la table CategorieEquipe
        $categoryColumns = EquipeConfig::getEquipesSlug();

        $categories = [];
        foreach ($categoryColumns as $col) {
            if (!empty($joueur[$col]) && (int)$joueur[$col] === 1) {
                $categories[] = $col;
            }
        }

        return $categories;
    }

    /**
     * Compte le nombre total de joueurs (avec filtres de recherche et d'équipe).
     */
    public static function count(?string $search = null, ?string $equipe = null): int
    {
        $db = ExternalDatabase::get();
        $sql = "SELECT COUNT(*) FROM Joueurs WHERE 1=1";
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= " AND (Nom LIKE ? OR Prénom LIKE ? OR Mel LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($equipe !== null && $equipe !== '') {
            // Liste blanche dynamique pour la sécurité
            $validColumns = array_merge(
                ['Compétition', 'Débutant', 'beach', 'Jeune', 'Adulte', 'Loisir'],
                \App\Models\MotsClef::getByCategory('EquipeParEquipe')
            );
            if (in_array($equipe, $validColumns, true)) {
                $sql .= " AND `$equipe` = 1";
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère les joueurs de façon paginée avec filtres de recherche et d'équipe.
     */
    public static function allPaginated(int $page, int $perPage, ?string $search = null, ?string $equipe = null): array
    {
        $db = ExternalDatabase::get();
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM Joueurs WHERE 1=1";
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= " AND (Nom LIKE ? OR Prénom LIKE ? OR Mel LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($equipe !== null && $equipe !== '') {
            // Liste blanche dynamique pour la sécurité
            $validColumns = array_merge(
                ['Compétition', 'Débutant', 'beach', 'Jeune', 'Adulte', 'Loisir'],
                \App\Models\MotsClef::getByCategory('EquipeParEquipe')
            );
            if (in_array($equipe, $validColumns, true)) {
                $sql .= " AND `$equipe` = 1";
            }
        }

        $sql .= " ORDER BY Nom ASC, Prénom ASC LIMIT ? OFFSET ?";

        $stmt = $db->prepare($sql);

        // bind values
        $paramIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex++, $param);
        }
        $stmt->bindValue($paramIndex++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offset, \PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Calcule le nombre de joueurs pour chaque catégorie d'équipe en une seule requête.
     */
    public static function getStatsByTeam(array $categories): array
    {
        $db = ExternalDatabase::get();
        if (empty($categories)) {
            return [];
        }

        $sumClauses = [];
        foreach ($categories as $cat) {
            // Sécurité : échapper le nom de la colonne
            $sumClauses[] = "SUM(CASE WHEN `$cat` = 1 THEN 1 ELSE 0 END) AS `$cat`";
        }

        $sql = "SELECT " . implode(', ', $sumClauses) . " FROM Joueurs";
        $stmt = $db->query($sql);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
}
