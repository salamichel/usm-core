<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class SiteConfig
{
    /**
     * Retourne toutes les clés de configuration sous la forme [cle => valeur].
     * Tolère l'absence de la table (ex. avant migration) en retournant [].
     */
    public static function all(): array
    {
        try {
            $rows = Database::get()
                ->query("SELECT cle, valeur FROM site_config")
                ->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[$row['cle']] = $row['valeur'];
        }
        return $out;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::get()->prepare("SELECT valeur FROM site_config WHERE cle = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    /**
     * Upsert d'une seule clé.
     */
    public static function set(string $key, ?string $value): void
    {
        Database::get()->prepare(
            "INSERT INTO site_config (cle, valeur) VALUES (:cle, :valeur)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
        )->execute([':cle' => $key, ':valeur' => $value]);
    }

    /**
     * Upsert massif depuis un tableau associatif.
     */
    public static function setMany(array $data): void
    {
        $db = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO site_config (cle, valeur) VALUES (:cle, :valeur)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
        );
        $db->beginTransaction();
        try {
            foreach ($data as $key => $value) {
                $stmt->execute([':cle' => $key, ':valeur' => $value]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function getModelList(string $key, array $default): array
    {
        $raw = self::get($key);
        if ($raw === null) {
            return $default;
        }
        $lines = array_filter(array_map('trim', explode("\n", $raw)));
        return $lines ?: $default;
    }
}
