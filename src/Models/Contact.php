<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Contact
{
    public static function create(array $data): int
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO contacts (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['subject'],
            $data['message'],
            $data['status'] ?? 'new',
        ]);
        return (int)Database::get()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM contacts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(string $status = 'new'): array
    {
        if ($status === 'all') {
            $stmt = Database::get()->prepare('SELECT * FROM contacts ORDER BY created_at DESC');
            $stmt->execute();
        } else {
            $stmt = Database::get()->prepare('SELECT * FROM contacts WHERE status = ? ORDER BY created_at DESC');
            $stmt->execute([$status]);
        }
        return $stmt->fetchAll() ?? [];
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::get()->prepare('UPDATE contacts SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::get()->prepare('DELETE FROM contacts WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function countByStatus(string $status): int
    {
        $stmt = Database::get()->prepare('SELECT COUNT(*) as count FROM contacts WHERE status = ?');
        $stmt->execute([$status]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
}
