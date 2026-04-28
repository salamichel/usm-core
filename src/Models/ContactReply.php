<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ContactReply
{
    public static function create(int $contactId, string $fromEmail, string $replyText): int
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO contact_replies (contact_id, from_email, reply_text) VALUES (?, ?, ?)'
        );
        $stmt->execute([$contactId, $fromEmail, $replyText]);
        return (int)Database::get()->lastInsertId();
    }

    public static function findByContact(int $contactId): array
    {
        $stmt = Database::get()->prepare(
            'SELECT * FROM contact_replies WHERE contact_id = ? ORDER BY created_at ASC'
        );
        $stmt->execute([$contactId]);
        return $stmt->fetchAll() ?? [];
    }

    public static function getLatest(int $contactId): ?array
    {
        $stmt = Database::get()->prepare(
            'SELECT * FROM contact_replies WHERE contact_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$contactId]);
        return $stmt->fetch() ?: null;
    }
}
