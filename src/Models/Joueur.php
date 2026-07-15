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
     * * @param string $nom
     * @param string $prenom
     */
    public static function create(string $nom, string $prenom): void
    {
        $db = ExternalDatabase::get();
        $mdp = rand(1000, 9999); // Génération d'un MDP aléatoire à 4 chiffres (comme l'ancien Round(RAND()*1000))
        
        $stmt = $db->prepare("INSERT INTO Joueurs (Nom, Prénom, mdp) VALUES (?, ?, ?)");
        $stmt->execute([strtoupper($nom), $prenom, $mdp]);
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
}