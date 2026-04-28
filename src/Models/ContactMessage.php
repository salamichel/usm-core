<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ContactMessage
{
    public static function all(): array
    {
        return Database::get()
            ->query("SELECT * FROM contact_messages ORDER BY created_at DESC")
            ->fetchAll();
    }

    public static function unread(): array
    {
        return Database::get()
            ->query("SELECT * FROM contact_messages WHERE read_at IS NULL ORDER BY created_at DESC")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM contact_messages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::get();
        $stmt = $db->prepare(
            "INSERT INTO contact_messages (name, email, subject, message, phone)
             VALUES (:name, :email, :subject, :message, :phone)"
        );
        $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $data['email'],
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':phone'   => $data['phone'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function markAsRead(int $id): void
    {
        Database::get()
            ->prepare("UPDATE contact_messages SET read_at = NOW() WHERE id = ?")
            ->execute([$id]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
    }
}
