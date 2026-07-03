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
}
