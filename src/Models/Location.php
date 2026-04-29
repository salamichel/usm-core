<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Location
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM locations ORDER BY name ASC")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM locations WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO locations (name, address, latitude, longitude)
             VALUES (:name, :address, :latitude, :longitude)"
        );
        $stmt->execute([
            ':name'      => $data['name'],
            ':address'   => $data['address'],
            ':latitude'  => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::get()->prepare(
            "UPDATE locations
             SET name = :name,
                 address = :address,
                 latitude = :latitude,
                 longitude = :longitude
             WHERE id = :id"
        )->execute([
            ':name'      => $data['name'],
            ':address'   => $data['address'],
            ':latitude'  => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':id'        => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM locations WHERE id = ?")->execute([$id]);
    }
}
