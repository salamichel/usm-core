<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\ExternalDatabase;

class EquipeConfig
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM equipes_config ORDER BY categorie ASC, ordre ASC")
            ->fetchAll();
    }

    public static function allActive(): array
    {
        return Database::get()
            ->query("SELECT * FROM equipes_config WHERE is_active = 1 ORDER BY categorie ASC, ordre ASC")
            ->fetchAll();
    }
    
    public static function getEquipesSlug(): array
    {
        $rows = ExternalDatabase::get()
            ->query("SELECT DISTINCT Mot FROM Mots_clef WHERE `Catégorie` = 'EquipeParEquipe' ORDER BY Mot ASC")
            ->fetchAll();
            
        return array_column($rows, 'Mot');
    }  

    public static function groupedByCategorie(): array
    {
        $rows   = Database::get()
            ->query("
                SELECT ec.* 
                FROM equipes_config ec
                LEFT JOIN categories_equipes ce ON ec.categorie = ce.nom
                WHERE ec.is_active = 1 
                ORDER BY ce.ordre ASC, ce.nom ASC, ec.ordre ASC
            ")
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['categorie']][] = $row;
        }
        return $result;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM equipes_config WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM equipes_config WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function findByCategory(string $categorie): array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM equipes_config WHERE categorie = ? AND is_active = 1 ORDER BY ordre ASC"
        );
        $stmt->execute([$categorie]);
        return $stmt->fetchAll();
    }

    public static function findByCategoryAndSlug(string $categorie, string $slug): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM equipes_config WHERE categorie = ? AND slug = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$categorie, $slug]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO equipes_config (slug_colonne, libelle, categorie, ordre, is_active, slug, team_filter, manifestation_filter, description, description_courte, type, hauteur_filet)
             VALUES (:slug_colonne, :libelle, :categorie, :ordre, :is_active, :slug, :team_filter, :manifestation_filter, :description, :description_courte, :type, :hauteur_filet)"
        );
        $stmt->execute([
            ':slug_colonne'         => $data['slug_colonne'],
            ':libelle'              => $data['libelle'],
            ':categorie'            => $data['categorie'],
            ':ordre'                => (int)($data['ordre'] ?? 0),
            ':is_active'            => (int)($data['is_active'] ?? 1),
            ':slug'                 => $data['slug'] ?? '',
            ':team_filter'          => $data['team_filter'] ?? null,
            ':manifestation_filter' => $data['manifestation_filter'] ?? null,
            ':description'          => $data['description'] ?? null,
            ':description_courte'   => $data['description_courte'] ?? null,
            ':type'                 => $data['type'] ?? null,
            ':hauteur_filet'        => $data['hauteur_filet'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE equipes_config
             SET slug_colonne         = :slug_colonne,
                 libelle              = :libelle,
                 categorie            = :categorie,
                 ordre                = :ordre,
                 is_active            = :is_active,
                 slug                 = :slug,
                 team_filter          = :team_filter,
                 manifestation_filter = :manifestation_filter,
                 description          = :description,
                 description_courte   = :description_courte,
                 type                 = :type,
                 hauteur_filet        = :hauteur_filet
             WHERE id = :id"
        )->execute([
            ':slug_colonne'         => $data['slug_colonne'],
            ':libelle'              => $data['libelle'],
            ':categorie'            => $data['categorie'],
            ':ordre'                => (int)($data['ordre'] ?? 0),
            ':is_active'            => (int)($data['is_active'] ?? 1),
            ':slug'                 => $data['slug'] ?? '',
            ':team_filter'          => $data['team_filter'] ?? null,
            ':manifestation_filter' => $data['manifestation_filter'] ?? null,
            ':description'          => $data['description'] ?? null,
            ':description_courte'   => $data['description_courte'] ?? null,
            ':type'                 => $data['type'] ?? null,
            ':hauteur_filet'        => $data['hauteur_filet'] ?? null,
            ':id'                   => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM equipes_config WHERE id = ?")->execute([$id]);
    }
}
