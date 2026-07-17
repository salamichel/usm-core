<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\ExternalDatabase;

class MotsClef
{
    /**
     * Récupère les mots-clés d'une catégorie donnée.
     *
     * @param string $category
     * @return array
     */
    public static function getByCategory(string $category): array
    {
        try {
            $db = ExternalDatabase::get();
            $stmt = $db->prepare("SELECT Mot FROM Mots_clef WHERE Catégorie = ? ORDER BY Mot");
            $stmt->execute([$category]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Récupère tous les mots-clés.
     */
    public static function all(): array
    {
        try {
            $db = ExternalDatabase::get();
            $stmt = $db->query("SELECT * FROM Mots_clef ORDER BY Catégorie ASC, Mot ASC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Récupère les mots-clés de façon paginée, éventuellement filtrés par catégorie.
     */
    public static function allPaginated(int $page, int $perPage, ?string $category = null): array
    {
        try {
            $db = ExternalDatabase::get();
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT * FROM Mots_clef";
            $params = [];

            if ($category) {
                $sql .= " WHERE Catégorie = ?";
                $params[] = $category;
            }

            $sql .= " ORDER BY Catégorie ASC, Mot ASC LIMIT ? OFFSET ?";

            $stmt = $db->prepare($sql);

            // Liaison sécurisée des entiers pour LIMIT/OFFSET
            $paramIndex = 1;
            foreach ($params as $param) {
                $stmt->bindValue($paramIndex++, $param);
            }
            $stmt->bindValue($paramIndex++, $perPage, \PDO::PARAM_INT);
            $stmt->bindValue($paramIndex++, $offset, \PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Compte le nombre total de mots-clés, éventuellement filtrés par catégorie.
     */
    public static function count(?string $category = null): int
    {
        try {
            $db = ExternalDatabase::get();
            $sql = "SELECT COUNT(*) FROM Mots_clef";
            $params = [];

            if ($category) {
                $sql .= " WHERE Catégorie = ?";
                $params[] = $category;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Recherche un mot-clé par son ID.
     */
    public static function find(int $id): ?array
    {
        try {
            $db = ExternalDatabase::get();
            $stmt = $db->prepare("SELECT * FROM Mots_clef WHERE id_mot_clef = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Crée un nouveau mot-clé.
     */
    public static function create(array $data): int
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("
            INSERT INTO Mots_clef (Catégorie, Mot) 
            VALUES (:categorie, :mot)
        ");
        $stmt->execute([
            'categorie' => trim($data['Catégorie']),
            'mot'       => trim($data['Mot']),
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Met à jour un mot-clé existant.
     */
    public static function update(int $id, array $data): void
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("
            UPDATE Mots_clef 
            SET Catégorie = :categorie, Mot = :mot 
            WHERE id_mot_clef = :id
        ");
        $stmt->execute([
            'id'        => $id,
            'categorie' => trim($data['Catégorie']),
            'mot'       => trim($data['Mot']),
        ]);
    }

    /**
     * Supprime un mot-clé.
     */
    public static function delete(int $id): void
    {
        $db = ExternalDatabase::get();
        $stmt = $db->prepare("DELETE FROM Mots_clef WHERE id_mot_clef = ?");
        $stmt->execute([$id]);
    }

    /**
     * Récupère toutes les catégories uniques.
     */
    public static function getCategories(): array
    {
        try {
            $db = ExternalDatabase::get();
            $stmt = $db->query("SELECT DISTINCT Catégorie FROM Mots_clef WHERE Catégorie != '' ORDER BY Catégorie ASC");
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Récupère les mots de la catégorie ManifestationTypée correspondants à des entraînements.
     *
     * @return array
     */
    public static function getTrainingTypes(): array
    {
        try {
            $db = ExternalDatabase::get();
            $stmt = $db->prepare("
                SELECT Mot FROM Mots_clef 
                WHERE Catégorie = 'ManifestationTypée' 
                  AND (Mot LIKE '%Entrainement%' OR Mot LIKE '%BEACH%')
                ORDER BY Mot
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
