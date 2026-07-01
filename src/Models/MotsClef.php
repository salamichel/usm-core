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
     * Retourne la liste des statuts valides pour un événement spécifique.
     * Basé sur le champ ManifestationTypée.
     *
     * @param array $event
     * @return array
     */
    public static function getValidStatusesForEvent(array $event): array
    {
        $type = $event['ManifestationTypée'] ?? '';

        $parts = explode(' ', trim($type));
        $firstTerm = strtolower($parts[0] ?? '');

        if (str_starts_with($firstTerm, 'disponibilit')) {
            return self::getByCategory('Participation_match');
        } elseif (str_starts_with($firstTerm, 'présence') || str_starts_with($firstTerm, 'presence')) {
            return self::getByCategory('Participation_entrai');
        }

        // Par défaut, on fusionne tout pour les cas inconnus
        return array_unique(array_merge(
            self::getByCategory('Participation_match'),
            self::getByCategory('Participation_select'),
            self::getByCategory('Participation_entrai')
        ));
    }
}
